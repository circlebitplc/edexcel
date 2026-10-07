<?php
declare(strict_types=1);

/*
 * Public Teacher Profile
 * Uses the shared Edexcel College header/footer.
 */

require_once __DIR__ . '/../config/database.php';

function tp_e(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function tp_initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    $letters = '';

    foreach ($parts as $part) {
        if ($part !== '') {
            $letters .= strtoupper(substr($part, 0, 1));
        }
    }

    return substr($letters ?: 'TE', 0, 2);
}

function tp_photo_url(int $teacherId, ?string $stored = null): string
{
    $root = __DIR__ . '/../';
    $candidates = [];

    if ($stored) {
        $stored = ltrim(trim($stored), '/');
        $candidates[] = $stored;
        $candidates[] = 'assets/images/teachers/' . basename($stored);
        $candidates[] = 'uploads/teachers/' . basename($stored);
    }

    foreach (['png','jpg','jpeg','webp','gif'] as $ext) {
        $candidates[] = "assets/images/teachers/{$teacherId}.{$ext}";
        $candidates[] = "uploads/teachers/{$teacherId}.{$ext}";
    }

    foreach (array_unique($candidates) as $relative) {
        if (is_file($root . $relative)) {
            return '../' . ltrim($relative, '/');
        }
    }

    return '';
}

function tp_background_url(?string $stored): string
{
    if (!$stored) {
        return '';
    }

    $stored = ltrim(trim($stored), '/');
    $root = __DIR__ . '/../';

    $candidates = [
        $stored,
        'uploads/teachers/backgrounds/' . basename($stored),
        'assets/images/teachers/background/' . basename($stored)
    ];

    foreach (array_unique($candidates) as $relative) {
        if (is_file($root . $relative)) {
            return '../' . ltrim($relative, '/');
        }
    }

    return '';
}

$teacherId = (int)($_GET['id'] ?? 0);

if ($teacherId < 1) {
    http_response_code(400);
    exit('Teacher ID required.');
}

$stmt = $pdo->prepare("
    SELECT
        t.id,
        t.name,
        t.email,
        t.phone,
        t.photo,
        p.bio,
        p.qualifications,
        p.experience_years,
        p.achievements,
        p.profile_background,
        p.website,
        p.facebook,
        p.instagram,
        p.youtube
    FROM teachers t
    LEFT JOIN teacher_profiles p
        ON p.teacher_id = t.id
    WHERE t.id = ?
      AND t.deleted_at IS NULL
    LIMIT 1
");

$stmt->execute([$teacherId]);
$teacher = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$teacher) {
    http_response_code(404);
    exit('Teacher not found.');
}

$stmt = $pdo->prepare("
    SELECT
        s.id,
        s.name
    FROM subjects s
    INNER JOIN teacher_subjects ts
        ON ts.subject_id = s.id
    WHERE ts.teacher_id = ?
    ORDER BY s.name
");

$stmt->execute([$teacherId]);
$subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);

$monday = date(
    'Y-m-d',
    strtotime('monday this week')
);

$sunday = date(
    'Y-m-d',
    strtotime('sunday this week')
);

$sql = "
    SELECT
        t.id,
        t.date,
        t.start_time,
        t.end_time,
        r.name AS room_name,
        c.name AS class_name,
        sub.name AS subject_name,
        COALESCE(
            cw_specific.whatsapp_link,
            cw_general.whatsapp_link
        ) AS whatsapp_link
    FROM timetable t

    INNER JOIN student_classes c
        ON c.id = t.class_id
        AND c.deleted_at IS NULL

    INNER JOIN subjects sub
        ON sub.id = t.subject_id
        AND sub.deleted_at IS NULL

    INNER JOIN rooms r
        ON r.id = t.room_id
        AND r.deleted_at IS NULL

    LEFT JOIN class_teacher_whatsapp cw_specific
        ON cw_specific.class_id = t.class_id
        AND cw_specific.teacher_id = t.teacher_id
        AND cw_specific.subject_id = t.subject_id

    LEFT JOIN class_teacher_whatsapp cw_general
        ON cw_general.class_id = t.class_id
        AND cw_general.teacher_id = t.teacher_id
        AND cw_general.subject_id IS NULL

    WHERE t.teacher_id = ?
      AND t.deleted_at IS NULL
      AND t.date BETWEEN ? AND ?

    ORDER BY
        t.date ASC,
        t.start_time ASC
";

$stmt = $pdo->prepare($sql);
$stmt->execute([
    $teacherId,
    $monday,
    $sunday
]);

$timetable = $stmt->fetchAll(PDO::FETCH_ASSOC);

$photoUrl = tp_photo_url(
    $teacherId,
    $teacher['photo'] ?? null
);

$backgroundUrl = tp_background_url(
    $teacher['profile_background'] ?? null
);

$wa = preg_replace(
    '/\D+/',
    '',
    (string)($teacher['phone'] ?? '')
);

$waUrl = $wa
    ? 'https://wa.me/' . $wa
    : '';

$subjectsCount = count($subjects);
$weeklyClasses = count($timetable);

$subjectNames = array_values(array_filter(array_map(
    static fn(array $row): string => trim((string)($row['name'] ?? '')),
    $subjects
)));

$experienceYears = null;
if (isset($teacher['experience_years']) && $teacher['experience_years'] !== '' && $teacher['experience_years'] !== null) {
    $experienceYears = (int)$teacher['experience_years'];
    if ($experienceYears < 1) {
        $experienceYears = null;
    }
}

if (!function_exists('seo_teacher_page_title')) {
    require_once __DIR__ . '/../includes/seo.php';
}

$subjectLabel = seo_teacher_subject_label($subjectNames);
$photoAlt = seo_teacher_photo_alt((string)$teacher['name'], $subjectNames);
$canonical_url = seo_teacher_profile_url((int)$teacher['id']);
$page_title = seo_teacher_page_title((string)$teacher['name'], $subjectNames);
$meta_description = seo_teacher_meta_description(
    (string)$teacher['name'],
    $subjectNames,
    $experienceYears
);
$meta_robots = 'index, follow';
$seo_social = true;
$og_type = 'profile';
$og_image_alt = $photoAlt;

$ogImageAbsolute = '';
if ($photoUrl !== '') {
    $relativePhoto = ltrim(str_replace('\\', '/', (string)$photoUrl), '/');
    $relativePhoto = preg_replace('#^(\.\./)+#', '', $relativePhoto) ?? $relativePhoto;
    $ogImageAbsolute = seo_absolute_url($relativePhoto);
}
$og_image = $ogImageAbsolute !== '' ? $ogImageAbsolute : seo_default_image_url();

$sameAs = array_values(array_filter([
    trim((string)($teacher['website'] ?? '')),
    trim((string)($teacher['facebook'] ?? '')),
    trim((string)($teacher['instagram'] ?? '')),
    trim((string)($teacher['youtube'] ?? '')),
]));

$personDescription = trim((string)($teacher['bio'] ?? ''));
if ($personDescription === '') {
    $personDescription = seo_teacher_fallback_intro((string)$teacher['name'], $subjectNames);
}

$personSchema = seo_person_schema([
    'id' => (int)$teacher['id'],
    'name' => (string)$teacher['name'],
    'url' => $canonical_url,
    'description' => $personDescription,
    'image' => $og_image,
    'email' => '',
    'subjects' => $subjectNames,
    'sameAs' => $sameAs,
    'experienceYears' => $experienceYears,
]);

$head_json_ld = [
    seo_organization_schema($pdo instanceof PDO ? $pdo : null),
    seo_website_schema($pdo instanceof PDO ? $pdo : null),
    $personSchema,
    seo_profile_page_schema(
        $canonical_url,
        (string)$personSchema['@id'],
        (string)$teacher['name'] . ($subjectLabel !== '' ? (' — ' . $subjectLabel . ' Teacher') : ''),
        $meta_description
    ),
    seo_breadcrumb_schema([
        ['name' => 'Home', 'url' => seo_absolute_url('/')],
        ['name' => 'Teachers', 'url' => seo_absolute_url('teachers/')],
        ['name' => (string)$teacher['name'], 'url' => $canonical_url],
    ]),
];

require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../includes/header.php';
?>

<style>
.teacher-public-profile {
    max-width: 1180px;
    margin: 0 auto;
}

.teacher-directory-back {
    margin: 0 0 14px;
}

.teacher-directory-back a {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    color: var(--bs-secondary-color);
    text-decoration: none;
    font-size: .9rem;
    font-weight: 650;
}

.teacher-directory-back a:hover {
    color: var(--bs-body-color);
}

.teacher-profile-hero {
    position: relative;
    min-height: 390px;
    overflow: hidden;
    border-radius: 24px;
    margin-bottom: 22px;
    background:
        linear-gradient(
            135deg,
            #27356f,
            #5161ce
        );
}

.teacher-profile-hero-bg {
    position: absolute;
    inset: 0;
    background-size: cover;
    background-position: center;
}

.teacher-profile-hero-overlay {
    position: absolute;
    inset: 0;
    background:
        linear-gradient(
            90deg,
            rgba(5,9,20,.94),
            rgba(5,9,20,.68) 48%,
            rgba(5,9,20,.20)
        );
}

.teacher-profile-hero-content {
    position: relative;
    z-index: 2;
    min-height: 390px;
    padding: 42px;
    display: flex;
    align-items: flex-end;
    gap: 28px;
    color: #fff;
}

.teacher-profile-avatar {
    width: 154px;
    height: 154px;
    flex: 0 0 154px;
    overflow: hidden;
    border-radius: 50%;
    display: grid;
    place-items: center;
    background: #f5f7fb;
    border: 5px solid rgba(255,255,255,.9);
    box-shadow: 0 15px 40px rgba(0,0,0,.25);
}

.teacher-profile-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.teacher-profile-avatar span {
    color: #5161ce;
    font-size: 2.4rem;
    font-weight: 800;
}

.teacher-profile-kicker {
    font-size: .75rem;
    text-transform: uppercase;
    letter-spacing: .12em;
    font-weight: 800;
    opacity: .82;
}

.teacher-profile-name {
    margin: 5px 0 8px;
    font-size: clamp(2rem,5vw,3.5rem);
    line-height: 1.05;
}

.teacher-profile-subjects {
    display: flex;
    flex-wrap: wrap;
    gap: 7px;
}

.teacher-profile-chip {
    border: 1px solid rgba(255,255,255,.24);
    background: rgba(255,255,255,.11);
    padding: 6px 10px;
    border-radius: 999px;
    font-size: .75rem;
    font-weight: 700;
    color: inherit;
    text-decoration: none;
}

.teacher-profile-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 16px;
}

.teacher-profile-actions a {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    text-decoration: none;
}

.teacher-profile-grid {
    display: grid;
    grid-template-columns: 1.5fr .85fr;
    gap: 18px;
}

.teacher-profile-card {
    padding: 24px;
    border-radius: 20px;
}

.teacher-profile-card h2 {
    margin-top: 0;
    font-size: 1.15rem;
}

.teacher-profile-card p {
    white-space: pre-line;
}

.teacher-profile-stats {
    display: grid;
    grid-template-columns: repeat(3,1fr);
    gap: 10px;
}

.teacher-profile-stat {
    padding: 17px;
    text-align: center;
    border-radius: 15px;
    background: var(--bs-tertiary-bg);
}

.teacher-profile-stat strong {
    display: block;
    font-size: 1.45rem;
}

.teacher-profile-stat span {
    font-size: .72rem;
    color: var(--bs-secondary-color);
}

.teacher-profile-table-wrap {
    overflow-x: auto;
}

.teacher-profile-table {
    width: 100%;
    min-width: 720px;
}

.teacher-profile-table th {
    font-size: .7rem;
    text-transform: uppercase;
}

.teacher-profile-socials {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.teacher-profile-socials a {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    text-decoration: none;
}

.teacher-profile-whatsapp {
    background: #25d366 !important;
    border-color: #25d366 !important;
    color: #fff !important;
}


/* ============================================================
   PUBLIC TEACHER PROFILE POLISH
   ============================================================ */

.teacher-public-profile {
    padding-bottom: 30px;
}

.teacher-profile-hero {
    isolation: isolate;
    box-shadow: 0 20px 55px rgba(20, 30, 60, .16);
}

.teacher-profile-hero::after {
    content: "";
    position: absolute;
    left: 0;
    right: 0;
    bottom: 0;
    height: 35%;
    z-index: 1;
    pointer-events: none;
    background:
        linear-gradient(
            to top,
            rgba(5, 9, 20, .58),
            transparent
        );
}

.teacher-profile-hero-bg {
    transition: transform .6s ease;
}

.teacher-profile-hero:hover .teacher-profile-hero-bg {
    transform: scale(1.025);
}

.teacher-profile-hero-content {
    max-width: 1180px;
    margin: 0 auto;
}

.teacher-profile-name {
    text-shadow: 0 4px 18px rgba(0,0,0,.28);
    font-weight: 850;
}

.teacher-profile-kicker {
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.teacher-profile-kicker::before {
    content: "";
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: #f8b400;
    box-shadow: 0 0 0 5px rgba(248,180,0,.15);
}

.teacher-profile-chip {
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
    transition: transform .2s ease, background .2s ease;
}

.teacher-profile-chip:hover {
    transform: translateY(-2px);
    background: rgba(255,255,255,.18);
}

.teacher-profile-actions a {
    border-radius: 999px;
    padding-left: 15px;
    padding-right: 15px;
    font-weight: 750;
    box-shadow: 0 7px 18px rgba(0,0,0,.12);
}

.teacher-profile-summary {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 12px;
    margin: -2px 0 20px;
}

.teacher-profile-summary-item {
    background: var(--bs-body-bg);
    border: 1px solid var(--bs-border-color);
    border-radius: 16px;
    padding: 15px 18px;
    box-shadow: 0 8px 25px rgba(20,30,60,.07);
    display: flex;
    align-items: center;
    gap: 12px;
}

.teacher-profile-summary-icon {
    width: 42px;
    height: 42px;
    border-radius: 12px;
    display: grid;
    place-items: center;
    flex: none;
    background: rgba(81,97,206,.10);
    color: #5161ce;
    font-size: 1.15rem;
}

.teacher-profile-summary strong {
    display: block;
    font-size: 1.15rem;
    line-height: 1.1;
}

.teacher-profile-summary span {
    display: block;
    margin-top: 3px;
    color: var(--bs-secondary-color);
    font-size: .72rem;
}

.teacher-profile-card {
    border: 1px solid var(--bs-border-color) !important;
    box-shadow: 0 8px 28px rgba(20,30,60,.055);
    transition: transform .2s ease, box-shadow .2s ease;
}

.teacher-profile-card:hover {
    transform: translateY(-1px);
    box-shadow: 0 12px 32px rgba(20,30,60,.09);
}

.teacher-profile-card h2 {
    display: flex;
    align-items: center;
    gap: 9px;
    font-weight: 800;
}

.teacher-profile-card h2 i {
    color: #5161ce;
}

.teacher-profile-text {
    font-size: .94rem;
    line-height: 1.8;
}

.teacher-profile-section-divider {
    height: 1px;
    background: var(--bs-border-color);
    margin: 22px 0;
}

.teacher-profile-highlight {
    border-radius: 15px;
    padding: 15px 17px;
    background: var(--bs-tertiary-bg);
    border: 1px solid var(--bs-border-color);
}

.teacher-profile-highlight + .teacher-profile-highlight {
    margin-top: 10px;
}

.teacher-profile-highlight-title {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: .74rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: .04em;
    color: var(--bs-secondary-color);
    margin-bottom: 5px;
}

.teacher-profile-highlight-value {
    font-weight: 750;
}

.teacher-profile-table {
    border-collapse: separate;
    border-spacing: 0;
}

.teacher-profile-table thead th {
    white-space: nowrap;
    background: var(--bs-tertiary-bg);
    border-bottom: 1px solid var(--bs-border-color);
    padding: 13px 14px;
}

.teacher-profile-table tbody td {
    padding: 14px;
    vertical-align: middle;
}

.teacher-profile-table tbody tr {
    transition: background .15s ease;
}

.teacher-profile-table tbody tr:hover {
    background: rgba(81,97,206,.035);
}

.teacher-profile-table tbody tr:last-child td {
    border-bottom: 0;
}

.teacher-profile-day {
    white-space: nowrap;
}

.teacher-profile-day strong {
    display: block;
}

.teacher-profile-day small {
    color: var(--bs-secondary-color);
}

.teacher-profile-time {
    white-space: nowrap;
    font-weight: 700;
}

.teacher-profile-socials a {
    border-radius: 999px;
    font-weight: 700;
    transition: transform .18s ease;
}

.teacher-profile-socials a:hover {
    transform: translateY(-2px);
}

.teacher-profile-contact-item {
    display: flex;
    align-items: flex-start;
    gap: 11px;
    padding: 11px 0;
    border-bottom: 1px solid var(--bs-border-color);
}

.teacher-profile-contact-item:last-child {
    border-bottom: 0;
    padding-bottom: 0;
}

.teacher-profile-contact-icon {
    width: 34px;
    height: 34px;
    border-radius: 10px;
    display: grid;
    place-items: center;
    background: var(--bs-tertiary-bg);
    color: #5161ce;
    flex: none;
}

.teacher-profile-contact-item strong {
    display: block;
    font-size: .72rem;
    color: var(--bs-secondary-color);
    margin-bottom: 2px;
}

.teacher-profile-contact-item a {
    text-decoration: none;
    word-break: break-word;
}

.teacher-profile-public-note {
    margin-top: 18px;
    padding: 12px 14px;
    border-radius: 13px;
    background: rgba(81,97,206,.07);
    color: var(--bs-secondary-color);
    font-size: .76rem;
}

.teacher-profile-empty-background {
    position: absolute;
    inset: 0;
    background:
        radial-gradient(
            circle at 80% 20%,
            rgba(255,255,255,.15),
            transparent 35%
        ),
        linear-gradient(
            135deg,
            #27356f,
            #5161ce
        );
}

@media (max-width: 800px) {

    .teacher-profile-summary {
        grid-template-columns: 1fr;
    }

    .teacher-profile-summary-item {
        padding: 13px 15px;
    }

    .teacher-profile-table-wrap {
        margin-left: -5px;
        margin-right: -5px;
    }

}

@media (max-width: 520px) {

    .teacher-profile-hero {
        border-radius: 18px;
    }

    .teacher-profile-hero-content {
        min-height: 450px;
    }

    .teacher-profile-avatar {
        width: 105px;
        height: 105px;
        flex-basis: 105px;
    }

    .teacher-profile-name {
        font-size: 1.85rem;
    }

    .teacher-profile-actions {
        width: 100%;
    }

    .teacher-profile-actions a {
        flex: 1 1 auto;
        justify-content: center;
    }

    .teacher-profile-card {
        padding: 19px;
        border-radius: 17px;
    }

}

@media (max-width: 800px) {

    .teacher-profile-grid {
        grid-template-columns: 1fr;
    }

    .teacher-profile-hero-content {
        padding: 28px 22px;
        min-height: 420px;
        align-items: center;
        flex-direction: column;
        justify-content: flex-end;
        text-align: center;
    }

    .teacher-profile-subjects,
    .teacher-profile-actions {
        justify-content: center;
    }

}

@media (max-width: 520px) {

    .teacher-profile-hero-content {
        padding: 22px 16px;
    }

    .teacher-profile-avatar {
        width: 112px;
        height: 112px;
        flex-basis: 112px;
    }

    .teacher-profile-name {
        font-size: 2rem;
    }

    .teacher-profile-stats {
        grid-template-columns: 1fr;
    }

}

/* ============================================================
   TEACHER WEEKLY TIMETABLE
   ============================================================ */

.teacher-week-schedule {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.teacher-schedule-day {
    border: 1px solid var(--bs-border-color);
    border-radius: 16px;
    overflow: hidden;
    background: var(--bs-body-bg);
    transition:
        border-color .2s ease,
        box-shadow .2s ease;
}

.teacher-schedule-day.is-today {
    border-color: rgba(25, 135, 84, .45);
    box-shadow: 0 8px 25px rgba(25, 135, 84, .08);
}

.teacher-schedule-day-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 12px 16px;
    background: var(--bs-tertiary-bg);
    border-bottom: 1px solid var(--bs-border-color);
}

.teacher-schedule-date {
    display: flex;
    align-items: baseline;
    gap: 9px;
}

.teacher-schedule-weekday {
    font-weight: 850;
    font-size: .9rem;
}

.teacher-schedule-date-number {
    color: var(--bs-secondary-color);
    font-size: .76rem;
}

.teacher-schedule-today {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 9px;
    border-radius: 999px;
    background: rgba(25, 135, 84, .12);
    color: #198754;
    font-size: .68rem;
    font-weight: 800;
}

.teacher-schedule-today i {
    font-size: .42rem;
}

.teacher-schedule-class {
    display: grid;
    grid-template-columns: 105px minmax(0, 1fr) auto;
    align-items: center;
    gap: 18px;
    padding: 16px;
}

.teacher-schedule-time {
    display: flex;
    flex-direction: column;
    gap: 2px;
    padding-right: 16px;
    border-right: 1px solid var(--bs-border-color);
}

.teacher-schedule-time strong {
    font-size: 1rem;
    line-height: 1.1;
}

.teacher-schedule-time span {
    color: var(--bs-secondary-color);
    font-size: .72rem;
}

.teacher-schedule-info {
    min-width: 0;
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 8px 14px;
}

.teacher-schedule-subject {
    width: 100%;
    font-size: .95rem;
}

.teacher-schedule-class-name,
.teacher-schedule-room {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    color: var(--bs-secondary-color);
    font-size: .74rem;
}

.teacher-schedule-class-name i,
.teacher-schedule-room i {
    font-size: .7rem;
}

.teacher-schedule-whatsapp {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    min-width: 90px;
    padding: 8px 12px;
    border-radius: 10px;
    background: #25d366;
    color: #fff !important;
    text-decoration: none;
    font-size: .72rem;
    font-weight: 800;
    transition:
        transform .15s ease,
        background .15s ease;
}

.teacher-schedule-whatsapp:hover {
    background: #1da851;
    color: #fff !important;
    transform: translateY(-1px);
}

.teacher-schedule-empty {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 6px;
    padding: 36px 20px;
    border: 1px dashed var(--bs-border-color);
    border-radius: 16px;
    color: var(--bs-secondary-color);
    text-align: center;
}

.teacher-schedule-empty i {
    font-size: 2rem;
    margin-bottom: 4px;
}

.teacher-schedule-empty strong {
    color: var(--bs-body-color);
    font-size: .9rem;
}

.teacher-schedule-empty span {
    font-size: .75rem;
}

@media (max-width: 700px) {

    .teacher-schedule-day-header {
        padding: 11px 13px;
    }

    .teacher-schedule-class {
        grid-template-columns: 1fr auto;
        gap: 12px;
        padding: 14px;
    }

    .teacher-schedule-time {
        grid-column: 1 / -1;
        flex-direction: row;
        align-items: baseline;
        gap: 7px;
        padding: 0 0 8px;
        border-right: 0;
        border-bottom: 1px solid var(--bs-border-color);
    }

    .teacher-schedule-time span::before {
        content: '– ';
    }

    .teacher-schedule-info {
        grid-column: 1;
    }

    .teacher-schedule-whatsapp {
        grid-column: 2;
        grid-row: 2;
        min-width: 42px;
        width: 42px;
        height: 42px;
        padding: 0;
        border-radius: 50%;
    }

    .teacher-schedule-whatsapp span {
        display: none;
    }

    .teacher-schedule-date {
        flex-direction: column;
        gap: 1px;
    }
}

</style>

<div class="teacher-public-profile">

    <p class="teacher-directory-back">
        <a href="<?= htmlspecialchars(rtrim((string)BASE_URL, '/') . '/teachers/index.php', ENT_QUOTES, 'UTF-8') ?>">
            <i class="bi bi-arrow-left"></i>
            All teachers
        </a>
    </p>

    <section class="teacher-profile-hero">

        <?php if ($backgroundUrl): ?>

            <div
                class="teacher-profile-hero-bg"
                style="
                    background-image:
                    url('<?= tp_e($backgroundUrl) ?>');
                "
            ></div>

        <?php else: ?>

            <div class="teacher-profile-empty-background"></div>

        <?php endif; ?>

        <div class="teacher-profile-hero-overlay"></div>

        <div class="teacher-profile-hero-content">

            <div class="teacher-profile-avatar">

                <?php if ($photoUrl): ?>

                    <img
                        src="<?= tp_e($photoUrl) ?>"
                        alt="<?= tp_e($photoAlt) ?>"
                        width="154"
                        height="154"
                    >

                <?php else: ?>

                    <span>
                        <?= tp_e(
                            tp_initials(
                                $teacher['name']
                            )
                        ) ?>
                    </span>

                <?php endif; ?>

            </div>

            <div>

                <div class="teacher-profile-kicker">
                    <?= $subjectLabel !== ''
                        ? tp_e($subjectLabel . ' · Pearson Edexcel')
                        : 'Edexcel College Teacher'
                    ?>
                </div>

                <h1 class="teacher-profile-name">
                    <?= tp_e($teacher['name']) ?>
                </h1>

                <?php if ($subjectLabel !== ''): ?>
                    <p class="teacher-profile-role mb-2">
                        <?= tp_e($subjectLabel) ?> — live online worldwide. Physical classes at the Kandy campus only when scheduled.
                    </p>
                <?php endif; ?>

                <div class="teacher-profile-subjects" aria-label="Teaching subjects">

                    <?php $publicSubjectPages = seo_public_subject_pages(); ?>
                    <?php foreach ($subjects as $subject): ?>

                        <?php
                        $subjectSlug = strtolower(trim((string)preg_replace('/[^a-z0-9]+/i', '-', (string)$subject['name']), '-'));
                        $subjectHref = ($subjectSlug !== '' && isset($publicSubjectPages[$subjectSlug]))
                            ? seo_absolute_url('subjects/' . $subjectSlug . '/')
                            : '';
                        ?>
                        <?php if ($subjectHref !== ''): ?>
                        <a class="teacher-profile-chip" href="<?= tp_e($subjectHref) ?>"><?= tp_e($subject['name']) ?></a>
                        <?php else: ?>
                        <span class="teacher-profile-chip"><?= tp_e($subject['name']) ?></span>
                        <?php endif; ?>

                    <?php endforeach; ?>

                </div>

                <p class="teacher-profile-role mb-2">
                    <a href="<?= tp_e(seo_absolute_url('online-classes')) ?>">Online classes worldwide</a>
                    · <a href="<?= tp_e(seo_absolute_url('locations/kandy')) ?>">Physical classes in Kandy</a>
                    · <a href="<?= tp_e(seo_absolute_url('admissions/enquire.php')) ?>">Admissions</a>
                </p>

                <div class="teacher-profile-actions">

                    <?php if ($waUrl): ?>

                        <a
                            class="btn btn-success teacher-profile-whatsapp"
                            href="<?= tp_e($waUrl) ?>"
                            target="_blank"
                            rel="noopener"
                        >
                            <i class="bi bi-whatsapp"></i>
                            WhatsApp
                        </a>

                    <?php endif; ?>

                    <?php if (!empty($teacher['email'])): ?>

                        <a
                            class="btn btn-light"
                            href="mailto:<?= tp_e($teacher['email']) ?>"
                        >
                            <i class="bi bi-envelope"></i>
                            Email
                        </a>

                    <?php endif; ?>

                    <?php if (!empty($teacher['website'])): ?>

                        <a
                            class="btn btn-outline-light"
                            href="<?= tp_e($teacher['website']) ?>"
                            target="_blank"
                            rel="noopener"
                        >
                            <i class="bi bi-globe2"></i>
                            Website
                        </a>

                    <?php endif; ?>

                </div>

            </div>

        </div>

    </section>


    <div class="teacher-profile-summary">

        <div class="teacher-profile-summary-item">

            <div class="teacher-profile-summary-icon">
                <i class="bi bi-book"></i>
            </div>

            <div>
                <strong><?= $subjectsCount ?></strong>
                <span>Subjects</span>
            </div>

        </div>


        <div class="teacher-profile-summary-item">

            <div class="teacher-profile-summary-icon">
                <i class="bi bi-calendar-check"></i>
            </div>

            <div>
                <strong><?= $weeklyClasses ?></strong>
                <span>Classes This Week</span>
            </div>

        </div>


        <div class="teacher-profile-summary-item">

            <div class="teacher-profile-summary-icon">
                <i class="bi bi-award"></i>
            </div>

            <div>
                <strong>
                    <?= !empty($teacher['experience_years'])
                        ? (int)$teacher['experience_years'] . '+'
                        : '—'
                    ?>
                </strong>
                <span>Years Experience</span>
            </div>

        </div>

    </div>


    <div class="teacher-profile-grid">

        <div>

            <?php
            $aboutText = trim((string)($teacher['bio'] ?? ''));
            if ($aboutText === '') {
                $aboutText = seo_teacher_fallback_intro((string)$teacher['name'], $subjectNames);
            }
            ?>

                <section class="card teacher-profile-card" id="about-teacher">

                    <h2>
                        <i class="bi bi-person-lines-fill"></i>
                        About <?= tp_e($teacher['name']) ?>
                    </h2>

                    <p class="text-body-secondary mb-0 teacher-profile-text">
                        <?= tp_e($aboutText) ?>
                    </p>

                    <?php if ($subjectLabel !== ''): ?>
                        <p class="text-body-secondary teacher-profile-text mt-3 mb-0">
                            Teaching focus:
                            <strong><?= tp_e($subjectLabel) ?></strong>
                            for Edexcel IGCSE and International A Level pathways.
                            Explore
                            <a href="<?= tp_e(seo_absolute_url('edexcel-classes')) ?>">Edexcel classes</a>,
                            <a href="<?= tp_e(seo_absolute_url('edexcel-o-level')) ?>">IGCSE / O Level</a>,
                            and
                            <a href="<?= tp_e(seo_absolute_url('edexcel-a-level')) ?>">A Level / IAL</a>
                            at Edexcel College.
                        </p>
                    <?php endif; ?>

                </section>


            <?php if (
                !empty($teacher['qualifications']) ||
                !empty($teacher['achievements'])
            ): ?>

                <section class="card teacher-profile-card mt-3">

                    <?php if (!empty($teacher['qualifications'])): ?>

                        <h2>
                            <i class="bi bi-mortarboard"></i>
                            Qualifications
                        </h2>

                        <p class="text-body-secondary teacher-profile-text">
                            <?= tp_e(
                                $teacher['qualifications']
                            ) ?>
                        </p>

                    <?php endif; ?>


                    <?php if (!empty($teacher['achievements'])): ?>

                        <h2 class="mt-4">
                            <i class="bi bi-award"></i>
                            Achievements
                        </h2>

                        <p class="text-body-secondary mb-0 teacher-profile-text">
                            <?= tp_e(
                                $teacher['achievements']
                            ) ?>
                        </p>

                    <?php endif; ?>

                </section>

            <?php endif; ?>


            <section class="card teacher-profile-card mt-3">

                <h2>
                    <i class="bi bi-calendar-week"></i>
                    This Week's Classes
                </h2>

                <p class="text-body-secondary">
                    <?= tp_e(
                        date('d M', strtotime($monday))
                    ) ?>
                    –
                    <?= tp_e(
                        date('d M', strtotime($sunday))
                    ) ?>
                </p>

                <?php if ($timetable): ?>

                    <div class="teacher-week-schedule">

                        <?php $todayDate = date('Y-m-d'); ?>

                        <?php foreach ($timetable as $row): ?>

                            <?php
                            $rowDate = (string)$row['date'];
                            $isToday = ($rowDate === $todayDate);
                            ?>

                            <div class="teacher-schedule-day <?= $isToday ? 'is-today' : '' ?>">

                                <div class="teacher-schedule-day-header">

                                    <div class="teacher-schedule-date">

                                        <span class="teacher-schedule-weekday">
                                            <?= tp_e(
                                                date(
                                                    'l',
                                                    strtotime($rowDate)
                                                )
                                            ) ?>
                                        </span>

                                        <span class="teacher-schedule-date-number">
                                            <?= tp_e(
                                                date(
                                                    'd M Y',
                                                    strtotime($rowDate)
                                                )
                                            ) ?>
                                        </span>

                                    </div>

                                    <?php if ($isToday): ?>

                                        <span class="teacher-schedule-today">
                                            <i class="bi bi-circle-fill"></i>
                                            Today
                                        </span>

                                    <?php endif; ?>

                                </div>


                                <div class="teacher-schedule-class">

                                    <div class="teacher-schedule-time">

                                        <strong>
                                            <?= tp_e(
                                                date(
                                                    'H:i',
                                                    strtotime(
                                                        $row['start_time']
                                                    )
                                                )
                                            ) ?>
                                        </strong>

                                        <span>
                                            <?= tp_e(
                                                date(
                                                    'H:i',
                                                    strtotime(
                                                        $row['end_time']
                                                    )
                                                )
                                            ) ?>
                                        </span>

                                    </div>


                                    <div class="teacher-schedule-info">

                                        <strong class="teacher-schedule-subject">
                                            <?= tp_e(
                                                $row['subject_name']
                                            ) ?>
                                        </strong>

                                        <span class="teacher-schedule-class-name">
                                            <i class="bi bi-mortarboard-fill"></i>
                                            <?= tp_e(
                                                $row['class_name']
                                            ) ?>
                                        </span>

                                        <span class="teacher-schedule-room">
                                            <i class="bi bi-door-open-fill"></i>
                                            <?= tp_e(
                                                $row['room_name']
                                            ) ?>
                                        </span>

                                    </div>


                                    <?php if (!empty($row['whatsapp_link'])): ?>

                                        <a
                                            class="teacher-schedule-whatsapp"
                                            href="<?= tp_e(
                                                $row['whatsapp_link']
                                            ) ?>"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            aria-label="WhatsApp for <?= tp_e(
                                                $row['class_name']
                                            ) ?>"
                                        >
                                            <i class="bi bi-whatsapp"></i>
                                            <span>WhatsApp</span>
                                        </a>

                                    <?php endif; ?>

                                </div>

                            </div>

                        <?php endforeach; ?>

                    </div>

                <?php else: ?>

                    <div class="teacher-schedule-empty">

                        <i class="bi bi-calendar-x"></i>

                        <strong>
                            No classes scheduled this week
                        </strong>

                        <span>
                            The timetable will appear here when classes are scheduled.
                        </span>

                    </div>

                <?php endif; ?>

            </section>

        </div>


        <aside>

            <section class="card teacher-profile-card">

                <h2>
                    <i class="bi bi-stars"></i>
                    Profile Highlights
                </h2>

                <div class="teacher-profile-highlight">

                    <div class="teacher-profile-highlight-title">
                        <i class="bi bi-book"></i>
                        Teaching Subjects
                    </div>

                    <div class="teacher-profile-highlight-value">
                        <?= $subjectsCount > 0
                            ? tp_e($subjectLabel !== '' ? $subjectLabel : ($subjectsCount . ' subjects'))
                            : 'Subjects to be confirmed'
                        ?>
                    </div>

                </div>


                <div class="teacher-profile-highlight">

                    <div class="teacher-profile-highlight-title">
                        <i class="bi bi-calendar3"></i>
                        Weekly Schedule
                    </div>

                    <div class="teacher-profile-highlight-value">
                        <?= $weeklyClasses ?>
                        <?= $weeklyClasses === 1 ? 'class' : 'classes' ?>
                        this week
                    </div>

                </div>


                <div class="teacher-profile-highlight">

                    <div class="teacher-profile-highlight-title">
                        <i class="bi bi-award"></i>
                        Teaching Experience
                    </div>

                    <div class="teacher-profile-highlight-value">

                        <?php if (!empty($teacher['experience_years'])): ?>

                            <?= (int)$teacher['experience_years'] ?>+
                            years

                        <?php else: ?>

                            Information not provided

                        <?php endif; ?>

                    </div>

                </div>

            </section>


            <section class="card teacher-profile-card mt-3">

                <h2>
                    <i class="bi bi-person-vcard"></i>
                    Contact
                </h2>

                <?php if (!empty($teacher['email'])): ?>

                    <div class="teacher-profile-contact-item">

                        <div class="teacher-profile-contact-icon">
                            <i class="bi bi-envelope"></i>
                        </div>

                        <div>

                            <strong>Email</strong>

                            <a
                                href="mailto:<?= tp_e(
                                    $teacher['email']
                                ) ?>"
                            >
                                <?= tp_e(
                                    $teacher['email']
                                ) ?>
                            </a>

                        </div>

                    </div>

                <?php endif; ?>


                <?php if (!empty($teacher['phone'])): ?>

                    <div class="teacher-profile-contact-item">

                        <div class="teacher-profile-contact-icon">
                            <i class="bi bi-whatsapp"></i>
                        </div>

                        <div>

                            <strong>WhatsApp</strong>

                            <?php if ($waUrl): ?>

                                <a
                                    href="<?= tp_e($waUrl) ?>"
                                    target="_blank"
                                    rel="noopener"
                                >
                                    <?= tp_e(
                                        $teacher['phone']
                                    ) ?>
                                </a>

                            <?php else: ?>

                                <?= tp_e(
                                    $teacher['phone']
                                ) ?>

                            <?php endif; ?>

                        </div>

                    </div>

                <?php endif; ?>


                <?php if ($waUrl): ?>

                    <div class="d-grid mt-3">

                        <a
                            href="<?= tp_e($waUrl) ?>"
                            target="_blank"
                            rel="noopener"
                            class="btn btn-success teacher-profile-whatsapp"
                        >
                            <i class="bi bi-whatsapp"></i>
                            Chat on WhatsApp
                        </a>

                    </div>

                <?php endif; ?>

            </section>


            <?php if (
                $teacher['website'] ||
                $teacher['facebook'] ||
                $teacher['instagram'] ||
                $teacher['youtube']
            ): ?>

                <section class="card teacher-profile-card mt-3">

                    <h2>
                        <i class="bi bi-link-45deg"></i>
                        Links
                    </h2>

                    <div class="teacher-profile-socials">

                        <?php if ($teacher['website']): ?>

                            <a
                                class="btn btn-outline-secondary"
                                href="<?= tp_e(
                                    $teacher['website']
                                ) ?>"
                                target="_blank"
                                rel="noopener"
                            >
                                <i class="bi bi-globe"></i>
                                Website
                            </a>

                        <?php endif; ?>


                        <?php if ($teacher['facebook']): ?>

                            <a
                                class="btn btn-outline-secondary"
                                href="<?= tp_e(
                                    $teacher['facebook']
                                ) ?>"
                                target="_blank"
                                rel="noopener"
                            >
                                <i class="bi bi-facebook"></i>
                                Facebook
                            </a>

                        <?php endif; ?>


                        <?php if ($teacher['instagram']): ?>

                            <a
                                class="btn btn-outline-secondary"
                                href="<?= tp_e(
                                    $teacher['instagram']
                                ) ?>"
                                target="_blank"
                                rel="noopener"
                            >
                                <i class="bi bi-instagram"></i>
                                Instagram
                            </a>

                        <?php endif; ?>


                        <?php if ($teacher['youtube']): ?>

                            <a
                                class="btn btn-outline-secondary"
                                href="<?= tp_e(
                                    $teacher['youtube']
                                ) ?>"
                                target="_blank"
                                rel="noopener"
                            >
                                <i class="bi bi-youtube"></i>
                                YouTube
                            </a>

                        <?php endif; ?>

                    </div>

                </section>

            <?php endif; ?>

            <div class="teacher-profile-public-note">

                <i class="bi bi-info-circle"></i>

                This public profile shows <?= tp_e($teacher['name']) ?>’s
                professional information<?= $subjectLabel !== '' ? (' for ' . tp_e($subjectLabel)) : '' ?>
                and current weekly teaching schedule at Edexcel College.
                See all teachers on the
                <a href="<?= tp_e(seo_absolute_url('teachers/')) ?>">teachers directory</a>.

            </div>

        </aside>

    </div>

</div>

<style>
.teacher-profile-role {
    margin: 0 0 10px;
    max-width: 36rem;
    color: rgba(255,255,255,.88);
    font-size: .95rem;
    font-weight: 650;
    line-height: 1.45;
}
@media (max-width: 800px) {
    .teacher-profile-role {
        margin-left: auto;
        margin-right: auto;
    }
}
</style>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>
