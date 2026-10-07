<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../vendor/autoload.php';
use Edexcel\Services\AssessmentAttemptService;
use Edexcel\Services\AssessmentService;
require_student();
$studentId = (int)$_SESSION['user_id'];
$id = (int)($_GET['id'] ?? $_POST['assessment_id'] ?? 0);
$svc = new AssessmentAttemptService($pdo);
$error = '';
$payload = null;
if ($id < 1) {
    $list = (new AssessmentService($pdo))->dashboard(['status' => 'published'], ['role' => 'student', 'user_id' => $studentId]);
    include __DIR__ . '/../includes/header.php';
    echo '<div class="container py-4"><h1 class="h3">Assessments</h1><div class="list-group">';
    foreach ($list as $r) {
        echo '<a class="list-group-item list-group-item-action" href="assessment.php?id='.(int)$r['id'].'">'.e($r['title']).' <span class="badge text-bg-secondary">'.e($r['assessment_type']).'</span></a>';
    }
    echo '</div></div>';
    include __DIR__ . '/../includes/footer.php';
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Session expired. Your last saved answers should still be available.';
    } else {
        try {
            $attemptId = (int)($_POST['attempt_id'] ?? 0);
            $answers = [];
            foreach ((array)($_POST['q'] ?? []) as $qid => $val) {
                $answers[(int)$qid] = ['choice_index' => is_numeric($val) ? (int)$val : null, 'answer_text' => is_numeric($val) ? null : (string)$val, 'flagged' => !empty($_POST['flag'][$qid])];
            }
            if (($_POST['action'] ?? '') === 'save' || ($_POST['action'] ?? '') === 'autosave') {
                $svc->save($attemptId, $studentId, $answers);
            } else {
                $svc->save($attemptId, $studentId, $answers);
                $result = $svc->submit($attemptId, $studentId);
                header('Location: assessment_result.php?attempt='.(int)$result['attempt']['id']);
                exit;
            }
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}
try {
    $payload = $svc->start($id, $studentId);
} catch (Throwable $e) {
    $error = $error ?: $e->getMessage();
}
include __DIR__ . '/../includes/header.php';
$copy = !empty($payload['assessment']['copy_controls']);
?>
<div class="container py-4">
    <h1 class="h3"><?= e($payload['assessment']['title'] ?? 'Assessment') ?></h1>
    <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
    <?php if ($payload): ?>
    <p class="text-muted"><?= e((string)$payload['assessment']['instructions']) ?></p>
    <p class="small"><?= e((string)$payload['assessment']['security_note']) ?></p>
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>Progress: <span id="progress">0</span> / <?= count($payload['questions']) ?></div>
        <div class="fw-bold" id="timer" data-expires="<?= e((string)($payload['attempt']['expires_at'] ?? '')) ?>">--:--</div>
    </div>
    <form method="post" id="exam-form" <?= $copy ? 'oncopy="return false" onpaste="return false"' : '' ?>>
        <?= csrf_field() ?>
        <input type="hidden" name="assessment_id" value="<?= $id ?>">
        <input type="hidden" name="attempt_id" value="<?= (int)$payload['attempt']['id'] ?>">
        <?php foreach ($payload['questions'] as $i => $q): $ans = $q['answer'] ?? null; ?>
        <div class="card border-0 shadow-sm p-3 mb-3" id="q<?= (int)$q['id'] ?>">
            <div class="d-flex justify-content-between"><strong>Q<?= $i+1 ?></strong><span><?= e((string)$q['marks']) ?> marks</span></div>
            <p><?= e($q['prompt']) ?></p>
            <?php if (in_array($q['question_type'], ['mcq','true_false'], true) && is_array($q['choices'])): ?>
                <?php foreach ($q['choices'] as $ci => $choice): ?>
                <label class="d-block"><input type="radio" name="q[<?= (int)$q['id'] ?>]" value="<?= (int)$ci ?>" <?= isset($ans['choice_index']) && (int)$ans['choice_index']===(int)$ci?'checked':'' ?>> <?= e((string)$choice) ?></label>
                <?php endforeach; ?>
            <?php else: ?>
                <textarea class="form-control" name="q[<?= (int)$q['id'] ?>]" rows="3"><?= e((string)($ans['answer_text'] ?? '')) ?></textarea>
            <?php endif; ?>
            <label class="small mt-2"><input type="checkbox" name="flag[<?= (int)$q['id'] ?>]" <?= !empty($ans['flagged'])?'checked':'' ?>> Flag for review</label>
        </div>
        <?php endforeach; ?>
        <div class="d-flex gap-2">
            <button class="btn btn-outline-secondary" name="action" value="save">Save and continue</button>
            <button class="btn btn-primary" name="action" value="submit" onclick="return confirm('Submit this attempt? You cannot edit after submission.');">Submit</button>
        </div>
    </form>
    <script>
    (function(){
        const timer=document.getElementById('timer');
        const exp=timer && timer.dataset.expires ? Date.parse(timer.dataset.expires.replace(' ','T')) : 0;
        function tick(){ if(!exp) return; const left=Math.max(0,Math.floor((exp-Date.now())/1000)); timer.textContent=String(Math.floor(left/60)).padStart(2,'0')+':'+String(left%60).padStart(2,'0'); if(left<=0){ document.getElementById('exam-form').requestSubmit(); } }
        setInterval(tick,1000); tick();
        const form=document.getElementById('exam-form');
        const csrf=form.querySelector('[name=csrf_token]').value;
        const attempt=form.querySelector('[name=attempt_id]').value;
        function collect(){ const answers={}; form.querySelectorAll('[name^="q["]').forEach(el=>{ const m=el.name.match(/q\[(\d+)\]/); if(!m) return; if(el.type==='radio' && !el.checked) return; answers[m[1]]={choice_index: el.type==='radio'?el.value:null, answer_text: el.type==='radio'?null:el.value}; }); return answers; }
        async function autosave(){ try{ await fetch('<?= e(BASE_URL) ?>api/v1/index.php?resource=assessments',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-Token':csrf},credentials:'same-origin',body:JSON.stringify({csrf_token:csrf,action:'save',attempt_id:parseInt(attempt,10),answers:collect(),idempotency_key:'save-'+attempt+'-'+Date.now()})}); }catch(e){} }
        setInterval(autosave,20000);
        window.addEventListener('beforeunload',autosave);
        <?php if ($copy): ?>document.addEventListener('copy',e=>e.preventDefault());document.addEventListener('paste',e=>e.preventDefault());<?php endif; ?>
        document.addEventListener('visibilitychange',()=>{ if(document.hidden){ fetch('<?= e(BASE_URL) ?>api/v1/index.php?resource=assessments',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-Token':csrf},credentials:'same-origin',body:JSON.stringify({csrf_token:csrf,action:'event',attempt_id:parseInt(attempt,10),event_type:'blur',idempotency_key:'evt-'+attempt+'-'+Date.now()})}); }});
    })();
    </script>
    <?php endif; ?>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
