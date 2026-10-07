<?php
homepage_render_head($activeLayout);
$teachers = $hp['teachers'] ?? [];
$programmes = array_slice($hp['programmes'] ?? [], 0, 4);
$events = array_slice($hp['news'] ?? [], 0, 3);
$lessons = array_slice($hp['upcomingLessons'] ?? [], 0, 4);
$achievements = array_slice($hp['achievements'] ?? [], 0, 3);
$institute = (string)($hp['institute'] ?? 'Edexcel College');
$apply = rtrim((string)BASE_URL, '/') . '/student/register.php';
?>
<body class="hp hp-l4">
<?php homepage_render_preview_banner(); ?>
<header class="l4-bar">
    <strong><?= e($institute) ?></strong>
    <nav>
        <button type="button" data-open-auth="login">Login</button>
        <a href="<?= e($apply) ?>">Apply Now</a>
    </nav>
</header>
<p class="l4-title">Everything You Need In One Place</p>
<main class="l4-bento">
    <article class="l4-tile l4-hero">
        <p>Edexcel College</p>
        <h1><?= homepage_stat($hp, 'students') ?>+ students · live timetable · digital portal</h1>
        <p>Live online classes worldwide. Physical classes at the Kandy campus when scheduled.</p>
        <p><?= homepage_stat($hp, 'today') ?> classes today · <?= homepage_stat($hp, 'attendance') ?>% attendance</p>
    </article>
    <article class="l4-tile l4-purple" id="programmes">
        <h2>Programmes</h2>
        <p>IGCSE, AL, &amp; More.</p>
        <ul><?php foreach ($programmes as $row): ?><li><?= e($row['name'] ?? '') ?></li><?php endforeach; ?></ul>
    </article>
    <article class="l4-tile l4-navy" id="teachers">
        <h2>Teachers</h2>
        <p>Expert &amp; Qualified.</p>
        <div class="l4-people">
            <?php foreach ($teachers as $teacher): ?>
            <a href="<?= e($teacher['profile_url']) ?>">
                <?php homepage_teacher_avatar($teacher, 'l4-ava'); ?>
                <span><?= e($teacher['name']) ?><?php homepage_render_teacher_rank($teacher); ?></span>
            </a>
            <?php endforeach; ?>
        </div>
    </article>
    <article class="l4-tile l4-green" id="timetable">
        <h2>Timetable</h2>
        <p>Today's Classes.</p>
        <?php foreach ($lessons as $lesson): ?>
        <p><b><?= e(homepage_format_date($lesson['date'] ?? '', 'D')) ?></b> <?= e($lesson['subject_name'] ?? '') ?></p>
        <?php endforeach; ?>
    </article>
    <a class="l4-tile l4-yellow" href="<?= e(rtrim(BASE_URL, '/') . '/student/dashboard.php') ?>">
        <h2>Student Portal</h2>
        <p>Access your account.</p>
    </a>
    <a class="l4-tile l4-red" href="<?= e($apply) ?>">
        <h2>Admissions</h2>
        <p>Apply for <?= e(date('Y')) ?>.</p>
    </a>
    <article class="l4-tile l4-pink">
        <h2>Achievements</h2>
        <p>Our Success.</p>
        <?php foreach ($achievements as $item): ?><p><?= e($item['title']) ?></p><?php endforeach; ?>
    </article>
    <article class="l4-tile l4-wide l4-teal" id="news">
        <h2>News &amp; Events</h2>
        <?php foreach ($events as $event): ?>
        <p><time><?= e(homepage_format_date($event['event_date'] ?? '')) ?></time> <?= e($event['title'] ?? '') ?></p>
        <?php endforeach; ?>
    </article>
    <a class="l4-tile l4-cyan" href="<?= e(rtrim((string)BASE_URL, '/') . '/contact.php') ?>">
        <h2>Contact Us</h2>
        <p>Email, phone, and campus address.</p>
    </a>
</main>
<?php
homepage_render_auth('modal');
homepage_render_site_footer($hp);
homepage_render_foot($activeLayout);
