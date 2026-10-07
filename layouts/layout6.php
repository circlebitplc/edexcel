<?php
homepage_render_head($activeLayout);
$today = $hp['todayLessons'] ?? [];
$events = array_slice($hp['events'] ?? [], 0, 4);
$programmes = array_slice($hp['programmes'] ?? [], 0, 8);
$teachers = $hp['teachers'] ?? [];
$institute = (string)($hp['institute'] ?? 'Edexcel College');
$hour = (int)date('G');
$greet = $hour < 12 ? 'Good Morning' : ($hour < 17 ? 'Good Afternoon' : 'Good Evening');
?>
<body class="hp hp-l6">
<?php homepage_render_preview_banner(); ?>
<div class="l6-phone">
<header class="l6-appbar">
    <button class="l6-iconbtn" type="button" data-l6-tab="home" aria-label="Menu"><i class="fas fa-bars"></i></button>
    <strong><?= e($institute) ?></strong>
    <button type="button" class="l6-avatar" data-open-auth="login" aria-label="Profile">S</button>
</header>
<main class="l6-sheet">
    <section class="l6-panel" id="home">
        <p class="l6-greet"><?= e($greet) ?>, Student!</p>
        <p class="l6-muted">Around 90% of classes are live online worldwide. Physical classes are at the Kandy campus when scheduled.</p>
        <div class="l6-actions">
            <a href="#courses" data-l6-tab="courses"><i class="fas fa-book"></i> Courses</a>
            <a href="#timetable" data-l6-tab="timetable"><i class="fas fa-clock"></i> Timetable</a>
            <a href="#teachers" data-l6-tab="teachers"><i class="fas fa-chalkboard-user"></i> Teachers</a>
            <a href="<?= e(rtrim(BASE_URL, '/') . '/student/dashboard.php?tab=documents') ?>"><i class="fas fa-file"></i> Assignments</a>
            <a href="<?= e(rtrim(BASE_URL, '/') . '/student/dashboard.php?tab=exams') ?>"><i class="fas fa-chart-column"></i> Results</a>
            <button type="button" data-open-auth="login"><i class="fas fa-right-to-bracket"></i> Portal</button>
        </div>
        <h2>Today's classes</h2>
        <?php if ($today === []): ?><p class="l6-muted">Nothing on today's timetable.</p><?php endif; ?>
        <?php foreach ($today as $lesson): ?>
        <article class="l6-card">
            <strong><?= e($lesson['subject_name'] ?? '') ?></strong>
            <span><?= e(homepage_format_time($lesson['start_time'] ?? '')) ?> · <?= e($lesson['room_name'] ?? '') ?></span>
        </article>
        <?php endforeach; ?>
        <h2>Upcoming events</h2>
        <?php foreach ($events as $event): ?>
        <article class="l6-card"><strong><?= e($event['title'] ?? '') ?></strong><span><?= e(homepage_format_date($event['event_date'] ?? '')) ?></span></article>
        <?php endforeach; ?>
    </section>
    <section class="l6-panel" id="courses" hidden>
        <h2>Courses</h2>
        <?php foreach ($programmes as $row): ?>
        <article class="l6-card"><strong><?= e($row['name'] ?? '') ?></strong><span><?= e(homepage_clip((string)($row['description'] ?? 'Class'), 100)) ?></span></article>
        <?php endforeach; ?>
    </section>
    <section class="l6-panel" id="timetable" hidden>
        <h2>Timetable</h2>
        <div class="l6-week">
            <a href="<?= e(homepage_url(['timetable_week' => (int)$hp['weekOffset'] - 1], 'timetable')) ?>">Prev</a>
            <a href="<?= e(homepage_url(['timetable_week' => 0], 'timetable')) ?>">This week</a>
            <a href="<?= e(homepage_url(['timetable_week' => (int)$hp['weekOffset'] + 1], 'timetable')) ?>">Next</a>
        </div>
        <?php foreach (array_slice($hp['upcomingLessons'] ?? [], 0, 10) as $lesson): ?>
        <article class="l6-card">
            <strong><?= e($lesson['subject_name'] ?? '') ?></strong>
            <span><?= e(homepage_format_date($lesson['date'] ?? '', 'D d M')) ?> · <?= e($lesson['teacher_name'] ?? '') ?></span>
        </article>
        <?php endforeach; ?>
    </section>
    <section class="l6-panel" id="teachers" hidden>
        <h2>Teachers</h2>
        <p class="l6-muted">Ranked by enrolled students and profile completion.</p>
        <?php foreach ($teachers as $teacher): ?>
        <a class="l6-card l6-person" href="<?= e($teacher['profile_url']) ?>">
            <?php homepage_teacher_avatar($teacher, 'l6-ava'); ?>
            <span><strong><?= e($teacher['name']) ?></strong><em><?= e(implode(', ', array_slice($teacher['subject_list'] ?? [], 0, 2))) ?></em><?php homepage_render_teacher_rank($teacher); ?></span>
        </a>
        <?php endforeach; ?>
    </section>
</main>
<nav class="l6-tabs">
    <a href="#home" data-l6-tab="home" class="is-on"><i class="fas fa-house"></i> Home</a>
    <a href="#timetable" data-l6-tab="timetable"><i class="fas fa-calendar"></i> Timetable</a>
    <button type="button" data-open-auth="login"><i class="fas fa-table-cells-large"></i> Portal</button>
    <button type="button" data-open-auth="login"><i class="fas fa-user"></i> Profile</button>
</nav>
</div>
<?php
homepage_render_auth('modal');
homepage_render_site_footer($hp);
homepage_render_foot($activeLayout);
