<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Edexcel\Services\CommunicationAuth;
use Edexcel\Services\CommunicationHubService;
use Edexcel\Services\CommunicationThreadService;
use Edexcel\Services\StaffCommunicationWorkbenchService;
use Edexcel\Services\Student360Service;
use Edexcel\Services\StudentRiskService;

require_staff();

$studentId = (int)($_GET['student'] ?? 0);
$tab = strtolower(trim((string)($_GET['tab'] ?? 'overview')));
if (!in_array($tab, ['overview', 'communication'], true)) {
    $tab = 'overview';
}

$svc = new Student360Service($pdo);
$role = (string)($_SESSION['role'] ?? '');
$userId = (int)($_SESSION['user_id'] ?? 0);
if ($studentId < 1 || !$svc->canView($userId, $role, $studentId)) {
    http_response_code(403);
    require __DIR__ . '/../error_page.php';
    exit;
}

$profile = $svc->profile($studentId);
$student = $profile['student'] ?? [];
$risk = $profile['risk'] ?? [];
$interventions = (new StudentRiskService($pdo))->interventions($studentId);

$workbench = new StaffCommunicationWorkbenchService($pdo);
$commStats = $workbench->studentStats($studentId);
$commTimeline = $tab === 'communication' ? $workbench->studentTimeline($studentId, 80) : [];

$error = '';
$success = '';
if ($tab === 'communication' && $_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf_token($_POST['csrf_token'] ?? '')) {
    try {
        if (($role === 'admin' || CommunicationAuth::can($pdo, $userId, 'communication.send') || $role === 'teacher')
            && ($_POST['action'] ?? '') === 'open_thread') {
            $tid = (new CommunicationThreadService($pdo))->open([
                'subject' => (string)($_POST['subject'] ?? 'Student follow-up'),
                'body' => (string)($_POST['body'] ?? ''),
                'related_student_id' => $studentId,
                'thread_type' => 'staff',
            ], $role === 'admin' ? 'admin' : 'teacher', $userId);
            $success = 'Thread #'.$tid.' opened.';
            $commTimeline = $workbench->studentTimeline($studentId, 80);
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
} elseif ($tab === 'communication' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $error = 'Session expired.';
}

$name = (string)($student['full_name'] ?? $student['username'] ?? 'Student');
include __DIR__ . '/../includes/header.php';
?>
<div class="container-fluid py-4">
    <div class="d-flex flex-wrap justify-content-between gap-2">
        <div>
            <h1 class="h3"><i class="bi bi-person-vcard"></i> Student 360°</h1>
            <p class="text-muted mb-0"><?= e($name) ?> · ID <?= (int)$studentId ?></p>
        </div>
        <span class="badge text-bg-<?= in_array($risk['risk_level'] ?? 'LOW', ['HIGH', 'CRITICAL'], true) ? 'danger' : 'success' ?> align-self-start p-2">
            <?= e($risk['risk_level'] ?? 'LOW') ?> risk · <?= e((string)($risk['risk_score'] ?? 0)) ?>
        </span>
    </div>

    <ul class="nav nav-tabs mt-3">
        <li class="nav-item"><a class="nav-link <?= $tab === 'overview' ? 'active' : '' ?>" href="?student=<?= $studentId ?>&tab=overview">Overview</a></li>
        <li class="nav-item"><a class="nav-link <?= $tab === 'communication' ? 'active' : '' ?>" href="?student=<?= $studentId ?>&tab=communication">Communication</a></li>
    </ul>

    <?php if ($error): ?><div class="alert alert-danger mt-3"><?= e($error) ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert alert-success mt-3"><?= e($success) ?></div><?php endif; ?>

    <?php if ($tab === 'overview'): ?>
        <div class="row g-3 mb-3 mt-1">
            <?php foreach ([['Attendance', $profile['attendance']['percent'] ?? null, '%'], ['Academic', $profile['risk']['academic_percent'] ?? null, '%'], ['Homework', $profile['risk']['homework_percent'] ?? null, '%'], ['Outstanding', 'Rs '.number_format((float)($profile['fees']['outstanding'] ?? 0), 2), '']] as $card): ?>
                <div class="col-6 col-lg-3"><div class="card border-0 shadow-sm p-3"><div class="small text-muted"><?= e($card[0]) ?></div><div class="fs-4 fw-bold"><?= $card[1] === null ? '—' : e((string)$card[1]).e($card[2]) ?></div></div></div>
            <?php endforeach; ?>
        </div>
        <div class="row g-4">
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm mb-3"><div class="card-body">
                    <h2 class="h5">Academic &amp; classes</h2>
                    <div class="row g-2 mb-3"><?php foreach ($profile['classes'] as $c): ?>
                        <div class="col-md-6"><div class="border rounded p-2"><?= e($c['name']) ?><div class="small text-muted"><?= e((string)($c['subject_name'] ?? '')) ?> · <?= e((string)($c['teacher_name'] ?? '')) ?></div></div></div>
                    <?php endforeach; ?></div>
                    <div class="table-responsive"><table class="table table-sm"><thead><tr><th>Recent mark</th><th>Subject</th><th>Score</th><th>Date</th></tr></thead>
                        <tbody><?php foreach ($profile['academic']['recent_marks'] ?? [] as $m): ?>
                            <tr><td><?= e($m['metric']) ?></td><td><?= e($m['subject_name']) ?></td><td><?= e((string)$m['percent']) ?>%</td><td><?= e($m['recorded_at']) ?></td></tr>
                        <?php endforeach; ?></tbody></table></div>
                </div></div>
                <div class="card border-0 shadow-sm"><div class="card-body">
                    <h2 class="h5">Activity timeline</h2>
                    <?php foreach ($profile['activity'] as $event): ?>
                        <div class="border-start ps-3 mb-2"><div class="small text-muted"><?= e($event['created_at']) ?></div><div><?= e((string)($event['event_type'] ?? 'event')) ?> · <?= e((string)($event['detail'] ?? '')) ?></div></div>
                    <?php endforeach; ?>
                </div></div>
            </div>
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm mb-3"><div class="card-body">
                    <h2 class="h5">Risk reasons</h2>
                    <?php foreach (($risk['reasons'] ?? []) as $reason): ?><div class="alert alert-warning py-2"><?= e($reason) ?></div><?php endforeach; ?>
                    <?php if (empty($risk['reasons'])): ?><p class="text-success">No configured risk factors.</p><?php endif; ?>
                </div></div>
                <div class="card border-0 shadow-sm mb-3"><div class="card-body">
                    <h2 class="h5">Communication snapshot</h2>
                    <ul class="mb-2">
                        <li>Deliveries: <?= (int)$commStats['deliveries'] ?></li>
                        <li>Failed: <?= (int)$commStats['failed'] ?></li>
                        <li>Threads: <?= (int)$commStats['threads'] ?></li>
                        <li>Unread in-app: <?= (int)$commStats['unread_in_app'] ?></li>
                    </ul>
                    <a class="btn btn-sm btn-outline-primary" href="?student=<?= $studentId ?>&tab=communication">Open communication tab</a>
                </div></div>
                <div class="card border-0 shadow-sm"><div class="card-body">
                    <h2 class="h5">Interventions</h2>
                    <?php foreach ($interventions as $i): ?>
                        <div class="border-bottom py-2"><strong><?= e($i['intervention_type']) ?></strong><div class="small"><?= e($i['status']) ?> · <?= e((string)$i['due_date']) ?></div></div>
                    <?php endforeach; ?>
                </div></div>
            </div>
        </div>
    <?php else: ?>
        <div class="row g-3 mt-1 mb-3">
            <?php foreach ([['Deliveries', $commStats['deliveries']], ['Failed', $commStats['failed']], ['Threads', $commStats['threads']], ['Unread', $commStats['unread_in_app']]] as $c): ?>
                <div class="col-6 col-lg-3"><div class="card border-0 shadow-sm p-3"><div class="small text-muted"><?= e($c[0]) ?></div><div class="fs-4 fw-bold"><?= (int)$c[1] ?></div></div></div>
            <?php endforeach; ?>
        </div>
        <div class="row g-4">
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm"><div class="card-body">
                    <h2 class="h5">Delivery &amp; message timeline</h2>
                    <p class="small text-muted">Student deliveries plus linked parent notices. Sensitive risk AI content is not shown here.</p>
                    <?php if ($commTimeline === []): ?><div class="alert alert-light border">No communication events yet.</div><?php endif; ?>
                    <?php foreach ($commTimeline as $e):
                        $badge = match ((string)$e['kind']) {
                            'delivery' => 'secondary',
                            'parent_delivery' => 'info',
                            'thread' => 'primary',
                            default => 'light',
                        };
                        ?>
                        <div class="border-start ps-3 mb-3">
                            <div class="d-flex justify-content-between flex-wrap gap-2">
                                <div>
                                    <span class="badge text-bg-<?= e($badge) ?>"><?= e((string)$e['kind']) ?></span>
                                    <?php if (!empty($e['channel'])): ?><span class="badge text-bg-light text-dark"><?= e((string)$e['channel']) ?></span><?php endif; ?>
                                    <?php if (!empty($e['status'])): ?><span class="badge text-bg-<?= ($e['status']==='failed')?'danger':(($e['status']==='delivered'||$e['status']==='read')?'success':'secondary') ?>"><?= e((string)$e['status']) ?></span><?php endif; ?>
                                    <strong><?= e((string)$e['title']) ?></strong>
                                </div>
                                <div class="small text-muted"><?= e((string)$e['when']) ?></div>
                            </div>
                            <div class="small mt-1"><?= nl2br(e((string)$e['body'])) ?></div>
                            <?php if (!empty($e['link'])): ?><a class="small" href="<?= e((string)$e['link']) ?>">Open</a><?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div></div>
            </div>
            <div class="col-lg-4">
                <form method="post" class="card border-0 shadow-sm p-3 mb-3"><?= csrf_field() ?>
                    <input type="hidden" name="action" value="open_thread">
                    <h2 class="h6">Start staff thread</h2>
                    <input class="form-control mb-2" name="subject" required placeholder="Subject" value="Follow-up · <?= e($name) ?>">
                    <textarea class="form-control mb-2" name="body" rows="4" required placeholder="First message"></textarea>
                    <button class="btn btn-primary btn-sm">Open thread</button>
                </form>
                <div class="card border-0 shadow-sm p-3">
                    <h2 class="h6">Quick links</h2>
                    <a class="d-block mb-1" href="communications.php">Communication centre</a>
                    <a class="d-block mb-1" href="communication_ops.php?q=<?= $studentId ?>">Failed deliveries for student ID</a>
                    <a class="d-block" href="communication_threads.php">All threads</a>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
