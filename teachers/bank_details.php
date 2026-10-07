<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../includes/bank_name_field.php';
require_teacher();

use Edexcel\Services\TeacherBankAccountService;

\Edexcel\Services\ClassSessionFeeCalculator::ensureSchema($pdo);
$userId = (int)($_SESSION['user_id'] ?? 0);
$teacherId = (int)($_SESSION['teacher_id'] ?? 0);
if ($teacherId < 1) {
    http_response_code(403);
    exit('Your teacher account is not linked to a teacher profile.');
}

$error = '';
$success = '';
$account = TeacherBankAccountService::findForTeacher($pdo, $teacherId);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please refresh and try again.';
    } else {
        try {
            TeacherBankAccountService::save($pdo, $teacherId, $_POST, $userId);
            $account = TeacherBankAccountService::findForTeacher($pdo, $teacherId);
            $success = 'Bank details saved. You can create online classes.';
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}

$complete = TeacherBankAccountService::isCompleteRow($account);
$masked = TeacherBankAccountService::mask((string)($account['account_number'] ?? ''));
$pageTitle = 'Bank details';
include __DIR__ . '/../includes/header.php';
?>
<div class="container py-4" style="max-width: 760px;">
    <h1 class="h3 mb-2">Bank details</h1>
    <p class="text-muted">These details are used only to settle your online class earnings. Students cannot see them.</p>

    <?php if ($error !== ''): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>
    <?php if ($success !== ''): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <?php if ($complete): ?>
        <div class="alert alert-success">
            <strong>Bank details: completed.</strong> You can create online classes.
            <?php if ($masked !== ''): ?>
                Account <?= htmlspecialchars($masked, ENT_QUOTES, 'UTF-8') ?>.
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="alert alert-warning">
            <strong>Bank details: required.</strong>
            Online classes require a completed bank account profile so that your class earnings can be settled.
        </div>
    <?php endif; ?>

    <form method="post" class="card border-0 shadow-sm">
        <div class="card-body">
            <?= csrf_field() ?>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="bank_name_query">Bank name</label>
                    <?php bank_name_field((string)($account['bank_name'] ?? '')); ?>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="account_holder_name">Account holder name</label>
                    <input class="form-control" id="account_holder_name" name="account_holder_name" required maxlength="160" value="<?= htmlspecialchars((string)($account['account_holder_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="account_number">Account number</label>
                    <input class="form-control" id="account_number" name="account_number" inputmode="numeric" autocomplete="off" maxlength="24" placeholder="<?= $masked !== '' ? htmlspecialchars($masked, ENT_QUOTES, 'UTF-8') : 'Account number' ?>" <?= $account ? '' : 'required' ?>>
                    <div class="form-text"><?= $account ? 'Leave this blank to keep the saved account number.' : '6 to 20 digits. It is masked after you save.' ?></div>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="account_type">Account type</label>
                    <select class="form-select" id="account_type" name="account_type" required>
                        <?php $type = (string)($account['account_type'] ?? ''); ?>
                        <option value="savings" <?= $type === 'savings' ? 'selected' : '' ?>>Savings</option>
                        <option value="current" <?= $type === 'current' ? 'selected' : '' ?>>Current</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="branch">Branch</label>
                    <input class="form-control" id="branch" name="branch" required maxlength="120" value="<?= htmlspecialchars((string)($account['branch'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="branch_code">Branch code <span class="text-muted">(optional)</span></label>
                    <input class="form-control" id="branch_code" name="branch_code" maxlength="20" value="<?= htmlspecialchars((string)($account['branch_code'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                    <div class="form-text">Leave this blank if you do not have a branch code.</div>
                </div>
            </div>
            <button type="submit" class="btn btn-primary mt-3">Save bank details</button>
            <a class="btn btn-secondary mt-3" href="<?= htmlspecialchars(rtrim((string)BASE_URL, '/') . '/timetable/add.php', ENT_QUOTES, 'UTF-8') ?>">Back to add class</a>
        </div>
    </form>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
