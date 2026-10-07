<?php
homepage_render_head($activeLayout);
$teachers = $hp['teachers'] ?? [];
$why = homepage_why_choose();
$institute = (string)($hp['institute'] ?? 'Edexcel College');
$video = HOMEPAGE_ROOT . '/assets/video/campus.mp4';
$hasVideo = is_file($video);
$apply = rtrim((string)BASE_URL, '/') . '/student/register.php';
?>
<body class="hp hp-l8">
<?php homepage_render_preview_banner(); ?>
<header class="l8-bar">
    <strong><?= e($institute) ?></strong>
    <nav>
        <a href="#why">Why us</a>
        <a href="#teachers">Teachers</a>
        <a href="<?= e($apply) ?>">Apply Now</a>
    </nav>
</header>
<section class="l8-hero" id="hero">
    <?php if ($hasVideo): ?>
    <video class="l8-media" autoplay muted loop playsinline id="l8-video">
        <source src="<?= e(rtrim(BASE_URL, '/') . '/assets/video/campus.mp4') ?>" type="video/mp4">
    </video>
    <?php else: ?>
    <img class="l8-media" src="<?= e(homepage_stock('students')) ?>" alt="Students on campus">
    <?php endif; ?>
    <div class="l8-overlay"></div>
    <div class="l8-copy">
        <p><?= e($institute) ?></p>
        <h1>Empowering Future Leaders</h1>
        <p>Live online IGCSE and IAL classes worldwide. Physical classes at the Kandy campus when scheduled.</p>
        <button type="button" class="l8-play" data-l8-play aria-label="Play campus film"><i class="fas fa-play"></i></button>
        <a class="l8-apply" href="<?= e($apply) ?>">Apply Now</a>
    </div>
</section>
<section class="l8-white" id="why">
    <h2>Why Choose Us</h2>
    <div class="l8-why">
        <?php foreach ($why as $item): ?>
        <article>
            <i class="fas <?= e($item['icon']) ?>"></i>
            <h3><?= e($item['title']) ?></h3>
            <p><?= e($item['text']) ?></p>
        </article>
        <?php endforeach; ?>
    </div>
    <div class="l8-stats">
        <span><strong data-count="<?= homepage_stat($hp, 'students') ?>">0</strong> Active students</span>
        <span><strong data-count="<?= homepage_stat($hp, 'today') ?>">0</strong> Classes today</span>
        <span><strong data-count="<?= homepage_stat($hp, 'attendance') ?>">0</strong>% Attendance</span>
        <span><strong data-count="<?= homepage_stat($hp, 'teachers') ?>">0</strong> Teachers</span>
    </div>
</section>
<section class="l8-white l8-alt" id="teachers">
    <h2>Our Teachers</h2>
    <div class="l8-cast">
        <?php foreach ($teachers as $teacher): ?>
        <a href="<?= e($teacher['profile_url']) ?>">
            <?php homepage_teacher_avatar($teacher, 'l8-ava'); ?>
            <span><?= e($teacher['name']) ?><?php homepage_render_teacher_rank($teacher); ?></span>
        </a>
        <?php endforeach; ?>
    </div>
    <div class="l8-cta">
        <button type="button" data-open-auth="login">Login</button>
    </div>
</section>
<?php
homepage_render_auth('modal');
homepage_render_site_footer($hp);
homepage_render_foot($activeLayout);
