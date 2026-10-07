<?php
homepage_render_head($activeLayout);
$news = $hp['news'] ?? [];
$featured = $news[0] ?? null;
$rest = array_slice($news, 1, 6);
$sideNews = array_slice($news, 0, 5);
$teachers = $hp['teachers'] ?? [];
$today = array_slice($hp['todayLessons'] ?? [], 0, 5);
$events = array_slice($hp['events'] ?? [], 0, 5);
$institute = (string)($hp['institute'] ?? 'Edexcel College');
$thumbs = ['news', 'classroom', 'lab', 'campus'];
?>
<body class="hp hp-l9">
<?php homepage_render_preview_banner(); ?>
<header class="l9-mast">
    <p class="l9-date"><?= e(date('l, d F Y')) ?></p>
    <h1><?= e($institute) ?> Gazette</h1>
    <p>Live online IGCSE and IAL classes worldwide, with physical classes at the Kandy campus when scheduled.</p>
    <nav>
        <a href="#featured">Top story</a>
        <a href="#news">News</a>
        <a href="#events">Events</a>
        <a href="#teachers">Teachers</a>
        <button type="button" data-open-auth="login">Login</button>
    </nav>
</header>
<div class="l9-wrap">
    <main>
        <article class="l9-feature" id="featured">
            <img src="<?= e(homepage_stock('news')) ?>" alt="">
            <div>
                <p class="l9-cat">Featured</p>
                <?php if ($featured): ?>
                <h2><?= e($featured['title'] ?? 'Campus news') ?></h2>
                <p><?= e(homepage_clip((string)($featured['description'] ?? 'Latest from the college.'), 280)) ?></p>
                <time><?= e(homepage_format_date($featured['event_date'] ?? '')) ?></time>
                <?php else: ?>
                <h2>Admissions Open For <?= e(date('Y')) ?> Intake</h2>
                <p>Register for the student portal to join IGCSE and IAL classes at Edexcel College.</p>
                <?php endif; ?>
            </div>
        </article>
        <section class="l9-grid" id="news">
            <?php foreach (array_slice($rest, 0, 3) as $i => $item): ?>
            <article>
                <img src="<?= e(homepage_stock($thumbs[$i % count($thumbs)])) ?>" alt="">
                <p class="l9-cat">News</p>
                <h3><?= e($item['title'] ?? '') ?></h3>
                <p><?= e(homepage_clip((string)($item['description'] ?? ''), 100)) ?></p>
            </article>
            <?php endforeach; ?>
        </section>
    </main>
    <aside>
        <section>
            <h2>Latest News</h2>
            <?php foreach ($sideNews as $item): ?>
            <a class="l9-side" href="#news">
                <img src="<?= e(homepage_stock('classroom')) ?>" alt="">
                <span><?= e($item['title'] ?? '') ?><small><?= e(homepage_format_date($item['event_date'] ?? '')) ?></small></span>
            </a>
            <?php endforeach; ?>
            <?php if ($sideNews === []): ?><p>News published by staff appears here.</p><?php endif; ?>
        </section>
        <section id="events">
            <h2>Upcoming Events</h2>
            <?php foreach ($events as $event): ?>
            <p class="l9-event">
                <b><?= e(homepage_format_date($event['event_date'] ?? '', 'd')) ?><small><?= e(homepage_format_date($event['event_date'] ?? '', 'M')) ?></small></b>
                <?= e($event['title'] ?? '') ?>
            </p>
            <?php endforeach; ?>
        </section>
        <section>
            <h2>Class announcements</h2>
            <?php foreach ($today as $lesson): ?>
            <p><?= e(homepage_format_time($lesson['start_time'] ?? '')) ?> <?= e($lesson['class_name'] ?? '') ?></p>
            <?php endforeach; ?>
        </section>
        <section id="teachers">
            <h2>Teachers</h2>
            <?php foreach ($teachers as $teacher): ?>
            <a class="l9-spot" href="<?= e($teacher['profile_url']) ?>">
                <?php homepage_teacher_avatar($teacher, 'l9-ava'); ?>
                <span><?= e($teacher['name']) ?><small><?= e(implode(', ', array_slice($teacher['subject_list'] ?? [], 0, 2))) ?></small><?php homepage_render_teacher_rank($teacher); ?></span>
            </a>
            <?php endforeach; ?>
        </section>
        <p><a href="<?= e(rtrim(BASE_URL, '/') . '/student/register.php') ?>">Admissions desk →</a></p>
    </aside>
</div>
<?php
homepage_render_auth('modal');
homepage_render_site_footer($hp);
homepage_render_foot($activeLayout);
