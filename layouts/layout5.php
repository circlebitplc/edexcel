<?php
homepage_render_head($activeLayout);
$qNames = array_map(static fn($q) => strtoupper((string)($q['name'] ?? $q['code'] ?? '')), $hp['qualifications'] ?? []);
$hasIgcse = $qNames === [] || (bool)array_filter($qNames, static fn($n) => str_contains($n, 'IGCSE') || str_contains($n, 'GCSE'));
$hasIal = $qNames === [] || (bool)array_filter($qNames, static fn($n) => str_contains($n, 'IAL') || str_contains($n, 'A LEVEL') || str_contains($n, 'AL'));
$events = array_slice($hp['events'] ?? [], 0, 4);
$institute = (string)($hp['institute'] ?? 'Edexcel College');
$apply = rtrim((string)BASE_URL, '/') . '/student/register.php';
?>
<body class="hp hp-l5">
<?php homepage_render_preview_banner(); ?>
<header class="l5-top">
    <strong><?= e($institute) ?> Digital Campus</strong>
    <div>
        <button type="button" data-open-auth="login">Login</button>
    </div>
</header>
<h1 class="l5-hint">Edexcel IGCSE and IAL classes — online worldwide</h1>
<p class="l5-hint">Around 90% of classes are live online. Physical classes are at the Kandy campus when scheduled.</p>
<div class="l5-map" id="campus-map">
    <svg viewBox="0 0 1000 640" role="img" aria-label="Digital campus map">
        <defs>
            <linearGradient id="sky" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0" stop-color="#7dd3fc"/><stop offset="1" stop-color="#bbf7d0"/>
            </linearGradient>
        </defs>
        <rect width="1000" height="640" rx="28" fill="url(#sky)"/>
        <ellipse cx="500" cy="520" rx="420" ry="90" fill="#86efac"/>
        <ellipse cx="500" cy="500" rx="380" ry="70" fill="#4ade80"/>
        <path d="M80 470 L920 470 L860 540 L140 540 Z" fill="#166534" opacity=".35"/>
        <g class="l5-bldg" data-campus="igcse" transform="translate(160,240)">
            <polygon points="0,80 90,40 180,80 90,120" fill="#fde68a"/>
            <polygon points="0,80 0,160 90,200 90,120" fill="#f59e0b"/>
            <polygon points="90,120 90,200 180,160 180,80" fill="#d97706"/>
            <rect x="70" y="130" width="28" height="50" fill="#7c2d12"/>
        </g>
        <g class="l5-bldg" data-campus="ial" transform="translate(420,160)">
            <polygon points="0,90 110,30 220,90 110,150" fill="#93c5fd"/>
            <polygon points="0,90 0,180 110,240 110,150" fill="#3b82f6"/>
            <polygon points="110,150 110,240 220,180 220,90" fill="#1d4ed8"/>
        </g>
        <g class="l5-bldg" data-campus="teachers" transform="translate(700,180)">
            <polygon points="0,70 80,30 160,70 80,110" fill="#bbf7d0"/>
            <polygon points="0,70 0,150 80,190 80,110" fill="#22c55e"/>
            <polygon points="80,110 80,190 160,150 160,70" fill="#15803d"/>
        </g>
        <g class="l5-bldg" data-campus="timetable" transform="translate(120,390)">
            <rect width="200" height="90" rx="16" fill="#67e8f9"/>
        </g>
        <g class="l5-bldg" data-campus="admissions" transform="translate(390,400)">
            <rect width="190" height="90" rx="16" fill="#fda4af"/>
        </g>
        <g class="l5-bldg" data-campus="portal" transform="translate(620,400)">
            <rect width="160" height="90" rx="16" fill="#c4b5fd"/>
        </g>
        <g class="l5-bldg" data-campus="events" transform="translate(810,400)">
            <rect width="140" height="90" rx="16" fill="#f9a8d4"/>
        </g>
        <text x="250" y="228" text-anchor="middle" class="l5-label">IGCSE</text>
        <text x="530" y="155" text-anchor="middle" class="l5-label">IAL</text>
        <text x="780" y="175" text-anchor="middle" class="l5-label">Teachers</text>
        <text x="220" y="445" text-anchor="middle" class="l5-label">Timetable</text>
        <text x="485" y="455" text-anchor="middle" class="l5-label">Admissions</text>
        <text x="700" y="455" text-anchor="middle" class="l5-label">Portal</text>
        <text x="880" y="455" text-anchor="middle" class="l5-label">Events</text>
    </svg>
    <div class="l5-pins">
        <button type="button" class="l5-pin" data-campus="igcse" style="left:22%;top:28%">Explore</button>
        <button type="button" class="l5-pin" data-campus="ial" style="left:48%;top:18%">Explore</button>
        <button type="button" class="l5-pin" data-campus="teachers" style="left:74%;top:22%">Explore</button>
        <button type="button" class="l5-pin" data-campus="admissions" style="left:48%;top:68%">Explore</button>
        <button type="button" class="l5-pin" data-campus="portal" style="left:68%;top:68%">Explore</button>
    </div>
</div>
<div class="l5-stats">
    <span><strong data-count="<?= homepage_stat($hp, 'students') ?>">0</strong> students</span>
    <span><strong data-count="<?= homepage_stat($hp, 'teachers') ?>">0</strong> teachers</span>
    <span><strong data-count="<?= homepage_stat($hp, 'today') ?>">0</strong> classes today</span>
</div>
<div class="l5-panel" id="campus-panel" hidden>
    <button type="button" data-close-campus>&times;</button>
    <div data-campus-body></div>
</div>
<template id="campus-igcse">
    <h2>IGCSE Building</h2>
    <p><?= $hasIgcse ? 'International GCSE classes from the live programme list.' : 'IGCSE teaching at Edexcel College.' ?></p>
    <ul><?php foreach (array_slice($hp['programmes'] ?? [], 0, 6) as $row): ?><li><?= e($row['name'] ?? '') ?></li><?php endforeach; ?></ul>
</template>
<template id="campus-ial">
    <h2>IAL Building</h2>
    <p><?= $hasIal ? 'IAL groups currently on the college timetable.' : 'IAL pathway at Edexcel College.' ?></p>
</template>
<template id="campus-teachers">
    <h2>Teachers Centre</h2>
    <?php foreach ($hp['teachers'] ?? [] as $teacher): ?>
    <p><a href="<?= e($teacher['profile_url']) ?>"><?= e($teacher['name']) ?></a><?php homepage_render_teacher_rank($teacher); ?></p>
    <?php endforeach; ?>
</template>
<template id="campus-timetable">
    <h2>Timetable Centre</h2>
    <?php foreach (array_slice($hp['todayLessons'] ?? [], 0, 6) as $lesson): ?>
    <p><?= e(homepage_format_time($lesson['start_time'] ?? '')) ?> · <?= e($lesson['subject_name'] ?? '') ?></p>
    <?php endforeach; ?>
    <?php if (($hp['todayLessons'] ?? []) === []): ?><p>No classes listed for today.</p><?php endif; ?>
</template>
<template id="campus-admissions">
    <h2>Admissions Office</h2>
    <p><a href="<?= e($apply) ?>">Create student account</a></p>
</template>
<template id="campus-portal">
    <h2>Student Portal</h2>
    <p>Timetable, recordings, fees, documents and Talk with AI.</p>
    <button type="button" data-open-auth="login">Sign in</button>
</template>
<template id="campus-events">
    <h2>Events lawn</h2>
    <?php foreach ($events as $event): ?>
    <p><?= e(homepage_format_date($event['event_date'] ?? '')) ?> — <?= e($event['title'] ?? '') ?></p>
    <?php endforeach; ?>
</template>
<?php
homepage_render_auth('modal');
homepage_render_site_footer($hp);
homepage_render_foot($activeLayout);
