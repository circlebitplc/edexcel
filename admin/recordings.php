<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/bunny.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Edexcel\Services\BunnyVideoService;
use Edexcel\Services\RecordingService;
use Edexcel\Services\StudentLessonFeeService;

require_admin();
ensure_recordings_schema($pdo);

$error = '';
$success = '';
$recordings = new RecordingService($pdo);
$fees = new StudentLessonFeeService($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token((string)($_POST['csrf_token'] ?? ''))) {
        $error = 'Invalid security token.';
    } else {
        $action = (string)($_POST['action'] ?? '');
        if ($action === 'archive') {
            if ($recordings->archive((int)($_POST['recording_id'] ?? 0), 0, true)) {
                $success = 'Recording archived.';
            } else {
                $error = 'Recording not found.';
            }
        } elseif ($action === 'waive') {
            $lessonStmt = $pdo->prepare('SELECT * FROM timetable WHERE id = ? LIMIT 1');
            $lessonStmt->execute([(int)($_POST['timetable_id'] ?? 0)]);
            $lesson = $lessonStmt->fetch(PDO::FETCH_ASSOC);
            if (!$lesson) {
                $error = 'Lesson not found.';
            } else {
                $fees->ensureRow((int)$_POST['student_id'], $lesson);
                $fees->waive((int)$_POST['student_id'], (int)$lesson['id'], (int)($_SESSION['user_id'] ?? 0));
                log_audit($pdo, 'lesson_fee_waive', 'student_lesson_fees', (int)$lesson['id'], null, [
                    'student_id' => (int)$_POST['student_id'],
                ]);
                $success = 'Lesson fee waived.';
            }
        } elseif ($action === 'cash') {
            $lessonStmt = $pdo->prepare("
                SELECT tt.*, s.name AS subject_name
                FROM timetable tt
                JOIN subjects s ON s.id = tt.subject_id
                WHERE tt.id = ?
                LIMIT 1
            ");
            $lessonStmt->execute([(int)($_POST['timetable_id'] ?? 0)]);
            $lesson = $lessonStmt->fetch(PDO::FETCH_ASSOC);
            if (!$lesson) {
                $error = 'Lesson not found.';
            } else {
                $result = $fees->collectCash(
                    (int)$_POST['student_id'],
                    $lesson,
                    (int)($_SESSION['user_id'] ?? 0),
                    (int)($lesson['recording_id'] ?? $_POST['recording_id'] ?? 0) ?: null
                );
                log_audit($pdo, 'lesson_fee_cash', 'student_lesson_fees', (int)$lesson['id'], null, [
                    'student_id' => (int)$_POST['student_id'],
                    'transaction_id' => $result['transaction_id'] ?? null,
                ]);
                $success = !empty($result['already']) ? 'Already paid.' : 'Cash payment recorded. Student can watch the recording.';
            }
        } elseif ($action === 'unpay') {
            $lessonStmt = $pdo->prepare("
                SELECT tt.*, s.name AS subject_name
                FROM timetable tt
                JOIN subjects s ON s.id = tt.subject_id
                WHERE tt.id = ?
                LIMIT 1
            ");
            $lessonStmt->execute([(int)($_POST['timetable_id'] ?? 0)]);
            $lesson = $lessonStmt->fetch(PDO::FETCH_ASSOC);
            if (!$lesson) {
                $error = 'Lesson not found.';
            } else {
                try {
                    $result = $fees->unpay(
                        (int)$_POST['student_id'],
                        $lesson,
                        (int)($_SESSION['user_id'] ?? 0),
                        true
                    );
                    log_audit($pdo, 'lesson_fee_unpay', 'student_lesson_fees', (int)$lesson['id'], null, [
                        'student_id' => (int)$_POST['student_id'],
                    ]);
                    $success = !empty($result['already'])
                        ? 'Already unpaid.'
                        : 'Payment unmarked. If this was OnePay, a card refund was requested.';
                } catch (Throwable $e) {
                    $error = $e->getMessage();
                }
            }
        } elseif ($action === 'sync') {
            try {
                $assets = $recordings->assets((int)$_POST['recording_id']);
                $synced = 0;
                foreach ($assets as $asset) {
                    $libraryId = trim((string)($asset['bunny_library_id'] ?? ''));
                    $bunny = $libraryId !== ''
                        ? BunnyVideoService::forLibraryId($pdo, $libraryId)
                        : BunnyVideoService::tryForStoredVideo($pdo, (string)$asset['bunny_video_id']);
                    if ($bunny === null) {
                        continue;
                    }
                    $video = $bunny->getVideo((string)$asset['bunny_video_id']);
                    $recordings->applyBunnyMetadata((string)$asset['bunny_video_id'], $video, $bunny);
                    $synced++;
                }
                if ($synced === 0) {
                    $error = 'Could not refresh metadata. The lesson teacher needs a Bunny library assigned.';
                } else {
                    $success = 'Metadata refreshed from Bunny.';
                }
            } catch (Throwable $e) {
                $error = 'Could not refresh metadata.';
            }
        }
    }
}

$from = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_GET['from'] ?? '')) ? (string)$_GET['from'] : date('Y-m-01');
$to = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_GET['to'] ?? '')) ? (string)$_GET['to'] : date('Y-m-d');
$teacherFilter = (int)($_GET['teacher_id'] ?? 0);
$classFilter = (int)($_GET['class_id'] ?? 0);
$subjectFilter = (int)($_GET['subject_id'] ?? 0);
$statusFilter = trim((string)($_GET['status'] ?? ''));
$payFilter = trim((string)($_GET['pay'] ?? ''));

$sql = "
    SELECT cr.*, tt.date, tt.start_time, tt.end_time, tt.class_id, tt.teacher_id AS lesson_teacher_id,
           s.name AS subject_name, c.name AS class_name, t.name AS teacher_name,
           (SELECT a.bunny_video_id FROM class_recording_assets a WHERE a.recording_id = cr.id ORDER BY a.id LIMIT 1) AS bunny_video_id,
           (SELECT a.bunny_library_id FROM class_recording_assets a WHERE a.recording_id = cr.id ORDER BY a.id LIMIT 1) AS bunny_library_id
    FROM class_recordings cr
    JOIN timetable tt ON tt.id = cr.timetable_id
    JOIN subjects s ON s.id = tt.subject_id
    JOIN student_classes c ON c.id = tt.class_id
    JOIN teachers t ON t.id = tt.teacher_id
    WHERE cr.deleted_at IS NULL AND cr.status <> 'deleted'
      AND tt.date BETWEEN ? AND ?
";
$params = [$from, $to];
if ($teacherFilter > 0) {
    $sql .= ' AND tt.teacher_id = ?';
    $params[] = $teacherFilter;
}
if ($classFilter > 0) {
    $sql .= ' AND tt.class_id = ?';
    $params[] = $classFilter;
}
if ($subjectFilter > 0) {
    $sql .= ' AND tt.subject_id = ?';
    $params[] = $subjectFilter;
}
if (in_array($statusFilter, ['ready', 'processing', 'uploading', 'failed', 'draft'], true)) {
    $sql .= ' AND cr.status = ?';
    $params[] = $statusFilter;
}
$sql .= ' ORDER BY tt.date DESC, cr.id DESC LIMIT 300';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$stats = ['total' => 0, 'ready' => 0, 'processing' => 0, 'failed' => 0, 'paid' => 0, 'pending' => 0, 'partial' => 0, 'waived' => 0];
foreach ($rows as &$row) {
    $stats['total']++;
    $st = (string)$row['status'];
    if (isset($stats[$st])) {
        $stats[$st]++;
    } elseif ($st === 'uploading') {
        $stats['processing']++;
    }
    $enrolled = campus_class_enrolled_count($pdo, (int)$row['class_id']);
    $paidStmt = $pdo->prepare("SELECT status, COUNT(*) c FROM student_lesson_fees WHERE timetable_id = ? GROUP BY status");
    $paidStmt->execute([(int)$row['timetable_id']]);
    $paid = 0;
    $unpaid = $enrolled;
    foreach ($paidStmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $feeRow) {
        if (in_array($feeRow['status'], ['paid', 'waived'], true)) {
            $paid += (int)$feeRow['c'];
        }
        if (isset($stats[$feeRow['status']])) {
            $stats[$feeRow['status']] += (int)$feeRow['c'];
        }
    }
    $row['enrolled_count'] = $enrolled;
    $row['paid_count'] = $paid;
    $row['unpaid_count'] = max(0, $enrolled - $paid);
    if ($payFilter === 'paid' && $row['unpaid_count'] > 0) {
        $row['_hide'] = true;
    }
    if ($payFilter === 'unpaid' && $row['unpaid_count'] < 1) {
        $row['_hide'] = true;
    }
}
unset($row);

$teachers = $pdo->query("SELECT id, name FROM teachers WHERE deleted_at IS NULL ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$classes = $pdo->query("SELECT id, name FROM student_classes WHERE deleted_at IS NULL ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$subjects = $pdo->query("SELECT id, name FROM subjects WHERE deleted_at IS NULL ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$inspectId = (int)($_GET['id'] ?? 0);
$inspect = $inspectId > 0 ? $recordings->find($inspectId) : null;
$inspectFees = [];
if ($inspect) {
    $inspectFees = $pdo->prepare("
        SELECT f.*, u.username, p.full_name
        FROM student_lesson_fees f
        JOIN users u ON u.id = f.student_id
        LEFT JOIN student_profiles p ON p.user_id = f.student_id
        WHERE f.timetable_id = ?
        ORDER BY f.status, f.id
    ");
    $inspectFees->execute([(int)$inspect['timetable_id']]);
    $inspectFees = $inspectFees->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

include __DIR__ . '/../includes/header.php';
?>
<h1 class="h3 mb-3"><i class="bi bi-camera-reels me-2"></i>Class recordings</h1>
<?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="row g-3 mb-3">
    <?php foreach (['total' => 'Total', 'ready' => 'Ready', 'processing' => 'Processing', 'failed' => 'Failed'] as $k => $label): ?>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm p-3">
                <div class="text-muted small"><?= htmlspecialchars($label) ?></div>
                <div class="fs-4 fw-bold"><?= (int)$stats[$k] ?></div>
            </div>
        </div>
    <?php endforeach; ?>
</div>
<div class="row g-3 mb-4">
    <?php foreach (['paid' => 'Paid fees', 'pending' => 'Pending fees', 'partial' => 'Partial', 'waived' => 'Waived'] as $k => $label): ?>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm p-3">
                <div class="text-muted small"><?= htmlspecialchars($label) ?></div>
                <div class="fs-5 fw-bold"><?= (int)$stats[$k] ?></div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<form class="row g-2 mb-3" method="get">
    <div class="col-md-2"><input type="date" class="form-control" name="from" value="<?= htmlspecialchars($from) ?>"></div>
    <div class="col-md-2"><input type="date" class="form-control" name="to" value="<?= htmlspecialchars($to) ?>"></div>
    <div class="col-md-2">
        <select class="form-select" name="teacher_id">
            <option value="0">All teachers</option>
            <?php foreach ($teachers as $t): ?>
                <option value="<?= (int)$t['id'] ?>" <?= $teacherFilter === (int)$t['id'] ? 'selected' : '' ?>><?= htmlspecialchars((string)$t['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-2">
        <select class="form-select" name="class_id">
            <option value="0">All classes</option>
            <?php foreach ($classes as $c): ?>
                <option value="<?= (int)$c['id'] ?>" <?= $classFilter === (int)$c['id'] ? 'selected' : '' ?>><?= htmlspecialchars((string)$c['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-2">
        <select class="form-select" name="subject_id">
            <option value="0">All subjects</option>
            <?php foreach ($subjects as $s): ?>
                <option value="<?= (int)$s['id'] ?>" <?= $subjectFilter === (int)$s['id'] ? 'selected' : '' ?>><?= htmlspecialchars((string)$s['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-1">
        <select class="form-select" name="status">
            <option value="">Status</option>
            <?php foreach (['ready','processing','uploading','failed','draft'] as $st): ?>
                <option <?= $statusFilter === $st ? 'selected' : '' ?>><?= $st ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-1"><button class="btn btn-primary w-100" type="submit">Filter</button></div>
</form>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>Date</th><th>Lesson</th><th>Teacher</th><th>Status</th><th>Bunny ID</th><th>Access</th><th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $row): if (!empty($row['_hide'])) continue; ?>
                <tr>
                    <td><?= htmlspecialchars(date('d M Y', strtotime((string)$row['date']))) ?></td>
                    <td>
                        <?= htmlspecialchars((string)$row['subject_name']) ?>
                        <div class="small text-muted"><?= htmlspecialchars((string)$row['class_name']) ?></div>
                    </td>
                    <td><?= htmlspecialchars((string)$row['teacher_name']) ?></td>
                    <td><span class="badge text-bg-<?= $row['status'] === 'ready' ? 'success' : ($row['status'] === 'failed' ? 'danger' : 'warning') ?>"><?= htmlspecialchars((string)$row['status']) ?></span></td>
                    <td class="small">
                        <code><?= htmlspecialchars((string)($row['bunny_video_id'] ?? '')) ?></code>
                        <?php if (!empty($row['bunny_library_id'])): ?>
                            <div class="text-muted">lib <?= htmlspecialchars((string)$row['bunny_library_id']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="small">Enrolled <?= (int)$row['enrolled_count'] ?> · Paid <?= (int)$row['paid_count'] ?> · Unpaid <?= (int)$row['unpaid_count'] ?></td>
                    <td class="text-nowrap">
                        <a class="btn btn-sm btn-success" href="<?= htmlspecialchars(BASE_URL . 'campus/watch_recording.php?id=' . (int)$row['id']) ?>">Watch</a>
                        <a class="btn btn-sm btn-outline-primary" href="?id=<?= (int)$row['id'] ?>&amp;from=<?= urlencode($from) ?>&amp;to=<?= urlencode($to) ?>">Inspect</a>
                        <form method="post" class="d-inline">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="sync">
                            <input type="hidden" name="recording_id" value="<?= (int)$row['id'] ?>">
                            <button class="btn btn-sm btn-outline-secondary" type="submit">Refresh</button>
                        </form>
                        <form method="post" class="d-inline" onsubmit="return confirm('Archive this recording?');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="archive">
                            <input type="hidden" name="recording_id" value="<?= (int)$row['id'] ?>">
                            <button class="btn btn-sm btn-outline-danger" type="submit">Archive</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($inspect): ?>
<div class="card border-0 shadow-sm mt-4">
    <div class="card-body">
        <h2 class="h5">Payment / access — <?= htmlspecialchars((string)$inspect['title']) ?></h2>
        <p class="text-muted">
            <?= htmlspecialchars((string)$inspect['subject_name']) ?> · <?= htmlspecialchars(date('d M Y', strtotime((string)$inspect['date']))) ?>
            · <a href="<?= htmlspecialchars(BASE_URL . 'campus/lesson_fees.php?date=' . urlencode((string)$inspect['date']) . '&lesson=' . (int)$inspect['timetable_id']) ?>">Open class fees</a>
        </p>
        <div class="table-responsive">
            <table class="table">
                <thead><tr><th>Student</th><th>Due</th><th>Paid</th><th>Status</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($inspectFees as $fee): ?>
                    <tr>
                        <td><?= htmlspecialchars((string)($fee['full_name'] ?: $fee['username'])) ?></td>
                        <td>Rs <?= number_format((float)$fee['amount_due'], 2) ?></td>
                        <td>Rs <?= number_format((float)$fee['amount_paid'], 2) ?></td>
                        <td><?= htmlspecialchars((string)$fee['status']) ?></td>
                        <td>
                            <?php if (!in_array($fee['status'], ['paid', 'waived'], true)): ?>
                                <form method="post" class="d-inline">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="cash">
                                    <input type="hidden" name="student_id" value="<?= (int)$fee['student_id'] ?>">
                                    <input type="hidden" name="timetable_id" value="<?= (int)$fee['timetable_id'] ?>">
                                    <button class="btn btn-sm btn-success" type="submit">Mark paid</button>
                                </form>
                                <form method="post" class="d-inline">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="waive">
                                    <input type="hidden" name="student_id" value="<?= (int)$fee['student_id'] ?>">
                                    <input type="hidden" name="timetable_id" value="<?= (int)$fee['timetable_id'] ?>">
                                    <button class="btn btn-sm btn-outline-secondary" type="submit">Waive</button>
                                </form>
                            <?php elseif ((float)$fee['amount_due'] > 0): ?>
                                <form method="post" class="d-inline">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="unpay">
                                    <input type="hidden" name="student_id" value="<?= (int)$fee['student_id'] ?>">
                                    <input type="hidden" name="timetable_id" value="<?= (int)$fee['timetable_id'] ?>">
                                    <button class="btn btn-sm btn-outline-warning" type="submit">Refund OnePay / mark unpaid</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($inspectFees === []): ?>
                    <tr><td colspan="5" class="text-muted">No lesson-fee rows yet. They are created when a student opens the recording.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
