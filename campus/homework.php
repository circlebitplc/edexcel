<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Edexcel\Services\HomeworkSubmissionService;

require_staff();
ensure_campus_schema($pdo);
ensure_ops_schema($pdo);

$isAdmin = is_admin();
$teacherId = (int)($_SESSION['teacher_id'] ?? 0);
$userId = (int)($_SESSION['user_id'] ?? 0);
$error = '';
$success = '';
$view = strtolower(trim((string)($_GET['view'] ?? 'upload')));
if (!in_array($view, ['upload', 'submissions'], true)) {
    $view = 'upload';
}
$uploadDir = dirname(__DIR__) . '/files/materials';
if (!is_dir($uploadDir)) {
    @mkdir($uploadDir, 0755, true);
}

$classes = campus_staff_classes($pdo, $isAdmin, $teacherId);
$hwSvc = new HomeworkSubmissionService($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
            throw new RuntimeException('Security token expired.');
        }
        $action = (string)($_POST['action'] ?? 'upload');

        if ($action === 'review_homework') {
            $scoreRaw = trim((string)($_POST['score'] ?? ''));
            $maxRaw = trim((string)($_POST['max_score'] ?? ''));
            $hwSvc->review(
                (int)($_POST['submission_id'] ?? 0),
                $userId,
                $teacherId,
                $isAdmin,
                (string)($_POST['review_status'] ?? 'done'),
                $scoreRaw !== '' ? (float)$scoreRaw : null,
                $maxRaw !== '' ? (float)$maxRaw : null,
                trim((string)($_POST['feedback'] ?? ''))
            );
            $success = 'Submission marked. Student notified on WhatsApp and in the portal.';
            $view = 'submissions';
        } else {
            $classId = (int)($_POST['class_id'] ?? 0);
            $title = trim((string)($_POST['title'] ?? ''));
            $description = trim((string)($_POST['description'] ?? ''));
            $due = trim((string)($_POST['due_date'] ?? ''));
            $pearsonUnit = trim((string)($_POST['pearson_unit'] ?? ''));
            if ($classId < 1 || $title === '') {
                throw new RuntimeException('Class and title are required.');
            }
            if (!campus_staff_can_access_class($pdo, $classId, $isAdmin, $teacherId)) {
                throw new RuntimeException('You can only upload papers for your classes.');
            }

            $fileUrl = '';
            $fileName = '';
            if (!empty($_FILES['paper']['name']) && (int)$_FILES['paper']['error'] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo((string)$_FILES['paper']['name'], PATHINFO_EXTENSION));
                if (!in_array($ext, ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'], true)) {
                    throw new RuntimeException('Upload a PDF, Word, or image file.');
                }
                if ((int)$_FILES['paper']['size'] > 12 * 1024 * 1024) {
                    throw new RuntimeException('File is larger than 12 MB.');
                }
                $fileName = 'paper_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                if (!move_uploaded_file($_FILES['paper']['tmp_name'], $uploadDir . '/' . $fileName)) {
                    throw new RuntimeException('Could not save the file.');
                }
                $fileUrl = rtrim((string)BASE_URL, '/') . '/files/materials/' . $fileName;
            }

            $subjectId = $pdo->prepare("
                SELECT subject_id FROM timetable
                WHERE class_id = ? AND deleted_at IS NULL
                ORDER BY date DESC LIMIT 1
            ");
            $subjectId->execute([$classId]);
            $sid = (int)$subjectId->fetchColumn();

            $pdo->prepare("
                INSERT INTO student_materials (class_id, subject_id, teacher_id, title, description, file_url, file_name)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ")->execute([
                $classId,
                $sid ?: null,
                $teacherId ?: null,
                $title,
                $description !== '' ? $description : null,
                $fileUrl !== '' ? $fileUrl : null,
                $fileName !== '' ? $fileName : null,
            ]);
            if ($pearsonUnit !== '' && campus_column_exists($pdo, 'student_materials', 'pearson_unit')) {
                $pdo->prepare('UPDATE student_materials SET pearson_unit = ? WHERE id = LAST_INSERT_ID()')
                    ->execute([mb_substr($pearsonUnit, 0, 80)]);
            }

            $pdo->prepare("
                INSERT INTO student_homework (class_id, subject_id, teacher_id, title, description, due_date, link)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ")->execute([
                $classId,
                $sid ?: null,
                $teacherId ?: null,
                $title,
                $description !== '' ? $description : null,
                $due !== '' ? $due : null,
                $fileUrl !== '' ? $fileUrl : null,
            ]);
            if ($pearsonUnit !== '' && campus_column_exists($pdo, 'student_homework', 'pearson_unit')) {
                $pdo->prepare('UPDATE student_homework SET pearson_unit = ? WHERE id = LAST_INSERT_ID()')
                    ->execute([mb_substr($pearsonUnit, 0, 80)]);
            }

            $className = '';
            foreach ($classes as $c) {
                if ((int)$c['id'] === $classId) {
                    $className = (string)$c['name'];
                    break;
                }
            }
            $dueText = $due !== '' ? "\nDue: " . date('d M Y', strtotime($due)) : '';
            $msg = "📄 *New paper / homework*\n\n*{$title}*\nClass: {$className}{$dueText}\n\nOpen Student Portal → Homework & notes to download or submit.";
            campus_notify_students($pdo, campus_class_student_ids($pdo, $classId), $msg, 'HOMEWORK');
            try {
                $homeworkId = (int)$pdo->lastInsertId();
                (new \Edexcel\Services\CommunicationEventService($pdo))->homeworkAssigned($classId, $title, [
                    'homework_id' => $homeworkId,
                    'actor_id' => (int)($_SESSION['user_id'] ?? 0),
                    'class_name' => $className,
                    'due' => $due,
                ]);
            } catch (Throwable $e) {
                error_log('homework communication hub: ' . $e->getMessage());
            }
            $success = 'Uploaded. Students notified on WhatsApp and in the portal.';
            $view = 'upload';
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

if ($isAdmin) {
    $list = $pdo->query("
        SELECT m.*, c.name AS class_name
        FROM student_materials m
        LEFT JOIN student_classes c ON c.id = m.class_id
        ORDER BY m.created_at DESC
        LIMIT 30
    ")->fetchAll(PDO::FETCH_ASSOC);
} else {
    $classIds = array_map(static fn($c) => (int)$c['id'], $classes);
    $list = [];
    if ($classIds !== []) {
        $in = implode(',', array_fill(0, count($classIds), '?'));
        $stmt = $pdo->prepare("
            SELECT m.*, c.name AS class_name
            FROM student_materials m
            LEFT JOIN student_classes c ON c.id = m.class_id
            WHERE m.class_id IN ($in)
            ORDER BY m.created_at DESC
            LIMIT 30
        ");
        $stmt->execute($classIds);
        $list = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

$submissions = $hwSvc->pendingForTeacher($teacherId, $isAdmin, 50);

include __DIR__ . '/../includes/header.php';
?>
<div class="container-fluid py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1"><i class="bi bi-file-earmark-pdf text-danger"></i> Homework & papers</h1>
            <p class="text-muted mb-0">Upload papers, collect student submissions, and mark them.</p>
        </div>
        <div class="btn-group">
            <a class="btn btn-<?= $view === 'upload' ? 'primary' : 'outline-primary' ?>" href="?view=upload">Upload</a>
            <a class="btn btn-<?= $view === 'submissions' ? 'primary' : 'outline-primary' ?>" href="?view=submissions">
                Submissions<?php if ($submissions): ?> <span class="badge text-bg-light text-dark"><?= count($submissions) ?></span><?php endif; ?>
            </a>
        </div>
    </div>
    <?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>

    <?php if ($view === 'submissions'): ?>
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-body p-4">
                <?php if (!$submissions): ?>
                    <p class="text-muted mb-0">No pending submissions.</p>
                <?php else: ?>
                    <?php foreach ($submissions as $sub): ?>
                        <div class="border rounded-3 p-3 mb-3">
                            <div class="d-flex justify-content-between flex-wrap gap-2">
                                <div>
                                    <div class="fw-semibold"><?= e((string)$sub['student_name']) ?></div>
                                    <div class="small text-muted">
                                        <?= e((string)$sub['title']) ?>
                                        <?php if (!empty($sub['class_name'])): ?> · <?= e((string)$sub['class_name']) ?><?php endif; ?>
                                        · <?= e(date('d M H:i', strtotime((string)$sub['submitted_at']))) ?>
                                    </div>
                                </div>
                                <?php if (!empty($sub['file_url'])): ?>
                                    <a href="<?= e((string)$sub['file_url']) ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary">Open file</a>
                                <?php endif; ?>
                            </div>
                            <?php if (!empty($sub['note'])): ?>
                                <p class="small mt-2 mb-2"><?= e((string)$sub['note']) ?></p>
                            <?php endif; ?>
                            <form method="post" class="row g-2 align-items-end">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="review_homework">
                                <input type="hidden" name="submission_id" value="<?= (int)$sub['id'] ?>">
                                <div class="col-md-2">
                                    <label class="form-label small mb-0">Status</label>
                                    <select name="review_status" class="form-select form-select-sm">
                                        <option value="done">Done</option>
                                        <option value="returned">Return</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label small mb-0">Score</label>
                                    <input class="form-control form-control-sm" name="score" placeholder="e.g. 18">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label small mb-0">Max</label>
                                    <input class="form-control form-control-sm" name="max_score" value="100">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small mb-0">Feedback</label>
                                    <input class="form-control form-control-sm" name="feedback" placeholder="Short note to student">
                                </div>
                                <div class="col-md-2">
                                    <button class="btn btn-sm btn-primary w-100">Save & notify</button>
                                </div>
                            </form>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    <?php else: ?>
        <div class="row g-4">
            <div class="col-lg-5">
                <div class="card border-0 shadow-sm rounded-4">
                    <div class="card-body p-4">
                        <form method="post" enctype="multipart/form-data">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="upload">
                            <label class="form-label">Class</label>
                            <select class="form-select mb-3" name="class_id" required>
                                <option value="">Select class</option>
                                <?php foreach ($classes as $c): ?>
                                    <option value="<?= (int)$c['id'] ?>"><?= e($c['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <label class="form-label">Title</label>
                            <input class="form-control mb-3" name="title" required placeholder="June 2026 Physics paper">
                            <label class="form-label">Note</label>
                            <textarea class="form-control mb-3" name="description" rows="3"></textarea>
                            <label class="form-label">Pearson unit (optional)</label>
                            <input class="form-control mb-3" name="pearson_unit" maxlength="80" placeholder="WMA11 / Unit 1 Pure">
                            <label class="form-label">Due date (optional)</label>
                            <input class="form-control mb-3" type="date" name="due_date">
                            <label class="form-label">File</label>
                            <input class="form-control mb-3" type="file" name="paper" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                            <button class="btn btn-primary rounded-pill">Upload & notify</button>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-lg-7">
                <div class="card border-0 shadow-sm rounded-4">
                    <div class="card-body">
                        <h5>Recent uploads</h5>
                        <div class="list-group list-group-flush">
                            <?php foreach ($list as $row): ?>
                                <div class="list-group-item">
                                    <strong><?= e($row['title']) ?></strong>
                                    <div class="small text-muted"><?= e($row['class_name'] ?: 'All') ?> · <?= e($row['created_at']) ?></div>
                                    <?php if (!empty($row['file_url'])): ?>
                                        <a href="<?= e($row['file_url']) ?>" target="_blank">Download</a>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                            <?php if (!$list): ?><p class="text-muted mb-0">No papers yet.</p><?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
