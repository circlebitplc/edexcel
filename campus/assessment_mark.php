<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../vendor/autoload.php';
use Edexcel\Services\AssessmentAttemptService;
use Edexcel\Services\AssessmentMarkingService;
use Edexcel\Services\AssessmentService;
require_staff();
$auth = ['user_id' => (int)($_SESSION['user_id'] ?? 0), 'role' => (string)($_SESSION['role'] ?? ''), 'teacher_id' => (int)($_SESSION['teacher_id'] ?? 0)];
$mark = new AssessmentMarkingService($pdo);
$error = '';
$success = '';
$attemptId = (int)($_GET['attempt'] ?? $_POST['attempt_id'] ?? 0);
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf_token($_POST['csrf_token'] ?? '')) {
    try {
        if (($_POST['action'] ?? '') === 'suggest') {
            $suggestion = $mark->suggest($attemptId, (int)$_POST['question_id'], $auth);
            $success = 'AI suggestion ready. Teacher remains the final authority.';
        } else {
            $mark->override($attemptId, (int)$_POST['question_id'], (float)$_POST['marks'], (string)$_POST['comment'], $auth, (string)($_POST['notes'] ?? ''));
            $success = 'Mark saved and audited.';
        }
    } catch (Throwable $e) { $error = $e->getMessage(); }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') { $error = 'Session expired.'; }
$queue = $mark->queue($auth);
$attempt = $attemptId ? (new AssessmentAttemptService($pdo))->getAttempt($attemptId) : null;
$questions = $attempt ? (new AssessmentService($pdo))->questions((int)$attempt['assessment_id']) : [];
$answers = $attempt ? (new AssessmentAttemptService($pdo))->answers($attemptId) : [];
include __DIR__ . '/../includes/header.php';
?>
<div class="container-fluid py-4">
    <h1 class="h3">Marking</h1>
    <p class="text-muted">Automatic marks apply to objective items. Written work can use an optional AI suggestion. Teachers set the final mark. AI guidance is not official Pearson/Edexcel marking.</p>
    <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
    <div class="row g-3">
        <div class="col-lg-4"><div class="card border-0 shadow-sm"><div class="list-group list-group-flush"><?php foreach ($queue as $q): ?><a class="list-group-item" href="?attempt=<?= (int)$q['attempt_id'] ?>"><?= e($q['title']) ?> · student #<?= (int)$q['student_id'] ?> · <?= e($q['status']) ?></a><?php endforeach; ?></div></div></div>
        <div class="col-lg-8">
            <?php if ($attempt): foreach ($questions as $q): $ans = $answers[(int)$q['id']] ?? []; ?>
            <div class="card border-0 shadow-sm p-3 mb-3">
                <h2 class="h6"><?= e($q['topic_label'] ?? '') ?> · <?= e($q['question_type']) ?> · <?= e((string)$q['marks']) ?> marks</h2>
                <p><?= e($q['prompt']) ?></p>
                <div class="small text-muted mb-2">Student answer: <?= e((string)($ans['answer_text'] ?? $ans['choice_index'] ?? '—')) ?></div>
                <?php if (!empty($q['marking_guidance_json'])): ?><details class="mb-2"><summary>Marking guidance</summary><pre class="small"><?= e((string)$q['marking_guidance_json']) ?></pre></details><?php endif; ?>
                <form method="post" class="row g-2">
                    <?= csrf_field() ?>
                    <input type="hidden" name="attempt_id" value="<?= $attemptId ?>">
                    <input type="hidden" name="question_id" value="<?= (int)$q['id'] ?>">
                    <div class="col-md-2"><input class="form-control" name="marks" type="number" step="0.5" value="<?= e((string)($ans['marks_awarded'] ?? '')) ?>" placeholder="Mark"></div>
                    <div class="col-md-5"><input class="form-control" name="comment" placeholder="Feedback to student" value="<?= e((string)($ans['teacher_comment'] ?? '')) ?>"></div>
                    <div class="col-md-3"><input class="form-control" name="notes" placeholder="Internal marking notes" value="<?= e((string)($ans['marking_notes'] ?? '')) ?>"></div>
                    <div class="col-md-2"><button class="btn btn-primary w-100" name="action" value="save">Save</button></div>
                    <div class="col-12"><button class="btn btn-sm btn-outline-secondary" name="action" value="suggest">Ask AI assistant</button></div>
                </form>
            </div>
            <?php endforeach; endif; ?>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
