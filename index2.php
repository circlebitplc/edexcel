<?php
/**
 * Edexcel College
 * Public Homepage + Inline Teacher & Student Login
 *
 * index2.php — redesigned 2026 public UI.
 * PHP login, OTP, timetable, and teacher directory logic matches index.php.
 */

define('DB_ALLOW_FAILURE', true);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/student/otp_helpers.php';


// ============================================================
// HELPER FUNCTIONS
// ============================================================

function e($value): string
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
}


function teacherInitials(string $name): string
{
    $parts = preg_split(
        '/\s+/',
        trim($name)
    );

    $initials = '';

    foreach ($parts as $part) {

        if ($part !== '') {

            $initials .= strtoupper(
                substr($part, 0, 1)
            );
        }
    }

    return substr(
        $initials ?: 'TE',
        0,
        2
    );
}


function teacherAvatarColor(string $name): string
{
    $colors = [
        '#03bfcb',
        '#f8b400',
        '#4a90d9',
        '#27ae60',
        '#e67e22',
        '#8e44ad',
        '#1abc9c',
        '#e74c3c'
    ];

    return $colors[
        abs(crc32($name)) % count($colors)
    ];
}


function teacherPhotoPath(
    int $id,
    ?string $photo = null
): ?string
{
    /*
     * First use the photo path stored in the database.
     */
    $photo = trim(
        (string) $photo
    );

    if ($photo !== '') {

        /*
         * Remove accidental leading slash.
         */
        $photo = ltrim(
            $photo,
            '/'
        );

        /*
         * If the database already contains
         * uploads/teachers/filename.jpg
         */
        $absolutePhoto =
            __DIR__ .
            '/' .
            $photo;

        if (is_file($absolutePhoto)) {

            return $photo;
        }

        /*
         * Some records may contain only:
         *
         * filename.jpg
         */
        $filename =
            basename($photo);

        $possiblePaths = [
            "uploads/teachers/{$filename}",
            "uploads/teacher/{$filename}",
            "assets/uploads/teachers/{$filename}",
            "assets/images/teachers/{$filename}",
            "uploads/{$filename}",
        ];

        foreach ($possiblePaths as $path) {

            if (
                is_file(
                    __DIR__ . '/' . $path
                )
            ) {

                return $path;
            }
        }
    }

    /*
     * FALLBACK:
     *
     * Existing ID-based photo system.
     */
    /*
     * New teacher profile photo location.
     *
     * teachers/profile.php stores uploaded photos here:
     *
     * assets/images/teachers/{teacher_id}.ext
     */
    foreach (
        ['png', 'jpg', 'jpeg', 'webp', 'gif']
        as $ext
    ) {

        $relativePath =
            "assets/images/teachers/{$id}.{$ext}";

        $filePath =
            __DIR__ .
            '/' .
            $relativePath;

        if (is_file($filePath)) {

            return $relativePath;
        }
    }


    /*
     * Legacy teacher photo location.
     */
    foreach (
        ['jpg', 'jpeg', 'png', 'webp', 'gif']
        as $ext
    ) {

        $relativePath =
            "uploads/teachers/{$id}.{$ext}";

        $filePath =
            __DIR__ .
            '/' .
            $relativePath;

        if (is_file($filePath)) {

            return $relativePath;
        }
    }

    return null;
}


function teacherSubjects($subjects): array
{
    if (is_array($subjects)) {

        return array_values(
            array_filter(
                array_map(
                    'trim',
                    $subjects
                )
            )
        );
    }

    return array_values(
        array_filter(
            array_map(
                'trim',
                explode(
                    ',',
                    (string) $subjects
                )
            )
        )
    );
}


function whatsappUrl(?string $phone): ?string
{
    $digits = preg_replace(
        '/[^0-9]/',
        '',
        (string) $phone
    );

    if ($digits === '') {
        return null;
    }

    return "https://wa.me/{$digits}";
}


// ============================================================
// LOGIN STATE
// ============================================================

$activeSection = 'home';

$teacherLoginError = '';

$teacherOtpNotice = '';

$teacherOtpStep = false;

$studentLoginError = '';

$studentOtpNotice = '';

$studentOtpStep = false;


// ============================================================
// INLINE TEACHER LOGIN
// ============================================================

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['teacher_login'])
) {

    /*
     * Keep the user on the teacher-login section
     * if authentication fails.
     */
    $activeSection = 'teacher-login';

    $teacherLoginError = '';

    // --------------------------------------------------------
    // CSRF CHECK
    // --------------------------------------------------------

    if (
        !verify_csrf_token(
            $_POST['csrf_token'] ?? ''
        )
    ) {

        $teacherLoginError =
            'Your session expired. Please refresh the page and try again.';

    } else {

        // ----------------------------------------------------
        // GET LOGIN DETAILS
        // ----------------------------------------------------

        $username = trim(
            $_POST['teacher_username'] ?? ''
        );

        $password =
            $_POST['teacher_password'] ?? '';


        // ----------------------------------------------------
        // VALIDATION
        // ----------------------------------------------------

        if (
            $username === '' ||
            $password === ''
        ) {

            $teacherLoginError =
                'Please enter your username and password.';

        } elseif (!($pdo instanceof PDO)) {

            $teacherLoginError =
                'Unable to connect to the database. Please try again later.';

        } elseif (login_is_locked($pdo, $username)) {

            $teacherLoginError =
                'Too many failed sign-in attempts. Wait 15 minutes and try again.';

        } else {

            try {

                // ------------------------------------------------
                // FIND USER
                //
                // IMPORTANT:
                // This matches login.php authentication.
                // ------------------------------------------------

                $stmt = $pdo->prepare("
                    SELECT
                        id,
                        username,
                        password_hash,
                        role,
                        teacher_id,
                        deleted_at,
                        is_active
                    FROM users
                    WHERE username = ?
                      AND deleted_at IS NULL
                    LIMIT 1
                ");

                $stmt->execute([
                    $username
                ]);

                $user = $stmt->fetch(
                    PDO::FETCH_ASSOC
                );


                // ------------------------------------------------
                // VERIFY PASSWORD
                // ------------------------------------------------

                if (
                    !$user ||
                    empty($user['password_hash']) ||
                    !password_verify(
                        $password,
                        $user['password_hash']
                    )
                ) {

                    $teacherLoginError =
                        'Invalid username or password.';
                    login_record_failure($pdo, $username);

                } else {

                    // ------------------------------------------------
                    // CHECK USER ROLE
                    //
                    // The public "Teacher Login" form is allowed to
                    // authenticate BOTH teacher and admin accounts.
                    //
                    // Teachers continue to receive their teacher_id.
                    // Admin accounts receive the admin role so the
                    // dashboard/admin permissions work normally.
                    // ------------------------------------------------

                    $loginRole =
                        strtolower(
                            trim(
                                (string)($user['role'] ?? '')
                            )
                        );

                    if (
                        !in_array(
                            $loginRole,
                            ['teacher', 'admin'],
                            true
                        )
                    ) {

                        $teacherLoginError =
                            'This account is not registered as a teacher or administrator account.';

                    } elseif (
                        isset($user['is_active']) &&
                        (int)$user['is_active'] !== 1
                    ) {

                        $teacherLoginError =
                            $loginRole === 'admin'
                                ? 'This administrator account is currently inactive.'
                                : 'This teacher account is currently inactive.';

                    } else {

                        // ------------------------------------------------
                        // LOGIN SUCCESS (admins may still need WhatsApp OTP)
                        // ------------------------------------------------

                        login_clear_failures($pdo, $username);

                        $requireOtp = $loginRole === 'admin';
                        try {
                            require_once __DIR__ . '/config/ops.php';
                            require_once __DIR__ . '/includes/helpers.php';
                            $requireOtp = $requireOtp && ops_setting($pdo, 'admin_password_requires_otp', '1') === '1';
                        } catch (Throwable $e) {
                            $requireOtp = $loginRole === 'admin';
                        }
                        $otpPhoneForUser = staff_whatsapp_for_user($pdo, $user);
                        if ($requireOtp && $otpPhoneForUser !== '') {
                            $otpResult = send_staff_login_otp($pdo, $otpPhoneForUser);
                            if (!empty($otpResult['ok'])) {
                                $_SESSION['staff_pending_2fa_user_id'] = (int)$user['id'];
                                $teacherOtpStep = true;
                                $teacherOtpNotice = 'Enter the WhatsApp code to finish signing in as admin.';
                            } else {
                                $teacherLoginError = (string)($otpResult['message'] ?? 'Could not send a verification code. Use WhatsApp login.');
                            }
                        } else {
                            complete_staff_portal_login($user);

                            header(
                                'Location: ' . BASE_URL . 'dashboard.php'
                            );

                            exit;
                        }
                    }
                }

            } catch (PDOException $exception) {

                error_log(
                    'Inline teacher login error: ' .
                    $exception->getMessage()
                );

                $teacherLoginError =
                    'Unable to process your login. Please try again.';
            }
        }
    }
}


// ============================================================
// TEACHER WHATSAPP OTP LOGIN (forgotten password)
// ============================================================

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && (
        isset($_POST['teacher_otp_send'])
        || isset($_POST['teacher_otp_verify'])
        || isset($_POST['teacher_otp_resend'])
        || isset($_POST['teacher_otp_cancel'])
        || (isset($_POST['teacher_otp']) && $_POST['teacher_otp'] !== '')
    )
) {
    $activeSection = 'teacher-login';
    require_once __DIR__ . '/includes/helpers.php';
    require_once __DIR__ . '/student/otp_helpers.php';

    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $teacherLoginError = 'Your session expired. Please try again.';
    } elseif (isset($_POST['teacher_otp_cancel'])) {
        unset($_SESSION['staff_login_otp_phone'], $_SESSION['staff_pending_2fa_user_id']);
        $teacherOtpStep = false;
    } elseif (!($pdo instanceof PDO)) {
        $teacherLoginError = 'Unable to connect to the server. Please try again later.';
    } elseif (isset($_POST['teacher_otp_verify']) || (isset($_POST['teacher_otp']) && $_POST['teacher_otp'] !== '')) {
        $posted = normalize_phone((string)($_POST['teacher_username'] ?? current_staff_login_otp_phone()));
        $otp = (string)($_POST['teacher_otp'] ?? '');
        $newPassword = (string)($_POST['teacher_new_password'] ?? '');
        $newConfirm = (string)($_POST['teacher_new_password_confirm'] ?? '');
        $teacherOtpStep = true;
        if ($newPassword !== '' && $newPassword !== $newConfirm) {
            $teacherLoginError = 'New passwords do not match.';
        } elseif (!valid_lk_phone($posted)) {
            $teacherLoginError = 'Enter a valid Sri Lankan WhatsApp number.';
        } else {
            try {
                $result = verify_staff_login_otp($pdo, $posted, $otp, $newPassword);
            } catch (Throwable $e) {
                error_log('Staff OTP verify error: ' . $e->getMessage());
                $result = ['ok' => false, 'message' => 'Unable to verify the code. Please try again.'];
            }
            if (!empty($result['ok']) && !empty($result['user'])) {
                $pending = (int)($_SESSION['staff_pending_2fa_user_id'] ?? 0);
                if ($pending > 0 && (int)$result['user']['id'] !== $pending) {
                    $teacherLoginError = 'Sign in with the same admin account that requested the code.';
                } else {
                    unset($_SESSION['staff_pending_2fa_user_id']);
                    complete_staff_portal_login($result['user']);
                    header('Location: ' . BASE_URL . 'dashboard.php');
                    exit;
                }
            }
            $teacherLoginError = (string)($result['message'] ?? 'Could not verify the code.');
        }
    } else {
        $posted = normalize_phone((string)($_POST['teacher_phone'] ?? $_POST['teacher_username'] ?? ''));
        if (!valid_lk_phone($posted)) {
            $teacherLoginError = 'Enter a valid Sri Lankan WhatsApp number, e.g. 0771234567.';
        } else {
            $result = send_staff_login_otp($pdo, $posted);
            $teacherOtpStep = !empty($result['show_otp']);
            if ($result['ok']) {
                $teacherOtpNotice = $result['message'];
            } else {
                $teacherLoginError = $result['message'];
            }
        }
    }
}

if (!empty($_SESSION['staff_login_otp_phone']) || !empty($_SESSION['staff_pending_2fa_user_id'])) {
    $teacherOtpStep = true;
}


// ============================================================
// INLINE STUDENT LOGIN
// ============================================================

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['student_login'])
) {

    /*
     * If anything goes wrong, remain inside
     * index.php#student-login.
     */
    $activeSection = 'student-login';


    // --------------------------------------------------------
    // CSRF
    // --------------------------------------------------------

    if (
        !verify_csrf_token(
            $_POST['csrf_token'] ?? ''
        )
    ) {

        $studentLoginError =
            'Your session expired. Please try again.';

    } else {

        $username =
            trim(
                $_POST['student_username'] ?? ''
            );

        $password =
            (string)($_POST['student_password'] ?? '');

        if ($username === '') {
            $studentLoginError = 'Please enter your mobile number.';
        } elseif (!($pdo instanceof PDO)) {
            $studentLoginError = 'Unable to connect to the server. Please try again later.';
        } else {
            require_once __DIR__ . '/includes/helpers.php';
            $result = attempt_student_password_login($pdo, $username, $password);
            if (!empty($result['otp'])) {
                $studentOtpStep = !empty($result['show_otp']);
                if (!empty($result['ok'])) {
                    $studentOtpNotice = (string)($result['message'] ?? '');
                } else {
                    $studentLoginError = (string)($result['message'] ?? 'Could not send a login code.');
                }
            } elseif (!empty($result['ok']) && !empty($result['user'])) {
                $signed = student_portal_sign_in($result['user'], $pdo, 'password');
                if (($signed['status'] ?? '') === 'ok') {
                    header('Location: ' . BASE_URL . 'student/dashboard.php');
                    exit;
                }
                if (($signed['status'] ?? '') === 'otp') {
                    header('Location: ' . BASE_URL . 'student/login.php');
                    exit;
                }
                if (in_array(($signed['status'] ?? ''), ['device_limit', 'device_blocked'], true)) {
                    header('Location: ' . BASE_URL . 'student/device_gate.php');
                    exit;
                }
                $studentLoginError = (string)($signed['message'] ?? 'Could not sign in from this device.');
            } else {
                $studentLoginError = (string)($result['message'] ?? 'Invalid username or password.');
            }
        }
    }
}


// ============================================================
// STUDENT WHATSAPP OTP LOGIN (forgotten password)
// ============================================================

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && (
        isset($_POST['student_otp_send'])
        || isset($_POST['student_otp_verify'])
        || isset($_POST['student_otp_resend'])
        || isset($_POST['student_otp_cancel'])
        || (isset($_POST['student_otp']) && $_POST['student_otp'] !== '')
    )
) {
    $activeSection = 'student-login';
    require_once __DIR__ . '/includes/helpers.php';
    require_once __DIR__ . '/student/otp_helpers.php';

    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $studentLoginError = 'Your session expired. Please try again.';
    } elseif (isset($_POST['student_otp_cancel'])) {
        unset($_SESSION['student_login_otp_phone']);
        $studentOtpStep = false;
    } elseif (!($pdo instanceof PDO)) {
        $studentLoginError = 'Unable to connect to the server. Please try again later.';
    } elseif (isset($_POST['student_otp_verify']) || (isset($_POST['student_otp']) && $_POST['student_otp'] !== '')) {
        $posted = normalize_phone((string)($_POST['student_username'] ?? current_student_login_otp_phone()));
        $otp = (string)($_POST['student_otp'] ?? '');
        $newPassword = (string)($_POST['student_new_password'] ?? '');
        $newConfirm = (string)($_POST['student_new_password_confirm'] ?? '');
        $studentOtpStep = true;
        if ($newPassword !== '' && $newPassword !== $newConfirm) {
            $studentLoginError = 'New passwords do not match.';
        } elseif (!valid_lk_phone($posted)) {
            $studentLoginError = 'Enter a valid Sri Lankan mobile number.';
        } else {
            try {
                $result = verify_student_login_otp($pdo, $posted, $otp, $newPassword);
            } catch (Throwable $e) {
                error_log('Student OTP verify error: ' . $e->getMessage());
                $result = ['ok' => false, 'message' => 'Unable to verify the code. Please try again.'];
            }
            if (!empty($result['ok']) && !empty($result['user'])) {
                $signed = student_portal_sign_in($result['user'], $pdo, 'login_otp');
                if (($signed['status'] ?? '') === 'ok') {
                    header('Location: ' . BASE_URL . 'student/dashboard.php');
                    exit;
                }
                if (($signed['status'] ?? '') === 'otp') {
                    header('Location: ' . BASE_URL . 'student/login.php');
                    exit;
                }
                if (in_array(($signed['status'] ?? ''), ['device_limit', 'device_blocked'], true)) {
                    header('Location: ' . BASE_URL . 'student/device_gate.php');
                    exit;
                }
                $studentLoginError = (string)($signed['message'] ?? 'Could not sign in from this device.');
            } else {
                $studentLoginError = (string)($result['message'] ?? 'Could not verify the code.');
            }
        }
    } else {
        $posted = normalize_phone((string)($_POST['student_phone'] ?? $_POST['student_username'] ?? ''));
        if (!valid_lk_phone($posted)) {
            $studentLoginError = 'Enter a valid Sri Lankan mobile number, e.g. 0771234567.';
        } else {
            $result = send_student_login_otp($pdo, $posted);
            $studentOtpStep = !empty($result['show_otp']);
            if ($result['ok']) {
                $studentOtpNotice = $result['message'];
            } else {
                $studentLoginError = $result['message'];
            }
        }
    }
}

if (!empty($_SESSION['student_login_otp_phone']) && empty($studentOtpStep)) {
    $pendingPhone = current_student_login_otp_phone();
    $pendingUser = ($pdo instanceof PDO && $pendingPhone !== '')
        ? find_student_user_by_phone($pdo, $pendingPhone)
        : null;
    if ($pendingUser && student_is_first_login($pendingUser, $pdo instanceof PDO ? $pdo : null)) {
        $studentOtpStep = true;
    } else {
        unset($_SESSION['student_login_otp_phone']);
    }
}


// ============================================================
// HOMEPAGE DATABASE DATA
// ============================================================

$teacherProfiles = [];

$teacherCount = 0;

$studentCount = 0;

$classCount = 0;

$todayClassCount = 0;

$subjectCount = 0;


if ($pdo instanceof PDO) {

    try {

        // --------------------------------------------------------
        // TEACHER COUNT
        // --------------------------------------------------------

        $teacherCountStmt = $pdo->query("
            SELECT COUNT(*)
            FROM teachers
            WHERE deleted_at IS NULL
              AND LOWER(name) <> 'default teacher'
        ");

        $teacherCount =
            (int) $teacherCountStmt->fetchColumn();


        // --------------------------------------------------------
        // STUDENT COUNT
        // --------------------------------------------------------

        $studentCountStmt = $pdo->query("
            SELECT COUNT(DISTINCT id)
            FROM users WHERE role='student'
        ");

        $studentCount =
            (int) $studentCountStmt->fetchColumn();


        // --------------------------------------------------------
        // ACTIVE CLASS COUNT
        // --------------------------------------------------------

        $classCountStmt = $pdo->query("
            SELECT COUNT(*)
            FROM student_classes
            WHERE deleted_at IS NULL
        ");

        $classCount =
            (int) $classCountStmt->fetchColumn();


        // --------------------------------------------------------
        // TODAY'S CLASS COUNT
        // --------------------------------------------------------

        $todayClassCountStmt = $pdo->query("
            SELECT COUNT(*)
            FROM timetable
            WHERE deleted_at IS NULL
              AND date = CURDATE()
        ");

        $todayClassCount =
            (int) $todayClassCountStmt->fetchColumn();


        // --------------------------------------------------------
        // SUBJECT COUNT
        // --------------------------------------------------------

        $subjectCountStmt = $pdo->query("
            SELECT COUNT(*)
            FROM subjects
            WHERE deleted_at IS NULL
        ");

        $subjectCount =
            (int) $subjectCountStmt->fetchColumn();


        // --------------------------------------------------------
        // TEACHER DIRECTORY
        // --------------------------------------------------------

        $teacherStmt = $pdo->query("
            SELECT
                t.id,
                t.name,
                t.email,
                t.phone,
                t.photo,

                GROUP_CONCAT(
                    DISTINCT subject_pool.name
                    ORDER BY subject_pool.name
                    SEPARATOR ', '
                ) AS subjects

            FROM teachers t

            LEFT JOIN (

                /*
                 * Direct teacher-subject assignments
                 */
                SELECT
                    ts.teacher_id,
                    s.name

                FROM teacher_subjects ts

                INNER JOIN subjects s
                    ON s.id = ts.subject_id
                   AND s.deleted_at IS NULL


                UNION


                /*
                 * Subjects found in timetable
                 */
                SELECT
                    tt2.teacher_id,
                    s2.name

                FROM timetable tt2

                INNER JOIN subjects s2
                    ON s2.id = tt2.subject_id
                   AND s2.deleted_at IS NULL

                WHERE tt2.deleted_at IS NULL

            ) subject_pool

                ON subject_pool.teacher_id = t.id

            WHERE t.deleted_at IS NULL
              AND LOWER(t.name) <> 'default teacher'

            GROUP BY
                t.id,
                t.name,
                t.email,
                t.phone,
                t.photo

            ORDER BY t.name ASC
        ");

        $teacherProfiles =
            $teacherStmt->fetchAll(
                PDO::FETCH_ASSOC
            );

    } catch (PDOException $exception) {

        /*
         * Keep the public homepage usable if the
         * database temporarily fails.
         */
        $teacherProfiles = [];

        $teacherCount = 0;

        $studentCount = 0;

        $classCount = 0;

        $todayClassCount = 0;

        $subjectCount = 0;
    }
}
// ============================================================
// WEEKLY PUBLIC TIMETABLE
// ============================================================

$weeklyTimetable = [];

$weekStart = null;
$weekEnd = null;
$weekOffset = 0;

if ($pdo instanceof PDO) {

    try {

        /*
         * Optional week offset.
         *
         * 0  = current week
         * -1 = previous week
         * 1  = next week
         */
        $weekOffset =
            filter_input(
                INPUT_GET,
                'timetable_week',
                FILTER_VALIDATE_INT
            );

        if ($weekOffset === false || $weekOffset === null) {
            $weekOffset = 0;
        }

        /*
         * Prevent somebody from requesting
         * an unnecessarily large date range.
         */
        $weekOffset =
            max(
                -52,
                min(
                    52,
                    (int) $weekOffset
                )
            );


        /*
         * Find Monday of the requested week.
         *
         * MySQL DAYOFWEEK:
         * Sunday = 1
         * Monday = 2
         * ...
         * Saturday = 7
         */
        $weekStartStmt =
            $pdo->prepare("
                SELECT DATE_SUB(
                    CURDATE(),
                    INTERVAL (
                        DAYOFWEEK(CURDATE()) - 2
                    ) DAY
                )
            ");

        $weekStartStmt->execute();

        $weekStart =
            $weekStartStmt->fetchColumn();


        /*
         * Apply requested week offset.
         */
        if ($weekOffset !== 0) {

            $weekStart =
                date(
                    'Y-m-d',
                    strtotime(
                        ($weekOffset > 0 ? '+' : '') .
                        ($weekOffset * 7) .
                        ' days',
                        strtotime($weekStart)
                    )
                );
        }


        /*
         * Sunday = Monday + 6 days.
         */
        $weekEnd =
            date(
                'Y-m-d',
                strtotime(
                    '+6 days',
                    strtotime($weekStart)
                )
            );


        /*
         * Fetch timetable.
         *
         * The WhatsApp lookup first tries a
         * class + teacher + subject match.
         *
         * If that doesn't exist, it falls back
         * to class + teacher.
         */
        $weeklyStmt =
            $pdo->prepare("
                SELECT

                    tt.id,

                    tt.date,

                    tt.start_time,

                    tt.end_time,

                    tt.class_id,

                    tt.teacher_id,

                    tt.subject_id,

                    tt.room_id,

                    sc.name AS class_name,

                    s.name AS subject_name,

                    t.name AS teacher_name,

                    r.name AS room_name,

                    COALESCE(

                        (
                            SELECT cw.whatsapp_link

                            FROM class_teacher_whatsapp cw

                            WHERE cw.class_id =
                                tt.class_id

                              AND cw.teacher_id =
                                tt.teacher_id

                              AND cw.subject_id =
                                tt.subject_id

                              AND cw.whatsapp_link IS NOT NULL

                              AND cw.whatsapp_link <> ''

                            ORDER BY cw.id DESC

                            LIMIT 1
                        ),

                        (
                            SELECT cw2.whatsapp_link

                            FROM class_teacher_whatsapp cw2

                            WHERE cw2.class_id =
                                tt.class_id

                              AND cw2.teacher_id =
                                tt.teacher_id

                              AND (
                                  cw2.subject_id IS NULL
                                  OR cw2.subject_id = 0
                              )

                              AND cw2.whatsapp_link IS NOT NULL

                              AND cw2.whatsapp_link <> ''

                            ORDER BY cw2.id DESC

                            LIMIT 1
                        )

                    ) AS whatsapp_link


                FROM timetable tt


                INNER JOIN student_classes sc
                    ON sc.id =
                        tt.class_id

                   AND sc.deleted_at IS NULL


                INNER JOIN subjects s
                    ON s.id =
                        tt.subject_id

                   AND s.deleted_at IS NULL


                INNER JOIN teachers t
                    ON t.id =
                        tt.teacher_id

                   AND t.deleted_at IS NULL

                   AND LOWER(t.name) <>
                        'default teacher'


                INNER JOIN rooms r
                    ON r.id =
                        tt.room_id

                   AND r.deleted_at IS NULL


                WHERE tt.deleted_at IS NULL

                  AND tt.date BETWEEN
                        :week_start
                    AND :week_end


                ORDER BY

                    tt.date ASC,

                    tt.start_time ASC,

                    tt.end_time ASC,

                    sc.name ASC
            ");


        $weeklyStmt->execute(
            [
                ':week_start' =>
                    $weekStart,

                ':week_end' =>
                    $weekEnd
            ]
        );


        $weeklyRows =
            $weeklyStmt->fetchAll(
                PDO::FETCH_ASSOC
            );


        /*
         * Create Monday → Sunday structure.
         */
        $weeklyTimetable = [];


        for (
            $day = 0;
            $day < 7;
            $day++
        ) {

            $date =
                date(
                    'Y-m-d',
                    strtotime(
                        '+' . $day . ' days',
                        strtotime($weekStart)
                    )
                );


            $weeklyTimetable[$date] = [];
        }


        /*
         * Put timetable records into
         * their correct day.
         */
        foreach (
            $weeklyRows as $row
        ) {

            $date =
                $row['date'];


            if (
                isset(
                    $weeklyTimetable[$date]
                )
            ) {

                $weeklyTimetable[$date][] =
                    $row;
            }
        }


    } catch (PDOException $exception) {

        error_log(
            'Weekly timetable error: ' .
            $exception->getMessage()
        );


        $weeklyTimetable = [];

        $weekStart = null;

        $weekEnd = null;
    }
}

// Public filename of this page so login / OTP forms post back here.
$pageSelf = basename((string)($_SERVER['SCRIPT_NAME'] ?? 'index2.php'));
if ($pageSelf === '') {
    $pageSelf = 'index2.php';
}

?>
<!DOCTYPE html>
<html lang="en" <?= function_exists('app_theme_html_attrs') ? app_theme_html_attrs() : 'data-theme="dark" data-bs-theme="dark"' ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light dark">
    <meta name="csrf-token" content="<?= htmlspecialchars(function_exists('generate_csrf_token') ? generate_csrf_token() : '', ENT_QUOTES, 'UTF-8') ?>">
    <?php if (function_exists('app_theme_boot_script')) { app_theme_boot_script(); } ?>
    <meta name="theme-color" content="#12182a">
    <meta name="description" content="Edexcel College — Pearson Edexcel IGCSE and International A Level teaching, class timetable, teacher directory, and student portal.">
    <title>Edexcel College</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preconnect" href="https://cdnjs.cloudflare.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/home2.css?v=<?= filemtime(__DIR__ . '/assets/css/home2.css') ?>">
    <?php if (function_exists('app_theme_css_link')) { app_theme_css_link(); } ?>
</head>
<body>
<a class="skip-link" href="#main">Skip to content</a>

<!-- Site header: brand, desktop nav, theme, mobile menu -->
<header class="site-header" role="banner">
    <a class="brand" href="#home" data-target="home" aria-label="Edexcel College home">
        <span class="brand-mark" aria-hidden="true">EK</span>
        <span class="brand-copy">
            <strong>Edexcel College</strong>
            <span>Pearson Edexcel</span>
        </span>
    </a>

    <nav class="desktop-nav" aria-label="Primary">
        <a class="site-nav-link <?= $activeSection === 'home' ? 'is-active' : '' ?>" href="#home" data-target="home">Home</a>
        <a class="site-nav-link <?= $activeSection === 'teachers' ? 'is-active' : '' ?>" href="#teachers" data-target="teachers">Teachers</a>
        <a class="site-nav-link <?= $activeSection === 'classes' ? 'is-active' : '' ?>" href="#classes" data-target="classes">Classes</a>
        <a class="site-nav-link <?= $activeSection === 'teacher-login' ? 'is-active' : '' ?>" href="#teacher-login" data-target="teacher-login">Staff</a>
        <a class="site-nav-link <?= $activeSection === 'student-login' ? 'is-active' : '' ?>" href="#student-login" data-target="student-login">Students</a>
    </nav>

    <div class="header-actions">
        <?php if (function_exists('app_theme_render_picker')) { app_theme_render_picker('header'); } ?>
        <button type="button" class="menu-toggle" id="siteMenuToggle" aria-label="Open menu" aria-expanded="false" aria-controls="siteDrawer">
            <i class="fas fa-bars" aria-hidden="true"></i>
        </button>
    </div>
</header>

<div class="site-drawer-backdrop" id="siteDrawerBackdrop" hidden></div>
<aside class="site-drawer" id="siteDrawer" hidden>
    <nav class="drawer-nav" aria-label="Mobile">
        <a class="site-nav-link <?= $activeSection === 'home' ? 'is-active' : '' ?>" href="#home" data-target="home"><i class="fas fa-house"></i> Home</a>
        <a class="site-nav-link <?= $activeSection === 'teachers' ? 'is-active' : '' ?>" href="#teachers" data-target="teachers"><i class="fas fa-chalkboard-user"></i> Teachers</a>
        <a class="site-nav-link <?= $activeSection === 'classes' ? 'is-active' : '' ?>" href="#classes" data-target="classes"><i class="fas fa-book-open"></i> Classes</a>
        <a class="site-nav-link <?= $activeSection === 'teacher-login' ? 'is-active' : '' ?>" href="#teacher-login" data-target="teacher-login"><i class="fas fa-user-tie"></i> Teacher login</a>
        <a class="site-nav-link <?= $activeSection === 'student-login' ? 'is-active' : '' ?>" href="#student-login" data-target="student-login"><i class="fas fa-user-graduate"></i> Student login</a>
    </nav>
    <div class="drawer-cta">
        <button type="button" class="btn btn-accent" data-open-public-ai>
            <i class="fas fa-comments" aria-hidden="true"></i> Talk with AI
        </button>
        <a class="btn btn-primary" href="#student-login" data-target="student-login">Student portal</a>
    </div>
</aside>

<main class="page-shell" id="main">

    <!-- ========================================================
         HOME
    ========================================================= -->
    <section
        class="app-section <?= $activeSection === 'home' ? 'active-section' : '' ?>"
        id="home"
        aria-labelledby="homeTitle"
        <?= $activeSection !== 'home' ? 'hidden' : '' ?>
    >
        <article class="hero">
            <div class="hero-copy">
                <span class="eyebrow">Edexcel College — Kandy</span>
                <h1 id="homeTitle">Exam-focused teaching for <span>IGCSE</span> and International A Level</h1>
                <p>Award-winning teachers, a live weekly timetable, and a student portal built around Pearson Edexcel — so every lesson moves you closer to the mark scheme.</p>
                <div class="hero-actions">
                    <a class="btn btn-primary" href="#teachers" data-target="teachers">Meet our teachers</a>
                    <a class="btn btn-ghost" href="#classes" data-target="classes">View timetable</a>
                    <button type="button" class="btn btn-accent home-ai-cta" data-open-public-ai>
                        <i class="fas fa-comments" aria-hidden="true"></i>
                        Talk with AI
                    </button>
                </div>
            </div>

            <div class="metric-strip" aria-label="College overview">
                <article class="metric-card">
                    <span>Enrolled Students</span>
                    <strong><?= e($studentCount) ?></strong>
                </article>
                <article class="metric-card">
                    <span>Teachers</span>
                    <strong><?= e($teacherCount) ?></strong>
                </article>
                <article class="metric-card">
                    <span>Classes Today</span>
                    <strong><?= e($todayClassCount) ?></strong>
                </article>
            </div>
        </article>

        <div class="pathway-grid" aria-label="Quick links">
            <a class="pathway-card" href="#teachers" data-target="teachers">
                <i class="fas fa-chalkboard-user" aria-hidden="true"></i>
                <h2>Academic team</h2>
                <p>Browse teacher profiles and WhatsApp your subject specialist.</p>
            </a>
            <a class="pathway-card" href="#classes" data-target="classes">
                <i class="fas fa-calendar-week" aria-hidden="true"></i>
                <h2>Weekly timetable</h2>
                <p>See this week’s classes, rooms, and group links.</p>
            </a>
            <a class="pathway-card" href="#teacher-login" data-target="teacher-login">
                <i class="fas fa-user-tie" aria-hidden="true"></i>
                <h2>Staff portal</h2>
                <p>Teachers and administrators sign in here.</p>
            </a>
            <a class="pathway-card" href="#student-login" data-target="student-login">
                <i class="fas fa-user-graduate" aria-hidden="true"></i>
                <h2>Student portal</h2>
                <p>Timetable, recordings, fees, and Talk with AI.</p>
            </a>
        </div>

        <section class="why-choose-section" aria-labelledby="whyChooseTitle">
            <div class="section-header why-intro">
                <span class="eyebrow">Why Choose Us?</span>
                <h2 id="whyChooseTitle">Built around how Edexcel exams are marked</h2>
            </div>

            <div class="why-slider" aria-label="Why choose Edexcel College">
                <button type="button" class="why-slider-arrow why-slider-prev" aria-label="Previous reason">
                    <i class="fas fa-chevron-left"></i>
                </button>

                <div class="why-slider-viewport">
                    <div class="why-slider-track">
                        <article class="why-slide">
                            <div class="why-slide-icon">🏆</div>
                            <h3>Award-Winning Teachers</h3>
                            <p>Learn from experienced teachers with strong subject knowledge and proven teaching experience.</p>
                        </article>
                        <article class="why-slide">
                            <div class="why-slide-icon">📚</div>
                            <h3>Edexcel-Focused Teaching</h3>
                            <p>Lessons aligned with the Pearson Edexcel syllabus and examination requirements.</p>
                        </article>
                        <article class="why-slide">
                            <div class="why-slide-icon">🎯</div>
                            <h3>Exam-Focused Preparation</h3>
                            <p>Build understanding while learning how to score marks effectively in examinations.</p>
                        </article>
                        <article class="why-slide">
                            <div class="why-slide-icon">📝</div>
                            <h3>Past Paper Practice</h3>
                            <p>Regular practice with past papers and exam-style questions.</p>
                        </article>
                        <article class="why-slide">
                            <div class="why-slide-icon">👨‍🏫</div>
                            <h3>Individual Attention</h3>
                            <p>Students get opportunities to ask questions and receive personal guidance.</p>
                        </article>
                        <article class="why-slide">
                            <div class="why-slide-icon">📈</div>
                            <h3>Progress Monitoring</h3>
                            <p>Track strengths, weaknesses and improvement throughout the course.</p>
                        </article>
                    </div>
                </div>

                <button type="button" class="why-slider-arrow why-slider-next" aria-label="Next reason">
                    <i class="fas fa-chevron-right"></i>
                </button>
            </div>

            <div class="why-slider-dots" role="tablist" aria-label="Why choose us slides"></div>
        </section>

        <footer class="site-footer">
            <span>© <?= date('Y') ?> Edexcel College</span>
            <span>
                <a href="<?= BASE_URL ?>privacy.php">Privacy</a>
                ·
                <a href="<?= BASE_URL ?>terms.php">Terms</a>
            </span>
        </footer>
    </section>

    <!-- ========================================================
         TEACHERS
    ========================================================= -->
    <section
        class="app-section <?= $activeSection === 'teachers' ? 'active-section' : '' ?>"
        id="teachers"
        aria-labelledby="teachersTitle"
        <?= $activeSection !== 'teachers' ? 'hidden' : '' ?>
    >
        <div class="section-header teachers-intro">
            <div>
                <span class="eyebrow">Academic Team</span>
                <h1 id="teachersTitle">Teachers who know the papers</h1>
                <p class="lede">Open a profile for subjects, contact details, and class information.</p>
            </div>
            <div class="teacher-directory-count" aria-label="Teacher count">
                <i class="fas fa-users"></i>
                <strong><?= e($teacherCount) ?></strong>
                <span><?= $teacherCount === 1 ? 'Teacher' : 'Teachers' ?></span>
            </div>
        </div>

        <?php if (!empty($teacherProfiles)): ?>
            <div class="teacher-profile-grid" aria-label="Teacher profile cards">
                <?php foreach ($teacherProfiles as $teacher): ?>
                    <?php
                    $teacherId = (int) ($teacher['id'] ?? 0);
                    $teacherName = (string) ($teacher['name'] ?? 'Teacher');
                    $subjects = teacherSubjects($teacher['subjects'] ?? '');
                    $subjectLabel = !empty($subjects) ? implode(' • ', $subjects) : 'Edexcel Teacher';
                    $avatar = teacherPhotoPath($teacherId, $teacher['photo'] ?? null);
                    $whatsapp = whatsappUrl($teacher['phone'] ?? '');
                    ?>
                    <article
                        class="teacher-profile-card"
                        data-teacher-profile="<?= BASE_URL ?>teachers/teacher_profile.php?id=<?= $teacherId ?>"
                        tabindex="0"
                        role="link"
                        aria-label="View <?= e($teacherName) ?> profile"
                    >
                        <div class="teacher-avatar-ring" style="--avatar-accent: <?= e(teacherAvatarColor($teacherName)) ?>;">
                            <?php if ($avatar): ?>
                                <img
                                    src="<?= e($avatar) ?>"
                                    alt="<?= e($teacherName) ?>"
                                    class="teacher-avatar-image"
                                    loading="lazy"
                                    decoding="async"
                                    width="96"
                                    height="96"
                                >
                            <?php else: ?>
                                <span class="teacher-avatar-initials" aria-hidden="true">
                                    <?= e(teacherInitials($teacherName)) ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        <h2><?= e($teacherName) ?></h2>
                        <p class="teacher-subject"><?= e($subjectLabel) ?></p>
                        <div class="teacher-profile-actions">
                            <a
                                class="teacher-primary-action"
                                href="<?= BASE_URL ?>teachers/teacher_profile.php?id=<?= $teacherId ?>"
                                aria-label="View <?= e($teacherName) ?> profile"
                            >
                                <i class="fas fa-user"></i>
                                <span>Profile</span>
                            </a>
                            <?php if ($whatsapp): ?>
                                <a
                                    class="teacher-ghost-action"
                                    href="<?= e($whatsapp) ?>"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    aria-label="WhatsApp <?= e($teacherName) ?>"
                                >
                                    <i class="fab fa-whatsapp"></i>
                                    <span>WhatsApp</span>
                                </a>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="teacher-empty-state">
                <i class="fas fa-user-slash"></i>
                <h2>No teacher records available</h2>
                <p>Teacher information could not be loaded from the database.</p>
            </div>
        <?php endif; ?>
    </section>

    <!-- ========================================================
         CLASSES
    ========================================================= -->
    <section
        class="app-section <?= $activeSection === 'classes' ? 'active-section' : '' ?>"
        id="classes"
        aria-labelledby="classesTitle"
        <?= $activeSection !== 'classes' ? 'hidden' : '' ?>
    >
        <div class="section-card-grid">
            <article class="info-card">
                <i class="fas fa-chalkboard-teacher"></i>
                <h2>Active Classes</h2>
                <strong class="info-card-number"><?= e($classCount) ?></strong>
                <p>Classes currently available in the student class database.</p>
            </article>
            <article class="info-card">
                <i class="fas fa-calendar-day"></i>
                <h2>Classes Today</h2>
                <strong class="info-card-number"><?= e($todayClassCount) ?></strong>
                <p>Timetable entries scheduled for today.</p>
            </article>
            <article class="info-card">
                <i class="fas fa-book-open"></i>
                <h2>Subjects</h2>
                <strong class="info-card-number"><?= e($subjectCount) ?></strong>
                <p>Active subjects available in the subject database.</p>
            </article>
        </div>

        <div class="weekly-timetable-header">
            <div>
                <span class="eyebrow">Weekly Timetable</span>
                <h1 id="classesTitle">Class Schedule</h1>
                <?php if ($weekStart && $weekEnd): ?>
                    <p>
                        <?= e(date('d M', strtotime($weekStart))) ?>
                        –
                        <?= e(date('d M Y', strtotime($weekEnd))) ?>
                    </p>
                <?php endif; ?>
            </div>

            <div class="weekly-timetable-controls" aria-label="Timetable week navigation">
                <a
                    href="?timetable_week=<?= (int) ($weekOffset - 1) ?>#classes"
                    class="weekly-week-button"
                    aria-label="Previous week"
                    title="Previous week"
                >
                    <i class="fas fa-chevron-left"></i>
                </a>
                <a href="?timetable_week=0#classes" class="weekly-today-button">This Week</a>
                <a
                    href="?timetable_week=<?= (int) ($weekOffset + 1) ?>#classes"
                    class="weekly-week-button"
                    aria-label="Next week"
                    title="Next week"
                >
                    <i class="fas fa-chevron-right"></i>
                </a>
            </div>
        </div>

        <div class="weekly-timetable" aria-label="Weekly class timetable">
            <?php
            $dayNumber = 0;
            foreach ($weeklyTimetable as $date => $classes):
                $dayNumber++;
                $timestamp = strtotime($date);
                $dayName = date('l', $timestamp);
                $dayDate = date('d M', $timestamp);
                $isToday = $date === date('Y-m-d');
            ?>
                <section
                    class="timetable-day <?= $isToday ? 'is-today' : '' ?>"
                    aria-labelledby="timetable-day-<?= e($date) ?>"
                >
                    <div class="timetable-day-header">
                        <div>
                            <h2 id="timetable-day-<?= e($date) ?>">
                                <?= e($dayName) ?>
                                <span><?= e($dayDate) ?></span>
                                <?php if ($isToday): ?>
                                    <small>Today</small>
                                <?php endif; ?>
                            </h2>
                        </div>
                        <div class="timetable-day-count">
                            <?= count($classes) ?>
                            <?= count($classes) === 1 ? 'class' : 'classes' ?>
                        </div>
                    </div>

                    <?php if (!empty($classes)): ?>
                        <div class="timetable-class-list">
                            <?php foreach ($classes as $class): ?>
                                <?php
                                $startTimestamp = strtotime($class['start_time']);
                                $endTimestamp = strtotime($class['end_time']);
                                $startTime = date('g:i A', $startTimestamp);
                                $endTime = date('g:i A', $endTimestamp);
                                $accentIndex = ((int) $class['id']) % 5;
                                $whatsapp = trim((string) ($class['whatsapp_link'] ?? ''));
                                ?>
                                <article class="timetable-class-card timetable-accent-<?= $accentIndex ?>">
                                    <div class="timetable-class-time">
                                        <strong><?= e($startTime) ?></strong>
                                        <span>– <?= e($endTime) ?></span>
                                        <div class="timetable-time-line"></div>
                                    </div>
                                    <div class="timetable-class-main">
                                        <h3><?= e($class['class_name']) ?></h3>
                                        <p class="timetable-subject">
                                            <i class="fas fa-book-open"></i>
                                            <?= e($class['subject_name']) ?>
                                        </p>
                                        <p class="timetable-teacher-mobile">
                                            <i class="fas fa-user-tie"></i>
                                            <?= e($class['teacher_name']) ?>
                                        </p>
                                    </div>
                                    <div class="timetable-class-actions">
                                        <span class="timetable-teacher" title="Teacher">
                                            <i class="fab fa-whatsapp"></i>
                                            <?= e($class['teacher_name']) ?>
                                        </span>
                                        <span class="timetable-room">
                                            <i class="fas fa-door-open"></i>
                                            <?= e($class['room_name']) ?>
                                        </span>
                                        <?php if ($whatsapp !== ''): ?>
                                            <a
                                                href="<?= e($whatsapp) ?>"
                                                class="timetable-join"
                                                target="_blank"
                                                rel="noopener noreferrer"
                                            >
                                                <i class="fab fa-whatsapp"></i>
                                                <span>Join Group</span>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="timetable-empty-day">
                            <i class="fas fa-calendar-check"></i>
                            <span>No classes scheduled</span>
                        </div>
                    <?php endif; ?>
                </section>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- ========================================================
         TEACHER LOGIN
    ========================================================= -->
    <section
        class="app-section <?= $activeSection === 'teacher-login' ? 'active-section' : '' ?>"
        id="teacher-login"
        aria-labelledby="teacherLoginTitle"
        <?= $activeSection !== 'teacher-login' ? 'hidden' : '' ?>
    >
        <div class="login-layout">
            <div class="login-aside">
                <span class="eyebrow">Staff Access</span>
                <h2 id="teacherLoginTitle">Sign in to the staff portal</h2>
                <p class="lede">Teachers and administrators use the same entry point. After login you land on the campus dashboard.</p>
            </div>

            <div class="login-panel">
                <div class="login-icon-badge" aria-hidden="true">
                    <i class="fas fa-chalkboard-user"></i>
                </div>
                <h2>Teacher Portal</h2>
                <p>Enter your teacher username and password.</p>

                <?php if ($teacherLoginError): ?>
                    <div class="login-alert" role="alert">
                        <i class="fas fa-circle-exclamation"></i>
                        <span><?= e($teacherLoginError) ?></span>
                    </div>
                <?php endif; ?>

                <?php
                $staffOtpPhoneShown = '';
                if (function_exists('current_staff_login_otp_phone')) {
                    $staffOtpPhoneShown = current_staff_login_otp_phone();
                } else {
                    $staffOtpPhoneShown = trim((string)($_SESSION['staff_login_otp_phone'] ?? ''));
                }
                $staffVerifyingOtp = $teacherOtpStep && $staffOtpPhoneShown !== '';
                $teacherForgotMode = !$staffVerifyingOtp && (
                    isset($_POST['teacher_otp_send']) || isset($_POST['teacher_otp_resend'])
                );
                ?>

                <?php if (!empty($teacherOtpNotice)): ?>
                    <div class="alert alert-success" role="alert"><?= e($teacherOtpNotice) ?></div>
                <?php endif; ?>

                <?php if ($staffVerifyingOtp): ?>
                    <p>Enter the WhatsApp code sent to <strong><?= e($staffOtpPhoneShown) ?></strong></p>
                    <form method="POST" action="<?= e($pageSelf) ?>#teacher-login">
                        <?= csrf_field() ?>
                        <input type="hidden" name="teacher_username" value="<?= e($staffOtpPhoneShown) ?>">
                        <input type="hidden" name="teacher_otp_verify" value="1">
                        <label for="teacher_otp">WhatsApp login code</label>
                        <input class="otp-input js-otp-auto" type="text" id="teacher_otp" name="teacher_otp" inputmode="numeric" maxlength="6" autocomplete="one-time-code" placeholder="------" required autofocus>
                        <button type="submit" name="teacher_otp_verify" value="1">Verify OTP &amp; Sign In</button>
                    </form>
                    <form method="POST" action="<?= e($pageSelf) ?>#teacher-login">
                        <?= csrf_field() ?>
                        <input type="hidden" name="teacher_username" value="<?= e($staffOtpPhoneShown) ?>">
                        <button type="submit" name="teacher_otp_resend" value="1">Resend WhatsApp code</button>
                    </form>
                    <form method="POST" action="<?= e($pageSelf) ?>#teacher-login">
                        <?= csrf_field() ?>
                        <button type="submit" name="teacher_otp_cancel" value="1">Back to password login</button>
                    </form>
                <?php else: ?>
                    <form method="POST" action="<?= e($pageSelf) ?>#teacher-login" autocomplete="on" class="js-login-form" data-otp-mode="<?= $teacherForgotMode ? '1' : '0' ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="teacher_login" value="1" class="js-password-flag"<?= $teacherForgotMode ? ' disabled' : '' ?>>
                        <input type="hidden" name="teacher_otp_send" value="1" class="js-otp-flag"<?= $teacherForgotMode ? '' : ' disabled' ?>>

                        <label for="teacher_username">Username</label>
                        <input class="js-user-input" type="text" id="teacher_username" name="teacher_username" value="<?= e($_POST['teacher_username'] ?? '') ?>" autocomplete="username" placeholder="Enter your username" required>

                        <div class="js-pass-wrap">
                            <label for="teacher_password">Password</label>
                            <div class="field-wrap">
                                <input class="js-pass-input" type="password" id="teacher_password" name="teacher_password" autocomplete="current-password" placeholder="Enter your password"<?= $teacherForgotMode ? ' disabled' : ' required' ?>>
                                <button type="button" class="password-toggle" id="toggleTeacherPassword" aria-label="Show password">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>

                        <div class="js-phone-wrap"<?= $teacherForgotMode ? '' : ' hidden' ?>>
                            <label for="teacher_phone">WhatsApp number</label>
                            <input class="js-phone-input" type="text" id="teacher_phone" name="teacher_phone" value="<?= e($_POST['teacher_phone'] ?? $_POST['teacher_username'] ?? '') ?>" inputmode="tel" placeholder="077XXXXXXX"<?= $teacherForgotMode ? ' required' : ' disabled' ?>>
                        </div>

                        <button type="submit" class="js-login-submit" data-login-label="Login to Teacher Portal" data-otp-label="Send WhatsApp OTP">
                            <?= $teacherForgotMode ? 'Send WhatsApp OTP' : 'Login to Teacher Portal' ?>
                        </button>

                        <p class="login-links">
                            <a href="#teacher-login" class="js-forgot-link"<?= $teacherForgotMode ? ' hidden' : '' ?>>Forgot password? Use WhatsApp OTP</a>
                            <a href="#teacher-login" class="js-back-password"<?= $teacherForgotMode ? '' : ' hidden' ?>>Back to password login</a>
                        </p>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- ========================================================
         STUDENT LOGIN
    ========================================================= -->
    <section
        class="app-section <?= $activeSection === 'student-login' ? 'active-section' : '' ?>"
        id="student-login"
        aria-labelledby="studentLoginTitle"
        <?= $activeSection !== 'student-login' ? 'hidden' : '' ?>
    >
        <div class="login-layout">
            <div class="login-aside">
                <span class="eyebrow">Student Access</span>
                <h2 id="studentLoginTitle">Your classes, recordings, and results</h2>
                <p class="lede">Sign in with your WhatsApp number. First-time students receive a verification code.</p>
            </div>

            <div class="login-panel">
                <div class="login-icon-badge" aria-hidden="true">
                    <i class="fas fa-user-graduate"></i>
                </div>
                <h2>Student Portal</h2>
                <p
                    class="js-student-login-intro"
                    data-password-intro="Enter your WhatsApp number and password."
                    data-otp-intro="Enter your WhatsApp number to receive a login code."
                    data-first-intro="First sign-in: we will send a verification code to this WhatsApp number."
                >
                    Enter your WhatsApp number and password.
                </p>

                <?php if ($studentLoginError): ?>
                    <div class="login-alert" role="alert">
                        <i class="fas fa-circle-exclamation"></i>
                        <span><?= e($studentLoginError) ?></span>
                    </div>
                <?php endif; ?>

                <?php
                $otpVia = student_otp_channel_name($pdo instanceof PDO ? $pdo : null);
                $otpPhoneShown = '';
                if (function_exists('current_student_login_otp_phone')) {
                    $otpPhoneShown = current_student_login_otp_phone();
                } else {
                    $otpPhoneShown = trim((string)($_SESSION['student_login_otp_phone'] ?? ''));
                }
                $studentVerifyingOtp = $studentOtpStep && $otpPhoneShown !== '';
                $studentForgotMode = !$studentVerifyingOtp && (
                    isset($_POST['student_otp_send']) || isset($_POST['student_otp_resend'])
                );
                ?>

                <?php if (!empty($studentOtpNotice)): ?>
                    <div class="alert alert-success" role="alert"><?= e($studentOtpNotice) ?></div>
                <?php endif; ?>

                <?php if ($studentVerifyingOtp): ?>
                    <p>Enter the <?= e($otpVia) ?> code sent to <strong><?= e($otpPhoneShown) ?></strong></p>
                    <form method="POST" action="<?= e($pageSelf) ?>#student-login">
                        <?= csrf_field() ?>
                        <input type="hidden" name="student_username" value="<?= e($otpPhoneShown) ?>">
                        <input type="hidden" name="student_otp_verify" value="1">
                        <label for="student_otp"><?= e($otpVia) ?> login code</label>
                        <input class="otp-input js-otp-auto" type="text" id="student_otp" name="student_otp" inputmode="numeric" maxlength="6" autocomplete="one-time-code" placeholder="------" required autofocus>
                        <button type="submit" name="student_otp_verify" value="1">Verify OTP &amp; Sign In</button>
                    </form>
                    <form method="POST" action="<?= e($pageSelf) ?>#student-login">
                        <?= csrf_field() ?>
                        <input type="hidden" name="student_username" value="<?= e($otpPhoneShown) ?>">
                        <button type="submit" name="student_otp_resend" value="1">Resend <?= e($otpVia) ?> code</button>
                    </form>
                    <form method="POST" action="<?= e($pageSelf) ?>#student-login">
                        <?= csrf_field() ?>
                        <button type="submit" name="student_otp_cancel" value="1">Back to password login</button>
                    </form>
                <?php else: ?>
                    <form
                        method="POST"
                        action="<?= e($pageSelf) ?>#student-login"
                        autocomplete="on"
                        class="js-login-form"
                        data-otp-mode="<?= $studentForgotMode ? '1' : '0' ?>"
                        data-student-login="1"
                        data-status-url="<?= e(BASE_URL) ?>ajax/student_login_status.php"
                    >
                        <?= csrf_field() ?>
                        <input type="hidden" name="student_login" value="1" class="js-password-flag" <?= $studentForgotMode ? ' disabled' : '' ?>>
                        <input type="hidden" name="student_otp_send" value="1" class="js-otp-flag"<?= $studentForgotMode ? '' : ' disabled' ?>>

                        <div class="js-user-wrap">
                            <label for="student_username">Mobile number</label>
                            <div class="field-wrap">
                                <i class="fas fa-user" aria-hidden="true"></i>
                                <input
                                    type="text"
                                    id="student_username"
                                    name="student_username"
                                    class="js-user-input"
                                    value="<?= e($_POST['student_username'] ?? '') ?>"
                                    autocomplete="username"
                                    placeholder="077XXXXXXX"
                                    inputmode="tel"
                                    required
                                    autofocus
                                >
                            </div>
                        </div>

                        <div class="js-pass-wrap">
                            <label for="student_password">Password</label>
                            <div class="field-wrap">
                                <i class="fas fa-lock" aria-hidden="true"></i>
                                <input
                                    type="password"
                                    id="student_password"
                                    name="student_password"
                                    class="js-pass-input"
                                    autocomplete="current-password"
                                    placeholder="Enter your password"
                                    <?= $studentForgotMode ? 'disabled' : 'required' ?>
                                >
                                <button type="button" class="password-toggle" id="toggleStudentPassword" aria-label="Show password">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>

                        <div class="js-phone-wrap"<?= $studentForgotMode ? '' : ' hidden' ?>>
                            <label for="student_phone">Mobile number</label>
                            <input class="js-phone-input" type="text" id="student_phone" name="student_phone" value="<?= e($_POST['student_phone'] ?? $_POST['student_username'] ?? '') ?>" inputmode="tel" placeholder="077XXXXXXX"<?= $studentForgotMode ? ' required' : ' disabled' ?>>
                        </div>

                        <button
                            type="submit"
                            class="js-login-submit"
                            data-login-label="Login to Student Portal"
                            data-otp-label="Send <?= e($otpVia) ?> OTP"
                        >
                            <?= $studentForgotMode ? 'Send ' . e($otpVia) . ' OTP' : 'Login to Student Portal' ?>
                        </button>

                        <p class="login-links">
                            <a href="#student-login" class="js-forgot-link"<?= $studentForgotMode ? ' hidden' : '' ?>>Forgot password? Use <?= e($otpVia) ?> OTP</a>
                            <a href="#student-login" class="js-back-password"<?= $studentForgotMode ? '' : ' hidden' ?>>Back to password login</a>
                        </p>
                    </form>
                <?php endif; ?>

                <a href="<?= BASE_URL ?>student/register.php" class="student-register-link js-student-register">
                    <i class="fas fa-user-plus"></i>
                    <span>Create Student Account</span>
                </a>
                <p class="login-links">
                    <a href="<?= BASE_URL ?>parent/login.php">Parent sign-in (WhatsApp)</a>
                </p>
            </div>
        </div>
    </section>

</main>

<!-- Bottom dock — required by home.js section routing -->
<nav class="bottom-navbar" aria-label="Primary bottom navigation">
    <div class="bottom-navbar-menu" id="bottomNavbarMenu">
        <span class="nav-selector" aria-hidden="true"></span>
        <ul class="bottom-nav-list">
            <li class="bottom-nav-item <?= $activeSection === 'home' ? 'active' : '' ?>">
                <a class="bottom-nav-link" href="#home" data-target="home" aria-label="Home" aria-current="<?= $activeSection === 'home' ? 'page' : 'false' ?>">
                    <i class="fas fa-house"></i>
                    <span>Home</span>
                </a>
            </li>
            <li class="bottom-nav-item <?= $activeSection === 'teachers' ? 'active' : '' ?>">
                <a class="bottom-nav-link" href="#teachers" data-target="teachers" aria-label="Teachers">
                    <i class="fas fa-chalkboard-user"></i>
                    <span>Teachers</span>
                </a>
            </li>
            <li class="bottom-nav-item <?= $activeSection === 'classes' ? 'active' : '' ?>">
                <a class="bottom-nav-link" href="#classes" data-target="classes" aria-label="Classes">
                    <i class="fas fa-book-open"></i>
                    <span>Classes</span>
                </a>
            </li>
            <li class="bottom-nav-item <?= $activeSection === 'teacher-login' ? 'active' : '' ?>">
                <a class="bottom-nav-link" href="#teacher-login" data-target="teacher-login" aria-label="Teachers login">
                    <i class="fas fa-user-tie"></i>
                    <span>Staff</span>
                </a>
            </li>
            <li class="bottom-nav-item <?= $activeSection === 'student-login' ? 'active' : '' ?>">
                <a class="bottom-nav-link" href="#student-login" data-target="student-login" aria-label="Student login">
                    <i class="fas fa-user-graduate"></i>
                    <span>Students</span>
                </a>
            </li>
        </ul>
    </div>
</nav>

<?php if (function_exists('app_theme_js_link')) { app_theme_js_link(); } ?>
<script src="<?= BASE_URL ?>assets/js/home.js?v=<?= filemtime(__DIR__ . '/assets/js/home.js') ?>" defer></script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const activeSection = <?= json_encode($activeSection) ?>;

    if (activeSection === 'teacher-login' || activeSection === 'student-login') {
        setTimeout(function () {
            const expectedHash = '#' + activeSection;
            if (window.location.hash !== expectedHash) {
                history.replaceState(null, '', expectedHash);
                window.dispatchEvent(new HashChangeEvent('hashchange'));
            }
        }, 100);
    }

    function bindPasswordToggle(inputId, buttonId) {
        const input = document.getElementById(inputId);
        const button = document.getElementById(buttonId);
        if (!input || !button) return;
        button.addEventListener('click', function () {
            const icon = button.querySelector('i');
            if (input.type === 'password') {
                input.type = 'text';
                if (icon) { icon.classList.remove('fa-eye'); icon.classList.add('fa-eye-slash'); }
                button.setAttribute('aria-label', 'Hide password');
            } else {
                input.type = 'password';
                if (icon) { icon.classList.remove('fa-eye-slash'); icon.classList.add('fa-eye'); }
                button.setAttribute('aria-label', 'Show password');
            }
        });
    }
    bindPasswordToggle('teacher_password', 'toggleTeacherPassword');
    bindPasswordToggle('student_password', 'toggleStudentPassword');

    function guardSubmit(form) {
        if (!form) return;
        form.addEventListener('submit', function () {
            const button = form.querySelector('button[type="submit"]');
            if (!button) return;
            button.disabled = true;
            const otpMode = form.getAttribute('data-otp-mode') === '1';
            button.innerHTML = otpMode
                ? 'Sending code...'
                : '<i class="fas fa-spinner fa-spin"></i>&nbsp; Signing in...';
        });
    }
    guardSubmit(document.querySelector('#teacher-login form.js-login-form'));
    guardSubmit(document.querySelector('#student-login form.js-login-form'));

    /* Chrome nav + mobile drawer (home.js still owns section routing) */
    const body = document.body;
    const toggle = document.getElementById('siteMenuToggle');
    const drawer = document.getElementById('siteDrawer');
    const backdrop = document.getElementById('siteDrawerBackdrop');

    function setDrawer(open) {
        body.classList.toggle('drawer-open', open);
        if (toggle) {
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            toggle.setAttribute('aria-label', open ? 'Close menu' : 'Open menu');
            const icon = toggle.querySelector('i');
            if (icon) {
                icon.classList.toggle('fa-bars', !open);
                icon.classList.toggle('fa-xmark', open);
            }
        }
        if (drawer) {
            if (open) drawer.removeAttribute('hidden');
            else drawer.setAttribute('hidden', '');
        }
        if (backdrop) {
            if (open) backdrop.removeAttribute('hidden');
            else backdrop.setAttribute('hidden', '');
        }
        body.style.overflow = open ? 'hidden' : '';
    }

    if (toggle) {
        toggle.addEventListener('click', function () {
            setDrawer(!body.classList.contains('drawer-open'));
        });
    }
    if (backdrop) {
        backdrop.addEventListener('click', function () { setDrawer(false); });
    }
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') setDrawer(false);
    });

    function syncChromeNav() {
        const id = (window.location.hash || '#home').replace('#', '') || 'home';
        document.querySelectorAll('.site-nav-link').forEach(function (link) {
            const on = link.getAttribute('data-target') === id;
            link.classList.toggle('is-active', on);
            if (on) link.setAttribute('aria-current', 'page');
            else link.removeAttribute('aria-current');
        });
    }

    document.querySelectorAll('[data-target]').forEach(function (link) {
        if (link.closest('#bottomNavbarMenu')) return;
        link.addEventListener('click', function () {
            setDrawer(false);
            setTimeout(syncChromeNav, 0);
        });
    });
    window.addEventListener('hashchange', syncChromeNav);
    window.addEventListener('popstate', syncChromeNav);
    document.querySelectorAll('.bottom-nav-link').forEach(function (link) {
        link.addEventListener('click', function () { setTimeout(syncChromeNav, 0); });
    });
    syncChromeNav();
});
</script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.teacher-profile-card[data-teacher-profile]').forEach(function (card) {
        function openProfile() {
            window.location.href = card.dataset.teacherProfile;
        }
        card.addEventListener('click', function (event) {
            if (event.target.closest('a, button')) return;
            openProfile();
        });
        card.addEventListener('keydown', function (event) {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                openProfile();
            }
        });
    });
});
</script>

<script src="<?= BASE_URL ?>assets/js/login-otp.js?v=<?= filemtime(__DIR__ . '/assets/js/login-otp.js') ?>"></script>
<?php include __DIR__ . '/includes/public_ai_widget.php'; ?>
</body>
</html>
