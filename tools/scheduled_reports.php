<?php
declare(strict_types=1);
date_default_timezone_set('Asia/Colombo');
require_once __DIR__ . '/../config/cron_http_guard.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/ops.php';
require_once __DIR__ . '/../vendor/autoload.php';
use Edexcel\Services\NotificationCenterService;
if(!($pdo instanceof PDO))exit(1);ensure_ops_schema($pdo);ops_job_start($pdo,'scheduled_reports');$count=0;
try{
 $rows=$pdo->query("SELECT * FROM scheduled_reports WHERE active=1 AND (next_run_at IS NULL OR next_run_at<=NOW())")->fetchAll(PDO::FETCH_ASSOC)?:[];
 $center=new NotificationCenterService($pdo);
 foreach($rows as $r){
  $recipients=json_decode((string)$r['recipients_json'],true)?:[];
  foreach($recipients as $recipient){$center->create('admin','system','Scheduled report ready',(string)$r['report_key'].' is due for review.',(defined('BASE_URL')?BASE_URL:'/').'admin/finance.php',is_numeric($recipient)?(int)$recipient:null,null,'normal');}
  $next=$r['frequency']==='monthly'?date('Y-m-d H:i:s',strtotime('+1 month')):($r['frequency']==='weekly'?date('Y-m-d H:i:s',strtotime('+1 week')):date('Y-m-d H:i:s',strtotime('+1 day')));
  $pdo->prepare('UPDATE scheduled_reports SET last_run_at=NOW(),next_run_at=? WHERE id=?')->execute([$next,(int)$r['id']]);$count++;
 }
 ops_job_finish($pdo,'scheduled_reports',true,'scheduled='.$count);exit(0);
}catch(Throwable $e){ops_job_finish($pdo,'scheduled_reports',false,$e->getMessage());exit(1);}
