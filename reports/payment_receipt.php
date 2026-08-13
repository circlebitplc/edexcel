<?php
/**
 * reports/payment_receipt.php
 * Generate a payment receipt for a teacher.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/payment.php';
require_admin();

$teacher_id = (int)($_GET['teacher_id'] ?? 0);
$period_start = $_GET['period_start'] ?? date('Y-m-d', strtotime('-1 month'));
$period_end = $_GET['period_end'] ?? date('Y-m-d');

if (!$teacher_id) {
    die('Teacher ID required.');
}

// Get teacher name
$stmt = $pdo->prepare("SELECT name FROM teachers WHERE id = ?");
$stmt->execute([$teacher_id]);
$teacher = $stmt->fetch();
if (!$teacher) {
    die('Teacher not found.');
}

// Get payments for this period
$sql = "SELECT t.*, s.name as subject, c.name as class, r.name as room 
        FROM timetable t
        JOIN subjects s ON t.subject_id = s.id
        JOIN student_classes c ON t.class_id = c.id
        JOIN rooms r ON t.room_id = r.id
        WHERE t.teacher_id = ? 
        AND t.payment_status = 'paid'
        AND t.payment_date BETWEEN ? AND ?
        ORDER BY t.payment_date, t.date";
$stmt = $pdo->prepare($sql);
$stmt->execute([$teacher_id, $period_start, $period_end]);
$payments = $stmt->fetchAll();

$total_amount = array_sum(array_map(function($p) use ($FEE_PER_STUDENT_LIVE) { return lesson_amount((int)$p['student_count'], $FEE_PER_STUDENT_LIVE); }, $payments));

include __DIR__ . '/../includes/header.php';
?>
<div class="finance-heading"><div><h1><i class="bi bi-receipt"></i> Payment Receipt</h1><p>Teacher payment confirmation for the selected period.</p></div><div class="finance-actions"><button onclick="window.print()" class="btn btn-primary"><i class="bi bi-printer"></i> Print</button></div></div>

<div class="card">
    <div class="card-header bg-primary text-white">
        <h3 class="mb-0">Edexcel College</h3>
        <small>Payment Receipt</small>
    </div>
    <div class="card-body">
        <div class="row mb-3">
            <div class="col-md-6">
                <p><strong>Teacher:</strong> <?= htmlspecialchars($teacher['name']) ?></p>
                <p><strong>Period:</strong> <?= date('d M Y', strtotime($period_start)) ?> to <?= date('d M Y', strtotime($period_end)) ?></p>
            </div>
            <div class="col-md-6 text-end">
                <p><strong>Receipt Date:</strong> <?= date('d M Y') ?></p>
                <p><strong>Receipt #:</strong> RCP-<?= date('Ymd') ?>-<?= str_pad($teacher_id, 4, '0', STR_PAD_LEFT) ?></p>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-bordered">
                <thead class="table-dark">
                    <tr>
                        <th>Date</th>
                        <th>Subject</th>
                        <th>Class</th>
                        <th>Room</th>
                        <th>Students</th>
                        <th>Amount</th>
                        <th>Payment Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($payments as $p): ?>
                        <tr>
                            <td><?= date('d M Y', strtotime($p['date'])) ?></td>
                            <td><?= htmlspecialchars($p['subject']) ?></td>
                            <td><?= htmlspecialchars($p['class']) ?></td>
                            <td><?= htmlspecialchars($p['room']) ?></td>
                            <td><?= $p['student_count'] ?></td>
                            <td><?= htmlspecialchars($CURRENCY_SYMBOL_LIVE) ?> <?= number_format(lesson_amount((int)$p['student_count'], $FEE_PER_STUDENT_LIVE)) ?></td>
                            <td><?= date('d M Y', strtotime($p['payment_date'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (count($payments) === 0): ?>
                        <tr><td colspan="7" class="text-center">No payments in this period.</td></tr>
                    <?php endif; ?>
                </tbody>
                <tfoot class="table-success">
                    <tr>
                        <td colspan="5" class="text-end"><strong>Total</strong></td>
                        <td><strong>Rs <?= number_format($total_amount) ?></strong></td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <div class="row mt-3">
            <div class="col-12 text-center">
                <p><strong>Amount in words:</strong> <?= ucwords(num_to_words($total_amount)) ?> Rupees Only</p>
                <p class="text-muted"><small>This is a computer-generated receipt. No signature required.</small></p>
            </div>
        </div>

        <div class="text-center mt-3">
            <button onclick="window.print()" class="btn btn-primary"><i class="bi bi-printer"></i> Print</button>
            <a href="javascript:history.back()" class="btn btn-secondary">Back</a>
        </div>
    </div>
</div>

<?php
function num_to_words($num) {
    $ones = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine'];
    $tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];
    $teens = ['Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];
    
    if ($num == 0) return 'Zero';
    
    $words = '';
    if ($num >= 1000) {
        $words .= $ones[floor($num / 1000)] . ' Thousand ';
        $num %= 1000;
    }
    if ($num >= 100) {
        $words .= $ones[floor($num / 100)] . ' Hundred ';
        $num %= 100;
    }
    if ($num >= 20) {
        $words .= $tens[floor($num / 10)] . ' ';
        $num %= 10;
    } elseif ($num >= 10) {
        $words .= $teens[$num - 10] . ' ';
        $num = 0;
    }
    if ($num > 0) {
        $words .= $ones[$num] . ' ';
    }
    return trim($words);
}

include __DIR__ . '/../includes/footer.php';