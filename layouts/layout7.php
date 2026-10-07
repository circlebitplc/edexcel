<?php
homepage_render_head($activeLayout);
$teachers = $hp['teachers'] ?? [];
$achievements = array_slice($hp['achievements'] ?? [], 0, 4);
$testimonials = $hp['testimonials'] ?? [];
$quote = $testimonials[0] ?? ['quote' => 'Teachers explain Pearson papers clearly.', 'name' => 'Current student', 'role' => 'IGCSE'];
$institute = (string)($hp['institute'] ?? 'Edexcel College');
$apply = rtrim((string)BASE_URL, '/') . '/student/register.php';
?>
<body class="hp hp-l7">
<?php homepage_render_preview_banner(); ?>
<header class="l7-nav">
    <span><?= e($institute) ?></span>
    <nav>
        <a href="#results">Results</a>
        <a href="#teachers">Teachers</a>
        <a href="#stories">Stories</a>
        <a href="<?= e($apply) ?>">Apply</a>
    </nav>
</header>
<section class="l7-hero">
    <div class="l7-trophy" aria-hidden="true">
        <svg viewBox="0 0 120 140" width="160" height="180">
            <defs>
                <linearGradient id="gold" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0" stop-color="#fde68a"/><stop offset="1" stop-color="#d97706"/>
                </linearGradient>
            </defs>
            <path d="M28 28 h64 v18 c0 22-14 40-32 48 C42 86 28 68 28 46z" fill="url(#gold)"/>
            <rect x="52" y="92" width="16" height="18" fill="#fbbf24"/>
            <rect x="36" y="110" width="48" height="10" rx="3" fill="#f59e0b"/>
            <path d="M28 36 c-16 8-18 28-6 36" fill="none" stroke="#fbbf24" stroke-width="8"/>
            <path d="M92 36 c16 8 18 28 6 36" fill="none" stroke="#fbbf24" stroke-width="8"/>
        </svg>
    </div>
    <p>For parents and students</p>
    <h1>Building Future Achievers.</h1>
    <p class="l7-sub">Live online Pearson Edexcel IGCSE and IAL classes for students worldwide. Physical classes are at the Kandy campus when scheduled.</p>
</section>
<section class="l7-stats" id="results">
    <article><strong data-count="<?= homepage_stat($hp, 'success_rate') ?>">0</strong><span>Student success %</span></article>
    <article><strong data-count="<?= homepage_stat($hp, 'years') ?>">0</strong><span>Years of experience</span></article>
    <article><strong data-count="<?= homepage_stat($hp, 'students') ?>">0</strong><span>Active students</span></article>
    <article><strong data-count="<?= homepage_stat($hp, 'teachers') ?>">0</strong><span>Expert teachers</span></article>
</section>
<section class="l7-block" id="awards">
    <h2>How we teach</h2>
    <div class="l7-gold">
        <article><i class="fas fa-medal"></i><h3>Exam-focused teaching</h3><p>Syllabus topics and past-paper practice inside live classes.</p></article>
        <article><i class="fas fa-university"></i><h3>University preparation</h3><p>IAL teaching aimed at the next step after school.</p></article>
        <article><i class="fas fa-chalkboard-user"></i><h3>Subject teachers</h3><p>Named on the public teacher directory.</p></article>
        <article><i class="fas fa-star"></i><h3>How classes run</h3>
            <?php if ($achievements): ?><p><?= e($achievements[0]['title'] ?? '') ?></p>
            <?php else: ?><p>Live online worldwide. Physical classes at the Kandy campus when scheduled.</p><?php endif; ?>
        </article>
    </div>
</section>
<section class="l7-block" id="teachers">
    <h2>Teacher experience</h2>
    <div class="l7-teachers">
        <?php foreach ($teachers as $teacher): ?>
        <article>
            <a href="<?= e($teacher['profile_url']) ?>">
            <?php homepage_teacher_avatar($teacher, 'l7-photo'); ?>
            <h3><?= e($teacher['name']) ?></h3>
            <p><?= e((string)($teacher['subject_label'] ?? '')) ?: (!empty($teacher['experience_years']) ? (int)$teacher['experience_years'] . ' years' : 'Specialist tutor') ?></p>
            <?php homepage_render_teacher_rank($teacher); ?>
            </a>
        </article>
        <?php endforeach; ?>
    </div>
</section>
<section class="l7-block" id="stories">
    <h2>What Our Students Say</h2>
    <blockquote class="l7-quote">
        <img src="<?= e(homepage_stock('hero-student')) ?>" alt="" width="72" height="72">
        <p><?= e($quote['quote']) ?></p>
        <cite><?= e($quote['name']) ?> · <?= e($quote['role']) ?></cite>
    </blockquote>
    <div class="l7-cta">
        <a href="<?= e($apply) ?>">Begin admissions</a>
        <button type="button" data-open-auth="login">Login</button>
    </div>
</section>
<?php
homepage_render_auth('modal');
homepage_render_site_footer($hp);
homepage_render_foot($activeLayout);
