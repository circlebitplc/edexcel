<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../vendor/autoload.php';
use Edexcel\Services\GlobalSearchService;
require_login();
$q=trim((string)($_GET['q']??''));$results=[];
if($q!=='')$results=(new GlobalSearchService($pdo))->search($q,(string)($_SESSION['role']??''),(int)$_SESSION['user_id'],(int)($_SESSION['teacher_id']??0));
include __DIR__ . '/../includes/header.php';?>
<div class="container-fluid py-4"><h1 class="h3"><i class="bi bi-search"></i> Global search</h1><form class="input-group mb-4"><input class="form-control" name="q" value="<?=e($q)?>" placeholder="Search students, teachers, classes<?=is_admin()?', payments':''?>"><button class="btn btn-primary">Search</button></form><?php foreach($results as $group=>$rows):?><div class="card shadow-sm border-0 mb-3"><div class="card-body"><h2 class="h5 text-capitalize"><?=e($group)?></h2><div class="list-group list-group-flush"><?php foreach($rows as $r):?><div class="list-group-item d-flex justify-content-between"><span><?=e((string)($r['title']??''))?></span><span class="badge text-bg-light"><?=e((string)($r['entity']??''))?> #<?= (int)$r['id']?></span></div><?php endforeach;?></div></div></div><?php endforeach;?><?php if($q!==''&&!$results):?><div class="alert alert-info">No authorized matches found.</div><?php endif;?></div><?php include __DIR__ . '/../includes/footer.php';?>
