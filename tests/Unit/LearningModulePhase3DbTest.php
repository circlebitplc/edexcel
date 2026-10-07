<?php
declare(strict_types=1);

use Edexcel\Services\CourseService;
use Edexcel\Services\LessonAccessService;
use Edexcel\Services\LessonAiService;
use Edexcel\Services\LessonPlannerService;
use Edexcel\Services\LessonVersionService;
use Edexcel\Services\OnlineLessonService;
use Edexcel\Services\ResourceLibraryService;
use Edexcel\Services\StudentStudyService;
use PHPUnit\Framework\TestCase;

/**
 * Phase 3 services against an in-memory database: pools, versions, cross-class copy, scheduling,
 * prerequisites, bookmarks and notes, resource versions, AI drafts, bulk drafts and the learning path.
 */
final class LearningModulePhase3DbTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->sqliteCreateFunction('NOW', static fn (): string => date('Y-m-d H:i:s'), 0);
        foreach ($this->schema() as $sql) {
            $this->pdo->exec($sql);
        }
        $this->pdo->exec("INSERT INTO users (id, username) VALUES (1, 'admin'), (2, 'teacher_a'), (3, 'teacher_b'), (50, 'stu50'), (51, 'stu51')");
        $this->pdo->exec("INSERT INTO subjects (id, name) VALUES (1, 'Computer Science')");
        $this->pdo->exec("INSERT INTO student_classes (id, name) VALUES (1, 'CS A'), (2, 'CS B'), (3, 'CS C')");
        $this->pdo->exec("INSERT INTO teachers (id, name, user_id) VALUES (10, 'Teacher A', 2), (11, 'Teacher B', 3)");
        $this->pdo->exec("INSERT INTO timetable (id, date, start_time, end_time, class_id, subject_id, teacher_id, delivery_mode) VALUES
            (100, '2026-05-01', '08:00:00', '09:00:00', 1, 1, 10, 'online'),
            (101, '2026-05-08', '08:00:00', '09:00:00', 1, 1, 10, 'physical'),
            (102, '2026-05-15', '08:00:00', '09:00:00', 1, 1, 10, 'hybrid'),
            (200, '2026-05-02', '10:00:00', '11:30:00', 2, 1, 10, 'online'),
            (300, '2026-05-03', '10:00:00', '11:00:00', 3, 1, 11, 'online')");
        $this->pdo->exec('INSERT INTO student_enrollments (student_id, class_id, teacher_id) VALUES (50, 1, NULL), (51, 1, 10), (51, 2, NULL)');
    }

    public function testPoolAttemptStoresShownQuestionsAndScoresOnlyThose(): void
    {
        [$lessonId, $itemId, $activityId, $qids] = $this->lessonWithQuiz(100, 6);
        $this->pdo->prepare('UPDATE online_lesson_activities SET draw_count = 3, pass_percent = 100 WHERE id = ?')->execute([$activityId]);
        $service = new OnlineLessonService($this->pdo);
        $activity = $service->activity($activityId);

        $session = $service->attemptSession(50, $itemId, $activity, $service->studentQuestions($activityId), true);
        $again = $service->attemptSession(50, $itemId, $activity, $service->studentQuestions($activityId), false);
        $this->assertCount(3, $session['question_ids']);
        $this->assertSame($session['question_ids'], $again['question_ids'], 'reloading the page shows the same questions');

        $answers = [];
        foreach ($session['question_ids'] as $i => $qid) {
            $answers[$qid] = ['choice' => $i === 0 ? 1 : 0];
        }
        $service->submitActivity(50, $itemId, $answers);
        $attempt = $service->latestAttempt(50, $itemId);
        $this->assertSame($session['question_ids'], array_map('intval', json_decode((string)$attempt['question_ids_json'], true)));
        $this->assertEqualsWithDelta(3.0, (float)$attempt['max_score'], 0.001, 'the maximum counts only the drawn questions');
        $this->assertEqualsWithDelta(2.0, (float)$attempt['score'], 0.001);
        $answered = $this->pdo->query('SELECT COUNT(*) FROM online_lesson_answers WHERE attempt_id = ' . (int)$attempt['id'])->fetchColumn();
        $this->assertSame(3, (int)$answered);

        $next = $service->attemptSession(50, $itemId, $activity, $service->studentQuestions($activityId), true);
        $this->assertSame(2, $next['attempt_no']);
    }

    public function testUnreviewedAiQuestionsAreHiddenFromStudentsUntilApproved(): void
    {
        [$lessonId, $itemId, $activityId, $qids] = $this->lessonWithQuiz(100, 2);
        $service = new OnlineLessonService($this->pdo);
        $service->markQuestionAiGenerated($qids[1], true);
        $this->assertSame([$qids[0]], array_map(static fn ($q) => (int)$q['id'], $service->studentQuestions($activityId)));
        $this->assertCount(2, $service->questions($activityId), 'teachers still see it');
        $service->updateQuestion($qids[1], 'Reviewed prompt', ['A', 'B'], 0, 1.0, 'Because.');
        $this->assertCount(2, $service->studentQuestions($activityId), 'saving the question approves it');
    }

    public function testVersionsSkipUnchangedContentAndNeverStoreStudentWork(): void
    {
        [$lessonId, $itemId, $activityId, $qids] = $this->lessonWithQuiz(100, 2);
        $this->pdo->exec("INSERT INTO online_lesson_item_state (student_id, item_id, status) VALUES (50, $itemId, 'completed')");
        $this->pdo->exec("INSERT INTO online_lesson_attempts (student_id, item_id, activity_id, attempt_no, status, score, max_score, passed) VALUES (50, $itemId, $activityId, 1, 'submitted', 7, 9, 0)");
        $versions = new LessonVersionService($this->pdo);
        $v1 = $versions->createVersion($lessonId, 2, 'saved');
        $this->assertSame($v1, $versions->createVersion($lessonId, 2, 'saved'), 'no new version when nothing changed');
        $json = (string)$this->pdo->query("SELECT snapshot_json FROM online_lesson_versions WHERE id = $v1")->fetchColumn();
        foreach (['"student_id"', '"score"', '"max_score"', '"status"', '"marks_awarded"', '"essay_text"', '"completed_at"'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $json);
        }
        $this->pdo->exec("UPDATE online_lessons SET title = 'Changed' WHERE id = $lessonId");
        $v2 = $versions->createVersion($lessonId, 2, 'saved');
        $this->assertNotSame($v1, $v2);
        $this->assertCount(2, $versions->versions($lessonId));
    }

    public function testRestoreCreatesNewVersionAndKeepsItemsStudentsUsed(): void
    {
        [$lessonId, $itemId, $activityId, $qids] = $this->lessonWithQuiz(100, 2);
        $versions = new LessonVersionService($this->pdo);
        $v1 = $versions->createVersion($lessonId, 2, 'saved');
        $service = new OnlineLessonService($this->pdo);
        $page = $service->addPageAfter($lessonId, $itemId, 'Extra notes', 'Body');
        $used = $service->addPageAfter($lessonId, $itemId, 'Used page', 'Body');
        $this->pdo->exec("INSERT INTO online_lesson_item_state (student_id, item_id, status) VALUES (50, {$used['id']}, 'completed')");
        $this->pdo->prepare('UPDATE online_lesson_questions SET prompt = ? WHERE id = ?')->execute(['Edited', $qids[0]]);
        $versions->createVersion($lessonId, 2, 'saved');

        $result = $versions->restore($lessonId, $v1, 2);
        $titles = array_column($service->items($lessonId), 'title');
        $this->assertNotContains('Extra notes', $titles);
        $this->assertContains('Used page', $titles, 'items with student progress are never deleted');
        $this->assertCount(1, $result['kept']);
        $this->assertSame('Question 1', (string)$this->pdo->query("SELECT prompt FROM online_lesson_questions WHERE id = {$qids[0]}")->fetchColumn());
        $reasons = array_column($versions->versions($lessonId), 'reason');
        $this->assertSame(['restored', 'saved', 'saved'], $reasons, 'restore adds a version; the pre-restore state was already saved so no duplicate is stored');
        $this->pdo->prepare('UPDATE online_lesson_questions SET prompt = ? WHERE id = ?')->execute(['Unsaved edit', $qids[0]]);
        $versions->restore($lessonId, $v1, 2);
        $this->assertSame(['restored', 'before_restore', 'restored', 'saved', 'saved'], array_column($versions->versions($lessonId), 'reason'), 'unsaved edits are kept as a before-restore version');
        $this->assertSame(1, (int)$this->pdo->query("SELECT COUNT(*) FROM online_lesson_item_state WHERE item_id = {$used['id']}")->fetchColumn());
    }

    public function testCopyToAnotherClassCreatesDraftWithoutProgressOrRecording(): void
    {
        [$lessonId, $itemId, $activityId, $qids] = $this->lessonWithQuiz(100, 2);
        $this->pdo->exec("UPDATE online_lessons SET published = 1, recording_id = 77 WHERE id = $lessonId");
        $this->pdo->exec("INSERT INTO online_lesson_items (lesson_id, item_type, title, sort_order, video_asset_id) VALUES ($lessonId, 'video', 'Source clip', 0, 900)");
        $this->pdo->exec("INSERT INTO online_lesson_objectives (lesson_id, body, sort_order) VALUES ($lessonId, 'Explain the ALU', 1)");
        $objectiveId = (int)$this->pdo->lastInsertId();
        $this->pdo->exec("INSERT INTO online_lesson_objective_links (objective_id, question_id, item_id) VALUES ($objectiveId, {$qids[0]}, 0)");
        $this->pdo->exec("INSERT INTO online_lesson_item_state (student_id, item_id, status) VALUES (50, $itemId, 'completed')");
        $this->pdo->exec("INSERT INTO online_lessons (timetable_id, title) VALUES (200, 'Target')");
        $targetId = (int)$this->pdo->lastInsertId();

        $created = (new LessonVersionService($this->pdo))->copyInto($lessonId, $targetId, 2, [555]);
        $target = $this->pdo->query("SELECT * FROM online_lessons WHERE id = $targetId")->fetch(PDO::FETCH_ASSOC);
        $this->assertSame(0, (int)$target['published'], 'the copy is a draft');
        $this->assertSame('Target', $target['title'], 'the copy keeps the title dated for its own class');
        $this->assertNull($target['recording_id'], 'the source recording is not copied');
        $this->assertSame(2, $created);
        $items = (new OnlineLessonService($this->pdo))->items($targetId);
        $video = array_values(array_filter($items, static fn ($i) => $i['item_type'] === 'video'));
        $this->assertSame(555, (int)$video[0]['video_asset_id'], 'video positions use the target class clips');
        $activityCopy = array_values(array_filter($items, static fn ($i) => $i['item_type'] === 'activity'))[0];
        $this->assertNotSame($activityId, (int)$activityCopy['activity_id']);
        $this->assertSame(2, (int)$this->pdo->query('SELECT COUNT(*) FROM online_lesson_questions WHERE activity_id = ' . (int)$activityCopy['activity_id'])->fetchColumn());
        $this->assertSame(0, (int)$this->pdo->query('SELECT COUNT(*) FROM online_lesson_item_state WHERE item_id IN (SELECT id FROM online_lesson_items WHERE lesson_id = ' . $targetId . ')')->fetchColumn());
        $links = (int)$this->pdo->query("SELECT COUNT(*) FROM online_lesson_objective_links l JOIN online_lesson_objectives o ON o.id = l.objective_id WHERE o.lesson_id = $targetId")->fetchColumn();
        $this->assertSame(1, $links, 'objective links point at the copied question');

        $this->pdo->exec('INSERT INTO online_lesson_item_state (student_id, item_id, status) VALUES (51, ' . (int)$activityCopy['id'] . ", 'incomplete')");
        $this->expectException(RuntimeException::class);
        (new LessonVersionService($this->pdo))->copyInto($lessonId, $targetId, 2, []);
    }

    public function testScheduledPublishAndUnpublishKeepProgress(): void
    {
        [$lessonId, $itemId] = $this->lessonWithQuiz(100, 1);
        $this->pdo->exec("INSERT INTO online_lesson_item_state (student_id, item_id, status) VALUES (50, $itemId, 'completed')");
        $access = new LessonAccessService($this->pdo);
        $now = strtotime('2026-05-01 12:00:00');
        $access->saveSchedule($lessonId, '2026-05-02 08:00:00', '2026-06-01 08:00:00', true, $now);
        $row = $this->pdo->query("SELECT * FROM online_lessons WHERE id = $lessonId")->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('scheduled', OnlineLessonService::publicationState($row, $now));
        $this->assertSame('published', OnlineLessonService::publicationState($row, strtotime('2026-05-03 08:00:00')), 'evaluated at request time, no cron needed');
        $this->assertSame('closed', OnlineLessonService::publicationState($row, strtotime('2026-06-02 08:00:00')));
        $this->assertSame(1, (int)$this->pdo->query("SELECT COUNT(*) FROM online_lesson_item_state WHERE item_id = $itemId")->fetchColumn());

        $this->expectException(RuntimeException::class);
        $access->saveSchedule($lessonId, '2026-05-10 08:00:00', '2026-05-09 08:00:00', true, $now);
    }

    public function testPrerequisitesBlockUntilMetAndRejectCyclesAndOtherClasses(): void
    {
        [$a, $itemA] = $this->lessonWithQuiz(100, 1);
        [$b] = $this->lessonWithQuiz(101, 1);
        [$c] = $this->lessonWithQuiz(102, 1);
        [$other] = $this->lessonWithQuiz(200, 1);
        $this->pdo->exec('UPDATE online_lessons SET published = 1');
        $access = new LessonAccessService($this->pdo);
        $access->addPrerequisite($b, $a, 100);
        $access->addPrerequisite($c, $b, 50);
        $unmet = $access->unmetFor(50, $b);
        $this->assertSame($a, $unmet[0]['lesson_id']);
        $this->assertSame(0, $unmet[0]['percent']);
        $this->pdo->exec("INSERT INTO online_lesson_item_state (student_id, item_id, status) VALUES (50, $itemA, 'completed')");
        $this->assertSame([], $access->unmetFor(50, $b));
        $this->assertNotSame([], $access->unmetFor(51, $b), 'each student is checked separately');

        try {
            $access->addPrerequisite($a, $c, 100);
            $this->fail('circular prerequisite accepted');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('circular', $e->getMessage());
        }
        $this->expectExceptionMessage('same class');
        $access->addPrerequisite($b, $other, 100);
    }

    public function testBookmarksAndNotesArePrivateToEachStudent(): void
    {
        [$lessonId, $itemId, $activityId, $qids] = $this->lessonWithQuiz(100, 1);
        $study = new StudentStudyService($this->pdo);
        $this->assertTrue($study->toggleBookmark(50, $lessonId, $itemId, $qids[0]));
        $study->saveNote(50, $lessonId, 0, $itemId, 'My private note');
        $this->assertSame(1, $study->myBookmarks(50)['total']);
        $this->assertSame(0, $study->myBookmarks(51)['total']);
        $this->assertSame([], $study->notesForLesson(51, $lessonId));
        $this->assertSame('My private note', $study->notesForLesson(50, $lessonId)['0:' . $itemId]);

        $bookmarkId = (int)$this->pdo->query('SELECT id FROM online_lesson_bookmarks')->fetchColumn();
        $this->assertFalse($study->removeBookmark(51, $bookmarkId));
        $this->assertSame(1, $study->myBookmarks(50)['total'], 'another student cannot remove it');
        $this->assertFalse($study->toggleBookmark(50, $lessonId, $itemId, $qids[0]));
        $study->saveNote(50, $lessonId, 0, $itemId, '   ');
        $this->assertSame(0, $study->myNotes(50)['total'], 'an empty note is deleted');

        [$otherLesson, $otherItem] = $this->lessonWithQuiz(300, 1);
        $this->expectException(RuntimeException::class);
        $study->toggleBookmark(50, $lessonId, $otherItem);
    }

    public function testResourceVersionsKeepOldRowsAndRespectOwnership(): void
    {
        $library = new ResourceLibraryService($this->pdo);
        $id = $library->create(2, ['title' => 'Revision site', 'type' => 'url', 'url' => 'https://example.com/v1', 'tags' => 'CPU'], null);
        $this->pdo->exec("INSERT INTO online_lessons (timetable_id, title) VALUES (100, 'L')");
        $lessonId = (int)$this->pdo->lastInsertId();
        $this->pdo->exec("INSERT INTO online_lesson_items (lesson_id, item_type, title, sort_order, resource_id) VALUES ($lessonId, 'resource', 'Site', 1, $id)");

        $v2 = $library->newVersion($id, 2, false, null, 'https://example.com/v2');
        $this->assertSame($v2, $library->latestVersionId($id));
        $this->assertSame($id, (int)$this->pdo->query("SELECT resource_id FROM online_lesson_items WHERE lesson_id = $lessonId")->fetchColumn(), 'existing lessons keep the old version');
        $this->assertSame('https://example.com/v1', (string)$this->pdo->query("SELECT url FROM online_lesson_resources WHERE id = $id")->fetchColumn());
        $search = $library->search(2, false, ['tag' => 'CPU']);
        $this->assertSame([$v2], array_map(static fn ($r) => (int)$r['id'], $search['rows']), 'search lists the latest version only');
        $this->assertSame(1, $search['rows'][0]['used_in']);
        $this->assertCount(2, $library->versions($v2, 2, false));
        $this->assertSame(0, $library->search(3, false, [])['total'], 'other teachers do not see it');
        $this->assertSame(1, $library->search(1, true, [])['total'], 'admins see every latest resource');

        try {
            $library->newVersion($id, 2, false, null, 'https://example.com/v3');
            $this->fail('new version from a superseded row');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('latest copy', $e->getMessage());
        }
        $this->expectException(RuntimeException::class);
        $library->updateMeta($v2, 3, false, ['title' => 'Hijack']);
    }

    public function testAiDraftsAreStoredForReviewAndAppliedQuestionsStayHidden(): void
    {
        [$lessonId, $itemId, $activityId] = $this->lessonWithQuiz(100, 1);
        $calls = [];
        $fake = static function (string $system, string $user, int $max) use (&$calls): string {
            $calls[] = $system;
            return json_encode(['questions' => [
                ['prompt' => 'Which part of the CPU does arithmetic?', 'choices' => ['ALU', 'CU', 'RAM', 'Cache'], 'correct_index' => 0, 'explanation' => 'The ALU.', 'difficulty' => 'easy'],
                ['prompt' => 'Official exam question from the 2020 past paper', 'choices' => ['a', 'b'], 'correct_index' => 0],
            ]]);
        };
        $ai = new LessonAiService($this->pdo, $fake);
        $draftId = $ai->draftQuestions($lessonId, $activityId, 2, ['topic' => 'CPU', 'count' => 2, 'type' => 'mcq']);
        $this->assertStringContainsString('Never say or imply that a question is an official exam', $calls[0]);
        $draft = $ai->draft($draftId, $lessonId);
        $this->assertSame('draft', $draft['status']);
        $this->assertCount(1, $draft['output']['questions'], 'claims of official questions are dropped');
        $before = (int)$this->pdo->query("SELECT COUNT(*) FROM online_lesson_questions WHERE activity_id = $activityId")->fetchColumn();
        $this->assertSame(1, $before, 'nothing is added until the teacher applies the draft');

        $this->assertSame(1, $ai->applyQuestions($draftId, $lessonId, [0]));
        $added = $this->pdo->query("SELECT * FROM online_lesson_questions WHERE activity_id = $activityId ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        $this->assertSame(1, (int)$added['ai_generated']);
        $this->assertCount(1, (new OnlineLessonService($this->pdo))->studentQuestions($activityId));
        $this->assertSame('applied', $ai->draft($draftId, $lessonId)['status']);

        $this->expectException(RuntimeException::class);
        $ai->draft($draftId, $lessonId + 999);
    }

    public function testAiPlanOnlyAppliesToAnEmptyUnpublishedLesson(): void
    {
        $this->pdo->exec("INSERT INTO online_lessons (timetable_id, title) VALUES (101, 'Empty')");
        $lessonId = (int)$this->pdo->lastInsertId();
        $plan = ['objectives' => ['Describe the CPU'], 'sections' => [[
            'title' => 'Starter', 'minutes' => 10, 'items' => [
                ['type' => 'page', 'title' => 'Notes', 'body' => 'Hello'],
                ['type' => 'mcq', 'title' => 'Check', 'questions' => [['prompt' => 'ALU?', 'choices' => ['Yes', 'No'], 'correct_index' => 0]]],
            ],
        ]]];
        $ai = new LessonAiService($this->pdo, static fn (): string => json_encode($plan));
        $draftId = $ai->draftPlan($lessonId, 2, ['topic' => 'CPU', 'minutes' => 30]);
        $this->assertSame(2, $ai->applyPlan($draftId, $lessonId));
        $this->assertSame(0, (int)$this->pdo->query("SELECT published FROM online_lessons WHERE id = $lessonId")->fetchColumn());
        $this->assertSame(1, (int)$this->pdo->query('SELECT MIN(ai_generated) FROM online_lesson_questions')->fetchColumn());

        $second = $ai->draftPlan($lessonId, 2, ['topic' => 'CPU']);
        $this->expectExceptionMessage('already has pages');
        $ai->applyPlan($second, $lessonId);
    }

    public function testBulkDraftsForOnlinePhysicalAndHybridClassesNeverPublish(): void
    {
        $this->pdo->exec("INSERT INTO online_lessons (timetable_id, title, published) VALUES (102, 'Existing', 1)");
        $planner = new LessonPlannerService($this->pdo);
        $result = $planner->bulkCreateDrafts([100, 101, 102, 300], 10, false, 2);
        $this->assertCount(2, $result['created'], 'online and physical slots get drafts');
        $reasons = array_column($result['skipped'], 'reason', 'timetable_id');
        $this->assertSame('A lesson already exists.', $reasons[102]);
        $this->assertSame('You cannot manage this class.', $reasons[300], "another teacher's class is refused");
        $this->assertSame(0, (int)$this->pdo->query('SELECT COUNT(*) FROM online_lessons WHERE timetable_id IN (100, 101) AND published = 1')->fetchColumn());
        $this->assertSame('Existing', (string)$this->pdo->query('SELECT title FROM online_lessons WHERE timetable_id = 102')->fetchColumn());

        $admin = $planner->bulkCreateDrafts([300], 0, true, 1);
        $this->assertCount(1, $admin['created'], 'admins can plan any class');
    }

    public function testLearningPathLocksByPrerequisiteAndPicksNextLesson(): void
    {
        [$a, $itemA] = $this->lessonWithQuiz(100, 1);
        [$b] = $this->lessonWithQuiz(101, 1);
        [$hidden] = $this->lessonWithQuiz(102, 1);
        [$otherClass] = $this->lessonWithQuiz(300, 1);
        $this->pdo->exec("UPDATE online_lessons SET published = 1 WHERE id IN ($a, $b, $otherClass)");
        (new LessonAccessService($this->pdo))->addPrerequisite($b, $a, 100);
        $courses = new CourseService($this->pdo);

        $path = $courses->studentPath(50, strtotime('2026-06-01'));
        $lessons = $path['groups'][0]['lessons'];
        $this->assertSame([$a, $b], array_column($lessons, 'lesson_id'), 'drafts and other classes are not listed');
        $this->assertSame(['available', 'locked'], array_column($lessons, 'state'));
        $this->assertStringContainsString('Complete', $lessons[1]['reason']);
        $this->assertSame($a, $path['next']['lesson_id']);

        $this->pdo->exec("INSERT INTO online_lesson_item_state (student_id, item_id, status) VALUES (50, $itemA, 'completed')");
        $path = $courses->studentPath(50, strtotime('2026-06-01'));
        $this->assertSame(['completed', 'available'], array_column($path['groups'][0]['lessons'], 'state'));
        $this->assertSame($b, $path['next']['lesson_id']);
        $this->assertSame(50, $path['progress']);
    }

    public function testLearningPathShowsUnpaidLessonsAsPaymentRequiredAndNeverNext(): void
    {
        [$a] = $this->lessonWithQuiz(100, 1);
        [$b] = $this->lessonWithQuiz(101, 1);
        $this->pdo->exec("UPDATE online_lessons SET published = 1 WHERE id IN ($a, $b)");
        $this->pdo->exec('UPDATE timetable SET class_fee_per_student = 500 WHERE id = 100');

        $path = (new CourseService($this->pdo))->studentPath(50, strtotime('2026-06-01'));
        $lessons = $path['groups'][0]['lessons'];
        $this->assertSame(['payment', 'available'], array_column($lessons, 'state'));
        $this->assertSame('Payment required.', $lessons[0]['reason']);
        $this->assertSame($b, $path['next']['lesson_id']);
    }

    public function testCourseCoverageCountsDeliveredLessonsPerClass(): void
    {
        [$a] = $this->lessonWithQuiz(100, 1);
        [$b] = $this->lessonWithQuiz(200, 1);
        $this->pdo->exec('UPDATE online_lessons SET published = 1');
        $courses = new CourseService($this->pdo);
        $courseId = $courses->saveCourse(0, 'Paper 1', 1, '', 0, 2, false);
        $unitId = $courses->addUnit($courseId, 'Hardware', 2, false);
        $topicId = $courses->saveTopic(0, $unitId, 'CPU', '', 1, 2, false);
        $courses->assignLesson($a, $topicId);
        $courses->assignLesson($b, $topicId);
        $classA = $courses->coverage($courseId, [1], '2026-06-01');
        $this->assertSame(1, $classA['covered']);
        $this->assertCount(1, $classA['units'][0]['topics'][0]['lessons'], 'coverage for one class only lists its lessons');
        $none = $courses->coverage($courseId, [], '2026-06-01');
        $this->assertSame(0, $none['covered']);
        $this->expectException(RuntimeException::class);
        $courses->addUnit($courseId, 'Not mine', 3, false);
    }

    /**
     * @return array{0:int,1:int,2:int,3:list<int>}
     */
    private function lessonWithQuiz(int $timetableId, int $questions): array
    {
        $this->pdo->prepare('INSERT INTO online_lessons (timetable_id, title) VALUES (?, ?)')->execute([$timetableId, 'Lesson ' . $timetableId]);
        $lessonId = (int)$this->pdo->lastInsertId();
        $service = new OnlineLessonService($this->pdo);
        $item = $service->addActivityAfter($lessonId, 0, 'mcq', 'Quiz');
        $ids = [];
        for ($i = 1; $i <= $questions; $i++) {
            $ids[] = $service->addQuestion((int)$item['activity_id'], 'mcq', 'Question ' . $i, ['A', 'B'], 0, 1.0, 'Because');
        }
        return [$lessonId, (int)$item['id'], (int)$item['activity_id'], $ids];
    }

    /**
     * @return list<string>
     */
    private function schema(): array
    {
        return [
            'CREATE TABLE users (id INTEGER PRIMARY KEY, username TEXT)',
            'CREATE TABLE subjects (id INTEGER PRIMARY KEY, name TEXT)',
            'CREATE TABLE student_classes (id INTEGER PRIMARY KEY, name TEXT, deleted_at TEXT NULL)',
            'CREATE TABLE teachers (id INTEGER PRIMARY KEY, name TEXT, user_id INT)',
            'CREATE TABLE rooms (id INTEGER PRIMARY KEY, name TEXT)',
            'CREATE TABLE timetable (id INTEGER PRIMARY KEY, date TEXT, start_time TEXT, end_time TEXT, class_id INT, subject_id INT, teacher_id INT,
                substitute_teacher_id INT NULL, room_id INT NULL, delivery_mode TEXT, lesson_status TEXT NULL, deleted_at TEXT NULL, class_fee_per_student REAL DEFAULT 0)',
            'CREATE TABLE student_enrollments (id INTEGER PRIMARY KEY AUTOINCREMENT, student_id INT, class_id INT, teacher_id INT NULL)',
            "CREATE TABLE online_lessons (id INTEGER PRIMARY KEY AUTOINCREMENT, timetable_id INT UNIQUE, recording_id INT NULL, title TEXT NOT NULL, intro TEXT NULL,
                published INT NOT NULL DEFAULT 0, sequential INT NOT NULL DEFAULT 1, min_watch_percent INT NOT NULL DEFAULT 80,
                available_after_class INT NOT NULL DEFAULT 0, close_after_days INT NOT NULL DEFAULT 0, created_by INT NULL, pass_percent INT NULL,
                plan_objectives TEXT NULL, plan_topics TEXT NULL, plan_minutes INT NULL, plan_difficulty TEXT NULL, plan_prerequisites TEXT NULL,
                plan_outcomes TEXT NULL, plan_materials TEXT NULL, plan_homework TEXT NULL, plan_assessment TEXT NULL, archived INT NOT NULL DEFAULT 0,
                publish_at TEXT NULL, unpublish_at TEXT NULL, topic_id INT NULL, created_at TEXT DEFAULT CURRENT_TIMESTAMP, updated_at TEXT DEFAULT CURRENT_TIMESTAMP)",
            'CREATE TABLE online_lesson_sections (id INTEGER PRIMARY KEY AUTOINCREMENT, lesson_id INT, title TEXT, sort_order INT DEFAULT 0, estimated_minutes INT NULL)',
            'CREATE TABLE online_lesson_objectives (id INTEGER PRIMARY KEY AUTOINCREMENT, lesson_id INT, body TEXT, sort_order INT DEFAULT 0)',
            'CREATE TABLE online_lesson_objective_links (id INTEGER PRIMARY KEY AUTOINCREMENT, objective_id INT, question_id INT DEFAULT 0, item_id INT DEFAULT 0)',
            'CREATE TABLE online_lesson_items (id INTEGER PRIMARY KEY AUTOINCREMENT, lesson_id INT, item_type TEXT, title TEXT, sort_order INT DEFAULT 0,
                video_asset_id INT NULL, activity_id INT NULL, body TEXT NULL, required INT DEFAULT 1, link_url TEXT NULL, open_new_tab INT DEFAULT 1,
                section_id INT NULL, estimated_minutes INT NULL, resource_id INT NULL)',
            "CREATE TABLE online_lesson_activities (id INTEGER PRIMARY KEY AUTOINCREMENT, lesson_id INT, activity_type TEXT DEFAULT 'mcq', title TEXT,
                instructions TEXT NULL, pass_percent INT NULL, max_attempts INT NOT NULL DEFAULT 0, show_correct INT DEFAULT 1, shuffle_choices INT DEFAULT 0,
                shuffle_questions INT DEFAULT 0, due_at TEXT NULL, max_marks REAL NULL, allow_text INT DEFAULT 1, allow_file INT DEFAULT 0,
                draw_count INT NULL, scoring_rule TEXT NULL, time_limit_minutes INT NULL, show_score INT DEFAULT 1, show_explanation INT DEFAULT 1)",
            "CREATE TABLE online_lesson_questions (id INTEGER PRIMARY KEY AUTOINCREMENT, activity_id INT, question_type TEXT DEFAULT 'mcq', sort_order INT DEFAULT 0,
                prompt TEXT, choices_json TEXT NULL, correct_index INT NULL, marks REAL DEFAULT 1, explanation TEXT NULL, topic TEXT NULL, difficulty TEXT NULL,
                exam_ref TEXT NULL, expected_answer TEXT NULL, tags TEXT NULL, ai_generated INT NOT NULL DEFAULT 0)",
            'CREATE TABLE online_lesson_criteria (id INTEGER PRIMARY KEY AUTOINCREMENT, question_id INT, label TEXT, marks REAL, sort_order INT DEFAULT 0)',
            "CREATE TABLE online_lesson_attempts (id INTEGER PRIMARY KEY AUTOINCREMENT, student_id INT, item_id INT, activity_id INT, attempt_no INT DEFAULT 1,
                status TEXT DEFAULT 'submitted', score REAL NULL, max_score REAL NULL, passed INT NULL, submitted_at TEXT NULL, question_ids_json TEXT NULL,
                version_id INT NULL, started_at TEXT NULL, over_time INT NOT NULL DEFAULT 0, created_at TEXT DEFAULT CURRENT_TIMESTAMP)",
            'CREATE TABLE online_lesson_answers (id INTEGER PRIMARY KEY AUTOINCREMENT, attempt_id INT, question_id INT, choice_index INT NULL, essay_text TEXT NULL,
                is_correct INT NULL, marks_awarded REAL NULL, teacher_comment TEXT NULL)',
            'CREATE TABLE online_lesson_attempt_sessions (student_id INT, item_id INT, attempt_no INT, question_ids_json TEXT, started_at TEXT, PRIMARY KEY (student_id, item_id, attempt_no))',
            "CREATE TABLE online_lesson_item_state (student_id INT, item_id INT, status TEXT DEFAULT 'incomplete', video_seconds INT DEFAULT 0,
                video_duration_seconds INT DEFAULT 0, completed_at TEXT NULL, open_count INT DEFAULT 0, active_seconds INT DEFAULT 0, last_seen_at TEXT NULL,
                PRIMARY KEY (student_id, item_id))",
            "CREATE TABLE online_lesson_submissions (id INTEGER PRIMARY KEY AUTOINCREMENT, student_id INT, item_id INT, status TEXT DEFAULT 'submitted',
                body_text TEXT NULL, file_key TEXT NULL, marks_awarded REAL NULL, submitted_at TEXT NULL)",
            "CREATE TABLE online_lesson_versions (id INTEGER PRIMARY KEY AUTOINCREMENT, lesson_id INT, version_no INT, snapshot_json TEXT, snapshot_hash TEXT,
                reason TEXT DEFAULT 'saved', restored_from INT NULL, created_by INT NULL, created_at TEXT, UNIQUE (lesson_id, version_no))",
            'CREATE TABLE online_lesson_prerequisites (id INTEGER PRIMARY KEY AUTOINCREMENT, lesson_id INT, requires_lesson_id INT, min_percent INT DEFAULT 100, UNIQUE (lesson_id, requires_lesson_id))',
            'CREATE TABLE online_lesson_bookmarks (id INTEGER PRIMARY KEY AUTOINCREMENT, student_id INT, lesson_id INT, item_id INT, question_id INT DEFAULT 0,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP, UNIQUE (student_id, item_id, question_id))',
            'CREATE TABLE online_lesson_notes (id INTEGER PRIMARY KEY AUTOINCREMENT, student_id INT, lesson_id INT, section_id INT DEFAULT 0, item_id INT DEFAULT 0,
                body TEXT NOT NULL, updated_at TEXT NOT NULL, UNIQUE (student_id, lesson_id, section_id, item_id))',
            "CREATE TABLE online_lesson_resources (id INTEGER PRIMARY KEY AUTOINCREMENT, owner_user_id INT, title TEXT, resource_type TEXT DEFAULT 'url', url TEXT NULL,
                file_key TEXT NULL, file_name TEXT NULL, mime TEXT NULL, file_size INT NULL, description TEXT NULL, subject TEXT NULL, topic TEXT NULL, tags TEXT NULL,
                root_id INT NULL, version_no INT NOT NULL DEFAULT 1, superseded_by INT NULL, archived INT NOT NULL DEFAULT 0, created_at TEXT DEFAULT CURRENT_TIMESTAMP)",
            "CREATE TABLE online_lesson_ai_drafts (id INTEGER PRIMARY KEY AUTOINCREMENT, lesson_id INT, activity_id INT DEFAULT 0, question_id INT DEFAULT 0, user_id INT,
                kind TEXT, input_json TEXT NULL, output_json TEXT NULL, status TEXT DEFAULT 'draft', created_at TEXT)",
            'CREATE TABLE lm_courses (id INTEGER PRIMARY KEY AUTOINCREMENT, title TEXT, subject_id INT NULL, description TEXT NULL, coverage_min_percent INT DEFAULT 0,
                created_by INT NULL, archived INT DEFAULT 0, created_at TEXT DEFAULT CURRENT_TIMESTAMP)',
            'CREATE TABLE lm_units (id INTEGER PRIMARY KEY AUTOINCREMENT, course_id INT, title TEXT, sort_order INT DEFAULT 0)',
            'CREATE TABLE lm_topics (id INTEGER PRIMARY KEY AUTOINCREMENT, unit_id INT, title TEXT, objectives TEXT NULL, planned_lessons INT NULL, sort_order INT DEFAULT 0)',
        ];
    }
}
