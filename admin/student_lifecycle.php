<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../vendor/autoload.php';
use Edexcel\Services\AdmissionAuth;
use Edexcel\Services\AdmissionLifecycleService;
try { $auth = AdmissionAuth::require($pdo, 'admissions.edit'); } catch (Throwable $e) { http_response_code(403); echo 'Access denied.'; exit; }
$life = new AdmissionLifecycleService($pdo);
$error = '';
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf_token($_POST['csrf_token'] ?? '')) {
    try {
        $action = (string)($_POST['action'] ?? '');
        $studentId = (int)($_POST['student_id'] ?? 0);
        if ($action === 'transfer') {
            $life->transfer($studentId, (int)$_POST['from_class'], (int)$_POST['to_class'], $auth['user_id'], (string)($_POST['reason'] ?? ''), !empty($_POST['override']));
            $success = 'Transfer recorded. Historical enrollments were kept.';
        } elseif ($action === 'withdraw') {
            $life->withdraw($studentId, (int)$_POST['class_id'], $auth['user_id'], (string)($_POST['reason'] ?? 'Withdrawal'), (string)($_POST['effective'] ?? ''));
            $success = 'Withdrawal recorded. History was preserved.';
        } elseif ($action === 'complete') {
            $life->completeProgramme($studentId, (int)$_POST['class_id'], $auth['user_id'], (string)($_POST['notes'] ?? ''));
            $success = 'Programme completion recorded.';
        } elseif ($action === 'reenroll') {
            $life->reenroll($studentId, array_map('intval', (array)($_POST['class_ids'] ?? [])), $auth['user_id']);
            $success = 'Returning student re-enrolled on the existing identity.';
        }
    } catch (Throwable $e) { $error = $e->getMessage(); }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') { $error = 'Session expired.'; }
include __DIR__ . '/../includes/header.php';
?>
<div class="container py-4" style="max-width:860px">
    <a href="admissions_control.php">← Command centre</a>
    <h1 class="h3">Transfer, withdrawal, completion, re-enrollment</h1>
    <p class="text-muted">Uses the existing student identity. Historical classes, attendance, payments and results are never deleted.</p>
    <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
    <div class="row g-3">
        <div class="col-md-6"><form method="post" class="card border-0 shadow-sm p-3"><?= csrf_field() ?><input type="hidden" name="action" value="transfer">
            <h2 class="h6">Class / subject transfer</h2>
            <input class="form-control mb-2" name="student_id" placeholder="Student ID" required>
            <input class="form-control mb-2" name="from_class" placeholder="From class ID" required>
            <input class="form-control mb-2" name="to_class" placeholder="To class ID" required>
            <input class="form-control mb-2" name="reason" placeholder="Reason" required>
            <button class="btn btn-outline-primary">Transfer</button>
        </form></div>
        <div class="col-md-6"><form method="post" class="card border-0 shadow-sm p-3"><?= csrf_field() ?><input type="hidden" name="action" value="withdraw">
            <h2 class="h6">Withdrawal</h2>
            <input class="form-control mb-2" name="student_id" placeholder="Student ID" required>
            <input class="form-control mb-2" name="class_id" placeholder="Class ID" required>
            <input class="form-control mb-2" name="reason" placeholder="Reason" required>
            <input class="form-control mb-2" type="date" name="effective">
            <button class="btn btn-outline-danger">Withdraw</button>
        </form></div>
        <div class="col-md-6"><form method="post" class="card border-0 shadow-sm p-3"><?= csrf_field() ?><input type="hidden" name="action" value="complete">
            <h2 class="h6">Programme completion</h2>
            <input class="form-control mb-2" name="student_id" placeholder="Student ID" required>
            <input class="form-control mb-2" name="class_id" placeholder="Class ID" required>
            <input class="form-control mb-2" name="notes" placeholder="Certificate / academic note">
            <button class="btn btn-outline-success">Record completion</button>
        </form></div>
        <div class="col-md-6"><form method="post" class="card border-0 shadow-sm p-3"><?= csrf_field() ?><input type="hidden" name="action" value="reenroll">
            <h2 class="h6">Re-enroll returning student</h2>
            <input class="form-control mb-2" name="student_id" placeholder="Existing student ID" required>
            <input class="form-control mb-2" name="class_ids[]" placeholder="Class ID">
            <p class="small text-muted">Creates a new enrollment period on the same person. Duplicates are never auto-merged.</p>
            <button class="btn btn-primary">Re-enroll</button>
        </form></div>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
