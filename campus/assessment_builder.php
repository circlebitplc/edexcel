<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../vendor/autoload.php';
use Edexcel\Services\AssessmentService;
require_staff();
$auth = ['user_id' => (int)($_SESSION['user_id'] ?? 0), 'role' => (string)($_SESSION['role'] ?? ''), 'teacher_id' => (int)($_SESSION['teacher_id'] ?? 0)];
$svc = new AssessmentService($pdo);
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$error = '';
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Session expired.';
    } else {
        try {
            $action = (string)($_POST['action'] ?? 'save');
            $blueprint = [];
            $topics = preg_split('/\r\n|\n/', (string)($_POST['blueprint_topics'] ?? '')) ?: [];
            foreach ($topics as $line) {
                if (preg_match('/^(.+?)\s*[:=\-]\s*(\d+(?:\.\d+)?)/', trim($line), $m)) {
                    $blueprint[] = ['topic' => trim($m[1]), 'marks' => (float)$m[2]];
                } elseif (trim($line) !== '') {
                    $blueprint[] = ['topic' => trim($line), 'marks' => 10];
                }
            }
            $_POST['blueprint'] = ['qualification' => $_POST['qualification_label'] ?? '', 'unit' => $_POST['unit_label'] ?? '', 'total_marks' => (float)($_POST['total_marks'] ?? 0), 'topics' => $blueprint, 'difficulty' => $_POST['difficulty'] ?? 'mixed'];
            if ($action === 'create' || $id < 1) {
                $id = $svc->create($_POST, $auth);
                $success = 'Draft created. Review the blueprint, then generate or add questions.';
            } elseif ($action === 'generate') {
                if ($id < 1) { $id = $svc->create($_POST, $auth); }
                else { $svc->update($id, $_POST, $auth); }
                $gen = $svc->generateFromBlueprint($id, $auth);
                $success = 'Generated '.$gen['questions'].' questions. Status is review — approve before publishing.';
            } elseif ($action === 'add_question') {
                $svc->addQuestion($id, $_POST, $auth);
                $success = 'Question added.';
            } elseif (in_array($action, ['review','approved','published','archived','draft'], true)) {
                $svc->transition($id, $action === 'review' ? 'review' : $action, $auth);
                $success = 'Status updated to '.$action.'.';
            } else {
                $svc->update($id, $_POST, $auth);
                $success = 'Assessment saved as draft/review.';
            }
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}
$row = $id ? $svc->get($id) : null;
$questions = $id ? $svc->questions($id) : [];
$bp = $svc->normalizeBlueprint($row['blueprint_json'] ?? []);
$bpText = '';
foreach ($bp['topics'] as $t) { $bpText .= $t['topic'].' = '.$t['marks']."\n"; }
include __DIR__ . '/../includes/header.php';
?>
<div class="container py-4">
    <h1 class="h3">Exam builder</h1>
    <p class="text-muted">Workflow: create → blueprint → questions → preview → approve → publish. Generated papers never auto-publish.</p>
    <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
    <form method="post" class="card border-0 shadow-sm p-3 mb-4">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= $id ?>">
        <div class="row g-2">
            <div class="col-md-6"><label class="form-label">Title</label><input class="form-control" name="title" required value="<?= e((string)($row['title'] ?? '')) ?>"></div>
            <div class="col-md-3"><label class="form-label">Type</label><select class="form-select" name="assessment_type"><?php foreach (AssessmentService::TYPES as $t): ?><option <?= (($row['assessment_type'] ?? '')===$t)?'selected':'' ?>><?= e($t) ?></option><?php endforeach; ?></select></div>
            <div class="col-md-3"><label class="form-label">Duration (minutes)</label><input class="form-control" type="number" name="duration_minutes" value="<?= e((string)($row['duration_minutes'] ?? 60)) ?>"></div>
            <div class="col-md-3"><label class="form-label">Qualification</label><input class="form-control" name="qualification_label" value="<?= e((string)($row['qualification_label'] ?? '')) ?>" placeholder="IAL / IGCSE"></div>
            <div class="col-md-3"><label class="form-label">Unit</label><input class="form-control" name="unit_label" value="<?= e((string)($row['unit_label'] ?? '')) ?>"></div>
            <div class="col-md-2"><label class="form-label">Subject ID</label><input class="form-control" type="number" name="subject_id" value="<?= e((string)($row['subject_id'] ?? '')) ?>"></div>
            <div class="col-md-2"><label class="form-label">Class ID</label><input class="form-control" type="number" name="class_id" value="<?= e((string)($row['class_id'] ?? '')) ?>"></div>
            <div class="col-md-2"><label class="form-label">Pass %</label><input class="form-control" type="number" name="pass_threshold" value="<?= e((string)($row['pass_threshold'] ?? 40)) ?>"></div>
            <div class="col-md-2"><label class="form-label">Attempts</label><input class="form-control" type="number" name="attempt_limit" value="<?= e((string)($row['attempt_limit'] ?? 1)) ?>"></div>
            <div class="col-md-2"><label class="form-label">Question count</label><input class="form-control" type="number" name="question_count" value="<?= e((string)($row['question_count'] ?? 10)) ?>"></div>
            <div class="col-md-3"><label class="form-label">Start</label><input class="form-control" type="datetime-local" name="start_at" value="<?= e($row && $row['start_at'] ? date('Y-m-d\TH:i', strtotime((string)$row['start_at'])) : '') ?>"></div>
            <div class="col-md-3"><label class="form-label">End</label><input class="form-control" type="datetime-local" name="end_at" value="<?= e($row && $row['end_at'] ? date('Y-m-d\TH:i', strtotime((string)$row['end_at'])) : '') ?>"></div>
        </div>
        <div class="form-check mt-2"><input class="form-check-input" type="checkbox" name="randomize_questions" <?= !empty($row['randomize_questions'])?'checked':'' ?>> Randomize questions</div>
        <div class="form-check"><input class="form-check-input" type="checkbox" name="randomize_answers" <?= !empty($row['randomize_answers'])?'checked':'' ?>> Randomize answers</div>
        <div class="form-check"><input class="form-check-input" type="checkbox" name="adaptive" <?= !empty($row['adaptive'])?'checked':'' ?>> Adaptive difficulty</div>
        <div class="form-check"><input class="form-check-input" type="checkbox" name="copy_controls" <?= !empty($row['copy_controls'])?'checked':'' ?>> Copy/paste warning (technical control, not invigilation)</div>
        <label class="form-label mt-3">Blueprint (topic = marks)</label>
        <textarea class="form-control" name="blueprint_topics" rows="6" placeholder="Databases = 15&#10;Networks = 10"><?= e($bpText) ?></textarea>
        <textarea class="form-control mt-2" name="instructions" rows="3" placeholder="Student instructions"><?= e((string)($row['instructions'] ?? '')) ?></textarea>
        <textarea class="form-control mt-2" name="generation_prompt" rows="2" placeholder="Optional: Create a 60-mark IAL ICT Unit 4 mock examination"><?= e((string)($row['generation_prompt'] ?? '')) ?></textarea>
        <div class="d-flex flex-wrap gap-2 mt-3">
            <button class="btn btn-outline-primary" name="action" value="<?= $id?'save':'create' ?>">Save draft</button>
            <button class="btn btn-outline-secondary" name="action" value="generate">Generate from blueprint</button>
            <?php if ($id): ?>
            <button class="btn btn-outline-warning" name="action" value="review">Send to review</button>
            <button class="btn btn-outline-success" name="action" value="approved">Approve</button>
            <button class="btn btn-primary" name="action" value="published">Publish</button>
            <?php endif; ?>
        </div>
    </form>
    <?php if ($id): ?>
    <div class="card border-0 shadow-sm p-3 mb-4">
        <h2 class="h5">Add a question</h2>
        <form method="post" class="row g-2">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= $id ?>">
            <input type="hidden" name="action" value="add_question">
            <div class="col-md-2"><select class="form-select" name="question_type"><option>mcq</option><option>true_false</option><option>numeric</option><option>short</option><option>essay</option></select></div>
            <div class="col-md-2"><input class="form-control" name="topic_label" placeholder="Topic"></div>
            <div class="col-md-2"><select class="form-select" name="difficulty"><option>easy</option><option selected>medium</option><option>hard</option></select></div>
            <div class="col-md-1"><input class="form-control" name="marks" value="1" placeholder="Marks"></div>
            <div class="col-md-5"><input class="form-control" name="prompt" required placeholder="Question prompt"></div>
            <div class="col-md-8"><input class="form-control" name="choices[]" placeholder="MCQ choice (repeat fields as needed)"></div>
            <div class="col-md-2"><input class="form-control" name="correct_index" placeholder="Correct index 0+"></div>
            <div class="col-md-2"><button class="btn btn-outline-primary w-100">Add</button></div>
        </form>
    </div>
    <div class="card border-0 shadow-sm p-3">
        <h2 class="h5">Preview (<?= count($questions) ?> questions, <?= e((string)($row['total_marks'] ?? 0)) ?> marks, status <?= e((string)($row['status'] ?? 'draft')) ?>)</h2>
        <ol><?php foreach ($questions as $q): ?><li class="mb-2"><strong><?= e($q['topic_label'] ?? '') ?></strong> · <?= e((string)$q['marks']) ?> marks · <?= e($q['question_type']) ?><div><?= e($q['prompt']) ?></div></li><?php endforeach; ?></ol>
        <a class="btn btn-sm btn-outline-secondary" href="assessment_analytics.php?id=<?= $id ?>">Class analysis</a>
        <a class="btn btn-sm btn-outline-secondary" href="assessment_mark.php?assessment=<?= $id ?>">Marking queue</a>
    </div>
    <?php endif; ?>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
