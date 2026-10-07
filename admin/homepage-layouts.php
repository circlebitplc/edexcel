<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/homepage-layouts.php';

require_admin();
homepage_ensure_layout_setting($pdo);

$error = '';
$success = '';
$catalog = homepage_layout_catalog();
$active = getActiveHomepageLayout($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Your session expired. Please try again.';
    } else {
        $key = strtolower(preg_replace('/[^a-z0-9]/', '', (string)($_POST['layout'] ?? '')) ?? '');
        if (!homepage_is_allowed_layout($key)) {
            $error = 'That layout is not available.';
        } else {
            try {
                $previous = $active;
                $active = setActiveHomepageLayout($pdo, $key);
                if (function_exists('log_audit')) {
                    log_audit($pdo, 'update_homepage_layout', 'settings', null, ['layout' => $previous], ['layout' => $active]);
                }
                $success = homepage_layout_meta($active)['name'] . ' is now the live homepage.';
            } catch (Throwable $e) {
                error_log('homepage layout activate: ' . $e->getMessage());
                $error = 'Could not save the homepage layout.';
            }
        }
    }
}

include __DIR__ . '/../includes/header.php';
?>
<div class="container-fluid py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1">Homepage layouts</h1>
            <p class="text-muted mb-0">Choose one public homepage. Only one layout is active at a time. Preview opens in a new tab without changing the live site.</p>
        </div>
        <a class="btn btn-outline-primary" href="<?= e(BASE_URL) ?>" target="_blank" rel="noopener">View live homepage</a>
    </div>

    <?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>

    <div class="row g-4">
        <?php foreach ($catalog as $key => $meta): ?>
            <?php $isActive = $active === $key; ?>
            <div class="col-md-6 col-xl-4">
                <article class="card h-100 border-0 shadow-sm rounded-4 overflow-hidden <?= $isActive ? 'border border-success' : '' ?>">
                    <div class="hp-admin-thumb hp-admin-thumb-<?= (int)$meta['number'] ?>" aria-hidden="true">
                        <span><?= e($meta['name']) ?></span>
                    </div>
                    <div class="card-body d-flex flex-column">
                        <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                            <div>
                                <div class="small text-muted">Layout <?= (int)$meta['number'] ?></div>
                                <h2 class="h5 mb-0"><?= e($meta['name']) ?></h2>
                            </div>
                            <?php if ($isActive): ?>
                                <span class="badge text-bg-success">✓ Currently active</span>
                            <?php endif; ?>
                        </div>
                        <p class="text-muted small flex-grow-1"><?= e($meta['description']) ?></p>
                        <div class="d-flex gap-2">
                            <a class="btn btn-outline-secondary" href="<?= e(BASE_URL) ?>preview.php?layout=<?= e($key) ?>" target="_blank" rel="noopener">Preview</a>
                            <?php if ($isActive): ?>
                                <button class="btn btn-success" type="button" disabled>Active</button>
                            <?php else: ?>
                                <form method="post" class="m-0">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="layout" value="<?= e($key) ?>">
                                    <button class="btn btn-primary" type="submit">Activate</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </article>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<style>
.hp-admin-thumb { height: 140px; display: grid; place-items: end start; padding: 12px; color: #fff; font-weight: 700; }
.hp-admin-thumb-1 { background: linear-gradient(135deg,#1e3a8a,#3b82f6); }
.hp-admin-thumb-2 { background: linear-gradient(135deg,#eff6ff,#2563eb); color:#1e3a8a; }
.hp-admin-thumb-3 { background: linear-gradient(160deg,#2e1065 40%,#7c3aed); }
.hp-admin-thumb-4 { background:
    linear-gradient(#111827,#111827) 0 0/40% 55%,
    linear-gradient(#6b7280,#6b7280) 42% 0/58% 30%,
    linear-gradient(#e5e7eb,#e5e7eb) 0 60%/100% 40%; background-repeat:no-repeat; color:#111; }
.hp-admin-thumb-5 { background: linear-gradient(#0f766e,#115e59); }
.hp-admin-thumb-6 { background: linear-gradient(#5161ce,#3948aa); }
.hp-admin-thumb-7 { background: linear-gradient(#fff7ed,#b45309); color:#7c2d12; }
.hp-admin-thumb-8 { background: linear-gradient(#0b0b0f,#eab308); }
.hp-admin-thumb-9 { background: linear-gradient(#fff,#fecdd3 40%,#9f1239); color:#4c0519; }
.hp-admin-thumb-10 { background: radial-gradient(circle at 20% 20%,#22d3ee,transparent 40%), #05060a; }
</style>
<?php include __DIR__ . '/../includes/footer.php'; ?>
