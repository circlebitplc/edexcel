<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../vendor/autoload.php';
use Edexcel\Services\ParentAuthService;
use Edexcel\Services\ReportCardService;
if(!ParentAuthService::isLoggedIn()) { header('Location: /parent/login.php'); exit; }
$auth=new ParentAuthService($pdo);$parentId=(int)$_SESSION['parent_id'];$children=$auth->children($parentId);$studentId=(int)($_GET['student']??$_SESSION['parent_student_id']??0);$allowed=array_map(static fn($r)=>(int)$r['id'],$children);if(!in_array($studentId,$allowed,true))$studentId=$allowed[0]??0;
$card=$studentId?(new ReportCardService($pdo))->published($studentId,(string)($_GET['term']??'')):null;
include __DIR__ . '/../includes/header.php';?>
<div class="container-fluid py-4"><h1 class="h3">Published report card</h1><div class="d-flex flex-wrap gap-2 mb-3"><?php foreach($children as $child):?><a class="btn btn-sm <?=$studentId===(int)$child['id']?'btn-primary':'btn-outline-primary'?>" href="?student=<?= (int)$child['id']?>"><?=e($child['name']??$child['username']??'Child')?></a><?php endforeach;?></div><?php if(!$card):?><div class="alert alert-info">No published report card is available for this child.</div><?php else:?><div class="card shadow-sm border-0"><div class="card-body"><h2 class="h5"><?=e($card['term_label'])?></h2><div class="table-responsive"><table class="table"><thead><tr><th>Subject</th><th>Overall</th><th>Grade</th><th>Comment</th></tr></thead><tbody><?php foreach($card['items'] as $item):?><tr><td><?=e($item['subject_name'])?></td><td><?=e((string)$item['overall_percent'])?>%</td><td><?=e((string)$item['grade'])?></td><td><?=e((string)$item['teacher_comment'])?></td></tr><?php endforeach;?></tbody></table></div><?=!empty($card['overall_comment'])?'<p class="border-top pt-3">'.nl2br(e($card['overall_comment'])).'</p>':''?></div></div><?php endif;?></div><?php include __DIR__ . '/../includes/footer.php';?>
