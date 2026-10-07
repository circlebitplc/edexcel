<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

require_admin();

$id = (int)($_GET['id'] ?? 0);

if ($id < 1) {
    $_SESSION['error'] = 'Invalid teacher ID.';
    header('Location: index.php');
    exit;
}

/* ============================================================
   HELPERS
   ============================================================ */

function admin_profile_e(mixed $value): string
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}

/* ============================================================
   TEACHER
   ============================================================ */

$stmt = $pdo->prepare("
    SELECT
        id,
        name,
        email,
        phone,
        photo
    FROM teachers
    WHERE id = ?
      AND deleted_at IS NULL
    LIMIT 1
");

$stmt->execute([$id]);

$teacher = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$teacher) {
    $_SESSION['error'] = 'Teacher not found.';
    header('Location: index.php');
    exit;
}

/* ============================================================
   PROFILE
   ============================================================ */

$stmt = $pdo->prepare("
    SELECT *
    FROM teacher_profiles
    WHERE teacher_id = ?
    LIMIT 1
");

$stmt->execute([$id]);

$profile = $stmt->fetch(PDO::FETCH_ASSOC) ?: [
    'bio' => '',
    'qualifications' => '',
    'experience_years' => '',
    'achievements' => '',
    'profile_background' => '',
    'website' => '',
    'facebook' => '',
    'instagram' => '',
    'youtube' => ''
];

$error = '';
$success = '';

/* ============================================================
   BACKGROUND DIRECTORY
   ============================================================ */

$backgroundDir =
    __DIR__ .
    '/../uploads/teachers/backgrounds/';

if (!is_dir($backgroundDir)) {

    mkdir(
        $backgroundDir,
        0755,
        true
    );
}

/* ============================================================
   SAVE
   ============================================================ */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (
        !verify_csrf_token(
            $_POST['csrf_token'] ?? ''
        )
    ) {

        $error =
            'Invalid security token. Please try again.';

    } else {

        $bio =
            trim(
                $_POST['bio'] ?? ''
            );

        $qualifications =
            trim(
                $_POST['qualifications'] ?? ''
            );

        $experience =
            trim(
                $_POST['experience_years'] ?? ''
            );

        $achievements =
            trim(
                $_POST['achievements'] ?? ''
            );

        $website =
            trim(
                $_POST['website'] ?? ''
            );

        $facebook =
            trim(
                $_POST['facebook'] ?? ''
            );

        $instagram =
            trim(
                $_POST['instagram'] ?? ''
            );

        $youtube =
            trim(
                $_POST['youtube'] ?? ''
            );

        $removeBackground =
            isset(
                $_POST['remove_background']
            );

        /* -----------------------------------------------
           EXPERIENCE
        ------------------------------------------------ */

        if (
            $experience !== '' &&
            (
                !ctype_digit($experience) ||
                (int)$experience > 100
            )
        ) {

            $error =
                'Experience must be a valid number of years.';
        }

        /* -----------------------------------------------
           URL VALIDATION
        ------------------------------------------------ */

        $urls = [
            'Website' => $website,
            'Facebook' => $facebook,
            'Instagram' => $instagram,
            'YouTube' => $youtube
        ];

        if (empty($error)) {

            foreach ($urls as $label => $url) {

                if (
                    $url !== '' &&
                    !filter_var(
                        $url,
                        FILTER_VALIDATE_URL
                    )
                ) {

                    $error =
                        $label .
                        ' must be a valid URL.';

                    break;
                }
            }
        }

        /* -----------------------------------------------
           BACKGROUND UPLOAD
        ------------------------------------------------ */

        $backgroundName =
            $profile['profile_background'] ?? null;

        if (
            empty($error) &&
            $removeBackground
        ) {

            if ($backgroundName) {

                $oldFile =
                    $backgroundDir .
                    basename($backgroundName);

                if (is_file($oldFile)) {
                    @unlink($oldFile);
                }
            }

            $backgroundName = null;
        }

        if (
            empty($error) &&
            isset($_FILES['profile_background']) &&
            $_FILES['profile_background']['error']
                !== UPLOAD_ERR_NO_FILE
        ) {

            $file =
                $_FILES['profile_background'];

            if (
                $file['error'] !==
                UPLOAD_ERR_OK
            ) {

                $error =
                    'Background image upload failed.';

            } elseif (
                $file['size'] > 5 * 1024 * 1024
            ) {

                $error =
                    'Background image must be smaller than 5 MB.';

            } else {

                $finfo =
                    new finfo(FILEINFO_MIME_TYPE);

                $mime =
                    $finfo->file(
                        $file['tmp_name']
                    );

                $allowed = [
                    'image/jpeg' => 'jpg',
                    'image/png' => 'png',
                    'image/webp' => 'webp'
                ];

                if (
                    !isset(
                        $allowed[$mime]
                    )
                ) {

                    $error =
                        'Only JPG, PNG or WebP images are allowed.';

                } else {

                    if ($backgroundName) {

                        $oldFile =
                            $backgroundDir .
                            basename($backgroundName);

                        if (is_file($oldFile)) {
                            @unlink($oldFile);
                        }
                    }

                    $backgroundName =
                        'teacher_' .
                        $id .
                        '_background.' .
                        $allowed[$mime];

                    $destination =
                        $backgroundDir .
                        $backgroundName;

                    if (
                        !move_uploaded_file(
                            $file['tmp_name'],
                            $destination
                        )
                    ) {

                        $error =
                            'Could not save the background image.';
                    }
                }
            }
        }

        /* -----------------------------------------------
           SAVE DATABASE
        ------------------------------------------------ */

        if (empty($error)) {

            $experienceValue =
                $experience === ''
                    ? null
                    : (int)$experience;

            $stmt = $pdo->prepare("
                INSERT INTO teacher_profiles (
                    teacher_id,
                    bio,
                    qualifications,
                    experience_years,
                    achievements,
                    profile_background,
                    website,
                    facebook,
                    instagram,
                    youtube
                )
                VALUES (
                    :teacher_id,
                    :bio,
                    :qualifications,
                    :experience_years,
                    :achievements,
                    :profile_background,
                    :website,
                    :facebook,
                    :instagram,
                    :youtube
                )
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
                ':teacher_id' =>
                    $id,

                ':bio' =>
                    $bio !== ''
                        ? $bio
                        : null,

                ':qualifications' =>
                    $qualifications !== ''
                        ? $qualifications
                        : null,

                ':experience_years' =>
                    $experienceValue,

                ':achievements' =>
                    $achievements !== ''
                        ? $achievements
                        : null,

                ':profile_background' =>
                    $backgroundName,

                ':website' =>
                    $website !== ''
                        ? $website
                        : null,

                ':facebook' =>
                    $facebook !== ''
                        ? $facebook
                        : null,

                ':instagram' =>
                    $instagram !== ''
                        ? $instagram
                        : null,

                ':youtube' =>
                    $youtube !== ''
                        ? $youtube
                        : null
            ]);

            $success =
                'Teacher profile updated successfully.';

            /* Refresh profile */

            $stmt = $pdo->prepare("
                SELECT *
                FROM teacher_profiles
                WHERE teacher_id = ?
                LIMIT 1
            ");

            $stmt->execute([$id]);

            $profile =
                $stmt->fetch(PDO::FETCH_ASSOC) ?: $profile;
        }
    }
}

$page_title =
    'Manage Profile - ' .
    $teacher['name'];

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid py-4">

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">

        <div>

            <div class="text-body-secondary small mb-1">
                Teacher Management
            </div>

            <h1 class="mb-1">
                <i class="bi bi-person-vcard"></i>
                Manage Teacher Profile
            </h1>

            <p class="text-body-secondary mb-0">
                <?= admin_profile_e($teacher['name']) ?>
            </p>

        </div>

        <div class="d-flex gap-2">

            <a
                href="teacher_profile.php?id=<?= $id ?>"
                target="_blank"
                class="btn btn-outline-primary"
            >
                <i class="bi bi-box-arrow-up-right"></i>
                View Public Profile
            </a>

            <a
                href="index.php"
                class="btn btn-outline-secondary"
            >
                <i class="bi bi-arrow-left"></i>
                Back
            </a>

        </div>

    </div>


    <?php if ($success): ?>

        <div class="alert alert-success">
            <i class="bi bi-check-circle-fill"></i>
            <?= admin_profile_e($success) ?>
        </div>

    <?php endif; ?>


    <?php if ($error): ?>

        <div class="alert alert-danger">
            <i class="bi bi-exclamation-triangle-fill"></i>
            <?= admin_profile_e($error) ?>
        </div>

    <?php endif; ?>


    <div class="row g-4">

        <div class="col-xl-8">

            <form
                method="post"
                enctype="multipart/form-data"
                class="card border-0 shadow-sm"
            >

                <div class="card-body p-4">

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= admin_profile_e(
                            generate_csrf_token()
                        ) ?>"
                    >


                    <h2 class="h5 mb-3">
                        <i class="bi bi-person-lines-fill"></i>
                        Professional Information
                    </h2>


                    <div class="mb-4">

                        <label class="form-label fw-semibold">
                            Biography
                        </label>

                        <textarea
                            name="bio"
                            class="form-control"
                            rows="6"
                            placeholder="Write a professional biography..."
                        ><?= admin_profile_e(
                            $profile['bio'] ?? ''
                        ) ?></textarea>

                    </div>


                    <div class="mb-4">

                        <label class="form-label fw-semibold">
                            Qualifications
                        </label>

                        <textarea
                            name="qualifications"
                            class="form-control"
                            rows="5"
                            placeholder="Degrees, certifications, professional qualifications..."
                        ><?= admin_profile_e(
                            $profile['qualifications'] ?? ''
                        ) ?></textarea>

                    </div>


                    <div class="mb-4">

                        <label class="form-label fw-semibold">
                            Years of Experience
                        </label>

                        <input
                            type="number"
                            name="experience_years"
                            class="form-control"
                            min="0"
                            max="100"
                            value="<?= admin_profile_e(
                                $profile['experience_years'] ?? ''
                            ) ?>"
                        >

                    </div>


                    <div class="mb-4">

                        <label class="form-label fw-semibold">
                            Achievements
                        </label>

                        <textarea
                            name="achievements"
                            class="form-control"
                            rows="6"
                            placeholder="Awards, achievements, recognitions..."
                        ><?= admin_profile_e(
                            $profile['achievements'] ?? ''
                        ) ?></textarea>

                    </div>


                    <hr class="my-4">


                    <h2 class="h5 mb-3">
                        <i class="bi bi-share"></i>
                        Social & Web Links
                    </h2>


                    <div class="row g-3">

                        <div class="col-md-6">

                            <label class="form-label">
                                Website
                            </label>

                            <input
                                type="url"
                                name="website"
                                class="form-control"
                                value="<?= admin_profile_e(
                                    $profile['website'] ?? ''
                                ) ?>"
                                placeholder="https://..."
                            >

                        </div>


                        <div class="col-md-6">

                            <label class="form-label">
                                Facebook
                            </label>

                            <input
                                type="url"
                                name="facebook"
                                class="form-control"
                                value="<?= admin_profile_e(
                                    $profile['facebook'] ?? ''
                                ) ?>"
                                placeholder="https://facebook.com/..."
                            >

                        </div>


                        <div class="col-md-6">

                            <label class="form-label">
                                Instagram
                            </label>

                            <input
                                type="url"
                                name="instagram"
                                class="form-control"
                                value="<?= admin_profile_e(
                                    $profile['instagram'] ?? ''
                                ) ?>"
                                placeholder="https://instagram.com/..."
                            >

                        </div>


                        <div class="col-md-6">

                            <label class="form-label">
                                YouTube
                            </label>

                            <input
                                type="url"
                                name="youtube"
                                class="form-control"
                                value="<?= admin_profile_e(
                                    $profile['youtube'] ?? ''
                                ) ?>"
                                placeholder="https://youtube.com/..."
                            >

                        </div>

                    </div>


                    <hr class="my-4">


                    <h2 class="h5 mb-3">
                        <i class="bi bi-image"></i>
                        Profile Background
                    </h2>


                    <input
                        type="file"
                        name="profile_background"
                        class="form-control"
                        accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                    >

                    <div class="form-text">
                        JPG, PNG or WebP. Maximum 5 MB.
                    </div>


                    <?php if (
                        !empty(
                            $profile['profile_background']
                        )
                    ): ?>

                        <div class="form-check mt-3">

                            <input
                                type="checkbox"
                                class="form-check-input"
                                name="remove_background"
                                id="removeBackground"
                                value="1"
                            >

                            <label
                                class="form-check-label"
                                for="removeBackground"
                            >
                                Remove current background
                            </label>

                        </div>

                    <?php endif; ?>


                    <div class="mt-4">

                        <button
                            type="submit"
                            class="btn btn-primary btn-lg"
                        >
                            <i class="bi bi-check-lg"></i>
                            Save Teacher Profile
                        </button>

                    </div>

                </div>

            </form>

        </div>


        <div class="col-xl-4">

            <div class="card border-0 shadow-sm mb-4">

                <div class="card-body p-4">

                    <h2 class="h5">
                        <i class="bi bi-person-circle"></i>
                        Teacher
                    </h2>

                    <div class="d-flex align-items-center gap-3">

                        <?php
                        $photo = '';

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

                            $candidate =
                                __DIR__ .
                                '/../assets/images/teachers/' .
                                $id .
                                '.' .
                                $ext;

                            if (is_file($candidate)) {

                                $photo =
                                    BASE_URL .
                                    'assets/images/teachers/' .
                                    $id .
                                    '.' .
                                    $ext;

                                break;
                            }
                        }
                        ?>

                        <?php if ($photo): ?>

                            <img
                                src="<?= admin_profile_e($photo) ?>"
                                alt="<?= admin_profile_e($teacher['name']) ?>"
                                style="
                                    width:90px;
                                    height:90px;
                                    object-fit:cover;
                                    border-radius:50%;
                                "
                            >

                        <?php else: ?>

                            <div
                                class="rounded-circle bg-body-secondary d-grid place-items-center"
                                style="
                                    width:90px;
                                    height:90px;
                                "
                            >
                                <i class="bi bi-person fs-1"></i>
                            </div>

                        <?php endif; ?>


                        <div>

                            <h3 class="h5 mb-1">
                                <?= admin_profile_e(
                                    $teacher['name']
                                ) ?>
                            </h3>

                            <div class="small text-body-secondary">
                                <?= admin_profile_e(
                                    $teacher['email']
                                ) ?>
                            </div>

                            <?php if (
                                !empty(
                                    $teacher['phone']
                                )
                            ): ?>

                                <div class="small text-body-secondary">
                                    <?= admin_profile_e(
                                        $teacher['phone']
                                    ) ?>
                                </div>

                            <?php endif; ?>

                        </div>

                    </div>

                </div>

            </div>


            <div class="card border-0 shadow-sm">

                <div class="card-body p-4">

                    <h2 class="h5">
                        <i class="bi bi-info-circle"></i>
                        Profile Fields
                    </h2>

                    <ul class="small text-body-secondary mb-0">

                        <li>Biography</li>
                        <li>Qualifications</li>
                        <li>Experience</li>
                        <li>Achievements</li>
                        <li>Background image</li>
                        <li>Website</li>
                        <li>Facebook</li>
                        <li>Instagram</li>
                        <li>YouTube</li>

                    </ul>

                </div>

            </div>

        </div>

    </div>

</div>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>
