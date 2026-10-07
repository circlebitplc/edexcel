<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../vendor/autoload.php';
use Edexcel\Services\LeadService;
use Edexcel\Services\PublicCatalogueService;
$error = '';
$success = '';
$dupes = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Your session expired. Please try again.';
    } else {
        try {
            $svc = new LeadService($pdo);
            $dupes = $svc->duplicates($_POST);
            $svc->create($_POST + ['source' => $_POST['source'] ?? 'website']);
            $success = 'Thank you. An admissions officer will follow up. We will not spam you.';
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}
$subjects = (new PublicCatalogueService($pdo))->subjects();
$page_title = 'Course Enquiry | Online Edexcel Classes Worldwide';
$meta_description = 'Enquire about live online Pearson Edexcel IGCSE and International A Level classes for students worldwide, or a physical class at the Kandy campus.';
$meta_robots = 'index, follow';
if (!function_exists('seo_absolute_url')) {
    require_once __DIR__ . '/../includes/seo.php';
}
$canonical_url = seo_absolute_url('admissions/enquire.php');
include __DIR__ . '/../includes/header.php';
?>
<div class="container py-4" style="max-width:720px">
    <h1 class="h3">Course enquiry — online classes worldwide</h1>
    <p class="text-muted">Tell us which Pearson Edexcel IGCSE or International A Level subjects you need. Most classes are live online. Say if you need a physical class at the Kandy campus. Only the details needed for a follow-up are stored.</p>
    <p class="small"><a href="/online-classes/">Online classes</a> · <a href="/subjects/">Subjects</a> · <a href="/edexcel-o-level">IGCSE</a> · <a href="/edexcel-a-level">IAL</a> · <a href="/locations/kandy">Kandy campus</a></p>
    <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><a class="btn btn-primary" href="apply.php">Continue to application</a><?php endif; ?>
    <?php if (!$success): ?>
    <form method="post" class="card border-0 shadow-sm p-4">
        <?= csrf_field() ?>
        <div class="row g-3">
            <div class="col-md-6"><label class="form-label" for="enq-name">Name</label><input class="form-control" id="enq-name" name="full_name" required></div>
            <div class="col-md-6"><label class="form-label" for="enq-phone">Phone / WhatsApp</label><input class="form-control" id="enq-phone" name="phone" required></div>
            <div class="col-md-6"><label class="form-label" for="enq-email">Email (optional)</label><input class="form-control" id="enq-email" type="email" name="email"></div>
            <div class="col-md-6"><label class="form-label" for="enq-qual">Qualification</label><input class="form-control" id="enq-qual" name="qualification_label" placeholder="IGCSE / IAL"></div>
            <div class="col-md-6"><label class="form-label" for="enq-subject">Subject interest</label><select class="form-select" id="enq-subject" name="subject_id"><option value="">—</option><?php foreach ($subjects as $s): ?><option value="<?= (int)$s['id'] ?>"><?= e($s['name']) ?></option><?php endforeach; ?></select></div>
            <div class="col-md-6"><label class="form-label" for="enq-source">How did you hear about us?</label>
                <select class="form-select" id="enq-source" name="source"><?php foreach (LeadService::SOURCES as $src): ?><option><?= e($src) ?></option><?php endforeach; ?></select>
            </div>
            <div class="col-md-6"><label class="form-label" for="enq-year">Academic year</label><input class="form-control" id="enq-year" name="academic_year" placeholder="2026/27"></div>
            <div class="col-md-6"><label class="form-label" for="enq-location">Location</label><input class="form-control" id="enq-location" name="location_pref" placeholder="Your country or city"></div>
            <div class="col-md-6"><label class="form-label" for="enq-delivery">Online / on-site</label><select class="form-select" id="enq-delivery" name="delivery_pref"><option value="either">Either</option><option value="online">Online</option><option value="onsite">On-site in Kandy</option></select></div>
            <div class="col-12"><label class="form-label" for="enq-notes">Notes</label><textarea class="form-control" id="enq-notes" name="notes" rows="3"></textarea></div>
        </div>
        <button class="btn btn-primary mt-3">Send enquiry</button>
    </form>
    <?php endif; ?>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
