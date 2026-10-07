<?php
declare(strict_types=1);

/**
 * ============================================================================
 * EDEXCEL COLLEGE — PROMOTIONAL LANDING PAGE (CLASS 2027)
 * ----------------------------------------------------------------------------
 * Teacher   : Enidu Batuwanthudawe
 * Course    : Mock Paper Class 2027 (ICT & Computer Science)
 * Locations : Kandy @ Edexcel College • Kurunegala @ Apollo Institute • Online
 * URL       : https://edexcel.college/class
 * ============================================================================
 */

// Bootstrap & system includes
if (!defined('DB_ALLOW_FAILURE')) {
    define('DB_ALLOW_FAILURE', true);
}
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/college_contact.php';
require_once __DIR__ . '/../includes/seo.php';
require_once __DIR__ . '/../config/bunny.php';
$classAnalyticsFile = __DIR__ . '/../src/Services/ClassPageAnalyticsService.php';
if (file_exists($classAnalyticsFile)) {
    require_once $classAnalyticsFile;
}

use Edexcel\Services\ClassPageAnalyticsService;

global $pdo;
$pdoSafe = $pdo instanceof PDO ? $pdo : null;
$contact = college_contact($pdoSafe);
$base = rtrim(defined('BASE_URL') ? (string)BASE_URL : '/', '/');
if ($base === '') {
    $base = '';
}
$canonicalUrl = (defined('CANONICAL_HOST') ? rtrim((string)CANONICAL_HOST, '/') : 'https://edexcel.college') . '/class';

// Server-side visit logging for dedicated /class analytics (100% resilient across all browsers/devices)
if ($pdoSafe !== null && class_exists('Edexcel\\Services\\ClassPageAnalyticsService')) {
    try {
        ClassPageAnalyticsService::ensureSchema($pdoSafe);
        $vHash = (string)($_COOKIE['_cls_vid'] ?? '');
        $sHash = (string)($_COOKIE['_cls_sid'] ?? '');
        $isNewVid = false;
        $isNewSid = false;
        if (!ClassPageAnalyticsService::isValidHash($vHash)) {
            $vHash = bin2hex(random_bytes(16));
            $isNewVid = true;
        }
        if (!ClassPageAnalyticsService::isValidHash($sHash)) {
            $sHash = bin2hex(random_bytes(16));
            $isNewSid = true;
        }
        if (!headers_sent()) {
            if ($isNewVid) {
                setcookie('_cls_vid', $vHash, [
                    'expires' => time() + 31536000,
                    'path' => '/',
                    'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
                    'httponly' => false,
                    'samesite' => 'Lax'
                ]);
            }
            if ($isNewSid) {
                setcookie('_cls_sid', $sHash, [
                    'expires' => time() + 1800,
                    'path' => '/',
                    'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
                    'httponly' => false,
                    'samesite' => 'Lax'
                ]);
            }
        }

        $ref = (string)($_SERVER['HTTP_REFERER'] ?? '');
        $ua = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');

        ClassPageAnalyticsService::recordVisit($pdoSafe, [
            'session_hash' => $sHash,
            'visitor_hash' => $vHash,
            'page_url' => (string)($_SERVER['REQUEST_URI'] ?? '/class'),
            'referrer' => $ref,
            'utm_source' => isset($_GET['utm_source']) ? (string)$_GET['utm_source'] : null,
            'utm_medium' => isset($_GET['utm_medium']) ? (string)$_GET['utm_medium'] : null,
            'utm_campaign' => isset($_GET['utm_campaign']) ? (string)$_GET['utm_campaign'] : null,
            'utm_content' => isset($_GET['utm_content']) ? (string)$_GET['utm_content'] : null,
            'utm_term' => isset($_GET['utm_term']) ? (string)$_GET['utm_term'] : null,
        ], $ua);
    } catch (Throwable $e) {
        // Analytics error must never block page rendering
    }
}

// ----------------------------------------------------------------------------
// 1. BUNNY.NET STREAM CONFIGURATION
// Video 1: fea69ded-0e30-47c2-9c75-e0a494f7b00f
// Video 2: d7a309c2-1519-4944-8159-6b697652ac65 (Autoplay Muted Inline, loop=false)
// Video 3: b6125a08-5dd6-4e59-94c7-08c98c6b3fcb
// ----------------------------------------------------------------------------
$bunnyLibraryId = '737938'; // Enidu Batuwanthudawe assigned Stream Library ID
if ($pdoSafe !== null) {
    try {
        $stmt = $pdoSafe->query("SELECT bunny_library_id FROM teachers WHERE id = 14 LIMIT 1");
        $val = $stmt->fetchColumn();
        if ($val && trim((string)$val) !== '') {
            $bunnyLibraryId = trim((string)$val);
        } else {
            $stmt2 = $pdoSafe->query("SELECT setting_value FROM settings WHERE setting_key = 'bunny_stream_library_id' LIMIT 1");
            $val2 = $stmt2->fetchColumn();
            if ($val2 && trim((string)$val2) !== '') {
                $bunnyLibraryId = trim((string)$val2);
            }
        }
    } catch (Throwable $e) {
        // Fallback to verified production library ID 737938
    }
}

$promoVideos = [
    [
        'id' => 'fea69ded-0e30-47c2-9c75-e0a494f7b00f',
        'title' => 'Mock Paper Class Walkthrough',
        'subtitle' => 'Exam Technique & Question Breakdown',
        'autoplay' => false,
        'embed_url' => 'https://iframe.mediadelivery.net/embed/' . rawurlencode($bunnyLibraryId) . '/fea69ded-0e30-47c2-9c75-e0a494f7b00f?autoplay=false&loop=false&muted=false&preload=true&responsive=false&playsinline=true',
    ],
    [
        'id' => 'd7a309c2-1519-4944-8159-6b697652ac65',
        'title' => 'Exam Preparation in Action',
        'subtitle' => 'Timed Conditions & Live Marking',
        'autoplay' => true, // REQUIRED: Autoplays muted inline, loop=false
        'embed_url' => 'https://iframe.mediadelivery.net/embed/' . rawurlencode($bunnyLibraryId) . '/d7a309c2-1519-4944-8159-6b697652ac65?autoplay=true&loop=false&muted=true&preload=true&responsive=false&playsinline=true',
    ],
    [
        'id' => 'b6125a08-5dd6-4e59-94c7-08c98c6b3fcb',
        'title' => 'Mastering Mark Schemes',
        'subtitle' => 'Targeted Corrections & Performance Analysis',
        'autoplay' => false,
        'embed_url' => 'https://iframe.mediadelivery.net/embed/' . rawurlencode($bunnyLibraryId) . '/b6125a08-5dd6-4e59-94c7-08c98c6b3fcb?autoplay=false&loop=false&muted=false&preload=true&responsive=false&playsinline=true',
    ],
];

// ----------------------------------------------------------------------------
// 2. MOCK PAPER PDF CONFIGURATION (PAGES 1–5 PREVIEW)
// ----------------------------------------------------------------------------
$mockPaperPdf = 'MOCK_1.pdf';
$pdfResolvedUrl = $base . '/class/MOCK_1_preview.pdf'; // 5-page preview file

// ----------------------------------------------------------------------------
// 3. CLASS SCHEDULES BY FORMAT & VENUE
// Physical Kandy      : Kandy @ Edexcel College
// Physical Kurunegala : Kurunegala @ Apollo Institute
// Online              : Worldwide Live Virtual Sessions
// ----------------------------------------------------------------------------
$classGroups = [
    'kandy' => [
        'format_title' => 'Kandy Physical Classes',
        'venue_name' => 'Kandy @ Edexcel College',
        'venue_desc' => 'Physical classroom tuition at Edexcel College campus, Katugastota Road, Kandy.',
        'badge' => 'KANDY @ EDEXCEL COLLEGE',
        'badge_class' => 'badge-kandy',
        'classes' => [
            [
                'id' => 'kandy-ict',
                'subject' => 'ICT',
                'specification' => 'Pearson Edexcel IGCSE & International A Level',
                'day' => 'Sunday',
                'time' => '8:00 AM',
                'format' => 'Physical Class',
                'venue' => 'Kandy @ Edexcel College',
                'whatsapp_url' => 'https://chat.whatsapp.com/EGGGbKauJTw0v7YWP0oiCK',
                'button_text' => 'JOIN KANDY ICT GROUP',
                'track_target' => 'kandy_ict',
                'icon' => 'fa-laptop-code',
            ],
            [
                'id' => 'kandy-cs',
                'subject' => 'Computer Science',
                'specification' => 'Pearson Edexcel IGCSE & International A Level',
                'day' => 'Sunday',
                'time' => '10:00 AM',
                'format' => 'Physical Class',
                'venue' => 'Kandy @ Edexcel College',
                'whatsapp_url' => 'https://chat.whatsapp.com/LC7A8Wm0SuY8LwElUb1KMW',
                'button_text' => 'JOIN KANDY CS GROUP',
                'track_target' => 'kandy_cs',
                'icon' => 'fa-microchip',
            ],
        ],
    ],
    'kurunegala' => [
        'format_title' => 'Kurunegala Physical Classes',
        'venue_name' => 'Kurunegala @ Apollo Institute',
        'venue_desc' => 'Physical classroom tuition conducted at Apollo Institute, Kurunegala.',
        'badge' => 'KURUNEGALA @ APOLLO INSTITUTE',
        'badge_class' => 'badge-kurunegala',
        'classes' => [
            [
                'id' => 'kurunegala-ict',
                'subject' => 'ICT',
                'specification' => 'Pearson Edexcel IGCSE & International A Level',
                'day' => 'Saturday',
                'time' => '3:30 PM',
                'format' => 'Physical Class',
                'venue' => 'Kurunegala @ Apollo Institute',
                'whatsapp_url' => 'https://chat.whatsapp.com/GW8KGkhJXq03gblLKOTWJm',
                'button_text' => 'JOIN KURUNEGALA ICT GROUP',
                'track_target' => 'kurunegala_ict',
                'icon' => 'fa-laptop-code',
            ],
            [
                'id' => 'kurunegala-cs',
                'subject' => 'Computer Science',
                'specification' => 'Pearson Edexcel IGCSE & International A Level',
                'day' => 'Saturday',
                'time' => '1:30 PM',
                'format' => 'Physical Class',
                'venue' => 'Kurunegala @ Apollo Institute',
                'whatsapp_url' => 'https://chat.whatsapp.com/JLvWComjvADH27bALjqF9U',
                'button_text' => 'JOIN KURUNEGALA CS GROUP',
                'track_target' => 'kurunegala_cs',
                'icon' => 'fa-microchip',
            ],
        ],
    ],
    'online' => [
        'format_title' => 'Online Live Classes',
        'venue_name' => 'Online Classes Worldwide',
        'venue_desc' => 'Live interactive virtual sessions with digital mock papers, realtime feedback, and portal recordings.',
        'badge' => 'ONLINE CLASSES',
        'badge_class' => 'badge-online',
        'classes' => [
            [
                'id' => 'online-ict',
                'subject' => 'ICT',
                'specification' => 'Pearson Edexcel IGCSE & International A Level',
                'day' => 'Weekly Live Session',
                'time' => 'Schedule: Contact us',
                'timezone' => 'Sri Lanka Time (UTC+5:30)',
                'format' => 'Live Online',
                'venue' => 'Live Online (Virtual Portal)',
                'whatsapp_url' => 'https://chat.whatsapp.com/GW8KGkhJXq03gblLKOTWJm',
                'button_text' => 'ONLINE ICT — JOIN WHATSAPP',
                'track_target' => 'online_ict',
                'icon' => 'fa-globe',
            ],
            [
                'id' => 'online-cs',
                'subject' => 'Computer Science',
                'specification' => 'Pearson Edexcel IGCSE & International A Level',
                'day' => 'Weekly Live Session',
                'time' => 'Schedule: Contact us',
                'timezone' => 'Sri Lanka Time (UTC+5:30)',
                'format' => 'Live Online',
                'venue' => 'Live Online (Virtual Portal)',
                'whatsapp_url' => 'https://chat.whatsapp.com/JLvWComjvADH27bALjqF9U',
                'button_text' => 'ONLINE COMPUTER SCIENCE — JOIN WHATSAPP',
                'track_target' => 'online_cs',
                'icon' => 'fa-network-wired',
            ],
        ],
    ],
];

// Handle lead submission from page
$leadMsg = '';
$leadErr = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'register_lead') {
    $csrfToken = (string)($_POST['csrf_token'] ?? '');
    if (!function_exists('verify_csrf_token') || verify_csrf_token($csrfToken)) {
        // Spam rate limiter (minimum 10 seconds between submissions in same session)
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $lastSubmitTime = (int)($_SESSION['last_class_lead_submit'] ?? 0);
        if (time() - $lastSubmitTime < 10) {
            $leadErr = 'Please wait a moment before submitting again.';
        } else {
            $studentName = trim((string)($_POST['student_name'] ?? ''));
            $parentName = trim((string)($_POST['parent_name'] ?? ''));
            $whatsappNumber = trim((string)($_POST['whatsapp_number'] ?? ''));
            $subject = trim((string)($_POST['subject_interest'] ?? ''));
            $format = trim((string)($_POST['class_format'] ?? ''));
            $location = trim((string)($_POST['location'] ?? ''));
            $grade = trim((string)($_POST['grade'] ?? ''));
            $school = trim((string)($_POST['school'] ?? ''));

            $visitorHash = (string)($_POST['visitor_hash'] ?? ($_COOKIE['_cls_vid'] ?? ''));
            $sessionHash = (string)($_POST['session_hash'] ?? ($_COOKIE['_cls_sid'] ?? ''));
            if (!\Edexcel\Services\ClassPageAnalyticsService::isValidHash($visitorHash)) {
                $visitorHash = bin2hex(random_bytes(16));
            }
            if (!\Edexcel\Services\ClassPageAnalyticsService::isValidHash($sessionHash)) {
                $sessionHash = bin2hex(random_bytes(16));
            }

            // Server-side field validation
            if ($studentName === '' || mb_strlen($studentName) < 2 || mb_strlen($studentName) > 100) {
                $leadErr = 'Please provide a valid student name (2–100 characters).';
            } elseif ($whatsappNumber === '' || !preg_match('/^[0-9+\s\-()]{7,20}$/', $whatsappNumber)) {
                $leadErr = 'Please enter a valid WhatsApp telephone number (e.g. 077 123 4567).';
            }

            if ($leadErr !== '') {
                if ($pdoSafe !== null && class_exists('Edexcel\\Services\\ClassPageAnalyticsService')) {
                    try {
                        ClassPageAnalyticsService::recordEvent($pdoSafe, [
                            'event_name' => 'registration_fail',
                            'event_target' => $format !== '' ? ($format . ' — ' . $location) : 'validation_error',
                            'event_value' => mb_substr($leadErr, 0, 100),
                            'session_hash' => $sessionHash,
                            'visitor_hash' => $visitorHash,
                        ], true);
                    } catch (Throwable $e) {}
                }
            } else {
                $_SESSION['last_class_lead_submit'] = time();

                if ($pdoSafe !== null && class_exists('Edexcel\\Services\\LeadService')) {
                    try {
                        $leadService = new \Edexcel\Services\LeadService($pdoSafe);
                        $leadService->create([
                            'full_name' => $studentName,
                            'phone' => $whatsappNumber,
                            'source' => 'class_landing_page',
                            'qualification_label' => $grade !== '' ? $grade : 'IGCSE 2027',
                            'location_pref' => $location !== '' ? $location : $format,
                            'notes' => "Parent: {$parentName} | School: {$school} | Subject: {$subject} | Format: {$format} | Venue: {$location}",
                        ]);
                    } catch (Throwable $e) {
                        error_log('LeadService creation note: ' . $e->getMessage());
                    }
                }

                // Log conversion event in dedicated /class analytics
                if ($pdoSafe !== null && class_exists('Edexcel\\Services\\ClassPageAnalyticsService')) {
                    try {
                        ClassPageAnalyticsService::recordEvent($pdoSafe, [
                            'event_name' => 'registration_success',
                            'event_target' => $format . ' — ' . $location,
                            'event_value' => $subject,
                            'session_hash' => $sessionHash,
                            'visitor_hash' => $visitorHash,
                            'page_url' => '/class#register',
                        ], true);
                    } catch (Throwable $e) {
                        // Analytics error must never block user
                    }
                }

                $leadMsg = "Thank you, {$studentName}! Registration is free. Your details have been received and class fees will be confirmed before enrolment. Join your subject WhatsApp group below to receive immediate timetable details.";
            }
        }
    } else {
        $leadErr = 'Security token expired. Please refresh the page and submit again.';
    }
}

// SEO Metas
$pageTitle = 'Edexcel Mock Paper Classes 2027 | IGCSE ICT & Computer Science | Kandy, Kurunegala & Online';
$pageDescription = 'Join Edexcel College Mock Paper Classes 2027 for ICT and Computer Science in Kandy, Kurunegala and online. Practise exam-style questions, receive feedback and prepare for your exams.';

// Schema.org structured data
$schemaGraph = [
    [
        '@context' => 'https://schema.org',
        '@type' => 'Course',
        'name' => 'Mock Paper Class 2027 — ICT & Computer Science',
        'description' => $pageDescription,
        'provider' => [
            '@type' => 'EducationalOrganization',
            'name' => 'Edexcel College',
            'url' => 'https://edexcel.college',
            'address' => [
                '@type' => 'PostalAddress',
                'streetAddress' => 'No 83 Katugastota Road',
                'addressLocality' => 'Kandy',
                'addressCountry' => 'LK',
            ],
        ],
        'instructor' => [
            '@type' => 'Person',
            'name' => 'Enidu Batuwanthudawe',
            'jobTitle' => 'Specialist Teacher of ICT and Computer Science',
            'award' => [
                '2025 Best Teacher Award — ICONIC Awards',
                '2026 Best Teacher Award — Asian Achievers',
            ],
            'image' => 'https://edexcel.college/assets/images/teachers/14.png',
        ],
        'educationalLevel' => 'Pearson Edexcel IGCSE & International A Level',
        'courseMode' => ['blended', 'onsite', 'online'],
        'url' => $canonicalUrl,
    ],
    [
        '@context' => 'https://schema.org',
        '@type' => 'BreadcrumbList',
        'itemListElement' => [
            [
                '@type' => 'ListItem',
                'position' => 1,
                'name' => 'Home',
                'item' => 'https://edexcel.college/',
            ],
            [
                '@type' => 'ListItem',
                'position' => 2,
                'name' => 'Mock Paper Class 2027',
                'item' => $canonicalUrl,
            ],
        ],
    ],
];
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title><?= e($pageTitle) ?></title>
    <meta name="description" content="<?= e($pageDescription) ?>">
    <link rel="canonical" href="<?= e($canonicalUrl) ?>">
    <meta name="robots" content="index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1">
    
    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?= e($canonicalUrl) ?>">
    <meta property="og:title" content="<?= e($pageTitle) ?>">
    <meta property="og:description" content="<?= e($pageDescription) ?>">
    <meta property="og:image" content="<?= e($base) ?>/assets/images/teachers/14.png">
    <meta property="og:site_name" content="Edexcel College">
    <meta property="og:locale" content="en_LK">

    <!-- Twitter Cards -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:url" content="<?= e($canonicalUrl) ?>">
    <meta name="twitter:title" content="<?= e($pageTitle) ?>">
    <meta name="twitter:description" content="<?= e($pageDescription) ?>">
    <meta name="twitter:image" content="<?= e($base) ?>/assets/images/teachers/14.png">

    <!-- Preconnect & Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preconnect" href="https://iframe.mediadelivery.net">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" referrerpolicy="no-referrer">

    <!-- Global Shared & Page Stylesheets -->
    <link rel="stylesheet" href="<?= e($base) ?>/assets/css/homepage-shared.css">
    <link rel="stylesheet" href="<?= e($base) ?>/class/class.css">

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="<?= e($base) ?>/favicon.ico">

    <!-- JSON-LD Structured Data -->
    <script type="application/ld+json">
    <?= json_encode($schemaGraph, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?>
    </script>
</head>
<body class="class-landing">

    <!-- Keyboard Navigation Skip Target -->
    <a href="#main-content" class="skip-nav-link">Skip to main content</a>

    <!-- =====================================================================
         1. STICKY INSTITUTIONAL HEADER
         ===================================================================== -->
    <header class="cls-header" id="cls-header">
        <div class="cls-header-inner">
            <a href="<?= e($base) ?>/" class="cls-brand" aria-label="Edexcel College Homepage">
                <span class="cls-brand-icon"><i class="fas fa-landmark" aria-hidden="true"></i></span>
                <div class="cls-brand-text">
                    <span class="cls-brand-title">Edexcel College</span>
                    <span class="cls-brand-sub">Academic Excellence</span>
                </div>
            </a>

            <!-- Desktop Nav Links -->
            <nav class="cls-nav-menu" id="clsNavMenu" aria-label="Landing Page Navigation">
                <a href="#videos" class="cls-nav-link"><i class="fas fa-play-circle" aria-hidden="true"></i> Videos</a>
                <a href="#schedule" class="cls-nav-link"><i class="fas fa-calendar-check" aria-hidden="true"></i> Classes</a>
                <a href="#preview" class="cls-nav-link"><i class="fas fa-file-pdf" aria-hidden="true"></i> Mock Paper</a>
                <a href="#awards" class="cls-nav-link"><i class="fas fa-award" aria-hidden="true"></i> Awards</a>
                <a href="#why" class="cls-nav-link"><i class="fas fa-lightbulb" aria-hidden="true"></i> Why Mock?</a>
                <a href="#locations" class="cls-nav-link"><i class="fas fa-location-dot" aria-hidden="true"></i> Locations</a>
                <a href="#register" class="cls-nav-link"><i class="fas fa-user-plus" aria-hidden="true"></i> Register</a>
            </nav>

            <!-- Nav Actions -->
            <div class="cls-header-actions">
                <a href="<?= e($base) ?>/portal/login.php" class="cls-btn-portal" title="Portal Login">
                    <i class="fas fa-arrow-right-to-bracket" aria-hidden="true"></i>
                    <span>Portal</span>
                </a>
                <a href="#schedule" class="cls-btn-join-nav">
                    <i class="fas fa-compass" aria-hidden="true"></i>
                    <span>Choose Class</span>
                </a>
                <button type="button" class="cls-mobile-toggle" id="clsMobileToggle" aria-label="Toggle navigation menu" aria-expanded="false" aria-controls="clsNavMenu">
                    <i class="fas fa-bars" aria-hidden="true"></i>
                </button>
            </div>
        </div>
    </header>

    <main id="main-content">

        <!-- =================================================================
             2. HERO SECTION
             ================================================================= -->
        <section class="cls-hero" id="hero" aria-labelledby="hero-title">
            <div class="cls-container">
                <div class="cls-hero-shell">
                    
                    <div class="cls-hero-badge-wrap">
                        <span class="cls-hero-badge">
                            <span class="cls-badge-dot"></span>
                            <span class="cls-badge-loc">KANDY • KURUNEGALA • ONLINE</span>
                        </span>
                    </div>

                    <h1 class="cls-hero-title" id="hero-title">
                        MOCK PAPER CLASS 2027
                    </h1>

                    <p class="cls-hero-subject-tag">
                        ICT &amp; COMPUTER SCIENCE
                    </p>

                    <p class="cls-hero-tagline">
                        Prepare. Practise. Improve.
                    </p>

                    <!-- Teacher Pill -->
                    <div class="cls-teacher-pill">
                        <div class="cls-teacher-avatar-wrap">
                            <img src="<?= e($base) ?>/assets/images/teachers/14.png" alt="Enidu Batuwanthudawe" width="56" height="56" class="cls-teacher-avatar" loading="eager">
                        </div>
                        <div class="cls-teacher-pill-info">
                            <span class="cls-pill-caption">Conducted by</span>
                            <strong class="cls-pill-name">Enidu Batuwanthudawe</strong>
                            <span class="cls-pill-meta">Specialist in Pearson Edexcel ICT &amp; Computer Science</span>
                        </div>
                    </div>

                    <p class="cls-hero-description">
                        Prepare for your examination by practising with structured mock papers, exam-style questions, timed practice and focused feedback.
                    </p>

                    <!-- Primary & Secondary CTAs -->
                    <div class="cls-hero-actions">
                        <a href="#schedule" class="cls-btn cls-btn-primary cls-btn-pulse">
                            <i class="fas fa-list-check" aria-hidden="true"></i>
                            <span>CHOOSE YOUR CLASS</span>
                        </a>
                        <a href="#preview" class="cls-btn cls-btn-secondary">
                            <i class="fas fa-file-pdf" aria-hidden="true"></i>
                            <span>PREVIEW MOCK PAPER</span>
                        </a>
                        <a href="#register" class="cls-btn cls-btn-ghost">
                            <i class="fas fa-pen-to-square" aria-hidden="true"></i>
                            <span>REGISTER NOW</span>
                        </a>
                    </div>

                    <!-- Trust Strip -->
                    <div class="cls-trust-strip">
                        <div class="cls-trust-item">
                            <i class="fas fa-stopwatch" aria-hidden="true"></i>
                            <span>Timed Exam Conditions</span>
                        </div>
                        <div class="cls-trust-item">
                            <i class="fas fa-check-double" aria-hidden="true"></i>
                            <span>Detailed Marking &amp; Feedback</span>
                        </div>
                        <div class="cls-trust-item">
                            <i class="fas fa-award" aria-hidden="true"></i>
                            <span>Award-Winning Instruction</span>
                        </div>
                    </div>

                </div>
            </div>
        </section>


        <!-- =================================================================
             3. THREE BUNNY.NET PROMOTIONAL VIDEOS (3 IN A ROW, VIDEO 2 AUTOPLAYS)
             ================================================================= -->
        <section class="cls-section cls-videos-section" id="videos" aria-labelledby="videos-heading">
            <div class="cls-container">
                
                <div class="cls-section-head text-center">
                    <span class="cls-eyebrow"><i class="fas fa-video" aria-hidden="true"></i> Classroom Footage</span>
                    <h2 class="cls-section-title" id="videos-heading">SEE THE CLASS IN ACTION</h2>
                    <p class="cls-section-desc">
                        Watch how mock paper sessions operate, how exam techniques are broken down, and how structured feedback empowers learners.
                    </p>
                </div>

                <!-- 3 Videos in One Row on Desktop, 2+1 on Tablet, 1 per row on Mobile -->
                <div class="cls-videos-grid">
                    <?php foreach ($promoVideos as $index => $video): 
                        $videoNumber = $index + 1;
                    ?>
                    <article class="cls-video-card <?= $video['autoplay'] ? 'cls-video-featured' : '' ?>" aria-label="Video <?= $videoNumber ?>: <?= e($video['title']) ?>">
                        <div class="cls-video-ratio-box">
                            <iframe
                                src="<?= e($video['embed_url']) ?>"
                                title="<?= e($video['title']) ?> — Edexcel College"
                                loading="lazy"
                                class="cls-video-iframe"
                                allow="accelerometer; gyroscope; autoplay; encrypted-media; picture-in-picture;"
                                allowfullscreen
                            ></iframe>
                        </div>
                        <div class="cls-video-caption">
                            <span class="cls-video-num">0<?= $videoNumber ?></span>
                            <div class="cls-video-text">
                                <h3 class="cls-video-title"><?= e($video['title']) ?></h3>
                                <p class="cls-video-sub"><?= e($video['subtitle']) ?></p>
                            </div>
                            <?php if ($video['autoplay']): ?>
                                <span class="cls-video-live-badge" title="Playing live preview"><i class="fas fa-circle-play" aria-hidden="true"></i> Preview</span>
                            <?php endif; ?>
                        </div>
                    </article>
                    <?php endforeach; ?>
                </div>

            </div>
        </section>


        <!-- =================================================================
             4. CLASS FORMAT SELECTION & TIMETABLE (KANDY, KURUNEGALA, ONLINE)
             ================================================================= -->
        <section class="cls-section cls-schedule-section" id="schedule" aria-labelledby="schedule-heading">
            <div class="cls-container">
                
                <div class="cls-section-head text-center">
                    <span class="cls-eyebrow"><i class="fas fa-calendar-alt" aria-hidden="true"></i> Choose Your Format &amp; Group</span>
                    <h2 class="cls-section-title" id="schedule-heading">CHOOSE YOUR CLASS</h2>
                    <p class="cls-section-desc">
                        First choose your physical location or online format, select your subject, and tap to join the official class WhatsApp group.
                    </p>
                </div>

                <!-- Format Selection Tabs / Quick Links -->
                <div class="cls-format-pills-bar">
                    <a href="#kandy-section" class="cls-format-pill">
                        <i class="fas fa-building-columns" aria-hidden="true"></i>
                        <span>Kandy @ Edexcel College</span>
                    </a>
                    <a href="#kurunegala-section" class="cls-format-pill">
                        <i class="fas fa-school" aria-hidden="true"></i>
                        <span>Kurunegala @ Apollo Institute</span>
                    </a>
                    <a href="#online-section" class="cls-format-pill">
                        <i class="fas fa-globe" aria-hidden="true"></i>
                        <span>Online Classes Worldwide</span>
                    </a>
                </div>

                <?php if ($leadMsg): ?>
                    <div class="cls-alert cls-alert-success mt-4 mb-6">
                        <i class="fas fa-check-circle" aria-hidden="true"></i>
                        <div><?= e($leadMsg) ?></div>
                    </div>
                <?php endif; ?>

                <!-- FORMAT 1: KANDY @ EDEXCEL COLLEGE -->
                <div class="cls-format-block" id="kandy-section">
                    <div class="cls-fb-header">
                        <div class="cls-fb-title-wrap">
                            <span class="cls-location-badge badge-kandy"><i class="fas fa-map-pin" aria-hidden="true"></i> Kandy Physical Class</span>
                            <h3 class="cls-fb-venue">Kandy @ Edexcel College</h3>
                            <p class="cls-fb-desc"><i class="fas fa-location-dot" aria-hidden="true"></i> No 83 Katugastota Road, Kandy (Central Province)</p>
                        </div>
                        <span class="cls-fb-day-tag"><i class="far fa-calendar" aria-hidden="true"></i> Every Sunday</span>
                    </div>

                    <div class="cls-cards-grid">
                        <?php foreach ($classGroups['kandy']['classes'] as $c): ?>
                        <article class="cls-class-card" id="<?= e($c['id']) ?>">
                            <div class="cls-card-top">
                                <span class="cls-subject-tag"><?= e($c['specification']) ?></span>
                                <span class="cls-venue-badge">Physical</span>
                            </div>

                            <div class="cls-subject-block">
                                <div class="cls-subject-icon">
                                    <i class="fas <?= e($c['icon']) ?>" aria-hidden="true"></i>
                                </div>
                                <div class="cls-subject-meta">
                                    <span class="cls-subject-sub"><?= e($c['venue']) ?></span>
                                    <h4 class="cls-subject-name"><?= e($c['subject']) ?></h4>
                                </div>
                            </div>

                            <div class="cls-schedule-details">
                                <div class="cls-detail-item">
                                    <span class="cls-detail-label"><i class="far fa-calendar" aria-hidden="true"></i> Day</span>
                                    <strong class="cls-detail-value"><?= e($c['day']) ?></strong>
                                </div>
                                <div class="cls-detail-item">
                                    <span class="cls-detail-label"><i class="far fa-clock" aria-hidden="true"></i> Time</span>
                                    <strong class="cls-detail-value cls-time-value"><?= e($c['time']) ?></strong>
                                </div>
                            </div>

                            <div class="cls-card-pricing-hint">
                                <span><i class="fas fa-receipt" aria-hidden="true"></i> Fees: Contact us for current class fees</span>
                            </div>

                            <div class="cls-card-action">
                                <a
                                    href="<?= e($c['whatsapp_url']) ?>"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="cls-btn cls-btn-wa"
                                    data-track-action="whatsapp_click"
                                    data-track-target="<?= e($c['track_target'] ?? 'kandy_wa') ?>"
                                    aria-label="Join WhatsApp Group for <?= e($c['subject']) ?> at Kandy"
                                >
                                    <i class="fab fa-whatsapp" aria-hidden="true"></i>
                                    <span>JOIN WHATSAPP GROUP</span>
                                </a>
                            </div>
                        </article>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- FORMAT 2: KURUNEGALA @ APOLLO INSTITUTE -->
                <div class="cls-format-block mt-8" id="kurunegala-section">
                    <div class="cls-fb-header">
                        <div class="cls-fb-title-wrap">
                            <span class="cls-location-badge badge-kurunegala"><i class="fas fa-map-pin" aria-hidden="true"></i> Kurunegala Physical Class</span>
                            <h3 class="cls-fb-venue">Kurunegala @ Apollo Institute</h3>
                            <p class="cls-fb-desc"><i class="fas fa-location-dot" aria-hidden="true"></i> Apollo Institute, Kurunegala (North Western Province)</p>
                        </div>
                        <span class="cls-fb-day-tag"><i class="far fa-calendar" aria-hidden="true"></i> Every Saturday</span>
                    </div>

                    <div class="cls-cards-grid">
                        <?php foreach ($classGroups['kurunegala']['classes'] as $c): ?>
                        <article class="cls-class-card" id="<?= e($c['id']) ?>">
                            <div class="cls-card-top">
                                <span class="cls-subject-tag"><?= e($c['specification']) ?></span>
                                <span class="cls-venue-badge">Physical</span>
                            </div>

                            <div class="cls-subject-block">
                                <div class="cls-subject-icon">
                                    <i class="fas <?= e($c['icon']) ?>" aria-hidden="true"></i>
                                </div>
                                <div class="cls-subject-meta">
                                    <span class="cls-subject-sub"><?= e($c['venue']) ?></span>
                                    <h4 class="cls-subject-name"><?= e($c['subject']) ?></h4>
                                </div>
                            </div>

                            <div class="cls-schedule-details">
                                <div class="cls-detail-item">
                                    <span class="cls-detail-label"><i class="far fa-calendar" aria-hidden="true"></i> Day</span>
                                    <strong class="cls-detail-value"><?= e($c['day']) ?></strong>
                                </div>
                                <div class="cls-detail-item">
                                    <span class="cls-detail-label"><i class="far fa-clock" aria-hidden="true"></i> Time</span>
                                    <strong class="cls-detail-value cls-time-value"><?= e($c['time']) ?></strong>
                                </div>
                            </div>

                            <div class="cls-card-pricing-hint">
                                <span><i class="fas fa-receipt" aria-hidden="true"></i> Fees: Contact us for current class fees</span>
                            </div>

                            <div class="cls-card-action">
                                <a
                                    href="<?= e($c['whatsapp_url']) ?>"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="cls-btn cls-btn-wa"
                                    data-track-action="whatsapp_click"
                                    data-track-target="<?= e($c['track_target'] ?? 'kurunegala_wa') ?>"
                                    aria-label="Join WhatsApp Group for <?= e($c['subject']) ?> at Kurunegala"
                                >
                                    <i class="fab fa-whatsapp" aria-hidden="true"></i>
                                    <span>JOIN WHATSAPP GROUP</span>
                                </a>
                            </div>
                        </article>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- FORMAT 3: ONLINE CLASSES (SEPARATELY OFFERED) -->
                <div class="cls-format-block mt-8" id="online-section">
                    <div class="cls-fb-header">
                        <div class="cls-fb-title-wrap">
                            <span class="cls-location-badge badge-online"><i class="fas fa-globe" aria-hidden="true"></i> Worldwide Online Class</span>
                            <h3 class="cls-fb-venue">Online Classes</h3>
                            <p class="cls-fb-desc"><i class="fas fa-signal" aria-hidden="true"></i> Live virtual interactive tuition accessible from anywhere in Sri Lanka &amp; overseas</p>
                        </div>
                        <span class="cls-fb-day-tag"><i class="fas fa-video" aria-hidden="true"></i> Live Sessions</span>
                    </div>

                    <div class="cls-cards-grid">
                        <?php foreach ($classGroups['online']['classes'] as $c): ?>
                        <article class="cls-class-card cls-class-card-online" id="<?= e($c['id']) ?>">
                            <div class="cls-card-top">
                                <span class="cls-subject-tag"><?= e($c['specification']) ?></span>
                                <span class="cls-venue-badge cls-venue-badge-online">Virtual Portal</span>
                            </div>

                            <div class="cls-subject-block">
                                <div class="cls-subject-icon">
                                    <i class="fas <?= e($c['icon']) ?>" aria-hidden="true"></i>
                                </div>
                                <div class="cls-subject-meta">
                                    <span class="cls-subject-sub">Live Interactive Stream</span>
                                    <h4 class="cls-subject-name"><?= e($c['subject']) ?> (Online)</h4>
                                </div>
                            </div>

                            <div class="cls-schedule-details">
                                <div class="cls-detail-item">
                                    <span class="cls-detail-label"><i class="far fa-calendar" aria-hidden="true"></i> Day</span>
                                    <strong class="cls-detail-value"><?= e($c['day']) ?></strong>
                                </div>
                                <div class="cls-detail-item">
                                    <span class="cls-detail-label"><i class="far fa-clock" aria-hidden="true"></i> Time</span>
                                    <strong class="cls-detail-value cls-time-value"><?= e($c['time']) ?></strong>
                                </div>
                                <div class="cls-detail-item">
                                    <span class="cls-detail-label"><i class="fas fa-globe" aria-hidden="true"></i> Timezone</span>
                                    <strong class="cls-detail-value"><?= e($c['timezone'] ?? 'Sri Lanka Time (UTC+5:30)') ?></strong>
                                </div>
                                <div class="cls-detail-item">
                                    <span class="cls-detail-label"><i class="fas fa-video" aria-hidden="true"></i> Format</span>
                                    <strong class="cls-detail-value">Live Online</strong>
                                </div>
                            </div>

                            <div class="cls-card-pricing-hint">
                                <span><i class="fas fa-receipt" aria-hidden="true"></i> Fees: Contact us for current class fees</span>
                            </div>

                            <div class="cls-card-action">
                                <a
                                    href="<?= e($c['whatsapp_url']) ?>"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="cls-btn cls-btn-wa"
                                    data-track-action="whatsapp_click"
                                    data-track-target="<?= e($c['track_target'] ?? 'online_wa') ?>"
                                    aria-label="Join Online WhatsApp Group for <?= e($c['subject']) ?>"
                                >
                                    <i class="fab fa-whatsapp" aria-hidden="true"></i>
                                    <span><?= e($c['button_text']) ?></span>
                                </a>
                            </div>
                        </article>
                        <?php endforeach; ?>
                    </div>

                    <div class="cls-online-notice-card mt-4">
                        <i class="fab fa-whatsapp text-success fs-3 me-3" aria-hidden="true"></i>
                        <div>
                            <strong class="text-white">Online WhatsApp Coordination:</strong>
                            <p class="mb-0 text-muted small">Online students use the official subject WhatsApp group (ICT or Computer Science). Live session links, interactive Google Classroom access, and mock paper PDF materials are shared directly inside these groups before each session.</p>
                        </div>
                    </div>
                </div>

            </div>
        </section>


        <!-- =================================================================
             5. MOCK PAPER PREVIEW (PAGES 1–5 ONLY) + CRITICAL DISCLAIMER
             ================================================================= -->
        <section class="cls-section cls-preview-section" id="preview" aria-labelledby="preview-heading">
            <div class="cls-container">
                
                <div class="cls-section-head text-center">
                    <span class="cls-eyebrow"><i class="fas fa-file-lines" aria-hidden="true"></i> Practice Paper Viewer</span>
                    <h2 class="cls-section-title" id="preview-heading">MOCK PAPER 2027</h2>
                    <p class="cls-section-subheading">Edexcel College Practice Examination</p>
                    <p class="cls-section-desc">
                        Preview Pages 1–5 of our structured mock paper format before joining the class.
                    </p>
                </div>

                <!-- Prominent Legal & Academic Disclaimer -->
                <div class="cls-disclaimer-notice" role="note">
                    <div class="cls-dn-icon"><i class="fas fa-shield-halved" aria-hidden="true"></i></div>
                    <div class="cls-dn-content">
                        <strong>IMPORTANT NOTICE:</strong>
                        <p>This is an original Edexcel College practice/mock paper prepared for student preparation. It is not an official Pearson Edexcel examination paper.</p>
                    </div>
                </div>

                <!-- PDF Viewer Shell -->
                <div class="cls-viewer-shell" id="pdfViewerApp" data-pdf-url="<?= e($pdfResolvedUrl) ?>">
                    
                    <!-- Viewer Toolbar -->
                    <div class="cls-viewer-toolbar">
                        <div class="cls-toolbar-left">
                            <span class="cls-doc-type"><i class="fas fa-file-shield" aria-hidden="true"></i> MOCK PAPER PREVIEW</span>
                            <span class="cls-doc-notice" id="docNoticeText">Showing Pages 1–5 Only</span>
                        </div>

                        <!-- Page Selector Tabs (Pages 1 to 5) -->
                        <div class="cls-page-tabs" role="tablist" aria-label="Page Selection">
                            <button type="button" class="cls-page-tab is-active" data-page="1" role="tab" aria-selected="true" id="tab-p1">PAGE 1</button>
                            <button type="button" class="cls-page-tab" data-page="2" role="tab" aria-selected="false" id="tab-p2">PAGE 2</button>
                            <button type="button" class="cls-page-tab" data-page="3" role="tab" aria-selected="false" id="tab-p3">PAGE 3</button>
                            <button type="button" class="cls-page-tab" data-page="4" role="tab" aria-selected="false" id="tab-p4">PAGE 4</button>
                            <button type="button" class="cls-page-tab" data-page="5" role="tab" aria-selected="false" id="tab-p5">PAGE 5</button>
                        </div>

                        <!-- Zoom & Navigation Controls -->
                        <div class="cls-toolbar-right">
                            <button type="button" class="cls-tool-btn" id="prevPageBtn" title="Previous Page" aria-label="Previous Page">
                                <i class="fas fa-chevron-left" aria-hidden="true"></i>
                            </button>
                            <span class="cls-page-indicator" id="pageIndicator" aria-live="polite">Page 1 of 5</span>
                            <button type="button" class="cls-tool-btn" id="nextPageBtn" title="Next Page" aria-label="Next Page">
                                <i class="fas fa-chevron-right" aria-hidden="true"></i>
                            </button>
                            <span class="cls-toolbar-sep"></span>
                            <button type="button" class="cls-tool-btn" id="zoomOutBtn" title="Zoom Out" aria-label="Zoom Out">
                                <i class="fas fa-magnifying-glass-minus" aria-hidden="true"></i>
                            </button>
                            <span class="cls-zoom-display" id="zoomDisplay">100%</span>
                            <button type="button" class="cls-tool-btn" id="zoomInBtn" title="Zoom In" aria-label="Zoom In">
                                <i class="fas fa-magnifying-glass-plus" aria-hidden="true"></i>
                            </button>
                            <button type="button" class="cls-tool-btn" id="zoomResetBtn" title="Reset Zoom" aria-label="Fit Page">
                                <i class="fas fa-arrows-rotate" aria-hidden="true"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Viewer Viewport Canvas & Interactive Examination Layout -->
                    <div class="cls-viewer-viewport" id="viewerViewport">
                        
                        <!-- PDF.js Canvas Container (Renders MOCK_1_preview.pdf pages 1-5) -->
                        <div class="cls-pdf-canvas-container" id="pdfCanvasContainer">
                            <canvas id="pdfCanvas" class="cls-pdf-canvas"></canvas>
                        </div>

                        <!-- Fallback Interactive Examination Paper Mockup (Pages 1 through 5) -->
                        <div class="cls-mock-doc" id="interactiveMockDoc" data-active-page="1" style="display:none;">
                            
                            <!-- PAGE 1: Cover & Instructions -->
                            <div class="cls-exam-page cls-page-1 is-visible" data-page-number="1">
                                <div class="cls-exam-border">
                                    <div class="cls-exam-top">
                                        <div class="cls-exam-brand">
                                            <strong>Edexcel College</strong>
                                            <span>Practice Mock Examination 2027</span>
                                        </div>
                                        <div class="cls-exam-paper-code">
                                            <span>Paper Reference</span>
                                            <strong>4IT1 / 01M</strong>
                                        </div>
                                    </div>

                                    <div class="cls-exam-title-box">
                                        <span class="cls-exam-level">Information and Communication Technology (ICT)</span>
                                        <h4 class="cls-exam-spec">Paper 1: Written Mock Paper</h4>
                                        <div class="cls-exam-meta-row">
                                            <span>Target: May/June 2027</span>
                                            <span>Time: 1 hour 30 minutes</span>
                                            <span>Total Marks: 100</span>
                                        </div>
                                    </div>

                                    <div class="cls-candidate-box">
                                        <div class="cls-cb-row">
                                            <div class="cls-cb-col">
                                                <label>Candidate Surname:</label>
                                                <div class="cls-cb-line"></div>
                                            </div>
                                            <div class="cls-cb-col">
                                                <label>Other Names:</label>
                                                <div class="cls-cb-line"></div>
                                            </div>
                                        </div>
                                        <div class="cls-cb-numbers">
                                            <div>Centre Number: <span class="cls-box-cells"><span></span><span></span><span></span><span></span><span></span></span></div>
                                            <div>Candidate Number: <span class="cls-box-cells"><span></span><span></span><span></span><span></span></span></div>
                                        </div>
                                    </div>

                                    <div class="cls-instructions-box">
                                        <h5>Instructions to Candidates</h5>
                                        <ul>
                                            <li>Use black ink or ball-point pen. Fill in candidate boxes above.</li>
                                            <li>Answer <strong>all questions</strong> in Section A and Section B.</li>
                                            <li>Answer the questions in the spaces provided.</li>
                                        </ul>
                                    </div>

                                    <div class="cls-page-footer-note">
                                        <span>Turn over for Section A &rarr;</span>
                                        <span class="cls-page-number-tag">Page 1 of 5 (Preview)</span>
                                    </div>
                                </div>
                            </div>

                            <!-- PAGE 2: Section A Questions -->
                            <div class="cls-exam-page cls-page-2" data-page-number="2" style="display:none;">
                                <div class="cls-exam-border">
                                    <div class="cls-exam-page-header">
                                        <span>Edexcel College Mock Paper 2027</span>
                                        <span>Section A</span>
                                    </div>
                                    <div class="cls-question-block">
                                        <h5>Question 1</h5>
                                        <p class="cls-q-text">A student designs a cloud backup protocol for revision materials.</p>
                                        <div class="cls-sub-question">
                                            <p><strong>(a)</strong> State two benefits of cloud storage over local external storage. (2 marks)</p>
                                        </div>
                                    </div>
                                    <div class="cls-page-footer-note">
                                        <span>Page 2 of 5 (Preview)</span>
                                    </div>
                                </div>
                            </div>

                            <!-- PAGE 3: Scenario Questions -->
                            <div class="cls-exam-page cls-page-3" data-page-number="3" style="display:none;">
                                <div class="cls-exam-border">
                                    <div class="cls-exam-page-header">
                                        <span>Edexcel College Mock Paper 2027</span>
                                        <span>Section A (Continued)</span>
                                    </div>
                                    <div class="cls-question-block">
                                        <h5>Question 2</h5>
                                        <p class="cls-q-text">Network topology architecture and security analysis.</p>
                                    </div>
                                    <div class="cls-page-footer-note">
                                        <span>Page 3 of 5 (Preview)</span>
                                    </div>
                                </div>
                            </div>

                            <!-- PAGE 4: Algorithmic Thinking -->
                            <div class="cls-exam-page cls-page-4" data-page-number="4" style="display:none;">
                                <div class="cls-exam-border">
                                    <div class="cls-exam-page-header">
                                        <span>Edexcel College Mock Paper 2027</span>
                                        <span>Section B</span>
                                    </div>
                                    <div class="cls-question-block">
                                        <h5>Question 3</h5>
                                        <p class="cls-q-text">Structured pseudocode analysis and flow logic verification.</p>
                                    </div>
                                    <div class="cls-page-footer-note">
                                        <span>Page 4 of 5 (Preview)</span>
                                    </div>
                                </div>
                            </div>

                            <!-- PAGE 5: Data Analysis -->
                            <div class="cls-exam-page cls-page-5" data-page-number="5" style="display:none;">
                                <div class="cls-exam-border">
                                    <div class="cls-exam-page-header">
                                        <span>Edexcel College Mock Paper 2027</span>
                                        <span>Section B (Continued)</span>
                                    </div>
                                    <div class="cls-question-block">
                                        <h5>Question 4</h5>
                                        <p class="cls-q-text">Database schema normalization and relational queries.</p>
                                    </div>
                                    <div class="cls-page-footer-note">
                                        <span>Page 5 of 5 (Preview)</span>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>

                    <!-- Viewer Footer with Enrollment Explanation -->
                    <div class="cls-viewer-foot">
                        <div class="cls-vf-status">
                            <span class="cls-vf-dot"></span>
                            <span>The complete paper, Section B, and worked mark schemes are available to enrolled students.</span>
                        </div>
                        <a href="#schedule" class="cls-vf-action">
                            <i class="fab fa-whatsapp" aria-hidden="true"></i> Join Class for Full Papers
                        </a>
                    </div>

                </div>

            </div>
        </section>


        <!-- =================================================================
             6. AWARDS & RECOGNITION SECTION
             ================================================================= -->
        <section class="cls-section cls-teacher-section" id="awards" aria-labelledby="awards-heading">
            <div class="cls-container">
                
                <div class="cls-section-head text-center">
                    <span class="cls-eyebrow"><i class="fas fa-trophy" aria-hidden="true"></i> Faculty Profile</span>
                    <h2 class="cls-section-title" id="awards-heading">Awards &amp; Recognition</h2>
                    <p class="cls-section-desc">
                        Direct instruction by award-winning faculty committed to academic rigor, syllabus depth, and student mastery.
                    </p>
                </div>

                <div class="cls-teacher-card">
                    <div class="cls-tc-left">
                        <div class="cls-tc-photo-shell">
                            <img src="<?= e($base) ?>/assets/images/teachers/14.png" alt="Enidu Batuwanthudawe" width="220" height="220" class="cls-tc-photo" loading="lazy">
                            <span class="cls-tc-verified" title="Faculty Instructor"><i class="fas fa-check" aria-hidden="true"></i></span>
                        </div>
                        <div class="cls-tc-name-block">
                            <h3 class="cls-tc-name">Enidu Batuwanthudawe</h3>
                            <span class="cls-tc-role">Lead Faculty — ICT &amp; Computer Science</span>
                            <span class="cls-tc-institution">Edexcel College</span>
                        </div>
                    </div>

                    <div class="cls-tc-right">
                        <h4 class="cls-awards-subtitle">Honours &amp; Faculty Accolades</h4>
                        
                        <div class="cls-awards-grid">
                            
                            <!-- Award 1: 2025 ICONIC Awards -->
                            <article class="cls-award-item">
                                <div class="cls-award-icon-box">
                                    <img src="<?= e($base) ?>/assets/images/award1.png" alt="2025 ICONIC Awards Best Teacher Award" width="72" height="72" class="cls-award-img" loading="lazy">
                                </div>
                                <div class="cls-award-content">
                                    <span class="cls-award-year">2025</span>
                                    <h5 class="cls-award-title">Best Teacher Award</h5>
                                    <p class="cls-award-org">ICONIC Awards</p>
                                </div>
                            </article>

                            <!-- Award 2: 2026 Asian Achievers -->
                            <article class="cls-award-item">
                                <div class="cls-award-icon-box">
                                    <img src="<?= e($base) ?>/assets/images/award2.png" alt="2026 Asian Achievers Best Teacher Award" width="72" height="72" class="cls-award-img" loading="lazy">
                                </div>
                                <div class="cls-award-content">
                                    <span class="cls-award-year">2026</span>
                                    <h5 class="cls-award-title">Best Teacher Award</h5>
                                    <p class="cls-award-org">Asian Achievers</p>
                                </div>
                            </article>

                        </div>

                        <p class="cls-awards-note">
                            Recognised for delivering structured syllabus preparation, clear examination strategies, and consistent student achievement.
                        </p>
                    </div>
                </div>

            </div>
        </section>


        <!-- =================================================================
             7. WHY MOCK PAPERS? (6 CARDS)
             ================================================================= -->
        <section class="cls-section cls-why-section" id="why" aria-labelledby="why-heading">
            <div class="cls-container">
                
                <div class="cls-section-head text-center">
                    <span class="cls-eyebrow"><i class="fas fa-graduation-cap" aria-hidden="true"></i> Educational Foundations</span>
                    <h2 class="cls-section-title" id="why-heading">WHY MOCK PAPER PRACTICE MATTERS</h2>
                    <p class="cls-section-desc">
                        Six key educational pillars that turn revision into measurable examination performance.
                    </p>
                </div>

                <div class="cls-why-grid">
                    
                    <article class="cls-why-card">
                        <div class="cls-why-num">01</div>
                        <div class="cls-why-icon"><i class="fas fa-pen-ruler" aria-hidden="true"></i></div>
                        <h3 class="cls-why-card-title">EXAM-STYLE PRACTICE</h3>
                        <p class="cls-why-card-text">
                            Practise answering questions in a format similar to the examination environment.
                        </p>
                    </article>

                    <article class="cls-why-card">
                        <div class="cls-why-num">02</div>
                        <div class="cls-why-icon"><i class="fas fa-stopwatch" aria-hidden="true"></i></div>
                        <h3 class="cls-why-card-title">TIME MANAGEMENT</h3>
                        <p class="cls-why-card-text">
                            Develop awareness of how to manage time during a full paper.
                        </p>
                    </article>

                    <article class="cls-why-card">
                        <div class="cls-why-num">03</div>
                        <div class="cls-why-icon"><i class="fas fa-magnifying-glass-chart" aria-hidden="true"></i></div>
                        <h3 class="cls-why-card-title">IDENTIFY WEAK AREAS</h3>
                        <p class="cls-why-card-text">
                            Discover topics and question types that need additional revision.
                        </p>
                    </article>

                    <article class="cls-why-card">
                        <div class="cls-why-num">04</div>
                        <div class="cls-why-icon"><i class="fas fa-arrows-spin" aria-hidden="true"></i></div>
                        <h3 class="cls-why-card-title">LEARN FROM MISTAKES</h3>
                        <p class="cls-why-card-text">
                            Use corrections and feedback to understand where marks may be lost.
                        </p>
                    </article>

                    <article class="cls-why-card">
                        <div class="cls-why-num">05</div>
                        <div class="cls-why-icon"><i class="fas fa-shield-halved" aria-hidden="true"></i></div>
                        <h3 class="cls-why-card-title">BUILD CONFIDENCE</h3>
                        <p class="cls-why-card-text">
                            Become more familiar with the experience of completing a paper under time pressure.
                        </p>
                    </article>

                    <article class="cls-why-card">
                        <div class="cls-why-num">06</div>
                        <div class="cls-why-icon"><i class="fas fa-list-check" aria-hidden="true"></i></div>
                        <h3 class="cls-why-card-title">STRUCTURED PREPARATION</h3>
                        <p class="cls-why-card-text">
                            Turn revision into a consistent preparation process.
                        </p>
                    </article>

                </div>

            </div>
        </section>


        <!-- =================================================================
             8. PARENT + STUDENT PSYCHOLOGICAL PERSPECTIVE
             ================================================================= -->
        <section class="cls-section cls-perspective-section" id="perspective" aria-labelledby="perspective-heading">
            <div class="cls-container">
                
                <div class="cls-perspective-shell">
                    
                    <div class="cls-perspective-head text-center">
                        <span class="cls-eyebrow"><i class="fas fa-bullseye" aria-hidden="true"></i> Real Exam Readiness</span>
                        <h2 class="cls-section-title" id="perspective-heading">
                            DON'T WAIT UNTIL EXAM DAY TO DISCOVER WHAT NEEDS IMPROVEMENT.
                        </h2>
                        <p class="cls-perspective-lead">
                            A mock paper is more than another paper to complete. It gives students an opportunity to practise under exam-style conditions, identify gaps in their preparation, learn from mistakes and become more familiar with the demands of the examination.
                        </p>
                    </div>

                    <div class="cls-perspective-grid">
                        
                        <article class="cls-perspective-card cls-card-parent">
                            <div class="cls-persp-icon"><i class="fas fa-heart" aria-hidden="true"></i></div>
                            <span class="cls-persp-badge">FOR PARENTS</span>
                            <h3 class="cls-persp-title">Guidance, Security &amp; Peace of Mind</h3>
                            <blockquote class="cls-persp-quote">
                                "Every parent wants their child to enter the examination room prepared. Studying the syllabus is important — but students also benefit from practising how to apply their knowledge under time pressure."
                            </blockquote>
                            <ul class="cls-persp-points">
                                <li><i class="fas fa-check" aria-hidden="true"></i> Practice examination timing under realistic conditions</li>
                                <li><i class="fas fa-check" aria-hidden="true"></i> Objective marking highlights areas for focused revision</li>
                                <li><i class="fas fa-check" aria-hidden="true"></i> Reduces last-minute examination anxiety</li>
                            </ul>
                        </article>

                        <article class="cls-perspective-card cls-card-student">
                            <div class="cls-persp-icon"><i class="fas fa-bolt" aria-hidden="true"></i></div>
                            <span class="cls-persp-badge">FOR STUDENTS</span>
                            <h3 class="cls-persp-title">Clarity, Resilience &amp; Real Growth</h3>
                            <blockquote class="cls-persp-quote">
                                "Know where you stand before the real exam. Practise. Make mistakes. Understand them. Improve. Then practise again."
                            </blockquote>
                            <ul class="cls-persp-points">
                                <li><i class="fas fa-check" aria-hidden="true"></i> Practise common examination command words (State, Explain, Discuss)</li>
                                <li><i class="fas fa-check" aria-hidden="true"></i> Turn errors into marks through live walkthroughs</li>
                                <li><i class="fas fa-check" aria-hidden="true"></i> Enter the exam room knowing you've done this before</li>
                            </ul>
                        </article>

                    </div>

                    <div class="cls-highlight-bar text-center">
                        <strong class="cls-highlight-text">
                            PRACTICE BEFORE THE REAL EXAMINATION.
                        </strong>
                        <div class="cls-highlight-cta">
                            <a href="#schedule" class="cls-btn cls-btn-primary cls-btn-pulse">
                                <i class="fas fa-list-check" aria-hidden="true"></i>
                                <span>CHOOSE YOUR CLASS</span>
                            </a>
                        </div>
                    </div>

                </div>

            </div>
        </section>


        <!-- =================================================================
             9. CLEAR LOCATION CARDS (KANDY, KURUNEGALA, ONLINE)
             ================================================================= -->
        <section class="cls-section cls-locations-section" id="locations" aria-labelledby="loc-heading">
            <div class="cls-container">
                
                <div class="cls-section-head text-center">
                    <span class="cls-eyebrow"><i class="fas fa-map-location-dot" aria-hidden="true"></i> Attendance Formats</span>
                    <h2 class="cls-section-title" id="loc-heading">THREE CLASS OPTIONS</h2>
                    <p class="cls-section-desc">
                        Choose the option that fits your location: attend physical classes in Kandy (@ Edexcel College) or Kurunegala (@ Apollo Institute), or join live online worldwide.
                    </p>
                </div>

                <div class="cls-loc-grid">
                    
                    <!-- KANDY @ EDEXCEL COLLEGE -->
                    <article class="cls-loc-card">
                        <div class="cls-loc-icon-wrap cls-icon-kandy">
                            <i class="fas fa-building-columns" aria-hidden="true"></i>
                        </div>
                        <h3 class="cls-loc-name">Kandy</h3>
                        <span class="cls-loc-venue-bold">@ Edexcel College</span>
                        <span class="cls-loc-type">Physical Classes</span>
                        <p class="cls-loc-info">
                            On-site mock paper classes conducted at the Edexcel College campus in Kandy. Interactive review and tutor feedback.
                        </p>
                        <div class="cls-loc-meta">
                            <span><i class="far fa-calendar" aria-hidden="true"></i> Sundays (8:00 AM &amp; 10:00 AM)</span>
                            <span><i class="fas fa-map-marker-alt" aria-hidden="true"></i> Katugastota Rd, Kandy</span>
                        </div>
                        <a href="#kandy-section" class="cls-btn cls-btn-sm cls-btn-secondary mt-3">VIEW CLASSES &rarr;</a>
                    </article>

                    <!-- KURUNEGALA @ APOLLO INSTITUTE -->
                    <article class="cls-loc-card">
                        <div class="cls-loc-icon-wrap cls-icon-kurunegala">
                            <i class="fas fa-school" aria-hidden="true"></i>
                        </div>
                        <h3 class="cls-loc-name">Kurunegala</h3>
                        <span class="cls-loc-venue-bold">@ Apollo Institute</span>
                        <span class="cls-loc-type">Physical Classes</span>
                        <p class="cls-loc-info">
                            On-site mock paper classes conducted at Apollo Institute in Kurunegala for North Western Province students.
                        </p>
                        <div class="cls-loc-meta">
                            <span><i class="far fa-calendar" aria-hidden="true"></i> Saturdays (1:30 PM &amp; 3:30 PM)</span>
                            <span><i class="fas fa-map-marker-alt" aria-hidden="true"></i> Apollo Institute, Kurunegala</span>
                        </div>
                        <a href="#kurunegala-section" class="cls-btn cls-btn-sm cls-btn-secondary mt-3">VIEW CLASSES &rarr;</a>
                    </article>

                    <!-- ONLINE CLASSES -->
                    <article class="cls-loc-card cls-loc-featured">
                        <div class="cls-loc-badge-top">Live Everywhere</div>
                        <div class="cls-loc-icon-wrap cls-icon-online">
                            <i class="fas fa-globe" aria-hidden="true"></i>
                        </div>
                        <h3 class="cls-loc-name">Online</h3>
                        <span class="cls-loc-venue-bold">Live Online</span>
                        <span class="cls-loc-type">Live Online Classes</span>
                        <p class="cls-loc-info">
                            Live virtual classes accessible from anywhere in Sri Lanka or internationally. Digital mock papers and live walkthroughs.
                        </p>
                        <div class="cls-loc-meta">
                            <span><i class="fas fa-video" aria-hidden="true"></i> Live Virtual Sessions</span>
                            <span><i class="fas fa-clock" aria-hidden="true"></i> Scheduled Weekly Modules</span>
                        </div>
                        <a href="#online-section" class="cls-btn cls-btn-sm cls-btn-secondary mt-3">VIEW CLASSES &rarr;</a>
                    </article>

                </div>

            </div>
        </section>


        <!-- =================================================================
             10. REGISTRATION & INTAKE FORM (FITS EXISTING ARCHITECTURE)
             ================================================================= -->
        <section class="cls-section cls-register-section" id="register" aria-labelledby="reg-heading">
            <div class="cls-container">
                <div class="cls-register-card">
                    
                    <div class="cls-reg-header text-center">
                        <span class="cls-eyebrow"><i class="fas fa-pen-nib" aria-hidden="true"></i> Enrolment Desk</span>
                        <h2 class="cls-section-title" id="reg-heading">REGISTER FOR MOCK PAPERS 2027</h2>
                        <p class="cls-section-desc">
                            Submit your details below to register for the upcoming mock paper series. Our academic office will assist with batch allocation.
                        </p>
                    </div>

                    <?php if ($leadMsg): ?>
                        <div class="cls-alert cls-alert-success mb-6">
                            <i class="fas fa-check-circle" aria-hidden="true"></i>
                            <div><?= e($leadMsg) ?></div>
                        </div>
                    <?php endif; ?>

                    <?php if ($leadErr): ?>
                        <div class="cls-alert cls-alert-danger mb-6">
                            <i class="fas fa-circle-exclamation" aria-hidden="true"></i>
                            <div><?= e($leadErr) ?></div>
                        </div>
                    <?php endif; ?>

                    <form method="post" action="#register" class="cls-reg-form" id="classRegForm">
                        <input type="hidden" name="action" value="register_lead">
                        <input type="hidden" name="csrf_token" value="<?= e(function_exists('generate_csrf_token') ? generate_csrf_token() : '') ?>">
                        <input type="hidden" name="visitor_hash" id="reg_visitor_hash" value="">
                        <input type="hidden" name="session_hash" id="reg_session_hash" value="">

                        <div class="cls-form-grid">
                            <div class="cls-form-group">
                                <label for="reg_student_name">Student Name <span class="cls-req">*</span></label>
                                <input type="text" id="reg_student_name" name="student_name" required placeholder="e.g. Sahan Perera" class="cls-input">
                            </div>

                            <div class="cls-form-group">
                                <label for="reg_parent_name">Parent / Guardian Name <span class="cls-req">*</span></label>
                                <input type="text" id="reg_parent_name" name="parent_name" required placeholder="e.g. Mr. Perera" class="cls-input">
                            </div>

                            <div class="cls-form-group">
                                <label for="reg_phone">WhatsApp Number <span class="cls-req">*</span></label>
                                <input type="tel" id="reg_phone" name="whatsapp_number" required placeholder="e.g. 077 123 4567" class="cls-input">
                            </div>

                            <div class="cls-form-group">
                                <label for="reg_subject">Subject <span class="cls-req">*</span></label>
                                <select id="reg_subject" name="subject_interest" class="cls-select" required>
                                    <option value="ICT">ICT</option>
                                    <option value="Computer Science">Computer Science</option>
                                    <option value="Both ICT & Computer Science">Both ICT &amp; Computer Science</option>
                                </select>
                            </div>

                            <div class="cls-form-group">
                                <label for="reg_format">Class Format <span class="cls-req">*</span></label>
                                <select id="reg_format" name="class_format" class="cls-select" required>
                                    <option value="Physical Class">Physical Class</option>
                                    <option value="Live Online Class">Live Online Class</option>
                                </select>
                            </div>

                            <div class="cls-form-group">
                                <label for="reg_location">Location <span class="cls-req">*</span></label>
                                <select id="reg_location" name="location" class="cls-select" required>
                                    <option value="Kandy @ Edexcel College">Kandy @ Edexcel College</option>
                                    <option value="Kurunegala @ Apollo Institute">Kurunegala @ Apollo Institute</option>
                                    <option value="Online Worldwide">Online Worldwide</option>
                                </select>
                            </div>

                            <div class="cls-form-group">
                                <label for="reg_grade">Grade / Year <span class="cls-req">*</span></label>
                                <select id="reg_grade" name="grade" class="cls-select" required>
                                    <option value="Pearson Edexcel IGCSE 2027">Pearson Edexcel IGCSE 2027</option>
                                    <option value="Pearson Edexcel IAL AS / A2 2027">Pearson Edexcel IAL AS / A2 2027</option>
                                    <option value="Grade 10 Foundation">Grade 10 Foundation</option>
                                </select>
                            </div>

                            <div class="cls-form-group">
                                <label for="reg_school">Current School <span class="cls-req">*</span></label>
                                <input type="text" id="reg_school" name="school" required placeholder="e.g. Trinity College, Kingswood, Maliyadeva, etc." class="cls-input">
                            </div>
                        </div>

                        <div class="cls-reg-foot">
                            <button type="submit" class="cls-btn cls-btn-primary cls-btn-lg" data-track-action="cta_click" data-track-target="submit_registration_btn">
                                <i class="fas fa-paper-plane" aria-hidden="true"></i>
                                <span>SUBMIT REGISTRATION</span>
                            </button>
                            <p class="cls-reg-note">
                                <i class="fas fa-circle-check" aria-hidden="true"></i> <strong>Registration is free. Class fees will be confirmed before enrolment.</strong> Class fees: <em>Contact us for current class fees</em>. Existing students can also <a href="<?= e($base) ?>/student/register.php">register on student portal</a>.
                            </p>
                        </div>
                    </form>

                </div>
            </div>
        </section>


        <!-- =================================================================
             11. FINAL CALL TO ACTION (4 DIRECT WHATSAPP BUTTONS)
             ================================================================= -->
        <section class="cls-section cls-final-cta-section" id="final-cta" aria-labelledby="final-cta-heading">
            <div class="cls-container">
                <div class="cls-final-cta-box">
                    
                    <span class="cls-eyebrow cls-eyebrow-light"><i class="fas fa-rocket" aria-hidden="true"></i> Ready to Begin?</span>
                    
                    <h2 class="cls-final-cta-title" id="final-cta-heading">
                        READY TO START YOUR MOCK PAPER PREPARATION?
                    </h2>

                    <p class="cls-final-cta-text">
                        Choose your subject and location, then join the relevant WhatsApp group for class information and updates.
                    </p>

                    <!-- 4 Clear WhatsApp Buttons With Correct Physical Venues -->
                    <div class="cls-final-buttons-grid">
                        
                        <!-- Kandy ICT -->
                        <a
                            href="https://chat.whatsapp.com/EGGGbKauJTw0v7YWP0oiCK"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="cls-final-btn"
                            data-track-action="whatsapp_click"
                            data-track-target="final_kandy_ict_wa"
                        >
                            <i class="fab fa-whatsapp" aria-hidden="true"></i>
                            <div class="cls-fb-text">
                                <span class="cls-fb-loc">KANDY @ EDEXCEL COLLEGE</span>
                                <strong>JOIN KANDY ICT GROUP</strong>
                                <span class="cls-fb-time">Sunday 8:00 AM</span>
                            </div>
                        </a>

                        <!-- Kandy CS -->
                        <a
                            href="https://chat.whatsapp.com/LC7A8Wm0SuY8LwElUb1KMW"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="cls-final-btn"
                            data-track-action="whatsapp_click"
                            data-track-target="final_kandy_cs_wa"
                        >
                            <i class="fab fa-whatsapp" aria-hidden="true"></i>
                            <div class="cls-fb-text">
                                <span class="cls-fb-loc">KANDY @ EDEXCEL COLLEGE</span>
                                <strong>JOIN KANDY CS GROUP</strong>
                                <span class="cls-fb-time">Sunday 10:00 AM</span>
                            </div>
                        </a>

                        <!-- Kurunegala ICT -->
                        <a
                            href="https://chat.whatsapp.com/GW8KGkhJXq03gblLKOTWJm"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="cls-final-btn"
                            data-track-action="whatsapp_click"
                            data-track-target="final_kurunegala_ict_wa"
                        >
                            <i class="fab fa-whatsapp" aria-hidden="true"></i>
                            <div class="cls-fb-text">
                                <span class="cls-fb-loc">KURUNEGALA @ APOLLO INSTITUTE</span>
                                <strong>JOIN KURUNEGALA ICT GROUP</strong>
                                <span class="cls-fb-time">Saturday 3:30 PM</span>
                            </div>
                        </a>

                        <!-- Kurunegala CS -->
                        <a
                            href="https://chat.whatsapp.com/JLvWComjvADH27bALjqF9U"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="cls-final-btn"
                            data-track-action="whatsapp_click"
                            data-track-target="final_kurunegala_cs_wa"
                        >
                            <i class="fab fa-whatsapp" aria-hidden="true"></i>
                            <div class="cls-fb-text">
                                <span class="cls-fb-loc">KURUNEGALA @ APOLLO INSTITUTE</span>
                                <strong>JOIN KURUNEGALA CS GROUP</strong>
                                <span class="cls-fb-time">Saturday 1:30 PM</span>
                            </div>
                        </a>

                    </div>

                    <!-- Online Subtext & Separate Explicit Online Actions -->
                    <div class="cls-final-online-strip">
                        <span class="cls-os-tag"><i class="fas fa-globe" aria-hidden="true"></i> LIVE ONLINE STUDENTS WORLDWIDE</span>
                        <p class="cls-os-note">
                            Online students use the official subject WhatsApp group (same as the relevant class) to receive live session links, virtual classroom credentials, and digital mock papers.
                        </p>
                        <div class="cls-final-online-btns">
                            <a
                                href="https://chat.whatsapp.com/GW8KGkhJXq03gblLKOTWJm"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="cls-final-btn cls-final-btn-online"
                                data-track-action="whatsapp_click"
                                data-track-target="final_online_ict_wa"
                            >
                                <i class="fab fa-whatsapp" aria-hidden="true"></i>
                                <div class="cls-fb-text">
                                    <span class="cls-fb-loc">LIVE ONLINE &bull; SRI LANKA (UTC+5:30)</span>
                                    <strong>ONLINE ICT — JOIN WHATSAPP</strong>
                                    <span class="cls-fb-time">Schedule: Contact us</span>
                                </div>
                            </a>
                            <a
                                href="https://chat.whatsapp.com/JLvWComjvADH27bALjqF9U"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="cls-final-btn cls-final-btn-online"
                                data-track-action="whatsapp_click"
                                data-track-target="final_online_cs_wa"
                            >
                                <i class="fab fa-whatsapp" aria-hidden="true"></i>
                                <div class="cls-fb-text">
                                    <span class="cls-fb-loc">LIVE ONLINE &bull; SRI LANKA (UTC+5:30)</span>
                                    <strong>ONLINE COMPUTER SCIENCE — JOIN WHATSAPP</strong>
                                    <span class="cls-fb-time">Schedule: Contact us</span>
                                </div>
                            </a>
                        </div>
                    </div>

                </div>
            </div>
        </section>

    </main>

    <!-- =====================================================================
         12. OFFICIAL INSTITUTIONAL FOOTER
         ===================================================================== -->
    <footer class="cls-footer" role="contentinfo">
        <div class="cls-container">
            <div class="cls-footer-grid">
                
                <div class="cls-footer-col cls-footer-brand-col">
                    <div class="cls-footer-brand">
                        <span class="cls-brand-icon"><i class="fas fa-landmark" aria-hidden="true"></i></span>
                        <strong>Edexcel College</strong>
                    </div>
                    <p class="cls-footer-desc">
                        Pearson Edexcel IGCSE and International A Level tuition and structured exam preparation. Live online classes worldwide and scheduled physical classes at Kandy @ Edexcel College and Kurunegala @ Apollo Institute.
                    </p>
                    <div class="cls-footer-contacts">
                        <p><i class="fas fa-map-marker-alt" aria-hidden="true"></i> No 83 Katugatota Road, Kandy, Sri Lanka</p>
                        <p><i class="fas fa-phone" aria-hidden="true"></i> <a href="tel:+94785858585">+94 78 585 8585</a></p>
                        <p><i class="fas fa-envelope" aria-hidden="true"></i> <a href="mailto:info@edexcel.college">info@edexcel.college</a></p>
                    </div>
                </div>

                <div class="cls-footer-col">
                    <h4 class="cls-footer-head">Academic Pathways</h4>
                    <ul class="cls-footer-links">
                        <li><a href="<?= e($base) ?>/online-classes">Online Classes Worldwide</a></li>
                        <li><a href="<?= e($base) ?>/subjects">Subject Directory</a></li>
                        <li><a href="<?= e($base) ?>/edexcel-classes">Edexcel Classes Overview</a></li>
                        <li><a href="<?= e($base) ?>/edexcel-o-level">Pearson Edexcel IGCSE</a></li>
                        <li><a href="<?= e($base) ?>/edexcel-a-level">International A Level (IAL)</a></li>
                        <li><a href="<?= e($base) ?>/exam-preparation">Past Paper Preparation</a></li>
                    </ul>
                </div>

                <div class="cls-footer-col">
                    <h4 class="cls-footer-head">College Information</h4>
                    <ul class="cls-footer-links">
                        <li><a href="<?= e($base) ?>/about">About Edexcel College</a></li>
                        <li><a href="<?= e($base) ?>/teachers/">Specialist Faculty</a></li>
                        <li><a href="<?= e($base) ?>/locations/kandy">Kandy Campus</a></li>
                        <li><a href="<?= e($base) ?>/faq">Frequently Asked Questions</a></li>
                        <li><a href="<?= e($base) ?>/contact">Contact Academic Office</a></li>
                    </ul>
                </div>

                <div class="cls-footer-col">
                    <h4 class="cls-footer-head">Portals &amp; Registration</h4>
                    <ul class="cls-footer-links">
                        <li><a href="<?= e($base) ?>/student/login.php"><i class="fas fa-user-graduate" aria-hidden="true"></i> Student Portal</a></li>
                        <li><a href="<?= e($base) ?>/student/register.php"><i class="fas fa-user-plus" aria-hidden="true"></i> Portal Registration</a></li>
                        <li><a href="<?= e($base) ?>/parent/login.php"><i class="fas fa-users" aria-hidden="true"></i> Parent Portal</a></li>
                        <li><a href="<?= e($base) ?>/portal/login.php"><i class="fas fa-lock" aria-hidden="true"></i> Staff Login</a></li>
                    </ul>
                </div>

            </div>

            <div class="cls-disclaimer-box">
                <p>
                    <strong>Independent Academic Institution:</strong> Edexcel College is an independent educational centre providing tuition and exam preparation. We are not owned by, affiliated with, or endorsed by Pearson Education Ltd. Pearson and Edexcel are registered trademarks of Pearson Education Ltd. Official specifications are available at qualifications.pearson.com.
                </p>
            </div>

            <div class="cls-footer-bottom">
                <p>&copy; <?= date('Y') ?> Edexcel College. All rights reserved.</p>
                <div class="cls-legal-links">
                    <a href="<?= e($base) ?>/terms">Terms &amp; Conditions</a>
                    <span>&middot;</span>
                    <a href="<?= e($base) ?>/privacy-policy">Privacy Policy</a>
                    <span>&middot;</span>
                    <a href="<?= e($base) ?>/refund-policy">Refund Policy</a>
                </div>
            </div>

        </div>
    </footer>

    <!-- Floating Back to Top Button -->
    <button type="button" class="cls-back-to-top" id="clsBackToTop" aria-label="Back to top">
        <i class="fas fa-arrow-up" aria-hidden="true"></i>
    </button>

    <!-- Floating WhatsApp Action -->
    <a
        href="https://chat.whatsapp.com/JjVSFWUIro19KTtDRRsc8W"
        target="_blank"
        rel="noopener noreferrer"
        class="cls-floating-wa"
        aria-label="Join Mock Paper WhatsApp Group"
    >
        <i class="fab fa-whatsapp" aria-hidden="true"></i>
        <span class="cls-wa-tooltip">Join WhatsApp Group</span>
    </a>

    <!-- PDF.js Vendor Script (Pre-installed in codebase) -->
    <script src="<?= e($base) ?>/assets/vendor/pdfjs/pdf.min.js"></script>
    <script>
        if (window.pdfjsLib) {
            window.pdfjsLib.GlobalWorkerOptions.workerSrc = '<?= e($base) ?>/assets/vendor/pdfjs/pdf.worker.min.js';
        }
    </script>

    <!-- Page Interaction Script -->
    <script src="<?= e($base) ?>/class/class.js?v=<?= filemtime(__DIR__ . '/class.js') ?>" defer></script>

</body>
</html>
