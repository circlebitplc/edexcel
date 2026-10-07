<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../vendor/autoload.php';
use Edexcel\Services\AdmissionAssistantService;
use Edexcel\Services\AdmissionAuth;
use Edexcel\Services\AdmissionLifecycleService;
use Edexcel\Services\ClassAllocationService;
try { $auth = AdmissionAuth::require($pdo, 'admissions.review'); } catch (Throwable $e) { http_response_code(403); echo 'Access denied.'; exit; }
$life = new AdmissionLifecycleService($pdo);
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$error = '';
$success = '';
$ai = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf_token($_POST['csrf_token'] ?? '')) {
    try {
        $action = (string)($_POST['action'] ?? '');
        if ($action === 'document' && !empty($_FILES['file']['tmp_name'])) {
            $life->storeDocument($id, $_FILES['file'], (string)($_POST['document_type'] ?? 'other'), $auth['user_id']);
            $success = 'Document stored privately.';
        } elseif ($action === 'payment') {
            $life->recordVerifiedPayment($id, $_POST, $auth['user_id']);
            $success = 'Verified payment recorded against the existing payment process.';
        } elseif ($action === 'allocate') {
            $result = (new ClassAllocationService($pdo))->allocate($id, (int)$_POST['class_id'], $auth['user_id'], !empty($_POST['override']));
            $success = $result === 'waitlist' ? 'Class full. Student placed on the existing waitlist.' : 'Class allocated and enrolled.';
        } elseif ($action === 'enroll') {
            $sid = $life->enroll($id, array_map('intval', (array)($_POST['class_ids'] ?? [])), $auth['user_id']);
            $success = 'Enrollment confirmed for student #'.$sid.'.';
        } elseif ($action === 'ai') {
            $ai = (new AdmissionAssistantService($pdo))->summarize($id);
        } else {
            $life->review($id, $action, $auth['user_id'], (string)($_POST['notes'] ?? ''));
            $success = 'Application updated.';
        }
    } catch (Throwable $e) { $error = $e->getMessage(); }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') { $error = 'Session expired.'; }
$app = $id ? $life->get($id) : null;
$docs = $id ? $life->documents($id) : [];
$recs = $app ? (new ClassAllocationService($pdo))->recommend([
    'class_ids' => json_decode((string)($app['selected_classes_json'] ?? '[]'), true) ?: [],
    'subject_id' => (int)((json_decode((string)($app['selected_subjects_json'] ?? '[]'), true) ?: [0])[0] ?? 0),
    'delivery_pref' => (string)($app['delivery_pref'] ?? 'either'),
]) : [];
$timeline = $app ? $life->timeline((int)($app['lead_id'] ?? 0) ?: null, $id, (int)($app['student_id'] ?? 0) ?: null) : [];
include __DIR__ . '/../includes/header.php';
?>
<div class="container-fluid py-4">
    <a href="admissions_control.php">← Command centre</a>
    <h1 class="h3">Application review</h1>
    <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
    <?php if (!$app): ?><div class="alert alert-warning">Application not found.</div><?php else: ?>
    <div class="row g-3">
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm p-3 mb-3">
                <h2 class="h5"><?= e($app['full_name']) ?> <span class="badge text-bg-secondary"><?= e((string)($app['lifecycle_status'] ?? $app['status'])) ?></span></h2>
                <p class="small text-muted"><?= e($app['application_no']) ?> · <?= e((string)$app['phone']) ?> · <?= e((string)$app['email']) ?></p>
                <p>Parent: <?= e((string)$app['parent_name']) ?> <?= e((string)$app['parent_phone']) ?></p>
                <p>Programme: <?= e((string)($app['qualification_label'] ?? '—')) ?> · <?= e((string)($app['location_pref'] ?? '')) ?></p>
                <form method="post" class="d-flex flex-wrap gap-2"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>">
                    <textarea class="form-control" name="notes" placeholder="Internal notes (not shown to applicant)"><?= e((string)($app['admission_notes'] ?? '')) ?></textarea>
                    <button class="btn btn-outline-secondary" name="action" value="under_review">Under review</button>
                    <button class="btn btn-outline-warning" name="action" value="request_information">Request information</button>
                    <button class="btn btn-outline-dark" name="action" value="hold">Hold</button>
                    <button class="btn btn-success" name="action" value="approve">Approve & issue offer</button>
                    <button class="btn btn-outline-danger" name="action" value="reject">Reject</button>
                </form>
            </div>
            <div class="card border-0 shadow-sm p-3 mb-3">
                <h2 class="h5">Documents</h2>
                <ul><?php foreach ($docs as $d): ?><li><?= e($d['document_type']) ?> · <?= e($d['original_name']) ?></li><?php endforeach; ?></ul>
                <form method="post" enctype="multipart/form-data"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="action" value="document">
                    <input class="form-control mb-2" name="document_type" placeholder="identification / results / other">
                    <input class="form-control mb-2" type="file" name="file" required>
                    <button class="btn btn-sm btn-outline-primary">Upload securely</button>
                </form>
            </div>
            <div class="card border-0 shadow-sm p-3">
                <h2 class="h5">Recommended classes</h2>
                <p class="small text-muted">Explainable suggestions only. Staff confirm allocation.</p>
                <?php foreach (array_slice($recs, 0, 5) as $r): ?>
                    <form method="post" class="border rounded p-2 mb-2"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="action" value="allocate"><input type="hidden" name="class_id" value="<?= (int)$r['class_id'] ?>">
                        <strong><?= e($r['class_name']) ?></strong> · <?= e($r['teacher']) ?> · <?= e($r['schedule']) ?> · <?= e($r['capacity']) ?>
                        <div class="small"><?php foreach ($r['reasons'] as $reason): ?><div><?= e($reason) ?></div><?php endforeach; ?></div>
                        <button class="btn btn-sm btn-primary mt-1"><?= !empty($r['full']) ? 'Join waitlist' : 'Allocate' ?></button>
                        <?php if ($r['full']): ?><label class="small ms-2"><input type="checkbox" name="override"> Override capacity and enrol</label><?php endif; ?>
                    </form>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="col-lg-5">
            <form method="post" class="card border-0 shadow-sm p-3 mb-3"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="action" value="payment">
                <h2 class="h6">Record verified admission payment</h2>
                <input class="form-control mb-2" name="amount" placeholder="Amount"><input class="form-control mb-2" name="reference" placeholder="Existing payment reference">
                <select class="form-select mb-2" name="method"><option>cash</option><option>bank</option><option>onepay</option></select>
                <button class="btn btn-outline-primary">Mark verified</button>
            </form>
            <form method="post" class="card border-0 shadow-sm p-3 mb-3"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="action" value="enroll">
                <button class="btn btn-primary">Confirm enrollment / onboarding</button>
            </form>
            <form method="post" class="mb-3"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><button class="btn btn-outline-secondary" name="action" value="ai">AI summary (advisory)</button></form>
            <?php if ($ai): ?><div class="card border-0 shadow-sm p-3 mb-3"><p><?= nl2br(e($ai['summary'])) ?></p><div class="small text-muted"><?= e($ai['disclaimer']) ?></div></div><?php endif; ?>
            <div class="card border-0 shadow-sm p-3"><h2 class="h6">Lifecycle</h2><ul><?php foreach ($timeline as $t): ?><li><span class="small text-muted"><?= e((string)$t['created_at']) ?></span> <?= e((string)$t['event_type']) ?> — <?= e((string)($t['detail'] ?? '')) ?></li><?php endforeach; ?></ul></div>
        </div>
    </div>
    <?php endif; ?>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
