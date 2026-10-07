<?php
declare(strict_types=1);

/*
 * Student class enrollment endpoint.
 *
 * Flow:
 * 1. Verify logged-in student + CSRF.
 * 2. Verify WhatsApp number.
 * 3. Verify class exists and is active.
 * 4. Insert student_enrollments.
 * 5. Send class WhatsApp group link(s) to the student's WhatsApp.
 * 6. Notify all teachers associated with the class.
 * 7. Log WhatsApp messages + notification attempts.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/campus.php';
require_once __DIR__ . '/../config/load_env.php';
require_once __DIR__ . '/../config/whatsapp_gateway.php';
require_once __DIR__ . '/../vendor/autoload.php';

require_student();

$joinReturnTab = strtolower(trim((string)($_POST['return_tab'] ?? 'join')));
if (!in_array($joinReturnTab, ['join', 'teachers', 'classes'], true)) {
    $joinReturnTab = 'join';
}
$joinReturnTeacherId = (int)($_POST['teacher_id'] ?? 0);
$joinReturnUrl = 'dashboard.php?tab=' . $joinReturnTab;
if ($joinReturnTab === 'teachers' && $joinReturnTeacherId > 0) {
    $joinReturnUrl .= '&id=' . $joinReturnTeacherId;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dashboard.php?tab=join');
    exit;
}

$studentId = (int)($_SESSION['user_id'] ?? $_SESSION['student_id'] ?? 0);
$classId = (int)($_POST['class_id'] ?? 0);

if ($studentId <= 0 || $classId <= 0) {
    $_SESSION['student_error'] = 'Invalid student or class.';
    header('Location: ' . $joinReturnUrl);
    exit;
}

if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $_SESSION['student_error'] = 'Your security token expired. Please refresh the page and try again.';
    header('Location: ' . $joinReturnUrl);
    exit;
}

function join_normalize_phone(string $phone): string
{
    $phone = preg_replace('/\D+/', '', $phone) ?? '';

    if (str_starts_with($phone, '0') && strlen($phone) === 10) {
        $phone = '94' . substr($phone, 1);
    }

    return $phone;
}

function join_log_whatsapp(
    PDO $pdo,
    string $phone,
    string $message,
    string $event = 'STUDENT_CLASS_JOIN'
): void {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO whatsapp_bot_messages
                (phone, direction, message, event_name)
            VALUES
                (?, 'outbound', ?, ?)
        ");
        $stmt->execute([$phone, $message, $event]);
    } catch (Throwable $e) {
        error_log('Student join WhatsApp log failed: ' . $e->getMessage());
    }
}

function join_log_notification(
    PDO $pdo,
    string $recipient,
    string $type,
    string $message,
    string $status,
    ?string $error = null
): void {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO notifications_log
                (recipient, type, message, status, error, sent_at)
            VALUES
                (?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $recipient,
            $type,
            $message,
            $status,
            $error,
            $status === 'sent' ? date('Y-m-d H:i:s') : null
        ]);
    } catch (Throwable $e) {
        error_log('Student join notification log failed: ' . $e->getMessage());
    }
}

try {
    ensure_campus_schema($pdo);

    $joinAction = (string)($_POST['join_action'] ?? 'enroll');

    /*
     * Fetch student account.
     */
    $stmt = $pdo->prepare("
        SELECT id, username
        FROM users
        WHERE id = ?
          AND role = 'student'
          AND deleted_at IS NULL
          AND is_active = 1
        LIMIT 1
    ");
    $stmt->execute([$studentId]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$student) {
        throw new RuntimeException('Student account is not active.');
    }

    /*
     * Fetch class.
     */
    $stmt = $pdo->prepare("
        SELECT
            c.id,
            c.name,
            c.whatsapp_link,
            c.capacity
        FROM student_classes c
        WHERE c.id = ?
          AND c.deleted_at IS NULL
        LIMIT 1
    ");
    $stmt->execute([$classId]);
    $class = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$class) {
        throw new RuntimeException('The selected class is no longer available.');
    }

    if ($joinAction === 'leave_waitlist') {
        campus_waitlist_leave($pdo, $studentId, $classId);
        $_SESSION['student_success'] = 'You left the waitlist for "' . $class['name'] . '".';
        header('Location: ' . $joinReturnUrl);
        exit;
    }

    /*
     * Do not enroll twice.
     */
    $stmt = $pdo->prepare("
        SELECT id
        FROM student_enrollments
        WHERE student_id = ?
          AND class_id = ?
        LIMIT 1
    ");
    $stmt->execute([$studentId, $classId]);

    if ($stmt->fetchColumn()) {
        throw new RuntimeException('You have already joined this class.');
    }

    $capacity = isset($class['capacity']) && $class['capacity'] !== null && $class['capacity'] !== ''
        ? (int)$class['capacity']
        : null;
    if (campus_class_is_full($pdo, $classId, $capacity)) {
        $position = campus_waitlist_join($pdo, $studentId, $classId);
        $_SESSION['student_success'] =
            'This class is full. You are number ' . $position .
            ' on the waitlist for "' . $class['name'] . '". ' .
            'If a student leaves, you will get a 24-hour offer to take the seat.';
        header('Location: ' . $joinReturnUrl);
        exit;
    }

    campus_waitlist_leave($pdo, $studentId, $classId);

    $studentPhone = join_normalize_phone((string)$student['username']);
    try {
        $p = $pdo->prepare('SELECT whatsapp_number FROM student_profiles WHERE user_id = ? LIMIT 1');
        $p->execute([$studentId]);
        $profilePhone = join_normalize_phone((string)($p->fetchColumn() ?: ''));
        if ($profilePhone !== '') {
            $studentPhone = $profilePhone;
        }
    } catch (Throwable $e) {
    }

    /*
     * Fetch all group links.
     *
     * Priority:
     *   1. student_classes.whatsapp_link
     *   2. class_teacher_whatsapp mappings
     *
     * Multiple teacher groups are supported.
     */
    $groupLinks = [];

    if (!empty($class['whatsapp_link'])) {
        $groupLinks[] = trim((string)$class['whatsapp_link']);
    }

    $stmt = $pdo->prepare("
        SELECT whatsapp_link
        FROM class_teacher_whatsapp
        WHERE class_id = ?
          AND whatsapp_link IS NOT NULL
          AND whatsapp_link <> ''
        ORDER BY id
    ");
    $stmt->execute([$classId]);

    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $link) {
        $link = trim((string)$link);

        if ($link !== '' && !in_array($link, $groupLinks, true)) {
            $groupLinks[] = $link;
        }
    }

    /*
     * Enrolment.
     *
     * The database already has a UNIQUE(student_id,class_id)
     * constraint in the project schema, so duplicate joins are
     * also protected at database level.
     */
    $chosenTeacherId = (int)($_POST['teacher_id'] ?? 0);
    if ($chosenTeacherId > 0) {
        $teaches = false;
        try {
            $chk = $pdo->prepare("
                SELECT 1
                FROM timetable
                WHERE class_id = ?
                  AND teacher_id = ?
                  AND deleted_at IS NULL
                LIMIT 1
            ");
            $chk->execute([$classId, $chosenTeacherId]);
            $teaches = (bool)$chk->fetchColumn();
            if (!$teaches && campus_column_exists($pdo, 'student_classes', 'teacher_id')) {
                $chk = $pdo->prepare("SELECT 1 FROM student_classes WHERE id = ? AND teacher_id = ? LIMIT 1");
                $chk->execute([$classId, $chosenTeacherId]);
                $teaches = (bool)$chk->fetchColumn();
            }
        } catch (Throwable $e) {
            $teaches = false;
        }
        if (!$teaches) {
            $chosenTeacherId = 0;
        }
    }

    $pdo->beginTransaction();

    $enrolled = false;
    try {
        if ($chosenTeacherId > 0 && campus_column_exists($pdo, 'student_enrollments', 'teacher_id')) {
            $stmt = $pdo->prepare("
                INSERT INTO student_enrollments (student_id, class_id, teacher_id)
                VALUES (?, ?, ?)
            ");
            $enrolled = $stmt->execute([$studentId, $classId, $chosenTeacherId]);
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO student_enrollments (student_id, class_id)
                VALUES (?, ?)
            ");
            $enrolled = $stmt->execute([$studentId, $classId]);
        }
    } catch (Throwable $insertError) {
        throw new RuntimeException('Unable to complete enrolment. Please try again.');
    }

    if (!$enrolled) {
        throw new RuntimeException('Unable to complete enrolment. Please try again.');
    }

    $enrollmentId = (int)$pdo->lastInsertId();

    $pdo->commit();

    $teacherLabel = '';
    if ($chosenTeacherId > 0) {
        try {
            $tn = $pdo->prepare("SELECT name FROM teachers WHERE id = ? AND deleted_at IS NULL LIMIT 1");
            $tn->execute([$chosenTeacherId]);
            $teacherLabel = trim((string)($tn->fetchColumn() ?: ''));
        } catch (Throwable $e) {
            $teacherLabel = '';
        }
    }

    $studentName = '';
    try {
        $n = $pdo->prepare("SELECT full_name FROM student_profiles WHERE user_id = ? LIMIT 1");
        $n->execute([$studentId]);
        $studentName = trim((string)($n->fetchColumn() ?: ''));
    } catch (Throwable $e) {
    }
    if ($studentName === '') {
        $studentName = trim((string)($student['username'] ?? ''));
    }

    campus_notify_class_join(
        $pdo,
        $studentId,
        $classId,
        (string)$class['name'],
        $studentPhone,
        $studentName,
        $groupLinks
    );

    $_SESSION['student_success'] =
        'You joined "' . $class['name'] . '"' .
        ($teacherLabel !== '' ? ' with ' . $teacherLabel : '') .
        ($studentPhone !== ''
            ? '. A WhatsApp message with your teacher contact and class details has been sent.'
            : '.');

} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('Student class join error: ' . $e->getMessage());

    $_SESSION['student_error'] = $e->getMessage();
}

header('Location: ' . $joinReturnUrl);
exit;
