<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../vendor/autoload.php';
use Edexcel\Services\AdmissionLifecycleService;
require_student();
$life = new AdmissionLifecycleService($pdo);
$life->refreshOnboarding((int)$_SESSION['user_id']);
$on = $life->onboarding((int)$_SESSION['user_id']);
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $life->acceptAgreement(0, (string)($_POST['document_key'] ?? 'college_policies'), '2026-09', (string)($_SESSION['username'] ?? 'student'), (int)$_SESSION['user_id']);
}
include __DIR__ . '/../includes/header.php';
?>
<div class="container py-4">
    <h1 class="h3">Welcome to Edexcel College</h1>
    <p class="text-muted">Your account, classes, and learning tools are connected from this checklist.</p>
    <ul class="list-group mb-4"><?php foreach (($on['checklist'] ?? []) as $item): ?>
        <li class="list-group-item d-flex justify-content-between"><span><?= e($item['label']) ?></span><span><?= !empty($item['done']) ? '✓' : '□' ?></span></li>
    <?php endforeach; ?></ul>
    <div class="d-flex flex-wrap gap-2 mb-4">
        <a class="btn btn-outline-primary" href="<?= e(BASE_URL) ?>student/dashboard.php?tab=timetable">Timetable</a>
        <a class="btn btn-outline-primary" href="<?= e(BASE_URL) ?>student/dashboard.php?tab=fees">Payments</a>
        <a class="btn btn-outline-primary" href="<?= e(BASE_URL) ?>student/dashboard.php?tab=courso">Learning portal</a>
        <a class="btn btn-outline-primary" href="<?= e(BASE_URL) ?>student/exam_prep.php">Exams</a>
        <a class="btn btn-outline-primary" href="<?= e(BASE_URL) ?>student/requests.php">Support</a>
    </div>
    <form method="post" class="card border-0 shadow-sm p-3"><?= csrf_field() ?>
        <p class="small">Acceptance is stored with version and date. This is an operational acknowledgement, not a substitute for legal review.</p>
        <input type="hidden" name="document_key" value="college_policies">
        <label class="form-check"><input class="form-check-input" type="checkbox" required name="ok"> I acknowledge the college policies, refund policy, and programme rules.</label>
        <button class="btn btn-primary mt-2">Accept</button>
    </form>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
