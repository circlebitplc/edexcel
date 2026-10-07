<?php
declare(strict_types=1);
date_default_timezone_set('Asia/Colombo');
require_once __DIR__ . '/../config/cron_http_guard.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/ops.php';
require_once __DIR__ . '/../vendor/autoload.php';
use Edexcel\Services\AutomationService;
use Edexcel\Services\StudentSuccessService;
if(!($pdo instanceof PDO))exit(1);ensure_ops_schema($pdo);ops_job_start($pdo,'automation_worker');$done=0;
try{$ids=$pdo->query("SELECT id FROM users WHERE role='student' AND is_active=1 AND deleted_at IS NULL")->fetchAll(PDO::FETCH_COLUMN)?:[];$success=new StudentSuccessService($pdo);$automation=new AutomationService($pdo);foreach($ids as $id){$r=$success->assess((int)$id);$automation->handle('student_success_changed','success-'.(int)$id.'-'.date('Y-m-d'),$r);$done++;}ops_job_finish($pdo,'automation_worker',true,'students='.$done);echo"students={$done}\n";}catch(Throwable $e){ops_job_finish($pdo,'automation_worker',false,$e->getMessage());fwrite(STDERR,$e->getMessage()."\n");exit(1);}
