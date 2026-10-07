<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../vendor/autoload.php';
use Edexcel\Services\ParentIntelligenceService;
if(empty($_SESSION['parent_id'])){header('Location: '.BASE_URL.'parent/login.php');exit;}
$children=(new ParentIntelligenceService($pdo))->children((int)$_SESSION['parent_id']);include __DIR__ . '/../includes/header.php';?>
<div class="container py-4"><h1 class="h3">Family progress insights</h1><p class="text-muted">Simple, supportive summaries based on linked-child records.</p><div class="row g-3"><?php foreach($children as $child):?><div class="col-md-6"><div class="card border-0 shadow-sm p-3"><h2 class="h5"><?=e($child['name'])?></h2><p><?=e($child['message'])?></p><div class="small text-muted">Attendance: <?=e((string)($child['attendance_recent']??'—'))?>% · Academic: <?=e((string)($child['academic_recent']??'—'))?>% · Homework: <?=e((string)($child['homework_percent']??'—'))?>%</div></div></div><?php endforeach;?></div></div><?php include __DIR__ . '/../includes/footer.php';?>
