<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Edexcel\Services\LearningModuleService;
use Edexcel\Services\OnlineLessonService;
use Edexcel\Services\QuestionPool;
use Edexcel\Services\ResourceLibraryService;

require_staff();
ensure_online_lesson_schema($pdo);

$isAdmin = is_admin();
$teacherId = (int)($_SESSION['teacher_id'] ?? 0);
$userId = (int)($_SESSION['user_id'] ?? 0);
$library = new ResourceLibraryService($pdo);
$modules = new LearningModuleService($pdo);
$error = '';
$success = '';

$filters = [
    'q' => (string)($_GET['q'] ?? ''),
    'type' => (string)($_GET['type'] ?? ''),
    'subject' => (string)($_GET['subject'] ?? ''),
    'topic' => (string)($_GET['topic'] ?? ''),
    'tag' => (string)($_GET['tag'] ?? ''),
    'archived' => !empty($_GET['archived']),
];
$page = max(1, (int)($_GET['page'] ?? 1));
$openId = (int)($_GET['resource'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');
    try {
        if (!verify_csrf_token((string)($_POST['csrf_token'] ?? ''))) {
            throw new RuntimeException('Invalid security token.');
        }
        $resourceId = (int)($_POST['resource_id'] ?? 0);
        $meta = [
            'title' => (string)($_POST['title'] ?? ''),
            'description' => (string)($_POST['description'] ?? ''),
            'subject' => (string)($_POST['subject'] ?? ''),
            'topic' => (string)($_POST['topic'] ?? ''),
            'tags' => (string)($_POST['tags'] ?? ''),
            'type' => (string)($_POST['resource_type'] ?? 'document'),
            'url' => (string)($_POST['url'] ?? ''),
        ];
        if ($action === 'create') {
            $resourceId = $library->create($userId, $meta, $_FILES['resource_file'] ?? null);
            $success = 'Resource added to your library.';
        } elseif ($action === 'update') {
            $library->updateMeta($resourceId, $userId, $isAdmin, $meta);
            $success = 'Resource details saved.';
        } elseif ($action === 'new_version') {
            $resourceId = $library->newVersion($resourceId, $userId, $isAdmin, $_FILES['resource_file'] ?? null, (string)($_POST['url'] ?? ''));
            $success = 'New version saved. Lessons that use the older version keep it until you switch them in the lesson manager.';
        } elseif ($action === 'archive' || $action === 'restore') {
            $library->editable($resourceId, $userId, $isAdmin);
            $modules->archiveResource($resourceId, $userId, $isAdmin, $action === 'archive');
            $success = $action === 'archive' ? 'Resource archived. It cannot be added to new lessons.' : 'Resource restored.';
        } else {
            throw new RuntimeException('Unknown action.');
        }
        log_audit($pdo, 'resource_' . $action, 'online_lesson_resources', $resourceId);
        $openId = $action === 'archive' ? 0 : $resourceId;
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$result = $library->search($userId, $isAdmin, $filters, $page);
$pages = max(1, (int)ceil($result['total'] / 30));
$open = null;
$openVersions = [];
if ($openId > 0) {
    try {
        $open = $library->editable($openId, $userId, $isAdmin);
        $openVersions = $library->versions($openId, $userId, $isAdmin);
    } catch (Throwable $e) {
        $open = null;
    }
}

$lessonTargets = [];
try {
    $sql = "
        SELECT tt.id, tt.date, tt.start_time, tt.delivery_mode, s.name AS subject_name, c.name AS class_name
        FROM timetable tt
        JOIN subjects s ON s.id = tt.subject_id
        JOIN student_classes c ON c.id = tt.class_id
        WHERE tt.deleted_at IS NULL AND tt.date BETWEEN ? AND ?" . ($isAdmin ? '' : ' AND tt.teacher_id = ?') . "
        ORDER BY tt.date DESC, tt.start_time DESC
        LIMIT 80";
    $stmt = $pdo->prepare($sql);
    $params = [date('Y-m-d', strtotime('-30 days')), date('Y-m-d', strtotime('+60 days'))];
    if (!$isAdmin) {
        $params[] = $teacherId;
    }
    $stmt->execute($params);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
        if (OnlineLessonService::supportsDeliveryMode((string)($row['delivery_mode'] ?? 'physical'))) {
            $lessonTargets[] = $row;
        }
    }
} catch (Throwable $e) {
    $lessonTargets = [];
}

$h = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$query = static function (array $overrides) use ($filters, $page): string {
    $params = array_filter(array_merge($filters, ['page' => $page], $overrides), static fn ($v) => $v !== '' && $v !== false && $v !== null && $v !== 0);
    return BASE_URL . 'campus/resource_library.php?' . http_build_query($params);
};

$pageTitle = 'Resource library';
include __DIR__ . '/../includes/header.php';
?>
<div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-3">
    <div>
        <h1 class="h3 mb-1"><i class="bi bi-folder2-open me-2"></i>Resource library</h1>
        <p class="text-muted mb-0">Files and links you can reuse in any lesson. Using a resource in a lesson does not copy the file.</p>
    </div>
</div>

<?php if ($success !== ''): ?><div class="alert alert-success"><?= $h($success) ?></div><?php endif; ?>
<?php if ($error !== ''): ?><div class="alert alert-danger"><?= $h($error) ?></div><?php endif; ?>

<div class="row g-4">
    <div class="col-lg-8">
        <form method="get" class="card border-0 shadow-sm rounded-4 p-3 mb-3 row g-2 mx-0">
            <div class="col-md-4"><input class="form-control" name="q" value="<?= $h($filters['q']) ?>" placeholder="Search title, description, file" aria-label="Search"></div>
            <div class="col-md-2">
                <select class="form-select" name="type" aria-label="Type">
                    <option value="">All types</option>
                    <?php foreach (ResourceLibraryService::TYPES as $k => $v): ?><option value="<?= $h($k) ?>" <?= $filters['type'] === $k ? 'selected' : '' ?>><?= $h($v) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2"><input class="form-control" name="subject" value="<?= $h($filters['subject']) ?>" placeholder="Subject" aria-label="Subject"></div>
            <div class="col-md-2"><input class="form-control" name="topic" value="<?= $h($filters['topic']) ?>" placeholder="Topic" aria-label="Topic"></div>
            <div class="col-md-2"><input class="form-control" name="tag" value="<?= $h($filters['tag']) ?>" placeholder="Tag" aria-label="Tag" list="resTags"></div>
            <div class="col-12 d-flex gap-3 align-items-center">
                <button class="btn btn-primary btn-sm" type="submit">Filter</button>
                <label class="form-check mb-0"><input class="form-check-input" type="checkbox" name="archived" value="1" <?= $filters['archived'] ? 'checked' : '' ?>> <span class="form-check-label small">Show archived</span></label>
                <span class="small text-muted ms-auto"><?= (int)$result['total'] ?> resource<?= $result['total'] === 1 ? '' : 's' ?></span>
            </div>
        </form>
        <datalist id="resTags"><?php foreach (QuestionPool::SUGGESTED_TAGS as $t): ?><option value="<?= $h($t) ?>"><?php endforeach; ?></datalist>

        <div class="card border-0 shadow-sm rounded-4">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead><tr><th>Title</th><th>Type</th><th>Subject / topic</th><th>Size</th><th>Added</th><th>Used in</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($result['rows'] as $row): ?>
                        <tr>
                            <td>
                                <a href="<?= $h($query(['resource' => (int)$row['id']])) ?>" class="fw-semibold"><?= $h((string)$row['title']) ?></a>
                                <?php if ((int)($row['version_no'] ?? 1) > 1): ?><span class="badge text-bg-light border">v<?= (int)$row['version_no'] ?></span><?php endif; ?>
                                <?php foreach (QuestionPool::tagList($row['tags'] ?? null) as $tag): ?><span class="badge text-bg-secondary ms-1"><?= $h($tag) ?></span><?php endforeach; ?>
                                <?php if ($isAdmin): ?><div class="small text-muted">by <?= $h((string)($row['owner_name'] ?? '')) ?></div><?php endif; ?>
                            </td>
                            <td><?= $h(ResourceLibraryService::TYPES[(string)$row['resource_type']] ?? (string)$row['resource_type']) ?></td>
                            <td class="small"><?= $h(trim((string)($row['subject'] ?? '') . ' · ' . (string)($row['topic'] ?? ''), ' ·')) ?></td>
                            <td class="small text-nowrap"><?= $h(ResourceLibraryService::formatSize(isset($row['file_size']) ? (int)$row['file_size'] : null)) ?></td>
                            <td class="small text-nowrap"><?= $h(date('d M Y', strtotime((string)$row['created_at']))) ?></td>
                            <td class="small"><?= (int)$row['used_in'] ?> lesson item<?= (int)$row['used_in'] === 1 ? '' : 's' ?></td>
                            <td class="text-nowrap">
                                <?php if (!empty($row['file_key'])): ?>
                                    <a class="btn btn-sm btn-outline-secondary" target="_blank" rel="noopener" href="<?= $h(BASE_URL . 'campus/lesson_file.php?resource=' . (int)$row['id']) ?>">Preview</a>
                                <?php elseif (!empty($row['url'])): ?>
                                    <a class="btn btn-sm btn-outline-secondary" target="_blank" rel="noopener noreferrer" href="<?= $h((string)$row['url']) ?>">Open</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($result['rows'] === []): ?>
                        <tr><td colspan="7" class="text-muted p-4">No resources match.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php if ($pages > 1): ?>
            <nav class="mt-3"><ul class="pagination pagination-sm">
                <?php for ($p = 1; $p <= min($pages, 40); $p++): ?>
                    <li class="page-item <?= $p === $page ? 'active' : '' ?>"><a class="page-link" href="<?= $h($query(['page' => $p])) ?>"><?= $p ?></a></li>
                <?php endfor; ?>
            </ul></nav>
        <?php endif; ?>
    </div>

    <div class="col-lg-4">
        <?php if ($open): ?>
            <div class="card border-0 shadow-sm rounded-4 p-4 mb-4">
                <h2 class="h5"><?= $h((string)$open['title']) ?> <span class="badge text-bg-light border">v<?= (int)($open['version_no'] ?? 1) ?></span></h2>
                <?php if (!empty($open['superseded_by'])): ?>
                    <div class="alert alert-info small">This is an older version. <a href="<?= $h($query(['resource' => $library->latestVersionId((int)$open['id'])])) ?>">Open the latest</a>.</div>
                <?php endif; ?>
                <form method="post" class="row g-2">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="resource_id" value="<?= (int)$open['id'] ?>">
                    <div class="col-12"><label class="form-label small" for="e_title">Title</label><input class="form-control" id="e_title" name="title" maxlength="200" value="<?= $h((string)$open['title']) ?>" required></div>
                    <div class="col-12"><label class="form-label small" for="e_desc">Description</label><textarea class="form-control" id="e_desc" name="description" rows="2"><?= $h((string)($open['description'] ?? '')) ?></textarea></div>
                    <div class="col-6"><label class="form-label small" for="e_sub">Subject</label><input class="form-control" id="e_sub" name="subject" value="<?= $h((string)($open['subject'] ?? '')) ?>"></div>
                    <div class="col-6"><label class="form-label small" for="e_top">Topic</label><input class="form-control" id="e_top" name="topic" value="<?= $h((string)($open['topic'] ?? '')) ?>"></div>
                    <div class="col-12"><label class="form-label small" for="e_tags">Tags (comma separated)</label><input class="form-control" id="e_tags" name="tags" value="<?= $h((string)($open['tags'] ?? '')) ?>" list="resTags"></div>
                    <div class="col-12"><button class="btn btn-outline-primary btn-sm" type="submit">Save details</button></div>
                </form>

                <?php if (empty($open['superseded_by'])): ?>
                    <hr>
                    <h3 class="h6">Upload a new version</h3>
                    <form method="post" enctype="multipart/form-data" class="row g-2">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="new_version">
                        <input type="hidden" name="resource_id" value="<?= (int)$open['id'] ?>">
                        <?php if (!empty($open['file_key'])): ?>
                            <div class="col-12"><input class="form-control form-control-sm" type="file" name="resource_file" required aria-label="New file"></div>
                        <?php else: ?>
                            <div class="col-12"><input class="form-control form-control-sm" type="url" name="url" placeholder="https://" required aria-label="New link"></div>
                        <?php endif; ?>
                        <div class="col-12"><button class="btn btn-outline-primary btn-sm" type="submit">Save as new version</button></div>
                    </form>

                    <?php if ($lessonTargets !== [] && empty($open['archived'])): ?>
                        <hr>
                        <h3 class="h6">Use in a lesson</h3>
                        <form method="post" action="<?= $h(BASE_URL . 'campus/online_lesson.php') ?>" class="d-flex gap-2">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="use_resource">
                            <input type="hidden" name="resource_id" value="<?= (int)$open['id'] ?>">
                            <select class="form-select form-select-sm" name="timetable_id" required aria-label="Lesson">
                                <option value="">Choose a class date…</option>
                                <?php foreach ($lessonTargets as $t): ?>
                                    <option value="<?= (int)$t['id'] ?>"><?= $h(date('d M', strtotime((string)$t['date'])) . ' · ' . $t['subject_name'] . ' · ' . $t['class_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button class="btn btn-sm btn-primary" type="submit">Add</button>
                        </form>
                    <?php endif; ?>
                <?php endif; ?>

                <hr>
                <h3 class="h6">Versions</h3>
                <ul class="small mb-3">
                    <?php foreach ($openVersions as $v): ?>
                        <li>v<?= (int)$v['version_no'] ?> · <?= $h(date('d M Y', strtotime((string)$v['created_at']))) ?>
                            · used in <?= (int)$v['used_in'] ?>
                            <?php if (!empty($v['file_name'])): ?>· <a target="_blank" rel="noopener" href="<?= $h(BASE_URL . 'campus/lesson_file.php?resource=' . (int)$v['id']) ?>"><?= $h((string)$v['file_name']) ?></a><?php endif; ?>
                            <?= empty($v['superseded_by']) ? '<span class="badge text-bg-success">latest</span>' : '' ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="<?= !empty($open['archived']) ? 'restore' : 'archive' ?>">
                    <input type="hidden" name="resource_id" value="<?= (int)$open['id'] ?>">
                    <button class="btn btn-sm btn-outline-secondary" type="submit"><?= !empty($open['archived']) ? 'Restore' : 'Archive' ?></button>
                </form>
            </div>
        <?php endif; ?>

        <div class="card border-0 shadow-sm rounded-4 p-4">
            <h2 class="h5">Add a resource</h2>
            <form method="post" enctype="multipart/form-data" class="row g-2">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="create">
                <div class="col-12"><label class="form-label small" for="n_title">Title</label><input class="form-control" id="n_title" name="title" maxlength="200"></div>
                <div class="col-12">
                    <label class="form-label small" for="n_type">Type</label>
                    <select class="form-select" id="n_type" name="resource_type">
                        <?php foreach (ResourceLibraryService::TYPES as $k => $v): ?><option value="<?= $h($k) ?>"><?= $h($v) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12"><label class="form-label small" for="n_file">File (PDF, PowerPoint, Word, image; up to 8 MB)</label><input class="form-control" type="file" id="n_file" name="resource_file"></div>
                <div class="col-12"><label class="form-label small" for="n_url">…or a link (video or web page)</label><input class="form-control" type="url" id="n_url" name="url" placeholder="https://"></div>
                <div class="col-12"><label class="form-label small" for="n_desc">Description</label><textarea class="form-control" id="n_desc" name="description" rows="2"></textarea></div>
                <div class="col-6"><label class="form-label small" for="n_sub">Subject</label><input class="form-control" id="n_sub" name="subject"></div>
                <div class="col-6"><label class="form-label small" for="n_top">Topic</label><input class="form-control" id="n_top" name="topic"></div>
                <div class="col-12"><label class="form-label small" for="n_tags">Tags</label><input class="form-control" id="n_tags" name="tags" list="resTags" placeholder="CPU, Memory"></div>
                <div class="col-12"><button class="btn btn-primary" type="submit">Add to library</button></div>
            </form>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
