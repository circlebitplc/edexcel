<?php
declare(strict_types=1);

/**
 * Shared homepage helpers (presentation + URLs).
 */

function homepage_asset(string $relative): string
{
    $root = defined('HOMEPAGE_ROOT') ? HOMEPAGE_ROOT : dirname(__DIR__);
    $relative = ltrim(str_replace('\\', '/', $relative), '/');
    $path = $root . '/' . $relative;
    $base = rtrim((string)(defined('BASE_URL') ? BASE_URL : '/'), '/');
    $v = is_file($path) ? (string)filemtime($path) : '1';
    return $base . '/' . $relative . '?v=' . $v;
}

function homepage_url(array $params = [], string $hash = ''): string
{
    $preview = !empty($GLOBALS['homepagePreviewMode']);
    $layout = (string)($GLOBALS['activeLayout'] ?? HOMEPAGE_LAYOUT_DEFAULT);
    if ($preview) {
        $params = ['layout' => $layout] + $params;
        $path = 'preview.php';
    } else {
        $path = 'index.php';
    }
    $q = http_build_query($params);
    $url = $path . ($q !== '' ? '?' . $q : '');
    return $hash !== '' ? $url . '#' . ltrim($hash, '#') : $url;
}

function homepage_form_action(string $hash = ''): string
{
    $base = (string)($GLOBALS['homepageFormAction'] ?? 'index.php');
    return $hash !== '' ? $base . '#' . ltrim($hash, '#') : $base;
}

function homepage_why_choose(): array
{
    return [
        [
            'icon' => 'fa-chalkboard-user',
            'title' => 'Expert Edexcel Teachers',
            'text' => 'Specialist IGCSE and International A Level tutors, live classes, and published marks parents can follow.',
        ],
        [
            'icon' => 'fa-file-pen',
            'title' => 'Exam-Focused Tuition',
            'text' => 'Pearson Edexcel papers, official exam planning, and a timetable built for results in Sri Lanka.',
        ],
        [
            'icon' => 'fa-book-open',
            'title' => 'Past Papers & Practice',
            'text' => 'Past-paper practice, recordings, and Talk with AI inside the student portal.',
        ],
        [
            'icon' => 'fa-user-check',
            'title' => 'Individual Attention',
            'text' => 'Smaller groups, attendance tracking, and a parent login so families stay involved.',
        ],
    ];
}

function homepage_stock(string $key): string
{
    $photos = [
        'hero-student' => 'https://images.unsplash.com/photo-1523240795612-9a054b0db644?auto=format&fit=crop&w=1400&q=80',
        'learn' => 'https://images.unsplash.com/photo-1434030216411-0b793f4b4173?auto=format&fit=crop&w=1600&q=80',
        'teacher' => 'https://images.unsplash.com/photo-1577896851231-70ef18881754?auto=format&fit=crop&w=1600&q=80',
        'books' => 'https://images.unsplash.com/photo-1481627834876-b7833e8f5570?auto=format&fit=crop&w=1600&q=80',
        'grad' => 'https://images.unsplash.com/photo-1523050854058-8df90110c9f1?auto=format&fit=crop&w=1600&q=80',
        'campus' => 'https://images.unsplash.com/photo-1562774053-701939374585?auto=format&fit=crop&w=1800&q=80',
        'students' => 'https://images.unsplash.com/photo-1523580494863-6f3031224c94?auto=format&fit=crop&w=1800&q=80',
        'news' => 'https://images.unsplash.com/photo-1503676260728-1c00da094a0b?auto=format&fit=crop&w=1200&q=80',
        'lab' => 'https://images.unsplash.com/photo-1532094349884-543bc11b234d?auto=format&fit=crop&w=1200&q=80',
        'classroom' => 'https://images.unsplash.com/photo-1427504494785-3a9ca7044f45?auto=format&fit=crop&w=1200&q=80',
    ];
    return $photos[$key] ?? $photos['hero-student'];
}

/**
 * @return array{label:string,today:int,cells:list<int>}
 */
function homepage_month_cells(): array
{
    $daysInMonth = (int)date('t');
    $start = (int)date('N', strtotime(date('Y-m-01')));
    $cells = array_fill(0, max(0, $start - 1), 0);
    for ($day = 1; $day <= $daysInMonth; $day++) {
        $cells[] = $day;
    }
    return [
        'label' => date('F Y'),
        'today' => (int)date('j'),
        'cells' => $cells,
    ];
}

function homepage_format_time(?string $time): string
{
    $time = trim((string)$time);
    if ($time === '') {
        return '';
    }
    $ts = strtotime($time);
    return $ts ? date('g:i A', $ts) : $time;
}

function homepage_format_date(?string $date, string $format = 'd M Y'): string
{
    $date = trim((string)$date);
    if ($date === '') {
        return '';
    }
    $ts = strtotime($date);
    return $ts ? date($format, $ts) : $date;
}

function homepage_initials(string $name): string
{
    return function_exists('teacherInitials') ? teacherInitials($name) : strtoupper(substr(trim($name) ?: 'EC', 0, 2));
}

function homepage_photo_url(array $teacher): string
{
    $path = $teacher['photo_path'] ?? null;
    if (is_string($path) && $path !== '' && function_exists('teacherPhotoSrc')) {
        return teacherPhotoSrc($path);
    }
    return '';
}

/**
 * Safe integer from mixed stats.
 */
function homepage_stat(array $hp, string $key, int $fallback = 0): int
{
    return (int)($hp['stats'][$key] ?? $fallback);
}

function homepage_clip(string $text, int $max = 140): string
{
    $text = trim($text);
    if ($text === '') {
        return '';
    }
    if (function_exists('mb_strimwidth')) {
        return mb_strimwidth($text, 0, $max, '…');
    }
    if (strlen($text) <= $max) {
        return $text;
    }
    return rtrim(substr($text, 0, max(1, $max - 1))) . '…';
}
