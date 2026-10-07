<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../includes/bank_name_field.php';
require_admin();

use Edexcel\Services\TeacherBankAccountService;

\Edexcel\Services\ClassSessionFeeCalculator::ensureSchema($pdo);
$userId = (int)($_SESSION['user_id'] ?? 0);
$teacherId = (int)($_GET['teacher'] ?? $_POST['teacher_id'] ?? 0);
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token.';
    } else {
        $teacherId = (int)($_POST['teacher_id'] ?? 0);
        $check = $pdo->prepare('SELECT id FROM teachers WHERE id = ? AND deleted_at IS NULL LIMIT 1');
        $check->execute([$teacherId]);
        if (!$check->fetchColumn()) {
            $error = 'Teacher not found.';
        } else {
            try {
                TeacherBankAccountService::save($pdo, $teacherId, $_POST, $userId);
                $success = 'Bank details saved for this teacher.';
            } catch (Throwable $e) {
                $error = $e->getMessage();
            }
        }
    }
}

$teachers = $pdo->query(
    'SELECT t.id, t.name, b.bank_name, b.account_holder_name, b.account_number, b.branch, b.branch_code, b.account_type
     FROM teachers t
     LEFT JOIN teacher_bank_accounts b ON b.teacher_id = t.id
     WHERE t.deleted_at IS NULL
     ORDER BY t.name'
)->fetchAll(PDO::FETCH_ASSOC);

$selected = null;
if ($teacherId > 0) {
    foreach ($teachers as $row) {
        if ((int)$row['id'] === $teacherId) {
            $selected = $row;
            break;
        }
    }
}

include __DIR__ . '/../includes/header.php';
?>
<div class="container-fluid py-4">
    <h1 class="h3">Teacher bank details</h1>
    <p class="text-muted">Account numbers are masked in this list. Open a teacher to view the account used for payouts. Students never see this page.</p>
    <?php if ($error !== ''): ?><div class="alert alert-danger"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
    <?php if ($success !== ''): ?><div class="alert alert-success"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>

    <div class="table-responsive mb-4">
        <table class="table table-sm align-middle">
            <thead>
                <tr><th>Teacher</th><th>Bank details</th><th>Account</th><th></th></tr>
            </thead>
            <tbody>
            <?php foreach ($teachers as $row): ?>
                <?php $ready = TeacherBankAccountService::isCompleteRow($row['account_number'] ? $row : null); ?>
                <tr>
                    <td><?= htmlspecialchars((string)$row['name'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= $ready ? 'Completed' : 'Required' ?></td>
                    <td class="font-monospace"><?= htmlspecialchars(TeacherBankAccountService::mask((string)($row['account_number'] ?? '')), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><a href="?teacher=<?= (int)$row['id'] ?>">Manage</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if ($selected): ?>
        <form method="post" class="card" style="max-width: 760px;">
            <div class="card-body">
                <h2 class="h5"><?= htmlspecialchars((string)$selected['name'], ENT_QUOTES, 'UTF-8') ?></h2>
                <?= csrf_field() ?>
                <input type="hidden" name="teacher_id" value="<?= (int)$selected['id'] ?>">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Bank name</label>
                        <?php bank_name_field((string)($selected['bank_name'] ?? '')); ?>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Account holder name</label>
                        <input class="form-control" name="account_holder_name" required maxlength="160" value="<?= htmlspecialchars((string)($selected['account_holder_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Account number</label>
                        <input class="form-control" name="account_number" inputmode="numeric" autocomplete="off" value="<?= htmlspecialchars((string)($selected['account_number'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                        <div class="form-text">Shown to admins on this form only. The list above stays masked.</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Account type</label>
                        <?php $type = (string)($selected['account_type'] ?? 'savings'); ?>
                        <select class="form-select" name="account_type">
                            <option value="savings" <?= $type === 'savings' ? 'selected' : '' ?>>Savings</option>
                            <option value="current" <?= $type === 'current' ? 'selected' : '' ?>>Current</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Branch</label>
                        <input class="form-control" name="branch" required maxlength="120" value="<?= htmlspecialchars((string)($selected['branch'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Branch code <span class="text-muted">(optional)</span></label>
                        <input class="form-control" name="branch_code" maxlength="20" value="<?= htmlspecialchars((string)($selected['branch_code'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                        <div class="form-text">Leave this blank if you do not have a branch code.</div>
                    </div>
                </div>
                <button class="btn btn-primary mt-3" type="submit">Save</button>
            </div>
        </form>
    <?php endif; ?>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
