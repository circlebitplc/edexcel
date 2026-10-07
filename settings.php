<?php
// ============================================================
// settings.php
// Teacher account settings
// ============================================================

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/config.php';

require_teacher();

$user = get_logged_in_user($pdo);

$teacher_id =
    $_SESSION['teacher_id'] ??
    ($user['teacher_id'] ?? null);

$teacher_id = (int)$teacher_id;

if ($teacher_id <= 0) {
    die('Teacher profile is not correctly linked to this account.');
}


/* ============================================================
   LOAD TEACHER ACCOUNT DATA
   ============================================================ */

$stmt = $pdo->prepare("
    SELECT
        t.id,
        t.name,
        t.email,
        t.phone,
        u.id AS user_id,
        u.username
    FROM teachers t
    INNER JOIN users u
        ON u.teacher_id = t.id
    WHERE t.id = ?
      AND t.deleted_at IS NULL
      AND u.deleted_at IS NULL
    LIMIT 1
");

$stmt->execute([
    $teacher_id
]);

$teacher_account =
    $stmt->fetch(PDO::FETCH_ASSOC);

if (!$teacher_account) {
    die('Unable to load your teacher account.');
}

$account_message = '';
$account_error = '';

$password_message = '';
$password_error = '';

$teacher_photo_message = '';
$teacher_photo_error = '';

/* ============================================================
   HANDLE ACCOUNT INFORMATION UPDATE
   ============================================================ */

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    ($_POST['teacher_profile_action'] ?? '') === 'update_profile'
) {

    if (
        !verify_csrf_token(
            $_POST['csrf_token'] ?? ''
        )
    ) {

        $account_error =
            'Your session expired. Please refresh the page and try again.';

    } else {

        $name =
            trim(
                $_POST['name'] ?? ''
            );

        $email =
            trim(
                $_POST['email'] ?? ''
            );

        $phone =
            trim(
                $_POST['phone'] ?? ''
            );


        if ($name === '') {

            $account_error =
                'Please enter your full name.';

        } elseif (mb_strlen($name) > 100) {

            $account_error =
                'Name must be 100 characters or fewer.';

        } elseif (
            $email !== '' &&
            !filter_var(
                $email,
                FILTER_VALIDATE_EMAIL
            )
        ) {

            $account_error =
                'Please enter a valid email address.';

        } elseif (mb_strlen($email) > 100) {

            $account_error =
                'Email address must be 100 characters or fewer.';

        } elseif (mb_strlen($phone) > 20) {

            $account_error =
                'Phone number must be 20 characters or fewer.';

        } else {

            /*
             * Prevent another teacher from using the same
             * email address.
             */
            if ($email !== '') {

                $stmt = $pdo->prepare("
                    SELECT id
                    FROM teachers
                    WHERE email = ?
                      AND id <> ?
                      AND deleted_at IS NULL
                    LIMIT 1
                ");

                $stmt->execute([
                    $email,
                    $teacher_id
                ]);

                if ($stmt->fetch()) {

                    $account_error =
                        'That email address is already being used by another teacher.';
                }
            }


            if ($account_error === '') {

                $stmt = $pdo->prepare("
                    UPDATE teachers
                    SET
                        name = ?,
                        email = ?,
                        phone = ?
                    WHERE id = ?
                      AND deleted_at IS NULL
                ");

                $stmt->execute([
                    $name,
                    $email !== '' ? $email : null,
                    $phone !== '' ? $phone : null,
                    $teacher_id
                ]);


                /*
                 * Keep the current session username unchanged.
                 */
                $teacher_account['name'] =
                    $name;

                $teacher_account['email'] =
                    $email;

                $teacher_account['phone'] =
                    $phone;

                $account_message =
                    'Profile information updated successfully.';
            }
        }
    }
}


/* ============================================================
   HANDLE PASSWORD CHANGE
   ============================================================ */

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    ($_POST['teacher_profile_action'] ?? '') === 'change_password'
) {

    if (
        !verify_csrf_token(
            $_POST['csrf_token'] ?? ''
        )
    ) {

        $password_error =
            'Your session expired. Please refresh the page and try again.';

    } else {

        $current_password =
            $_POST['current_password'] ?? '';

        $new_password =
            $_POST['new_password'] ?? '';

        $confirm_password =
            $_POST['confirm_password'] ?? '';


        if (
            $current_password === '' ||
            $new_password === '' ||
            $confirm_password === ''
        ) {

            $password_error =
                'Please complete all password fields.';

        } elseif (
            strlen($new_password) < 8
        ) {

            $password_error =
                'New password must be at least 8 characters long.';

        } elseif (
            $new_password !== $confirm_password
        ) {

            $password_error =
                'New password and confirmation do not match.';

        } else {

            $stmt = $pdo->prepare("
                SELECT password_hash
                FROM users
                WHERE id = ?
                  AND teacher_id = ?
                  AND role = 'teacher'
                  AND deleted_at IS NULL
                  AND is_active = 1
                LIMIT 1
            ");

            $stmt->execute([
                (int)$teacher_account['user_id'],
                $teacher_id
            ]);

            $user_security =
                $stmt->fetch(PDO::FETCH_ASSOC);


            if (
                !$user_security ||
                !password_verify(
                    $current_password,
                    $user_security['password_hash']
                )
            ) {

                $password_error =
                    'Current password is incorrect.';

            } elseif (
                password_verify(
                    $new_password,
                    $user_security['password_hash']
                )
            ) {

                $password_error =
                    'New password must be different from your current password.';

            } else {

                $new_hash =
                    password_hash(
                        $new_password,
                        PASSWORD_DEFAULT
                    );

                $stmt = $pdo->prepare("
                    UPDATE users
                    SET password_hash = ?
                    WHERE id = ?
                      AND teacher_id = ?
                      AND role = 'teacher'
                      AND deleted_at IS NULL
                ");

                $stmt->execute([
                    $new_hash,
                    (int)$teacher_account['user_id'],
                    $teacher_id
                ]);


                /*
                 * Regenerate the session after a credential change.
                 */
                regenerate_session();

                $password_message =
                    'Password changed successfully.';
            }
        }
    }
}


/* ============================================================
   HANDLE PHOTO UPLOAD
   ============================================================ */

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    ($_POST['teacher_profile_action'] ?? '') === 'upload_photo'
) {

    if (
        !verify_csrf_token(
            $_POST['csrf_token'] ?? ''
        )
    ) {

        $teacher_photo_error =
            'Your session expired. Please refresh the page and try again.';

    } elseif (
        !isset($_FILES['teacher_photo'])
    ) {

        $teacher_photo_error =
            'Please select a profile photo.';

    } else {

        $file = $_FILES['teacher_photo'];

        if (
            $file['error'] !== UPLOAD_ERR_OK
        ) {

            $upload_errors = [
                UPLOAD_ERR_INI_SIZE =>
                    'The uploaded photo is too large.',

                UPLOAD_ERR_FORM_SIZE =>
                    'The uploaded photo is too large.',

                UPLOAD_ERR_PARTIAL =>
                    'The photo upload was incomplete.',

                UPLOAD_ERR_NO_FILE =>
                    'Please select a profile photo.',
            ];

            $teacher_photo_error =
                $upload_errors[$file['error']]
                ?? 'The photo could not be uploaded.';

        } else {

            $max_photo_size =
                512 * 1024;

            $allowed_mime = [
                'image/jpeg',
                'image/png',
                'image/gif',
                'image/webp'
            ];

            if (
                !is_uploaded_file(
                    $file['tmp_name']
                )
            ) {

                $teacher_photo_error =
                    'Invalid upload. Please try again.';

            } elseif (
                $file['size'] <= 0
            ) {

                $teacher_photo_error =
                    'The uploaded photo is empty.';

            } elseif (
                $file['size'] > $max_photo_size
            ) {

                $teacher_photo_error =
                    'Profile photo must be 512 KB or smaller.';

            } else {

                /* REAL MIME TYPE */
                $real_mime = '';

                if (
                    function_exists('finfo_open')
                ) {

                    $finfo =
                        finfo_open(
                            FILEINFO_MIME_TYPE
                        );

                    if ($finfo) {

                        $real_mime =
                            (string)finfo_file(
                                $finfo,
                                $file['tmp_name']
                            );
                    }
                }

                if (
                    !in_array(
                        $real_mime,
                        $allowed_mime,
                        true
                    )
                ) {

                    $teacher_photo_error =
                        'Only JPG, PNG, GIF, and WEBP images are allowed.';

                } else {

                    $image_data =
                        @file_get_contents(
                            $file['tmp_name']
                        );

                    if ($image_data === false) {

                        $teacher_photo_error =
                            'Failed to read the uploaded photo.';

                    } else {

                        /*
                         * Suppress harmless GD warnings.
                         */
                        set_error_handler(
                            static function (
                                int $errno,
                                string $errstr
                            ): bool {
                                return true;
                            },
                            E_WARNING
                        );

                        try {

                            $image =
                                imagecreatefromstring(
                                    $image_data
                                );

                        } finally {

                            restore_error_handler();
                        }

                        if ($image === false) {

                            $teacher_photo_error =
                                'Failed to process the photo. Please try another image.';

                        } else {

                            if (
                                function_exists('imagealphablending') &&
                                function_exists('imagesavealpha')
                            ) {

                                imagealphablending(
                                    $image,
                                    false
                                );

                                imagesavealpha(
                                    $image,
                                    true
                                );
                            }

                            $photo_dir =
                                __DIR__ .
                                '/assets/images/teachers';

                            if (
                                !is_dir($photo_dir) &&
                                !mkdir(
                                    $photo_dir,
                                    0755,
                                    true
                                )
                            ) {

                                $teacher_photo_error =
                                    'Unable to create the teacher photo directory.';

                            } elseif (
                                !is_writable($photo_dir)
                            ) {

                                $teacher_photo_error =
                                    'The teacher photo directory is not writable.';

                            } else {

                                /*
                                 * Always store as teacher_id.png
                                 */
                                $destination =
                                    $photo_dir .
                                    '/' .
                                    $teacher_id .
                                    '.png';

                                set_error_handler(
                                    static function (
                                        int $errno,
                                        string $errstr
                                    ): bool {
                                        return true;
                                    },
                                    E_WARNING
                                );

                                try {

                                    $saved =
                                        imagepng(
                                            $image,
                                            $destination,
                                            6
                                        );

                                } finally {

                                    restore_error_handler();
                                }

                                if (!$saved) {

                                    $teacher_photo_error =
                                        'Failed to save the profile photo.';

                                } else {

                                    /*
                                     * Remove old alternative extensions.
                                     */
                                    foreach (
                                        [
                                            'jpg',
                                            'jpeg',
                                            'webp',
                                            'gif'
                                        ] as $old_extension
                                    ) {

                                        $old_file =
                                            $photo_dir .
                                            '/' .
                                            $teacher_id .
                                            '.' .
                                            $old_extension;

                                        if (
                                            is_file($old_file)
                                        ) {

                                            @unlink($old_file);
                                        }
                                    }

                                    $teacher_photo_message =
                                        'Profile photo updated successfully.';
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}


/* ============================================================
   CURRENT PHOTO
   ============================================================ */

$teacher_photo_url = '';

$teacher_photo_file = '';

$teacher_photo_dir =
    __DIR__ .
    '/assets/images/teachers';

foreach (
    [
        'png',
        'jpg',
        'jpeg',
        'webp',
        'gif'
    ] as $extension
) {

    $candidate =
        $teacher_photo_dir .
        '/' .
        $teacher_id .
        '.' .
        $extension;

    if (is_file($candidate)) {

        $teacher_photo_file =
            $candidate;

        $teacher_photo_url =
            BASE_URL .
            'assets/images/teachers/' .
            $teacher_id .
            '.' .
            $extension .
            '?v=' .
            filemtime($candidate);

        break;
    }
}

include __DIR__ . '/includes/header.php';
?>

<div class="admin-dashboard settings-page">

    <section class="welcome-section mb-4">

        <div class="d-flex justify-content-between align-items-center gap-3">

            <div>

                <h1>
                    ⚙️ Settings
                </h1>

                <div class="subtitle">

                    <span class="badge-role">
                        TEACHER
                    </span>

                    Manage your profile and account settings.

                </div>

            </div>

        </div>

    </section>


    <?php if (function_exists('app_theme_render_settings_section')) { app_theme_render_settings_section(); } ?>


    <!-- ========================================================
         PERSONAL INFORMATION
    ========================================================= -->

    <section class="settings-card mb-4">

        <div class="settings-card-header">

            <div>

                <h2>
                    <i class="bi bi-person-vcard"></i>
                    Personal Information
                </h2>

                <p>
                    Update the information shown on your teacher profile.
                </p>

            </div>

        </div>


        <?php if ($account_message): ?>

            <div class="alert alert-success">

                <i class="bi bi-check-circle-fill me-1"></i>

                <?= htmlspecialchars(
                    $account_message,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>

            </div>

        <?php endif; ?>


        <?php if ($account_error): ?>

            <div class="alert alert-danger">

                <i class="bi bi-exclamation-triangle-fill me-1"></i>

                <?= htmlspecialchars(
                    $account_error,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>

            </div>

        <?php endif; ?>


        <form method="POST">

            <?= csrf_field() ?>

            <input
                type="hidden"
                name="teacher_profile_action"
                value="update_profile"
            >


            <div class="row g-3">

                <div class="col-md-6">

                    <label
                        for="settings_name"
                        class="form-label fw-semibold"
                    >
                        Full Name
                    </label>

                    <input
                        type="text"
                        class="form-control"
                        id="settings_name"
                        name="name"
                        maxlength="100"
                        value="<?= htmlspecialchars(
                            $teacher_account['name'] ?? '',
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"
                        required
                    >

                </div>


                <div class="col-md-6">

                    <label
                        for="settings_username"
                        class="form-label fw-semibold"
                    >
                        Username
                    </label>

                    <input
                        type="text"
                        class="form-control"
                        id="settings_username"
                        value="<?= htmlspecialchars(
                            $teacher_account['username'] ?? '',
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"
                        readonly
                        disabled
                    >

                    <div class="form-text">
                        Username cannot be changed here.
                    </div>

                </div>


                <div class="col-md-6">

                    <label
                        for="settings_email"
                        class="form-label fw-semibold"
                    >
                        Email Address
                    </label>

                    <input
                        type="email"
                        class="form-control"
                        id="settings_email"
                        name="email"
                        maxlength="100"
                        value="<?= htmlspecialchars(
                            $teacher_account['email'] ?? '',
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"
                        placeholder="your@email.com"
                    >

                </div>


                <div class="col-md-6">

                    <label
                        for="settings_phone"
                        class="form-label fw-semibold"
                    >
                        Phone Number
                    </label>

                    <input
                        type="tel"
                        class="form-control"
                        id="settings_phone"
                        name="phone"
                        maxlength="20"
                        value="<?= htmlspecialchars(
                            $teacher_account['phone'] ?? '',
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"
                        placeholder="07XXXXXXXX"
                    >

                </div>


            </div>


            <div class="mt-4">

                <button
                    type="submit"
                    class="btn btn-primary"
                >

                    <i class="bi bi-check2-circle me-1"></i>

                    Save Changes

                </button>

            </div>

        </form>

    </section>


    <!-- ========================================================
         PROFILE PHOTO
    ========================================================= -->

    <!-- ========================================================
         PROFILE SETTINGS
    ========================================================= -->

    <section class="settings-card">

        <div class="settings-card-header">

            <div>

                <h2>
                    <i class="bi bi-person-circle"></i>
                    Profile
                </h2>

                <p>
                    Update the photo shown on your teacher profile.
                </p>

            </div>

        </div>


        <div class="settings-profile">

            <div class="settings-photo">

                <?php if ($teacher_photo_url): ?>

                    <img
                        src="<?= htmlspecialchars(
                            $teacher_photo_url,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"
                        alt="Your profile photo"
                    >

                <?php else: ?>

                    <i class="bi bi-person"></i>

                <?php endif; ?>

            </div>


            <div class="settings-profile-info">

                <h3>
                    Profile Photo
                </h3>

                <p>
                    This photo will be displayed on your teacher profile
                    and dashboard.
                </p>


                <form
                    method="POST"
                    enctype="multipart/form-data"
                >

                    <?= csrf_field() ?>

                    <input
                        type="hidden"
                        name="teacher_profile_action"
                        value="upload_photo"
                    >


                    <div class="settings-upload-row">

                        <label class="btn btn-outline-primary">

                            <i class="bi bi-image me-1"></i>

                            Choose Photo

                            <input
                                type="file"
                                name="teacher_photo"
                                accept=".jpg,.jpeg,.png,.gif,.webp,image/jpeg,image/png,image/gif,image/webp"
                                hidden
                                required
                            >

                        </label>


                        <button
                            type="submit"
                            class="btn btn-primary"
                        >

                            <i class="bi bi-cloud-arrow-up me-1"></i>

                            Upload Photo

                        </button>

                    </div>


                    <small class="settings-help">

                        Maximum 512 KB · JPG, PNG, GIF or WEBP

                    </small>

                </form>


                <?php if ($teacher_photo_message): ?>

                    <div class="alert alert-success mt-3 mb-0">

                        <i class="bi bi-check-circle-fill me-1"></i>

                        <?= htmlspecialchars(
                            $teacher_photo_message,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>

                    </div>

                <?php endif; ?>


                <?php if ($teacher_photo_error): ?>

                    <div class="alert alert-danger mt-3 mb-0">

                        <i class="bi bi-exclamation-triangle-fill me-1"></i>

                        <?= htmlspecialchars(
                            $teacher_photo_error,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>

                    </div>

                <?php endif; ?>

            </div>

        </div>

    </section>

    <!-- ========================================================
         SECURITY / PASSWORD
    ========================================================= -->

    <section class="settings-card mb-4">

        <div class="settings-card-header">

            <div>

                <h2>
                    <i class="bi bi-shield-lock"></i>
                    Security
                </h2>

                <p>
                    Change your teacher portal password.
                </p>

            </div>

        </div>


        <?php if ($password_message): ?>

            <div class="alert alert-success">

                <i class="bi bi-check-circle-fill me-1"></i>

                <?= htmlspecialchars(
                    $password_message,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>

            </div>

        <?php endif; ?>


        <?php if ($password_error): ?>

            <div class="alert alert-danger">

                <i class="bi bi-exclamation-triangle-fill me-1"></i>

                <?= htmlspecialchars(
                    $password_error,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>

            </div>

        <?php endif; ?>


        <form method="POST">

            <?= csrf_field() ?>

            <input
                type="hidden"
                name="teacher_profile_action"
                value="change_password"
            >


            <div class="row g-3">

                <div class="col-md-12">

                    <label
                        for="current_password"
                        class="form-label fw-semibold"
                    >
                        Current Password
                    </label>

                    <input
                        type="password"
                        class="form-control"
                        id="current_password"
                        name="current_password"
                        autocomplete="current-password"
                        required
                    >

                </div>


                <div class="col-md-6">

                    <label
                        for="new_password"
                        class="form-label fw-semibold"
                    >
                        New Password
                    </label>

                    <input
                        type="password"
                        class="form-control"
                        id="new_password"
                        name="new_password"
                        minlength="8"
                        autocomplete="new-password"
                        required
                    >

                    <div class="form-text">
                        Minimum 8 characters.
                    </div>

                </div>


                <div class="col-md-6">

                    <label
                        for="confirm_password"
                        class="form-label fw-semibold"
                    >
                        Confirm New Password
                    </label>

                    <input
                        type="password"
                        class="form-control"
                        id="confirm_password"
                        name="confirm_password"
                        minlength="8"
                        autocomplete="new-password"
                        required
                    >

                </div>

            </div>


            <div class="mt-4">

                <button
                    type="submit"
                    class="btn btn-primary"
                >

                    <i class="bi bi-key me-1"></i>

                    Change Password

                </button>

            </div>

        </form>

    </section>


</div>


<style>

.eck-theme-settings {
    max-width: 1100px;
}

.settings-card {
    background: var(--surface);
    border: 1px solid var(--panel-border);
    border-radius: 18px;
    padding: 24px;
    box-shadow: var(--shadow-sm);
}

.settings-card-header {
    padding-bottom: 18px;
    margin-bottom: 20px;
    border-bottom: 1px solid var(--panel-border);
}

.settings-card-header h2 {
    margin: 0;
    color: var(--text);
    font-size: 1.15rem;
    font-weight: 800;
}

.settings-card-header h2 i {
    color: var(--primary);
    margin-right: 6px;
}

.settings-card-header p {
    margin: 5px 0 0;
    color: var(--muted);
    font-size: .82rem;
}

.settings-profile {
    display: flex;
    align-items: center;
    gap: 24px;
}

.settings-photo {
    width: 120px;
    height: 120px;
    flex: 0 0 120px;
    border-radius: 50%;
    overflow: hidden;
    display: grid;
    place-items: center;
    background: var(--surface-soft);
    border: 3px solid var(--primary);
    color: var(--muted);
    font-size: 3.5rem;
}

.settings-photo img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.settings-profile-info {
    flex: 1;
    min-width: 0;
}

.settings-profile-info h3 {
    margin: 0 0 5px;
    color: var(--text);
    font-size: 1rem;
    font-weight: 800;
}

.settings-profile-info p {
    margin: 0 0 14px;
    color: var(--muted);
    font-size: .82rem;
}

.settings-upload-row {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.settings-upload-row label {
    cursor: pointer;
}

.settings-help {
    display: block;
    margin-top: 8px;
    color: var(--muted);
    font-size: .72rem;
}

/* ============================================================
   ACCOUNT SETTINGS FORMS
============================================================ */

.settings-card .form-control {
    min-height: 44px;
    border-radius: 10px;
}

.settings-card .form-control:focus {
    box-shadow: 0 0 0 .2rem rgba(81, 97, 206, .12);
}

.settings-card .form-label {
    color: var(--text);
    font-size: .82rem;
}

.settings-card .form-text {
    color: var(--muted);
    font-size: .72rem;
}

.settings-card .btn {
    border-radius: 10px;
    font-weight: 700;
}

@media (max-width: 576px) {

    .settings-card {
        padding: 18px;
    }

    .settings-profile {
        align-items: flex-start;
        flex-direction: column;
    }

    .settings-upload-row {
        width: 100%;
        flex-direction: column;
    }

    .settings-upload-row .btn {
        width: 100%;
    }

}

</style>

<?php include __DIR__ . '/includes/footer.php'; ?>
