<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../vendor/autoload.php';
use Edexcel\Services\TeacherLeaveService;
require_admin();$svc=new TeacherLeaveService($pdo);$error='';$success='';
if($_SERVER['REQUEST_METHOD']==='POST'){if(!verify_csrf_token($_POST['csrf_token']??''))$error='Security token expired.';else try{$svc->review((int)$_POST['id'],(string)$_POST['status'],(int)$_SESSION['user_id']);$success='Leave request updated.';}catch(Throwable $e){$error=$e->getMessage();}}
$rows=$svc->list('pending');include __DIR__ . '/../includes/header.php';?>
<div class="container-fluid py-4"><h1 class="h3"><i class="bi bi-calendar-x"></i> Teacher leave</h1><?php if($error):?><div class="alert alert-danger"><?=e($error)?></div><?php endif;?><?php if($success):?><div class="alert alert-success"><?=e($success)?></div><?php endif;?><div class="card border-0 shadow-sm"><div class="table-responsive"><table class="table"><thead><tr><th>Teacher</th><th>Dates</th><th>Reason</th><th>Action</th></tr></thead><tbody><?php foreach($rows as $r):?><tr><td><?=e($r['teacher_name']??('Teacher #'.(int)$r['teacher_id']))?></td><td><?=e($r['start_date'])?> → <?=e($r['end_date'])?></td><td><?=e((string)$r['reason'])?></td><td><form method="post" class="d-flex gap-1"><?=csrf_field()?><input type="hidden" name="id" value="<?= (int)$r['id']?>"><button name="status" value="approved" class="btn btn-sm btn-success">Approve</button><button name="status" value="rejected" class="btn btn-sm btn-outline-danger">Reject</button></form></td></tr><?php endforeach;?></tbody></table></div></div></div><?php include __DIR__ . '/../includes/footer.php';?>
