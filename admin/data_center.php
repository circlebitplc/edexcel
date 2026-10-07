<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../vendor/autoload.php';
use Edexcel\Services\ImportExportService;
require_admin();$svc=new ImportExportService($pdo);$error='';$preview=null;$summary=null;$dataset=(string)($_POST['dataset']??$_GET['dataset']??'students');
if($_SERVER['REQUEST_METHOD']==='POST'){
 if(!verify_csrf_token($_POST['csrf_token']??''))$error='Security token expired.';
 else try{
  if(($_POST['action']??'')==='preview'){
   if(!isset($_FILES['csv'])||$_FILES['csv']['error']!==UPLOAD_ERR_OK)throw new RuntimeException('Upload a CSV file.');
   $dir=dirname(__DIR__).'/storage/imports';if(!is_dir($dir))mkdir($dir,0750,true);$path=$dir.'/'.bin2hex(random_bytes(12)).'.csv';if(!move_uploaded_file($_FILES['csv']['tmp_name'],$path))throw new RuntimeException('Could not store upload.');
   $_SESSION['import_preview_path']=$path;$_SESSION['import_preview_dataset']=$dataset;$preview=$svc->preview($dataset,$path);
  }elseif(($_POST['action']??'')==='confirm'){
   $path=(string)($_SESSION['import_preview_path']??'');$dataset=(string)($_SESSION['import_preview_dataset']??'');$p=$svc->preview($dataset,$path);$summary=$svc->import($dataset,$p['rows'],(int)$_SESSION['user_id']);@unlink($path);unset($_SESSION['import_preview_path'],$_SESSION['import_preview_dataset']);
  }
 }catch(Throwable $e){$error=$e->getMessage();}
}
include __DIR__ . '/../includes/header.php';?>
<div class="container-fluid py-4"><h1 class="h3"><i class="bi bi-arrow-left-right"></i> Data import &amp; export</h1><p class="text-muted">CSV imports are previewed and committed atomically. Invalid rows abort the import.</p><?php if($error):?><div class="alert alert-danger"><?=e($error)?></div><?php endif;?><?php if($summary):?><div class="alert alert-success">Imported <?= (int)$summary['imported']?> rows.</div><?php endif;?>
<div class="card shadow-sm border-0 p-4" style="max-width:900px"><form method="post" enctype="multipart/form-data"><?=csrf_field()?><input type="hidden" name="action" value="preview"><div class="row g-3 align-items-end"><div class="col-md-5"><label class="form-label">Dataset</label><select class="form-select" name="dataset"><?php foreach(ImportExportService::DATASETS as $d):?><option value="<?=$d?>" <?=$dataset===$d?'selected':''?>><?=ucfirst($d)?></option><?php endforeach;?></select></div><div class="col-md-5"><label class="form-label">CSV file</label><input class="form-control" type="file" name="csv" accept=".csv,text/csv" required></div><div class="col-md-2"><button class="btn btn-primary w-100">Preview</button></div></div></form><?php if($preview):?><hr><h2 class="h5">Preview: <?=count($preview['rows'])?> rows</h2><?php if($preview['errors']):?><div class="alert alert-danger"><?php foreach($preview['errors'] as $e):?><div><?=e($e)?></div><?php endforeach;?></div><?php else:?><div class="table-responsive"><table class="table table-sm"><thead><tr><?php foreach($preview['headers'] as $h):?><th><?=e($h)?></th><?php endforeach;?></tr></thead><tbody><?php foreach(array_slice($preview['rows'],0,10) as $row):?><tr><?php foreach($preview['headers'] as $h):?><td><?=e($row[$h]??'')?></td><?php endforeach;?></tr><?php endforeach;?></tbody></table></div><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="confirm"><button class="btn btn-success">Confirm atomic import</button></form><?php endif;?><?php endif;?></div></div><?php include __DIR__ . '/../includes/footer.php';?>
