<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../vendor/autoload.php';
use Edexcel\Services\FinanceService;
require_admin();
$finance=new FinanceService($pdo);$error='';$success='';
if($_SERVER['REQUEST_METHOD']==='POST'){
    if(!verify_csrf_token($_POST['csrf_token']??''))$error='Security token expired.';
    else try{
        if(($_POST['action']??'')==='adjustment'){$finance->recordAdjustment((int)$_POST['student_id'],(string)$_POST['type'],(float)$_POST['amount'],(string)$_POST['reason'],(int)$_SESSION['user_id']);$success='Adjustment recorded and audited.';}
    }catch(Throwable $e){$error=$e->getMessage();}
}
$from=(string)($_GET['from']??date('Y-m-01'));$to=(string)($_GET['to']??date('Y-m-d'));$report=$finance->collectionReport($from,$to);$teacherPayments=$finance->teacherPayments($from,$to);
include __DIR__ . '/../includes/header.php';
?>
<div class="container-fluid py-4">
<div class="d-flex flex-wrap justify-content-between gap-2"><div><h1 class="h3"><i class="bi bi-cash-stack"></i> Finance &amp; accounting</h1><p class="text-muted">Reporting layer over existing verified payments. OnePay logic is unchanged.</p></div><a class="btn btn-outline-primary" href="<?=e(BASE_URL)?>reports/monthly.php">Legacy monthly report</a></div>
<?php if($error):?><div class="alert alert-danger"><?=e($error)?></div><?php endif;?><?php if($success):?><div class="alert alert-success"><?=e($success)?></div><?php endif;?>
<form class="row g-2 mb-4"><div class="col-sm-4"><label class="form-label">From</label><input class="form-control" type="date" name="from" value="<?=e($from)?>"></div><div class="col-sm-4"><label class="form-label">To</label><input class="form-control" type="date" name="to" value="<?=e($to)?>"></div><div class="col-sm-4 d-flex align-items-end"><button class="btn btn-primary">Refresh report</button></div></form>
<div class="row g-3 mb-4"><?php foreach(['cash','onepay','bank','total'] as $k):?><div class="col-6 col-lg-3"><div class="card shadow-sm border-0 p-3"><div class="small text-muted"><?=ucfirst($k)?></div><div class="fs-4 fw-bold">Rs <?=number_format((float)$report['totals'][$k],2)?></div></div></div><?php endforeach;?></div>
<div class="row g-4"><div class="col-lg-7"><div class="card shadow-sm border-0"><div class="card-body"><h2 class="h5">Daily collection</h2><div class="table-responsive"><table class="table table-sm"><thead><tr><th>Date</th><th>Cash</th><th>OnePay</th><th>Bank</th><th>Total</th></tr></thead><tbody><?php foreach($report['rows'] as $r):?><tr><td><?=e($r['report_date'])?></td><td>Rs <?=number_format((float)$r['cash'],2)?></td><td>Rs <?=number_format((float)$r['onepay'],2)?></td><td>Rs <?=number_format((float)$r['bank'],2)?></td><td>Rs <?=number_format((float)$r['total'],2)?></td></tr><?php endforeach;?></tbody></table></div></div></div></div>
<div class="col-lg-5"><div class="card shadow-sm border-0 mb-4"><div class="card-body"><h2 class="h5">Teacher payment status</h2><?php foreach($teacherPayments as $r):?><div class="d-flex justify-content-between border-bottom py-2"><span><?=e($r['name'])?></span><span>Paid Rs <?=number_format((float)$r['paid_amount'],2)?> / scheduled Rs <?=number_format((float)$r['scheduled_amount'],2)?></span></div><?php endforeach;?></div></div>
<div class="card shadow-sm border-0"><div class="card-body"><h2 class="h5">Discount / scholarship / waiver</h2><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="adjustment"><input class="form-control mb-2" name="student_id" type="number" placeholder="Student ID" required><select class="form-select mb-2" name="type"><option value="discount">Discount</option><option value="scholarship">Scholarship</option><option value="waiver">Fee waiver</option><option value="refund">Refund</option></select><input class="form-control mb-2" name="amount" type="number" step="0.01" placeholder="Amount" required><textarea class="form-control mb-2" name="reason" placeholder="Reason" required></textarea><button class="btn btn-primary">Record adjustment</button></form></div></div></div></div>
</div><?php include __DIR__ . '/../includes/footer.php';?>
