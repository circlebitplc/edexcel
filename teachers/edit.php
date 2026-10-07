<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/campus.php';
require_once __DIR__ . '/../config/bunny.php';
require_once __DIR__ . '/../vendor/autoload.php';

require_admin();

if ($pdo instanceof PDO) {
    ensure_recordings_schema($pdo);
}

$id = $_GET['id'] ?? 0;

if (!$id) {
    header('Location: index.php');
    exit();
}


/* ============================================================
   GET TEACHER
   ============================================================ */

$stmt = $pdo->prepare("
    SELECT *
    FROM teachers
    WHERE id = ?
    LIMIT 1
");

$stmt->execute([$id]);

$teacher = $stmt->fetch();

if (!$teacher) {

    $_SESSION['error'] =
        'Teacher not found.';

    header('Location: index.php');
    exit();
}


/* ============================================================
   GET CURRENT SUBJECT ASSIGNMENTS
   ============================================================ */

$assigned = $pdo->prepare("
    SELECT subject_id
    FROM teacher_subjects
    WHERE teacher_id = ?
");

$assigned->execute([$id]);

$assigned_ids =
    $assigned->fetchAll(
        PDO::FETCH_COLUMN
    );


/* ============================================================
   GET ALL SUBJECTS
   ============================================================ */

$subjects = $pdo->query("
    SELECT *
    FROM subjects
    ORDER BY name
")->fetchAll();


/* ============================================================
   GET TEACHER LOGIN ACCOUNT
   ============================================================ */

$teacher_user = null;

try {

    $user_stmt = $pdo->prepare("
        SELECT
            id,
            username,
            role,
            teacher_id,
            is_active,
            google_email,
            google_id,
            teacher_oauth_status
        FROM users
        WHERE teacher_id = ?
          AND role = 'teacher'
          AND deleted_at IS NULL
        LIMIT 1
    ");

    $user_stmt->execute([
        $id
    ]);

    $teacher_user =
        $user_stmt->fetch(
            PDO::FETCH_ASSOC
        );

} catch (PDOException $e) {

    /*
     * Do not stop the edit page if the user table
     * cannot be queried. The teacher information can
     * still be edited.
     */
    $teacher_user = null;
}


/* ============================================================
   MESSAGES
   ============================================================ */

$error = '';
$success = '';

if ($success === '' && !empty($_SESSION['success'])) {
    $success = (string)$_SESSION['success'];
    unset($_SESSION['success']);
}
if ($error === '' && !empty($_SESSION['error'])) {
    $error = (string)$_SESSION['error'];
    unset($_SESSION['error']);
}


/* ============================================================
   PHOTO UPLOAD DIRECTORY
   ============================================================ */

$upload_dir =
    __DIR__ .
    '/../assets/images/teachers/';

if (!is_dir($upload_dir)) {

    mkdir(
        $upload_dir,
        0755,
        true
    );
}


/* ============================================================
   FORM SUBMISSION
   ============================================================ */

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
) {

    /* --------------------------------------------------------
       CSRF
       -------------------------------------------------------- */

    $csrf_token =
        $_POST['csrf_token'] ?? '';

    if (
        !verify_csrf_token(
            $csrf_token
        )
    ) {

        $error =
            'Invalid security token. Please try again.';

    } else {

        $bunnyAction = trim((string)($_POST['bunny_action'] ?? ''));
        $skipTeacherUpdate = false;

        if ($bunnyAction === 'create_library' || $bunnyAction === 'test_library') {
            $skipTeacherUpdate = true;
            try {
                $bunnyLibraries = new \Edexcel\Services\TeacherBunnyLibraryService($pdo);
                if ($bunnyAction === 'create_library') {
                    $created = $bunnyLibraries->createOnBunny((int)$id);
                    $success = 'Bunny Stream library created. Library ID: ' . (string)($created['library_id'] ?? '');
                    log_audit($pdo, 'teacher_bunny_library_create', 'teachers', (int)$id, null, [
                        'bunny_library_id' => (string)($created['library_id'] ?? ''),
                    ]);
                } else {
                    $bunnyClient = \Edexcel\Services\BunnyVideoService::forTeacher($pdo, (int)$id);
                    $test = $bunnyClient->testConnection();
                    if ($test['ok']) {
                        $success = $test['message'];
                    } else {
                        $error = $test['message'];
                    }
                }
                $reload = $pdo->prepare('SELECT * FROM teachers WHERE id = ? LIMIT 1');
                $reload->execute([$id]);
                $reloaded = $reload->fetch();
                if ($reloaded) {
                    $teacher = $reloaded;
                }
            } catch (Throwable $e) {
                $error = $e->getMessage();
            }
        }

        if (!$skipTeacherUpdate) {

        /* ----------------------------------------------------
           BASIC TEACHER INFORMATION
           ---------------------------------------------------- */

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

        $subject_ids =
            $_POST['subjects'] ?? [];

        $remove_photo =
            isset(
                $_POST['remove_photo']
            );


        /* ----------------------------------------------------
           PASSWORD INFORMATION
           ---------------------------------------------------- */

        $new_password =
            $_POST['new_password'] ?? '';

        $confirm_password =
            $_POST['confirm_password'] ?? '';


        /*
         * A password is changed ONLY if the admin
         * entered something in the new-password field.
         */
        $change_password =
            ($new_password !== '');


        /* ----------------------------------------------------
           USERNAME INFORMATION
           ---------------------------------------------------- */

        $posted_username =
            trim(
                (string)($_POST['username'] ?? '')
            );

        $change_username = false;
        $new_username = '';

        if (
            $teacher_user
        ) {

            $current_username =
                (string)($teacher_user['username'] ?? '');

            if (
                $posted_username === ''
            ) {

                $error =
                    'Username is required.';

            } elseif (
                strcasecmp(
                    $posted_username,
                    $current_username
                ) !== 0
            ) {

                $change_username = true;
                $new_username =
                    strtolower(
                        $posted_username
                    );
            }
        }


        /* ----------------------------------------------------
           VALIDATE BASIC INFORMATION
           ---------------------------------------------------- */

        if (
            empty($error) &&
            (
                empty($name) ||
                empty($email)
            )
        ) {

            $error =
                'Name and email are required.';
        }


        /* ----------------------------------------------------
           VALIDATE USERNAME
           ---------------------------------------------------- */

        if (
            empty($error) &&
            $change_username
        ) {

            if (
                strlen($new_username) < 3 ||
                strlen($new_username) > 64
            ) {

                $error =
                    'Username must be between 3 and 64 characters.';

            } elseif (
                !preg_match(
                    '/^[a-z0-9._-]+$/',
                    $new_username
                )
            ) {

                $error =
                    'Username may only contain lowercase letters, numbers, dots, underscores, and hyphens.';

            } else {

                $username_check =
                    $pdo->prepare("
                        SELECT id
                        FROM users
                        WHERE username = ?
                          AND id <> ?
                        LIMIT 1
                    ");

                $username_check->execute([
                    $new_username,
                    (int)$teacher_user['id']
                ]);

                if (
                    $username_check->fetch()
                ) {

                    $error =
                        'That username is already in use. Please choose another.';
                }
            }
        }


        /* ----------------------------------------------------
           VALIDATE PASSWORD
           ---------------------------------------------------- */

        if (
            empty($error) &&
            $change_password
        ) {

            if (
                strlen($new_password) < 8
            ) {

                $error =
                    'New password must contain at least 8 characters.';

            } elseif (
                $new_password !==
                $confirm_password
            ) {

                $error =
                    'New password and confirmation password do not match.';
            }
        }


        /* ----------------------------------------------------
           NORMALISE SUBJECT IDS
           ---------------------------------------------------- */

        if (
            !is_array($subject_ids)
        ) {

            $subject_ids = [];
        }

        $subject_ids =
            array_values(
                array_unique(
                    array_filter(
                        array_map(
                            'intval',
                            $subject_ids
                        ),
                        function ($value) {
                            return $value > 0;
                        }
                    )
                )
            );


        /* ----------------------------------------------------
           PHOTO PROCESSING
           ---------------------------------------------------- */

        $photo_filename =
            $id . '.png';

        /*
         * Preserve current photo by default.
         */
        $photo_path =
            $teacher['photo'] ?? null;

        $upload_error = '';


        if (
            empty($error)
        ) {

            /* ------------------------------------------------
               REMOVE PHOTO
               ------------------------------------------------ */

            if (
                $remove_photo
            ) {

                foreach (
                    [
                        'png',
                        'jpg',
                        'jpeg',
                        'webp',
                        'gif'
                    ]
                    as $old_ext
                ) {

                    $old_file =
                        $upload_dir .
                        $id .
                        '.' .
                        $old_ext;

                    if (
                        file_exists(
                            $old_file
                        )
                    ) {

                        @unlink(
                            $old_file
                        );
                    }
                }

                $photo_path = null;
            }


            /* ------------------------------------------------
               NEW PHOTO
               ------------------------------------------------ */

            elseif (
                isset(
                    $_FILES['photo']
                ) &&
                $_FILES['photo']['error'] ===
                UPLOAD_ERR_OK
            ) {

                $file =
                    $_FILES['photo'];

                // Maximum teacher profile image size: 512 KB
                $max_size = 512 * 1024;

                $allowed_mime = [
                    'image/jpeg',
                    'image/png',
                    'image/gif',
                    'image/webp'
                ];


                /* --------------------------------------------
                   FILE SIZE
                   -------------------------------------------- */

                if (
                    $file['size'] >
                    $max_size
                ) {

                    $upload_error =
                        'Profile photo must be 512 KB or smaller.';
                }


                /* --------------------------------------------
                   BASIC MIME CHECK
                   -------------------------------------------- */

                elseif (
                    !in_array(
                        $file['type'],
                        $allowed_mime,
                        true
                    )
                ) {

                    $upload_error =
                        'Only JPG, PNG, GIF, and WEBP images are allowed.';
                }


                /* --------------------------------------------
                   READ IMAGE
                   -------------------------------------------- */

                else {

                    $image_data =
                        file_get_contents(
                            $file['tmp_name']
                        );

                    if (
                        $image_data === false
                    ) {

                        $upload_error =
                            'Failed to read the image file.';

                    } else {

                        /* ------------------------------------
                           REAL MIME TYPE
                           ------------------------------------ */

                        $mime = '';

                        if (
                            function_exists(
                                'finfo_open'
                            )
                        ) {

                            $finfo =
                                finfo_open(
                                    FILEINFO_MIME_TYPE
                                );

                            if ($finfo) {

                                $mime =
                                    finfo_file(
                                        $finfo,
                                        $file['tmp_name']
                                    );

                                unset($finfo);
                            }
                        }

                        if (
                            empty($mime)
                        ) {

                            $mime =
                                $file['type'] ?? '';
                        }


                        $allowed_mime_map = [
                            'image/jpeg' =>
                                'jpg',

                            'image/png' =>
                                'png',

                            'image/gif' =>
                                'gif',

                            'image/webp' =>
                                'webp'
                        ];


                        if (
                            !isset(
                                $allowed_mime_map[
                                    $mime
                                ]
                            )
                        ) {

                            $upload_error =
                                'Only valid JPG, PNG, GIF, and WEBP images are allowed.';

                        } else {

                            /*
                             * Remove older teacher photo variants.
                             */
                            foreach (
                                [
                                    'png',
                                    'jpg',
                                    'jpeg',
                                    'webp',
                                    'gif'
                                ]
                                as $old_ext
                            ) {

                                $old_file =
                                    $upload_dir .
                                    $id .
                                    '.' .
                                    $old_ext;

                                if (
                                    file_exists(
                                        $old_file
                                    )
                                ) {

                                    @unlink(
                                        $old_file
                                    );
                                }
                            }


                            /*
                             * Always attempt to save PNG.
                             */
                            $dest =
                                $upload_dir .
                                $id .
                                '.png';


                            if (
                                function_exists(
                                    'imagecreatefromstring'
                                ) &&
                                function_exists(
                                    'imagepng'
                                )
                            ) {

                                /*
                                 * GD/libpng may emit harmless warnings for PNG files
                                 * containing an incorrect iCCP/sRGB profile.
                                 * The global error handler converts warnings into
                                 * exceptions, so isolate this decoding operation.
                                 */
                                $previous_error_handler = set_error_handler(
                                    static function (
                                        int $errno,
                                        string $errstr
                                    ): bool {
                                        return true;
                                    },
                                    E_WARNING
                                );

                                try {
                                    $image = imagecreatefromstring(
                                        $image_data
                                    );
                                } finally {
                                    restore_error_handler();
                                }

                                if (
                                    $image === false
                                ) {

                                    $upload_error =
                                        'Failed to process the image. Please try another image.';

                                } else {

                                    /*
                                     * Preserve transparency
                                     * where possible.
                                     */
                                    if (
                                        function_exists(
                                            'imagealphablending'
                                        ) &&
                                        function_exists(
                                            'imagesavealpha'
                                        )
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


                                    if (
                                        !@imagepng(
                                            $image,
                                            $dest,
                                            9
                                        )
                                    ) {

                                        $upload_error =
                                            'Failed to save the processed image. Please try another image.';
                                    }



                                }

                            } else {

                                /*
                                 * GD unavailable.
                                 * Save the original file using
                                 * its original extension.
                                 */
                                $original_ext =
                                    $allowed_mime_map[
                                        $mime
                                    ];

                                $dest =
                                    $upload_dir .
                                    $id .
                                    '.' .
                                    $original_ext;


                                if (
                                    !move_uploaded_file(
                                        $file['tmp_name'],
                                        $dest
                                    )
                                ) {

                                    $upload_error =
                                        'Unable to save the uploaded image. Check the uploads directory permissions.';
                                }
                            }


                            if (
                                empty($upload_error)
                            ) {

                                /*
                                 * We normally save as PNG.
                                 */
                                if (
                                    file_exists(
                                        $upload_dir .
                                        $id .
                                        '.png'
                                    )
                                ) {

                                    $photo_path =
                                        $id . '.png';

                                } else {

                                    $photo_path =
                                        $id .
                                        '.' .
                                        $allowed_mime_map[
                                            $mime
                                        ];
                                }
                            }
                        }
                    }
                }
            }
        }


        /* ----------------------------------------------------
           STOP IF PHOTO ERROR
           ---------------------------------------------------- */

        if (
            empty($error) &&
            !empty($upload_error)
        ) {

            $error =
                $upload_error;
        }


        /* ====================================================
           DATABASE UPDATE
           ==================================================== */

        if (
            empty($error)
        ) {

            try {

                $pdo->beginTransaction();


                /* ============================================
                   UPDATE TEACHER
                   ============================================ */

                $update_teacher =
                    $pdo->prepare("
                        UPDATE teachers
                        SET
                            name = ?,
                            email = ?,
                            phone = ?
                        WHERE id = ?
                    ");

                $update_teacher->execute([
                    $name,
                    $email,
                    $phone,
                    $id
                ]);

                // Sync teacher's user account google_email if unlinked or blank
                if ($email !== '') {
                    $pdo->prepare("
                        UPDATE users
                        SET google_email = ?
                        WHERE teacher_id = ?
                          AND role = 'teacher'
                          AND (google_id IS NULL OR google_id = '' OR google_email IS NULL OR google_email = '')
                    ")->execute([$email, $id]);
                }

                (new \Edexcel\Services\TeacherBunnyLibraryService($pdo))->saveManual(
                    (int)$id,
                    trim((string)($_POST['bunny_library_id'] ?? '')),
                    trim((string)($_POST['bunny_api_key'] ?? '')),
                    trim((string)($_POST['bunny_token_key'] ?? '')),
                    trim((string)($_POST['bunny_webhook_secret'] ?? '')),
                    trim((string)($_POST['bunny_cdn_hostname'] ?? ''))
                );


                /* ============================================
                   UPDATE SUBJECT ASSIGNMENTS
                   ============================================ */

                $pdo->prepare("
                    DELETE FROM teacher_subjects
                    WHERE teacher_id = ?
                ")->execute([
                    $id
                ]);


                if (
                    !empty($subject_ids)
                ) {

                    $insert_subject =
                        $pdo->prepare("
                            INSERT INTO teacher_subjects
                                (
                                    teacher_id,
                                    subject_id
                                )
                            VALUES
                                (
                                    ?,
                                    ?
                                )
                        ");

                    foreach (
                        $subject_ids
                        as $sid
                    ) {

                        $insert_subject->execute([
                            $id,
                            $sid
                        ]);
                    }
                }


                /* ============================================
                   CHANGE TEACHER USERNAME / PASSWORD
                   ============================================ */

                $password_changed = false;
                $username_changed = false;
                $previous_username = null;

                if (
                    $change_username ||
                    $change_password
                ) {

                    /*
                     * Find the teacher's login account.
                     */
                    $user_stmt =
                        $pdo->prepare("
                            SELECT
                                id,
                                username,
                                role,
                                teacher_id
                            FROM users
                            WHERE teacher_id = ?
                              AND role = 'teacher'
                              AND deleted_at IS NULL
                            LIMIT 1
                        ");

                    $user_stmt->execute([
                        $id
                    ]);

                    $teacher_user =
                        $user_stmt->fetch(
                            PDO::FETCH_ASSOC
                        );


                    if (
                        !$teacher_user
                    ) {

                        /*
                         * Do NOT silently create an account.
                         * This edit page only changes an existing
                         * teacher login account.
                         */
                        throw new RuntimeException(
                            'No teacher login account exists for this teacher. The login details could not be changed.'
                        );
                    }


                    if (
                        $change_username
                    ) {

                        $previous_username =
                            (string)$teacher_user['username'];

                        $update_username =
                            $pdo->prepare("
                                UPDATE users
                                SET
                                    username = ?
                                WHERE id = ?
                                  AND teacher_id = ?
                                  AND role = 'teacher'
                                  AND deleted_at IS NULL
                            ");

                        $update_username->execute([
                            $new_username,
                            $teacher_user['id'],
                            $id
                        ]);

                        $username_changed = true;
                        $teacher_user['username'] =
                            $new_username;
                    }


                    if (
                        $change_password
                    ) {

                        /*
                         * Hash the password securely.
                         */
                        $password_hash =
                            password_hash(
                                $new_password,
                                PASSWORD_DEFAULT
                            );


                        if (
                            $password_hash === false
                        ) {

                            throw new RuntimeException(
                                'Unable to securely process the new password.'
                            );
                        }


                        /*
                         * Update ONLY the password hash.
                         */
                        $update_password =
                            $pdo->prepare("
                                UPDATE users
                                SET
                                    password_hash = ?
                                WHERE id = ?
                                  AND teacher_id = ?
                                  AND role = 'teacher'
                                  AND deleted_at IS NULL
                            ");

                        $update_password->execute([
                            $password_hash,
                            $teacher_user['id'],
                            $id
                        ]);

                        $password_changed = true;
                    }
                }


                /* ============================================
                   COMMIT
                   ============================================ */

                $pdo->commit();


                /* ============================================
                   AUDIT LOG
                   ============================================ */

                $audit_data = [
                    'name' =>
                        $name,

                    'email' =>
                        $email,

                    'phone' =>
                        $phone,

                    'photo' =>
                        $photo_path
                ];


                if (
                    $username_changed
                ) {

                    $audit_data[
                        'username_changed'
                    ] = true;

                    $audit_data[
                        'login_username_from'
                    ] =
                        $previous_username;

                    $audit_data[
                        'login_username_to'
                    ] =
                        $new_username;
                }

                if (
                    $password_changed
                ) {

                    /*
                     * Never put the actual password
                     * into the audit log.
                     */
                    $audit_data[
                        'password_changed'
                    ] = true;

                    if (
                        !empty($teacher_user['username'])
                    ) {

                        $audit_data[
                            'login_username'
                        ] =
                            $teacher_user[
                                'username'
                            ];
                    }
                }


                log_audit(
                    $pdo,
                    'update',
                    'teachers',
                    $id,
                    $teacher,
                    $audit_data
                );


                /* ============================================
                   SUCCESS MESSAGE
                   ============================================ */

                if (
                    $username_changed &&
                    $password_changed
                ) {

                    $_SESSION['success'] =
                        'Teacher updated successfully. The teacher portal username and password have also been changed.';

                } elseif (
                    $username_changed
                ) {

                    $_SESSION['success'] =
                        'Teacher updated successfully. The teacher portal username has also been changed.';

                } elseif (
                    $password_changed
                ) {

                    $_SESSION['success'] =
                        'Teacher updated successfully. The teacher portal password has also been changed.';

                } else {

                    $_SESSION['success'] =
                        'Teacher updated successfully.';
                }


                header(
                    'Location: edit.php?id=' .
                    urlencode($id)
                );

                exit();


            } catch (RuntimeException $e) {

                if (
                    $pdo->inTransaction()
                ) {

                    $pdo->rollBack();
                }

                $error =
                    $e->getMessage();


            } catch (PDOException $e) {

                if (
                    $pdo->inTransaction()
                ) {

                    $pdo->rollBack();
                }

                error_log(
                    'Teacher edit database error: ' .
                    $e->getMessage()
                );

                $error =
                    'Database error while updating the teacher. Please try again.';
            }
        }
        }
    }
}


/* ============================================================
   FIND CURRENT PHOTO
   ============================================================ */

$photo_file = '';

foreach (
    [
        'png',
        'jpg',
        'jpeg',
        'webp',
        'gif'
    ]
    as $ext
) {

    if (
        file_exists(
            $upload_dir .
            $id .
            '.' .
            $ext
        )
    ) {

        $photo_file =
            $id .
            '.' .
            $ext;

        break;
    }
}


/* ============================================================
   ESCAPE HELPER
   ============================================================ */

function teacher_edit_e($value)
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}

?>

<?php include __DIR__ . '/../includes/header.php'; ?>


<div class="teacher-page-header">

    <div>

        <h1 class="teacher-page-title">

            <i class="bi bi-pencil-square"></i>

            Edit Teacher

        </h1>

        <p class="teacher-page-subtitle">

            Update profile information,
            login password, photo and
            subject assignments.

        </p>

    </div>


    <a
        href="index.php"
        class="btn btn-outline-secondary"
    >

        <i class="bi bi-arrow-left"></i>

        Back to Teachers

    </a>

</div>


<?php if ($error): ?>

    <div class="alert alert-danger">

        <i
            class="bi bi-exclamation-triangle-fill me-1"
        ></i>

        <?= teacher_edit_e($error) ?>

    </div>

<?php endif; ?>


<?php if ($success): ?>

    <div class="alert alert-success">

        <i
            class="bi bi-check-circle-fill me-1"
        ></i>

        <?= teacher_edit_e($success) ?>

    </div>

<?php endif; ?>


<div class="teacher-form-card">

<form
    method="POST"
    enctype="multipart/form-data"
    autocomplete="off"
>

<?= csrf_field() ?>


<!-- ========================================================
     BASIC INFORMATION
========================================================= -->

<div class="teacher-form-section">

    <h5>

        <i class="bi bi-person-vcard"></i>

        Basic Information

    </h5>


    <div class="row g-3">


        <div class="col-lg-7">


            <!-- NAME -->

            <div class="mb-3">

                <label
                    for="name"
                    class="form-label"
                >

                    Full Name

                    <span class="text-danger">
                        *
                    </span>

                </label>


                <input
                    type="text"
                    class="form-control"
                    id="name"
                    name="name"
                    required
                    value="<?= teacher_edit_e($teacher['name']) ?>"
                >

            </div>


            <!-- EMAIL -->

            <div class="mb-3">

                <label
                    for="email"
                    class="form-label"
                >

                    Email

                    <span class="text-danger">
                        *
                    </span>

                </label>


                <input
                    type="email"
                    class="form-control"
                    id="email"
                    name="email"
                    required
                    value="<?= teacher_edit_e($teacher['email']) ?>"
                >

            </div>


            <!-- PHONE -->

            <div>

                <label
                    for="phone"
                    class="form-label"
                >

                    WhatsApp / Phone

                </label>


                <input
                    type="tel"
                    class="form-control"
                    id="phone"
                    name="phone"
                    value="<?= teacher_edit_e($teacher['phone'] ?? '') ?>"
                    placeholder="+94771234567"
                >


                <div class="form-text">

                    Include the country code
                    for WhatsApp notifications.

                </div>

            </div>

        </div>


        <!-- ==================================================
             PHOTO
        =================================================== -->

        <div class="col-lg-5">

            <label class="form-label">

                Profile Photo

            </label>


            <div class="teacher-photo-box">


                <?php if ($photo_file): ?>


                    <img
                        class="teacher-photo-preview"
                        src="<?= BASE_URL ?>assets/images/teachers/<?= teacher_edit_e($photo_file) ?>"
                        alt="Teacher Photo"
                    >


                    <div class="form-check mb-2">

                        <input
                            class="form-check-input"
                            type="checkbox"
                            id="remove_photo"
                            name="remove_photo"
                            value="1"
                        >


                        <label
                            class="form-check-label text-danger"
                            for="remove_photo"
                        >

                            Remove current photo

                        </label>

                    </div>


                <?php else: ?>


                    <div class="teacher-photo-placeholder">

                        <i class="bi bi-person"></i>

                    </div>


                    <div class="small text-muted mb-2">

                        No photo uploaded

                    </div>


                <?php endif; ?>


                <input
                    type="file"
                    class="form-control"
                    id="photo"
                    name="photo"
                    accept="image/jpeg,image/png,image/gif,image/webp"
                >


                <div class="form-text">

                    Max 512 KB.

                    JPG, PNG, GIF or WEBP.

                    Saved as <?= teacher_edit_e($id) ?>.png.

                </div>

            </div>

        </div>

    </div>

</div>


<!-- ========================================================
     TEACHER LOGIN ACCOUNT
========================================================= -->

<div class="teacher-form-section">

    <h5>

        <i class="bi bi-shield-lock"></i>

        Teacher Login Account

    </h5>


    <p class="text-muted small mb-3">

        Change the username or password used by
        this teacher to access the Teacher Portal.

        Leave the password fields blank if you
        do not want to change the password.

    </p>


    <?php if ($teacher_user): ?>

        <?php
            $username_field_value =
                (
                    $_SERVER['REQUEST_METHOD'] === 'POST' &&
                    array_key_exists('username', $_POST)
                )
                    ? trim((string)$_POST['username'])
                    : (string)$teacher_user['username'];
        ?>


        <!-- USERNAME -->

        <div class="mb-3">

            <label
                class="form-label"
                for="username"
            >

                Username

            </label>


            <div class="input-group">

                <span class="input-group-text">

                    <i class="bi bi-person"></i>

                </span>


                <input
                    type="text"
                    class="form-control"
                    id="username"
                    name="username"
                    value="<?= teacher_edit_e($username_field_value) ?>"
                    required
                    minlength="3"
                    maxlength="64"
                    pattern="[A-Za-z0-9._\-]+"
                    autocomplete="username"
                >


                <?php if (
                    isset(
                        $teacher_user['is_active']
                    ) &&
                    (int)$teacher_user['is_active'] !== 1
                ): ?>

                    <span class="input-group-text text-danger">

                        <i class="bi bi-pause-circle"></i>

                        Inactive

                    </span>

                <?php else: ?>

                    <span class="input-group-text text-success">

                        <i class="bi bi-check-circle"></i>

                        Active

                    </span>

                <?php endif; ?>

            </div>


            <div class="form-text">
                This is the username the teacher
                uses to sign in to the Teacher Portal.
                Letters, numbers, dots, underscores,
                and hyphens only.
            </div>

            <div class="mt-2">
                <?php if (!empty($teacher_user['google_id'])): ?>
                    <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">
                        <i class="bi bi-google"></i> Google Login Linked: <?= teacher_edit_e($teacher_user['google_email'] ?: $teacher['email']) ?>
                    </span>
                <?php elseif (!empty($teacher['email'])): ?>
                    <span class="badge bg-info-subtle text-info border border-info-subtle px-2 py-1">
                        <i class="bi bi-google"></i> Google Login: Authorized for <?= teacher_edit_e($teacher['email']) ?> (connects automatically on first sign-in)
                    </span>
                <?php else: ?>
                    <span class="badge bg-warning-subtle text-warning border border-warning-subtle px-2 py-1">
                        <i class="bi bi-exclamation-circle"></i> Add an email address above to enable Google Login
                    </span>
                <?php endif; ?>
            </div>
        </div>


        <!-- NEW PASSWORD -->

        <div class="row g-3">


            <div class="col-md-6">

                <label
                    for="new_password"
                    class="form-label"
                >

                    New Password

                </label>


                <div class="input-group">

                    <span class="input-group-text">

                        <i class="bi bi-key"></i>

                    </span>


                    <input
                        type="password"
                        class="form-control"
                        id="new_password"
                        name="new_password"
                        minlength="8"
                        autocomplete="new-password"
                        placeholder="Enter new password"
                    >


                    <button
                        type="button"
                        class="btn btn-outline-secondary password-toggle"
                        data-target="new_password"
                        aria-label="Show password"
                    >

                        <i class="bi bi-eye"></i>

                    </button>

                </div>


                <div class="form-text">

                    Minimum 8 characters.

                </div>

            </div>


            <!-- CONFIRM PASSWORD -->

            <div class="col-md-6">

                <label
                    for="confirm_password"
                    class="form-label"
                >

                    Confirm New Password

                </label>


                <div class="input-group">

                    <span class="input-group-text">

                        <i class="bi bi-key-fill"></i>

                    </span>


                    <input
                        type="password"
                        class="form-control"
                        id="confirm_password"
                        name="confirm_password"
                        minlength="8"
                        autocomplete="new-password"
                        placeholder="Re-enter new password"
                    >


                    <button
                        type="button"
                        class="btn btn-outline-secondary password-toggle"
                        data-target="confirm_password"
                        aria-label="Show password"
                    >

                        <i class="bi bi-eye"></i>

                    </button>

                </div>


                <div
                    id="password-match-message"
                    class="form-text"
                ></div>

            </div>

        </div>


        <!-- PASSWORD NOTICE -->

        <div class="alert alert-warning mt-3 mb-0">

            <i
                class="bi bi-info-circle-fill me-1"
            ></i>

            Changing the username or password here
            will immediately update this teacher's
            Teacher Portal login.

            The current password cannot be displayed.

        </div>


    <?php else: ?>


        <!-- NO ACCOUNT -->

        <div class="alert alert-warning mb-0">

            <i
                class="bi bi-exclamation-triangle-fill me-1"
            ></i>

            <strong>
                No teacher login account found.
            </strong>

            <br>

            This teacher does not currently have
            a linked account in the
            <code>users</code> table.

            A password cannot be changed from this
            page until a teacher login account exists.

        </div>


    <?php endif; ?>

</div>


<!-- ========================================================
     SUBJECTS
========================================================= -->

<div class="teacher-form-section">

    <h5>

        <i class="bi bi-book"></i>

        Subjects

    </h5>


    <p class="text-muted small mb-3">

        Keep subject assignments accurate so
        teacher cards, timetable filters and
        reports remain correct.

    </p>


    <div class="subject-check-grid">

        <?php foreach (
            $subjects as $s
        ): ?>

            <div class="subject-check">

                <div class="form-check">

                    <input
                        class="form-check-input"
                        type="checkbox"
                        name="subjects[]"
                        value="<?= (int)$s['id'] ?>"
                        id="sub_<?= (int)$s['id'] ?>"
                        <?= in_array(
                            $s['id'],
                            $assigned_ids
                        ) ? 'checked' : '' ?>
                    >


                    <label
                        class="form-check-label"
                        for="sub_<?= (int)$s['id'] ?>"
                    >

                        <?= teacher_edit_e($s['name']) ?>

                    </label>

                </div>

            </div>

        <?php endforeach; ?>

    </div>

</div>


<!-- ========================================================
     BUNNY STREAM LIBRARY
========================================================= -->

<div class="teacher-form-section">

    <h5>
        <i class="bi bi-play-btn"></i>
        Bunny Stream library
    </h5>

    <p class="text-muted small mb-3">
        Each teacher must have a different Bunny.net library ID.
        Lesson recordings for this teacher’s timetable are stored in this library.
        Click Create library (account API key required in Settings) or paste credentials from an existing Stream library.
    </p>

    <?php
        $teacherBunnyId = trim((string)($teacher['bunny_library_id'] ?? ''));
        $teacherBunnyReady = $teacherBunnyId !== '' && trim((string)($teacher['bunny_api_key'] ?? '')) !== '';
    ?>

    <?php if ($teacherBunnyReady): ?>
        <div class="alert alert-success py-2">
            Library ID <code><?= teacher_edit_e($teacherBunnyId) ?></code> is assigned to this teacher only.
            <?php if (!empty($teacher['bunny_library_name'])): ?>
                <span class="text-muted"><?= teacher_edit_e($teacher['bunny_library_name']) ?></span>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="alert alert-warning py-2">
            No unique Bunny library yet. This teacher cannot upload class recordings until one is assigned.
        </div>
    <?php endif; ?>

    <div class="mb-3">
        <label class="form-label">Library ID</label>
        <input
            class="form-control"
            name="bunny_library_id"
            value="<?= teacher_edit_e($teacherBunnyId) ?>"
            autocomplete="off"
            placeholder="Numeric Stream library ID"
        >
        <div class="form-text">Leave blank and click Update Teacher to unlink this teacher from Bunny.</div>
    </div>

    <div class="mb-3">
        <label class="form-label">Library AccessKey</label>
        <input
            type="password"
            class="form-control"
            name="bunny_api_key"
            value=""
            placeholder="<?= trim((string)($teacher['bunny_api_key'] ?? '')) !== '' ? 'Saved — leave blank to keep' : 'Stream library AccessKey' ?>"
            autocomplete="new-password"
        >
    </div>

    <div class="mb-3">
        <label class="form-label">CDN hostname (optional)</label>
        <input
            class="form-control"
            name="bunny_cdn_hostname"
            value="<?= teacher_edit_e($teacher['bunny_cdn_hostname'] ?? '') ?>"
            placeholder="vz-xxxx.b-cdn.net"
            autocomplete="off"
        >
    </div>

    <div class="mb-3">
        <label class="form-label">Token authentication key (optional)</label>
        <input
            type="password"
            class="form-control"
            name="bunny_token_key"
            value=""
            placeholder="<?= trim((string)($teacher['bunny_token_key'] ?? '')) !== '' ? 'Saved — leave blank to keep' : 'Usually the library token security key' ?>"
            autocomplete="new-password"
        >
    </div>

    <div class="mb-3">
        <label class="form-label">Webhook signing secret (optional)</label>
        <input
            type="password"
            class="form-control"
            name="bunny_webhook_secret"
            value=""
            placeholder="<?= trim((string)($teacher['bunny_webhook_secret'] ?? '')) !== '' ? 'Saved — leave blank to keep' : 'Library Read-Only API key' ?>"
            autocomplete="new-password"
        >
    </div>

    <div class="d-flex flex-wrap gap-2">
        <button
            type="submit"
            class="btn btn-outline-primary"
            name="bunny_action"
            value="create_library"
        >
            <i class="bi bi-plus-circle"></i>
            Create library on Bunny
        </button>
        <button
            type="submit"
            class="btn btn-outline-secondary"
            name="bunny_action"
            value="test_library"
            <?= $teacherBunnyReady ? '' : 'disabled' ?>
        >
            Test library connection
        </button>
    </div>

</div>


<!-- ========================================================
     FORM BUTTONS
========================================================= -->

<div
    class="d-flex flex-wrap gap-2 justify-content-end"
>

    <a
        href="index.php"
        class="btn btn-outline-secondary"
    >

        Cancel

    </a>


    <button
        type="submit"
        class="btn btn-primary"
        id="update-teacher-button"
    >

        <i class="bi bi-save"></i>

        Update Teacher

    </button>

</div>


</form>

</div>


<!-- ========================================================
     PASSWORD JAVASCRIPT
========================================================= -->

<script>

document.addEventListener(
    'DOMContentLoaded',
    function () {

        /*
         * ================================================
         * SHOW / HIDE PASSWORD
         * ================================================
         */

        document
            .querySelectorAll(
                '.password-toggle'
            )
            .forEach(
                function (button) {

                    button.addEventListener(
                        'click',
                        function () {

                            const targetId =
                                button.getAttribute(
                                    'data-target'
                                );

                            const input =
                                document.getElementById(
                                    targetId
                                );

                            const icon =
                                button.querySelector(
                                    'i'
                                );


                            if (!input) {
                                return;
                            }


                            if (
                                input.type ===
                                'password'
                            ) {

                                input.type =
                                    'text';


                                if (icon) {

                                    icon.classList
                                        .remove(
                                            'bi-eye'
                                        );

                                    icon.classList
                                        .add(
                                            'bi-eye-slash'
                                        );
                                }


                                button.setAttribute(
                                    'aria-label',
                                    'Hide password'
                                );

                            } else {

                                input.type =
                                    'password';


                                if (icon) {

                                    icon.classList
                                        .remove(
                                            'bi-eye-slash'
                                        );

                                    icon.classList
                                        .add(
                                            'bi-eye'
                                        );
                                }


                                button.setAttribute(
                                    'aria-label',
                                    'Show password'
                                );
                            }

                        }
                    );

                }
            );


        /*
         * ================================================
         * PASSWORD MATCH CHECK
         * ================================================
         */

        const newPassword =
            document.getElementById(
                'new_password'
            );

        const confirmPassword =
            document.getElementById(
                'confirm_password'
            );

        const matchMessage =
            document.getElementById(
                'password-match-message'
            );


        function checkPasswordMatch() {

            if (
                !newPassword ||
                !confirmPassword ||
                !matchMessage
            ) {

                return;
            }


            const password =
                newPassword.value;

            const confirmation =
                confirmPassword.value;


            /*
             * Nothing entered.
             */
            if (
                password === '' &&
                confirmation === ''
            ) {

                matchMessage.textContent =
                    '';

                matchMessage.className =
                    'form-text';

                return;
            }


            /*
             * Password too short.
             */
            if (
                password !== '' &&
                password.length < 8
            ) {

                matchMessage.textContent =
                    'Password must contain at least 8 characters.';

                matchMessage.className =
                    'form-text text-danger';

                return;
            }


            /*
             * Confirmation empty.
             */
            if (
                confirmation === ''
            ) {

                matchMessage.textContent =
                    '';

                matchMessage.className =
                    'form-text';

                return;
            }


            /*
             * Passwords match.
             */
            if (
                password === confirmation
            ) {

                matchMessage.innerHTML =
                    '<i class="bi bi-check-circle-fill"></i> Passwords match.';

                matchMessage.className =
                    'form-text text-success';

            } else {

                matchMessage.innerHTML =
                    '<i class="bi bi-x-circle-fill"></i> Passwords do not match.';

                matchMessage.className =
                    'form-text text-danger';
            }
        }


        if (newPassword) {

            newPassword.addEventListener(
                'input',
                checkPasswordMatch
            );
        }


        if (confirmPassword) {

            confirmPassword.addEventListener(
                'input',
                checkPasswordMatch
            );
        }


        /*
         * ================================================
         * FINAL CLIENT-SIDE VALIDATION
         * ================================================
         */

        const form =
            document.querySelector(
                '.teacher-form-card form'
            );


        if (form) {

            form.addEventListener(
                'submit',
                function (event) {

                    if (
                        !newPassword ||
                        !confirmPassword
                    ) {

                        return;
                    }


                    const password =
                        newPassword.value;

                    const confirmation =
                        confirmPassword.value;


                    /*
                     * If no password was entered,
                     * leave it unchanged.
                     */
                    if (
                        password === '' &&
                        confirmation === ''
                    ) {

                        return;
                    }


                    if (
                        password.length < 8
                    ) {

                        event.preventDefault();

                        alert(
                            'New password must contain at least 8 characters.'
                        );

                        newPassword.focus();

                        return;
                    }


                    if (
                        password !==
                        confirmation
                    ) {

                        event.preventDefault();

                        alert(
                            'New password and confirmation password do not match.'
                        );

                        confirmPassword.focus();

                        return;
                    }

                }
            );
        }

    }
);

</script>


<?php include __DIR__ . '/../includes/footer.php'; ?> 