<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../vendor/autoload.php';
use Edexcel\Services\AppLogger;
require_admin();
$logger=new AppLogger($pdo);$slow=$logger->recentDb(50);$metrics=[];
try{$metrics=$pdo->query("SELECT SUBSTRING_INDEX(route,'?',1) route,method,ROUND(AVG(duration_ms),1) avg_ms,MAX(duration_ms) max_ms,COUNT(*) samples FROM performance_metrics WHERE created_at>=DATE_SUB(NOW(),INTERVAL 24 HOUR) GROUP BY SUBSTRING_INDEX(route,'?',1),method ORDER BY avg_ms DESC LIMIT 25")->fetchAll(PDO::FETCH_ASSOC)?:[];}catch(Throwable $e){}
include __DIR__ . '/../includes/header.php';?>
<div class="container-fluid py-4"><h1 class="h3"><i class="bi bi-activity"></i> API health</h1><p class="text-muted">Request telemetry is aggregated; credentials and request bodies are never displayed.</p><div class="card border-0 shadow-sm"><div class="card-body"><h2 class="h5">Slowest routes, last 24 hours</h2><div class="table-responsive"><table class="table table-sm"><thead><tr><th>Route</th><th>Method</th><th>Average ms</th><th>Max ms</th><th>Samples</th></tr></thead><tbody><?php foreach($metrics as $m):?><tr><td><?=e($m['route'])?></td><td><?=e($m['method'])?></td><td><?=e((string)$m['avg_ms'])?></td><td><?=e((string)$m['max_ms'])?></td><td><?=e((string)$m['samples'])?></td></tr><?php endforeach;?></tbody></table></div></div></div></div><?php include __DIR__ . '/../includes/footer.php';?>
