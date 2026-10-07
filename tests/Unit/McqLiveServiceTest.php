<?php
declare(strict_types=1);

use Edexcel\Services\McqLiveService;
use Edexcel\Services\OnlineLessonService;
use PHPUnit\Framework\TestCase;

/**
 * Live MCQ monitor against an in-memory database: current question per student, answer changes,
 * idle/offline/active transitions, submission, and access limits.
 */
final class McqLiveServiceTest extends TestCase
{
    private PDO $pdo;
    private OnlineLessonService $lessons;
    private McqLiveService $live;
    private int $lessonId;
    private int $itemId;
    private int $activityId;
    /** @var list<int> */
    private array $qids = [];

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->sqliteCreateFunction('NOW', static fn (): string => date('Y-m-d H:i:s'), 0);
        foreach ($this->schema() as $sql) {
            $this->pdo->exec($sql);
        }
        $this->pdo->exec("INSERT INTO users (id, username) VALUES (50, 'stu50'), (51, 'stu51'), (52, 'stu52')");
        $this->pdo->exec("INSERT INTO student_profiles (user_id, full_name) VALUES (50, 'John'), (51, 'Nimal'), (52, 'Kamal')");
        $this->pdo->exec('INSERT INTO student_enrollments (student_id, class_id) VALUES (50, 1), (51, 1), (52, 2)');
        $this->pdo->exec("INSERT INTO online_lessons (timetable_id, title, published) VALUES (100, 'Lesson', 1)");
        $this->lessonId = (int)$this->pdo->lastInsertId();
        $this->lessons = new OnlineLessonService($this->pdo);
        $item = $this->lessons->addActivityAfter($this->lessonId, 0, 'mcq', 'Quiz');
        $this->itemId = (int)$item['id'];
        $this->activityId = (int)$item['activity_id'];
        for ($i = 1; $i <= 5; $i++) {
            $this->qids[] = $this->lessons->addQuestion($this->activityId, 'mcq', 'Question ' . $i, ['A', 'B', 'C', 'D'], 1, 1.0, '');
        }
        $this->live = new McqLiveService($this->pdo, $this->lessons);
    }

    public function testTwoStudentsOnDifferentQuestionsAreShownSeparately(): void
    {
        $t = strtotime('2026-09-29 10:00:00');
        $this->live->record(50, $this->item(), 'open', ['question_id' => $this->qids[0]], $t);
        $this->live->record(51, $this->item(), 'open', ['question_id' => $this->qids[0]], $t);
        $this->live->record(50, $this->item(), 'question', ['question_id' => $this->qids[2]], $t + 5);
        $this->live->record(51, $this->item(), 'question', ['question_id' => $this->qids[3]], $t + 6);

        $rows = $this->byName($this->live->monitorRows($this->lessonId, 1, 0, 0, null, $t + 10));
        $this->assertSame(3, $rows['John']['question_no']);
        $this->assertSame(4, $rows['Nimal']['question_no']);
        $this->assertSame(5, $rows['John']['total']);
        $this->assertSame('active', $rows['John']['status']);
        $this->assertSame(5, $rows['John']['question_seconds'], 'time on question restarts when the question changes');
        $this->assertSame(10, $rows['John']['total_seconds']);

        $this->live->record(50, $this->item(), 'question', ['question_id' => $this->qids[1]], $t + 20);
        $rows = $this->byName($this->live->monitorRows($this->lessonId, 1, 0, 0, null, $t + 21));
        $this->assertSame(2, $rows['John']['question_no'], 'moving back to an earlier question is tracked');
    }

    public function testSelectingAndChangingAnswerUpdatesLetterAndProgress(): void
    {
        $t = strtotime('2026-09-29 10:00:00');
        $this->live->record(50, $this->item(), 'open', ['question_id' => $this->qids[0]], $t);
        $this->live->record(50, $this->item(), 'answer', ['question_id' => $this->qids[0], 'choice' => 1], $t + 3);
        $row = $this->byName($this->live->monitorRows($this->lessonId, 1, 0, 0, null, $t + 4))['John'];
        $this->assertSame('B', $row['answer']);
        $this->assertSame(1, $row['answered']);
        $this->assertSame(20, $row['progress']);

        $this->live->record(50, $this->item(), 'answer', ['question_id' => $this->qids[0], 'choice' => 3], $t + 6);
        $this->live->record(50, $this->item(), 'answer', ['question_id' => $this->qids[1], 'choice' => 0], $t + 9);
        $rows = $this->live->monitorRows($this->lessonId, 1, 0, 0, null, $t + 10);
        $row = $this->byName($rows)['John'];
        $this->assertSame('A', $row['answer'], 'the selected answer follows the current question');
        $this->assertSame(2, $row['question_no']);
        $this->assertSame(2, $row['answered']);

        $detail = $this->live->studentDetail($this->lessonId, 1, $this->item(), 50, $t + 10);
        $this->assertSame(['answered', 'current', 'unanswered', 'unanswered', 'unanswered'], array_column($detail['questions'], 'state'));
        $this->assertSame('D', $detail['questions'][0]['answer'], 'the changed answer replaces the first one');
        $this->assertArrayNotHasKey('correct_index', $detail['questions'][0]);
        $this->assertStringNotContainsString('correct', json_encode($detail));
    }

    public function testIdleOfflineAndBackToActive(): void
    {
        $t = strtotime('2026-09-29 10:00:00');
        $idle = McqLiveService::idleSeconds();
        $offline = McqLiveService::offlineSeconds();
        $this->live->record(50, $this->item(), 'open', ['question_id' => $this->qids[0]], $t);

        // Reading a long question: heartbeats keep coming but there is no input.
        for ($s = 30; $s <= $idle + 60; $s += 30) {
            $this->live->record(50, $this->item(), 'heartbeat', ['visible' => true, 'interacted' => false], $t + $s);
        }
        $row = $this->byName($this->live->monitorRows($this->lessonId, 1, 0, 0, null, $t + $idle + 61))['John'];
        $this->assertSame('idle', $row['status'], 'no input for the idle period');

        $row = $this->byName($this->live->monitorRows($this->lessonId, 1, 0, 0, null, $t + $idle + 60 + $offline + 1))['John'];
        $this->assertSame('offline', $row['status'], 'no heartbeat for the offline period');

        $back = $t + $idle + 60 + $offline + 30;
        $this->live->record(50, $this->item(), 'heartbeat', ['visible' => true, 'interacted' => true], $back);
        $row = $this->byName($this->live->monitorRows($this->lessonId, 1, 0, 0, null, $back + 1))['John'];
        $this->assertSame('active', $row['status']);

        $this->live->record(50, $this->item(), 'heartbeat', ['visible' => false, 'interacted' => false], $back + 5);
        $row = $this->byName($this->live->monitorRows($this->lessonId, 1, 0, 0, null, $back + 6))['John'];
        $this->assertSame('idle', $row['status'], 'a hidden tab is idle, not offline');
    }

    public function testLeavingShowsOfflineAndReopeningResumes(): void
    {
        $t = strtotime('2026-09-29 10:00:00');
        $this->live->record(50, $this->item(), 'open', ['question_id' => $this->qids[0]], $t);
        $this->live->record(50, $this->item(), 'question', ['question_id' => $this->qids[3]], $t + 5);
        $this->live->record(50, $this->item(), 'leave', [], $t + 10);
        $row = $this->byName($this->live->monitorRows($this->lessonId, 1, 0, 0, null, $t + 11))['John'];
        $this->assertSame('offline', $row['status']);

        $this->live->record(50, $this->item(), 'open', ['question_id' => $this->qids[3], 'answers' => json_encode([$this->qids[0] => 2])], $t + 40);
        $row = $this->byName($this->live->monitorRows($this->lessonId, 1, 0, 0, null, $t + 41))['John'];
        $this->assertSame('active', $row['status']);
        $this->assertSame(4, $row['question_no']);
        $this->assertSame(1, $row['answered'], 'answers restored by the browser are reported on reopen');
        $this->assertSame(36, $row['question_seconds'], 'reopening on the same question keeps its start time');
    }

    public function testSubmissionMarksSubmittedAndScoringIsUnchanged(): void
    {
        $now = time();
        $this->pdo->exec("UPDATE online_lesson_activities SET pass_percent = 100 WHERE id = {$this->activityId}");
        $this->live->record(50, $this->item(), 'open', ['question_id' => $this->qids[0]], $now);
        $answers = [];
        foreach ($this->qids as $i => $qid) {
            $answers[$qid] = ['choice' => $i < 3 ? 1 : 0];
        }
        $this->lessons->submitActivity(50, $this->itemId, $answers);
        $attempt = $this->lessons->latestAttempt(50, $this->itemId);
        $this->assertEqualsWithDelta(3.0, (float)$attempt['score'], 0.001);
        $this->assertEqualsWithDelta(5.0, (float)$attempt['max_score'], 0.001);

        $this->live->record(50, $this->item(), 'leave', [], $now + 1);
        $this->live->record(50, $this->item(), 'heartbeat', ['visible' => true, 'interacted' => true], $now + 2);
        $row = $this->byName($this->live->monitorRows($this->lessonId, 1, 0, 0, null, $now + 3))['John'];
        $this->assertSame('submitted', $row['status'], 'a late leave beacon cannot undo the submission');
        $this->assertSame((int)$attempt['id'], $row['attempt_id']);

        $this->live->record(50, $this->item(), 'open', ['question_id' => $this->qids[0]], $now + 10);
        $row = $this->byName($this->live->monitorRows($this->lessonId, 1, 0, 0, null, $now + 11))['John'];
        $this->assertSame(2, $row['attempt_no'], 'a retry is tracked as the next attempt');
        $this->assertSame('active', $row['status']);
        $this->assertSame(1, (int)$this->pdo->query('SELECT COUNT(*) FROM online_lesson_attempts')->fetchColumn(), 'opening never creates an attempt');
    }

    public function testOtherClassesAndForeignQuestionsAreRejected(): void
    {
        $t = strtotime('2026-09-29 10:00:00');
        $this->live->record(52, $this->item(), 'open', ['question_id' => $this->qids[0]], $t);
        $this->assertArrayNotHasKey('Kamal', $this->byName($this->live->monitorRows($this->lessonId, 1, 0, 0, null, $t + 1)), 'students outside the class are never listed');
        $this->assertNull($this->live->studentDetail($this->lessonId, 1, $this->item(), 52, $t + 1));

        $other = $this->lessons->addActivityAfter($this->lessonId, $this->itemId, 'mcq', 'Other');
        $foreign = $this->lessons->addQuestion((int)$other['activity_id'], 'mcq', 'Elsewhere', ['A', 'B'], 0, 1.0, '');
        $this->live->record(50, $this->item(), 'open', [], $t);
        $this->expectException(RuntimeException::class);
        $this->live->record(50, $this->item(), 'question', ['question_id' => $foreign], $t + 2);
    }

    public function testShuffledOrderAndChoicesMatchWhatTheStudentSees(): void
    {
        $this->pdo->exec("UPDATE online_lesson_activities SET shuffle_questions = 1, shuffle_choices = 1 WHERE id = {$this->activityId}");
        $lesson = $this->lessons->find($this->lessonId);
        $payload = $this->lessons->playerPayload(50, $lesson, $this->item(), false);
        $shown = $payload['activity']['questions'];
        $t = strtotime('2026-09-29 10:00:00');
        $this->live->record(50, $this->item(), 'open', ['question_id' => $shown[0]['id']], $t);
        $this->live->record(50, $this->item(), 'answer', ['question_id' => $shown[2]['id'], 'choice' => 0], $t + 2);
        $row = $this->byName($this->live->monitorRows($this->lessonId, 1, 0, 0, null, $t + 3))['John'];
        $this->assertSame(3, $row['question_no'], 'numbering follows the shuffled order the student sees');
        $original = OnlineLessonService::originalChoiceIndex(50, (int)$shown[2]['id'], 4, 0);
        $this->assertSame(McqLiveService::choiceLetter($original), $row['answer'], 'the letter is the original choice, as in the result tables');
    }

    public function testLiveRowNeverActsAsTimerStartAndFinishedActivitiesAreNotTracked(): void
    {
        $t = strtotime('2026-01-01 08:00:00');
        $this->live->record(50, $this->item(), 'open', ['question_id' => $this->qids[0]], $t);
        $this->pdo->exec("UPDATE online_lesson_activities SET time_limit_minutes = 10 WHERE id = {$this->activityId}");
        $activity = $this->lessons->activity($this->activityId);
        $this->assertNull($this->lessons->attemptSession(50, $this->itemId, $activity, $this->lessons->studentQuestions($this->activityId), false), 'the timed activity still needs Start');
        $session = $this->lessons->startAttempt(50, $this->itemId);
        $this->assertGreaterThan($t, strtotime($session['started_at']), 'the timer starts at Start, not at the earlier page view');
        $this->assertSame(0, (int)$this->pdo->query('SELECT live_only FROM online_lesson_attempt_sessions')->fetchColumn());

        $this->pdo->exec("UPDATE online_lesson_activities SET time_limit_minutes = NULL, max_attempts = 1 WHERE id = {$this->activityId}");
        $this->pdo->exec("INSERT INTO online_lesson_attempts (student_id, item_id, activity_id, attempt_no, status, score, max_score, passed) VALUES (51, {$this->itemId}, {$this->activityId}, 1, 'submitted', 1, 5, 0)");
        $result = $this->live->record(51, $this->item(), 'open', ['question_id' => $this->qids[0]], $t);
        $this->assertFalse($result['tracked'], 'no attempts left');
    }

    public function testDeltaPollingReturnsOnlyChangedRows(): void
    {
        $t = strtotime('2026-09-29 10:00:00');
        $this->live->record(50, $this->item(), 'open', ['question_id' => $this->qids[0]], $t);
        $this->live->record(51, $this->item(), 'open', ['question_id' => $this->qids[0]], $t);
        $this->live->record(51, $this->item(), 'question', ['question_id' => $this->qids[1]], $t + 20);
        $rows = $this->live->monitorRows($this->lessonId, 1, 0, 0, date('Y-m-d H:i:s', $t + 10), $t + 21);
        $this->assertSame(['Nimal'], array_column($rows, 'name'));
    }

    /**
     * @return array<string,mixed>
     */
    private function item(): array
    {
        return $this->lessons->item($this->itemId);
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array<string,array<string,mixed>>
     */
    private function byName(array $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            $out[$row['name']] = $row;
        }
        return $out;
    }

    /**
     * @return list<string>
     */
    private function schema(): array
    {
        return [
            'CREATE TABLE users (id INTEGER PRIMARY KEY, username TEXT, deleted_at TEXT NULL)',
            'CREATE TABLE student_profiles (user_id INT PRIMARY KEY, full_name TEXT)',
            'CREATE TABLE student_enrollments (id INTEGER PRIMARY KEY AUTOINCREMENT, student_id INT, class_id INT, teacher_id INT NULL)',
            "CREATE TABLE online_lessons (id INTEGER PRIMARY KEY AUTOINCREMENT, timetable_id INT UNIQUE, recording_id INT NULL, title TEXT NOT NULL,
                published INT NOT NULL DEFAULT 0, sequential INT NOT NULL DEFAULT 1, min_watch_percent INT NOT NULL DEFAULT 80,
                available_after_class INT NOT NULL DEFAULT 0, close_after_days INT NOT NULL DEFAULT 0, pass_percent INT NULL, archived INT NOT NULL DEFAULT 0)",
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
            'CREATE TABLE online_lesson_attempt_sessions (student_id INT, item_id INT, attempt_no INT, question_ids_json TEXT, started_at TEXT,
                lesson_id INT NULL, activity_id INT NULL, attempt_id INT NULL, live_only INT NOT NULL DEFAULT 0, current_question_id INT NULL,
                current_question_no INT NULL, total_questions INT NULL, selected_choice INT NULL, answers_json TEXT NULL, question_started_at TEXT NULL,
                last_interaction_at TEXT NULL, last_heartbeat_at TEXT NULL, live_status TEXT NULL, live_updated_at TEXT NULL,
                PRIMARY KEY (student_id, item_id, attempt_no))',
            "CREATE TABLE online_lesson_item_state (student_id INT, item_id INT, status TEXT DEFAULT 'incomplete', video_seconds INT DEFAULT 0,
                video_duration_seconds INT DEFAULT 0, completed_at TEXT NULL, open_count INT DEFAULT 0, active_seconds INT DEFAULT 0, last_seen_at TEXT NULL,
                PRIMARY KEY (student_id, item_id))",
            'CREATE TABLE online_lesson_versions (id INTEGER PRIMARY KEY AUTOINCREMENT, lesson_id INT, version_no INT, snapshot_json TEXT, snapshot_hash TEXT)',
        ];
    }
}
