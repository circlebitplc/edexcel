<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Edexcel\Services\CommunicationAuth;
use Edexcel\Services\CommunicationHubService;
use Edexcel\Services\MessageTemplateService;

try {
    $auth = CommunicationAuth::require($pdo, 'communication.send');
} catch (Throwable $e) {
    http_response_code(403);
    echo 'Access denied.';
    exit;
}
if (($auth['role'] ?? '') !== 'teacher' && ($auth['role'] ?? '') !== 'admin') {
    http_response_code(403);
    echo 'Teachers only.';
    exit;
}

$teacherId = (int)($auth['teacher_id'] ?: ($_SESSION['teacher_id'] ?? 0));
if ($teacherId < 1 && ($auth['role'] ?? '') === 'teacher') {
    try {
        $s = $pdo->prepare('SELECT id FROM teachers WHERE user_id=? LIMIT 1');
        $s->execute([(int)$auth['user_id']]);
        $teacherId = (int)($s->fetchColumn() ?: 0);
    } catch (Throwable $e) {
    }
}

$classes = [];
try {
    if (($auth['role'] ?? '') === 'admin') {
        $classes = $pdo->query("SELECT id,name FROM student_classes WHERE deleted_at IS NULL ORDER BY name LIMIT 200")->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } else {
        $s = $pdo->prepare("SELECT id,name FROM student_classes WHERE teacher_id=? AND deleted_at IS NULL ORDER BY name");
        $s->execute([$teacherId]);
        $classes = $s->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
} catch (Throwable $e) {
}

$error = '';
$success = '';
$preview = null;
$hub = new CommunicationHubService($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf_token($_POST['csrf_token'] ?? '')) {
    try {
        $classId = (int)($_POST['class_id'] ?? 0);
        if (($auth['role'] ?? '') === 'teacher' && !CommunicationAuth::teacherOwnsClass($pdo, $teacherId, $classId)) {
            throw new RuntimeException('You can only message your own classes.');
        }
        if (($_POST['action'] ?? '') === 'confirm') {
            $hub->confirmBulk((int)$_POST['message_id'], (string)$_POST['confirm_token'], (int)$auth['user_id']);
            $success = 'Class message confirmed and queued for background delivery.';
        } else {
            $body = (string)($_POST['body'] ?? '');
            if (!empty($_POST['template_id'])) {
                $tpl = (new MessageTemplateService($pdo));
                // body already provided; templates validated at save time
            }
            $preview = $hub->preview([
                'channel' => (string)($_POST['channel'] ?? 'in_app'),
                'audience_type' => 'class',
                'audience_id' => $classId,
                'include_parents' => !empty($_POST['include_parents']),
                'category' => (string)($_POST['category'] ?? 'classes'),
                'subject' => (string)($_POST['subject'] ?? 'Class notice'),
                'body' => $body,
                'scheduled_at' => $_POST['schedule_at'] ?? null,
            ], (int)$auth['user_id']);
            if (empty($preview['needs_confirm'])) {
                $success = 'Queued for ' . (int)($preview['recipient_count'] ?? 0) . ' recipients.';
            }
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $error = 'Session expired.';
}

include __DIR__ . '/../includes/header.php';
?>
<div class="container py-4" style="max-width:720px">
    <h1 class="h3">Class communication</h1>
    <p class="text-muted">Send academic notices only to classes you teach. Bulk sends are queued — never sent live in this page.</p>
    <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
    <?php if ($preview && !empty($preview['needs_confirm'])): ?>
        <div class="alert alert-warning">
            <strong>Confirm broadcast</strong><br>
            Recipients: <?= (int)$preview['recipient_count'] ?> · Channel: <?= e((string)$preview['channel']) ?>
            <form method="post" class="mt-2"><?= csrf_field() ?>
                <input type="hidden" name="action" value="confirm">
                <input type="hidden" name="message_id" value="<?= (int)$preview['message_id'] ?>">
                <input type="hidden" name="confirm_token" value="<?= e((string)$preview['confirm_token']) ?>">
                <button class="btn btn-warning">Confirm send</button>
            </form>
        </div>
    <?php endif; ?>
    <form method="post" class="card border-0 shadow-sm p-3"><?= csrf_field() ?>
        <select class="form-select mb-2" name="class_id" required>
            <option value="">Select class…</option>
            <?php foreach ($classes as $c): ?><option value="<?= (int)$c['id'] ?>"><?= e($c['name']) ?></option><?php endforeach; ?>
        </select>
        <input class="form-control mb-2" name="subject" required placeholder="Subject">
        <textarea class="form-control mb-2" name="body" rows="5" required placeholder="Message"></textarea>
        <div class="row g-2 mb-2">
            <div class="col"><select class="form-select" name="channel"><option value="in_app">In-app</option><option value="whatsapp">WhatsApp</option><option value="sms">SMS</option></select></div>
            <div class="col"><select class="form-select" name="category"><option value="classes">Class</option><option value="homework">Homework</option><option value="exams">Exams</option><option value="announcements">Announcement</option></select></div>
        </div>
        <label class="form-check mb-2"><input class="form-check-input" type="checkbox" name="include_parents" value="1"> Also notify linked parents where permitted</label>
        <label class="form-label small">Schedule (optional)</label>
        <input class="form-control mb-3" type="datetime-local" name="schedule_at">
        <button class="btn btn-primary">Preview / queue</button>
    </form>
    <p class="mt-3 small"><a href="<?= e(BASE_URL) ?>student/notice_board.php">Notice board</a> · <a href="<?= e(BASE_URL) ?>admin/communication_threads.php">Threads</a></p>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
