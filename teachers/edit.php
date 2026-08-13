<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

require_admin();

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
            is_active
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
           VALIDATE BASIC INFORMATION
           ---------------------------------------------------- */

        if (
            empty($name) ||
            empty($email)
        ) {

            $error =
                'Name and email are required.';
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

                $max_size =
                    2 * 1024 * 1024;

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
                        'Photo file size must be under 2MB.';
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

                                finfo_close(
                                    $finfo
                                );
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

                                $image =
                                    @imagecreatefromstring(
                                        $image_data
                                    );

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


                                    if (
                                        function_exists(
                                            'imagedestroy'
                                        )
                                    ) {

                                        imagedestroy(
                                            $image
                                        );
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
                   CHANGE TEACHER PASSWORD
                   ============================================ */

                $password_changed = false;

                if (
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
                            'No teacher login account exists for this teacher. The password could not be changed.'
                        );
                    }


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


                    if (
                        $update_password->rowCount() >= 0
                    ) {

                        $password_changed =
                            true;
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

                    Max 2MB.

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

        Change the password used by this teacher
        to access the Teacher Portal.

        Leave the password fields blank if you
        do not want to change the password.

    </p>


    <?php if ($teacher_user): ?>


        <!-- USERNAME -->

        <div class="mb-3">

            <label
                class="form-label"
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
                    value="<?= teacher_edit_e($teacher_user['username']) ?>"
                    readonly
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

            Changing the password here will immediately
            change the password used by this teacher
            for the Teacher Portal.

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