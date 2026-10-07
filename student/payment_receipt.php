<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Edexcel\Services\PaymentTransactionService;

require_student();
ensure_recordings_schema($pdo);

$studentId = (int)($_SESSION['user_id'] ?? 0);
$id = (int)($_GET['id'] ?? 0);
$payments = new PaymentTransactionService($pdo);
$txn = $payments->findById($id);
if (!$txn || (int)$txn['student_id'] !== $studentId || (string)$txn['status'] !== 'paid') {
    http_response_code(404);
    exit('Receipt not found.');
}

$lesson = $pdo->prepare("
    SELECT s.name AS subject_name, c.name AS class_name, tt.date, tt.start_time, t.name AS teacher_name
    FROM timetable tt
    JOIN subjects s ON s.id = tt.subject_id
    JOIN student_classes c ON c.id = tt.class_id
    JOIN teachers t ON t.id = tt.teacher_id
    WHERE tt.id = ?
");
$lesson->execute([(int)$txn['timetable_id']]);
$info = $lesson->fetch(PDO::FETCH_ASSOC) ?: [];

include __DIR__ . '/../includes/header.php';
?>
<p class="mb-3"><a href="<?= htmlspecialchars(BASE_URL . 'student/dashboard.php?tab=fees') ?>">&larr; Fees</a></p>
<div class="card border-0 shadow-sm rounded-4 p-4" style="max-width:640px">
    <h1 class="h4">Payment receipt</h1>
    <p class="text-muted">Edexcel College · Class recording fee</p>
    <table class="table">
        <tr><th>Date</th><td><?= htmlspecialchars(!empty($info['date']) ? date('d M Y', strtotime((string)$info['date'])) : '') ?></td></tr>
        <tr><th>Subject</th><td><?= htmlspecialchars((string)($info['subject_name'] ?? '')) ?></td></tr>
        <tr><th>Class</th><td><?= htmlspecialchars((string)($info['class_name'] ?? '')) ?></td></tr>
        <tr><th>Teacher</th><td><?= htmlspecialchars((string)($info['teacher_name'] ?? '')) ?></td></tr>
        <tr><th>Amount</th><td>Rs <?= number_format((float)$txn['amount'], 2) ?> <?= htmlspecialchars((string)$txn['currency']) ?></td></tr>
        <?php
        $receiptGateway = strtolower((string)($txn['gateway'] ?? 'payment'));
        $receiptMethod = \Edexcel\Services\StudentLessonFeeService::displayMethod($txn);
        $receiptLabel = $receiptMethod !== $receiptGateway
            ? \Edexcel\Services\StudentLessonFeeService::gatewayLabel($receiptMethod)
            : ucfirst($receiptGateway);
        ?>
        <tr><th>Method</th><td><?= htmlspecialchars($receiptLabel) ?></td></tr>
        <tr><th>Reference</th><td><?= htmlspecialchars((string)$txn['gateway_reference']) ?></td></tr>
        <tr><th>Paid at</th><td><?= htmlspecialchars((string)($txn['paid_at'] ?? '')) ?></td></tr>
    </table>
    <?php if (!empty($txn['recording_id'])): ?>
        <a class="btn btn-primary" href="<?= htmlspecialchars(BASE_URL . 'student/recording.php?id=' . (int)$txn['recording_id']) ?>">Watch recording</a>
    <?php endif; ?>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
