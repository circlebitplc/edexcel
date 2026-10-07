<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Edexcel\Services\StudentStudyService;

require_student();
ensure_online_lesson_schema($pdo);

$studentId = (int)($_SESSION['user_id'] ?? 0);
$study = new StudentStudyService($pdo);
$tab = (string)($_GET['tab'] ?? 'bookmarks') === 'notes' ? 'notes' : 'bookmarks';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 30;
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token((string)($_POST['csrf_token'] ?? ''))) {
        $error = 'Your session expired. Refresh the page and try again.';
    } elseif ((string)($_POST['action'] ?? '') === 'remove_bookmark') {
        if ($study->removeBookmark($studentId, (int)($_POST['bookmark_id'] ?? 0))) {
            $message = 'Bookmark removed.';
        } else {
            $error = 'That bookmark was not found.';
        }
    }
}

$data = $tab === 'notes' ? $study->myNotes($studentId, $page, $perPage) : $study->myBookmarks($studentId, $page, $perPage);
$pages = max(1, (int)ceil($data['total'] / $perPage));
$h = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$typeIcon = [
    'video' => 'bi-play-circle',
    'text' => 'bi-file-text',
    'resource' => 'bi-file-earmark-pdf',
    'external_link' => 'bi-box-arrow-up-right',
    'activity' => 'bi-ui-checks',
];

$pageTitle = 'Bookmarks and notes';
include __DIR__ . '/../includes/header.php';
?>
<div class="container-fluid py-3" style="max-width: 960px;">
    <h1 class="h4 mb-3">Bookmarks and notes</h1>
    <?php if ($message !== ''): ?><div class="alert alert-success"><?= $h($message) ?></div><?php endif; ?>
    <?php if ($error !== ''): ?><div class="alert alert-danger"><?= $h($error) ?></div><?php endif; ?>

    <ul class="nav nav-tabs mb-3">
        <li class="nav-item"><a class="nav-link <?= $tab === 'bookmarks' ? 'active' : '' ?>" href="?tab=bookmarks">Bookmarks</a></li>
        <li class="nav-item"><a class="nav-link <?= $tab === 'notes' ? 'active' : '' ?>" href="?tab=notes">My notes</a></li>
    </ul>

    <?php if ($data['rows'] === []): ?>
        <div class="card border-0 shadow-sm rounded-4 p-4 text-muted">
            <?= $tab === 'notes'
                ? 'You have no notes yet. Open a lesson and use “My private notes” under any part.'
                : 'You have no bookmarks yet. Use the Bookmark button on a video, reading, file, link or question inside a lesson.' ?>
        </div>
    <?php elseif ($tab === 'bookmarks'): ?>
        <div class="list-group shadow-sm rounded-4">
            <?php foreach ($data['rows'] as $row): ?>
                <?php $url = student_online_lesson_url((int)$row['timetable_id'], (int)$row['item_id'], 'recordings'); ?>
                <div class="list-group-item d-flex gap-3 align-items-start">
                    <i class="bi <?= $h($typeIcon[(string)$row['item_type']] ?? 'bi-bookmark') ?> fs-5 text-secondary"></i>
                    <div class="flex-grow-1">
                        <a class="fw-semibold" href="<?= $h($url) ?>"><?= $h((string)$row['item_title']) ?></a>
                        <div class="small text-muted"><?= $h((string)$row['lesson_title']) ?> · saved <?= $h(date('d M Y', strtotime((string)$row['created_at']))) ?></div>
                        <?php if ((int)$row['question_id'] > 0 && (string)($row['question_prompt'] ?? '') !== ''): ?>
                            <div class="small mt-1">Question: <?= $h(mb_strimwidth((string)$row['question_prompt'], 0, 160, '…')) ?></div>
                        <?php endif; ?>
                    </div>
                    <form method="post">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="remove_bookmark">
                        <input type="hidden" name="bookmark_id" value="<?= (int)$row['id'] ?>">
                        <button class="btn btn-sm btn-outline-secondary" type="submit" title="Remove bookmark"><i class="bi bi-x-lg"></i></button>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="list-group shadow-sm rounded-4">
            <?php foreach ($data['rows'] as $row): ?>
                <?php
                    $where = (int)$row['item_id'] > 0
                        ? (string)($row['item_title'] ?? 'Lesson part')
                        : ((int)$row['section_id'] > 0 ? 'Section: ' . (string)($row['section_title'] ?? '') : 'Whole lesson');
                    $url = student_online_lesson_url((int)$row['timetable_id'], (int)$row['item_id'], 'recordings');
                ?>
                <div class="list-group-item">
                    <a class="fw-semibold" href="<?= $h($url) ?>"><?= $h((string)$row['lesson_title']) ?></a>
                    <span class="small text-muted">· <?= $h($where) ?> · updated <?= $h(date('d M Y', strtotime((string)$row['updated_at']))) ?></span>
                    <div class="mt-1" style="white-space: pre-wrap;"><?= $h((string)$row['body']) ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if ($pages > 1): ?>
        <nav class="mt-3">
            <ul class="pagination pagination-sm">
                <?php for ($p = 1; $p <= $pages; $p++): ?>
                    <li class="page-item <?= $p === $page ? 'active' : '' ?>"><a class="page-link" href="?tab=<?= $h($tab) ?>&amp;page=<?= $p ?>"><?= $p ?></a></li>
                <?php endfor; ?>
            </ul>
        </nav>
    <?php endif; ?>
    <p class="small text-muted mt-3">Only you can see your bookmarks and notes. Teachers cannot read them.</p>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
