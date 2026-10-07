<?php
declare(strict_types=1);
date_default_timezone_set('Asia/Colombo');
require_once __DIR__ . '/../config/cron_http_guard.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/campus.php';
require_once __DIR__ . '/../config/ops.php';
require_once __DIR__ . '/../vendor/autoload.php';
use Edexcel\Services\StudentSuccessService;
if(!($pdo instanceof PDO))exit(1);
ensure_ops_schema($pdo);ops_job_start($pdo,'student_success_recalculate');
$count=0;try{$ids=$pdo->query("SELECT id FROM users WHERE role='student' AND is_active=1 AND deleted_at IS NULL")->fetchAll(PDO::FETCH_COLUMN)?:[];$svc=new StudentSuccessService($pdo);foreach($ids as $id){$svc->assess((int)$id);$count++;}ops_job_finish($pdo,'student_success_recalculate',true,'students='.$count);echo"students={$count}\n";}catch(Throwable $e){ops_job_finish($pdo,'student_success_recalculate',false,$e->getMessage());fwrite(STDERR,$e->getMessage()."\n");exit(1);}
