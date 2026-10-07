<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../vendor/autoload.php';
use Edexcel\Services\CapacityService;
require_admin();$svc=new CapacityService($pdo);$error='';$success='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 if(!verify_csrf_token($_POST['csrf_token']??''))$error='Security token expired.';
 else try{$svc->setLimit((int)$_POST['class_id'],(int)$_POST['max_students'],(int)$_SESSION['user_id']);$success='Capacity updated and audited.';}catch(Throwable $e){$error=$e->getMessage();}
}
$rows=$svc->all();include __DIR__ . '/../includes/header.php';?>
<div class="container-fluid py-4"><h1 class="h3"><i class="bi bi-people"></i> Class capacity</h1><p class="text-muted">Capacity checks integrate with existing enrolment and waitlist workflows.</p><?php if($error):?><div class="alert alert-danger"><?=e($error)?></div><?php endif;?><?php if($success):?><div class="alert alert-success"><?=e($success)?></div><?php endif;?>
<div class="card shadow-sm border-0"><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Class</th><th>Capacity</th><th>Current</th><th>Available</th><th>Occupancy</th><th>Update</th></tr></thead><tbody><?php foreach($rows as $r):$pct=(float)$r['occupancy_percent'];?><tr><td><?=e($r['name'])?></td><td><?= (int)$r['max_students']?></td><td><?= (int)$r['current_students']?></td><td class="<?=$r['full']?'text-danger':'text-success'?>"><?= (int)$r['available_seats']?></td><td><div class="progress" style="min-width:120px"><div class="progress-bar <?=$r['full']?'bg-danger':($pct>=85?'bg-warning':'')?>" style="width:<?=min(100,$pct)?>%"><?= $pct?>%</div></div></td><td><form method="post" class="d-flex gap-2"><?=csrf_field()?><input type="hidden" name="class_id" value="<?= (int)$r['id']?>"><input class="form-control form-control-sm" name="max_students" type="number" min="1" value="<?= (int)$r['max_students']?>" style="max-width:100px"><button class="btn btn-sm btn-primary">Save</button></form></td></tr><?php endforeach;?></tbody></table></div></div></div><?php include __DIR__ . '/../includes/footer.php';?>
