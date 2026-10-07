<?php
declare(strict_types=1);

/**
 * student/dashboard.php
 *
 * Clean Student Portal Controller.
 * Delegates view presentation to student/views/layout.php and student/views/tabs/*.
 */

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../includes/i18n.php';
require_once __DIR__ . '/otp_helpers.php';
require_once __DIR__ . '/device_helpers.php';

use Edexcel\Repositories\StudentRepository;
use Edexcel\Services\StudentService;

require_student();

$studentId = (int)($_SESSION['user_id'] ?? $_SESSION['student_id'] ?? 0);
if ($studentId <= 0) {
    http_response_code(403);
    exit('Student account is not correctly linked.');
}

// ------------------------------------------------------------
// URL STATE & NAVIGATION
// ------------------------------------------------------------
$allowedTabs = ['overview', 'timetable', 'classes', 'recordings', 'join', 'services', 'exams', 'fees', 'attendance', 'settings', 'documents', 'teachers', 'courso'];
$tabAliases = [
    'join' => 'join',
    'fees' => 'fees',
    'attendance' => 'attendance',
    'timetable' => 'timetable',
    'classes' => 'classes',
];
$tab = strtolower(trim((string)($_GET['tab'] ?? 'overview')));
if (isset($tabAliases[$tab])) {
    $tab = $tabAliases[$tab];
}
if (!in_array($tab, $allowedTabs, true)) {
    $tab = 'overview';
}

$weekOffset = filter_input(INPUT_GET, 'week', FILTER_VALIDATE_INT);
$weekOffset = ($weekOffset === false || $weekOffset === null) ? 0 : (int)$weekOffset;
$weekOffset = max(-52, min(52, $weekOffset));

$joinSearch = trim((string)($_GET['q'] ?? ''));
$weekStart = date('Y-m-d', strtotime(($weekOffset >= 0 ? '+' : '') . ($weekOffset * 7) . ' days', strtotime('monday this week')));
$weekEnd = date('Y-m-d', strtotime('+6 days', strtotime($weekStart)));
$today = date('Y-m-d');

$successMessage = student_flash('student_success');
$errorMessage = student_flash('student_error');

// ------------------------------------------------------------
// INITIALIZE REPOSITORIES & SERVICES
// ------------------------------------------------------------
$studentRepo = new StudentRepository($pdo);
$studentService = new StudentService($studentRepo, $pdo);

$student = $studentRepo->findById($studentId);
if (!$student) {
    http_response_code(403);
    exit('Student account not found.');
}
if (isset($student['is_active']) && (int)$student['is_active'] !== 1) {
    destroy_app_session();
    header('Location: ' . student_login_url());
    exit;
}

$studentProfile = [
    'full_name' => '',
    'email' => '',
    'parent_name' => '',
    'parent_whatsapp' => '',
    'website' => '',
    'facebook' => '',
    'instagram' => '',
    'tiktok' => '',
    'youtube' => '',
    'linkedin' => '',
];
$profileRow = [];
try {
    $p = $pdo->prepare('SELECT * FROM student_profiles WHERE user_id = ? LIMIT 1');
    $p->execute([$studentId]);
    $profileRow = $p->fetch(PDO::FETCH_ASSOC) ?: [];
    foreach ($studentProfile as $key => $_) {
        if (array_key_exists($key, $profileRow) && $profileRow[$key] !== null) {
            $studentProfile[$key] = (string)$profileRow[$key];
        }
    }
} catch (Throwable $e) {
}

$studentDisplayName = trim((string)($studentProfile['full_name'] ?? ''));
if ($studentDisplayName !== '') {
    if ((string)($_SESSION['student_full_name'] ?? '') !== $studentDisplayName) {
        $_SESSION['student_full_name'] = $studentDisplayName;
    }
} elseif (isset($_SESSION['student_full_name'])) {
    unset($_SESSION['student_full_name']);
}

$pendingPhoneChange = '';
$phoneChangeWait = 0;
$otpChannelName = 'WhatsApp';
$pendingParentWa = '';
if ($tab === 'settings') {
    require_once __DIR__ . '/otp_helpers.php';
    $otpChannelName = student_otp_channel_name($pdo);
    $pendingPhoneChange = student_normalize_lk_phone((string)($_SESSION['student_phone_change_pending'] ?? ''));
    if ($pendingPhoneChange !== '') {
        $phoneChangeWait = student_phone_change_resend_wait_seconds($pdo, $studentId, $pendingPhoneChange);
    }
    $pending = $_SESSION['parent_wa_pending'] ?? null;
    if (is_array($pending) && !empty($pending['phone']) && (int)($pending['expires'] ?? 0) > time()) {
        $pendingParentWa = (string)$pending['phone'];
    } elseif (is_array($pending)) {
        unset($_SESSION['parent_wa_pending']);
    }
}

// ------------------------------------------------------------
// WHATSAPP CONTACT & AVATAR (CACHED - NON-BLOCKING)
// ------------------------------------------------------------
if (isset($_GET['dismiss_phone_modal'])) {
    $_SESSION['dismiss_phone_modal'] = true;
}

$rawUsername = trim((string)($student['username'] ?? ''));
$studentPhone = '';
if (preg_match('/^(?:\+?94|0)?7\d{8}$/', $rawUsername)) {
    $studentPhone = normalize_phone($rawUsername);
}

try {
    $profilePhone = normalize_phone((string)($profileRow['whatsapp_number'] ?? ''));
    if ($profilePhone !== '' && valid_lk_phone($profilePhone)) {
        $studentPhone = $profilePhone;
    }
} catch (Throwable $e) {
}

$studentDisplayPhone = '';
if ($studentPhone !== '' && valid_lk_phone($studentPhone) && function_exists('campus_display_phone')) {
    $studentDisplayPhone = campus_display_phone($studentPhone);
} elseif ($studentPhone !== '') {
    $studentDisplayPhone = $studentPhone;
} else {
    $studentDisplayPhone = trim((string)($studentProfile['email'] ?? '')) ?: $rawUsername;
}

$hasParentPhone = function_exists('student_has_parent_phone') ? student_has_parent_phone($pdo, $studentId) : true;

$requireMobileNumberModal = false;
if ($hasParentPhone && $tab !== 'settings' && empty($_SESSION['dismiss_phone_modal'])) {
    if ($studentPhone === '' || !valid_lk_phone($studentPhone)) {
        $requireMobileNumberModal = true;
    }
}
$studentAvatarInitials = 'S';
if ($studentDisplayName !== '') {
    $nameParts = preg_split('/\s+/', $studentDisplayName) ?: [];
    if (count($nameParts) >= 2) {
        $studentAvatarInitials = strtoupper(substr($nameParts[0], 0, 1) . substr($nameParts[1], 0, 1));
    } else {
        $studentAvatarInitials = strtoupper(substr($studentDisplayName, 0, 1));
    }
}

$whatsappVerified = false;
$phoneNumberVerified = false;
$whatsappProfilePhoto = '';
$contact = null;

if ($studentPhone !== '') {
    $stmt = $pdo->prepare("SELECT verified_at, profile_picture_url FROM whatsapp_bot_contacts WHERE phone = ? LIMIT 1");
    $stmt->execute([$studentPhone]);
    $contact = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($contact) {
        $whatsappProfilePhoto = trim((string)($contact['profile_picture_url'] ?? ''));
    }
}
$studentProfilePhoto = trim((string)($student['profile_image'] ?? '')) ?: $whatsappProfilePhoto;
$verifyStatus = function_exists('student_verify_status')
    ? student_verify_status($pdo, $studentId, $studentPhone)
    : ['via' => '', 'whatsapp' => !empty($contact['verified_at'] ?? null), 'phone' => !empty($contact['verified_at'] ?? null)];
$whatsappVerified = !empty($verifyStatus['whatsapp']);
$phoneNumberVerified = !empty($verifyStatus['phone']);

// ------------------------------------------------------------
// ENROLLED CLASSES & TIMETABLE DATA
// ------------------------------------------------------------
$enrolledClasses = $studentService->getEnrolledClasses($studentId);
$enrolledIds = array_column($enrolledClasses, 'id');

// Fetch Timetable for the Current Week
$entries = [];
$todayEntries = [];
$days = [];

for ($i = 0; $i < 7; $i++) {
    $d = date('Y-m-d', strtotime("+$i days", strtotime($weekStart)));
    $days[$d] = [];
}

if (!empty($enrolledIds)) {
    $placeholders = implode(',', array_fill(0, count($enrolledIds), '?'));
    $sql = "
        SELECT tt.*, c.name AS class_name, s.name AS subject_name, t.name AS teacher_name, r.name AS room_name, c.whatsapp_link
        FROM timetable tt
        JOIN student_classes c ON tt.class_id = c.id AND c.deleted_at IS NULL
        JOIN subjects s ON tt.subject_id = s.id AND s.deleted_at IS NULL
        JOIN teachers t ON tt.teacher_id = t.id AND t.deleted_at IS NULL
        LEFT JOIN rooms r ON tt.room_id = r.id
        WHERE tt.deleted_at IS NULL
          AND tt.date BETWEEN ? AND ?
          AND tt.class_id IN ($placeholders)
        ORDER BY tt.date, tt.start_time
    ";
    $params = array_merge([$weekStart, $weekEnd], $enrolledIds);
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $entries = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $enrolledTeacherByClass = [];
    if (function_exists('campus_column_exists') && campus_column_exists($pdo, 'student_enrollments', 'teacher_id')) {
        try {
            $map = $pdo->prepare("SELECT class_id, teacher_id FROM student_enrollments WHERE student_id = ?");
            $map->execute([$studentId]);
            foreach ($map->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                $tid = (int)($row['teacher_id'] ?? 0);
                if ($tid > 0) {
                    $enrolledTeacherByClass[(int)$row['class_id']] = $tid;
                }
            }
        } catch (Throwable $e) {
            $enrolledTeacherByClass = [];
        }
    }

    foreach ($entries as $entry) {
        $wantTeacher = $enrolledTeacherByClass[(int)($entry['class_id'] ?? 0)] ?? 0;
        if ($wantTeacher > 0 && (int)($entry['teacher_id'] ?? 0) !== $wantTeacher) {
            continue;
        }
        $entry['_kind'] = 'lesson';
        if (isset($days[$entry['date']])) {
            $days[$entry['date']][] = $entry;
        }
        if ($entry['date'] === $today) {
            $todayEntries[] = $entry;
        }
    }
}

$upcomingExams = campus_student_exams($pdo, $enrolledIds, $today, null, 40);
$weekExams = campus_student_exams($pdo, $enrolledIds, $weekStart, $weekEnd, 80);
$todayExams = [];
foreach ($weekExams as $ex) {
    $ex['_kind'] = 'exam';
    $ex['start_time'] = !empty($ex['exam_time']) ? $ex['exam_time'] : '23:59:00';
    if (empty($ex['room_name']) && !empty($ex['location'])) {
        $ex['room_name'] = $ex['location'];
    }
    if (empty($ex['subject_name'])) {
        $ex['subject_name'] = $ex['title'];
    }
    $date = (string)$ex['exam_date'];
    if (isset($days[$date])) {
        $days[$date][] = $ex;
    }
    if ($date === $today) {
        $todayExams[] = $ex;
    }
}
foreach ($days as &$dayEntries) {
    usort($dayEntries, static function ($a, $b) {
        return strcmp((string)($a['start_time'] ?? ''), (string)($b['start_time'] ?? ''));
    });
}
unset($dayEntries);

// Next upcoming class (not limited to the displayed week)
$nextClass = null;
if (!empty($enrolledIds)) {
    $placeholders = implode(',', array_fill(0, count($enrolledIds), '?'));
    $stmt = $pdo->prepare("
        SELECT tt.*, c.name AS class_name, s.name AS subject_name, t.name AS teacher_name, r.name AS room_name,
               om.public_id, om.status AS meeting_status
        FROM timetable tt
        JOIN student_classes c ON tt.class_id = c.id AND c.deleted_at IS NULL
        JOIN subjects s ON tt.subject_id = s.id AND s.deleted_at IS NULL
        JOIN teachers t ON tt.teacher_id = t.id AND t.deleted_at IS NULL
        LEFT JOIN rooms r ON tt.room_id = r.id
        LEFT JOIN online_meetings om ON om.timetable_id = tt.id
        WHERE tt.deleted_at IS NULL
          AND tt.class_id IN ($placeholders)
          AND (tt.date > CURDATE() OR (tt.date = CURDATE() AND tt.start_time >= CURTIME()))
        ORDER BY tt.date ASC, tt.start_time ASC
        LIMIT 1
    ");
    $stmt->execute($enrolledIds);
    $nextClass = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

$lessonRecordings = [];
$lessonFees = [];
try {
    $weekLessonIds = [];
    $weekLessons = [];
    foreach ($days as $dayEntries) {
        foreach ($dayEntries as $lessonRow) {
            if (($lessonRow['_kind'] ?? 'lesson') === 'lesson' && !empty($lessonRow['id'])) {
                $weekLessonIds[] = (int)$lessonRow['id'];
                $weekLessons[] = $lessonRow;
            }
        }
    }
    if ($nextClass && !empty($nextClass['id'])) {
        $weekLessonIds[] = (int)$nextClass['id'];
        $weekLessons[] = $nextClass;
    }
    if ($weekLessonIds !== []) {
        $lessonRecordings = (new \Edexcel\Services\RecordingService($pdo))->mapForLessons($weekLessonIds);
        $lessonFees = (new \Edexcel\Services\StudentLessonFeeService($pdo))->mapForStudentLessons($studentId, $weekLessons);
    }
} catch (Throwable $e) {
    $lessonRecordings = [];
    $lessonFees = [];
}

$teacherDirectory = [];
$teacherProfile = null;
$teacherClasses = [];
$teacherUpcoming = [];
$selectedTeacherId = (int)($_GET['id'] ?? $_GET['teacher'] ?? 0);
try {
    if (in_array($tab, ['teachers', 'overview'], true)) {
        $teacherDirectory = campus_student_teachers_directory($pdo);
    }
    if ($tab === 'teachers' && $selectedTeacherId > 0) {
        $pack = campus_student_teacher_profile($pdo, $selectedTeacherId, $studentId);
        $teacherProfile = $pack['teacher'];
        $teacherClasses = $pack['classes'];
        $teacherUpcoming = $pack['upcoming'];
        if ($teacherProfile === null) {
            $selectedTeacherId = 0;
        }
    }
} catch (Throwable $e) {
    error_log('Student teachers directory: ' . $e->getMessage());
}

$availableClasses = [];
if ($tab === 'join') {
    try {
        $availableClasses = $studentRepo->getAvailableClassesForStudent($studentId, '');
        foreach ($availableClasses as &$classRow) {
            $classRow['teacher_photo_path'] = teacherPhotoPath(
                (int)($classRow['teacher_id'] ?? 0),
                $classRow['teacher_photo'] ?? null
            );
        }
        unset($classRow);
    } catch (Throwable $e) {
        error_log('Student join catalog: ' . $e->getMessage());
        $availableClasses = [];
    }
}

// Academic Services Hub Data
$academicServices = [];
if ($tab === 'services') {
    $academicServices = $studentService->getAcademicServices($studentId);
}

$studentNotifications = [];
$studentUnreadNotifications = 0;
$homeworkDueSoon = [];
try {
    ensure_ops_schema($pdo);
    $studentNotifications = campus_student_unread_notifications($pdo, $studentId, 12);
    $studentUnreadNotifications = campus_student_unread_notification_count($pdo, $studentId);
} catch (Throwable $e) {
    $studentNotifications = [];
    $studentUnreadNotifications = 0;
}

try {
    if (in_array($tab, ['overview', 'services'], true)) {
        $hwSvc = new \Edexcel\Services\HomeworkSubmissionService($pdo);
        $homeworkDueSoon = $hwSvc->dueSoonForStudent($studentId, $enrolledIds, 14);
        if ($tab === 'services') {
            $academicServices['homework'] = $hwSvc->forStudent($studentId, $enrolledIds, 30);
        }
    }
} catch (Throwable $e) {
    $homeworkDueSoon = [];
}

$feeWallet = ['due' => 0, 'paid' => 0, 'billed' => 0, 'next' => null, 'rows' => [], 'breakdown' => []];
$feeStatement = ['due' => 0, 'paid' => 0, 'lessons' => [], 'wallet' => $feeWallet, 'receipts' => []];
$attendanceMonth = [];
$attendanceSummary = ['present' => 0, 'absent' => 0, 'late' => 0, 'percent' => null];
try {
    if (in_array($tab, ['fees', 'overview'], true)) {
        $feeWallet = campus_fee_summary($pdo, $studentId);
        $feeStatement = (new \Edexcel\Services\FeeStatementService($pdo))->forStudent($studentId);
        $feeWallet = $feeStatement['wallet'] ?? $feeWallet;
    }
    if (in_array($tab, ['attendance', 'overview'], true)) {
        $attStmt = $pdo->prepare("
            SELECT sa.status, tt.date, tt.start_time, s.name AS subject_name, c.name AS class_name
            FROM student_attendance sa
            JOIN timetable tt ON tt.id = sa.timetable_id AND tt.deleted_at IS NULL
            JOIN subjects s ON s.id = tt.subject_id
            JOIN student_classes c ON c.id = tt.class_id
            WHERE sa.student_id = ?
              AND tt.date >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
            ORDER BY tt.date DESC, tt.start_time DESC
        ");
        $attStmt->execute([$studentId]);
        $attendanceMonth = $attStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($attendanceMonth as $row) {
            $status = $row['status'];
            if (isset($attendanceSummary[$status])) {
                $attendanceSummary[$status]++;
            }
        }
        $attTotal = $attendanceSummary['present'] + $attendanceSummary['absent'] + $attendanceSummary['late'];
        $attendanceSummary['percent'] = $attTotal > 0
            ? (int)round((($attendanceSummary['present'] + $attendanceSummary['late']) / $attTotal) * 100)
            : null;
    }
} catch (Throwable $e) {
    error_log('Student wallet/attendance: ' . $e->getMessage());
}

$examFilter = strtolower(trim((string)($_GET['exam_type'] ?? 'all')));
if (!in_array($examFilter, ['all', 'exam', 'mock'], true)) {
    $examFilter = 'all';
}
$nextExam = $upcomingExams[0] ?? null;

$myOfficialExams = [];
$officialSeries = [];
$officialCatalogue = [];
$officialSelectedIds = [];
$officialSubjects = [];
$officialMatchedSubjects = [];
$officialSeriesId = 0;
$officialSubject = trim((string)($_GET['subject'] ?? ''));
$officialSearch = trim((string)($_GET['q'] ?? ''));
$nextOfficialExam = null;
try {
    $officialExamService = new \Edexcel\Services\OfficialExamService($pdo);
    if (in_array($tab, ['exams', 'overview'], true)) {
        if (!$officialExamService->catalogIsReady()) {
            $officialExamService->ensureSchema();
            $officialExamService->seedIfEmpty();
        }
        $myOfficialExams = $officialExamService->studentTimetable($studentId);
        foreach ($myOfficialExams as $row) {
            if (empty($row['is_past'])) {
                $nextOfficialExam = $row;
                break;
            }
        }
    }
    if (in_array($tab, ['exams', 'overview'], true)) {
        $officialSelectedIds = $officialExamService->selectedExamIds($studentId);
    }
    if ($tab === 'exams') {
        $officialSeries = $officialExamService->listSeries();
        $officialSeriesId = (int)($_GET['series'] ?? 0);
        if ($officialSeriesId < 1) {
            $officialSeriesId = $officialExamService->defaultSeriesId($officialSeries);
        }
        $officialSubjects = $officialSeriesId > 0 ? $officialExamService->listSubjects($officialSeriesId) : [];
        $officialMatchedSubjects = $officialExamService::matchingSubjects($enrolledClasses, $officialSubjects);
        if ($officialSubject !== '' && !in_array($officialSubject, $officialSubjects, true)) {
            $officialSubject = '';
        }
        $officialCatalogue = $officialSeriesId > 0
            ? $officialExamService->listExams($officialSeriesId, $officialSearch, $officialSubject)
            : [];
    }
} catch (Throwable $e) {
    error_log('Official exam planner: ' . $e->getMessage());
}

$recordingCatalogue = [];
$libraryVideos = [];
$lessonPayments = [];
try {
    if (in_array($tab, ['recordings', 'fees'], true)) {
        $accessSvc = new \Edexcel\Services\RecordingAccessService($pdo);
        $recordingCatalogue = $accessSvc->studentCatalogue($studentId);
        $libraryVideos = (new \Edexcel\Services\TeacherVideoLibraryService($pdo))->studentVisible($studentId);
        $lessonPayments = (new \Edexcel\Services\PaymentTransactionService($pdo))->studentHistory($studentId);
    }
} catch (Throwable $e) {
    error_log('Student recordings: ' . $e->getMessage());
}

$studentDocuments = [];
if ($tab === 'documents') {
    try {
        $d = $pdo->prepare("SELECT id, title, file_url, created_at FROM student_documents WHERE student_id = ? ORDER BY id DESC LIMIT 40");
        $d->execute([$studentId]);
        $studentDocuments = $d->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        $studentDocuments = [];
    }
}

// Notifications stay unread until the student opens the bell / marks them.

$classroomNow = ['live' => null, 'next' => null, 'role' => 'student', 'live_count' => 0];
try {
    $classroomNow = classroom_dashboard_cards($pdo, $_SESSION);
} catch (Throwable $e) {
    error_log('Student classroom cards: ' . $e->getMessage());
}

$coursoSnapshot = [
    'profile' => [],
    'next_steps' => [],
    'classes' => [],
    'strengths' => [],
    'weaknesses' => [],
    'subject_scores' => [],
    'streak' => 0,
    'completed_lessons' => 0,
    'quizzes_done' => 0,
    'difficulty' => 'core',
];
try {
    $courso = new \Edexcel\Services\CoursoLearnerService($pdo);
    $courso->logActivity($studentId, 'portal', null, null, true);
    if (in_array($tab, ['courso', 'overview'], true)) {
        $coursoSnapshot = $courso->snapshot($studentId);
    }
} catch (Throwable $e) {
    error_log('Courso snapshot: ' . $e->getMessage());
}

$parentViewUrl = '';
$studentDevices = [];
$studentDeviceLimit = 2;
$studentLoginEvents = [];
$studentSecurityHistory = [];
$studentDeviceCurrentId = (int)($_SESSION['student_device_id'] ?? 0);
if ($tab === 'settings') {
    try {
        $parentToken = classroom_parent_view_token($pdo, $studentId);
        $parentViewUrl = $parentToken !== '' ? classroom_parent_page_url($parentToken) : '';
    } catch (Throwable $e) {
        $parentViewUrl = '';
    }
    try {
        require_once __DIR__ . '/device_helpers.php';
        $svc = student_devices($pdo);
        if ($svc) {
            $studentDevices = $svc->listDevices($studentId, true);
            $studentDeviceLimit = \Edexcel\Services\StudentDeviceService::MAX_DEVICES;
            $studentLoginEvents = $svc->recentEvents($studentId, 8);
            try {
                $studentSecurityHistory = (new \Edexcel\Services\SecurityEventService($pdo))->forStudent($studentId, 20);
            } catch (Throwable $e) {
                $studentSecurityHistory = [];
            }
        }
    } catch (Throwable $e) {
        $studentDevices = [];
        $studentLoginEvents = [];
    }
}

$showGoogleReviewPrompt = false;
$googleReviewUrl = 'https://g.page/r/CSFab2Hr_d_qEAI/review';
if ($hasParentPhone && !$requireMobileNumberModal) {
    try {
        require_once __DIR__ . '/otp_helpers.php';
        $showGoogleReviewPrompt = student_should_show_google_review_prompt($pdo, $studentId);
        $googleReviewUrl = student_google_review_url();
    } catch (Throwable $e) {
        $showGoogleReviewPrompt = false;
    }
}

// ------------------------------------------------------------
// RENDER MASTER LAYOUT
// ------------------------------------------------------------
require __DIR__ . '/views/layout.php';
