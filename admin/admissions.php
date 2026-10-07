<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../vendor/autoload.php';
use Edexcel\Services\AdmissionAuth;
use Edexcel\Services\AdmissionLifecycleService;
try { AdmissionAuth::require($pdo, 'admissions.view'); } catch (Throwable $e) { http_response_code(403); echo 'Access denied.'; exit; }
$life = new AdmissionLifecycleService($pdo);
$rows = $life->dashboard([
    'status' => $_GET['status'] ?? '',
    'qualification' => $_GET['qualification'] ?? '',
    'source' => $_GET['source'] ?? '',
    'from' => $_GET['from'] ?? '',
    'to' => $_GET['to'] ?? '',
    'assigned_to' => (int)($_GET['assigned_to'] ?? 0),
    'location' => $_GET['location'] ?? '',
    'academic_year' => $_GET['academic_year'] ?? '',
    'subject_id' => (int)($_GET['subject_id'] ?? 0),
]);
include __DIR__ . '/../includes/header.php';
?>
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between"><h1 class="h3">Applications</h1><a class="btn btn-outline-primary" href="admissions_control.php">Command centre</a></div>
    <form class="row g-2 my-2">
        <div class="col-md-2"><input class="form-control" name="status" placeholder="Status" value="<?= e((string)($_GET['status'] ?? '')) ?>"></div>
        <div class="col-md-2"><input class="form-control" name="qualification" placeholder="Qualification" value="<?= e((string)($_GET['qualification'] ?? '')) ?>"></div>
        <div class="col-md-2"><input class="form-control" name="source" placeholder="Source" value="<?= e((string)($_GET['source'] ?? '')) ?>"></div>
        <div class="col-md-2"><input class="form-control" name="location" placeholder="Branch / location" value="<?= e((string)($_GET['location'] ?? '')) ?>"></div>
        <div class="col-md-2"><input class="form-control" name="academic_year" placeholder="Year" value="<?= e((string)($_GET['academic_year'] ?? '')) ?>"></div>
        <div class="col-md-2"><input class="form-control" name="assigned_to" placeholder="Staff user ID" value="<?= e((string)($_GET['assigned_to'] ?? '')) ?>"></div>
        <div class="col-md-2"><input class="form-control" type="date" name="from" value="<?= e((string)($_GET['from'] ?? '')) ?>"></div>
        <div class="col-md-2"><input class="form-control" type="date" name="to" value="<?= e((string)($_GET['to'] ?? '')) ?>"></div>
        <div class="col-md-2"><button class="btn btn-outline-primary w-100">Filter</button></div>
    </form>
    <div class="card border-0 shadow-sm"><div class="table-responsive"><table class="table">
        <thead><tr><th>Application</th><th>Applicant</th><th>Lifecycle</th><th>Submitted</th><th></th></tr></thead>
        <tbody><?php foreach ($rows as $r): ?><tr>
            <td><?= e($r['application_no']) ?></td>
            <td><?= e($r['full_name']) ?><div class="small text-muted"><?= e((string)$r['phone']) ?></div></td>
            <td><?= e((string)($r['lifecycle_status'] ?? $r['status'])) ?></td>
            <td><?= e((string)$r['created_at']) ?></td>
            <td><a class="btn btn-sm btn-outline-primary" href="admission_review.php?id=<?= (int)$r['id'] ?>">Open</a></td>
        </tr><?php endforeach; ?></tbody>
    </table></div></div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
