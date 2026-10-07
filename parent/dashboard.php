<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../includes/i18n.php';

use Edexcel\Services\AcademicProgressService;
use Edexcel\Services\FeeStatementService;
use Edexcel\Services\NotificationCenterService;
use Edexcel\Services\OfficialExamService;
use Edexcel\Services\OnlineLessonService;
use Edexcel\Services\ParentAuthService;

header('Cache-Control: no-store');

if (!ParentAuthService::isLoggedIn() || !isset($pdo) || !($pdo instanceof PDO)) {
    header('Location: /portal/login.php');
    exit;
}

require_parent();

$auth = new ParentAuthService($pdo);
$parentId = (int)$_SESSION['parent_id'];
if (!$auth->isPortalAllowed($parentId)) {
    ParentAuthService::logout();
    header('Location: /portal/login.php?error=' . rawurlencode('This parent account cannot access the portal.'));
    exit;
}
$children = $auth->children($parentId);
$studentId = (int)($_GET['student'] ?? $_SESSION['parent_student_id'] ?? 0);
$ids = array_map(static fn($c) => (int)$c['id'], $children);
if (!in_array($studentId, $ids, true)) {
    $studentId = $ids[0] ?? 0;
}
$_SESSION['parent_student_id'] = $studentId;

$student = null;
foreach ($children as $c) {
    if ((int)$c['id'] === $studentId) {
        $student = $c;
        break;
    }
}

$classes = [];
$upcoming = [];
$lessonProgressMap = [];
$statement = ['due' => 0, 'paid' => 0, 'lessons' => [], 'receipts' => [], 'wallet' => []];
$progress = [
    'attendance_percent' => null,
    'homework' => ['completion_percent' => null],
    'recent_marks' => [],
    'subject_averages' => [],
];
$exams = [];
$homework = [];
$recordings = [];
$documents = [];
$teachers = [];
$notifications = [];

if ($studentId > 0) {
    try {
        $today = date('Y-m-d');
        $q = $pdo->prepare("
            SELECT tt.id, tt.start_time, tt.end_time, tt.delivery_mode, s.name AS subject_name, r.name AS room_name, t.name AS teacher_name
            FROM timetable tt
            JOIN student_enrollments se ON se.class_id = tt.class_id AND se.student_id = ?
            JOIN subjects s ON s.id = tt.subject_id
            LEFT JOIN rooms r ON r.id = tt.room_id
            JOIN teachers t ON t.id = tt.teacher_id
            WHERE tt.deleted_at IS NULL AND tt.date = ?
            ORDER BY tt.start_time
        ");
        $q->execute([$studentId, $today]);
        $classes = $q->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $lessonProgressMap = (new OnlineLessonService($pdo))->mapProgressForStudent(
            $studentId,
            array_map(static fn(array $row): int => (int)$row['id'], $classes)
        );

        $up = $pdo->prepare("
            SELECT tt.*, s.name AS subject_name, c.name AS class_name, r.name AS room_name, t.name AS teacher_name
            FROM timetable tt
            JOIN student_enrollments se ON se.class_id = tt.class_id AND se.student_id = ?
            JOIN subjects s ON s.id = tt.subject_id
            JOIN student_classes c ON c.id = tt.class_id
            LEFT JOIN rooms r ON r.id = tt.room_id
            JOIN teachers t ON t.id = tt.teacher_id
            WHERE tt.deleted_at IS NULL
              AND tt.date >= CURDATE()
              AND COALESCE(tt.lesson_status, 'scheduled') <> 'cancelled'
            ORDER BY tt.date ASC, tt.start_time ASC
            LIMIT 12
        ");
        $up->execute([$studentId]);
        $upcoming = $up->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $statement = (new FeeStatementService($pdo))->forStudent($studentId);
        $progress = (new AcademicProgressService($pdo))->forStudent($studentId);

        try {
            $exams = (new OfficialExamService($pdo))->studentTimetable($studentId);
        } catch (Throwable $e) {
            $exams = [];
        }

        $hw = $pdo->prepare("
            SELECT h.id, h.title, h.due_date, s.name AS subject_name, sub.status AS submission_status
            FROM student_homework h
            LEFT JOIN subjects s ON s.id = h.subject_id
            LEFT JOIN student_homework_submissions sub ON sub.homework_id = h.id AND sub.student_id = ?
            WHERE h.class_id IN (SELECT class_id FROM student_enrollments WHERE student_id = ?)
            ORDER BY h.due_date IS NULL, h.due_date ASC
            LIMIT 12
        ");
        $hw->execute([$studentId, $studentId]);
        $homework = $hw->fetchAll(PDO::FETCH_ASSOC) ?: [];

        try {
            $rec = $pdo->prepare("
                SELECT r.id, r.title, r.created_at, s.name AS subject_name
                FROM class_recordings r
                JOIN timetable tt ON tt.id = r.timetable_id AND tt.deleted_at IS NULL
                JOIN student_enrollments se ON se.class_id = tt.class_id AND se.student_id = ?
                LEFT JOIN subjects s ON s.id = tt.subject_id
                WHERE r.status = 'ready' AND r.deleted_at IS NULL
                ORDER BY r.created_at DESC
                LIMIT 8
            ");
            $rec->execute([$studentId]);
            $recordings = $rec->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            $recordings = [];
        }

        try {
            $doc = $pdo->prepare("
                SELECT id, title, file_url, created_at
                FROM student_documents
                WHERE student_id = ?
                ORDER BY created_at DESC
                LIMIT 8
            ");
            $doc->execute([$studentId]);
            $documents = $doc->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            $documents = [];
        }

        $tch = $pdo->prepare("
            SELECT DISTINCT t.id, t.name, s.name AS subject_name
            FROM timetable tt
            JOIN student_enrollments se ON se.class_id = tt.class_id AND se.student_id = ?
            JOIN teachers t ON t.id = tt.teacher_id
            JOIN subjects s ON s.id = tt.subject_id
            WHERE tt.deleted_at IS NULL AND tt.date >= DATE_SUB(CURDATE(), INTERVAL 60 DAY)
            ORDER BY t.name
            LIMIT 20
        ");
        $tch->execute([$studentId]);
        $teachers = $tch->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $nc = new NotificationCenterService($pdo);
        $notifications = $nc->listFor('parent', null, $parentId, ['limit' => 10]);
    } catch (Throwable $e) {
        error_log('parent dashboard: ' . $e->getMessage());
    }
}

$h = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$lang = eck_lang();
$receipts = is_array($statement['receipts'] ?? null) ? $statement['receipts'] : [];
?>
<!DOCTYPE html>
<html lang="<?= $lang === 'si' ? 'si' : 'en' ?>" <?= function_exists('app_theme_html_attrs') ? app_theme_html_attrs() : 'data-theme="dark" data-bs-theme="dark"' ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <?php if (function_exists('app_theme_boot_script')) { app_theme_boot_script(); } ?>
    <title>Parent Portal · Edexcel College</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="/assets/css/system.css">
    <link rel="stylesheet" href="/assets/css/a11y-mobile-v2.css">
    <?php if (function_exists('app_theme_css_link')) { app_theme_css_link(); } ?>
</head>
<body>
<?php if (function_exists('app_theme_render_picker')) { app_theme_render_picker('floating'); } ?>
<main class="py-4 px-3" style="min-height:100vh">
    <div style="width:min(100%,920px);margin:0 auto">
        <?= eck_lang_toggle() ?>
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <div>
                <div class="fw-bold fs-5">Parent Portal</div>
                <div class="small text-muted">Edexcel College · live view for your child</div>
            </div>
            <div class="d-flex gap-2">
                <a class="btn btn-sm btn-outline-secondary" href="/parent/home.php">Simple view</a>
                <a class="btn btn-sm btn-outline-secondary" href="/parent/logout.php"><?= $h(eck_t('parent.logout')) ?></a>
            </div>
        </div>

        <?php if ($children === []): ?>
            <div class="alert alert-warning">No students are linked to this WhatsApp number. Ask the college to save it as the parent number on the student profile.</div>
        <?php else: ?>
            <div class="mb-3">
                <div class="small text-muted mb-1">Children</div>
                <?php foreach ($children as $c): ?>
                    <a class="btn btn-sm <?= (int)$c['id'] === $studentId ? 'btn-primary' : 'btn-outline-primary' ?> me-1 mb-1"
                       href="?student=<?= (int)$c['id'] ?>"><?= $h($c['student_name']) ?></a>
                <?php endforeach; ?>
            </div>

            <h1 class="h4 fw-bold mb-3"><?= $h($student['student_name'] ?? '') ?></h1>

            <div class="row g-3 mb-4">
                <div class="col-6 col-md-3">
                    <div class="border rounded-4 p-3 h-100">
                        <div class="small text-muted">Fees due</div>
                        <div class="fs-4 fw-bold">Rs <?= number_format((float)($statement['due'] ?? 0)) ?></div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="border rounded-4 p-3 h-100">
                        <div class="small text-muted">Attendance</div>
                        <div class="fs-4 fw-bold"><?= $progress['attendance_percent'] !== null ? $h((string)$progress['attendance_percent']) . '%' : '—' ?></div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="border rounded-4 p-3 h-100">
                        <div class="small text-muted">Homework</div>
                        <div class="fs-4 fw-bold"><?= $progress['homework']['completion_percent'] !== null ? $h((string)$progress['homework']['completion_percent']) . '%' : '—' ?></div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="border rounded-4 p-3 h-100">
                        <div class="small text-muted">Paid (ledger)</div>
                        <div class="fs-4 fw-bold">Rs <?= number_format((float)($statement['paid'] ?? 0)) ?></div>
                    </div>
                </div>
            </div>

            <section class="mb-4">
                <h2 class="h5">Today's classes</h2>
                <?php if (!$classes): ?>
                    <p class="text-muted">No classes scheduled today.</p>
                <?php endif; ?>
                <?php foreach ($classes as $row): ?>
                    <div class="border rounded-4 p-3 mb-2">
                        <div class="fw-semibold"><?= $h($row['subject_name']) ?></div>
                        <div class="small text-muted">
                            <?= $h(date('g:i A', strtotime((string)$row['start_time']))) ?>
                            · <?= $h($row['teacher_name']) ?>
                            · <?= (string)$row['delivery_mode'] === 'online' ? 'Online' : $h($row['room_name']) ?>
                        </div>
                        <?php $prog = $lessonProgressMap[(int)$row['id']] ?? null; if ($prog && (int)$prog['total'] > 0): ?>
                            <div class="small mt-1"><?= (int)$prog['completed'] ?> of <?= (int)$prog['total'] ?> video items complete</div>
                        <?php endif; ?>
                        <a class="btn btn-sm btn-outline-primary mt-2" href="<?= $h(parent_class_page_url((int)$row['id'], $studentId)) ?>">Open class / pay</a>
                    </div>
                <?php endforeach; ?>
            </section>

            <section class="mb-4">
                <h2 class="h5">Upcoming</h2>
                <?php if (!$upcoming): ?><p class="text-muted">No upcoming classes.</p><?php endif; ?>
                <?php foreach (array_slice($upcoming, 0, 8) as $row): ?>
                    <div class="border rounded-4 p-3 mb-2 d-flex justify-content-between gap-2 flex-wrap">
                        <div>
                            <div class="fw-semibold"><?= $h($row['subject_name']) ?></div>
                            <div class="small text-muted"><?= $h(date('D d M', strtotime((string)$row['date']))) ?> · <?= $h(date('g:i A', strtotime((string)$row['start_time']))) ?></div>
                        </div>
                        <a class="btn btn-sm btn-outline-secondary align-self-center" href="<?= $h(parent_class_page_url((int)$row['id'], $studentId)) ?>">Open</a>
                    </div>
                <?php endforeach; ?>
            </section>

            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <h2 class="h5">Marks</h2>
                    <?php if (empty($progress['recent_marks'])): ?>
                        <p class="text-muted small">No published marks yet.</p>
                    <?php else: ?>
                        <?php foreach (array_slice($progress['recent_marks'], 0, 6) as $m): ?>
                            <div class="small border-bottom py-2 d-flex justify-content-between gap-2">
                                <span><?= $h($m['subject_name'] ?: $m['metric']) ?></span>
                                <span><?= $m['percent'] !== null ? $h((string)$m['percent']) . '%' : $h($m['score'] . '/' . $m['max_score']) ?></span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <div class="col-md-6">
                    <h2 class="h5">Homework</h2>
                    <?php if (!$homework): ?>
                        <p class="text-muted small">No homework listed.</p>
                    <?php else: ?>
                        <?php foreach ($homework as $hwRow): ?>
                            <div class="small border-bottom py-2">
                                <div class="fw-semibold"><?= $h($hwRow['title']) ?></div>
                                <div class="text-muted">
                                    <?= $h($hwRow['subject_name'] ?? '') ?>
                                    <?php if (!empty($hwRow['due_date'])): ?> · due <?= $h($hwRow['due_date']) ?><?php endif; ?>
                                    <?php if (!empty($hwRow['submission_status'])): ?> · <?= $h($hwRow['submission_status']) ?><?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <section class="mb-4">
                <h2 class="h5">Official exams</h2>
                <?php if (!$exams): ?>
                    <p class="text-muted small">No official papers selected for this student.</p>
                <?php else: ?>
                    <?php foreach (array_slice($exams, 0, 8) as $ex): ?>
                        <div class="border rounded-4 p-3 mb-2">
                            <div class="fw-semibold"><?= $h($ex['subject'] ?? '') ?> — <?= $h($ex['unit_title'] ?? '') ?></div>
                            <div class="small text-muted">
                                <?= $h($ex['date_label'] ?? $ex['exam_date'] ?? '') ?>
                                <?php if (!empty($ex['countdown_label'])): ?> · <?= $h($ex['countdown_label']) ?><?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </section>

            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <h2 class="h5">Teachers</h2>
                    <?php if (!$teachers): ?><p class="text-muted small">No recent teacher links.</p><?php endif; ?>
                    <?php foreach ($teachers as $t): ?>
                        <div class="small border-bottom py-2"><?= $h($t['name']) ?> <span class="text-muted">· <?= $h($t['subject_name']) ?></span></div>
                    <?php endforeach; ?>
                </div>
                <div class="col-md-6">
                    <h2 class="h5">Recordings</h2>
                    <?php if (!$recordings): ?><p class="text-muted small">No accessible recordings listed.</p><?php endif; ?>
                    <?php foreach ($recordings as $r): ?>
                        <div class="small border-bottom py-2">
                            <?= $h($r['title'] ?: ($r['subject_name'] ?? 'Recording')) ?>
                            <span class="text-muted">· <?= $h(substr((string)($r['created_at'] ?? ''), 0, 10)) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <section class="mb-4">
                <h2 class="h5">Documents</h2>
                <?php if (!$documents): ?><p class="text-muted small">No documents on file.</p><?php endif; ?>
                <?php foreach ($documents as $d): ?>
                    <div class="small border-bottom py-2 d-flex justify-content-between gap-2">
                        <span><?= $h($d['title'] ?? 'Document') ?></span>
                        <?php if (!empty($d['file_url'])): ?>
                            <a href="<?= $h($d['file_url']) ?>" target="_blank" rel="noopener">Open</a>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </section>

            <section class="mb-4">
                <h2 class="h5">Payment history</h2>
                <?php if ($receipts === []): ?>
                    <p class="text-muted small">No payment receipts yet.</p>
                <?php else: ?>
                    <?php foreach (array_slice($receipts, 0, 10) as $rcpt): ?>
                        <div class="small border-bottom py-2 d-flex justify-content-between gap-2">
                            <span>
                                <?php
                                $rawGateway = strtolower((string)($rcpt['gateway'] ?? ''));
                                $shownMethod = \Edexcel\Services\StudentLessonFeeService::displayMethod($rcpt);
                                $shownLabel = $shownMethod !== $rawGateway
                                    ? \Edexcel\Services\StudentLessonFeeService::gatewayLabel($shownMethod)
                                    : (string)($rcpt['gateway'] ?? $rcpt['label'] ?? 'Payment');
                                ?>
                                <?= $h($shownLabel) ?>
                                · <?= $h((string)($rcpt['paid_at'] ?? $rcpt['created_at'] ?? '')) ?>
                            </span>
                            <span>Rs <?= number_format((float)($rcpt['amount'] ?? $rcpt['amount_paid'] ?? 0), 2) ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </section>

            <section class="mb-4">
                <h2 class="h5">Notifications</h2>
                <?php if (!$notifications): ?><p class="text-muted small">No parent notifications.</p><?php endif; ?>
                <?php foreach ($notifications as $n): ?>
                    <div class="border rounded-4 p-3 mb-2 <?= empty($n['is_read']) ? '' : 'opacity-75' ?>">
                        <div class="fw-semibold"><?= $h($n['title']) ?></div>
                        <?php if (!empty($n['body'])): ?><div class="small"><?= $h($n['body']) ?></div><?php endif; ?>
                        <div class="small text-muted"><?= $h($n['category'] ?? '') ?> · <?= $h($n['created_at'] ?? '') ?></div>
                    </div>
                <?php endforeach; ?>
            </section>
        <?php endif; ?>
    </div>
</main>
<?php if (function_exists('app_theme_js_link')) { app_theme_js_link(); } ?>
</body>
</html>
