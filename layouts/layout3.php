<?php
homepage_render_head($activeLayout);
$teachers = $hp['teachers'] ?? [];
$weekly = $hp['weeklyTimetable'] ?? [];
$section = (string)($activeSection ?? 'home');
$institute = (string)($hp['institute'] ?? 'Edexcel College');
$teacherCount = count($teachers);
$seoBase = rtrim((string)(defined('BASE_URL') ? BASE_URL : '/'), '/');
$faqs = function_exists('seo_homepage_faqs')
    ? seo_homepage_faqs(($GLOBALS['pdo'] ?? null) instanceof PDO ? $GLOBALS['pdo'] : null)
    : [];
$why = [
    ['icon' => '🌍', 'title' => 'Worldwide Online Classes', 'text' => 'Around 90% of classes are live online, so students can join from outside Sri Lanka as well as from home in Sri Lanka.'],
    ['icon' => '📚', 'title' => 'Edexcel-Focused Teaching', 'text' => 'Lessons aligned with the Pearson Edexcel syllabus and examination requirements.'],
    ['icon' => '🎯', 'title' => 'Exam-Focused Preparation', 'text' => 'Build understanding while learning how to score marks effectively in Pearson examinations.'],
    ['icon' => '📝', 'title' => 'Past Paper Practice', 'text' => 'Regular practice with past papers and exam-style questions for IGCSE and IAL.'],
    ['icon' => '👨‍🏫', 'title' => 'Individual Attention', 'text' => 'Students get opportunities to ask questions and receive personal guidance.'],
    ['icon' => '📈', 'title' => 'Progress Monitoring', 'text' => 'Track strengths, weaknesses and improvement throughout the course.'],
];
?>
<body class="hp hp-l3">
<?php homepage_render_preview_banner(); ?>
<main class="page-shell">

    <section class="app-section <?= $section === 'home' ? 'active-section' : '' ?>" id="home" <?= $section !== 'home' ? 'hidden' : '' ?>>
        <div class="section-header home-intro">
            <span class="eyebrow">Online worldwide · Physical campus in Kandy</span>
            <h1>Edexcel IGCSE &amp; IAL Classes — Online Worldwide</h1>
            <p>Live online Pearson Edexcel classes for students worldwide. Around 90% of classes are online. Physical classes are at the Kandy campus when scheduled.</p>
            <div class="l3-seo-links" style="display:flex;flex-wrap:wrap;gap:.6rem;margin:.85rem 0 0;">
                <a class="btn btn-primary" href="<?= e($seoBase . '/online-classes') ?>">Online classes</a>
                <a class="btn btn-outline" href="<?= e($seoBase . '/edexcel-o-level') ?>">IGCSE</a>
                <a class="btn btn-outline" href="<?= e($seoBase . '/edexcel-a-level') ?>">IAL</a>
                <a class="btn btn-outline" href="<?= e($seoBase . '/subjects') ?>">Subjects</a>
                <a class="btn btn-outline" href="<?= e($seoBase . '/teachers/') ?>">Teachers</a>
                <a class="btn btn-outline" href="<?= e($seoBase . '/locations/kandy') ?>">Physical classes in Kandy</a>
                <a class="btn btn-outline" href="<?= e($seoBase . '/admissions/enquire.php') ?>">Admissions</a>
                <a class="btn btn-outline" href="<?= e($seoBase . '/contact') ?>">Contact</a>
                <button type="button" class="btn btn-primary" data-open-auth="login">Login</button>
                <button type="button" class="btn btn-primary" data-open-auth="login">Login</button>
            </div>
        </div>
        <div class="metric-strip" aria-label="College overview">
            <article class="metric-card"><span>Enrolled Students</span><strong><?= homepage_stat($hp, 'students') ?></strong></article>
            <article class="metric-card"><span>Teachers</span><strong><?= homepage_stat($hp, 'teachers') ?></strong></article>
            <article class="metric-card"><span>Classes Today</span><strong><?= homepage_stat($hp, 'today') ?></strong></article>
        </div>
        <section class="why-choose-section">
            <div class="section-header why-intro"><span class="eyebrow">Why Choose Us?</span><h2>Why choose <?= e($institute) ?>?</h2></div>
            <div class="why-slider" aria-label="Why choose Edexcel College">
                <button type="button" class="why-slider-arrow why-slider-prev" aria-label="Previous reason"><i class="fas fa-chevron-left"></i></button>
                <div class="why-slider-viewport">
                    <div class="why-slider-track">
                        <?php foreach ($why as $item): ?>
                        <article class="why-slide">
                            <div class="why-slide-icon"><?= $item['icon'] ?></div>
                            <h3><?= e($item['title']) ?></h3>
                            <p><?= e($item['text']) ?></p>
                        </article>
                        <?php endforeach; ?>
                    </div>
                </div>
                <button type="button" class="why-slider-arrow why-slider-next" aria-label="Next reason"><i class="fas fa-chevron-right"></i></button>
            </div>
            <div class="why-slider-dots" role="tablist" aria-label="Why choose us slides"></div>
        </section>
        <?php if ($faqs !== []): ?>
        <section class="why-choose-section" id="faq" aria-label="Frequently asked questions">
            <div class="section-header why-intro">
                <span class="eyebrow">FAQ</span>
                <h2>Questions parents and students ask</h2>
            </div>
            <div class="section-card-grid">
                <?php foreach ($faqs as $faq): ?>
                <article class="info-card">
                    <h3><?= e($faq['question']) ?></h3>
                    <p><?= e($faq['answer']) ?></p>
                </article>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>
    </section>

    <section class="app-section <?= $section === 'teachers' ? 'active-section' : '' ?>" id="teachers" <?= $section !== 'teachers' ? 'hidden' : '' ?>>
        <div class="section-header teachers-intro">
            <span class="eyebrow">Academic Team</span>
            <div class="teacher-directory-count">
                <i class="fas fa-users"></i>
                <strong><?= (int)$teacherCount ?></strong>
                <span><?= $teacherCount === 1 ? 'Teacher' : 'Teachers' ?></span>
            </div>
        </div>
        <?php if ($teachers === []): ?>
        <div class="teacher-empty-state">
            <i class="fas fa-user-slash"></i>
            <h2>No teacher records available</h2>
        </div>
        <?php else: ?>
        <div class="teacher-profile-grid">
            <?php foreach ($teachers as $teacher): ?>
            <?php
                $teacherId = (int)($teacher['id'] ?? 0);
                $teacherName = (string)($teacher['name'] ?? 'Teacher');
                $subjects = $teacher['subject_list'] ?? [];
                $subjectLabel = (string)($teacher['subject_label'] ?? '');
                if ($subjectLabel === '') {
                    $subjectLabel = $subjects !== [] ? implode(' • ', $subjects) : 'Edexcel Teacher';
                }
                $photo = homepage_photo_url($teacher);
                $whatsapp = (string)($teacher['whatsapp'] ?? '');
                $profileUrl = (string)($teacher['profile_url'] ?? '');
            ?>
            <article class="teacher-profile-card" data-teacher-profile="<?= e($profileUrl) ?>" tabindex="0" role="link" aria-label="View <?= e($teacherName) ?> profile">
                <div class="teacher-avatar-ring" style="--avatar-accent: <?= e((string)($teacher['avatar_color'] ?? '#5161ce')) ?>;">
                    <?php if ($photo !== ''): ?>
                    <img src="<?= e($photo) ?>" alt="<?= e((string)($teacher['photo_alt'] ?? $teacherName)) ?>" class="teacher-avatar-image" loading="lazy">
                    <?php else: ?>
                    <span class="teacher-avatar-initials"><?= e((string)($teacher['initials'] ?? homepage_initials($teacherName))) ?></span>
                    <?php endif; ?>
                </div>
                <h2><?= e($teacherName) ?></h2>
                <p class="teacher-subject"><?= e($subjectLabel) ?></p>
                <?php homepage_render_teacher_rank($teacher); ?>
                <div class="teacher-profile-actions">
                    <a class="teacher-primary-action" href="<?= e($profileUrl) ?>"><i class="fas fa-user"></i><span>Profile</span></a>
                    <?php if ($whatsapp !== ''): ?>
                    <a class="teacher-ghost-action" href="<?= e($whatsapp) ?>" target="_blank" rel="noopener noreferrer"><i class="fab fa-whatsapp"></i><span>WhatsApp</span></a>
                    <?php endif; ?>
                </div>
            </article>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </section>

    <section class="app-section <?= $section === 'classes' ? 'active-section' : '' ?>" id="classes" <?= $section !== 'classes' ? 'hidden' : '' ?>>
        <div class="section-card-grid">
            <article class="info-card">
                <i class="fas fa-chalkboard-teacher"></i>
                <h2>Active Classes</h2>
                <strong class="info-card-number"><?= homepage_stat($hp, 'classes') ?></strong>
                <p>Classes currently available in the student class database.</p>
            </article>
            <article class="info-card">
                <i class="fas fa-calendar-day"></i>
                <h2>Classes Today</h2>
                <strong class="info-card-number"><?= homepage_stat($hp, 'today') ?></strong>
                <p>Timetable entries scheduled for today.</p>
            </article>
            <article class="info-card">
                <i class="fas fa-book-open"></i>
                <h2>Subjects</h2>
                <strong class="info-card-number"><?= homepage_stat($hp, 'subjects') ?></strong>
                <p>Active subjects available in the subject database.</p>
            </article>
        </div>
        <div class="weekly-timetable-header">
            <div>
                <h2 id="classesTitle">Class Schedule</h2>
                <?php if (!empty($hp['weekStart'])): ?>
                <p><?= e(homepage_format_date($hp['weekStart'], 'd M')) ?> – <?= e(homepage_format_date($hp['weekEnd'] ?? '', 'd M Y')) ?></p>
                <?php endif; ?>
            </div>
            <div class="weekly-timetable-controls">
                <a href="<?= e(homepage_url(['timetable_week' => (int)$hp['weekOffset'] - 1], 'classes')) ?>" class="weekly-week-button" aria-label="Previous week"><i class="fas fa-chevron-left"></i></a>
                <a href="<?= e(homepage_url(['timetable_week' => 0], 'classes')) ?>" class="weekly-today-button">This Week</a>
                <a href="<?= e(homepage_url(['timetable_week' => (int)$hp['weekOffset'] + 1], 'classes')) ?>" class="weekly-week-button" aria-label="Next week"><i class="fas fa-chevron-right"></i></a>
            </div>
        </div>
        <div class="weekly-timetable">
            <?php foreach ($weekly as $date => $classes): ?>
            <?php
                $ts = strtotime((string)$date);
                $isToday = $date === date('Y-m-d');
            ?>
            <section class="timetable-day<?= $isToday ? ' is-today' : '' ?>">
                <div class="timetable-day-header">
                    <div>
                        <h2><?= e($ts ? date('l', $ts) : '') ?> <span><?= e($ts ? date('d M', $ts) : '') ?></span><?php if ($isToday): ?> <small>Today</small><?php endif; ?></h2>
                    </div>
                    <div class="timetable-day-count"><?= count($classes) ?> <?= count($classes) === 1 ? 'class' : 'classes' ?></div>
                </div>
                <?php if ($classes === []): ?>
                <div class="timetable-empty-day"><i class="fas fa-calendar-check"></i><span>No classes scheduled</span></div>
                <?php else: ?>
                <div class="timetable-class-list">
                    <?php foreach ($classes as $class): ?>
                    <?php $accentIndex = ((int)($class['id'] ?? 0)) % 5; ?>
                    <article class="timetable-class-card timetable-accent-<?= $accentIndex ?>">
                        <div class="timetable-class-time">
                            <strong><?= e(homepage_format_time($class['start_time'] ?? '')) ?></strong>
                            <span>– <?= e(homepage_format_time($class['end_time'] ?? '')) ?></span>
                            <div class="timetable-time-line"></div>
                        </div>
                        <div class="timetable-class-main">
                            <h3><?= e($class['class_name'] ?? '') ?></h3>
                            <p class="timetable-subject"><i class="fas fa-book-open"></i> <?= e($class['subject_name'] ?? '') ?></p>
                            <p class="timetable-teacher-mobile"><i class="fas fa-user-tie"></i> <?= e($class['teacher_name'] ?? '') ?></p>
                        </div>
                        <div class="timetable-class-actions">
                            <span class="timetable-teacher"><i class="fas fa-user-tie"></i> <?= e($class['teacher_name'] ?? '') ?></span>
                            <span class="timetable-room"><i class="fas fa-door-open"></i> <?= e($class['room_name'] ?? '') ?></span>
                        </div>
                    </article>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </section>
            <?php endforeach; ?>
        </div>
    </section>

    <?php homepage_render_auth('inline'); ?>
</main>
<?php homepage_render_site_footer($hp); ?>

<nav class="bottom-navbar" aria-label="Primary bottom navigation">
    <div class="bottom-navbar-menu" id="bottomNavbarMenu">
        <span class="nav-selector" aria-hidden="true"></span>
        <ul class="bottom-nav-list">
            <li class="bottom-nav-item <?= $section === 'home' ? 'active' : '' ?>">
                <a class="bottom-nav-link" href="#home" data-target="home"><i class="fas fa-house"></i><span>Home</span></a>
            </li>
            <li class="bottom-nav-item <?= $section === 'teachers' ? 'active' : '' ?>">
                <a class="bottom-nav-link" href="#teachers" data-target="teachers"><i class="fas fa-chalkboard-user"></i><span>Teachers</span></a>
            </li>
            <li class="bottom-nav-item <?= $section === 'classes' ? 'active' : '' ?>">
                <a class="bottom-nav-link" href="#classes" data-target="classes"><i class="fas fa-book-open"></i><span>Classes</span></a>
            </li>
            <li class="bottom-nav-item">
                <a class="bottom-nav-link" href="<?= e($seoBase . '/edexcel-classes') ?>"><i class="fas fa-graduation-cap"></i><span>Programmes</span></a>
            </li>
            <li class="bottom-nav-item">
                <button class="bottom-nav-link" type="button" data-open-auth="login" title="Login"><i class="fas fa-user"></i><span>Login</span></button>
            </li>
        </ul>
    </div>
</nav>
<?php homepage_render_foot($activeLayout);