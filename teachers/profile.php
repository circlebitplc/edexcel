<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/config.php';

require_teacher();

function te_e(mixed $v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$userId = (int)($_SESSION['user_id'] ?? 0);

$stmt = $pdo->prepare("
    SELECT
        u.id AS user_id,
        u.username,
        u.teacher_id,
        u.google_id,
        u.google_email,
        COALESCE(u.teacher_oauth_status, 'not_linked') AS teacher_oauth_status,
        u.teacher_oauth_linked_at,
        t.name, t.email, t.phone, t.photo,
        p.bio, p.qualifications, p.experience_years,
        p.achievements, p.profile_background,
        p.website, p.facebook, p.instagram, p.youtube
    FROM users u
    INNER JOIN teachers t ON t.id = u.teacher_id AND t.deleted_at IS NULL
    LEFT JOIN teacher_profiles p ON p.teacher_id = t.id
    WHERE u.id = ?
      AND (u.role = 'teacher' OR u.role = 'admin')
      AND u.deleted_at IS NULL
    LIMIT 1
");
$stmt->execute([$userId]);
$teacher = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$teacher || empty($teacher['teacher_id'])) {
    http_response_code(403);
    exit('Your teacher account is not linked to a teacher profile.');
}

$teacherId = (int)$teacher['teacher_id'];
$error = '';
$success = '';
if (!empty($_GET['linked'])) {
    $success = 'Your Google account has been successfully linked! You can now use Google Sign-in.';
}

$profileDir = __DIR__ . '/../uploads/teachers/backgrounds/';
$teacherPhotoDir = __DIR__ . '/../assets/images/teachers/';

if (!is_dir($teacherPhotoDir)) {
    mkdir($teacherPhotoDir, 0755, true);
}
if (!is_dir($profileDir)) {
    mkdir($profileDir, 0755, true);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';

    if (!verify_csrf_token($csrf)) {
        $error = 'Invalid security token. Please refresh and try again.';
    } else {
        $bio = trim((string)($_POST['bio'] ?? ''));
        $qualifications = trim((string)($_POST['qualifications'] ?? ''));
        $achievements = trim((string)($_POST['achievements'] ?? ''));
        $experience = trim((string)($_POST['experience_years'] ?? ''));
        $website = trim((string)($_POST['website'] ?? ''));
        $facebook = trim((string)($_POST['facebook'] ?? ''));
        $instagram = trim((string)($_POST['instagram'] ?? ''));
        $youtube = trim((string)($_POST['youtube'] ?? ''));
        $removeBackground = isset($_POST['remove_background']);
        $removeTeacherPhoto = isset($_POST['remove_teacher_photo']);

        $experienceValue = $experience === '' ? null : max(0, min(99, (int)$experience));
        $backgroundPath = $teacher['profile_background'] ?? null;

        if ($removeBackground) {
            foreach (glob($profileDir . $teacherId . '.*') ?: [] as $file) {
                @unlink($file);
            }
            $backgroundPath = null;
        }


        /*
         * ========================================================
         * TEACHER PROFILE PHOTO
         * ========================================================
         *
         * $teacherId comes from the authenticated teacher account.
         * Teachers cannot supply another teacher ID here.
         */

        if (
            !$error &&
            isset($_FILES['teacher_photo']) &&
            $_FILES['teacher_photo']['error'] !== UPLOAD_ERR_NO_FILE
        ) {

            $file = $_FILES['teacher_photo'];

            if ($file['error'] !== UPLOAD_ERR_OK) {

                $error =
                    'The profile photo could not be uploaded.';

            } elseif ($file['size'] > 5 * 1024 * 1024) {

                $error =
                    'Profile photo must be 5 MB or smaller.';

            } else {

                $imageInfo =
                    @getimagesize($file['tmp_name']);

                if ($imageInfo === false) {

                    $error =
                        'The selected profile photo is not a valid image.';

                } else {

                    $finfo =
                        finfo_open(FILEINFO_MIME_TYPE);

                    $mime =
                        $finfo
                            ? finfo_file(
                                $finfo,
                                $file['tmp_name']
                            )
                            : '';

                    if ($finfo) {
                        finfo_close($finfo);
                    }

                    $photoMap = [
                        'image/jpeg' => 'jpg',
                        'image/png'  => 'png',
                        'image/webp' => 'webp'
                    ];

                    if (!isset($photoMap[$mime])) {

                        $error =
                            'Profile photo must be JPG, PNG or WEBP.';

                    } else {

                        $width =
                            (int)($imageInfo[0] ?? 0);

                        $height =
                            (int)($imageInfo[1] ?? 0);

                        if ($width < 200 || $height < 200) {

                            $error =
                                'Profile photo must be at least 200 × 200 pixels.';

                        } else {

                            $ext =
                                $photoMap[$mime];

                            /*
                             * Remove old ID-based photo formats.
                             */
                            foreach (
                                ['jpg','jpeg','png','webp','gif']
                                as $oldExt
                            ) {

                                $oldPhoto =
                                    $teacherPhotoDir .
                                    $teacherId .
                                    '.' .
                                    $oldExt;

                                if (is_file($oldPhoto)) {
                                    @unlink($oldPhoto);
                                }
                            }

                            $destination =
                                $teacherPhotoDir .
                                $teacherId .
                                '.' .
                                $ext;

                            if (
                                !move_uploaded_file(
                                    $file['tmp_name'],
                                    $destination
                                )
                            ) {

                                $error =
                                    'Unable to save the profile photo. Check folder permissions.';

                            } else {

                                $photoPath =
                                    'assets/images/teachers/' .
                                    $teacherId .
                                    '.' .
                                    $ext;

                                $stmt =
                                    $pdo->prepare("
                                        UPDATE teachers
                                        SET photo = ?
                                        WHERE id = ?
                                        LIMIT 1
                                    ");

                                $stmt->execute([
                                    $photoPath,
                                    $teacherId
                                ]);
                            }
                        }
                    }
                }
            }
        }


        /*
         * Remove current teacher profile photo.
         */
        if (
            !$error &&
            $removeTeacherPhoto
        ) {

            foreach (
                ['jpg','jpeg','png','webp','gif']
                as $oldExt
            ) {

                $oldPhoto =
                    $teacherPhotoDir .
                    $teacherId .
                    '.' .
                    $oldExt;

                if (is_file($oldPhoto)) {
                    @unlink($oldPhoto);
                }
            }

            $stmt =
                $pdo->prepare("
                    UPDATE teachers
                    SET photo = NULL
                    WHERE id = ?
                    LIMIT 1
                ");

            $stmt->execute([
                $teacherId
            ]);
        }


        if (!$error && isset($_FILES['background']) && $_FILES['background']['error'] !== UPLOAD_ERR_NO_FILE) {
            $file = $_FILES['background'];

            if ($file['error'] !== UPLOAD_ERR_OK) {
                $error = 'The background image could not be uploaded.';
            } elseif ($file['size'] > 3 * 1024 * 1024) {
                $error = 'Background image must be 3 MB or smaller.';
            } else {
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime = $finfo ? finfo_file($finfo, $file['tmp_name']) : '';
                if ($finfo) finfo_close($finfo);

                $map = [
                    'image/jpeg' => 'jpg',
                    'image/png' => 'png',
                    'image/webp' => 'webp'
                ];

                if (!isset($map[$mime])) {
                    $error = 'Background must be JPG, PNG or WEBP.';
                } else {
                    foreach (glob($profileDir . $teacherId . '.*') ?: [] as $old) {
                        @unlink($old);
                    }
                    $ext = $map[$mime];
                    $destination = $profileDir . $teacherId . '.' . $ext;
                    if (!move_uploaded_file($file['tmp_name'], $destination)) {
                        $error = 'Unable to save the background image. Check folder permissions.';
                    } else {
                        $backgroundPath = 'uploads/teachers/backgrounds/' . $teacherId . '.' . $ext;
                    }
                }
            }
        }

        if (!$error) {
            try {
                $pdo->beginTransaction();

                $stmt = $pdo->prepare("
                    INSERT INTO teacher_profiles
                        (teacher_id, bio, qualifications, experience_years,
                         achievements, profile_background, website,
                         facebook, instagram, youtube)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE
                        bio = VALUES(bio),
                        qualifications = VALUES(qualifications),
                        experience_years = VALUES(experience_years),
                        achievements = VALUES(achievements),
                        profile_background = VALUES(profile_background),
                        website = VALUES(website),
                        facebook = VALUES(facebook),
                        instagram = VALUES(instagram),
                        youtube = VALUES(youtube)
                ");
                $stmt->execute([
                    $teacherId, $bio ?: null, $qualifications ?: null,
                    $experienceValue, $achievements ?: null,
                    $backgroundPath, $website ?: null, $facebook ?: null,
                    $instagram ?: null, $youtube ?: null
                ]);

                $pdo->commit();
                $success = 'Teacher profile updated successfully.';

                $stmt = $pdo->prepare("
                    SELECT
                        t.name, t.email, t.phone, t.photo,
                        p.bio, p.qualifications, p.experience_years,
                        p.achievements, p.profile_background,
                        p.website, p.facebook, p.instagram, p.youtube
                    FROM teachers t
                    LEFT JOIN teacher_profiles p ON p.teacher_id = t.id
                    WHERE t.id = ?
                    LIMIT 1
                ");
                $stmt->execute([$teacherId]);
                $teacher = $stmt->fetch(PDO::FETCH_ASSOC);

            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $error = 'Unable to save the profile. Please try again.';
                error_log('Teacher profile update: ' . $e->getMessage());
            }
        }
    }
}

$theme = function_exists('app_theme_current') ? app_theme_current() : 'dark';
$backgroundUrl = !empty($teacher['profile_background'])
    ? rtrim(BASE_URL,'/') . '/' . ltrim((string)$teacher['profile_background'],'/')
    : '';
$publicUrl = rtrim(BASE_URL,'/') . '/teachers/teacher_profile.php?id=' . $teacherId;

/*
 * Profile completion
 *
 * These are the main public-facing profile fields.
 */
$profileChecks = [
    !empty(trim((string)($teacher['bio'] ?? ''))),
    !empty(trim((string)($teacher['qualifications'] ?? ''))),
    $teacher['experience_years'] !== null &&
        $teacher['experience_years'] !== '',
    !empty(trim((string)($teacher['achievements'] ?? ''))),
    !empty(trim((string)($teacher['profile_background'] ?? '')))
];

$profileCompleted = count(array_filter($profileChecks));
$profileTotal = count($profileChecks);

$profilePercent = (int)round(
    ($profileCompleted / $profileTotal) * 100
);

$profileMissing = [];

if (empty(trim((string)($teacher['bio'] ?? '')))) {
    $profileMissing[] = 'Biography';
}

if (empty(trim((string)($teacher['qualifications'] ?? '')))) {
    $profileMissing[] = 'Qualifications';
}

if (
    $teacher['experience_years'] === null ||
    $teacher['experience_years'] === ''
) {
    $profileMissing[] = 'Experience';
}

if (empty(trim((string)($teacher['achievements'] ?? '')))) {
    $profileMissing[] = 'Achievements';
}

if (empty(trim((string)($teacher['profile_background'] ?? '')))) {
    $profileMissing[] = 'Background photo';
}

/*
 * Teacher photo
 */
$teacherPhotoUrl = '';

if (!empty($teacher['photo'])) {

    $storedPhoto = ltrim(
        trim((string)$teacher['photo']),
        '/'
    );

    $photoCandidates = [
        $storedPhoto,
        'assets/images/teachers/' . basename($storedPhoto),
        'uploads/teachers/' . basename($storedPhoto)
    ];

    foreach ($photoCandidates as $candidate) {

        if (is_file(__DIR__ . '/../' . $candidate)) {

            $teacherPhotoUrl =
                rtrim(BASE_URL, '/') .
                '/' .
                ltrim($candidate, '/');

            break;
        }
    }
}

if ($teacherPhotoUrl === '') {

    foreach (['png','jpg','jpeg','webp','gif'] as $ext) {

        $candidate =
            'assets/images/teachers/' .
            $teacherId .
            '.' .
            $ext;

        if (is_file(__DIR__ . '/../' . $candidate)) {

            $teacherPhotoUrl =
                rtrim(BASE_URL, '/') .
                '/' .
                $candidate;

            break;
        }
    }
}
?>
<!doctype html>
<html lang="en" <?= function_exists('app_theme_html_attrs') ? app_theme_html_attrs() : 'data-theme="dark" data-bs-theme="dark"' ?>>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php if (function_exists('app_theme_boot_script')) { app_theme_boot_script(); } ?>
<title>Edit Teacher Profile | Edexcel College</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<?php if (function_exists('app_theme_css_link')) { app_theme_css_link(); } ?>
<style>
:root{--bg:#f5f7fc;--surface:#fff;--soft:#f5f7fb;--text:#172033;--muted:#687386;--primary:#5161ce;--border:rgba(23,32,51,.09);--shadow:0 18px 50px rgba(23,32,51,.10)}
html[data-bs-theme=dark]{--bg:#0f131b;--surface:#181e2b;--soft:#222938;--text:#f5f7fb;--muted:#aeb8cb;--primary:#7181eb;--border:rgba(255,255,255,.09);--shadow:0 20px 55px rgba(0,0,0,.35)}
*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--text);font-family:Inter,system-ui,sans-serif}.wrap{max-width:980px;margin:auto;padding:18px}.top{display:flex;justify-content:space-between;align-items:center;gap:10px;margin-bottom:18px}.brand{font-weight:800;text-decoration:none}.actions{display:flex;gap:8px}.btn{display:inline-flex;align-items:center;gap:7px;border:1px solid var(--border);background:var(--surface);color:var(--text);border-radius:11px;padding:10px 13px;text-decoration:none;font-weight:700;cursor:pointer}.primary{background:var(--primary);border-color:var(--primary);color:#fff}.card{background:var(--surface);border:1px solid var(--border);border-radius:20px;box-shadow:var(--shadow);padding:24px;margin-bottom:18px}.grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}.field{margin-bottom:15px}.field label{display:block;font-size:.8rem;font-weight:800;margin-bottom:6px}.field input,.field textarea{width:100%;border:1px solid var(--border);border-radius:11px;background:var(--soft);color:var(--text);padding:11px 12px;outline:none;font:inherit}.field textarea{min-height:125px;resize:vertical}.field input:focus,.field textarea:focus{border-color:var(--primary);box-shadow:0 0 0 3px rgba(81,97,206,.12)}.hint{font-size:.72rem;color:var(--muted);margin-top:5px}.preview{height:250px;border-radius:16px;overflow:hidden;background:linear-gradient(135deg,#27356f,#5161ce);background-size:cover;background-position:center;display:flex;align-items:flex-end;padding:20px;color:#fff;margin-bottom:14px}.preview strong{font-size:1.5rem}.alert{padding:12px 14px;border-radius:11px;margin-bottom:15px}.success{background:rgba(25,135,84,.12);color:#198754}.error{background:rgba(220,53,69,.12);color:#dc3545}.check{display:flex;gap:8px;align-items:center;font-size:.8rem;color:var(--muted)}
.photo-management{
    display:flex;
    align-items:center;
    gap:22px;
}

.photo-management-preview{
    width:125px;
    height:125px;
    flex:none;
}

.photo-management-preview img{
    width:125px;
    height:125px;
    display:block;
    object-fit:cover;
    border-radius:50%;
    border:4px solid var(--surface);
    box-shadow:0 8px 25px rgba(0,0,0,.15);
}

.photo-management-preview .teacher-photo-placeholder{
    width:125px;
    height:125px;
}

.photo-management-info{
    min-width:0;
}

.photo-management-info h2{
    margin:0 0 5px;
    font-size:1.05rem;
}

.photo-management-info p{
    margin:0 0 12px;
    color:var(--muted);
    font-size:.78rem;
}

.photo-filename{
    display:block;
    margin-top:8px;
    color:var(--muted);
    font-size:.72rem;
    word-break:break-word;
}

.photo-remove-option{
    margin-top:12px;
}

@media(max-width:600px){

    .photo-management{
        flex-direction:column;
        text-align:center;
    }

    .photo-management-info{
        width:100%;
    }

}

@media(max-width:700px){.grid{grid-template-columns:1fr}.wrap{padding:10px}.card{padding:18px}}

.profile-hero{
    display:flex;
    align-items:center;
    gap:20px;
}

.teacher-photo{
    width:110px;
    height:110px;
    border-radius:50%;
    object-fit:cover;
    border:4px solid var(--surface);
    box-shadow:0 8px 25px rgba(0,0,0,.16);
    background:var(--soft);
    flex:none;
}

.teacher-photo-placeholder{
    width:110px;
    height:110px;
    border-radius:50%;
    display:grid;
    place-items:center;
    background:var(--soft);
    color:var(--muted);
    font-size:2.5rem;
    flex:none;
}

.profile-hero-info{
    min-width:0;
}

.profile-hero-info h1{
    margin:0 0 5px;
    font-size:clamp(1.45rem,3vw,2rem);
}

.profile-hero-info p{
    margin:0;
    color:var(--muted);
    font-size:.85rem;
}

.completion-wrap{
    margin-top:20px;
}

.completion-top{
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:10px;
    margin-bottom:7px;
}

.completion-label{
    font-size:.78rem;
    font-weight:800;
}

.completion-percent{
    font-size:.78rem;
    font-weight:800;
    color:var(--primary);
}

.progress-track{
    width:100%;
    height:9px;
    border-radius:999px;
    background:var(--soft);
    overflow:hidden;
}

.progress-bar{
    height:100%;
    border-radius:999px;
    background:var(--primary);
    transition:width .3s ease;
}

.missing-list{
    display:flex;
    flex-wrap:wrap;
    gap:6px;
    margin-top:10px;
}

.missing-item{
    padding:4px 9px;
    border-radius:999px;
    background:rgba(255,193,7,.12);
    color:#9a6700;
    border:1px solid rgba(255,193,7,.2);
    font-size:.7rem;
    font-weight:700;
}

[data-bs-theme=dark] .missing-item{
    color:#ffd76a;
    background:rgba(255,193,7,.14);
}

@media(max-width:520px){
    .profile-hero{
        flex-direction:column;
        text-align:center;
    }

    .teacher-photo,
    .teacher-photo-placeholder{
        width:96px;
        height:96px;
    }
}

/* ============================================================
   LIVE PUBLIC PROFILE PREVIEW
   ============================================================ */

.live-profile-preview-section{
    overflow:hidden;
}

.live-preview-heading{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:18px;
    margin-bottom:18px;
}

.live-preview-heading h2{
    margin:0;
}

.live-preview-heading p{
    margin:5px 0 0;
    color:var(--muted,#6c757d);
    font-size:.78rem;
}

.live-preview-shell{
    overflow:hidden;
    border:1px solid var(--border,#dee2e6);
    border-radius:20px;
    background:var(--surface,#fff);
}

.live-preview-hero{
    position:relative;
    display:flex;
    align-items:center;
    gap:22px;
    padding:30px;
    color:#fff;
    background:
        linear-gradient(
            135deg,
            #27356f,
            #5161ce
        );
}

.live-preview-photo{
    width:112px;
    height:112px;
    flex:0 0 112px;
    overflow:hidden;
    border:4px solid rgba(255,255,255,.9);
    border-radius:50%;
    background:#f5f7fb;
    box-shadow:0 10px 25px rgba(0,0,0,.2);
}

.live-preview-photo img{
    width:100%;
    height:100%;
    display:block;
    object-fit:cover;
}

.live-preview-photo-placeholder{
    width:100%;
    height:100%;
    display:grid;
    place-items:center;
    color:#5161ce;
    font-size:2.4rem;
}

.live-preview-identity{
    min-width:0;
}

.live-preview-kicker{
    display:block;
    margin-bottom:5px;
    font-size:.68rem;
    font-weight:800;
    letter-spacing:.12em;
    text-transform:uppercase;
    opacity:.8;
}

.live-preview-identity h3{
    margin:0;
    font-size:clamp(1.6rem,4vw,2.4rem);
    line-height:1.08;
}

.live-preview-experience{
    display:flex;
    align-items:center;
    gap:6px;
    margin-top:9px;
    font-size:.78rem;
    font-weight:700;
}

.live-preview-body{
    padding:24px;
}

.live-preview-block{
    margin-bottom:20px;
}

.live-preview-block h4{
    display:flex;
    align-items:center;
    gap:7px;
    margin:0 0 7px;
    font-size:.95rem;
    font-weight:800;
}

.live-preview-block p{
    margin:0;
    white-space:pre-line;
    color:var(--text,#212529);
    line-height:1.7;
    font-size:.86rem;
}

.live-preview-two-column{
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:22px;
}

.live-preview-links{
    display:flex;
    flex-wrap:wrap;
    gap:8px;
    padding-top:5px;
}

.live-preview-links a{
    display:inline-flex;
    align-items:center;
    gap:6px;
    padding:7px 11px;
    border-radius:999px;
    background:var(--soft,#f5f7fb);
    color:var(--text,#212529);
    text-decoration:none;
    font-size:.75rem;
    font-weight:700;
}

.live-preview-links a:hover{
    text-decoration:none;
}

@media(max-width:700px){

    .live-preview-heading{
        align-items:flex-start;
        flex-direction:column;
    }

    .live-preview-heading .btn{
        width:100%;
        justify-content:center;
    }

    .live-preview-hero{
        padding:22px;
        flex-direction:column;
        text-align:center;
    }

    .live-preview-identity{
        width:100%;
    }

    .live-preview-experience{
        justify-content:center;
    }

    .live-preview-two-column{
        grid-template-columns:1fr;
        gap:0;
    }

    .live-preview-body{
        padding:18px;
    }
}

</style>
</head>
<body>
<div class="wrap">
    <div class="top">
        <a class="brand" href="<?= te_e($publicUrl) ?>"><i class="bi bi-mortarboard-fill"></i> My Teacher Profile</a>
        <div class="actions">
            <a class="btn" href="<?= te_e($publicUrl) ?>"><i class="bi bi-eye"></i> View Profile</a>
            <?php if (function_exists('app_theme_render_picker')) { app_theme_render_picker('header'); } ?>
        </div>
    </div>

    <?php if ($success): ?><div class="alert success"><?= te_e($success) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert error"><?= te_e($error) ?></div><?php endif; ?>

    <!-- Google Account & Sign-in Status -->
    <section class="card" style="border: 1px solid var(--panel-border, #e5e7eb); border-radius: 12px; padding: 20px; background: var(--surface, #fff); margin-bottom: 24px;">
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:16px;">
            <div style="display:flex; align-items:center; gap:14px;">
                <div style="font-size:2rem; color:#4285F4;"><i class="bi bi-google"></i></div>
                <div>
                    <h3 style="margin:0; font-size:1.1rem; font-weight:700;">Google Sign-in</h3>
                    <?php if (($teacher['teacher_oauth_status'] ?? '') === 'linked' && !empty($teacher['google_email'])): ?>
                        <div style="color:#15803d; font-weight:600; font-size:0.9rem; margin-top:2px;">
                            <i class="bi bi-check-circle-fill"></i> Linked to <?= te_e($teacher['google_email']) ?>
                        </div>
                        <div style="color:var(--muted,#64748b); font-size:0.8rem;">
                            Linked on <?= te_e(!empty($teacher['teacher_oauth_linked_at']) ? date('Y-m-d H:i', strtotime((string)$teacher['teacher_oauth_linked_at'])) : 'Unknown') ?>. You can sign in using Sign in with Google.
                        </div>
                    <?php else: ?>
                        <div style="color:#b45309; font-weight:600; font-size:0.9rem; margin-top:2px;">
                            <i class="bi bi-exclamation-triangle-fill"></i> Not Linked
                        </div>
                        <div style="color:var(--muted,#64748b); font-size:0.8rem;">
                            Connect your Google account to enable secure Google Sign-in. Your classes and records will remain untouched.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <div>
                <?php if (($teacher['teacher_oauth_status'] ?? '') !== 'linked'): ?>
                    <a href="/auth/google/start.php?intent=link_teacher" class="btn" style="background:#4285F4; color:#fff; font-weight:700; border-radius:8px; padding:8px 16px; text-decoration:none; display:inline-flex; align-items:center; gap:8px;">
                        <i class="bi bi-google"></i> Link Google Account
                    </a>
                <?php else: ?>
                    <span style="font-size:0.85rem; color:#15803d; background:#dcfce7; padding:6px 12px; border-radius:20px; font-weight:700;">
                        <i class="bi bi-shield-check"></i> Account Connected
                    </span>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <form method="post" enctype="multipart/form-data">
        <?= csrf_field() ?>

        <section class="card">

            <div class="photo-management">

                <div class="photo-management-preview">

                    <?php if ($teacherPhotoUrl): ?>

                        <img
                            id="teacherPhotoPreview"
                            src="<?= te_e($teacherPhotoUrl) ?>"
                            alt="<?= te_e($teacher['name']) ?>"
                        >

                    <?php else: ?>

                        <div
                            id="teacherPhotoPreviewPlaceholder"
                            class="teacher-photo-placeholder"
                        >
                            <i class="bi bi-person-fill"></i>
                        </div>

                        <img
                            id="teacherPhotoPreview"
                            src=""
                            alt=""
                            style="display:none"
                        >

                    <?php endif; ?>

                </div>


                <div class="photo-management-info">

                    <h2>
                        <i class="bi bi-person-circle"></i>
                        Profile Photo
                    </h2>

                    <p>
                        This photo appears on your public
                        teacher profile and teacher directory.
                    </p>


                    <label
                        class="btn"
                        for="teacher_photo"
                    >
                        <i class="bi bi-camera"></i>
                        Choose Photo
                    </label>


                    <input
                        id="teacher_photo"
                        name="teacher_photo"
                        type="file"
                        accept="image/jpeg,image/png,image/webp"
                        hidden
                    >


                    <span
                        id="teacherPhotoFilename"
                        class="photo-filename"
                    >
                        No new photo selected
                    </span>


                    <?php if ($teacherPhotoUrl): ?>

                        <label class="check photo-remove-option">

                            <input
                                type="checkbox"
                                name="remove_teacher_photo"
                                value="1"
                            >

                            Remove current profile photo

                        </label>

                    <?php endif; ?>


                    <div class="hint">

                        JPG, PNG or WEBP · Maximum 5 MB ·
                        Minimum 200 × 200 pixels

                    </div>

                </div>

            </div>

        </section>


        <section class="card">

            <div class="profile-hero">

                <?php if ($teacherPhotoUrl): ?>

                    <img
                        class="teacher-photo"
                        src="<?= te_e($teacherPhotoUrl) ?>"
                        alt="<?= te_e($teacher['name']) ?>"
                    >

                <?php else: ?>

                    <div class="teacher-photo-placeholder">
                        <i class="bi bi-person-fill"></i>
                    </div>

                <?php endif; ?>


                <div class="profile-hero-info">

                    <h1>
                        <?= te_e($teacher['name']) ?>
                    </h1>

                    <p>
                        <?= te_e($teacher['email'] ?? '') ?>
                        <?php if (!empty($teacher['phone'])): ?>
                            · <?= te_e($teacher['phone']) ?>
                        <?php endif; ?>
                    </p>
                    <?php
                    if (!class_exists(\Edexcel\Services\TeacherBankAccountService::class, false)) {
                        require_once __DIR__ . '/../vendor/autoload.php';
                    }
                    $profileBankReady = \Edexcel\Services\TeacherBankAccountService::isComplete($pdo, $teacherId);
                    ?>
                    <p class="mb-0">
                        <?php if ($profileBankReady): ?>
                            Bank details: completed. You can create online classes.
                        <?php else: ?>
                            Bank details: required.
                            <a href="<?= te_e(rtrim((string)BASE_URL, '/') . '/teachers/bank_details.php') ?>">Add Bank Details</a>
                        <?php endif; ?>
                    </p>

                </div>

            </div>


            <div class="completion-wrap">

                <div class="completion-top">

                    <span class="completion-label">
                        Profile completion
                    </span>

                    <span class="completion-percent">
                        <?= $profilePercent ?>%
                    </span>

                </div>


                <div
                    class="progress-track"
                    role="progressbar"
                    aria-valuenow="<?= $profilePercent ?>"
                    aria-valuemin="0"
                    aria-valuemax="100"
                >

                    <div
                        class="progress-bar"
                        style="width:<?= $profilePercent ?>%"
                    ></div>

                </div>


                <?php if ($profileMissing): ?>

                    <div class="missing-list">

                        <?php foreach ($profileMissing as $missing): ?>

                            <span class="missing-item">
                                <i class="bi bi-exclamation-circle"></i>
                                <?= te_e($missing) ?>
                            </span>

                        <?php endforeach; ?>

                    </div>

                <?php else: ?>

                    <div
                        style="
                            margin-top:10px;
                            color:#198754;
                            font-size:.76rem;
                            font-weight:800;
                        "
                    >
                        <i class="bi bi-check-circle-fill"></i>
                        Your public profile is complete.
                    </div>

                <?php endif; ?>

            </div>

        </section>

        <section class="card">
            <h2>Profile Background</h2>
            <?php if ($backgroundUrl): ?>
                <div class="preview" style="background-image:linear-gradient(90deg,rgba(5,9,20,.72),rgba(5,9,20,.12)),url('<?= te_e($backgroundUrl) ?>')">
                    <strong><?= te_e($teacher['name']) ?></strong>
                </div>
            <?php else: ?>
                <div class="preview"><strong><?= te_e($teacher['name']) ?></strong></div>
            <?php endif; ?>

            <div class="field">
                <label for="background">Upload Background Photo</label>
                <input id="background" name="background" type="file" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
                <div class="hint">Recommended: 1920 × 600 px. Maximum 3 MB. JPG, PNG or WebP.</div>
            </div>

            <?php if ($backgroundUrl): ?>
                <label class="check"><input type="checkbox" name="remove_background" value="1"> Remove current background</label>
            <?php endif; ?>
        </section>

        <section class="card">
            <h2>About You</h2>
            <div class="field">
                <label for="bio">Biography</label>
                <textarea id="bio" name="bio" placeholder="Introduce yourself, your teaching experience and your approach."><?= te_e($teacher['bio'] ?? '') ?></textarea>
            </div>

            <div class="grid">
                <div class="field">
                    <label for="qualifications">Qualifications</label>
                    <textarea id="qualifications" name="qualifications" placeholder="Degrees, certifications and professional qualifications."><?= te_e($teacher['qualifications'] ?? '') ?></textarea>
                </div>
                <div class="field">
                    <label for="achievements">Achievements & Awards</label>
                    <textarea id="achievements" name="achievements" placeholder="Awards, achievements and notable accomplishments."><?= te_e($teacher['achievements'] ?? '') ?></textarea>
                </div>
            </div>

            <div class="field">
                <label for="experience_years">Teaching Experience (years)</label>
                <input id="experience_years" name="experience_years" type="number" min="0" max="99" value="<?= te_e($teacher['experience_years'] ?? '') ?>">
            </div>
        </section>

        <section class="card">
            <h2>Online Links</h2>
            <div class="grid">
                <div class="field"><label for="website">Website</label><input id="website" name="website" type="url" value="<?= te_e($teacher['website'] ?? '') ?>" placeholder="https://"></div>
                <div class="field"><label for="facebook">Facebook</label><input id="facebook" name="facebook" type="url" value="<?= te_e($teacher['facebook'] ?? '') ?>" placeholder="https://facebook.com/..."></div>
                <div class="field"><label for="instagram">Instagram</label><input id="instagram" name="instagram" type="url" value="<?= te_e($teacher['instagram'] ?? '') ?>" placeholder="https://instagram.com/..."></div>
                <div class="field"><label for="youtube">YouTube</label><input id="youtube" name="youtube" type="url" value="<?= te_e($teacher['youtube'] ?? '') ?>" placeholder="https://youtube.com/..."></div>
            </div>
        </section>

        <!-- =====================================================
             LIVE PUBLIC PROFILE PREVIEW
             ===================================================== -->

        <section class="card live-profile-preview-section">

            <div class="live-preview-heading">
                <div>
                    <h2>
                        <i class="bi bi-eye-fill"></i>
                        Live Profile Preview
                    </h2>

                    <p>
                        This preview updates as you edit your profile.
                        Nothing is saved until you click Save Profile.
                    </p>
                </div>

                <a
                    class="btn"
                    href="<?= te_e($publicUrl) ?>"
                    target="_blank"
                    rel="noopener"
                >
                    <i class="bi bi-box-arrow-up-right"></i>
                    Open Public Profile
                </a>
            </div>

            <div class="live-preview-shell">

                <div class="live-preview-hero">

                    <div class="live-preview-photo">

                        <?php if ($teacherPhotoUrl): ?>

                            <img
                                id="livePreviewPhoto"
                                src="<?= te_e($teacherPhotoUrl) ?>"
                                alt="<?= te_e($teacher['name']) ?>"
                            >

                        <?php else: ?>

                            <div
                                id="livePreviewPhotoPlaceholder"
                                class="live-preview-photo-placeholder"
                            >
                                <i class="bi bi-person-fill"></i>
                            </div>

                            <img
                                id="livePreviewPhoto"
                                src=""
                                alt=""
                                style="display:none"
                            >

                        <?php endif; ?>

                    </div>

                    <div class="live-preview-identity">

                        <span class="live-preview-kicker">
                            EDExcel College
                        </span>

                        <h3 id="livePreviewName">
                            <?= te_e($teacher['name']) ?>
                        </h3>

                        <div
                            id="livePreviewExperience"
                            class="live-preview-experience"
                            <?= empty($teacher['experience_years']) ? 'style="display:none"' : '' ?>
                        >
                            <i class="bi bi-award-fill"></i>
                            <span>
                                <?= !empty($teacher['experience_years'])
                                    ? (int)$teacher['experience_years'] . '+ years teaching experience'
                                    : ''
                                ?>
                            </span>
                        </div>

                    </div>

                </div>


                <div class="live-preview-body">

                    <div
                        id="livePreviewBioSection"
                        class="live-preview-block"
                        <?= empty(trim((string)($teacher['bio'] ?? ''))) ? 'style="display:none"' : '' ?>
                    >
                        <h4>
                            <i class="bi bi-person-lines-fill"></i>
                            About
                        </h4>

                        <p id="livePreviewBio">
                            <?= te_e($teacher['bio'] ?? '') ?>
                        </p>
                    </div>


                    <div class="live-preview-two-column">

                        <div
                            id="livePreviewQualificationsSection"
                            class="live-preview-block"
                            <?= empty(trim((string)($teacher['qualifications'] ?? ''))) ? 'style="display:none"' : '' ?>
                        >
                            <h4>
                                <i class="bi bi-mortarboard-fill"></i>
                                Qualifications
                            </h4>

                            <p id="livePreviewQualifications">
                                <?= te_e($teacher['qualifications'] ?? '') ?>
                            </p>
                        </div>


                        <div
                            id="livePreviewAchievementsSection"
                            class="live-preview-block"
                            <?= empty(trim((string)($teacher['achievements'] ?? ''))) ? 'style="display:none"' : '' ?>
                        >
                            <h4>
                                <i class="bi bi-trophy-fill"></i>
                                Achievements
                            </h4>

                            <p id="livePreviewAchievements">
                                <?= te_e($teacher['achievements'] ?? '') ?>
                            </p>
                        </div>

                    </div>


                    <div
                        id="livePreviewLinks"
                        class="live-preview-links"
                    >
                        <a
                            id="livePreviewWebsite"
                            href="#"
                            target="_blank"
                            rel="noopener"
                            style="display:none"
                        >
                            <i class="bi bi-globe"></i>
                            Website
                        </a>

                        <a
                            id="livePreviewFacebook"
                            href="#"
                            target="_blank"
                            rel="noopener"
                            style="display:none"
                        >
                            <i class="bi bi-facebook"></i>
                            Facebook
                        </a>

                        <a
                            id="livePreviewInstagram"
                            href="#"
                            target="_blank"
                            rel="noopener"
                            style="display:none"
                        >
                            <i class="bi bi-instagram"></i>
                            Instagram
                        </a>

                        <a
                            id="livePreviewYoutube"
                            href="#"
                            target="_blank"
                            rel="noopener"
                            style="display:none"
                        >
                            <i class="bi bi-youtube"></i>
                            YouTube
                        </a>
                    </div>

                </div>

            </div>

        </section>


        <div style="display:flex;justify-content:flex-end;gap:8px">
            <a class="btn" href="<?= te_e($publicUrl) ?>">Cancel</a>
            <button class="btn primary" type="submit"><i class="bi bi-check2-circle"></i> Save Profile</button>
        </div>
    </form>
</div>
<script>
(function(){
 if (window.EckTheme) { return; }
 const html=document.documentElement,b=document.getElementById('themeToggle'),i=document.getElementById('themeIcon');
 if(!b){return;}
 function set(t){html.setAttribute('data-bs-theme',t);document.cookie='dark_mode='+(t==='dark'?'true':'false')+';path=/;max-age=31536000;SameSite=Lax';i.className=t==='dark'?'bi bi-sun-fill':'bi bi-moon-stars-fill';}
 set(html.getAttribute('data-bs-theme')==='dark'?'dark':'light');
 b.addEventListener('click',()=>set(html.getAttribute('data-bs-theme')==='dark'?'light':'dark'));
})();
</script>
<?php if (function_exists('app_theme_js_link')) { app_theme_js_link(); } ?>

<script>
(function () {

    function fieldValue(id) {

        const field =
            document.getElementById(id);

        return field
            ? field.value.trim()
            : '';
    }


    function setText(id, value) {

        const element =
            document.getElementById(id);

        if (!element) {
            return;
        }

        element.textContent =
            value;
    }


    function toggleSection(id, visible) {

        const element =
            document.getElementById(id);

        if (!element) {
            return;
        }

        element.style.display =
            visible ? '' : 'none';
    }


    function updatePreview() {

        const bio =
            fieldValue('bio');

        const qualifications =
            fieldValue('qualifications');

        const achievements =
            fieldValue('achievements');

        const experience =
            fieldValue('experience_years');

        const website =
            fieldValue('website');

        const facebook =
            fieldValue('facebook');

        const instagram =
            fieldValue('instagram');

        const youtube =
            fieldValue('youtube');


        /*
         * Biography
         */
        setText(
            'livePreviewBio',
            bio
        );

        toggleSection(
            'livePreviewBioSection',
            bio !== ''
        );


        /*
         * Qualifications
         */
        setText(
            'livePreviewQualifications',
            qualifications
        );

        toggleSection(
            'livePreviewQualificationsSection',
            qualifications !== ''
        );


        /*
         * Achievements
         */
        setText(
            'livePreviewAchievements',
            achievements
        );

        toggleSection(
            'livePreviewAchievementsSection',
            achievements !== ''
        );


        /*
         * Experience
         */
        const experienceBox =
            document.getElementById(
                'livePreviewExperience'
            );

        if (experienceBox) {

            if (experience !== '') {

                experienceBox.style.display =
                    'flex';

                const span =
                    experienceBox.querySelector(
                        'span'
                    );

                if (span) {

                    span.textContent =
                        experience +
                        '+ years teaching experience';
                }

            } else {

                experienceBox.style.display =
                    'none';
            }
        }


        /*
         * Online links
         */
        updateLink(
            'livePreviewWebsite',
            website
        );

        updateLink(
            'livePreviewFacebook',
            facebook
        );

        updateLink(
            'livePreviewInstagram',
            instagram
        );

        updateLink(
            'livePreviewYoutube',
            youtube
        );
    }


    function updateLink(id, value) {

        const link =
            document.getElementById(id);

        if (!link) {
            return;
        }

        if (!value) {

            link.style.display =
                'none';

            link.removeAttribute('href');

            return;
        }

        link.style.display =
            'inline-flex';

        link.href =
            value;
    }


    /*
     * Update text fields live.
     */
    [
        'bio',
        'qualifications',
        'achievements',
        'experience_years',
        'website',
        'facebook',
        'instagram',
        'youtube'
    ].forEach(function (id) {

        const field =
            document.getElementById(id);

        if (!field) {
            return;
        }

        field.addEventListener(
            'input',
            updatePreview
        );

        field.addEventListener(
            'change',
            updatePreview
        );
    });


    /*
     * Teacher photo preview.
     */
    const photoInput =
        document.getElementById(
            'teacher_photo'
        );

    if (photoInput) {

        photoInput.addEventListener(
            'change',
            function () {

                const file =
                    this.files &&
                    this.files[0];

                if (!file) {
                    return;
                }

                if (
                    !file.type.startsWith(
                        'image/'
                    )
                ) {
                    return;
                }

                const reader =
                    new FileReader();

                reader.onload =
                    function (event) {

                        const image =
                            document.getElementById(
                                'livePreviewPhoto'
                            );

                        const placeholder =
                            document.getElementById(
                                'livePreviewPhotoPlaceholder'
                            );

                        if (image) {

                            image.src =
                                event.target.result;

                            image.style.display =
                                'block';
                        }

                        if (placeholder) {

                            placeholder.style.display =
                                'none';
                        }
                    };

                reader.readAsDataURL(file);
            }
        );
    }


    /*
     * Run once when page loads.
     */
    updatePreview();

})();
</script>

<script>
(function () {

    const input =
        document.getElementById('teacher_photo');

    const preview =
        document.getElementById('teacherPhotoPreview');

    const placeholder =
        document.getElementById(
            'teacherPhotoPreviewPlaceholder'
        );

    const filename =
        document.getElementById(
            'teacherPhotoFilename'
        );

    if (!input) {
        return;
    }

    input.addEventListener(
        'change',
        function () {

            const file =
                this.files && this.files[0];

            if (!file) {
                return;
            }

            if (filename) {

                filename.textContent =
                    file.name +
                    ' · ' +
                    Math.round(
                        file.size / 1024
                    ) +
                    ' KB';

            }

            if (!file.type.startsWith('image/')) {
                return;
            }

            const reader =
                new FileReader();

            reader.onload =
                function (event) {

                    if (preview) {

                        preview.src =
                            event.target.result;

                        preview.style.display =
                            'block';

                    }

                    if (placeholder) {

                        placeholder.style.display =
                            'none';

                    }

                };

            reader.readAsDataURL(file);

        }
    );

})();
</script>

</body>
</html>
