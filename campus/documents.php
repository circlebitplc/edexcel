<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/config.php';
require_staff();
ensure_campus_schema($pdo);

$isAdmin = is_admin();
$teacherId = (int)($_SESSION['teacher_id'] ?? 0);
$userId = (int)($_SESSION['user_id'] ?? 0);
$error = '';
$success = '';

$students = campus_staff_students($pdo, $isAdmin, $teacherId);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
            throw new RuntimeException('Security token expired.');
        }
        if (isset($_POST['delete_doc'])) {
            $id = (int)($_POST['id'] ?? 0);
            $row = $pdo->prepare("SELECT student_id, file_url FROM student_documents WHERE id = ?");
            $row->execute([$id]);
            $doc = $row->fetch(PDO::FETCH_ASSOC);
            if (!$doc) {
                throw new RuntimeException('Document not found.');
            }
            if (!campus_staff_can_access_student($pdo, (int)$doc['student_id'], $isAdmin, $teacherId)) {
                throw new RuntimeException('You can only manage documents for your students.');
            }
            $pdo->prepare("DELETE FROM student_documents WHERE id = ?")->execute([$id]);
            $stored = basename((string)($doc['file_url'] ?? ''));
            if ($stored !== '' && !str_contains($stored, '..')) {
                $path = dirname(__DIR__) . '/files/documents/' . $stored;
                if (is_file($path)) {
                    @unlink($path);
                }
            }
            $success = 'Document removed.';
        } else {
            $studentId = (int)($_POST['student_id'] ?? 0);
            $title = trim((string)($_POST['title'] ?? ''));
            if ($studentId < 1 || $title === '') {
                throw new RuntimeException('Choose a student and title.');
            }
            if (!campus_staff_can_access_student($pdo, $studentId, $isAdmin, $teacherId)) {
                throw new RuntimeException('You can only upload documents for your students.');
            }
            if (empty($_FILES['file']['tmp_name']) || !is_uploaded_file($_FILES['file']['tmp_name'])) {
                throw new RuntimeException('Choose a file.');
            }
            $orig = (string)($_FILES['file']['name'] ?? '');
            $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
            if (!in_array($ext, ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'], true)) {
                throw new RuntimeException('Allowed: pdf, jpg, png, doc, docx.');
            }
            if ((int)$_FILES['file']['size'] > 12 * 1024 * 1024) {
                throw new RuntimeException('File is too large (12 MB).');
            }
            $dir = dirname(__DIR__) . '/files/documents';
            if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
                throw new RuntimeException('Could not create upload folder.');
            }
            $stored = bin2hex(random_bytes(8)) . '.' . $ext;
            if (!move_uploaded_file($_FILES['file']['tmp_name'], $dir . '/' . $stored)) {
                throw new RuntimeException('Upload failed.');
            }
            $url = rtrim((string)BASE_URL, '/') . '/files/documents/' . $stored;
            $pdo->prepare("INSERT INTO student_documents (student_id, title, file_name, file_url, uploaded_by) VALUES (?,?,?,?,?)")
                ->execute([$studentId, $title, $orig, $url, $userId]);
            $success = 'Document saved. The student can open it in Academic Hub / Documents.';
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

if ($isAdmin) {
    $list = $pdo->query("
        SELECT d.*, COALESCE(NULLIF(sp.full_name,''), u.username) AS student_name
        FROM student_documents d
        JOIN users u ON u.id = d.student_id
        LEFT JOIN student_profiles sp ON sp.user_id = u.id
        ORDER BY d.id DESC LIMIT 80
    ")->fetchAll(PDO::FETCH_ASSOC) ?: [];
} else {
    $ids = [];
    foreach (campus_staff_classes($pdo, false, $teacherId) as $c) {
        $cid = (int)($c['id'] ?? 0);
        if ($cid > 0) {
            $ids[] = $cid;
        }
    }
    $list = [];
    if ($ids !== []) {
        $in = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare("
            SELECT d.*, COALESCE(NULLIF(sp.full_name,''), u.username) AS student_name
            FROM student_documents d
            JOIN users u ON u.id = d.student_id
            LEFT JOIN student_profiles sp ON sp.user_id = u.id
            WHERE d.student_id IN (
                SELECT se.student_id FROM student_enrollments se WHERE se.class_id IN ($in)
            )
            ORDER BY d.id DESC LIMIT 80
        ");
        $stmt->execute($ids);
        $list = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}

include __DIR__ . '/../includes/header.php';
?>
<div class="container-fluid py-4">
    <h1 class="h3 mb-3">Student documents</h1>
    <p class="text-muted">ID copies and other files for a student (separate from class papers).</p>
    <?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
    <div class="row g-4">
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm rounded-4"><div class="card-body">
                <form method="post" enctype="multipart/form-data">
                    <?= csrf_field() ?>
                    <label class="form-label">Student</label>
                    <select class="form-select mb-2" name="student_id" required>
                        <option value="">Choose</option>
                        <?php foreach ($students as $s): ?><option value="<?= (int)$s['id'] ?>"><?= e((string)$s['name']) ?></option><?php endforeach; ?>
                    </select>
                    <label class="form-label">Title</label>
                    <input class="form-control mb-2" name="title" required placeholder="NIC copy">
                    <label class="form-label">File</label>
                    <input class="form-control mb-3" type="file" name="file" required>
                    <button class="btn btn-primary rounded-pill">Upload</button>
                </form>
            </div></div>
        </div>
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm rounded-4"><div class="card-body">
                <?php foreach ($list as $row): ?>
                    <div class="d-flex justify-content-between border-bottom py-2">
                        <div>
                            <strong><?= e((string)$row['title']) ?></strong>
                            <div class="small text-muted"><?= e((string)$row['student_name']) ?></div>
                        </div>
                        <div class="d-flex gap-1">
                            <a class="btn btn-sm btn-outline-primary" href="<?= e(rtrim((string)BASE_URL, '/') . '/download_document.php?id=' . (int)$row['id']) ?>" target="_blank">Open</a>
                            <form method="post" onsubmit="return confirm('Remove this file?')"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$row['id'] ?>"><button class="btn btn-sm btn-outline-danger" name="delete_doc" value="1">Delete</button></form>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php if (!$list): ?><p class="text-muted mb-0">No documents yet.</p><?php endif; ?>
            </div></div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
