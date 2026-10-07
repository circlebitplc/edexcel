<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/config.php';
require_admin();
ensure_campus_schema($pdo);

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
            throw new RuntimeException('Security token expired.');
        }
        $action = (string)($_POST['action'] ?? '');
        if ($action === 'year') {
            $name = trim((string)($_POST['name'] ?? ''));
            if ($name === '') {
                throw new RuntimeException('Year name is required.');
            }
            $current = isset($_POST['is_current']) ? 1 : 0;
            if ($current) {
                $pdo->exec("UPDATE academic_years SET is_current = 0");
            }
            $pdo->prepare("INSERT INTO academic_years (name, starts_on, ends_on, is_current) VALUES (?,?,?,?)")
                ->execute([$name, $_POST['starts_on'] ?: null, $_POST['ends_on'] ?: null, $current]);
            $success = 'Academic year saved.';
        } elseif ($action === 'qual') {
            $name = trim((string)($_POST['name'] ?? ''));
            if ($name === '') {
                throw new RuntimeException('Qualification name is required.');
            }
            $pdo->prepare("INSERT INTO qualifications (name, code) VALUES (?,?)")->execute([$name, trim((string)($_POST['code'] ?? '')) ?: null]);
            $success = 'Qualification saved.';
        } elseif ($action === 'unit') {
            $subjectId = (int)($_POST['subject_id'] ?? 0);
            $name = trim((string)($_POST['name'] ?? ''));
            if ($subjectId < 1 || $name === '') {
                throw new RuntimeException('Subject and unit name are required.');
            }
            $pdo->prepare("INSERT INTO subject_units (subject_id, code, name) VALUES (?,?,?)")
                ->execute([$subjectId, trim((string)($_POST['code'] ?? '')) ?: null, $name]);
            $success = 'Unit / paper saved.';
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$years = $pdo->query("SELECT * FROM academic_years ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC) ?: [];
$quals = $pdo->query("SELECT * FROM qualifications ORDER BY name")->fetchAll(PDO::FETCH_ASSOC) ?: [];
$subjects = $pdo->query("SELECT id, name FROM subjects WHERE deleted_at IS NULL ORDER BY name")->fetchAll(PDO::FETCH_ASSOC) ?: [];
$units = $pdo->query("SELECT u.*, s.name AS subject_name FROM subject_units u LEFT JOIN subjects s ON s.id = u.subject_id ORDER BY s.name, u.code")->fetchAll(PDO::FETCH_ASSOC) ?: [];

include __DIR__ . '/../includes/header.php';
?>
<div class="container-fluid py-4">
    <h1 class="h3 mb-1">Academic catalogue</h1>
    <p class="text-muted">Years, qualifications (IGCSE / IAL), and subject units / papers. Classes still use levels as before.</p>
    <?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-4"><div class="card-body">
                <h5>Academic year</h5>
                <form method="post" class="mb-3"><?= csrf_field() ?><input type="hidden" name="action" value="year">
                    <input class="form-control mb-2" name="name" placeholder="2026/27" required>
                    <input class="form-control mb-2" type="date" name="starts_on">
                    <input class="form-control mb-2" type="date" name="ends_on">
                    <div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="is_current" id="cur"><label class="form-check-label" for="cur">Current year</label></div>
                    <button class="btn btn-primary btn-sm">Save year</button>
                </form>
                <ul class="list-unstyled mb-0"><?php foreach ($years as $y): ?><li><?= e((string)$y['name']) ?> <?= (int)$y['is_current'] ? '(current)' : '' ?></li><?php endforeach; ?></ul>
            </div></div>
        </div>
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-4"><div class="card-body">
                <h5>Qualifications</h5>
                <form method="post" class="mb-3"><?= csrf_field() ?><input type="hidden" name="action" value="qual">
                    <input class="form-control mb-2" name="name" placeholder="IGCSE" required>
                    <input class="form-control mb-2" name="code" placeholder="Optional code">
                    <button class="btn btn-primary btn-sm">Save</button>
                </form>
                <ul class="list-unstyled mb-0"><?php foreach ($quals as $q): ?><li><?= e((string)$q['name']) ?></li><?php endforeach; ?></ul>
            </div></div>
        </div>
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-4"><div class="card-body">
                <h5>Units / papers</h5>
                <form method="post" class="mb-3"><?= csrf_field() ?><input type="hidden" name="action" value="unit">
                    <select class="form-select mb-2" name="subject_id" required>
                        <option value="">Subject</option>
                        <?php foreach ($subjects as $s): ?><option value="<?= (int)$s['id'] ?>"><?= e((string)$s['name']) ?></option><?php endforeach; ?>
                    </select>
                    <input class="form-control mb-2" name="code" placeholder="4IT1">
                    <input class="form-control mb-2" name="name" placeholder="Paper 1" required>
                    <button class="btn btn-primary btn-sm">Save unit</button>
                </form>
                <ul class="list-unstyled small mb-0"><?php foreach (array_slice($units, 0, 20) as $u): ?><li><?= e((string)$u['subject_name']) ?> · <?= e((string)($u['code'] ?: $u['name'])) ?></li><?php endforeach; ?></ul>
            </div></div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
