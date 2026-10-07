<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../vendor/autoload.php';
use Edexcel\Services\AttendanceAnalyticsService;
require_admin();$rows=(new AttendanceAnalyticsService($pdo))->atRisk();include __DIR__ . '/../includes/header.php';?>
<div class="container-fluid py-4"><h1 class="h3"><i class="bi bi-graph-down-arrow"></i> Attendance analytics</h1><p class="text-muted">Students below the configurable attendance warning threshold. Parent notifications should be reviewed before sending.</p><div class="card shadow-sm border-0"><div class="table-responsive"><table class="table"><thead><tr><th>Student ID</th><th>Attendance</th><th>Present</th><th>Absent</th><th>Longest absence streak</th><th>Threshold</th></tr></thead><tbody><?php foreach($rows as $r):?><tr><td><?= (int)$r['student_id']?></td><td class="text-danger fw-semibold"><?=e((string)$r['percent'])?>%</td><td><?= (int)$r['present']?></td><td><?= (int)$r['absent']?></td><td><?= (int)$r['consecutive_absences']?></td><td><?=e((string)$r['threshold'])?>%</td></tr><?php endforeach;?><?php if(!$rows):?><tr><td colspan="6" class="text-muted text-center py-4">No students currently below threshold.</td></tr><?php endif;?></tbody></table></div></div></div><?php include __DIR__ . '/../includes/footer.php';?>
