<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../includes/i18n.php';

use Edexcel\Services\FeeStatementService;
use Edexcel\Services\OnlineLessonService;
use Edexcel\Services\ParentAuthService;

header('Cache-Control: no-store');

if (!ParentAuthService::isLoggedIn() || !isset($pdo) || !($pdo instanceof PDO)) {
    header('Location: /parent/login.php');
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

// IDOR: never trust a student id that is not in the verified children list
if ($studentId > 0 && !in_array($studentId, $ids, true)) {
    http_response_code(403);
    exit('Forbidden');
}

$student = null;
foreach ($children as $c) {
    if ((int)$c['id'] === $studentId) {
        $student = $c;
        break;
    }
}

$classes = [];
$lessonProgressMap = [];
$upcomingPay = [];
$attendanceSummary = ['present' => 0, 'absent' => 0, 'late' => 0, 'percent' => null];
$statement = ['due' => 0, 'lessons' => [], 'wallet' => []];
if ($studentId > 0) {
    try {
        $today = date('Y-m-d');
        $monthStart = date('Y-m-01');
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
            array_map(static fn (array $row): int => (int)$row['id'], $classes)
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
            LIMIT 20
        ");
        $up->execute([$studentId]);
        $upcoming = $up->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $feeMap = (new \Edexcel\Services\StudentLessonFeeService($pdo))->mapForStudentLessons($studentId, $upcoming);
        foreach ($upcoming as $row) {
            $info = $feeMap[(int)$row['id']] ?? null;
            if ($info && !empty($info['needs_pay'])) {
                $row['_fee'] = $info;
                $upcomingPay[] = $row;
            }
        }
        $attStmt = $pdo->prepare("
            SELECT sa.status FROM student_attendance sa
            JOIN timetable tt ON tt.id = sa.timetable_id AND tt.deleted_at IS NULL
            WHERE sa.student_id = ? AND tt.date >= ?
        ");
        $attStmt->execute([$studentId, $monthStart]);
        foreach ($attStmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $att) {
            $st = (string)($att['status'] ?? '');
            if (isset($attendanceSummary[$st])) {
                $attendanceSummary[$st]++;
            }
        }
        $attTotal = $attendanceSummary['present'] + $attendanceSummary['absent'] + $attendanceSummary['late'];
        $attendanceSummary['percent'] = $attTotal > 0
            ? (int)round((($attendanceSummary['present'] + $attendanceSummary['late']) / $attTotal) * 100)
            : null;
        $statement = (new FeeStatementService($pdo))->forStudent($studentId);
    } catch (Throwable $e) {
        error_log('parent home: ' . $e->getMessage());
    }
}

$h = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$lang = eck_lang();
?>
<!DOCTYPE html>
<html lang="<?= $lang === 'si' ? 'si' : 'en' ?>" <?= function_exists('app_theme_html_attrs') ? app_theme_html_attrs() : 'data-theme="dark" data-bs-theme="dark"' ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <?php if (function_exists('app_theme_boot_script')) { app_theme_boot_script(); } ?>
    <title><?= $h(eck_t('parent.view')) ?> · Edexcel College</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="/assets/css/system.css">
    <?php if (function_exists('app_theme_css_link')) { app_theme_css_link(); } ?>
</head>
<body>
<?php if (function_exists('app_theme_render_picker')) { app_theme_render_picker('floating'); } ?>
<main style="min-height:100vh;padding:24px 16px">
    <div style="width:min(100%,720px);margin:0 auto">
        <?= eck_lang_toggle() ?>
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <div class="fw-bold"><?= $h(eck_t('parent.brand')) ?></div>
                <div class="small text-muted"><?= $h(eck_t('parent.view')) ?></div>
            </div>
            <div class="d-flex gap-2">
                <a class="btn btn-sm btn-outline-primary" href="/parent/dashboard.php<?= $studentId > 0 ? '?student=' . $studentId : '' ?>">Full dashboard</a>
                <a class="btn btn-sm btn-outline-primary" href="/parent/history.php">History</a>
                <a class="btn btn-sm btn-outline-primary" href="/parent/communications.php">Messages</a>
                <a class="btn btn-sm btn-outline-primary" href="/parent/assessments.php">Assessments</a>
                <a class="btn btn-sm btn-outline-secondary" href="/parent/logout.php"><?= $h(eck_t('parent.logout')) ?></a>
            </div>
        </div>

        <?php if ($children === []): ?>
            <div class="alert alert-warning">
                <strong>Your parent account is awaiting verification.</strong>
                You do not have access to any student information yet.
                <div class="mt-2">
                    <a class="btn btn-sm btn-primary" href="/parent/verify.php">Request student access</a>
                </div>
                <div class="small text-muted mt-2">
                    If you signed in with WhatsApp OTP, ask the college to save your number as the parent WhatsApp on the student profile.
                </div>
            </div>
        <?php else: ?>
            <div class="mb-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <div class="small text-muted mb-1"><?= $h(eck_t('parent.children')) ?> · My Children</div>
                    <?php foreach ($children as $c): ?>
                        <a class="btn btn-sm <?= (int)$c['id'] === $studentId ? 'btn-primary' : 'btn-outline-primary' ?> me-1 mb-1"
                           href="?student=<?= (int)$c['id'] ?>">✓ <?= $h($c['student_name']) ?> <span class="opacity-75">ID <?= (int)$c['id'] ?></span></a>
                    <?php endforeach; ?>
                </div>
                <a class="btn btn-sm btn-outline-secondary" href="/parent/verify.php">Link another student</a>
            </div>

            <h1 class="h4 fw-bold"><?= $h($student['student_name'] ?? '') ?></h1>
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm rounded-4 p-3">
                        <div class="small text-muted"><?= $h(eck_t('parent.fees')) ?></div>
                        <div class="fs-4 fw-bold">Rs <?= number_format((float)($statement['due'] ?? 0)) ?></div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm rounded-4 p-3">
                        <div class="small text-muted"><?= $h(eck_t('parent.attendance')) ?></div>
                        <div class="fs-4 fw-bold"><?= $attendanceSummary['percent'] !== null ? (int)$attendanceSummary['percent'] . '%' : '—' ?></div>
                        <div class="small"><?= (int)$attendanceSummary['absent'] ?> <?= $h(eck_t('att.absent')) ?></div>
                    </div>
                </div>
            </div>

            <h2 class="h5"><?= $h(eck_t('parent.today')) ?></h2>
            <?php if (!$classes): ?>
                <p class="text-muted"><?= $h(eck_t('parent.no_classes')) ?></p>
            <?php endif; ?>
            <?php foreach ($classes as $row): ?>
                <div class="border rounded-4 p-3 mb-2">
                    <div class="fw-semibold"><?= $h($row['subject_name']) ?></div>
                    <div class="small text-muted">
                        <?= $h(date('g:i A', strtotime((string)$row['start_time']))) ?>
                        · <?= $h($row['teacher_name']) ?>
                        · <?= (string)$row['delivery_mode'] === 'online' ? 'Online' : $h($row['room_name']) ?>
                    </div>
                    <?php
                        $prog = $lessonProgressMap[(int)$row['id']] ?? null;
                        if ($prog && (int)$prog['total'] > 0):
                    ?>
                        <div class="small mt-1"><?= (int)$prog['completed'] ?> of <?= (int)$prog['total'] ?> video lesson items complete</div>
                    <?php endif; ?>
                    <?php if (!empty($row['id'])): ?>
                        <a class="btn btn-sm btn-outline-primary mt-2" href="<?= $h(parent_class_page_url((int)$row['id'], $studentId)) ?>">Open class / pay</a>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>

            <?php if ($upcomingPay !== []): ?>
                <h2 class="h5 mt-4">Unpaid classes</h2>
                <p class="small text-muted">Pay before class starts — card (OnePay) or bank slip.</p>
                <?php foreach ($upcomingPay as $row): ?>
                    <div class="border rounded-4 p-3 mb-2">
                        <div class="fw-semibold"><?= $h($row['subject_name']) ?></div>
                        <div class="small text-muted">
                            <?= $h(date('D d M', strtotime((string)$row['date']))) ?>
                            · <?= $h(date('g:i A', strtotime((string)$row['start_time']))) ?>
                            · Rs <?= number_format((float)($row['_fee']['amount_due'] ?? 0), 2) ?>
                        </div>
                        <a class="btn btn-sm btn-primary mt-2" href="<?= $h(parent_class_page_url((int)$row['id'], $studentId)) ?>">Pay now</a>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</main>
<?php if (function_exists('app_theme_js_link')) { app_theme_js_link(); } ?>
</body>
</html>
