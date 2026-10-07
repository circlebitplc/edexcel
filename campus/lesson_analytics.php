<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Edexcel\Services\LearningModuleService;
use Edexcel\Services\LessonInsightService;
use Edexcel\Services\LessonResultBuilder;
use Edexcel\Services\McqLiveService;
use Edexcel\Services\OnlineLessonService;
use Edexcel\Services\RecordingService;

require_staff();
ensure_online_lesson_schema($pdo);

$isAdmin = is_admin();
$teacherId = (int)($_SESSION['teacher_id'] ?? 0);
$timetableId = (int)($_GET['lesson'] ?? 0);
$recordings = new RecordingService($pdo);
$lessons = new OnlineLessonService($pdo);
$staffLesson = $timetableId > 0 ? $recordings->lessonForStaff($timetableId, $teacherId, $isAdmin) : null;
$onlineLesson = $staffLesson ? $lessons->findByTimetable($timetableId) : null;
$error = '';
if (!$staffLesson || !OnlineLessonService::supportsDeliveryMode((string)($staffLesson['delivery_mode'] ?? 'physical'))) {
    $error = 'That class was not found.';
    $staffLesson = null;
} elseif (!$onlineLesson) {
    $error = 'This class does not have a video lesson yet.';
}

if ($staffLesson && $onlineLesson && $_SERVER['REQUEST_METHOD'] === 'POST' && (string)($_POST['action'] ?? '') === 'save_pass_percent') {
    if (!verify_csrf_token((string)($_POST['csrf_token'] ?? ''))) {
        $error = 'The security token expired. Save the pass mark again.';
    } else {
        $raw = trim((string)($_POST['pass_percent'] ?? ''));
        $percent = $raw === '' ? null : (int)$raw;
        try {
            $lessons->savePassPercent((int)$onlineLesson['id'], $percent);
            header('Location: ' . campus_lesson_analytics_url($timetableId));
            exit;
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}

$report = null;
$results = null;
if ($staffLesson && $onlineLesson && $error === '') {
    $report = $lessons->lessonAnalytics((int)$onlineLesson['id'], (int)$staffLesson['class_id']);
    $pass = $onlineLesson['pass_percent'] ?? null;
    $results = $lessons->lessonResultReport(
        (int)$onlineLesson['id'],
        (int)$staffLesson['class_id'],
        $pass === null || $pass === '' ? null : (int)$pass,
        null,
        $report['students']
    );
}

$selectedId = (int)($_GET['student'] ?? 0);
$detail = null;
if ($results && $selectedId > 0) {
    foreach ($results['students'] as $student) {
        if ((int)$student['id'] === $selectedId) {
            $detail = $student;
            break;
        }
    }
    if ($detail === null) {
        $selectedId = 0;
    }
}

$export = strtolower((string)($_GET['export'] ?? ''));
if ($results && $export === 'results') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="video-lesson-results-' . $timetableId . '.csv"');
    $out = fopen('php://output', 'wb');
    fputcsv($out, ['Student', 'Student ID', 'Status', 'Progress percent', 'Marks obtained', 'Marks counted', 'Lesson marks available', 'Percentage', 'MCQ marks', 'MCQ counted', 'Essay marks', 'Essay counted', 'Items completed', 'Items total', 'Time spent seconds', 'Last activity', 'Marking status', 'Result', 'Short answer marks', 'Short answer counted', 'Exam marks', 'Exam counted', 'Assignment marks', 'Assignment counted']);
    foreach ($results['students'] as $student) {
        fputcsv($out, [
            $student['name'],
            $student['id'],
            $student['status'],
            $student['progress'],
            $student['obtained'],
            $student['counted_max'],
            $student['lesson_max'],
            $student['percent'],
            $student['mcq_obtained'],
            $student['mcq_counted_max'],
            $student['essay_obtained'],
            $student['essay_counted_max'],
            $student['items_done'],
            $student['items_total'],
            $student['active_seconds'],
            $student['last_seen_at'],
            $student['marking'],
            $student['outcome'],
            $student['short_obtained'] ?? null,
            $student['short_counted_max'] ?? null,
            $student['exam_obtained'] ?? null,
            $student['exam_counted_max'] ?? null,
            $student['assignment_obtained'] ?? null,
            $student['assignment_counted_max'] ?? null,
        ]);
    }
    fclose($out);
    exit;
}
if ($results && $export === 'detailed') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="video-lesson-detailed-' . $timetableId . '.csv"');
    $out = fopen('php://output', 'wb');
    fputcsv($out, ['Student', 'Student ID', 'Activity', 'Type', 'Maximum marks', 'Marks obtained', 'Marks counted', 'Status', 'MCQ correct', 'MCQ incorrect', 'Attempts', 'Time spent seconds']);
    foreach ($results['students'] as $student) {
        foreach ($student['activities'] as $activity) {
            fputcsv($out, [
                $student['name'],
                $student['id'],
                $activity['title'],
                $activity['kind'],
                $activity['academic'] ? $activity['max'] : '',
                $activity['obtained'],
                $activity['counted_max'],
                $activity['status'],
                $activity['mcq_questions'] > 0 ? $activity['mcq_correct'] : '',
                $activity['mcq_questions'] > 0 ? $activity['mcq_incorrect'] : '',
                $activity['attempts'],
                $activity['active_seconds'],
            ]);
        }
    }
    fclose($out);
    exit;
}

$advanced = null;
$lessonInsights = [];
if ($results) {
    $insightService = new LessonInsightService($pdo);
    try {
        $advanced = $insightService->advancedAnalytics((int)$onlineLesson['id'], (int)$staffLesson['class_id']);
        $lessonInsights = $insightService->insights((int)$onlineLesson['id']);
    } catch (Throwable $e) {
        error_log('lesson analytics advanced: ' . $e->getMessage());
    }
}

$liveActivities = [];
$liveLessons = [];
$liveMaxAttempt = 1;
if ($results) {
    foreach ($results['activities'] as $activity) {
        if ((string)$activity['type'] === 'activity' && !OnlineLessonService::isSubmissionActivity((string)($activity['activity_type'] ?? ''))) {
            $liveActivities[] = ['id' => (int)$activity['item_id'], 'title' => (string)$activity['title']];
        }
    }
    try {
        $sql = "
            SELECT tt.id, tt.date, tt.start_time, tt.class_id, c.name AS class_name, s.name AS subject_name, ol.title
            FROM online_lessons ol
            JOIN timetable tt ON tt.id = ol.timetable_id AND tt.deleted_at IS NULL
            JOIN student_classes c ON c.id = tt.class_id
            JOIN subjects s ON s.id = tt.subject_id
            WHERE (tt.id = ? OR (ol.published = 1 AND tt.date BETWEEN ? AND ?))
        ";
        $params = [$timetableId, date('Y-m-d', strtotime('-30 days')), date('Y-m-d', strtotime('+7 days'))];
        if (!$isAdmin) {
            $sql .= ' AND (tt.teacher_id = ? OR tt.substitute_teacher_id = ?)';
            $params[] = $teacherId;
            $params[] = $teacherId;
        }
        $sql .= ' ORDER BY tt.date DESC, tt.start_time DESC LIMIT 80';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $liveLessons = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $stmt = $pdo->prepare('SELECT MAX(attempt_no) FROM online_lesson_attempt_sessions WHERE lesson_id = ? AND live_status IS NOT NULL');
        $stmt->execute([(int)$onlineLesson['id']]);
        $liveMaxAttempt = max(1, min(20, (int)$stmt->fetchColumn()));
    } catch (Throwable $e) {
        error_log('lesson analytics live lessons: ' . $e->getMessage());
    }
}

$h = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$summary = $report['summary'] ?? [];
$resultSummary = $results['summary'] ?? [];
$statusLabel = static function (string $status): string {
    return match ($status) {
        'complete' => 'Completed',
        'in_progress' => 'In progress',
        default => 'Not started',
    };
};
$pair = static function (?float $got, ?float $max): string {
    if ($got === null || $max === null) {
        return '—';
    }
    return LessonResultBuilder::formatMark($got) . '/' . LessonResultBuilder::formatMark($max);
};
$plain = static function (string $value): string {
    $text = trim(html_entity_decode(strip_tags($value), ENT_QUOTES, 'UTF-8'));
    if (function_exists('mb_substr') && mb_strlen($text) > 280) {
        return mb_substr($text, 0, 280) . '…';
    }
    return strlen($text) > 280 ? substr($text, 0, 280) . '…' : $text;
};

include __DIR__ . '/../includes/header.php';
?>
<style>
@media print {
    nav, .sidebar, .no-print { display: none !important; }
    .portal-container { margin: 0 !important; }
}
</style>
<p class="mb-3 no-print"><a href="<?= $h(campus_online_lesson_url($timetableId)) ?>">&larr; Video lesson</a></p>
<div class="d-none d-print-block mb-3">
    <strong>Edexcel College</strong><br>
    Video lesson result<br>
    <?= $h((string)(($onlineLesson['title'] ?? null) ?: (($staffLesson['subject_name'] ?? null) ?: ''))) ?><br>
    <?= $h(date('d M Y')) ?>
</div>
<h1 class="h3 mb-1">Video lesson analytics</h1>
<p class="text-muted"><?= $h((string)($staffLesson['subject_name'] ?? '')) ?> · <?= $h((string)($staffLesson['class_name'] ?? '')) ?></p>

<?php if ($error !== ''): ?>
    <div class="alert alert-warning"><?= $h($error) ?></div>
<?php elseif ($report && $results): ?>
<?php if ($detail): ?>
    <p class="no-print"><a href="<?= $h(campus_lesson_analytics_url($timetableId)) ?>">&larr; Class results</a></p>
    <?php include __DIR__ . '/../includes/lesson_result_detail.php'; ?>
<?php else: ?>

<div class="row g-3 mb-4">
    <?php
    $cards = [
        'Students' => (int)$summary['enrolled'],
        'Started' => (int)$summary['started'],
        'Completed' => (int)$summary['completed'],
        'In progress' => (int)$summary['in_progress'],
        'Not started' => (int)$summary['not_started'],
        'Completion' => (int)$summary['completion_rate'] . '%',
        'Average progress' => (int)$summary['avg_progress'] . '%',
        'Average time' => OnlineLessonService::formatActiveTime((int)$summary['avg_seconds']),
    ];
    foreach ($cards as $label => $value):
    ?>
        <div class="col-6 col-md-4 col-xl-2">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body">
                    <div class="text-muted small"><?= $h($label) ?></div>
                    <div class="h4 mb-0"><?= $h((string)$value) ?></div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<?php if ($liveActivities !== []): ?>
<style>
.mcq-live .live-dot { display:inline-flex; align-items:center; gap:.35rem; font-weight:600; font-size:.85rem; padding:.2rem .55rem; border-radius:999px; white-space:nowrap; }
.mcq-live .live-active { background:#d1f5dd; color:#0f5132; }
.mcq-live .live-idle { background:#fff3cd; color:#7a5a00; }
.mcq-live .live-offline { background:#f8d7da; color:#842029; }
.mcq-live .live-submitted { background:#dbe7ff; color:#1d3f8f; }
.mcq-live tbody tr { cursor:pointer; }
.mcq-live tbody tr:hover, .mcq-live .live-card:hover { background:rgba(13,110,253,.05); }
.mcq-live .live-card { border:1px solid rgba(128,128,128,.35); border-radius:.9rem; padding:.75rem .9rem; cursor:pointer; background:transparent; color:inherit; width:100%; text-align:left; }
.mcq-live .live-progress { height:6px; border-radius:6px; background:rgba(128,128,128,.3); overflow:hidden; min-width:70px; }
.mcq-live .live-progress > div { height:100%; background:#198754; }
.mcq-live .live-strip { display:flex; flex-wrap:wrap; gap:.35rem; }
.mcq-live .live-q { min-width:3.2rem; text-align:center; font-size:.8rem; padding:.25rem .4rem; border-radius:.5rem; border:1px solid rgba(128,128,128,.45); }
.mcq-live .live-q.is-answered { background:#d1f5dd; border-color:#9fd8b4; color:#0f5132; }
.mcq-live .live-q.is-current { background:#0d6efd; color:#fff; border-color:#0d6efd; font-weight:700; }
.mcq-live .live-panel dl { display:grid; grid-template-columns:auto 1fr; gap:.25rem 1rem; margin:0; }
.mcq-live .live-panel dt { font-weight:500; color:var(--bs-secondary-color, #6c757d); }
.mcq-live .live-panel dd { margin:0; font-weight:600; }
</style>
<section class="card border-0 shadow-sm rounded-4 mb-4 no-print mcq-live" id="mcqLive"
         data-endpoint="<?= $h(BASE_URL . 'ajax/mcq_live_monitor.php') ?>"
         data-lesson="<?= (int)$timetableId ?>"
         data-analytics="<?= $h(rtrim((string)BASE_URL, '/') . '/campus/lesson_analytics.php?lesson=') ?>">
    <div class="card-body">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
            <h2 class="h5 mb-0">LIVE MCQ STUDENT MONITOR</h2>
            <span class="small text-muted" data-live-updated aria-live="polite">Connecting…</span>
        </div>
        <div class="d-flex flex-wrap gap-2 mb-3" data-live-counts>
            <span class="live-dot live-active">🟢 Active <span data-count="active">0</span></span>
            <span class="live-dot live-idle">🟡 Idle <span data-count="idle">0</span></span>
            <span class="live-dot live-offline">🔴 Offline <span data-count="offline">0</span></span>
            <span class="live-dot live-submitted">✅ Submitted <span data-count="submitted">0</span></span>
        </div>
        <div class="row g-2 align-items-end mb-3">
            <div class="col-6 col-md-2">
                <label class="form-label small mb-1" for="liveStatus">Status</label>
                <select class="form-select form-select-sm" id="liveStatus">
                    <option value="">All students</option>
                    <option value="active">Active</option>
                    <option value="idle">Idle</option>
                    <option value="offline">Offline</option>
                    <option value="submitted">Submitted</option>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small mb-1" for="liveClass">Class</label>
                <select class="form-select form-select-sm" id="liveClass">
                    <?php
                    $liveClasses = [];
                    foreach ($liveLessons as $row) {
                        $liveClasses[(int)$row['class_id']] = (string)$row['class_name'];
                    }
                    asort($liveClasses);
                    foreach ($liveClasses as $cid => $cname): ?>
                        <option value="<?= (int)$cid ?>" <?= (int)$cid === (int)$staffLesson['class_id'] ? 'selected' : '' ?>><?= $h($cname) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-4">
                <label class="form-label small mb-1" for="liveLesson">Lesson</label>
                <select class="form-select form-select-sm" id="liveLesson">
                    <?php foreach ($liveLessons as $row): ?>
                        <option value="<?= (int)$row['id'] ?>" data-class="<?= (int)$row['class_id'] ?>" <?= (int)$row['id'] === $timetableId ? 'selected' : '' ?>>
                            <?= $h(date('d M', strtotime((string)$row['date'])) . ' · ' . ($row['title'] !== '' ? $row['title'] : $row['subject_name'])) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small mb-1" for="liveItem">MCQ</label>
                <select class="form-select form-select-sm" id="liveItem">
                    <?php if (count($liveActivities) > 1): ?><option value="0">All activities</option><?php endif; ?>
                    <?php foreach ($liveActivities as $row): ?>
                        <option value="<?= (int)$row['id'] ?>"><?= $h($row['title']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small mb-1" for="liveAttempt">Attempt</label>
                <select class="form-select form-select-sm" id="liveAttempt">
                    <option value="0">Latest</option>
                    <?php for ($n = 1; $n <= $liveMaxAttempt; $n++): ?>
                        <option value="<?= $n ?>">Attempt <?= $n ?></option>
                    <?php endfor; ?>
                </select>
            </div>
        </div>
        <div class="table-responsive d-none d-md-block">
            <table class="table table-sm align-middle mb-0">
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Status</th>
                        <th>Current question</th>
                        <th>Progress</th>
                        <th>Selected answer</th>
                        <th>Time on question</th>
                        <th>Total time</th>
                        <th>Last activity</th>
                    </tr>
                </thead>
                <tbody data-live-rows></tbody>
            </table>
        </div>
        <div class="d-grid gap-2 d-md-none" data-live-cards></div>
        <p class="text-muted small mb-0 mt-2" data-live-empty hidden>No students are working on this MCQ right now.</p>
        <div class="live-panel card border rounded-4 mt-3" data-live-panel hidden>
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                    <div>
                        <h3 class="h6 mb-0" data-panel-name></h3>
                        <div class="small text-muted" data-panel-activity></div>
                    </div>
                    <button class="btn btn-sm btn-outline-secondary" type="button" data-panel-close>Close</button>
                </div>
                <dl class="mb-3">
                    <dt>Current question</dt><dd data-panel-question>—</dd>
                    <dt>Progress</dt><dd data-panel-progress>—</dd>
                    <dt>Selected answer</dt><dd data-panel-answer>—</dd>
                    <dt>Time on current question</dt><dd data-panel-qtime>—</dd>
                    <dt>Total attempt time</dt><dd data-panel-total>—</dd>
                    <dt>Last activity</dt><dd data-panel-last>—</dd>
                    <dt>Status</dt><dd data-panel-status>—</dd>
                </dl>
                <div class="small text-muted mb-1">Questions: ✓ answered · ● current · ○ not answered yet</div>
                <div class="live-strip" data-panel-strip></div>
            </div>
        </div>
        <p class="small text-muted mt-3 mb-0">
            <?php $idleFor = McqLiveService::idleSeconds(); ?>
            Updates every few seconds. Idle means no input for <?= $idleFor % 60 === 0 ? (int)($idleFor / 60) . ' minute' . ($idleFor === 60 ? '' : 's') : $idleFor . ' seconds' ?> or the tab is hidden;
            offline means the page stopped reporting in. Correct answers are not shown here.
        </p>
    </div>
</section>
<script src="<?= $h(BASE_URL . 'assets/js/mcq-live-monitor.js?v=' . filemtime(__DIR__ . '/../assets/js/mcq-live-monitor.js')) ?>" defer></script>
<?php endif; ?>

<h2 class="h5">Video lesson result</h2>
<div class="row g-3 mb-3">
    <?php
    $resultCards = [
        'Total marks' => LessonResultBuilder::formatMark((float)$results['lesson_max']),
        'Average marks' => $resultSummary['average_obtained'] === null ? '—' : LessonResultBuilder::formatMark((float)$resultSummary['average_obtained']) . ' / ' . LessonResultBuilder::formatMark((float)$results['lesson_max']),
        'Average' => LessonResultBuilder::formatPercent($resultSummary['average_percent']),
        'Highest' => $resultSummary['highest_obtained'] === null ? '—' : LessonResultBuilder::formatMark((float)$resultSummary['highest_obtained']) . ' / ' . LessonResultBuilder::formatMark((float)$results['lesson_max']),
        'Lowest' => $resultSummary['lowest_obtained'] === null ? '—' : LessonResultBuilder::formatMark((float)$resultSummary['lowest_obtained']) . ' / ' . LessonResultBuilder::formatMark((float)$results['lesson_max']),
        'Median' => LessonResultBuilder::formatPercent($resultSummary['median_percent']),
        'Pass rate' => $results['pass_percent'] === null ? 'Pass mark not set' : LessonResultBuilder::formatPercent($resultSummary['pass_rate']),
        'MCQ average' => LessonResultBuilder::formatPercent($resultSummary['mcq_average_percent']),
        'Essay average' => LessonResultBuilder::formatPercent($resultSummary['essay_average_percent']),
        'Pending marking' => (string)(int)$resultSummary['pending_marking'],
    ];
    if ((float)($results['short_max'] ?? 0) > 0) {
        $resultCards['Short-answer average'] = LessonResultBuilder::formatPercent($resultSummary['short_average_percent'] ?? null);
    }
    if ((float)($results['exam_max'] ?? 0) > 0) {
        $resultCards['Exam-question average'] = LessonResultBuilder::formatPercent($resultSummary['exam_average_percent'] ?? null);
    }
    if ((float)($results['assignment_max'] ?? 0) > 0) {
        $resultCards['Assignment average'] = LessonResultBuilder::formatPercent($resultSummary['assignment_average_percent'] ?? null);
        $resultCards['Assignments submitted'] = (string)(int)($resultSummary['assignment_submitted_students'] ?? 0);
        $resultCards['Assignments pending'] = (string)(int)($resultSummary['assignment_pending_students'] ?? 0);
    }
    foreach ($resultCards as $label => $value):
    ?>
        <div class="col-6 col-md-4 col-xl-2">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body">
                    <div class="text-muted small"><?= $h($label) ?></div>
                    <div class="h5 mb-0"><?= $h((string)$value) ?></div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>
<p class="small text-muted">
    Average, highest, lowest, median, and pass rate use students whose marked work covers the full <?= $h(LessonResultBuilder::formatMark((float)$results['lesson_max'])) ?> marks.
    A missing or unmarked activity is not scored as zero.
    MCQ and essay averages use the marks actually recorded.
</p>
<?php
    $objectiveRows = (new LearningModuleService($pdo))->objectives((int)$onlineLesson['id']);
    $objectiveScores = LearningModuleService::objectivePerformance(
        $objectiveRows,
        (new LearningModuleService($pdo))->objectiveIdsByQuestion((int)$onlineLesson['id']),
        $results['students'] ?? []
    );
    $objectiveShown = array_filter($objectiveScores, static fn (array $row): bool => $row['percent'] !== null);
?>
<?php if ($objectiveShown !== []): ?>
    <h2 class="h5">Learning objective performance</h2>
    <p class="small text-muted">Performance based on linked assessment results.</p>
    <ul>
        <?php foreach ($objectiveShown as $objective): ?>
            <li><?= $h((string)$objective['body']) ?> — <?= $h(LessonResultBuilder::formatPercent($objective['percent'])) ?></li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
<p class="small">
    MCQ: <?= (int)$resultSummary['auto_marked_students'] ?> / <?= (int)$resultSummary['students'] ?> students have an automatically marked submission.
    Essay: <?= (int)$resultSummary['essay_marked_students'] ?> marked · <?= (int)$resultSummary['essay_pending_students'] ?> pending.
</p>
<form class="row g-2 align-items-end mb-3 no-print" method="post">
    <input type="hidden" name="csrf_token" value="<?= $h(csrf_token()) ?>">
    <input type="hidden" name="action" value="save_pass_percent">
    <div class="col-auto">
        <label class="form-label" for="passPercent">Lesson pass mark %</label>
        <input class="form-control" id="passPercent" name="pass_percent" type="number" min="1" max="100" value="<?= $results['pass_percent'] === null ? '' : (int)$results['pass_percent'] ?>" placeholder="Not set">
    </div>
    <div class="col-auto">
        <button class="btn btn-outline-primary" type="submit">Save pass mark</button>
    </div>
</form>
<p class="small no-print">
    <a href="?lesson=<?= $timetableId ?>&export=results">Export results CSV</a>
    · <a href="?lesson=<?= $timetableId ?>&export=detailed">Export detailed results</a>
    · <button class="btn btn-link btn-sm p-0 align-baseline" type="button" onclick="window.print()">Print results</button>
</p>

<?php
    $enrolledN = max(0, (int)$summary['enrolled']);
    $doneW = $enrolledN > 0 ? (int)round((int)$summary['completed'] / $enrolledN * 100) : 0;
    $progW = $enrolledN > 0 ? (int)round((int)$summary['in_progress'] / $enrolledN * 100) : 0;
    $noneW = max(0, 100 - $doneW - $progW);
?>
<?php if ($enrolledN > 0): ?>
<div class="card border-0 shadow-sm rounded-4 mb-4">
    <div class="card-body">
        <div class="small text-muted mb-2">Class progress</div>
        <div class="d-flex rounded overflow-hidden" style="height:14px" role="img" aria-label="Completed <?= (int)$summary['completed'] ?>, in progress <?= (int)$summary['in_progress'] ?>, not started <?= (int)$summary['not_started'] ?>">
            <div class="bg-success" style="width:<?= $doneW ?>%"></div>
            <div class="bg-primary" style="width:<?= $progW ?>%"></div>
            <div class="bg-secondary" style="width:<?= $noneW ?>%"></div>
        </div>
        <div class="small text-muted mt-2">Green completed · blue in progress · grey not started</div>
    </div>
</div>
<?php endif; ?>

<div class="row g-2 align-items-end mb-3 no-print">
    <div class="col-sm-6 col-md-2">
        <label class="form-label" for="olFilterStatus">Status</label>
        <select class="form-select" id="olFilterStatus">
            <option value="">All</option>
            <option value="not_started">Not started</option>
            <option value="in_progress">In progress</option>
            <option value="complete">Completed</option>
            <option value="pending_marking">Pending marking</option>
        </select>
    </div>
    <div class="col-sm-6 col-md-2">
        <label class="form-label" for="olFilterOutcome">Performance</label>
        <select class="form-select" id="olFilterOutcome">
            <option value="">All</option>
            <option value="pass">Pass</option>
            <option value="fail">Fail</option>
            <option value="pending">Pending</option>
        </select>
    </div>
    <div class="col-sm-6 col-md-2">
        <label class="form-label" for="olFilterName">Search student</label>
        <input class="form-control" id="olFilterName" type="search" placeholder="Name or ID" autocomplete="off">
    </div>
    <div class="col-sm-6 col-md-2">
        <label class="form-label" for="olFilterKind">Activity type</label>
        <select class="form-select" id="olFilterKind">
            <option value="">All</option>
            <option value="mcq">MCQ</option>
            <option value="essay">Essay</option>
            <option value="video">Video</option>
            <option value="page">Text</option>
            <option value="external_link">External link</option>
        </select>
    </div>
    <div class="col-6 col-md-2">
        <label class="form-label" for="olFrom">From</label>
        <input class="form-control" id="olFrom" type="date">
    </div>
    <div class="col-6 col-md-2">
        <label class="form-label" for="olTo">To</label>
        <input class="form-control" id="olTo" type="date">
    </div>
</div>

<h2 class="h5">Student results</h2>
<div class="card border-0 shadow-sm rounded-4 mb-4">
    <div class="table-responsive">
        <table class="table align-middle mb-0" id="olStudentTable">
            <thead>
                <tr>
                    <th><button class="btn btn-link btn-sm p-0" type="button" data-sort="name">Student</button></th>
                    <th>Status</th>
                    <th><button class="btn btn-link btn-sm p-0" type="button" data-sort="progress">Progress</button></th>
                    <th><button class="btn btn-link btn-sm p-0" type="button" data-sort="marks">Marks</button></th>
                    <th><button class="btn btn-link btn-sm p-0" type="button" data-sort="percent">Percentage</button></th>
                    <th>MCQ</th>
                    <th>Essay</th>
                    <th>Items</th>
                    <th><button class="btn btn-link btn-sm p-0" type="button" data-sort="time">Time spent</button></th>
                    <th><button class="btn btn-link btn-sm p-0" type="button" data-sort="seen">Last activity</button></th>
                    <th>Result</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($results['students'] as $student):
                $essayCell = '—';
                if ((float)$results['essay_max'] <= 0) {
                    $essayCell = '—';
                } elseif (!empty($student['essay_pending']) && $student['essay_counted_max'] === null) {
                    $essayCell = 'Pending';
                } elseif ($student['essay_obtained'] !== null) {
                    $essayCell = $pair($student['essay_obtained'], $student['essay_counted_max']);
                    if (!empty($student['essay_pending'])) {
                        $essayCell .= ' · pending';
                    }
                } elseif (empty($student['essay_submitted'])) {
                    $essayCell = 'Not submitted';
                }
                $mcqCell = (float)$results['mcq_max'] <= 0 ? '—' : ($student['has_mcq'] ? $pair($student['mcq_obtained'], $student['mcq_counted_max']) : 'Not submitted');
                $resultText = match ((string)($student['outcome'] ?? '')) {
                    'pass' => 'Pass',
                    'fail' => 'Fail',
                    'pending' => 'Pending',
                    default => '—',
                };
            ?>
                <tr
                    data-status="<?= $h((string)$student['status']) ?>"
                    data-marking="<?= !empty($student['essay_pending']) ? 'pending' : '' ?>"
                    data-outcome="<?= $h((string)($student['outcome'] ?? '')) ?>"
                    data-name="<?= $h(strtolower((string)$student['name'] . ' ' . (int)$student['id'])) ?>"
                    data-seen="<?= $student['last_seen_at'] !== '' ? $h(substr((string)$student['last_seen_at'], 0, 10)) : '' ?>"
                    data-sort-name="<?= $h(strtolower((string)$student['name'])) ?>"
                    data-sort-progress="<?= (int)$student['progress'] ?>"
                    data-sort-marks="<?= $student['obtained'] === null ? -1 : $h((string)$student['obtained']) ?>"
                    data-sort-percent="<?= $student['percent'] === null ? -1 : $h((string)$student['percent']) ?>"
                    data-sort-time="<?= (int)$student['active_seconds'] ?>"
                    data-sort-seen="<?= $h((string)$student['last_seen_at']) ?>"
                >
                    <td><a href="<?= $h(campus_lesson_analytics_url($timetableId) . '&student=' . (int)$student['id']) ?>"><?= $h((string)$student['name']) ?></a></td>
                    <td><?= $h($statusLabel((string)$student['status'])) ?></td>
                    <td><?= (int)$student['progress'] ?>%</td>
                    <td><?= $h($pair($student['obtained'], $student['counted_max'])) ?></td>
                    <td><?= $h(LessonResultBuilder::formatPercent($student['percent'])) ?></td>
                    <td><?= $h($mcqCell) ?></td>
                    <td><?= $h($essayCell) ?></td>
                    <td><?= (int)$student['items_done'] ?> / <?= (int)$student['items_total'] ?></td>
                    <td><?= $h(OnlineLessonService::formatActiveTime((int)$student['active_seconds'])) ?></td>
                    <td><?= $student['last_seen_at'] !== '' ? $h(date('d M Y, g:i A', strtotime((string)$student['last_seen_at']))) : 'Never' ?></td>
                    <td><?= $h($resultText) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($lessonInsights !== []): ?>
    <h2 class="h5">What the numbers show</h2>
    <ul class="card border-0 shadow-sm rounded-4 p-3 ps-4 mb-4">
        <?php foreach ($lessonInsights as $insight): ?><li><?= $h($insight['message']) ?></li><?php endforeach; ?>
    </ul>
<?php endif; ?>
<?php if ($advanced && $advanced['attempts'] !== []): ?>
    <h2 class="h5">Attempts: first, latest and best</h2>
    <div class="table-responsive mb-4">
        <table class="table table-sm align-middle">
            <thead><tr><th>Activity</th><th>Students</th><th>Tried again</th><th>Average attempts</th><th>First attempt</th><th>Latest attempt</th><th>Best attempt</th></tr></thead>
            <tbody>
            <?php foreach ($advanced['attempts'] as $row): ?>
                <tr>
                    <td><?= $h($row['title']) ?></td>
                    <td><?= (int)$row['students'] ?></td>
                    <td><?= (int)$row['retried'] ?></td>
                    <td><?= $h((string)$row['avg_attempts']) ?></td>
                    <td><?= (int)$row['first'] ?>%</td>
                    <td><?= (int)$row['latest'] ?>%</td>
                    <td><?= (int)$row['best'] ?>%</td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <p class="small text-muted">Average percentage across students in this class. The mark that counts follows each activity’s scoring rule.</p>
    </div>
<?php endif; ?>
<?php if ($advanced && $advanced['pool'] !== []): ?>
    <h2 class="h5">Question pool usage</h2>
    <div class="table-responsive mb-4">
        <table class="table table-sm align-middle">
            <thead><tr><th>Question</th><th>Difficulty</th><th>Times drawn</th><th>Answered</th><th>Correct</th></tr></thead>
            <tbody>
            <?php foreach ($advanced['pool'] as $row): ?>
                <tr>
                    <td><?= $h($row['prompt']) ?></td>
                    <td><?= $h($row['difficulty'] !== '' ? ucfirst($row['difficulty']) : '—') ?></td>
                    <td><?= (int)$row['shown'] ?></td>
                    <td><?= (int)$row['answered'] ?></td>
                    <td><?= $row['correct_percent'] === null ? '—' : (int)$row['correct_percent'] . '%' ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
<?php if ($advanced && ($advanced['resources'] !== [] || $advanced['submissions'] !== [])): ?>
    <div class="row g-3 mb-4">
        <?php if ($advanced['resources'] !== []): ?>
            <div class="col-lg-6">
                <h2 class="h5">Resource use</h2>
                <table class="table table-sm align-middle">
                    <thead><tr><th>Item</th><th>Students opened</th><th>Total opens</th><th>Completed</th></tr></thead>
                    <tbody>
                    <?php foreach ($advanced['resources'] as $row): ?>
                        <tr>
                            <td><?= $h($row['title']) ?></td>
                            <td><?= (int)$row['opened'] ?> / <?= (int)$row['enrolled'] ?> (<?= (int)$row['percent'] ?>%)</td>
                            <td><?= (int)$row['opens'] ?></td>
                            <td><?= (int)$row['completed'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
        <?php if ($advanced['submissions'] !== []): ?>
            <div class="col-lg-6">
                <h2 class="h5">Assignment submissions</h2>
                <table class="table table-sm align-middle">
                    <thead><tr><th>Assignment</th><th>Submitted</th><th>Late</th><th>Marked</th></tr></thead>
                    <tbody>
                    <?php foreach ($advanced['submissions'] as $row): ?>
                        <tr>
                            <td><?= $h($row['title']) ?><?= $row['due_at'] !== '' ? '<div class="small text-muted">Due ' . $h(date('d M Y, g:i A', strtotime($row['due_at']))) . '</div>' : '' ?></td>
                            <td><?= (int)$row['submitted'] ?> / <?= (int)$row['enrolled'] ?> (<?= (int)$row['percent'] ?>%)</td>
                            <td><?= $row['due_at'] !== '' ? (int)$row['late'] : '—' ?></td>
                            <td><?= (int)$row['marked'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<h2 class="h5">Activity performance</h2>
<div class="row g-3 mb-4" id="olActivities">
    <?php foreach ($results['activities'] as $activity):
        $kindKey = (string)$activity['type'] === 'activity'
            ? (((string)$activity['activity_type'] === 'essay') ? 'essay' : (((string)$activity['activity_type'] === 'mixed') ? 'mixed' : 'mcq'))
            : (string)$activity['type'];
    ?>
        <div class="col-md-6" data-activity="<?= (int)$activity['item_id'] ?>" data-kind="<?= $h($kindKey) ?>">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body">
                    <div class="text-muted small"><?= $h((string)$activity['kind']) ?></div>
                    <h3 class="h6"><?= $h((string)$activity['title']) ?></h3>
                    <?php if (!empty($activity['academic']) && (int)$activity['mcq_count'] > 0): ?>
                        <p class="mb-1"><?= (int)$activity['mcq_count'] ?> questions · maximum <?= $h(LessonResultBuilder::formatMark((float)$activity['mcq_max'])) ?> marks</p>
                        <p class="mb-1">Students <?= (int)$activity['attempted'] ?> / <?= (int)$activity['enrolled'] ?></p>
                        <p class="mb-1">Average <?= $activity['average'] === null ? '—' : $h(LessonResultBuilder::formatMark((float)$activity['average']) . ' / ' . LessonResultBuilder::formatMark((float)$activity['mcq_max'])) ?></p>
                        <p class="mb-1">Highest <?= $activity['highest'] === null ? '—' : $h(LessonResultBuilder::formatMark((float)$activity['highest']) . ' / ' . LessonResultBuilder::formatMark((float)$activity['mcq_max'])) ?>
                            · Lowest <?= $activity['lowest'] === null ? '—' : $h(LessonResultBuilder::formatMark((float)$activity['lowest']) . ' / ' . LessonResultBuilder::formatMark((float)$activity['mcq_max'])) ?></p>
                        <p class="mb-1">Correct <?= (int)$activity['correct'] ?> · Incorrect <?= (int)$activity['incorrect'] ?> · Accuracy <?= $h(LessonResultBuilder::formatPercent($activity['accuracy'])) ?></p>
                    <?php elseif (!empty($activity['academic'])): ?>
                        <p class="mb-1">Maximum <?= $h(LessonResultBuilder::formatMark((float)$activity['max'])) ?> marks</p>
                        <p class="mb-1">Submitted <?= (int)$activity['essay_submitted'] ?> / <?= (int)$activity['enrolled'] ?> · Marked <?= (int)$activity['essay_marked'] ?> · Pending <?= (int)$activity['essay_pending'] ?></p>
                        <p class="mb-1">Average <?= $h(LessonResultBuilder::formatPercent($activity['essay_average'])) ?></p>
                    <?php else: ?>
                        <p class="mb-1">No academic marks</p>
                        <p class="mb-1">Completed <?= (int)$activity['completed'] ?> / <?= (int)$activity['enrolled'] ?> · Started <?= (int)$activity['started'] ?> · Opens <?= (int)$activity['opens'] ?></p>
                        <?php if ((string)$activity['type'] === 'video'): ?>
                            <p class="mb-1">Average watched <?= $activity['avg_watch_percent'] === null ? 'not recorded' : $h((string)$activity['avg_watch_percent'] . '%') ?></p>
                            <p class="mb-1">Not completed <?= (int)$activity['not_completed'] ?></p>
                        <?php endif; ?>
                        <?php if ((string)$activity['type'] === 'external_link'): ?>
                            <p class="mb-1">Opened <?= (int)$activity['completed'] ?> / <?= (int)$activity['enrolled'] ?>. This records the link being opened in the lesson, not time spent on the outside page.</p>
                        <?php endif; ?>
                        <p class="mb-1">Average time <?= $h(OnlineLessonService::formatActiveTime((int)$activity['avg_seconds'])) ?></p>
                    <?php endif; ?>
                    <?php if (!empty($activity['academic'])): ?>
                        <div class="table-responsive mt-2">
                            <table class="table table-sm mb-0">
                                <thead><tr><th>Student</th><th>Marks</th><th>%</th><th>Status</th></tr></thead>
                                <tbody>
                                <?php foreach ($activity['students'] as $row): ?>
                                    <tr>
                                        <td><?= $h((string)$row['name']) ?></td>
                                        <td><?= $row['status'] === 'not_submitted' ? 'Not submitted' : ($row['status'] === 'pending' && $row['obtained'] === null ? 'Pending' : $h($pair($row['obtained'], $row['max']))) ?></td>
                                        <td><?= $h(LessonResultBuilder::formatPercent($row['percent'])) ?></td>
                                        <td><?= $h((string)$row['status']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<?php foreach ($results['activities'] as $activity): ?>
    <?php if ((int)$activity['mcq_count'] < 1 || $activity['questions'] === []): continue; endif; ?>
    <h2 class="h5"><?= $h((string)$activity['title']) ?> — question results</h2>
    <?php if ($activity['distribution'] !== []): ?>
        <div class="card border-0 shadow-sm rounded-4 mb-3">
            <div class="card-body">
                <div class="small text-muted mb-2">Score distribution</div>
                <?php foreach ($activity['distribution'] as $band): ?>
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <div style="width:7rem" class="small"><?= $h((string)$band['label']) ?></div>
                        <div class="flex-grow-1"><div class="bg-primary rounded" style="height:10px;width:<?= (int)$band['width'] ?>%"></div></div>
                        <div class="small" style="width:6rem"><?= (int)$band['count'] ?> students</div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
    <?php if ($activity['lower'] !== []): ?>
        <p class="small text-muted">Below the average success rate for this activity:
            <?php foreach ($activity['lower'] as $question): ?>
                Q<?= (int)$question['n'] ?> — <?= $h(LessonResultBuilder::formatPercent($question['percent'])) ?>
            <?php endforeach; ?>
        </p>
    <?php endif; ?>
    <?php foreach ($activity['questions'] as $question): ?>
        <div class="card border-0 shadow-sm rounded-4 mb-3">
            <div class="card-body">
                <h3 class="h6">Question <?= (int)$question['n'] ?></h3>
                <p><?= $h($plain((string)$question['prompt'])) ?></p>
                <p class="mb-1">Correct answer: <?= $h((string)$question['correct_letter']) ?> · <?= $h(LessonResultBuilder::formatMark((float)$question['max'])) ?> marks</p>
                <p class="mb-1">Average marks <?= $question['average_marks'] === null ? '—' : $h(LessonResultBuilder::formatMark((float)$question['average_marks'])) ?> / <?= $h(LessonResultBuilder::formatMark((float)$question['max'])) ?></p>
                <p class="mb-1">Correct <?= $h(LessonResultBuilder::formatPercent($question['percent'])) ?>
                    <?php if ($question['percent'] !== null): ?> · Incorrect <?= $h(LessonResultBuilder::formatPercent(100 - (float)$question['percent'])) ?><?php endif; ?>
                </p>
                <ul class="mb-0">
                    <?php foreach ($question['choices'] as $choice): ?>
                        <li><?= $h((string)$choice['letter']) ?> — <?= (int)$choice['count'] ?> students<?= $choice['label'] !== '' ? ' · ' . $h($plain((string)$choice['label'])) : '' ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    <?php endforeach; ?>
<?php endforeach; ?>

<script>
(function () {
    var status = document.getElementById('olFilterStatus');
    var outcome = document.getElementById('olFilterOutcome');
    var name = document.getElementById('olFilterName');
    var kind = document.getElementById('olFilterKind');
    var from = document.getElementById('olFrom');
    var to = document.getElementById('olTo');
    var table = document.getElementById('olStudentTable');
    var rows = Array.prototype.slice.call(table.querySelectorAll('tbody tr'));
    var cards = document.querySelectorAll('#olActivities [data-kind]');
    var timer = 0;
    var sortKey = '';
    var sortDir = 1;
    function apply() {
        var want = status.value;
        var wantOutcome = outcome.value;
        var q = name.value.trim().toLowerCase();
        var fromDay = from.value;
        var toDay = to.value;
        rows.forEach(function (row) {
            var seen = row.getAttribute('data-seen') || '';
            var inRange = true;
            if (fromDay !== '' || toDay !== '') {
                inRange = seen !== '' && (fromDay === '' || seen >= fromDay) && (toDay === '' || seen <= toDay);
            }
            var statusOk = want === '' || (want === 'pending_marking' ? row.getAttribute('data-marking') === 'pending' : row.getAttribute('data-status') === want);
            var ok = statusOk
                && (wantOutcome === '' || row.getAttribute('data-outcome') === wantOutcome)
                && (q === '' || (row.getAttribute('data-name') || '').indexOf(q) !== -1)
                && inRange;
            row.hidden = !ok;
        });
        var wantKind = kind.value;
        cards.forEach(function (card) {
            var cardKind = card.getAttribute('data-kind') || '';
            var kindOk = wantKind === '' || cardKind === wantKind || (wantKind === 'mcq' && cardKind === 'mixed') || (wantKind === 'essay' && cardKind === 'mixed');
            card.hidden = !kindOk;
        });
    }
    function sortRows() {
        var body = table.querySelector('tbody');
        var numeric = sortKey !== 'name' && sortKey !== 'seen';
        var sorted = rows.slice().sort(function (a, b) {
            var av = a.getAttribute('data-sort-' + sortKey) || '';
            var bv = b.getAttribute('data-sort-' + sortKey) || '';
            if (numeric) {
                return (parseFloat(av) - parseFloat(bv)) * sortDir;
            }
            return av.localeCompare(bv) * sortDir;
        });
        sorted.forEach(function (row) { body.appendChild(row); });
    }
    status.addEventListener('change', apply);
    outcome.addEventListener('change', apply);
    kind.addEventListener('change', apply);
    from.addEventListener('change', apply);
    to.addEventListener('change', apply);
    name.addEventListener('input', function () {
        window.clearTimeout(timer);
        timer = window.setTimeout(apply, 200);
    });
    table.querySelectorAll('[data-sort]').forEach(function (button) {
        button.addEventListener('click', function () {
            var key = button.getAttribute('data-sort');
            sortDir = sortKey === key ? sortDir * -1 : 1;
            sortKey = key;
            sortRows();
        });
    });
})();
</script>
<?php endif; ?>
<?php endif; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>
