<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Edexcel\Services\LearningModuleService;
use Edexcel\Services\LessonAuthoringService;
use Edexcel\Services\LessonInsightService;
use Edexcel\Services\LessonResultBuilder;
use Edexcel\Services\LessonVersionService;
use Edexcel\Services\OnlineLessonService;
use Edexcel\Services\QuestionPool;
use Edexcel\Services\RecordingService;

/**
 * @param array<string,mixed> $post
 * @return list<string>
 */
function campus_posted_mcq_choices(array $post): array
{
    if (isset($post['choices']) && is_array($post['choices'])) {
        return array_map(static fn($v): string => (string)$v, $post['choices']);
    }
    return [
        (string)($post['choice_a'] ?? ''),
        (string)($post['choice_b'] ?? ''),
        (string)($post['choice_c'] ?? ''),
        (string)($post['choice_d'] ?? ''),
    ];
}

require_staff();
ensure_recordings_schema($pdo);
ensure_online_lesson_schema($pdo);

$isAdmin = is_admin();
$teacherId = (int)($_SESSION['teacher_id'] ?? 0);
$userId = (int)($_SESSION['user_id'] ?? 0);
$error = '';
$success = (string)($_SESSION['online_lesson_success'] ?? '');
unset($_SESSION['online_lesson_success']);
$recordings = new RecordingService($pdo);
$lessons = new OnlineLessonService($pdo);
$authoring = new LessonAuthoringService($pdo, $lessons);
$modules = new LearningModuleService($pdo);

$timetableId = (int)($_GET['lesson'] ?? $_POST['timetable_id'] ?? 0);
$activityId = (int)($_GET['activity'] ?? 0);
$pageItemId = (int)($_GET['page'] ?? 0);
$tab = strtolower(trim((string)($_GET['tab'] ?? 'build')));
if (!in_array($tab, ['build', 'grade', 'results'], true)) {
    $tab = 'build';
}

$from = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_GET['from'] ?? '')) ? (string)$_GET['from'] : date('Y-m-d', strtotime('-21 days'));
$to = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_GET['to'] ?? '')) ? (string)$_GET['to'] : date('Y-m-d');

$staffLesson = null;
$onlineLesson = null;
$items = [];
$activity = null;
$questions = [];
$ungraded = [];
$classResults = null;
$copyTargets = [];
$questionBanks = [];
$lessonObjectives = [];
$objectiveLinks = [];
$criteriaByQuestion = [];
$bankSearch = ['rows' => [], 'total' => 0];
$resourceRows = [];
$lessonSubmissions = [];
$planIssueRows = [];
$planDuration = ['state' => 'unknown', 'message' => ''];

if ($timetableId > 0) {
    $staffLesson = $recordings->lessonForStaff($timetableId, $teacherId, $isAdmin);
    if (!$staffLesson) {
        $error = 'You cannot edit a video lesson for that class.';
        $timetableId = 0;
    } elseif (!OnlineLessonService::supportsDeliveryMode((string)($staffLesson['delivery_mode'] ?? 'physical'))) {
        $error = 'That class cannot have a video lesson.';
        $timetableId = 0;
        $staffLesson = null;
    } else {
        try {
            $recording = $recordings->activeForLesson($timetableId);
            $onlineLesson = $lessons->getOrCreateForTimetable($staffLesson, $recording, $userId);
            $items = $lessons->items((int)$onlineLesson['id']);
            $ungraded = $lessons->ungradedEssays((int)$onlineLesson['id']);
        } catch (Throwable $e) {
            $error = $e->getMessage();
            $timetableId = 0;
            $staffLesson = null;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $onlineLesson) {
    try {
        if (!verify_csrf_token((string)($_POST['csrf_token'] ?? ''))) {
            throw new RuntimeException('Invalid security token.');
        }
        $action = (string)($_POST['action'] ?? '');
        $lessonPk = (int)$onlineLesson['id'];

        if ($action === 'save_meta') {
            $lessons->saveMeta(
                $lessonPk,
                (string)($_POST['title'] ?? ''),
                (string)($_POST['intro'] ?? ''),
                !empty($_POST['sequential']),
                (int)($_POST['min_watch_percent'] ?? 80),
                !empty($_POST['available_after_class']),
                (int)($_POST['close_after_days'] ?? 0)
            );
            $success = 'Lesson settings saved.';
        } elseif ($action === 'save_plan') {
            $authoring->savePlan($lessonPk, $_POST);
            $success = 'Lesson plan saved. It is still a draft until you publish.';
        } elseif ($action === 'add_section') {
            $minutes = trim((string)($_POST['estimated_minutes'] ?? ''));
            $authoring->addSection($lessonPk, (string)($_POST['title'] ?? ''), $minutes === '' ? null : (int)$minutes);
            $success = 'Section added.';
        } elseif ($action === 'rename_section') {
            $minutes = trim((string)($_POST['estimated_minutes'] ?? ''));
            $authoring->renameSection($lessonPk, (int)($_POST['section_id'] ?? 0), (string)($_POST['title'] ?? ''), $minutes === '' ? null : (int)$minutes);
            $success = 'Section saved.';
        } elseif ($action === 'delete_section') {
            $authoring->deleteSection($lessonPk, (int)($_POST['section_id'] ?? 0));
            $success = 'Section removed. Its activities are still in the lesson.';
        } elseif ($action === 'move_section') {
            $authoring->moveSection($lessonPk, (int)($_POST['section_id'] ?? 0), (string)($_POST['direction'] ?? 'down'));
            $success = 'Section moved.';
        } elseif ($action === 'assign_section') {
            $section = (int)($_POST['section_id'] ?? 0);
            $authoring->assignItem($lessonPk, (int)($_POST['item_id'] ?? 0), $section > 0 ? $section : null);
            $success = 'Activity moved to that section.';
        } elseif ($action === 'generate_plan') {
            $count = $authoring->generateDraft($lessonPk);
            $success = 'Draft structure created with ' . $count . ' activities. Review it, then add the questions before publishing.';
        } elseif ($action === 'save_template') {
            $authoring->saveTemplate($lessonPk, $userId, (string)($_POST['template_title'] ?? ''));
            $success = 'Template saved. It does not include student progress or class recordings.';
        } elseif ($action === 'apply_template') {
            $count = $authoring->applyTemplate($lessonPk, (int)($_POST['template_id'] ?? 0), $userId, $isAdmin);
            $success = 'Template applied as a draft with ' . $count . ' activities. Review it before publishing.';
        } elseif ($action === 'duplicate_section') {
            $count = $authoring->duplicateSection($lessonPk, (int)($_POST['section_id'] ?? 0));
            $success = 'Section copied with ' . $count . ' activities. The original was not changed.';
        } elseif ($action === 'duplicate_item') {
            $authoring->duplicateItem($lessonPk, (int)($_POST['item_id'] ?? 0));
            $success = 'Activity copied. The original was not changed.';
        } elseif ($action === 'add_content') {
            $kind = strtolower(trim((string)($_POST['content_type'] ?? 'mcq')));
            $after = (int)($_POST['after_item_id'] ?? 0);
            if ($kind === 'page') {
                $item = $lessons->addPageAfter($lessonPk, $after, 'Notes', '');
                header('Location: ' . campus_online_lesson_url($timetableId) . '&page=' . (int)$item['id']);
                exit;
            }
            if (in_array($kind, ['mcq', 'essay', 'short', 'exam', 'assignment', 'homework'], true)) {
                $item = $lessons->addActivityAfter($lessonPk, $after, $kind, '');
                header('Location: ' . campus_online_lesson_url($timetableId, (int)$item['activity_id']));
                exit;
            }
            throw new RuntimeException('Choose a content type.');
        } elseif ($action === 'add_objective') {
            $modules->addObjective($lessonPk, (string)($_POST['body'] ?? ''));
            $success = 'Objective added.';
        } elseif ($action === 'rename_objective') {
            $modules->renameObjective($lessonPk, (int)($_POST['objective_id'] ?? 0), (string)($_POST['body'] ?? ''));
            $success = 'Objective updated.';
        } elseif ($action === 'delete_objective') {
            $modules->deleteObjective($lessonPk, (int)($_POST['objective_id'] ?? 0));
            $success = 'Objective removed.';
        } elseif ($action === 'move_objective') {
            $modules->moveObjective($lessonPk, (int)($_POST['objective_id'] ?? 0), (string)($_POST['direction'] ?? 'down'));
            $success = 'Objective order updated.';
        } elseif ($action === 'duplicate_question') {
            $newId = $lessons->duplicateQuestion((int)($_POST['question_id'] ?? 0), $lessonPk);
            $modules->copyQuestionExtras((int)($_POST['question_id'] ?? 0), $newId);
            $success = 'Question copied. The original was not changed.';
        } elseif ($action === 'add_criterion') {
            $modules->addCriterion((int)($_POST['question_id'] ?? 0), (string)($_POST['label'] ?? ''), (float)($_POST['marks'] ?? 0));
            $success = 'Mark-scheme criterion added.';
        } elseif ($action === 'delete_criterion') {
            $modules->deleteCriterion((int)($_POST['question_id'] ?? 0), (int)($_POST['criterion_id'] ?? 0));
            $success = 'Criterion removed.';
        } elseif ($action === 'insert_selected') {
            $activityId = (int)($_POST['activity_id'] ?? $activityId);
            $ids = is_array($_POST['bank_item_ids'] ?? null) ? $_POST['bank_item_ids'] : [];
            $added = $lessons->insertBankItems($activityId, $ids, $userId, $isAdmin);
            $success = 'Added ' . $added . ' copied question' . ($added === 1 ? '' : 's') . '. The bank was not changed.';
        } elseif ($action === 'review_submission') {
            $submissionId = (int)($_POST['submission_id'] ?? 0);
            $markRaw = trim((string)($_POST['marks'] ?? ''));
            $modules->reviewSubmission(
                $lessonPk,
                $submissionId,
                (string)($_POST['review'] ?? 'mark'),
                $markRaw === '' ? null : (float)$markRaw,
                (string)($_POST['comment'] ?? ''),
                (float)($_POST['max_marks'] ?? 0)
            );
            $success = 'Submission updated.';
            $tab = 'grade';
        } elseif ($action === 'save_resource_url') {
            $modules->saveUrlResource($userId, (string)($_POST['title'] ?? ''), (string)($_POST['resource_type'] ?? 'url'), (string)($_POST['url'] ?? ''));
            $success = 'Resource saved.';
        } elseif ($action === 'save_resource_file') {
            $modules->saveFileResource($userId, (string)($_POST['title'] ?? ''), (string)($_POST['resource_type'] ?? 'document'), $_FILES['resource_file'] ?? []);
            $success = 'Resource uploaded.';
        } elseif ($action === 'archive_resource') {
            $modules->archiveResource((int)($_POST['resource_id'] ?? 0), $userId, $isAdmin, empty($_POST['restore']));
            $success = empty($_POST['restore']) ? 'Resource archived.' : 'Resource restored.';
        } elseif ($action === 'use_resource') {
            $resource = $modules->resource((int)($_POST['resource_id'] ?? 0));
            if (!$resource || (!$isAdmin && (int)$resource['owner_user_id'] !== $userId) || !empty($resource['archived'])) {
                throw new RuntimeException('That resource was not found.');
            }
            if (!empty($resource['url'])) {
                $link = $lessons->saveExternalLink($lessonPk, 0, (int)($_POST['after_item_id'] ?? 0), (string)$resource['title'], (string)$resource['url'], true, true);
                $pdo->prepare('UPDATE online_lesson_items SET resource_id = ? WHERE id = ? AND lesson_id = ?')
                    ->execute([(int)$resource['id'], (int)$link['id'], $lessonPk]);
            } else {
                $before = array_column($lessons->items($lessonPk), 'id');
                $lessons->addPageAfter($lessonPk, (int)($_POST['after_item_id'] ?? 0), (string)$resource['title'], 'Open the attached file.');
                $newId = 0;
                foreach ($lessons->items($lessonPk) as $candidate) {
                    if (!in_array((int)$candidate['id'], $before, true)) {
                        $newId = (int)$candidate['id'];
                        break;
                    }
                }
                if ($newId > 0) {
                    $pdo->prepare("UPDATE online_lesson_items SET item_type = 'resource', resource_id = ? WHERE id = ? AND lesson_id = ?")
                        ->execute([(int)$resource['id'], $newId, $lessonPk]);
                }
            }
            $success = 'Resource added to this lesson. The library copy was not changed.';
        } elseif ($action === 'archive') {
            $authoring->setArchived($lessonPk, true);
            $success = 'Lesson archived. Students can still open it until you unpublish.';
        } elseif ($action === 'unarchive') {
            $authoring->setArchived($lessonPk, false);
            $success = 'Lesson restored to the active list.';
        } elseif ($action === 'publish') {
            $issues = [];
            foreach ((new LessonInsightService($pdo))->qualityReport($lessonPk) as $issue) {
                if ($issue['severity'] === 'high') {
                    $issues[] = $issue['message'];
                }
            }
            if ($issues !== []) {
                throw new RuntimeException(implode(' ', $issues));
            }
            $lessons->publish($lessonPk, true);
            try {
                (new LessonVersionService($pdo))->createVersion($lessonPk, $userId, 'published');
            } catch (Throwable $e) {
                error_log('lesson version on publish: ' . $e->getMessage());
            }
            $success = 'Students can now open this sequenced lesson.';
            if (!empty($_POST['notify_students'])) {
                $n = $lessons->notifyPublished($onlineLesson, $staffLesson);
                $success .= $n > 0 ? ' Notified ' . $n . ' student' . ($n === 1 ? '' : 's') . '.' : ' No enrolled students to notify.';
            }
        } elseif ($action === 'unpublish') {
            $lessons->publish($lessonPk, false);
            $success = 'Lesson hidden from students. They will see the ordinary recording page.';
        } elseif ($action === 'sync') {
            $recording = $recordings->activeForLesson($timetableId);
            if (!$recording) {
                throw new RuntimeException('Upload class recording clips first.');
            }
            $added = $lessons->syncVideoItems($lessonPk, (int)$recording['id']);
            $success = $added > 0 ? "Added {$added} new video clip(s) to the lesson." : 'All recording clips are already in the lesson.';
        } elseif ($action === 'rename_item') {
            $lessons->renameItem((int)($_POST['item_id'] ?? 0), (string)($_POST['title'] ?? ''));
            $success = 'Item renamed.';
        } elseif ($action === 'move_item') {
            $lessons->moveItem($lessonPk, (int)($_POST['item_id'] ?? 0), (string)($_POST['direction'] ?? 'down'));
            $success = 'Lesson order updated.';
        } elseif ($action === 'add_activity') {
            $item = $lessons->addActivityAfter(
                $lessonPk,
                (int)($_POST['after_item_id'] ?? 0),
                (string)($_POST['activity_type'] ?? 'mcq'),
                (string)($_POST['title'] ?? '')
            );
            header('Location: ' . campus_online_lesson_url($timetableId, (int)$item['activity_id']));
            exit;
        } elseif ($action === 'save_link') {
            $lessons->saveExternalLink(
                $lessonPk,
                (int)($_POST['item_id'] ?? 0),
                (int)($_POST['after_item_id'] ?? 0),
                (string)($_POST['title'] ?? ''),
                (string)($_POST['link_url'] ?? ''),
                !empty($_POST['open_new_tab']),
                !empty($_POST['required'])
            );
            $success = 'External link saved.';
        } elseif ($action === 'add_page') {
            $item = $lessons->addPageAfter(
                $lessonPk,
                (int)($_POST['after_item_id'] ?? 0),
                (string)($_POST['title'] ?? 'Introduction'),
                (string)($_POST['body'] ?? '')
            );
            header('Location: ' . campus_online_lesson_url($timetableId) . '&page=' . (int)$item['id']);
            exit;
        } elseif ($action === 'delete_item') {
            $lessons->deleteItem($lessonPk, (int)($_POST['item_id'] ?? 0));
            $success = 'Removed from the lesson.';
        } elseif ($action === 'save_page') {
            $lessons->savePageBody((int)($_POST['item_id'] ?? 0), (string)($_POST['title'] ?? ''), (string)($_POST['body'] ?? ''));
            $success = 'Page saved.';
        } elseif ($action === 'save_activity') {
            $pass = trim((string)($_POST['pass_percent'] ?? ''));
            $dueRaw = trim((string)($_POST['due_at'] ?? ''));
            $maxRaw = trim((string)($_POST['max_marks'] ?? ''));
            $workType = OnlineLessonService::isSubmissionActivity((string)($_POST['activity_type'] ?? ''));
            $lessons->saveActivity(
                (int)($_POST['activity_id'] ?? 0),
                (string)($_POST['title'] ?? ''),
                (string)($_POST['instructions'] ?? ''),
                (string)($_POST['activity_type'] ?? 'mcq'),
                $pass === '' ? 0 : (int)$pass,
                (int)($_POST['max_attempts'] ?? 0),
                !empty($_POST['show_correct']),
                !empty($_POST['shuffle_choices']),
                !empty($_POST['shuffle_questions']),
                $dueRaw === '' ? null : LearningModuleService::normalizeDueAt($dueRaw),
                $maxRaw === '' ? null : (float)$maxRaw,
                $workType ? !empty($_POST['allow_text']) : true,
                $workType ? !empty($_POST['allow_file']) : true
            );
            if (!$workType) {
                $lessons->savePoolSettings(
                    (int)($_POST['activity_id'] ?? 0),
                    (int)($_POST['draw_count'] ?? 0),
                    (string)($_POST['scoring_rule'] ?? ''),
                    (int)($_POST['time_limit_minutes'] ?? 0),
                    !empty($_POST['show_score']),
                    !empty($_POST['show_explanation'])
                );
            }
            $success = 'Activity settings saved.';
        } elseif ($action === 'add_question') {
            $type = (string)($_POST['question_type'] ?? 'mcq');
            $choices = campus_posted_mcq_choices($_POST);
            $correctRaw = $_POST['correct_index'] ?? null;
            $correct = ($correctRaw === '' || $correctRaw === null) ? null : (int)$correctRaw;
            $questionId = $lessons->addQuestion(
                (int)($_POST['activity_id'] ?? 0),
                $type,
                (string)($_POST['prompt'] ?? ''),
                $choices,
                $correct,
                (float)($_POST['marks'] ?? 1),
                (string)($_POST['explanation'] ?? ''),
                (string)($_POST['topic'] ?? ''),
                (string)QuestionPool::normalizeDifficulty((string)($_POST['difficulty'] ?? '')),
                (string)($_POST['exam_ref'] ?? ''),
                (string)($_POST['expected_answer'] ?? '')
            );
            $lessons->setQuestionTags($questionId, (string)($_POST['tags'] ?? ''));
            $objectiveIds = is_array($_POST['objective_ids'] ?? null) ? $_POST['objective_ids'] : [];
            $modules->setQuestionObjectives($lessonPk, $questionId, $objectiveIds);
            $success = 'Question added.';
        } elseif ($action === 'import_aiken') {
            $activityId = (int)($_POST['activity_id'] ?? $activityId);
            $marks = (float)($_POST['marks'] ?? 1);
            $text = '';
            $hasFile = isset($_FILES['aiken_file']) && (int)($_FILES['aiken_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
            if ($hasFile) {
                $text = OnlineLessonService::aikenFromUpload($_FILES['aiken_file']);
            } else {
                $text = OnlineLessonService::normalizeAikenText((string)($_POST['aiken_text'] ?? ''));
            }
            if (trim($text) === '') {
                throw new RuntimeException('Upload an Aiken .txt file, or paste Aiken text.');
            }
            $result = $lessons->importAiken($activityId, $text, $marks > 0 ? $marks : 1);
            $success = 'Imported ' . $result['imported'] . ' MCQ question' . ($result['imported'] === 1 ? '' : 's') . ' from Aiken.';
            if ($result['skipped'] > 0) {
                $success .= ' Skipped ' . $result['skipped'] . ': ' . implode(' ', array_slice($result['errors'], 0, 4));
            }
            if (!empty($_POST['save_bank'])) {
                $bankTitle = trim((string)($_POST['bank_title'] ?? ''));
                $bankId = $lessons->createBankFromAiken($userId, $bankTitle !== '' ? $bankTitle : 'Aiken import', $text, $marks > 0 ? $marks : 1);
                $success .= ' Saved as question bank #' . $bankId . '.';
            }
        } elseif ($action === 'save_bank') {
            $activityId = (int)($_POST['activity_id'] ?? $activityId);
            $bankId = $lessons->createBankFromActivity($activityId, $userId, (string)($_POST['bank_title'] ?? ''));
            $success = 'Saved question bank.';
            $activityId = (int)($_POST['activity_id'] ?? $activityId);
        } elseif ($action === 'insert_bank') {
            $activityId = (int)($_POST['activity_id'] ?? $activityId);
            $added = $lessons->insertBankIntoActivity($activityId, (int)($_POST['bank_id'] ?? 0), $userId, $isAdmin);
            $success = 'Added ' . $added . ' question' . ($added === 1 ? '' : 's') . ' from the bank.';
        } elseif ($action === 'copy_to') {
            $targetId = (int)($_POST['target_timetable_id'] ?? 0);
            $targetLesson = $recordings->lessonForStaff($targetId, $teacherId, $isAdmin);
            if (!$targetLesson) {
                throw new RuntimeException('You cannot copy to that class date.');
            }
            if ($targetId === $timetableId) {
                throw new RuntimeException('Choose a different class date.');
            }
            $targetRecording = $recordings->activeForLesson($targetId);
            $copied = $lessons->getOrCreateForTimetable($targetLesson, $targetRecording, $userId);
            if ($lessons->lessonHasStudentWork((int)$copied['id'])) {
                throw new RuntimeException('That class already has student progress. Copy to a date nobody has started.');
            }
            $targetAssets = $targetRecording
                ? array_map(static fn (array $a): int => (int)$a['id'], $recordings->assets((int)$targetRecording['id']))
                : [];
            (new LessonVersionService($pdo))->copyInto($lessonPk, (int)$copied['id'], $userId, $targetAssets);
            log_audit($pdo, 'online_lesson_copy_to', 'online_lessons', (int)$copied['id'], null, ['source_lesson_id' => $lessonPk]);
            header('Location: ' . campus_online_lesson_url($targetId));
            exit;
        } elseif ($action === 'update_question') {
            $choices = campus_posted_mcq_choices($_POST);
            $correctRaw = $_POST['correct_index'] ?? null;
            $correct = ($correctRaw === '' || $correctRaw === null) ? null : (int)$correctRaw;
            $questionId = (int)($_POST['question_id'] ?? 0);
            $lessons->updateQuestion(
                $questionId,
                (string)($_POST['prompt'] ?? ''),
                $choices,
                $correct,
                (float)($_POST['marks'] ?? 1),
                (string)($_POST['explanation'] ?? ''),
                [
                    'topic' => (string)($_POST['topic'] ?? ''),
                    'difficulty' => (string)QuestionPool::normalizeDifficulty((string)($_POST['difficulty'] ?? '')),
                    'exam_ref' => (string)($_POST['exam_ref'] ?? ''),
                    'expected_answer' => (string)($_POST['expected_answer'] ?? ''),
                ]
            );
            $lessons->setQuestionTags($questionId, (string)($_POST['tags'] ?? ''));
            $objectiveIds = is_array($_POST['objective_ids'] ?? null) ? $_POST['objective_ids'] : [];
            $modules->setQuestionObjectives($lessonPk, $questionId, $objectiveIds);
            $success = 'Question updated.';
        } elseif ($action === 'delete_question') {
            $lessons->deleteQuestion((int)($_POST['question_id'] ?? 0));
            $success = 'Question removed.';
        } elseif ($action === 'grade_essay') {
            $questionId = (int)($_POST['question_id'] ?? 0);
            $selected = is_array($_POST['criteria'] ?? null) ? $_POST['criteria'] : [];
            $marks = (float)($_POST['marks'] ?? 0);
            if ($selected !== [] && empty($_POST['marks_edited'])) {
                $marks = LearningModuleService::criteriaAward($modules->criteriaForQuestion($questionId), $selected);
            }
            $lessons->gradeEssay(
                (int)($_POST['attempt_id'] ?? 0),
                $questionId,
                $marks,
                (string)($_POST['comment'] ?? ''),
                (int)$onlineLesson['id']
            );
            $success = 'Mark saved.';
        }

        $onlineLesson = $lessons->find((int)$onlineLesson['id']) ?? $onlineLesson;
        $items = $lessons->items((int)$onlineLesson['id']);
        $ungraded = $lessons->ungradedEssays((int)$onlineLesson['id']);
        if (in_array($action, ['add_question', 'update_question', 'delete_question', 'save_activity', 'import_aiken', 'save_bank', 'insert_bank', 'insert_selected', 'duplicate_question', 'add_criterion', 'delete_criterion'], true)) {
            $activityId = (int)($_POST['activity_id'] ?? $activityId);
        }
        if ($action === 'grade_essay') {
            $tab = 'grade';
        }
        $auditRefs = [];
        foreach (['item_id', 'activity_id', 'question_id', 'section_id', 'objective_id', 'resource_id', 'submission_id', 'attempt_id', 'template_id', 'bank_id'] as $refKey) {
            if ((int)($_POST[$refKey] ?? 0) > 0) {
                $auditRefs[$refKey] = (int)$_POST[$refKey];
            }
        }
        log_audit($pdo, 'online_lesson_' . $action, 'online_lessons', (int)$onlineLesson['id'], null, $auditRefs ?: null);
        if ($action === 'use_resource') {
            $_SESSION['online_lesson_success'] = $success;
            header('Location: ' . campus_online_lesson_url($timetableId));
            exit;
        }
        if (!empty($_POST['from_queue']) && in_array($action, ['grade_essay', 'review_submission'], true)) {
            parse_str((string)($_POST['queue_filter'] ?? ''), $queueInput);
            $queueQuery = http_build_query(array_filter(marking_queue_filters(is_array($queueInput) ? $queueInput : []), static fn ($v) => $v !== '' && $v !== 0));
            header('Location: ' . BASE_URL . 'campus/marking_queue.php?' . ($queueQuery !== '' ? $queueQuery . '&' : '') . 'next=1');
            exit;
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

if ($activityId > 0 && $onlineLesson) {
    $activity = $lessons->activity($activityId);
    if (!$activity || (int)$activity['lesson_id'] !== (int)$onlineLesson['id']) {
        $activity = null;
        $activityId = 0;
    } else {
        $questions = $lessons->questions($activityId);
        $questionIds = array_map(static fn (array $row): int => (int)$row['id'], $questions);
        if ($questionIds !== []) {
            $placeholders = implode(',', array_fill(0, count($questionIds), '?'));
            $criteriaStmt = $pdo->prepare("SELECT * FROM online_lesson_criteria WHERE question_id IN ($placeholders) ORDER BY sort_order ASC, id ASC");
            $criteriaStmt->execute($questionIds);
            foreach ($criteriaStmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $criterion) {
                $criteriaByQuestion[(int)$criterion['question_id']][] = $criterion;
            }
        }
    }
}

$pageItem = null;
if ($pageItemId > 0 && $onlineLesson) {
    $pageItem = $lessons->item($pageItemId);
    if (!$pageItem || (int)$pageItem['lesson_id'] !== (int)$onlineLesson['id'] || (string)$pageItem['item_type'] !== 'page') {
        $pageItem = null;
        $pageItemId = 0;
    }
}

if ($tab === 'results' && $onlineLesson && $staffLesson) {
    $classResults = $lessons->classResults((int)$onlineLesson['id'], (int)$staffLesson['class_id']);
    if (strtolower((string)($_GET['export'] ?? '')) === 'csv') {
        $csv = OnlineLessonService::resultsCsv($classResults);
        $name = 'lesson-results-' . (int)$onlineLesson['timetable_id'] . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $name . '"');
        header('Cache-Control: no-store');
        echo $csv;
        exit;
    }
}

if ($onlineLesson && $staffLesson) {
    $copyTargets = $lessons->copyTargets($staffLesson, $timetableId, $teacherId, $isAdmin);
}
$lessonTemplates = $onlineLesson ? $authoring->templatesFor($userId, $isAdmin) : [];
if ($onlineLesson) {
    $lessonObjectives = $modules->objectives((int)$onlineLesson['id']);
    $objectiveLinks = $modules->objectiveIdsByQuestion((int)$onlineLesson['id']);
    $resourceRows = $modules->resourcesFor($userId, $isAdmin, trim((string)($_GET['resource_q'] ?? '')), !empty($_GET['resource_archived']));
}
if ($tab === 'grade' && $onlineLesson) {
    $lessonSubmissions = $modules->lessonSubmissions((int)$onlineLesson['id']);
}
if ($activity) {
    $questionBanks = $lessons->banksForUser($userId, $isAdmin);
    $bankSearch = $modules->searchBankItems($userId, $isAdmin, [
        'subject' => (string)($_GET['bsubject'] ?? ''),
        'topic' => (string)($_GET['btopic'] ?? ''),
        'type' => (string)($_GET['btype'] ?? ''),
        'difficulty' => (string)($_GET['bdifficulty'] ?? ''),
        'tag' => (string)($_GET['btag'] ?? ''),
        'marks' => (string)($_GET['bmarks'] ?? ''),
    ], max(1, (int)($_GET['bpage'] ?? 1)));
}

$showArchived = !empty($_GET['archived']);
$catalogue = [];
if ($timetableId < 1) {
    foreach ($recordings->teacherLessons($teacherId, $isAdmin, $from, $to) as $row) {
        if (!OnlineLessonService::supportsDeliveryMode((string)($row['delivery_mode'] ?? 'physical'))) {
            continue;
        }
        $catalogue[] = $row;
    }
    $olMap = $lessons->mapForLessons(array_map(static fn (array $row): int => (int)$row['id'], $catalogue));
    foreach ($catalogue as &$row) {
        $row['online_lesson'] = $olMap[(int)$row['id']] ?? null;
    }
    unset($row);
}

$h = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$builderUrl = $timetableId > 0 ? campus_online_lesson_url($timetableId) : (BASE_URL . 'campus/online_lesson.php');

include __DIR__ . '/../includes/header.php';
?>
<div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
    <div>
        <h1 class="h3 mb-1"><i class="bi bi-collection-play me-2"></i>Learning modules</h1>
        <p class="text-muted mb-0">
            For online, in-college, and hybrid classes.
            Plan the lesson, then add notes, questions, and links. A physical class does not need a live room.
        </p>
    </div>
    <?php if ($timetableId > 0): ?>
        <a class="btn btn-outline-secondary" href="<?= $h(BASE_URL . 'campus/online_lesson.php') ?>">All video lessons</a>
    <?php endif; ?>
</div>

<?php if ($success): ?><div class="alert alert-success"><?= $h($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= $h($error) ?></div><?php endif; ?>

<?php if ($timetableId < 1): ?>
<form class="row g-2 align-items-end mb-3" method="get">
    <div class="col-auto">
        <label class="form-label mb-0">From</label>
        <input type="date" class="form-control" name="from" value="<?= $h($from) ?>">
    </div>
    <div class="col-auto">
        <label class="form-label mb-0">To</label>
        <input type="date" class="form-control" name="to" value="<?= $h($to) ?>">
    </div>
    <div class="col-auto">
        <button class="btn btn-primary" type="submit">Show classes</button>
    </div>
    <div class="col-auto">
        <div class="form-check mb-2">
            <input class="form-check-input" type="checkbox" name="archived" value="1" id="showArchived" <?= $showArchived ? 'checked' : '' ?>>
            <label class="form-check-label" for="showArchived">Archived</label>
        </div>
    </div>
</form>
<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Class</th>
                    <th>How it runs</th>
                    <th>Recording</th>
                    <th>Lesson</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($catalogue as $row):
                $mode = classroom_delivery_label((string)($row['delivery_mode'] ?? 'physical'));
                $ol = $row['online_lesson'] ?? null;
                if (!$showArchived && !empty($ol['archived'])) {
                    continue;
                }
            ?>
                <tr>
                    <td>
                        <?= $h(date('D, d M Y', strtotime((string)$row['date']))) ?><br>
                        <span class="small text-muted"><?= $h(date('g:i A', strtotime((string)$row['start_time'])) . ' – ' . date('g:i A', strtotime((string)$row['end_time']))) ?></span>
                    </td>
                    <td>
                        <?= $h((string)$row['subject_name']) ?><br>
                        <span class="small text-muted"><?= $h((string)$row['class_name']) ?><?php if ($isAdmin): ?> · <?= $h((string)$row['teacher_name']) ?><?php endif; ?></span>
                    </td>
                    <td><?= $h($mode) ?></td>
                    <td>
                        <?php if (!empty($row['recording_id'])): ?>
                            <span class="badge text-bg-<?= (string)($row['recording_status'] ?? '') === 'ready' ? 'success' : 'warning' ?>"><?= $h((string)$row['recording_status']) ?></span>
                        <?php else: ?>
                            <span class="text-muted">Upload clips first</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($ol): ?>
                            <?= match ((string)($ol['state'] ?? '')) {
                                'archived' => '<span class="badge text-bg-dark">Archived</span>',
                                'scheduled' => '<span class="badge text-bg-info">Scheduled</span>',
                                'closed' => '<span class="badge text-bg-warning">Closed</span>',
                                'published' => '<span class="badge text-bg-success">Published</span>',
                                default => '<span class="badge text-bg-secondary">Draft</span>',
                            } ?>
                            <div class="small text-muted"><?= (int)$ol['item_count'] ?> items</div>
                        <?php else: ?>
                            <span class="text-muted">Not built</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-end">
                        <a class="btn btn-sm btn-primary" href="<?= $h(campus_online_lesson_url((int)$row['id'])) ?>">Build lesson</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if ($catalogue === []): ?>
                <tr><td colspan="6" class="text-muted p-4">No classes in this date range.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php elseif ($activity): ?>
<p class="mb-3"><a href="<?= $h($builderUrl) ?>">&larr; Lesson sequence</a></p>
<div class="card border-0 shadow-sm rounded-4 p-4 mb-4">
    <h2 class="h4">Question activity</h2>
    <p class="text-muted">Questions are stored in the database and shown between video clips. They are not part of the video player.</p>
    <form method="post" class="row g-3">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save_activity">
        <input type="hidden" name="timetable_id" value="<?= $timetableId ?>">
        <input type="hidden" name="activity_id" value="<?= (int)$activity['id'] ?>">
        <input type="hidden" name="activity_type" value="<?= $h((string)$activity['activity_type']) ?>">
        <div class="col-md-6">
            <label class="form-label">Title</label>
            <input class="form-control" name="title" value="<?= $h((string)$activity['title']) ?>" required>
        </div>
        <div class="col-md-2">
            <label class="form-label">Pass mark %</label>
            <input class="form-control" type="number" min="0" max="100" name="pass_percent" value="<?= $h((string)($activity['pass_percent'] ?? 0)) ?>">
            <div class="form-text">0 = submit to continue</div>
        </div>
        <div class="col-md-2">
            <label class="form-label">Max attempts</label>
            <input class="form-control" type="number" min="0" name="max_attempts" value="<?= $h((string)$activity['max_attempts']) ?>">
            <div class="form-text">0 = unlimited</div>
        </div>
        <div class="col-md-2 d-flex align-items-end">
            <div class="form-check mb-2">
                <input class="form-check-input" type="checkbox" name="show_correct" value="1" id="show_correct" <?= !empty($activity['show_correct']) ? 'checked' : '' ?>>
                <label class="form-check-label" for="show_correct">Show correct answers after submit</label>
            </div>
        </div>
        <div class="col-md-3 d-flex align-items-end">
            <div class="form-check mb-2">
                <input class="form-check-input" type="checkbox" name="shuffle_choices" value="1" id="shuffle_choices" <?= !empty($activity['shuffle_choices']) ? 'checked' : '' ?>>
                <label class="form-check-label" for="shuffle_choices">Shuffle MCQ choices</label>
            </div>
        </div>
        <div class="col-md-3 d-flex align-items-end">
            <div class="form-check mb-2">
                <input class="form-check-input" type="checkbox" name="shuffle_questions" value="1" id="shuffle_questions" <?= !empty($activity['shuffle_questions']) ? 'checked' : '' ?>>
                <label class="form-check-label" for="shuffle_questions">Shuffle question order</label>
            </div>
        </div>
        <div class="col-12">
            <label class="form-label">Instructions<?= OnlineLessonService::isSubmissionActivity((string)$activity['activity_type']) ? '' : ' (optional)' ?></label>
            <textarea class="form-control" name="instructions" rows="2"><?= $h((string)($activity['instructions'] ?? '')) ?></textarea>
        </div>
        <?php if (!OnlineLessonService::isSubmissionActivity((string)$activity['activity_type'])): ?>
            <div class="col-12"><h3 class="h6 mb-0 mt-2">Question pool and attempts</h3></div>
            <div class="col-md-3">
                <label class="form-label">Questions per student</label>
                <div class="input-group">
                    <input class="form-control" type="number" min="0" max="500" name="draw_count" value="<?= $h((string)($activity['draw_count'] ?? '')) ?>">
                    <span class="input-group-text">/ <?= count($questions) ?></span>
                </div>
                <div class="form-text">Blank or 0 = every question. Each attempt draws its own set and keeps it for review.</div>
            </div>
            <div class="col-md-3">
                <label class="form-label">Time limit (minutes)</label>
                <input class="form-control" type="number" min="0" max="600" name="time_limit_minutes" value="<?= $h((string)($activity['time_limit_minutes'] ?? '')) ?>">
                <div class="form-text">Blank = no limit. Students press Start; answers submit when time runs out.</div>
            </div>
            <div class="col-md-3">
                <label class="form-label">Result uses</label>
                <select class="form-select" name="scoring_rule">
                    <option value="">Latest attempt (current behaviour)</option>
                    <option value="highest" <?= QuestionPool::normalizeRule($activity['scoring_rule'] ?? null) === 'highest' ? 'selected' : '' ?>>Highest score</option>
                    <option value="average" <?= QuestionPool::normalizeRule($activity['scoring_rule'] ?? null) === 'average' ? 'selected' : '' ?>>Average score</option>
                </select>
                <div class="form-text">Average changes the activity total only. Question detail shows the latest attempt.</div>
            </div>
            <div class="col-md-3 d-flex flex-column justify-content-end">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="show_score" value="1" id="show_score" <?= (int)($activity['show_score'] ?? 1) === 1 ? 'checked' : '' ?>>
                    <label class="form-check-label" for="show_score">Show score after submit</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="show_explanation" value="1" id="show_explanation" <?= (int)($activity['show_explanation'] ?? 1) === 1 ? 'checked' : '' ?>>
                    <label class="form-check-label" for="show_explanation">Show explanations</label>
                </div>
            </div>
        <?php endif; ?>
        <?php if (OnlineLessonService::isSubmissionActivity((string)$activity['activity_type'])): ?>
            <div class="col-md-4">
                <label class="form-label">Due date</label>
                <input class="form-control" type="datetime-local" name="due_at" value="<?= !empty($activity['due_at']) ? $h(date('Y-m-d\TH:i', strtotime((string)$activity['due_at']))) : '' ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">Maximum marks</label>
                <input class="form-control" type="number" min="0.5" step="0.5" name="max_marks" value="<?= $h((string)($activity['max_marks'] ?? '')) ?>">
            </div>
            <div class="col-md-3 d-flex align-items-end">
                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" name="allow_text" value="1" id="allow_text" <?= (int)($activity['allow_text'] ?? 1) === 1 ? 'checked' : '' ?>>
                    <label class="form-check-label" for="allow_text">Written answer</label>
                </div>
            </div>
            <div class="col-md-3 d-flex align-items-end">
                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" name="allow_file" value="1" id="allow_file" <?= (int)($activity['allow_file'] ?? 1) === 1 ? 'checked' : '' ?>>
                    <label class="form-check-label" for="allow_file">File upload</label>
                </div>
            </div>
        <?php endif; ?>
        <div class="col-12">
            <button class="btn btn-outline-primary" type="submit">Save activity</button>
        </div>
    </form>
</div>

<?php
    $questionMarkSum = 0.0;
    foreach ($questions as $markRow) {
        $questionMarkSum += (float)$markRow['marks'];
    }
?>
<?php
    $knownTags = QuestionPool::SUGGESTED_TAGS;
    foreach (array_merge($questions, $bankSearch['rows']) as $tagSource) {
        foreach (QuestionPool::tagList($tagSource['tags'] ?? null) as $tagLabel) {
            $knownTags[] = $tagLabel;
        }
    }
    $knownTags = array_values(array_unique($knownTags));
    $drawCount = (int)($activity['draw_count'] ?? 0);
?>
<datalist id="questionTagList"><?php foreach ($knownTags as $tagLabel): ?><option value="<?= $h($tagLabel) ?>"></option><?php endforeach; ?></datalist>
<p class="mb-3">Questions: <?= count($questions) ?> · Total: <?= $h(LessonResultBuilder::formatMark($questionMarkSum)) ?> marks
    <?php if ($drawCount > 0 && $drawCount < count($questions)): ?>
        · <strong>Question pool:</strong> each student receives <?= $drawCount ?> of <?= count($questions) ?>
    <?php elseif ($drawCount > 0): ?>
        · <span class="text-danger">Pool asks for <?= $drawCount ?> questions but only <?= count($questions) ?> exist. Every student gets all of them.</span>
    <?php endif; ?>
</p>
<?php foreach ($questions as $qi => $question):
    $choices = $question['choices'] ?? [];
    while (count($choices) < 4) {
        $choices[] = '';
    }
?>
<div class="card border-0 shadow-sm rounded-4 p-4 mb-3">
    <h3 class="h6">Question <?= $qi + 1 ?> · <?= $h(match ((string)$question['question_type']) {
        'essay' => 'Essay',
        'short' => 'Short answer',
        'exam' => 'Exam question',
        default => 'MCQ',
    }) ?>
        <?php if (!empty($question['ai_generated'])): ?>
            <span class="badge text-bg-warning ms-2">AI GENERATED — REVIEW REQUIRED</span>
        <?php endif; ?>
        <?php foreach (QuestionPool::tagList($question['tags'] ?? null) as $tagLabel): ?>
            <span class="badge text-bg-light border ms-1"><?= $h($tagLabel) ?></span>
        <?php endforeach; ?>
    </h3>
    <?php if (!empty($question['ai_generated'])): ?>
        <p class="small text-muted">Check the wording, answer, and marks, then press Update to approve it. Publishing is blocked until it is approved.</p>
    <?php endif; ?>
    <form method="post" class="row g-2">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="update_question">
        <input type="hidden" name="timetable_id" value="<?= $timetableId ?>">
        <input type="hidden" name="activity_id" value="<?= (int)$activity['id'] ?>">
        <input type="hidden" name="question_id" value="<?= (int)$question['id'] ?>">
        <div class="col-12">
            <textarea class="form-control" name="prompt" rows="3" required><?= $h((string)$question['prompt']) ?></textarea>
        </div>
        <?php if ((string)$question['question_type'] === 'mcq'): ?>
            <?php
                $choiceSlots = $choices;
                if (count($choiceSlots) < 4) {
                    $choiceSlots = array_pad($choiceSlots, 4, '');
                }
            ?>
            <?php foreach ($choiceSlots as $idx => $choiceVal):
                $letter = chr(ord('A') + (int)$idx);
            ?>
                <div class="col-md-6">
                    <div class="input-group">
                        <span class="input-group-text"><?= $h($letter) ?></span>
                        <input class="form-control" name="choices[]" value="<?= $h((string)$choiceVal) ?>">
                        <div class="input-group-text">
                            <input class="form-check-input mt-0" type="radio" name="correct_index" value="<?= (int)$idx ?>" <?= (int)$question['correct_index'] === (int)$idx ? 'checked' : '' ?>>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
            <div class="col-12">
                <label class="form-label">Explanation after marking (optional)</label>
                <input class="form-control" name="explanation" value="<?= $h((string)($question['explanation'] ?? '')) ?>">
            </div>
        <?php endif; ?>
        <?php if ((string)$question['question_type'] !== 'mcq'): ?>
            <div class="col-md-4">
                <label class="form-label">Exam reference</label>
                <input class="form-control" name="exam_ref" maxlength="80" value="<?= $h((string)($question['exam_ref'] ?? '')) ?>">
            </div>
            <div class="col-12">
                <label class="form-label">Expected answer <span class="text-muted">teachers only, not shown to students</span></label>
                <textarea class="form-control" name="expected_answer" rows="2"><?= $h((string)($question['expected_answer'] ?? '')) ?></textarea>
            </div>
        <?php endif; ?>
        <div class="col-md-3">
            <label class="form-label">Topic</label>
            <input class="form-control" name="topic" maxlength="120" value="<?= $h((string)($question['topic'] ?? '')) ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label">Difficulty</label>
            <?php $currentDifficulty = (string)($question['difficulty'] ?? ''); ?>
            <select class="form-select" name="difficulty">
                <option value="">Not set</option>
                <?php foreach (QuestionPool::DIFFICULTIES as $diffValue => $diffLabel): ?>
                    <option value="<?= $h($diffValue) ?>" <?= strtolower($currentDifficulty) === $diffValue ? 'selected' : '' ?>><?= $h($diffLabel) ?></option>
                <?php endforeach; ?>
                <?php if ($currentDifficulty !== '' && !array_key_exists(strtolower($currentDifficulty), QuestionPool::DIFFICULTIES)): ?>
                    <option value="<?= $h($currentDifficulty) ?>" selected><?= $h($currentDifficulty) ?></option>
                <?php endif; ?>
            </select>
        </div>
        <div class="col-md-6">
            <label class="form-label">Tags <span class="text-muted">comma separated</span></label>
            <input class="form-control" name="tags" maxlength="255" list="questionTagList" value="<?= $h(implode(', ', QuestionPool::tagList($question['tags'] ?? null))) ?>">
        </div>
        <?php if ($lessonObjectives !== []): ?>
            <div class="col-md-6">
                <label class="form-label">Learning objectives</label>
                <select class="form-select" name="objective_ids[]" multiple size="<?= min(4, count($lessonObjectives)) ?>">
                    <?php foreach ($lessonObjectives as $objective): ?>
                        <option value="<?= (int)$objective['id'] ?>" <?= in_array((int)$objective['id'], $objectiveLinks[(int)$question['id']] ?? [], true) ? 'selected' : '' ?>><?= $h((string)$objective['body']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>
        <div class="col-md-2">
            <label class="form-label">Marks</label>
            <input class="form-control" type="number" min="0.5" step="0.5" name="marks" value="<?= $h((string)$question['marks']) ?>">
        </div>
        <div class="col-md-10 d-flex align-items-end gap-2">
            <button class="btn btn-primary" type="submit">Update</button>
        </div>
    </form>
    <form method="post" class="mt-2" onsubmit="return confirm('Remove this question?');">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="delete_question">
        <input type="hidden" name="timetable_id" value="<?= $timetableId ?>">
        <input type="hidden" name="activity_id" value="<?= (int)$activity['id'] ?>">
        <input type="hidden" name="question_id" value="<?= (int)$question['id'] ?>">
        <button class="btn btn-sm btn-outline-danger" type="submit">Remove question</button>
    </form>
    <form method="post" class="mt-2">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="duplicate_question">
        <input type="hidden" name="timetable_id" value="<?= $timetableId ?>">
        <input type="hidden" name="activity_id" value="<?= (int)$activity['id'] ?>">
        <input type="hidden" name="question_id" value="<?= (int)$question['id'] ?>">
        <button class="btn btn-sm btn-outline-primary" type="submit">Duplicate question</button>
    </form>
    <?php if (OnlineLessonService::isManualQuestionType((string)$question['question_type'])): ?>
        <div class="mt-3">
            <h4 class="h6">Mark scheme</h4>
            <?php $criteriaRows = $criteriaByQuestion[(int)$question['id']] ?? []; $criteriaSum = 0.0; ?>
            <?php foreach ($criteriaRows as $criterion): $criteriaSum += (float)$criterion['marks']; ?>
                <div class="d-flex justify-content-between gap-2 mb-1">
                    <span><?= $h((string)$criterion['label']) ?> · <?= $h(LessonResultBuilder::formatMark((float)$criterion['marks'])) ?></span>
                    <form method="post"><?= csrf_field() ?>
                        <input type="hidden" name="action" value="delete_criterion">
                        <input type="hidden" name="timetable_id" value="<?= $timetableId ?>">
                        <input type="hidden" name="activity_id" value="<?= (int)$activity['id'] ?>">
                        <input type="hidden" name="question_id" value="<?= (int)$question['id'] ?>">
                        <input type="hidden" name="criterion_id" value="<?= (int)$criterion['id'] ?>">
                        <button class="btn btn-sm btn-outline-danger" type="submit">Remove</button>
                    </form>
                </div>
            <?php endforeach; ?>
            <?php if ($criteriaRows !== []): ?><p class="small text-muted">Criteria total: <?= $h(LessonResultBuilder::formatMark($criteriaSum)) ?></p><?php endif; ?>
            <form method="post" class="row g-2">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="add_criterion">
                <input type="hidden" name="timetable_id" value="<?= $timetableId ?>">
                <input type="hidden" name="activity_id" value="<?= (int)$activity['id'] ?>">
                <input type="hidden" name="question_id" value="<?= (int)$question['id'] ?>">
                <div class="col-md-6"><input class="form-control" name="label" placeholder="Criterion" required></div>
                <div class="col-md-2"><input class="form-control" type="number" min="0.5" step="0.5" name="marks" value="1" required></div>
                <div class="col-md-2"><button class="btn btn-outline-primary" type="submit">Add</button></div>
            </form>
        </div>
    <?php endif; ?>
</div>
<?php endforeach; ?>

<div class="card border-0 shadow-sm rounded-4 p-4 mb-4">
    <h3 class="h5">Import MCQs from an Aiken file</h3>
    <p class="text-muted mb-3">
        Same format Moodle uses. Save as <code>.txt</code>, UTF-8. Each question ends with <code>ANSWER:</code> and the correct letter.
    </p>
    <details class="mb-3">
        <summary class="small">Example</summary>
        <pre class="small bg-body-tertiary rounded-3 p-3 mt-2 mb-0">What is the capital of Sri Lanka?
A. Kandy
B. Colombo
C. Galle
D. Jaffna
ANSWER: B

Which particle has a negative charge?
A) Proton
B) Neutron
C) Electron
ANSWER: C</pre>
    </details>
    <form method="post" enctype="multipart/form-data" class="row g-2">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="import_aiken">
        <input type="hidden" name="timetable_id" value="<?= $timetableId ?>">
        <input type="hidden" name="activity_id" value="<?= (int)$activity['id'] ?>">
        <div class="col-md-6">
            <label class="form-label">Aiken file</label>
            <input class="form-control" type="file" name="aiken_file" accept=".txt,.aiken,text/plain">
        </div>
        <div class="col-md-2">
            <label class="form-label">Marks each</label>
            <input class="form-control" type="number" min="0.5" step="0.5" name="marks" value="1">
        </div>
        <div class="col-12">
            <label class="form-label">Or paste Aiken text</label>
            <textarea class="form-control" name="aiken_text" rows="6" placeholder="Question&#10;A. …&#10;B. …&#10;ANSWER: A"></textarea>
        </div>
        <div class="col-12">
            <button class="btn btn-primary" type="submit">Import MCQs</button>
        </div>
        <div class="col-md-6">
            <div class="form-check mt-2">
                <input class="form-check-input" type="checkbox" name="save_bank" value="1" id="save_bank">
                <label class="form-check-label" for="save_bank">Also save as a question bank</label>
            </div>
        </div>
        <div class="col-md-6">
            <label class="form-label">Bank title</label>
            <input class="form-control" name="bank_title" placeholder="e.g. Unit 1 MCQs">
        </div>
    </form>
</div>

<div class="card border-0 shadow-sm rounded-4 p-4 mb-4">
    <h3 class="h5">Question bank</h3>
    <p class="text-muted">Save this activity’s questions and drop them into another lesson.</p>
    <form method="post" class="row g-2 mb-3">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save_bank">
        <input type="hidden" name="timetable_id" value="<?= $timetableId ?>">
        <input type="hidden" name="activity_id" value="<?= (int)$activity['id'] ?>">
        <div class="col-md-8">
            <input class="form-control" name="bank_title" value="<?= $h((string)$activity['title']) ?>" placeholder="Bank title">
        </div>
        <div class="col-md-4">
            <button class="btn btn-outline-primary w-100" type="submit">Save these questions to a bank</button>
        </div>
    </form>
    <?php if ($questionBanks !== []): ?>
    <form method="post" class="row g-2">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="insert_bank">
        <input type="hidden" name="timetable_id" value="<?= $timetableId ?>">
        <input type="hidden" name="activity_id" value="<?= (int)$activity['id'] ?>">
        <div class="col-md-8">
            <select class="form-select" name="bank_id" required>
                <option value="">Choose a bank…</option>
                <?php foreach ($questionBanks as $bank): ?>
                    <option value="<?= (int)$bank['id'] ?>"><?= $h((string)$bank['title']) ?> (<?= (int)$bank['question_count'] ?>)</option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4">
            <button class="btn btn-outline-secondary w-100" type="submit">Insert bank into this activity</button>
        </div>
    </form>
    <form method="get" class="row g-2 mt-3">
        <input type="hidden" name="lesson" value="<?= $timetableId ?>">
        <input type="hidden" name="activity" value="<?= (int)$activity['id'] ?>">
        <div class="col-md-2"><input class="form-control" name="bsubject" value="<?= $h((string)($_GET['bsubject'] ?? '')) ?>" placeholder="Subject"></div>
        <div class="col-md-2"><input class="form-control" name="btopic" value="<?= $h((string)($_GET['btopic'] ?? '')) ?>" placeholder="Topic"></div>
        <div class="col-md-2">
            <select class="form-select" name="btype">
                <option value="">Type</option>
                <?php foreach (['mcq' => 'MCQ', 'short' => 'Short answer', 'essay' => 'Essay', 'exam' => 'Exam'] as $value => $label): ?>
                    <option value="<?= $h($value) ?>" <?= (string)($_GET['btype'] ?? '') === $value ? 'selected' : '' ?>><?= $h($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <select class="form-select" name="bdifficulty">
                <option value="">Difficulty</option>
                <?php foreach (QuestionPool::DIFFICULTIES as $diffValue => $diffLabel): ?>
                    <option value="<?= $h($diffValue) ?>" <?= strtolower((string)($_GET['bdifficulty'] ?? '')) === $diffValue ? 'selected' : '' ?>><?= $h($diffLabel) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2"><input class="form-control" name="btag" list="questionTagList" value="<?= $h((string)($_GET['btag'] ?? '')) ?>" placeholder="Tag"></div>
        <div class="col-md-1"><input class="form-control" type="number" step="0.5" name="bmarks" value="<?= $h((string)($_GET['bmarks'] ?? '')) ?>" placeholder="Marks"></div>
        <div class="col-md-1"><button class="btn btn-outline-primary w-100" type="submit">Filter</button></div>
    </form>
    <?php if ($bankSearch['rows'] !== []): ?>
    <form method="post" class="mt-3">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="insert_selected">
        <input type="hidden" name="timetable_id" value="<?= $timetableId ?>">
        <input type="hidden" name="activity_id" value="<?= (int)$activity['id'] ?>">
        <?php foreach ($bankSearch['rows'] as $bankRow): ?>
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="bank_item_ids[]" value="<?= (int)$bankRow['id'] ?>" id="bank-item-<?= (int)$bankRow['id'] ?>">
                <label class="form-check-label" for="bank-item-<?= (int)$bankRow['id'] ?>">
                    <?= $h((string)$bankRow['question_type']) ?> · <?= $h(LessonResultBuilder::formatMark((float)$bankRow['marks'])) ?> marks
                    <?php if (trim((string)($bankRow['difficulty'] ?? '')) !== ''): ?> · <?= $h(QuestionPool::DIFFICULTIES[strtolower((string)$bankRow['difficulty'])] ?? (string)$bankRow['difficulty']) ?><?php endif; ?>
                    · <?= $h(mb_substr((string)$bankRow['prompt'], 0, 120)) ?>
                    <?php foreach (QuestionPool::tagList($bankRow['tags'] ?? null) as $tagLabel): ?>
                        <span class="badge text-bg-light border"><?= $h($tagLabel) ?></span>
                    <?php endforeach; ?>
                </label>
            </div>
        <?php endforeach; ?>
        <button class="btn btn-primary mt-2" type="submit">Add selected</button>
        <p class="small text-muted mb-0"><?= (int)$bankSearch['total'] ?> matching questions. Added questions are copies.</p>
    </form>
    <?php endif; ?>
    <?php else: ?>
        <p class="small text-muted mb-0">No banks yet. Import Aiken or save this activity first.</p>
    <?php endif; ?>
</div>

<div class="card border-0 shadow-sm rounded-4 p-4">
    <h3 class="h5">Add a question</h3>
    <form method="post" class="row g-2">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add_question">
        <input type="hidden" name="timetable_id" value="<?= $timetableId ?>">
        <input type="hidden" name="activity_id" value="<?= (int)$activity['id'] ?>">
        <div class="col-md-3">
            <label class="form-label">Type</label>
            <select class="form-select" name="question_type" id="newQuestionType">
                <option value="mcq">Multiple choice</option>
                <option value="short">Short answer</option>
                <option value="exam">Exam question</option>
                <option value="essay">Essay / long answer</option>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label">Marks</label>
            <input class="form-control" type="number" min="0.5" step="0.5" name="marks" value="1">
        </div>
        <div class="col-12">
            <label class="form-label">Question</label>
            <textarea class="form-control" name="prompt" rows="3" required placeholder="Type the question students will see"></textarea>
        </div>
        <div class="col-12" data-mcq-fields>
            <label class="form-label">Choices — tick the correct one</label>
            <div class="row g-2">
                <?php foreach (['A', 'B', 'C', 'D'] as $idx => $letter): ?>
                    <div class="col-md-6">
                        <div class="input-group">
                            <span class="input-group-text"><?= $letter ?></span>
                            <input class="form-control" name="choice_<?= strtolower($letter) ?>" placeholder="Choice <?= $letter ?>">
                            <div class="input-group-text">
                                <input class="form-check-input mt-0" type="radio" name="correct_index" value="<?= $idx ?>" <?= $idx === 0 ? 'checked' : '' ?>>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <label class="form-label mt-2">Explanation (optional)</label>
            <input class="form-control" name="explanation">
        </div>
        <div class="col-md-4">
            <label class="form-label">Topic</label>
            <input class="form-control" name="topic" maxlength="120">
        </div>
        <div class="col-md-3">
            <label class="form-label">Difficulty</label>
            <select class="form-select" name="difficulty">
                <option value="">Not set</option>
                <?php foreach (QuestionPool::DIFFICULTIES as $diffValue => $diffLabel): ?>
                    <option value="<?= $h($diffValue) ?>"><?= $h($diffLabel) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label">Exam reference</label>
            <input class="form-control" name="exam_ref" maxlength="80">
        </div>
        <div class="col-md-6">
            <label class="form-label">Tags <span class="text-muted">comma separated</span></label>
            <input class="form-control" name="tags" maxlength="255" list="questionTagList" placeholder="CPU, Memory">
        </div>
        <div class="col-12">
            <label class="form-label">Expected answer <span class="text-muted">for written questions, not shown to students</span></label>
            <textarea class="form-control" name="expected_answer" rows="2"></textarea>
        </div>
        <?php if ($lessonObjectives !== []): ?>
            <div class="col-md-6">
                <label class="form-label">Learning objectives</label>
                <select class="form-select" name="objective_ids[]" multiple size="<?= min(4, count($lessonObjectives)) ?>">
                    <?php foreach ($lessonObjectives as $objective): ?>
                        <option value="<?= (int)$objective['id'] ?>"><?= $h((string)$objective['body']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>
        <div class="col-12">
            <button class="btn btn-primary" type="submit">Add question</button>
        </div>
    </form>
</div>
<script>
(function () {
    var sel = document.getElementById('newQuestionType');
    var box = document.querySelector('[data-mcq-fields]');
    if (!sel || !box) return;
    function sync() { box.style.display = sel.value === 'essay' ? 'none' : ''; }
    sel.addEventListener('change', sync);
    sync();
})();
</script>

<?php elseif ($pageItem): ?>
<p class="mb-3"><a href="<?= $h($builderUrl) ?>">&larr; Lesson sequence</a></p>
<div class="card border-0 shadow-sm rounded-4 p-4">
    <h2 class="h4">Text page</h2>
    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save_page">
        <input type="hidden" name="timetable_id" value="<?= $timetableId ?>">
        <input type="hidden" name="item_id" value="<?= (int)$pageItem['id'] ?>">
        <div class="mb-3">
            <label class="form-label">Title</label>
            <input class="form-control" name="title" value="<?= $h((string)$pageItem['title']) ?>" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Content</label>
            <textarea class="form-control" name="body" rows="10"><?= $h((string)($pageItem['body'] ?? '')) ?></textarea>
        </div>
        <button class="btn btn-primary" type="submit">Save page</button>
    </form>
</div>

<?php else: ?>
<?php
    $modeLabel = classroom_delivery_label((string)($staffLesson['delivery_mode'] ?? 'physical'));
    $published = !empty($onlineLesson['published']);
?>
<div class="card border-0 shadow-sm rounded-4 p-4 mb-4">
    <div class="d-flex flex-wrap justify-content-between gap-2 mb-2">
        <div>
            <p class="text-muted small mb-1"><?= $h($modeLabel) ?> · <?= $h((string)$staffLesson['class_name']) ?></p>
            <h2 class="h4 mb-0"><?= $h((string)$staffLesson['subject_name']) ?></h2>
            <p class="text-muted mb-0"><?= $h(date('l, d F Y', strtotime((string)$staffLesson['date']))) ?>
                · <?= $h(date('g:i A', strtotime((string)$staffLesson['start_time']))) ?>
                – <?= $h(date('g:i A', strtotime((string)$staffLesson['end_time']))) ?></p>
        </div>
        <?php
            $planSections = $authoring->sections((int)$onlineLesson['id']);
            $qualityRows = (new LessonInsightService($pdo))->qualityReport((int)$onlineLesson['id']);
            $planIssueRows = array_values(array_filter($qualityRows, static fn (array $row): bool => $row['severity'] === 'high'));
            $planAdvice = count($qualityRows) - count($planIssueRows);
            $planIssues = array_column($planIssueRows, 'message');
            $planDuration = LessonAuthoringService::durationStatus(
                $onlineLesson['plan_minutes'] !== null && $onlineLesson['plan_minutes'] !== '' ? (int)$onlineLesson['plan_minutes'] : null,
                $planSections,
                $items
            );
            $planWarning = LessonAuthoringService::durationWarning(
                isset($onlineLesson['plan_minutes']) && $onlineLesson['plan_minutes'] !== null && $onlineLesson['plan_minutes'] !== '' ? (int)$onlineLesson['plan_minutes'] : null,
                $planSections
            );
            $planMarks = $authoring->marksTotal((int)$onlineLesson['id']);
            $archivedLesson = !empty($onlineLesson['archived']);
            $pubState = OnlineLessonService::publicationState($onlineLesson);
            $statusText = $archivedLesson ? 'Archived' : ($published ? 'Published' : ($planIssues === [] && $items !== [] ? 'Ready to publish' : 'Draft'));
            $statusClass = $archivedLesson ? 'text-bg-dark' : ($published ? 'text-bg-success' : ($planIssues === [] && $items !== [] ? 'text-bg-primary' : 'text-bg-secondary'));
            if (!$archivedLesson && $pubState === 'scheduled') {
                $statusText = 'Scheduled for ' . date('d M Y, g:i A', strtotime((string)$onlineLesson['publish_at']));
                $statusClass = 'text-bg-info';
            } elseif (!$archivedLesson && $pubState === 'closed') {
                $statusText = 'Closed';
                $statusClass = 'text-bg-warning';
            }
        ?>
        <span class="badge <?= $statusClass ?> align-self-start"><?= $h($statusText) ?></span>
    </div>
    <form method="post" class="row g-3 mt-1">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save_meta">
        <input type="hidden" name="timetable_id" value="<?= $timetableId ?>">
        <div class="col-md-6">
            <label class="form-label">Lesson title</label>
            <input class="form-control" name="title" value="<?= $h((string)$onlineLesson['title']) ?>" required>
        </div>
        <div class="col-md-3">
            <label class="form-label">Must watch each clip</label>
            <div class="input-group">
                <input class="form-control" type="number" min="0" max="100" name="min_watch_percent" value="<?= $h((string)($onlineLesson['min_watch_percent'] ?? 80)) ?>">
                <span class="input-group-text">%</span>
            </div>
            <div class="form-text">0 = students can skip ahead</div>
        </div>
        <div class="col-md-3 d-flex align-items-end">
            <div class="form-check mb-2">
                <input class="form-check-input" type="checkbox" name="sequential" value="1" id="sequential" <?= !empty($onlineLesson['sequential']) ? 'checked' : '' ?>>
                <label class="form-check-label" for="sequential">Finish each item before the next</label>
            </div>
        </div>
        <div class="col-md-4 d-flex align-items-end">
            <div class="form-check mb-2">
                <input class="form-check-input" type="checkbox" name="available_after_class" value="1" id="available_after_class" <?= !empty($onlineLesson['available_after_class']) ? 'checked' : '' ?>>
                <label class="form-check-label" for="available_after_class">Open only after class ends</label>
            </div>
        </div>
        <div class="col-md-3">
            <label class="form-label">Close after (days)</label>
            <input class="form-control" type="number" min="0" max="365" name="close_after_days" value="<?= $h((string)($onlineLesson['close_after_days'] ?? 0)) ?>">
            <div class="form-text">0 = stays open</div>
        </div>
        <div class="col-12">
            <button class="btn btn-outline-primary" type="submit">Save settings</button>
        </div>
    </form>
    <div class="d-flex flex-wrap gap-2 mt-3">
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="timetable_id" value="<?= $timetableId ?>">
            <input type="hidden" name="action" value="<?= $published ? 'unpublish' : 'publish' ?>">
            <?php if (!$published): ?>
                <input type="hidden" name="notify_students" value="1">
            <?php endif; ?>
            <button class="btn <?= $published ? 'btn-outline-secondary' : 'btn-success' ?>" type="submit">
                <?= $published ? 'Unpublish' : 'Publish for students' ?>
            </button>
        </form>
        <a class="btn btn-outline-primary" href="<?= $h(campus_online_lesson_preview_url($timetableId)) ?>">Preview as student</a>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="timetable_id" value="<?= $timetableId ?>">
            <input type="hidden" name="action" value="sync">
            <button class="btn btn-outline-secondary" type="submit">Sync new video clips</button>
        </form>
        <a class="btn btn-outline-secondary" href="<?= $h(BASE_URL . 'campus/recordings.php?lesson=' . $timetableId) ?>#upload">Upload clips</a>
        <a class="btn <?= $tab === 'grade' ? 'btn-primary' : 'btn-outline-secondary' ?>" href="<?= $h($builderUrl . '&tab=grade') ?>">
            Mark essays<?= $ungraded !== [] ? ' (' . count($ungraded) . ')' : '' ?>
        </a>
        <a class="btn <?= $tab === 'results' ? 'btn-primary' : 'btn-outline-secondary' ?>" href="<?= $h($builderUrl . '&tab=results') ?>">Class results</a>
        <a class="btn btn-outline-primary" href="<?= $h(campus_lesson_analytics_url($timetableId)) ?>">Analytics</a>
        <a class="btn btn-outline-primary" href="<?= $h(BASE_URL . 'campus/lesson_manage.php?lesson=' . $timetableId) ?>">Schedule, versions &amp; AI</a>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="timetable_id" value="<?= $timetableId ?>">
            <input type="hidden" name="action" value="<?= $archivedLesson ? 'unarchive' : 'archive' ?>">
            <button class="btn btn-outline-secondary" type="submit"><?= $archivedLesson ? 'Restore' : 'Archive' ?></button>
        </form>
        <?php if ($tab === 'grade' || $tab === 'results'): ?>
            <a class="btn btn-outline-secondary" href="<?= $h($builderUrl) ?>">Sequence</a>
        <?php endif; ?>
    </div>
</div>

<p class="small text-muted mb-4"><?= count($items) ?> items · <?= $h(LessonResultBuilder::formatMark($planMarks)) ?> marks<?= $onlineLesson['plan_minutes'] !== null && $onlineLesson['plan_minutes'] !== '' ? ' · planned ' . (int)$onlineLesson['plan_minutes'] . ' min' : '' ?></p>
<?php if ($planIssues !== []): ?>
    <div class="alert alert-warning">
        <strong><?= count($planIssues) ?> issue<?= count($planIssues) === 1 ? '' : 's' ?> before publishing</strong>
        <ul class="mb-0"><?php foreach ($planIssueRows as $issue): ?>
            <li><?= $h((string)$issue['message']) ?>
                <?php if ((int)$issue['activity_id'] > 0): ?>
                    <a href="<?= $h(campus_online_lesson_url($timetableId, (int)$issue['activity_id'])) ?>">Fix</a>
                <?php elseif ((int)$issue['item_id'] > 0): ?>
                    <a href="<?= $h($builderUrl . '&page=' . (int)$issue['item_id']) ?>">Fix</a>
                <?php endif; ?>
            </li>
        <?php endforeach; ?></ul>
    </div>
<?php elseif (!$published): ?>
    <div class="alert alert-success">Lesson is ready to publish.</div>
<?php endif; ?>
<?php if (($planAdvice ?? 0) > 0): ?>
    <p class="small"><a href="<?= $h(BASE_URL . 'campus/lesson_manage.php?lesson=' . $timetableId) ?>#quality"><?= (int)$planAdvice ?> suggestion<?= $planAdvice === 1 ? '' : 's' ?> to improve this lesson</a> (these do not block publishing).</p>
<?php endif; ?>
<?php if (($planDuration['state'] ?? '') === 'fits'): ?>
    <div class="alert alert-success"><?= $h((string)$planDuration['message']) ?></div>
<?php elseif (($planDuration['state'] ?? '') === 'over'): ?>
    <div class="alert alert-warning"><?= $h((string)$planDuration['message']) ?></div>
<?php endif; ?>

<nav class="d-flex flex-wrap gap-2 mb-3" aria-label="Learning module steps">
    <a class="btn btn-sm btn-outline-secondary" href="#lesson-plan">1 Plan</a>
    <a class="btn btn-sm btn-outline-secondary" href="#lesson-sections">2 Sections</a>
    <a class="btn btn-sm btn-outline-secondary" href="#lesson-sequence">3 Build</a>
    <a class="btn btn-sm btn-outline-secondary" href="<?= $h(campus_online_lesson_preview_url($timetableId)) ?>">4 Preview</a>
    <span class="btn btn-sm btn-outline-secondary disabled">5 Publish when ready</span>
</nav>
<div class="card border-0 shadow-sm rounded-4 p-4 mb-4" id="lesson-plan">
    <h2 class="h5">Lesson plan</h2>
    <p class="text-muted">Save the plan first. Generating a structure adds draft pages and an empty quiz. It does not publish, and it will not replace a lesson that already has pages or questions.</p>
    <form method="post" class="row g-3">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save_plan">
        <input type="hidden" name="timetable_id" value="<?= $timetableId ?>">
        <div class="col-md-8">
            <label class="form-label">Learning objectives <span class="text-muted">one per line</span></label>
            <textarea class="form-control" name="plan_objectives" rows="4"><?= $h((string)($onlineLesson['plan_objectives'] ?? '')) ?></textarea>
            <div class="form-text">One objective per line. Saving the plan does not publish.</div>
        </div>
        <div class="col-md-4">
            <label class="form-label">Planned duration (minutes)</label>
            <input class="form-control" type="number" min="1" max="600" name="plan_minutes" value="<?= $h((string)($onlineLesson['plan_minutes'] ?? '')) ?>">
            <label class="form-label mt-3">Difficulty</label>
            <input class="form-control" name="plan_difficulty" maxlength="40" value="<?= $h((string)($onlineLesson['plan_difficulty'] ?? '')) ?>">
        </div>
        <div class="col-md-6">
            <label class="form-label">Topics</label>
            <textarea class="form-control" name="plan_topics" rows="2"><?= $h((string)($onlineLesson['plan_topics'] ?? '')) ?></textarea>
        </div>
        <div class="col-md-6">
            <label class="form-label">Prerequisites</label>
            <textarea class="form-control" name="plan_prerequisites" rows="2"><?= $h((string)($onlineLesson['plan_prerequisites'] ?? '')) ?></textarea>
        </div>
        <div class="col-md-6">
            <label class="form-label">Learning outcomes</label>
            <textarea class="form-control" name="plan_outcomes" rows="2"><?= $h((string)($onlineLesson['plan_outcomes'] ?? '')) ?></textarea>
        </div>
        <div class="col-md-6">
            <label class="form-label">Materials</label>
            <textarea class="form-control" name="plan_materials" rows="2"><?= $h((string)($onlineLesson['plan_materials'] ?? '')) ?></textarea>
        </div>
        <div class="col-md-6">
            <label class="form-label">Assessment plan</label>
            <textarea class="form-control" name="plan_assessment" rows="2"><?= $h((string)($onlineLesson['plan_assessment'] ?? '')) ?></textarea>
        </div>
        <div class="col-md-6">
            <label class="form-label">Homework</label>
            <textarea class="form-control" name="plan_homework" rows="2"><?= $h((string)($onlineLesson['plan_homework'] ?? '')) ?></textarea>
        </div>
        <div class="col-12 d-flex flex-wrap gap-2">
            <button class="btn btn-primary" type="submit">Save plan</button>
        </div>
    </form>
    <form method="post" class="mt-3" onsubmit="return confirm('Create a draft structure from these objectives?');">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="generate_plan">
        <input type="hidden" name="timetable_id" value="<?= $timetableId ?>">
        <button class="btn btn-outline-primary" type="submit">Build lesson structure</button>
    </form>
    <div class="mt-4">
        <h3 class="h6">Objectives</h3>
        <?php foreach ($lessonObjectives as $objectiveIndex => $objective): ?>
            <form method="post" class="row g-2 align-items-center mb-2">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="rename_objective">
                <input type="hidden" name="timetable_id" value="<?= $timetableId ?>">
                <input type="hidden" name="objective_id" value="<?= (int)$objective['id'] ?>">
                <div class="col-md-7"><input class="form-control" name="body" value="<?= $h((string)$objective['body']) ?>"></div>
                <div class="col-md-5 d-flex gap-2">
                    <button class="btn btn-outline-primary" type="submit">Save</button>
                </div>
            </form>
            <div class="d-flex gap-2 mb-3">
                <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="move_objective"><input type="hidden" name="timetable_id" value="<?= $timetableId ?>"><input type="hidden" name="objective_id" value="<?= (int)$objective['id'] ?>"><input type="hidden" name="direction" value="up"><button class="btn btn-sm btn-outline-secondary" type="submit" <?= $objectiveIndex === 0 ? 'disabled' : '' ?>>Up</button></form>
                <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="move_objective"><input type="hidden" name="timetable_id" value="<?= $timetableId ?>"><input type="hidden" name="objective_id" value="<?= (int)$objective['id'] ?>"><input type="hidden" name="direction" value="down"><button class="btn btn-sm btn-outline-secondary" type="submit" <?= $objectiveIndex === count($lessonObjectives) - 1 ? 'disabled' : '' ?>>Down</button></form>
                <form method="post" onsubmit="return confirm('Remove this objective?');"><?= csrf_field() ?><input type="hidden" name="action" value="delete_objective"><input type="hidden" name="timetable_id" value="<?= $timetableId ?>"><input type="hidden" name="objective_id" value="<?= (int)$objective['id'] ?>"><button class="btn btn-sm btn-outline-danger" type="submit">Remove</button></form>
            </div>
        <?php endforeach; ?>
        <form method="post" class="row g-2">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add_objective">
            <input type="hidden" name="timetable_id" value="<?= $timetableId ?>">
            <div class="col-md-8"><input class="form-control" name="body" placeholder="Add an objective" required></div>
            <div class="col-md-2"><button class="btn btn-outline-primary" type="submit">Add</button></div>
        </form>
    </div>
    <form method="post" class="row g-2 align-items-end mt-3">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save_template">
        <input type="hidden" name="timetable_id" value="<?= $timetableId ?>">
        <div class="col-md-6">
            <label class="form-label">Save this module as a template</label>
            <input class="form-control" name="template_title" maxlength="200" placeholder="Template name" required>
        </div>
        <div class="col-md-3">
            <button class="btn btn-outline-secondary" type="submit">Save template</button>
        </div>
    </form>
    <?php if ($lessonTemplates !== []): ?>
    <form method="post" class="row g-2 align-items-end mt-2" onsubmit="return confirm('Apply this template as a draft? It will not replace a module that already has pages or questions.');">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="apply_template">
        <input type="hidden" name="timetable_id" value="<?= $timetableId ?>">
        <div class="col-md-6">
            <label class="form-label">Use a template</label>
            <select class="form-select" name="template_id" required>
                <option value="">Choose a template…</option>
                <?php foreach ($lessonTemplates as $template): ?>
                    <option value="<?= (int)$template['id'] ?>"><?= $h((string)$template['title']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <button class="btn btn-outline-primary" type="submit">Use template</button>
        </div>
    </form>
    <?php endif; ?>
</div>

<div class="card border-0 shadow-sm rounded-4 p-4 mb-4" id="lesson-sections">
    <h2 class="h5">Sections</h2>
    <?php if ($planSections === []): ?>
        <p class="text-muted">No sections yet. Activities still play in their current order.</p>
    <?php endif; ?>
    <?php foreach ($planSections as $sectionIndex => $section): ?>
        <form method="post" class="row g-2 align-items-end mb-2">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="rename_section">
            <input type="hidden" name="timetable_id" value="<?= $timetableId ?>">
            <input type="hidden" name="section_id" value="<?= (int)$section['id'] ?>">
            <div class="col-md-6">
                <label class="form-label">Section</label>
                <input class="form-control" name="title" value="<?= $h((string)$section['title']) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">Minutes</label>
                <input class="form-control" type="number" min="1" max="600" name="estimated_minutes" value="<?= $h((string)($section['estimated_minutes'] ?? '')) ?>">
            </div>
            <div class="col-md-4 d-flex gap-2">
                <button class="btn btn-outline-primary" type="submit">Save</button>
            </div>
        </form>
        <div class="d-flex gap-2 mb-3">
            <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="move_section"><input type="hidden" name="timetable_id" value="<?= $timetableId ?>"><input type="hidden" name="section_id" value="<?= (int)$section['id'] ?>"><input type="hidden" name="direction" value="up"><button class="btn btn-sm btn-outline-secondary" type="submit" <?= $sectionIndex === 0 ? 'disabled' : '' ?>>Up</button></form>
            <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="move_section"><input type="hidden" name="timetable_id" value="<?= $timetableId ?>"><input type="hidden" name="section_id" value="<?= (int)$section['id'] ?>"><input type="hidden" name="direction" value="down"><button class="btn btn-sm btn-outline-secondary" type="submit" <?= $sectionIndex === count($planSections) - 1 ? 'disabled' : '' ?>>Down</button></form>
            <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="duplicate_section"><input type="hidden" name="timetable_id" value="<?= $timetableId ?>"><input type="hidden" name="section_id" value="<?= (int)$section['id'] ?>"><button class="btn btn-sm btn-outline-primary" type="submit">Duplicate</button></form>
            <form method="post" onsubmit="return confirm('Remove this section? Activities stay in the lesson.');"><?= csrf_field() ?><input type="hidden" name="action" value="delete_section"><input type="hidden" name="timetable_id" value="<?= $timetableId ?>"><input type="hidden" name="section_id" value="<?= (int)$section['id'] ?>"><button class="btn btn-sm btn-outline-danger" type="submit">Delete section</button></form>
        </div>
        <?php $sectionMinutes = 0; $sectionRows = []; foreach ($items as $sectionItem) { if ((int)($sectionItem['section_id'] ?? 0) === (int)$section['id']) { $sectionRows[] = $sectionItem; if (($sectionItem['estimated_minutes'] ?? '') !== '') { $sectionMinutes += (int)$sectionItem['estimated_minutes']; } } } ?>
        <?php if ($sectionRows !== []): ?>
            <ul class="small mb-3">
                <?php foreach ($sectionRows as $sectionItem): ?>
                    <li><?= $h(OnlineLessonService::itemKindLabel($sectionItem)) ?> — <?= $h((string)$sectionItem['title']) ?><?= ($sectionItem['estimated_minutes'] ?? '') !== '' ? ' · ' . (int)$sectionItem['estimated_minutes'] . ' min' : '' ?></li>
                <?php endforeach; ?>
            </ul>
            <?php if ($sectionMinutes > 0): ?><p class="small text-muted">Item minutes in this section: <?= (int)$sectionMinutes ?></p><?php endif; ?>
        <?php endif; ?>
    <?php endforeach; ?>
    <form method="post" class="row g-2 align-items-end">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add_section">
        <input type="hidden" name="timetable_id" value="<?= $timetableId ?>">
        <div class="col-md-6">
            <label class="form-label">New section</label>
            <input class="form-control" name="title" placeholder="Section title" required>
        </div>
        <div class="col-md-2">
            <label class="form-label">Minutes</label>
            <input class="form-control" type="number" min="1" max="600" name="estimated_minutes">
        </div>
        <div class="col-md-2">
            <button class="btn btn-outline-primary" type="submit">Add section</button>
        </div>
    </form>
</div>

<?php if ($copyTargets !== []): ?>
<div class="card border-0 shadow-sm rounded-4 p-4 mb-4">
    <h2 class="h5">Reuse this lesson on another date or class</h2>
    <p class="text-muted">Copies the plan, sections, objectives, pages, links, resources, activities, questions and mark schemes. That class’s own video clips fill the same clip positions. Student progress, attempts, marks and submissions are never copied. The copy stays a draft until you publish it.</p>
    <form method="post" class="row g-2 align-items-end">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="copy_to">
        <input type="hidden" name="timetable_id" value="<?= $timetableId ?>">
        <div class="col-md-8">
            <label class="form-label">Class date</label>
            <select class="form-select" name="target_timetable_id" required>
                <option value="">Choose a date…</option>
                <?php foreach ($copyTargets as $target): ?>
                    <option value="<?= (int)$target['id'] ?>">
                        <?= $h(date('D d M Y', strtotime((string)$target['date']))) ?>
                        · <?= $h(date('g:i A', strtotime((string)$target['start_time']))) ?>
                        · <?= $h((string)($target['class_name'] ?? '')) ?>
                        <?php if (!empty($target['online_lesson_id'])): ?>
                            · already has a lesson
                        <?php endif; ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4">
            <button class="btn btn-outline-primary w-100" type="submit">Copy sequence</button>
        </div>
    </form>
</div>
<?php endif; ?>

<?php if ($tab === 'grade'): ?>
<div class="card border-0 shadow-sm rounded-4 p-4">
    <?php
        $fromQueue = !empty($_GET['queue']);
        $queueFilter = (string)($_GET['queue_filter'] ?? '');
        $queueFields = $fromQueue ? '<input type="hidden" name="from_queue" value="1"><input type="hidden" name="queue_filter" value="' . $h($queueFilter) . '">' : '';
    ?>
    <div class="d-flex justify-content-between flex-wrap gap-2">
        <h2 class="h5">Essay submissions to mark</h2>
        <?php if ($fromQueue): ?>
            <div class="small text-muted">Saving a mark takes you to the next unmarked item. <a href="<?= $h(BASE_URL . 'campus/marking_queue.php?' . $queueFilter) ?>">Back to queue</a></div>
        <?php endif; ?>
    </div>
    <?php if ($ungraded === []): ?>
        <p class="text-muted mb-0">No unmarked essays for this lesson.</p>
    <?php endif; ?>
    <?php foreach ($ungraded as $row): ?>
        <article class="border rounded-4 p-3 mb-3" id="mark-a<?= (int)$row['attempt_id'] ?>-q<?= (int)$row['question_id'] ?>">
            <p class="mb-1"><strong><?= $h((string)($row['full_name'] ?: $row['username'] ?: 'Student')) ?></strong>
                · <?= $h((string)$row['item_title']) ?></p>
            <p class="small text-muted"><?= $h((string)$row['submitted_at']) ?></p>
            <p><?= nl2br($h((string)$row['prompt'])) ?></p>
            <div class="bg-body-tertiary rounded-3 p-3 mb-3"><?= nl2br($h((string)$row['essay_text'])) ?></div>
            <form method="post" class="row g-2">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="grade_essay">
                <input type="hidden" name="timetable_id" value="<?= $timetableId ?>">
                <?= $queueFields ?>
                <input type="hidden" name="attempt_id" value="<?= (int)$row['attempt_id'] ?>">
                <input type="hidden" name="question_id" value="<?= (int)$row['question_id'] ?>">
                <?php $gradeCriteria = $modules->criteriaForQuestion((int)$row['question_id']); ?>
                <?php if ($gradeCriteria !== []): ?>
                    <div class="col-12">
                        <p class="small mb-1">Mark scheme — ticking criteria fills the mark. You can still type a different mark.</p>
                        <?php foreach ($gradeCriteria as $criterion): ?>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="criteria[]" value="<?= (int)$criterion['id'] ?>" data-criterion-marks="<?= $h((string)$criterion['marks']) ?>">
                                <label class="form-check-label"><?= $h((string)$criterion['label']) ?> · <?= $h(LessonResultBuilder::formatMark((float)$criterion['marks'])) ?></label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <div class="col-md-2">
                    <label class="form-label">Marks / <?= $h((string)$row['marks']) ?></label>
                    <input class="form-control" type="number" min="0" step="0.5" max="<?= $h((string)$row['marks']) ?>" name="marks" data-mark-input required>
                    <input type="hidden" name="marks_edited" value="0" data-marks-edited>
                </div>
                <div class="col-md-8">
                    <label class="form-label">Comment</label>
                    <input class="form-control" name="comment">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button class="btn btn-primary w-100" type="submit">Save mark</button>
                </div>
            </form>
        </article>
    <?php endforeach; ?>
    <?php if ($lessonSubmissions !== []): ?>
        <h3 class="h6 mt-4">Assignments and homework</h3>
        <?php foreach ($lessonSubmissions as $submission): ?>
            <article class="border rounded-4 p-3 mb-3" id="mark-s<?= (int)$submission['id'] ?>">
                <p class="mb-1"><strong><?= $h((string)$submission['student_name']) ?></strong> · <?= $h((string)$submission['item_title']) ?></p>
                <p class="small text-muted"><?= $h(LearningModuleService::submissionLabel((string)$submission['status'])) ?><?= !empty($submission['due_at']) ? ' · due ' . $h(date('d M Y, g:i A', strtotime((string)$submission['due_at']))) : '' ?></p>
                <?php if (trim((string)($submission['body_text'] ?? '')) !== ''): ?>
                    <div class="bg-body-tertiary rounded-3 p-3 mb-3"><?= nl2br($h((string)$submission['body_text'])) ?></div>
                <?php endif; ?>
                <?php if (!empty($submission['file_key'])): ?>
                    <p><a href="<?= $h(BASE_URL . 'campus/lesson_file.php?submission=' . (int)$submission['id'] . '&lesson=' . $timetableId) ?>">Open file</a></p>
                <?php endif; ?>
                <form method="post" class="row g-2">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="review_submission">
                    <input type="hidden" name="timetable_id" value="<?= $timetableId ?>">
                    <?= $queueFields ?>
                    <input type="hidden" name="submission_id" value="<?= (int)$submission['id'] ?>">
                    <input type="hidden" name="max_marks" value="<?= $h((string)($submission['max_marks'] ?? 0)) ?>">
                    <div class="col-md-2">
                        <label class="form-label">Marks / <?= $h((string)($submission['max_marks'] ?? '')) ?></label>
                        <input class="form-control" type="number" min="0" step="0.5" name="marks" value="<?= $h((string)($submission['marks_awarded'] ?? '')) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Comment</label>
                        <input class="form-control" name="comment" value="<?= $h((string)($submission['teacher_comment'] ?? '')) ?>">
                    </div>
                    <div class="col-md-4 d-flex align-items-end gap-2">
                        <button class="btn btn-primary" name="review" value="mark" type="submit">Mark</button>
                        <button class="btn btn-outline-primary" name="review" value="return" type="submit">Return</button>
                        <button class="btn btn-outline-secondary" name="review" value="resubmit" type="submit">Request resubmission</button>
                    </div>
                </form>
            </article>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
<script>
document.querySelectorAll('article form').forEach(function (form) {
    var input = form.querySelector('[data-mark-input]');
    var edited = form.querySelector('[data-marks-edited]');
    if (!input || !edited) return;
    input.addEventListener('input', function () { edited.value = '1'; });
    form.querySelectorAll('[data-criterion-marks]').forEach(function (box) {
        box.addEventListener('change', function () {
            if (edited.value === '1') return;
            var total = 0;
            form.querySelectorAll('[data-criterion-marks]:checked').forEach(function (checked) {
                total += parseFloat(checked.getAttribute('data-criterion-marks') || '0');
            });
            input.value = String(total);
        });
    });
});
</script>
<?php elseif ($tab === 'results'): ?>
<?php
    $filter = strtolower(trim((string)($_GET['filter'] ?? 'all')));
    if (!in_array($filter, ['all', 'not_started', 'in_progress', 'complete'], true)) {
        $filter = 'all';
    }
    $totals = $classResults['totals'] ?? ['students' => 0, 'started' => 0, 'completed' => 0, 'not_started' => 0, 'in_progress' => 0, 'essays_pending' => 0];
    $rows = $classResults['students'] ?? [];
    $statusLabel = [
        'not_started' => 'Not started',
        'in_progress' => 'In progress',
        'complete' => 'Complete',
    ];
?>
<div class="card border-0 shadow-sm rounded-4 p-4">
    <h2 class="h5 mb-3">Class results</h2>
    <div class="d-flex flex-wrap gap-2 mb-3">
        <a class="btn btn-sm btn-outline-secondary" href="<?= $h($builderUrl . '&tab=results&export=csv') ?>">Download CSV</a>
    </div>
    <div class="ol-results-stat">
        <span><?= (int)$totals['students'] ?> enrolled</span>
        <span><?= (int)$totals['started'] ?> started</span>
        <span><?= (int)$totals['in_progress'] ?> in progress</span>
        <span><?= (int)$totals['completed'] ?> finished</span>
        <span><?= (int)$totals['not_started'] ?> not started</span>
        <?php if ((int)$totals['essays_pending'] > 0): ?>
            <span><?= (int)$totals['essays_pending'] ?> essays to mark</span>
        <?php endif; ?>
    </div>
    <div class="d-flex flex-wrap gap-2 mb-3">
        <?php foreach (['all' => 'All', 'in_progress' => 'In progress', 'complete' => 'Finished', 'not_started' => 'Not started'] as $key => $label): ?>
            <a class="btn btn-sm <?= $filter === $key ? 'btn-primary' : 'btn-outline-secondary' ?>" href="<?= $h($builderUrl . '&tab=results&filter=' . $key) ?>"><?= $h($label) ?></a>
        <?php endforeach; ?>
    </div>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>Student</th>
                    <th>Progress</th>
                    <th>Now on</th>
                    <th>Quizzes</th>
                    <th>Last seen</th>
                </tr>
            </thead>
            <tbody>
            <?php
            $shown = 0;
            foreach ($rows as $row):
                if ($filter !== 'all' && (string)$row['status'] !== $filter) {
                    continue;
                }
                $shown++;
                $quizBits = [];
                foreach ($row['quizzes'] as $quiz) {
                    $score = $quiz['score'] === null ? '—' : rtrim(rtrim(number_format((float)$quiz['score'], 1), '0'), '.');
                    $max = $quiz['max_score'] === null ? '' : rtrim(rtrim(number_format((float)$quiz['max_score'], 1), '0'), '.');
                    $quizBits[] = $h((string)$quiz['title']) . ': ' . $h($score) . ($max !== '' ? '/' . $h($max) : '');
                }
            ?>
                <tr>
                    <td>
                        <?= $h((string)$row['name']) ?>
                        <div class="small text-muted"><?= $h($statusLabel[(string)$row['status']] ?? (string)$row['status']) ?>
                            <?php if ((int)$row['essays_pending'] > 0): ?>
                                · <a href="<?= $h($builderUrl . '&tab=grade') ?>"><?= (int)$row['essays_pending'] ?> essay to mark</a>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td>
                        <?= (int)$row['completed_count'] ?> / <?= (int)$row['total'] ?>
                        <div class="small text-muted"><?= (int)$row['percent'] ?>%</div>
                    </td>
                    <td><?= $h((string)($row['current_title'] !== '' ? $row['current_title'] : '—')) ?></td>
                    <td class="small"><?= $quizBits !== [] ? implode('<br>', $quizBits) : '—' ?></td>
                    <td class="small text-muted">
                        <?= $row['last_seen_at'] !== '' ? $h(date('d M, g:i A', strtotime((string)$row['last_seen_at']))) : '—' ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if ($rows === []): ?>
                <tr><td colspan="5" class="text-muted p-4">No students are enrolled in this class.</td></tr>
            <?php elseif ($shown === 0): ?>
                <tr><td colspan="5" class="text-muted p-4">No students match this filter.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php else: ?>
<div class="card border-0 shadow-sm rounded-4 p-4 mb-4" id="lesson-resources">
    <h2 class="h5">Resources</h2>
    <p class="text-muted">Save a file or link once, then add it to this lesson. The library copy stays separate from the lesson.</p>
    <form method="post" class="row g-2 mb-3">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save_resource_url">
        <input type="hidden" name="timetable_id" value="<?= $timetableId ?>">
        <div class="col-md-4"><input class="form-control" name="title" placeholder="Title" required></div>
        <div class="col-md-2">
            <select class="form-select" name="resource_type">
                <option value="url">Link</option>
                <option value="video">Video link</option>
                <option value="pdf">PDF link</option>
                <option value="ppt">PowerPoint link</option>
            </select>
        </div>
        <div class="col-md-4"><input class="form-control" type="url" name="url" placeholder="https://" required></div>
        <div class="col-md-2"><button class="btn btn-outline-primary w-100" type="submit">Save link</button></div>
    </form>
    <form method="post" enctype="multipart/form-data" class="row g-2 mb-3">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save_resource_file">
        <input type="hidden" name="timetable_id" value="<?= $timetableId ?>">
        <div class="col-md-4"><input class="form-control" name="title" placeholder="File title"></div>
        <div class="col-md-2">
            <select class="form-select" name="resource_type">
                <option value="pdf">PDF</option>
                <option value="ppt">PowerPoint</option>
                <option value="image">Image</option>
                <option value="document">Document</option>
            </select>
        </div>
        <div class="col-md-4"><input class="form-control" type="file" name="resource_file" required></div>
        <div class="col-md-2"><button class="btn btn-outline-primary w-100" type="submit">Upload</button></div>
    </form>
    <?php if ($resourceRows === []): ?>
        <p class="small text-muted mb-0">No resources yet.</p>
    <?php endif; ?>
    <?php foreach ($resourceRows as $resource): ?>
        <div class="d-flex flex-wrap justify-content-between gap-2 border-top py-2">
            <div>
                <strong><?= $h((string)$resource['title']) ?></strong>
                <div class="small text-muted"><?= $h((string)$resource['resource_type']) ?></div>
            </div>
            <div class="d-flex gap-2">
                <form method="post"><?= csrf_field() ?>
                    <input type="hidden" name="action" value="use_resource">
                    <input type="hidden" name="timetable_id" value="<?= $timetableId ?>">
                    <input type="hidden" name="resource_id" value="<?= (int)$resource['id'] ?>">
                    <button class="btn btn-sm btn-outline-primary" type="submit">Use in lesson</button>
                </form>
                <form method="post"><?= csrf_field() ?>
                    <input type="hidden" name="action" value="archive_resource">
                    <input type="hidden" name="timetable_id" value="<?= $timetableId ?>">
                    <input type="hidden" name="resource_id" value="<?= (int)$resource['id'] ?>">
                    <button class="btn btn-sm btn-outline-secondary" type="submit">Archive</button>
                </form>
            </div>
        </div>
    <?php endforeach; ?>
</div>
<p class="text-muted" id="lesson-sequence">Clips come from the class recording. Add notes, questions, assignments, or a saved resource. Students cannot skip a required item.</p>

<div class="ol-insert mb-3" style="margin-left:0">
    <form method="post" class="d-inline">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add_page">
        <input type="hidden" name="timetable_id" value="<?= $timetableId ?>">
        <input type="hidden" name="after_item_id" value="0">
        <input type="hidden" name="title" value="Introduction">
        <button class="btn btn-sm btn-outline-secondary" type="submit">+ Text page at start</button>
    </form>
</div>

<?php if ($items === []): ?>
    <div class="alert alert-warning">No clips yet. Upload the split class video on Class recordings, then click <strong>Sync new video clips</strong>.</div>
<?php endif; ?>

<?php foreach ($items as $i => $item):
    $type = OnlineLessonService::normalizeItemType((string)$item['item_type']);
    $kind = match ($type) {
        'activity' => 'Question activity',
        'page' => 'Text page',
        'external_link' => 'External Link',
        default => 'Video clip',
    };
    $linkLabel = $type === 'external_link'
        ? OnlineLessonService::externalLinkLabel((string)($item['link_url'] ?? ''))
        : '';
?>
    <div class="ol-builder-item">
        <span class="ol-num"><?= $i + 1 ?></span>
        <div class="flex-grow-1">
            <form method="post" class="d-flex flex-wrap gap-2 align-items-center">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="rename_item">
                <input type="hidden" name="timetable_id" value="<?= $timetableId ?>">
                <input type="hidden" name="item_id" value="<?= (int)$item['id'] ?>">
                <input class="form-control" name="title" value="<?= $h((string)$item['title']) ?>" style="max-width:420px">
                <button class="btn btn-sm btn-outline-primary" type="submit">Rename</button>
            </form>
            <?php if ($planSections !== []): ?>
            <form method="post" class="d-flex gap-2 align-items-center mt-1">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="assign_section">
                <input type="hidden" name="timetable_id" value="<?= $timetableId ?>">
                <input type="hidden" name="item_id" value="<?= (int)$item['id'] ?>">
                <label class="small text-muted mb-0" for="section-<?= (int)$item['id'] ?>">Section</label>
                <select class="form-select form-select-sm" id="section-<?= (int)$item['id'] ?>" name="section_id" style="max-width:240px" onchange="this.form.submit()">
                    <option value="0">No section</option>
                    <?php foreach ($planSections as $section): ?>
                        <option value="<?= (int)$section['id'] ?>" <?= (int)($item['section_id'] ?? 0) === (int)$section['id'] ? 'selected' : '' ?>><?= $h((string)$section['title']) ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
            <?php endif; ?>
            <div class="small text-muted mt-1">
                <?= $h($kind) ?>
                <?php if ($type === 'activity'): ?>
                    · <?= (int)($item['question_count'] ?? 0) ?> question(s)
                    · <a href="<?= $h(campus_online_lesson_url($timetableId, (int)$item['activity_id'])) ?>">Edit questions</a>
                <?php elseif ($type === 'page'): ?>
                    · <a href="<?= $h($builderUrl . '&page=' . (int)$item['id']) ?>">Edit page</a>
                <?php elseif ($type === 'external_link'): ?>
                    · <?= $h($linkLabel) ?>
                    · <button type="button" class="btn btn-link btn-sm p-0 align-baseline" data-ol-link-open
                        data-item-id="<?= (int)$item['id'] ?>"
                        data-after="0"
                        data-title="<?= $h((string)$item['title']) ?>"
                        data-url="<?= $h((string)($item['link_url'] ?? '')) ?>"
                        data-new-tab="<?= (int)($item['open_new_tab'] ?? 1) === 1 ? '1' : '0' ?>"
                        data-required="<?= (int)($item['required'] ?? 1) === 1 ? '1' : '0' ?>">Edit</button>
                <?php endif; ?>
            </div>
        </div>
        <div class="d-flex flex-column gap-1">
            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="move_item">
                <input type="hidden" name="timetable_id" value="<?= $timetableId ?>">
                <input type="hidden" name="item_id" value="<?= (int)$item['id'] ?>">
                <input type="hidden" name="direction" value="up">
                <button class="btn btn-sm btn-outline-secondary" type="submit" <?= $i === 0 ? 'disabled' : '' ?>>↑</button>
            </form>
            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="move_item">
                <input type="hidden" name="timetable_id" value="<?= $timetableId ?>">
                <input type="hidden" name="item_id" value="<?= (int)$item['id'] ?>">
                <input type="hidden" name="direction" value="down">
                <button class="btn btn-sm btn-outline-secondary" type="submit" <?= $i === count($items) - 1 ? 'disabled' : '' ?>>↓</button>
            </form>
            <?php if ($type !== 'video'): ?>
            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="duplicate_item">
                <input type="hidden" name="timetable_id" value="<?= $timetableId ?>">
                <input type="hidden" name="item_id" value="<?= (int)$item['id'] ?>">
                <button class="btn btn-sm btn-outline-primary" type="submit">Copy</button>
            </form>
            <form method="post" onsubmit="return confirm('Remove this item from the lesson?');">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete_item">
                <input type="hidden" name="timetable_id" value="<?= $timetableId ?>">
                <input type="hidden" name="item_id" value="<?= (int)$item['id'] ?>">
                <button class="btn btn-sm btn-outline-danger" type="submit">Remove</button>
            </form>
            <?php endif; ?>
        </div>
    </div>
    <div class="ol-insert">
        <form method="post" class="d-flex flex-wrap gap-2 align-items-center">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add_content">
            <input type="hidden" name="timetable_id" value="<?= $timetableId ?>">
            <input type="hidden" name="after_item_id" value="<?= (int)$item['id'] ?>">
            <label class="visually-hidden" for="add-content-<?= (int)$item['id'] ?>">Add content</label>
            <select class="form-select form-select-sm" id="add-content-<?= (int)$item['id'] ?>" name="content_type">
                <option value="page">Text</option>
                <option value="mcq">MCQ</option>
                <option value="short">Short answer</option>
                <option value="exam">Exam question</option>
                <option value="essay">Essay</option>
                <option value="assignment">Assignment</option>
                <option value="homework">Homework</option>
            </select>
            <button class="btn btn-sm btn-primary" type="submit">Add content</button>
        </form>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add_activity">
            <input type="hidden" name="timetable_id" value="<?= $timetableId ?>">
            <input type="hidden" name="after_item_id" value="<?= (int)$item['id'] ?>">
            <input type="hidden" name="activity_type" value="mcq">
            <button class="btn btn-sm btn-outline-primary" type="submit">+ MCQ after this</button>
        </form>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add_activity">
            <input type="hidden" name="timetable_id" value="<?= $timetableId ?>">
            <input type="hidden" name="after_item_id" value="<?= (int)$item['id'] ?>">
            <input type="hidden" name="activity_type" value="essay">
            <button class="btn btn-sm btn-outline-primary" type="submit">+ Essay after this</button>
        </form>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add_page">
            <input type="hidden" name="timetable_id" value="<?= $timetableId ?>">
            <input type="hidden" name="after_item_id" value="<?= (int)$item['id'] ?>">
            <input type="hidden" name="title" value="Notes">
            <button class="btn btn-sm btn-outline-secondary" type="submit">+ Text page after this</button>
        </form>
        <button type="button" class="btn btn-sm btn-outline-secondary" data-ol-link-open
            data-item-id="0"
            data-after="<?= (int)$item['id'] ?>"
            data-title=""
            data-url=""
            data-new-tab="1"
            data-required="1">+ External link after this</button>
    </div>
<?php endforeach; ?>
<?php endif; ?>
<?php endif; ?>

<div class="modal fade" id="olLinkModal" tabindex="-1" aria-labelledby="olLinkModalTitle" aria-hidden="true">
    <div class="modal-dialog">
        <form method="post" class="modal-content">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save_link">
            <input type="hidden" name="timetable_id" value="<?= $timetableId ?>">
            <input type="hidden" name="item_id" value="0">
            <input type="hidden" name="after_item_id" value="0">
            <div class="modal-header">
                <h2 class="modal-title h5" id="olLinkModalTitle">External link</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label" for="ol-link-title">Title</label>
                    <input class="form-control" id="ol-link-title" name="title" maxlength="200" placeholder="Topic 1.1 – Computer Systems">
                </div>
                <div class="mb-3">
                    <label class="form-label" for="ol-link-url">URL</label>
                    <input class="form-control" id="ol-link-url" name="link_url" type="url" inputmode="url" maxlength="500" placeholder="https://edex.college/ppt/Topic_1_1.php">
                </div>
                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" id="ol-link-new" name="open_new_tab" value="1" checked>
                    <label class="form-check-label" for="ol-link-new">Open in new tab</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="ol-link-required" name="required" value="1" checked>
                    <label class="form-check-label" for="ol-link-required">Student must open this before continuing</label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-primary" type="submit">Save Link</button>
            </div>
        </form>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
<script>
(function () {
    var modalEl = document.getElementById('olLinkModal');
    if (!modalEl || !window.bootstrap) return;
    var modal = new bootstrap.Modal(modalEl);
    var form = modalEl.querySelector('form');
    document.querySelectorAll('[data-ol-link-open]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            form.querySelector('[name="item_id"]').value = btn.getAttribute('data-item-id') || '0';
            form.querySelector('[name="after_item_id"]').value = btn.getAttribute('data-after') || '0';
            form.querySelector('[name="title"]').value = btn.getAttribute('data-title') || '';
            form.querySelector('[name="link_url"]').value = btn.getAttribute('data-url') || '';
            form.querySelector('[name="open_new_tab"]').checked = btn.getAttribute('data-new-tab') !== '0';
            form.querySelector('[name="required"]').checked = btn.getAttribute('data-required') !== '0';
            modal.show();
        });
    });
})();
</script>
