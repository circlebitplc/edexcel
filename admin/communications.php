<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Edexcel\Services\AnnouncementService;
use Edexcel\Services\CommunicationAiAssistant;
use Edexcel\Services\CommunicationAuth;
use Edexcel\Services\CommunicationHubService;
use Edexcel\Services\CommunicationThreadService;
use Edexcel\Services\MessageTemplateService;

try {
    $auth = CommunicationAuth::require($pdo, 'communication.view');
} catch (Throwable $e) {
    http_response_code(403);
    echo 'Access denied.';
    exit;
}

$hub = new CommunicationHubService($pdo);
$threads = new CommunicationThreadService($pdo);
$templates = new MessageTemplateService($pdo);
$error = '';
$success = '';
$preview = null;
$aiDraft = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf_token($_POST['csrf_token'] ?? '')) {
    try {
        $action = (string)($_POST['action'] ?? 'preview');
        if ($action === 'preview') {
            if (!CommunicationAuth::can($pdo, $auth['user_id'], 'communication.send') && $auth['role'] !== 'admin' && $auth['role'] !== 'teacher') {
                throw new RuntimeException('communication.send required.');
            }
            $audience = (string)($_POST['audience_type'] ?? 'class');
            if (in_array($audience, ['everyone', 'students', 'parents', 'teachers'], true) && !CommunicationAuth::can($pdo, $auth['user_id'], 'communication.broadcast') && $auth['role'] !== 'admin') {
                throw new RuntimeException('communication.broadcast required for college-wide audiences.');
            }
            if ($auth['role'] === 'teacher' && $audience === 'class') {
                $classId = (int)($_POST['audience_id'] ?? 0);
                if (!CommunicationAuth::teacherOwnsClass($pdo, $auth['teacher_id'], $classId)) {
                    throw new RuntimeException('Teachers may only message their own classes.');
                }
            }
            $preview = $hub->preview($_POST, $auth['user_id']);
            $success = !empty($preview['needs_confirm'])
                ? 'Preview ready. Confirm before sending '.((int)$preview['preview']['recipient_count']).' recipients.'
                : 'Message queued for background delivery ('.((int)$preview['preview']['recipient_count']).' recipients).';
        } elseif ($action === 'confirm') {
            if (!CommunicationAuth::can($pdo, $auth['user_id'], 'communication.broadcast') && $auth['role'] !== 'admin') {
                throw new RuntimeException('Broadcast confirmation requires permission.');
            }
            $hub->confirmBulk((int)$_POST['message_id'], (string)$_POST['confirm_token'], $auth['user_id']);
            $success = 'Bulk message confirmed and queued. Delivery runs in the background.';
        } elseif ($action === 'cancel') {
            $hub->cancel((int)$_POST['message_id'], $auth['user_id']);
            $success = 'Message cancelled.';
        } elseif ($action === 'ai_draft') {
            $aiDraft = (new CommunicationAiAssistant($pdo))->draft((string)($_POST['intent'] ?? 'general notice'), [
                'student_name' => $_POST['student_name'] ?? '',
                'parent_name' => $_POST['parent_name'] ?? '',
                'detail' => $_POST['detail'] ?? '',
                'class_name' => $_POST['class_name'] ?? '',
            ]);
        } elseif ($action === 'thread') {
            $tid = $threads->open([
                'subject' => $_POST['subject'] ?? 'Staff conversation',
                'thread_type' => 'staff',
                'related_student_id' => (int)($_POST['related_student_id'] ?? 0),
                'body' => $_POST['body'] ?? '',
                'participants' => array_filter([
                    !empty($_POST['participant_role']) ? ['role' => $_POST['participant_role'], 'id' => (int)$_POST['participant_id']] : null,
                ]),
            ], $auth['role'], $auth['user_id']);
            $success = 'Thread #'.$tid.' opened.';
        } elseif ($action === 'seed_templates' && ($auth['role'] === 'admin' || CommunicationAuth::can($pdo, $auth['user_id'], 'communication.templates'))) {
            $n = $templates->seedDefaults($auth['user_id']);
            $success = $n.' default templates seeded.';
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $error = 'Session expired.';
}

$snap = $hub->centreSnapshot();
$history = $hub->search([
    'channel' => $_GET['channel'] ?? '',
    'status' => $_GET['status'] ?? '',
    'q' => $_GET['q'] ?? '',
], 40);
$inbox = $threads->inbox($auth['role'] === 'admin' ? 'admin' : $auth['role'], $auth['user_id'], 12);
$tpls = $templates->list();
$anns = (new AnnouncementService($pdo))->recent(8);

include __DIR__ . '/../includes/header.php';
?>
<div class="container-fluid py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div>
            <h1 class="h3 mb-0">Communication centre</h1>
            <p class="text-muted mb-0">In-app, WhatsApp and SMS — queued, auditable, not a social feed.</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a class="btn btn-sm btn-outline-success" href="bulk_sms.php"><i class="bi bi-chat-left-dots me-1"></i>Bulk SMS</a>
            <a class="btn btn-sm btn-outline-primary" href="announcements.php">Announcements</a>
            <a class="btn btn-sm btn-outline-primary" href="communication_templates.php">Templates</a>
            <a class="btn btn-sm btn-outline-primary" href="communication_analytics.php">Analytics</a>
            <a class="btn btn-sm btn-outline-warning" href="communication_ops.php">Channel ops</a>
            <a class="btn btn-sm btn-outline-secondary" href="communication_workbench.php">Student workbench</a>
            <a class="btn btn-sm btn-outline-secondary" href="communication_threads.php">Threads</a>
        </div>
    </div>

    <?php if ($error): ?><div class="alert alert-danger mt-3"><?= e($error) ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert alert-success mt-3"><?= e($success) ?></div><?php endif; ?>

    <div class="row g-2 mt-2">
        <?php foreach ([
            ['Unread in-app', $snap['unread_center']],
            ['Queued', $snap['queued']],
            ['Scheduled', $snap['scheduled']],
            ['Failed', $snap['failed']],
            ['Bulk awaiting confirm', $snap['pending_bulk']],
            ['Published announcements', $snap['announcements']],
        ] as $c): ?>
            <div class="col-6 col-md-4 col-xl-2"><div class="card border-0 shadow-sm p-3"><div class="small text-muted"><?= e($c[0]) ?></div><div class="fs-4 fw-bold"><?= (int)$c[1] ?></div></div></div>
        <?php endforeach; ?>
    </div>

    <div class="row g-4 mt-1">
        <div class="col-lg-5">
            <form method="post" class="card border-0 shadow-sm p-3 mb-3">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="preview">
                <h2 class="h5">Compose / queue</h2>
                <p class="small text-muted">Large audiences require confirmation. External messages are never sent in this request.</p>
                <div class="row g-2">
                    <div class="col-md-6">
                        <label class="form-label">Channel</label>
                        <select class="form-select" name="channel">
                            <option value="in_app">In-app</option>
                            <option value="whatsapp">WhatsApp</option>
                            <option value="sms">SMS</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Category</label>
                        <select class="form-select" name="category">
                            <?php foreach (['announcements','classes','homework','exams','attendance','payments','system'] as $cat): ?>
                                <option><?= e($cat) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Audience</label>
                        <select class="form-select" name="audience_type">
                            <?php foreach (['class'=>'Class (+ parents)','individual'=>'Individual','students'=>'All students','parents'=>'All parents','teachers'=>'All teachers','subject'=>'Subject','everyone'=>'Everyone'] as $val=>$label): ?>
                                <option value="<?= e($val) ?>"><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Audience / class ID</label>
                        <input class="form-control" type="number" name="audience_id" placeholder="Required for class/subject">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Priority</label>
                        <select class="form-select" name="priority"><option>normal</option><option>important</option><option>urgent</option></select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Template</label>
                        <select class="form-select" name="template_id">
                            <option value="">— none —</option>
                            <?php foreach ($tpls as $t): ?><option value="<?= (int)$t['id'] ?>"><?= e($t['name']) ?> (<?= e($t['channel']) ?>)</option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12"><input class="form-control" name="subject" placeholder="Subject"></div>
                    <div class="col-12"><textarea class="form-control" name="body" rows="5" placeholder="Message body. Variables like {{student_name}} are validated."></textarea></div>
                    <div class="col-md-6"><input class="form-control" name="recipient" placeholder="Phone for individual WhatsApp/SMS"></div>
                    <div class="col-md-6"><input class="form-control" type="datetime-local" name="scheduled_at" placeholder="Schedule"></div>
                </div>
                <button class="btn btn-primary mt-3">Preview / queue</button>
            </form>

            <?php if ($preview && !empty($preview['needs_confirm'])): ?>
                <div class="card border-warning shadow-sm p-3 mb-3">
                    <h2 class="h6">Confirm bulk send</h2>
                    <p><strong>Recipients: <?= (int)$preview['preview']['recipient_count'] ?></strong>
                        · Excluded: <?= (int)$preview['preview']['excluded_count'] ?>
                        · Channel: <?= e($preview['preview']['channel']) ?></p>
                    <p class="small"><?= e(mb_strimwidth((string)$preview['body'], 0, 240, '…')) ?></p>
                    <form method="post" class="d-inline"><?= csrf_field() ?>
                        <input type="hidden" name="action" value="confirm">
                        <input type="hidden" name="message_id" value="<?= (int)$preview['message_id'] ?>">
                        <input type="hidden" name="confirm_token" value="<?= e((string)$preview['confirm_token']) ?>">
                        <button class="btn btn-warning">Confirm send</button>
                    </form>
                    <form method="post" class="d-inline ms-2"><?= csrf_field() ?>
                        <input type="hidden" name="action" value="cancel">
                        <input type="hidden" name="message_id" value="<?= (int)$preview['message_id'] ?>">
                        <button class="btn btn-outline-secondary">Cancel</button>
                    </form>
                </div>
            <?php endif; ?>

            <form method="post" class="card border-0 shadow-sm p-3">
                <?= csrf_field() ?><input type="hidden" name="action" value="ai_draft">
                <h2 class="h6">AI draft (review before send)</h2>
                <input class="form-control mb-2" name="intent" placeholder="Intent e.g. attendance decline" required>
                <input class="form-control mb-2" name="parent_name" placeholder="Parent name (fact)">
                <input class="form-control mb-2" name="student_name" placeholder="Student name (fact)">
                <input class="form-control mb-2" name="detail" placeholder="Known fact / detail">
                <button class="btn btn-outline-secondary btn-sm">Draft</button>
                <?php if ($aiDraft): ?><div class="mt-2 p-2 bg-light rounded small"><?= nl2br(e($aiDraft['draft'])) ?><div class="text-muted mt-1"><?= e($aiDraft['disclaimer']) ?></div></div><?php endif; ?>
            </form>
        </div>

        <div class="col-lg-7">
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body">
                    <div class="d-flex justify-content-between"><h2 class="h5">Recent messages</h2>
                        <form class="d-flex gap-1"><input class="form-control form-control-sm" name="q" value="<?= e((string)($_GET['q'] ?? '')) ?>" placeholder="Search"><button class="btn btn-sm btn-outline-primary">Go</button></form>
                    </div>
                    <div class="table-responsive"><table class="table table-sm align-middle">
                        <thead><tr><th>When</th><th>Channel</th><th>Audience</th><th>Status</th><th>Recipients</th><th>Body</th></tr></thead>
                        <tbody>
                        <?php foreach ($history as $r): ?>
                            <tr>
                                <td class="small"><?= e((string)$r['created_at']) ?></td>
                                <td><?= e($r['channel']) ?></td>
                                <td><?= e($r['audience_type']) ?></td>
                                <td><span class="badge text-bg-secondary"><?= e($r['status']) ?></span></td>
                                <td><?= (int)($r['recipient_count'] ?? 0) ?></td>
                                <td class="small"><?= e(mb_strimwidth((string)$r['body'], 0, 60, '…')) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table></div>
                </div>
            </div>

            <div class="row g-3">
                <div class="col-md-6">
                    <div class="card border-0 shadow-sm p-3 h-100">
                        <h2 class="h6">Threads</h2>
                        <ul class="mb-0 small">
                            <?php foreach ($inbox as $t): ?>
                                <li><a href="communication_threads.php?id=<?= (int)$t['id'] ?>"><?= e($t['subject']) ?></a></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card border-0 shadow-sm p-3 h-100">
                        <h2 class="h6">Announcements</h2>
                        <ul class="mb-0 small">
                            <?php foreach ($anns as $a): ?>
                                <li><?= e($a['title']) ?> <span class="text-muted"><?= e($a['status']) ?></span></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
