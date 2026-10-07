<?php
homepage_render_head($activeLayout);
$subjects = array_slice($hp['subjects'] ?? [], 0, 5);
$nodes = $subjects !== [] ? $subjects : [
    ['name' => 'ICT'], ['name' => 'Mathematics'], ['name' => 'Physics'], ['name' => 'Science'], ['name' => 'Business'],
];
$teachers = $hp['teachers'] ?? [];
$institute = (string)($hp['institute'] ?? 'Edexcel College');
$apply = rtrim((string)BASE_URL, '/') . '/student/register.php';
?>
<body class="hp hp-l10">
<?php homepage_render_preview_banner(); ?>
<div class="l10-bg" aria-hidden="true"></div>
<header class="l10-bar">
    <strong><?= e($institute) ?></strong>
    <nav>
        <button type="button" data-open-auth="login">Login</button>
        <a href="<?= e($apply) ?>">Admissions</a>
    </nav>
</header>
<section class="l10-hero">
    <p class="l10-glow">Education for the Next Generation</p>
    <h1>Connect a student to every subject node.</h1>
    <p>Live online classes worldwide. Physical classes at the Kandy campus when scheduled.</p>
</section>
<section class="l10-net" id="network">
    <svg class="l10-lines" viewBox="0 0 100 100" preserveAspectRatio="none" aria-hidden="true">
        <line x1="50" y1="50" x2="50" y2="12"/>
        <line x1="50" y1="50" x2="88" y2="32"/>
        <line x1="50" y1="50" x2="82" y2="78"/>
        <line x1="50" y1="50" x2="18" y2="78"/>
        <line x1="50" y1="50" x2="12" y2="32"/>
    </svg>
    <button type="button" class="l10-node l10-core is-on" data-node="student">STUDENT</button>
    <?php foreach ($nodes as $i => $sub): ?>
    <button type="button" class="l10-node l10-n<?= (int)$i ?>" data-node="<?= e(strtolower(preg_replace('/[^a-z0-9]+/i', '-', (string)$sub['name']))) ?>">
        <?= e($sub['name']) ?>
    </button>
    <?php endforeach; ?>
</section>
<section class="l10-dash">
    <article class="l10-glass"><span>Uptime students</span><strong data-count="<?= homepage_stat($hp, 'students') ?>">0</strong></article>
    <article class="l10-glass"><span>Teacher nodes</span><strong data-count="<?= homepage_stat($hp, 'teachers') ?>">0</strong></article>
    <article class="l10-glass"><span>Live classes</span><strong data-count="<?= homepage_stat($hp, 'today') ?>">0</strong></article>
    <article class="l10-glass"><span>Subject graph</span><strong data-count="<?= homepage_stat($hp, 'subjects') ?>">0</strong></article>
</section>
<nav class="l10-dock">
    <a class="l10-glass" href="<?= e(homepage_url([], 'network')) ?>">Live Timetable</a>
    <a class="l10-glass" href="#teachers">Teachers</a>
    <a class="l10-glass" href="<?= e($apply) ?>">Admissions</a>
    <a class="l10-glass" href="<?= e(rtrim(BASE_URL, '/') . '/student/dashboard.php') ?>">Student Portal</a>
</nav>
<p class="l10-out" id="node-out">Select a subject node to inspect the connection.</p>
<section class="l10-teachers" id="teachers">
    <?php foreach ($teachers as $teacher): ?>
    <a class="l10-glass" href="<?= e($teacher['profile_url']) ?>">
        <strong><?= e($teacher['name']) ?></strong>
        <?php homepage_render_teacher_rank($teacher); ?>
    </a>
    <?php endforeach; ?>
</section>
<?php
homepage_render_auth('modal');
homepage_render_site_footer($hp);
homepage_render_foot($activeLayout);
