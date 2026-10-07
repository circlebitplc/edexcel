<?php
homepage_render_head($activeLayout);
$teachers = $hp['teachers'] ?? [];
$today = $hp['todayLessons'] ?? [];
$events = array_slice($hp['events'] ?? [], 0, 5);
$announcements = array_slice($hp['announcements'] ?? [], 0, 4);
$upcoming = array_slice($hp['upcomingLessons'] ?? [], 0, 6);
$institute = (string)($hp['institute'] ?? 'Edexcel College');
$cal = homepage_month_cells();
$apply = rtrim((string)BASE_URL, '/') . '/student/register.php';
$initials = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $institute) ?: 'EC', 0, 2));
?>
<body class="hp hp-l2">
<?php homepage_render_preview_banner(); ?>
<div class="l2-shell">
    <aside class="l2-side">
        <a class="l2-logo" href="<?= e(homepage_url()) ?>"><?= e($initials) ?></a>
        <p class="l2-side-name"><?= e($institute) ?></p>
        <nav>
            <a class="is-on" href="#overview"><i class="fas fa-border-all"></i> Dashboard</a>
            <a href="#programmes"><i class="fas fa-book-open"></i> Programmes</a>
            <a href="#timetable"><i class="fas fa-calendar"></i> Timetable</a>
            <a href="#teachers"><i class="fas fa-chalkboard-user"></i> Teachers</a>
            <button type="button" data-open-auth="login"><i class="fas fa-user-graduate"></i> Students</button>
            <a href="#announcements"><i class="fas fa-bullhorn"></i> Announcements</a>
            <a href="#events"><i class="fas fa-star"></i> Events</a>
            <a href="<?= e($apply) ?>"><i class="fas fa-file-signature"></i> Admissions</a>
            <button type="button" data-open-auth="login"><i class="fas fa-user-tie"></i> Contact</button>
        </nav>
    </aside>
    <div class="l2-main">
        <header class="l2-top">
            <label class="l2-search">
                <i class="fas fa-magnifying-glass"></i>
                <input type="search" placeholder="Search teachers, classes..." data-l2-search aria-label="Search">
            </label>
            <div class="l2-top-right">
                <button type="button" class="l2-bell" aria-label="Notifications"><i class="fas fa-bell"></i></button>
                <button type="button" class="l2-avatar" data-open-auth="login" aria-label="Student profile"><?= e($initials) ?></button>
            </div>
        </header>

        <p class="l2-hello">Welcome back, Student!</p>
        <h1 id="overview"><?= e($institute) ?> dashboard</h1>
        <p>Around 90% of classes are live online for students worldwide. Physical classes are at the Kandy campus when scheduled.</p>

        <section class="l2-kpis">
            <article>
                <span>Active Students</span>
                <strong data-count="<?= homepage_stat($hp, 'students') ?>">0</strong>
                <div class="l2-bar"><b style="width: <?= min(100, homepage_stat($hp, 'students')) ?>%"></b></div>
            </article>
            <article>
                <span>Classes Today</span>
                <strong data-count="<?= homepage_stat($hp, 'today') ?>">0</strong>
                <div class="l2-bar"><b style="width: <?= min(100, homepage_stat($hp, 'today') * 8) ?>%"></b></div>
            </article>
            <article>
                <span>Attendance</span>
                <strong data-count="<?= homepage_stat($hp, 'attendance') ?>">0</strong>%
                <div class="l2-bar"><b style="width: <?= homepage_stat($hp, 'attendance') ?>%"></b></div>
            </article>
        </section>

        <div class="l2-grid">
            <section class="l2-card" id="today">
                <h2>Today's Classes</h2>
                <?php if ($today === []): ?><p class="l2-empty">No lessons on the timetable for today.</p><?php endif; ?>
                <ul class="l2-table">
                    <?php foreach ($today as $lesson): ?>
                    <li>
                        <b><?= e(homepage_format_time($lesson['start_time'] ?? '')) ?></b>
                        <span><?= e($lesson['subject_name'] ?? '') ?> · <?= e($lesson['class_name'] ?? '') ?></span>
                        <em><?= e($lesson['room_name'] ?? '') ?></em>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </section>
            <section class="l2-card" id="announcements">
                <h2>Announcements</h2>
                <?php foreach ($announcements as $row): ?>
                <article class="l2-note">
                    <time><?= e(homepage_format_date($row['event_date'] ?? '')) ?></time>
                    <strong><?= e($row['title'] ?? '') ?></strong>
                </article>
                <?php endforeach; ?>
                <?php if ($announcements === []): ?><p class="l2-empty">No announcements yet.</p><?php endif; ?>
            </section>
            <section class="l2-card" id="cal">
                <h2><?= e($cal['label']) ?></h2>
                <div class="l2-cal-head"><span>M</span><span>T</span><span>W</span><span>T</span><span>F</span><span>S</span><span>S</span></div>
                <div class="l2-cal">
                    <?php foreach ($cal['cells'] as $day): ?>
                        <?php if ($day < 1): ?><span></span>
                        <?php else: ?><span class="<?= $day === $cal['today'] ? 'is-today' : '' ?>"><?= (int)$day ?></span>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </section>
            <section class="l2-card l2-span" id="quick">
                <h2>Quick Links</h2>
                <div class="l2-quick">
                    <a href="<?= e($apply) ?>"><i class="fas fa-file-signature"></i> Admissions</a>
                    <a href="#timetable"><i class="fas fa-calendar"></i> Timetable</a>
                    <a href="#teachers"><i class="fas fa-chalkboard-user"></i> Teachers</a>
                    <button type="button" data-open-auth="login"><i class="fas fa-right-to-bracket"></i> Login</button>
                </div>
            </section>
            <section class="l2-card l2-span" id="timetable">
                <h2>Timetable preview</h2>
                <div class="l2-week">
                    <a href="<?= e(homepage_url(['timetable_week' => (int)$hp['weekOffset'] - 1], 'timetable')) ?>">Prev</a>
                    <a href="<?= e(homepage_url(['timetable_week' => 0], 'timetable')) ?>">This week</a>
                    <a href="<?= e(homepage_url(['timetable_week' => (int)$hp['weekOffset'] + 1], 'timetable')) ?>">Next</a>
                </div>
                <ul class="l2-table">
                    <?php foreach ($upcoming as $lesson): ?>
                    <li>
                        <b><?= e(homepage_format_date($lesson['date'] ?? '', 'D')) ?></b>
                        <span><?= e($lesson['subject_name'] ?? '') ?> · <?= e($lesson['teacher_name'] ?? '') ?></span>
                        <em><?= e(homepage_format_time($lesson['start_time'] ?? '')) ?></em>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </section>
            <section class="l2-card" id="events">
                <h2>Events</h2>
                <?php foreach ($events as $event): ?>
                <p><b><?= e(homepage_format_date($event['event_date'] ?? '', 'd M')) ?></b> <?= e($event['title'] ?? '') ?></p>
                <?php endforeach; ?>
            </section>
            <section class="l2-card" id="programmes">
                <h2>Programmes</h2>
                <ul>
                    <?php foreach (array_slice($hp['programmes'] ?? [], 0, 6) as $row): ?>
                    <li><?= e($row['name'] ?? '') ?></li>
                    <?php endforeach; ?>
                </ul>
            </section>
            <section class="l2-card l2-span" id="teachers">
                <h2>Teachers</h2>
                <div class="l2-people" data-l2-people>
                    <?php foreach ($teachers as $teacher): ?>
                    <a href="<?= e($teacher['profile_url']) ?>" data-l2-name="<?= e(strtolower($teacher['name'])) ?>">
                        <?php homepage_teacher_avatar($teacher, 'l2-ava'); ?>
                        <span><?= e($teacher['name']) ?><?php if (!empty($teacher['subject_label'])): ?> <small><?= e((string)$teacher['subject_label']) ?></small><?php endif; ?><?php homepage_render_teacher_rank($teacher); ?></span>
                    </a>
                    <?php endforeach; ?>
                </div>
            </section>
        </div>
        <?php homepage_render_site_footer($hp); ?>
    </div>
</div>
<?php
homepage_render_auth('modal');
homepage_render_foot($activeLayout);
