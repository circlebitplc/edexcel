<?php
declare(strict_types=1);

/**
 * Token-gated parent snapshot. No parent login. Shows one student only.
 */
date_default_timezone_set('Asia/Colombo');

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../vendor/autoload.php';

require_once __DIR__ . '/../includes/i18n.php';
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

$token = strtolower(preg_replace('/[^a-f0-9]/', '', (string)($_GET['t'] ?? '')) ?? '');
$invalid = strlen($token) !== 32;
$student = null;
$classes = [];
$attendanceSummary = ['present' => 0, 'absent' => 0, 'late' => 0, 'percent' => null];
$feeDue = 0.0;
$todayLabel = date('l, j F Y');

if (!$invalid && isset($pdo) && $pdo instanceof PDO) {
    try {
        ensure_campus_schema($pdo);
        ensure_classroom_schema($pdo);
        $stmt = $pdo->prepare("
            SELECT
                u.id,
                u.is_active,
                u.deleted_at,
                COALESCE(NULLIF(TRIM(sp.full_name), ''), u.username) AS student_name,
                sp.parent_name,
                sp.parent_view_token_rotated_at
            FROM student_profiles sp
            JOIN users u ON u.id = sp.user_id
            WHERE sp.parent_view_token = ?
              AND u.role = 'student'
            LIMIT 1
        ");
        $stmt->execute([$token]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        if (
            $row
            && (int)($row['is_active'] ?? 0) === 1
            && empty($row['deleted_at'])
        ) {
            $rotatedAt = (string)($row['parent_view_token_rotated_at'] ?? '');
            if ($rotatedAt !== '' && strtotime($rotatedAt) < (time() - 90 * 86400)) {
                $row = null;
            } else {
                if ($rotatedAt === '') {
                    try {
                        $pdo->prepare('UPDATE student_profiles SET parent_view_token_rotated_at = NOW() WHERE user_id = ? AND (parent_view_token_rotated_at IS NULL OR parent_view_token_rotated_at = \'\')')
                            ->execute([(int)$row['id']]);
                    } catch (Throwable $e) {
                    }
                }
            $student = $row;
            $studentId = (int)$row['id'];
            $today = date('Y-m-d');
            $q = $pdo->prepare("
                SELECT
                    tt.id,
                    tt.start_time,
                    tt.end_time,
                    tt.delivery_mode,
                    COALESCE(tt.lesson_status, 'scheduled') AS lesson_status,
                    s.name AS subject_name,
                    r.name AS room_name,
                    t.name AS teacher_name,
                    st.name AS substitute_name,
                    om.status AS meeting_status
                FROM timetable tt
                JOIN student_enrollments se ON se.class_id = tt.class_id AND se.student_id = ?
                JOIN subjects s ON s.id = tt.subject_id
                LEFT JOIN rooms r ON r.id = tt.room_id
                JOIN teachers t ON t.id = tt.teacher_id
                LEFT JOIN teachers st ON st.id = tt.substitute_teacher_id
                LEFT JOIN online_meetings om ON om.timetable_id = tt.id
                WHERE tt.deleted_at IS NULL
                  AND tt.date = ?
                ORDER BY tt.start_time ASC, tt.id ASC
            ");
            $q->execute([$studentId, $today]);
            $classes = $q->fetchAll(PDO::FETCH_ASSOC) ?: [];

            $attStmt = $pdo->prepare("
                SELECT sa.status
                FROM student_attendance sa
                JOIN timetable tt ON tt.id = sa.timetable_id AND tt.deleted_at IS NULL
                WHERE sa.student_id = ?
                  AND tt.date >= ?
            ");
            $attStmt->execute([$studentId, date('Y-m-01')]);
            foreach ($attStmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $att) {
                $status = (string)($att['status'] ?? '');
                if (isset($attendanceSummary[$status])) {
                    $attendanceSummary[$status]++;
                }
            }
            $attTotal = $attendanceSummary['present'] + $attendanceSummary['absent'] + $attendanceSummary['late'];
            $attendanceSummary['percent'] = $attTotal > 0
                ? (int)round((($attendanceSummary['present'] + $attendanceSummary['late']) / $attTotal) * 100)
                : null;

            $fees = (new \Edexcel\Services\FeeStatementService($pdo))->forStudent($studentId);
            $feeDue = (float)($fees['due'] ?? 0);
            }
        }
    } catch (Throwable $e) {
        error_log('parent view: ' . $e->getMessage());
        $student = null;
    }
}

if (!$student) {
    http_response_code(404);
}

$h = static function (mixed $value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
};
?>
<!DOCTYPE html>
<html lang="<?= eck_lang() === 'si' ? 'si' : 'en' ?>" <?= function_exists('app_theme_html_attrs') ? app_theme_html_attrs() : 'data-theme="dark" data-bs-theme="dark"' ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <?php if (function_exists('app_theme_boot_script')) { app_theme_boot_script(); } ?>
    <title><?= $student ? $h($student['student_name']) . ' — today' : 'Link not found' ?> · Edexcel College</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= $h(BASE_URL) ?>assets/css/system.css?v=<?= is_file(__DIR__ . '/../assets/css/system.css') ? filemtime(__DIR__ . '/../assets/css/system.css') : '1' ?>">
    <?php if (function_exists('app_theme_css_link')) { app_theme_css_link(); } ?>
    <style>
        .parent-page { min-height: 100vh; padding: 24px 16px 40px; }
        .parent-shell { width: min(100%, 640px); margin: 0 auto; }
        .parent-card { background: var(--surface); border: 1px solid var(--panel-border); border-radius: var(--radius-xl); box-shadow: var(--shadow-md); padding: 28px 24px; }
        .parent-brand { display: flex; align-items: center; gap: 12px; margin-bottom: 20px; }
        .parent-brand-icon { width: 44px; height: 44px; display: grid; place-items: center; border-radius: 14px; color: #fff; background: linear-gradient(135deg, var(--primary), var(--primary-dark)); }
        .parent-class { border: 1px solid var(--panel-border); border-radius: 14px; padding: 14px 16px; margin-bottom: 10px; }
        .parent-class.is-live { border-color: #e25b5b; }
        .parent-muted { color: var(--muted); }
    </style>
</head>
<body>
<?php if (function_exists('app_theme_render_picker')) { app_theme_render_picker('floating'); } ?>
<main class="parent-page">
    <div class="parent-shell">
        <section class="parent-card">
            <div class="parent-brand">
                <div class="parent-brand-icon"><i class="bi bi-mortarboard-fill"></i></div>
                <div>
                    <div class="fw-bold">Edexcel College</div>
                    <div class="small parent-muted"><?= $h(eck_t('parent.view')) ?></div>
                </div>
            </div>
            <?= eck_lang_toggle() ?>
            <?php if (!$student): ?>
                <h1 class="h4 fw-bold"><?= $h(eck_t('parent.invalid')) ?></h1>
                <p class="parent-muted mb-3"><?= $h(eck_t('parent.invalid_help')) ?></p>
                <a class="btn btn-primary" href="/parent/login.php"><?= $h(eck_t('parent.login')) ?></a>
            <?php else: ?>
                <p class="small parent-muted mb-1"><?= $h($todayLabel) ?></p>
                <h1 class="h3 fw-bold mb-1"><?= $h($student['student_name']) ?></h1>
                <?php if (trim((string)($student['parent_name'] ?? '')) !== ''): ?>
                    <p class="parent-muted">Parent: <?= $h($student['parent_name']) ?></p>
                <?php endif; ?>

                <div class="row g-3 mb-4">
                    <div class="col-6">
                        <div class="border rounded-4 p-3 h-100">
                            <div class="small parent-muted">This month</div>
                            <div class="fs-4 fw-bold">
                                <?= $attendanceSummary['percent'] !== null ? (int)$attendanceSummary['percent'] . '%' : '—' ?>
                            </div>
                            <div class="small">Attendance</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="border rounded-4 p-3 h-100">
                            <div class="small parent-muted">Class fees</div>
                            <div class="fs-4 fw-bold">
                                <?= $feeDue > 0 ? 'Rs ' . number_format($feeDue) : 'Paid' ?>
                            </div>
                            <div class="small"><?= $feeDue > 0 ? 'Amount due' : 'No outstanding fees' ?></div>
                        </div>
                    </div>
                </div>

                <h2 class="h6 text-uppercase parent-muted fw-bold">Today’s classes</h2>
                <?php if (!$classes): ?>
                    <p class="parent-muted mb-0">No classes scheduled today.</p>
                <?php else: ?>
                    <?php foreach ($classes as $row): ?>
                        <?php
                        $status = (string)($row['lesson_status'] ?? 'scheduled');
                        $online = classroom_is_online_mode((string)($row['delivery_mode'] ?? 'physical'));
                        $live = $online && (string)($row['meeting_status'] ?? '') === 'live';
                        $time = !empty($row['start_time']) ? date('g:i A', strtotime((string)$row['start_time'])) : '';
                        $end = !empty($row['end_time']) ? date('g:i A', strtotime((string)$row['end_time'])) : '';
                        $when = $time . ($end !== '' ? ' – ' . $end : '');
                        $teacher = trim((string)(($row['substitute_name'] ?: $row['teacher_name']) ?? ''));
                        $place = classroom_lesson_place_line($row);
                        ?>
                        <div class="parent-class<?= $live ? ' is-live' : '' ?>">
                            <div class="d-flex justify-content-between gap-2">
                                <strong><?= $h($row['subject_name'] ?? 'Class') ?></strong>
                                <?php if ($status === 'cancelled'): ?>
                                    <span class="badge text-bg-secondary">Cancelled</span>
                                <?php elseif ($live): ?>
                                    <span class="badge text-bg-danger">Live now</span>
                                <?php elseif ($online): ?>
                                    <span class="badge text-bg-primary">Online</span>
                                <?php endif; ?>
                            </div>
                            <div class="small parent-muted mt-1"><?= $h($when) ?><?= $teacher !== '' ? ' · ' . $h($teacher) : '' ?></div>
                            <div class="small mt-1">
                                <?php if ($status === 'cancelled'): ?>
                                    This class will not run.
                                <?php elseif ($live): ?>
                                    Your child joins from the student portal (login required).
                                <?php else: ?>
                                    <?= $h($place) ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
                <p class="small parent-muted mt-4 mb-0">
                    <a href="/parent/login.php"><?= $h(eck_t('parent.login')) ?></a>
                    to pay class fees by card or bank transfer
                    · Keep the link private.
                </p>
            <?php endif; ?>
        </section>
    </div>
</main>
<?php if (function_exists('app_theme_js_link')) { app_theme_js_link(); } ?>
</body>
</html>
