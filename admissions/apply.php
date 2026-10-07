<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../vendor/autoload.php';
use Edexcel\Services\AdmissionLifecycleService;
use Edexcel\Services\PublicCatalogueService;
$error = '';
$success = '';
$result = null;
$life = new AdmissionLifecycleService($pdo);
$cat = new PublicCatalogueService($pdo);
$subjects = $cat->subjects();
$classes = $cat->classes();
$token = preg_replace('/[^a-f0-9]/', '', (string)($_GET['token'] ?? $_POST['tracking_token'] ?? '')) ?? '';
$draft = ($token !== '' ? $life->byToken($token) : null) ?: [];
$draftSubjects = array_map('intval', json_decode((string)($draft['selected_subjects_json'] ?? '[]'), true) ?: []);
$draftClasses = array_map('intval', json_decode((string)($draft['selected_classes_json'] ?? '[]'), true) ?: []);
$draftDelivery = (string)($draft['delivery_pref'] ?? 'either');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Your session expired. Please try again.';
    } else {
        try {
            $isDraft = ($_POST['action'] ?? '') === 'draft';
            if (!$isDraft && empty($_POST['agree_terms'])) {
                throw new RuntimeException('Please acknowledge the college policies before submitting.');
            }
            $result = $life->submitApplication($_POST, $isDraft);
            if (!$isDraft && !empty($_FILES['document']['tmp_name'])) {
                $life->storeDocument((int)$result['id'], $_FILES['document'], (string)($_POST['document_type'] ?? 'application'), null);
            }
            $success = $isDraft
                ? 'Draft saved. Use this link to continue later.'
                : 'Application submitted. Keep your reference '.$result['application_no'].'.';
            $token = $result['tracking_token'];
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}
if ($token !== '') {
    $draft = $life->byToken($token) ?: $draft;
}
$draft = is_array($draft) ? $draft : [];
$draftSubjects = array_map('intval', json_decode((string)($draft['selected_subjects_json'] ?? '[]'), true) ?: []);
$draftClasses = array_map('intval', json_decode((string)($draft['selected_classes_json'] ?? '[]'), true) ?: []);
$draftDelivery = (string)($draft['delivery_pref'] ?? 'either');
$page_title = 'Student Admission Application | Edexcel College';
$meta_description = 'Apply online for Pearson Edexcel IGCSE and International A Level classes. Most places are live online worldwide. Physical classes are at the Kandy campus when scheduled.';
$meta_robots = $token !== '' ? 'noindex, nofollow' : 'index, follow';
if (!function_exists('seo_absolute_url')) {
    require_once __DIR__ . '/../includes/seo.php';
}
$canonical_url = seo_absolute_url('admissions/apply.php');
include __DIR__ . '/../includes/header.php';
?>
<div class="container py-4" style="max-width:800px">
    <h1 class="h3">Student admission application</h1>
    <p class="text-muted">Apply for Pearson Edexcel IGCSE or International A Level classes. Most students attend live online. Ask for a Kandy campus seat only if that class is on site. We store only what the application needs.</p>
    <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
    <?php if ($success && $result): ?>
        <div class="alert alert-success"><?= e($success) ?></div>
        <p>Track your application: <a href="status.php?token=<?= e($result['tracking_token']) ?>"><?= e($result['application_no']) ?></a></p>
        <p class="small">Continue later: <code><?= e(BASE_URL) ?>admissions/apply.php?token=<?= e($result['tracking_token']) ?></code></p>
    <?php endif; ?>
    <form method="post" class="card shadow-sm border-0 p-4" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <input type="hidden" name="tracking_token" value="<?= e($token) ?>">
        <input type="hidden" name="source" value="website">
        <h2 class="h5">Student</h2>
        <div class="row g-3">
            <div class="col-md-6"><label class="form-label">Full name</label><input class="form-control" name="full_name" required value="<?= e((string)($draft['full_name'] ?? '')) ?>"></div>
            <div class="col-md-6"><label class="form-label">Phone / WhatsApp</label><input class="form-control" name="phone" inputmode="tel" value="<?= e((string)($draft['phone'] ?? '')) ?>"></div>
            <div class="col-md-6"><label class="form-label">Email</label><input class="form-control" type="email" name="email" value="<?= e((string)($draft['email'] ?? '')) ?>"></div>
            <div class="col-md-6"><label class="form-label">Date of birth</label><input class="form-control" type="date" name="date_of_birth" value="<?= e((string)($draft['date_of_birth'] ?? '')) ?>"></div>
            <div class="col-12"><label class="form-label">Address (optional)</label><input class="form-control" name="address" value="<?= e((string)($draft['address'] ?? '')) ?>"></div>
        </div>
        <h2 class="h5 mt-4">Academic</h2>
        <div class="row g-3">
            <div class="col-md-6"><label class="form-label">Qualification</label><input class="form-control" name="qualification_label" placeholder="IGCSE / IAL" value="<?= e((string)($draft['qualification_label'] ?? '')) ?>"></div>
            <div class="col-md-6"><label class="form-label">Academic year</label><input class="form-control" name="academic_year" value="<?= e((string)($draft['academic_year'] ?? '')) ?>"></div>
            <div class="col-md-6"><label class="form-label">Previous school/college</label><input class="form-control" name="previous_school" value="<?= e((string)($draft['previous_school'] ?? '')) ?>"></div>
            <div class="col-md-6"><label class="form-label">Subjects</label>
                <select class="form-select" name="subject_ids[]" multiple size="5"><?php foreach ($subjects as $subject): ?><option value="<?= (int)$subject['id'] ?>"<?= in_array((int)$subject['id'], $draftSubjects, true) ? ' selected' : '' ?>><?= e($subject['name']) ?></option><?php endforeach; ?></select>
            </div>
        </div>
        <h2 class="h5 mt-4">Parent / guardian</h2>
        <div class="row g-3">
            <div class="col-md-4"><label class="form-label">Name</label><input class="form-control" name="parent_name" value="<?= e((string)($draft['parent_name'] ?? '')) ?>"></div>
            <div class="col-md-4"><label class="form-label">Relationship</label><input class="form-control" name="parent_relationship" value="<?= e((string)($draft['parent_relationship'] ?? '')) ?>"></div>
            <div class="col-md-4"><label class="form-label">Contact</label><input class="form-control" name="parent_phone" required value="<?= e((string)($draft['parent_phone'] ?? '')) ?>"></div>
        </div>
        <h2 class="h5 mt-4">Programme</h2>
        <div class="row g-3">
            <div class="col-md-6"><label class="form-label">Preferred classes</label>
                <select class="form-select" name="class_ids[]" multiple size="5"><?php foreach ($classes as $class): ?><option value="<?= (int)$class['id'] ?>"<?= in_array((int)$class['id'], $draftClasses, true) ? ' selected' : '' ?>><?= e($class['name']) ?><?= !empty($class['full']) ? ' (full)' : '' ?></option><?php endforeach; ?></select>
            </div>
            <div class="col-md-3"><label class="form-label" for="app-location">Location</label><input class="form-control" id="app-location" name="location_pref" placeholder="Your country or city" value="<?= e((string)($draft['location_pref'] ?? '')) ?>"></div>
            <div class="col-md-3"><label class="form-label">Online / on-site</label>
                <select class="form-select" name="delivery_pref">
                    <?php foreach (['either' => 'Either', 'onsite' => 'On-site', 'online' => 'Online'] as $val => $label): ?>
                        <option value="<?= e($val) ?>"<?= $draftDelivery === $val ? ' selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <h2 class="h5 mt-4">Documents (optional)</h2>
        <input class="form-control mb-2" name="document_type" placeholder="identification / previous results / other" value="application">
        <input class="form-control" type="file" name="document">
        <p class="small text-muted">Files are validated by type and size. Extensions alone are not trusted.</p>
        <label class="form-check mt-3"><input class="form-check-input" type="checkbox" name="agree_terms"> I have read the college policies and understand this is an application, not an automatic admission.</label>
        <div class="d-flex gap-2 mt-4">
            <button class="btn btn-outline-secondary" name="action" value="draft" formnovalidate>Save and continue later</button>
            <button class="btn btn-primary" name="action" value="submit">Submit application</button>
        </div>
    </form>
    <p class="small mt-3"><a href="enquire.php">Make an enquiry first</a> · <a href="status.php">Track an application</a></p>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
