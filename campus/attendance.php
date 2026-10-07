<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/config.php';

require_staff();
ensure_campus_schema($pdo);

$isAdmin = is_admin();
$teacherId = (int)($_SESSION['teacher_id'] ?? 0);
$error = '';
$success = '';
$newLogin = null;
$date = trim((string)($_GET['date'] ?? date('Y-m-d')));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    $date = date('Y-m-d');
}
$selectedLesson = (int)($_GET['lesson'] ?? $_POST['timetable_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
            throw new RuntimeException('Security token expired. Refresh and try again.');
        }
        $action = (string)($_POST['action'] ?? 'save_attendance');

        if ($action === 'register_student') {
            $lessonId = (int)($_POST['timetable_id'] ?? 0);
            $lesson = $pdo->prepare("SELECT * FROM timetable WHERE id = ? AND deleted_at IS NULL LIMIT 1");
            $lesson->execute([$lessonId]);
            $entry = $lesson->fetch(PDO::FETCH_ASSOC);
            if (!$entry) {
                throw new RuntimeException('Choose a class first.');
            }
            if (!$isAdmin && !campus_staff_can_mark_lesson($entry, $isAdmin, $teacherId)) {
                throw new RuntimeException('You can only add students to your classes.');
            }
            $classId = (int)$entry['class_id'];
            if (!campus_staff_can_access_class($pdo, $classId, $isAdmin, $teacherId)) {
                throw new RuntimeException('You cannot add students to this class.');
            }
            $result = campus_teacher_add_student(
                $pdo,
                $classId,
                (string)($_POST['full_name'] ?? ''),
                (string)($_POST['whatsapp'] ?? ''),
                (string)($_POST['parent_name'] ?? ''),
                (string)($_POST['parent_whatsapp'] ?? ''),
                $lessonId,
                isset($_POST['mark_present']),
                (int)($_SESSION['user_id'] ?? 0),
                (int)($_POST['student_id'] ?? 0),
                (string)($_POST['google_email'] ?? '')
            );
            $selectedLesson = $lessonId;
            $date = (string)$entry['date'];
            if ($result['created']) {
                $newLogin = $result;
                $success = $result['name'] . ' is registered and added to this class.';
            } elseif ($result['already_enrolled']) {
                $success = $result['name'] . ' is already in this class.';
            } else {
                $success = $result['name'] . ' was added to this class.';
            }
            if (isset($_POST['mark_present'])) {
                $success .= ' Marked present.';
            }
            $targetPhone = (string)($result['phone'] ?? $_POST['whatsapp'] ?? '');
            if (isset($_POST['send_join_sms']) && $targetPhone !== '' && function_exists('classroom_send_lesson_join_sms')) {
                $sms = classroom_send_lesson_join_sms(
                    $pdo,
                    $lessonId,
                    $targetPhone,
                    (int)($_SESSION['user_id'] ?? 0),
                    true
                );
                $success .= ' ' . (string)($sms['message'] ?? '');
            }
        } elseif ($action === 'remove_student') {
            $lessonId = (int)($_POST['timetable_id'] ?? 0);
            $studentId = (int)($_POST['student_id'] ?? 0);
            $lesson = $pdo->prepare("SELECT * FROM timetable WHERE id = ? AND deleted_at IS NULL LIMIT 1");
            $lesson->execute([$lessonId]);
            $entry = $lesson->fetch(PDO::FETCH_ASSOC);
            if (!$entry) {
                throw new RuntimeException('Choose a class first.');
            }
            if (!$isAdmin && !campus_staff_can_mark_lesson($entry, $isAdmin, $teacherId)) {
                throw new RuntimeException('You can only remove students from your classes.');
            }
            $classId = (int)$entry['class_id'];
            if (!campus_staff_can_access_class($pdo, $classId, $isAdmin, $teacherId)) {
                throw new RuntimeException('You cannot remove students from this class.');
            }
            if ($studentId < 1) {
                throw new RuntimeException('Choose a student to remove.');
            }
            $rosterIds = campus_class_student_ids($pdo, $classId);
            if (!in_array($studentId, $rosterIds, true)) {
                throw new RuntimeException('That student is not on this class list.');
            }
            $reason = campus_teacher_remove_reason_from_post($_POST);
            campus_unenroll_from_class(
                $pdo,
                $studentId,
                $classId,
                $reason,
                $isAdmin && $teacherId < 1 ? 'admin' : 'teacher',
                (int)($_SESSION['user_id'] ?? 0),
                campus_staff_actor_name($pdo, $isAdmin, $teacherId)
            );
            campus_clear_lesson_attendance_for_student($pdo, $studentId, $lessonId);
            if (function_exists('log_audit')) {
                log_audit($pdo, 'teacher_remove_student', 'student_enrollments', $studentId, null, [
                    'class_id' => $classId,
                    'timetable_id' => $lessonId,
                ]);
            }
            $contacts = campus_student_contacts($pdo, $studentId);
            $removedName = trim((string)($contacts['name'] ?? ''));
            $success = ($removedName !== '' ? $removedName : 'Student')
                . ' was removed from this class and this lesson list.';
            $selectedLesson = $lessonId;
            $date = (string)$entry['date'];
        } else {
        $lessonId = (int)($_POST['timetable_id'] ?? 0);
        $marks = $_POST['status'] ?? [];
        if (!is_array($marks)) {
            $marks = [];
        }
        if ($lessonId < 1) {
            throw new RuntimeException('Choose a class first.');
        }

        $lesson = $pdo->prepare("SELECT * FROM timetable WHERE id = ? AND deleted_at IS NULL LIMIT 1");
        $lesson->execute([$lessonId]);
        $entry = $lesson->fetch(PDO::FETCH_ASSOC);
        if (!$entry) {
            throw new RuntimeException('Class not found.');
        }
        if (!$isAdmin && !campus_staff_can_mark_lesson($entry, $isAdmin, $teacherId)) {
            throw new RuntimeException('You can only mark attendance for your classes.');
        }

        $rosterIds = campus_class_student_ids($pdo, (int)$entry['class_id']);
        $rosterOk = array_fill_keys($rosterIds, true);
        $save = $pdo->prepare("
            INSERT INTO student_attendance (student_id, timetable_id, status, marked_by)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE status = VALUES(status), marked_by = VALUES(marked_by), marked_at = CURRENT_TIMESTAMP
        ");
        $userId = (int)($_SESSION['user_id'] ?? 0);
        $absentNames = [];
        $registeredPresent = 0;
        $feeSync = new \Edexcel\Services\StudentLessonFeeService($pdo);
        foreach ($marks as $studentId => $status) {
            $studentId = (int)$studentId;
            if ($studentId < 1 || !isset($rosterOk[$studentId])) {
                continue;
            }
            $status = in_array($status, ['present', 'absent', 'late', 'excused'], true) ? $status : 'present';
            $save->execute([$studentId, $lessonId, $status, $userId]);
            if ($status === 'present' || $status === 'late') {
                $registeredPresent++;
                try {
                    $feeSync->syncMonthlyCreditAfterAttendance($studentId, $lessonId);
                } catch (Throwable $e) {
                    error_log('attendance monthly credit: ' . $e->getMessage());
                }
            }
            if ($status === 'absent') {
                $contacts = campus_student_contacts($pdo, $studentId);
                $absentNames[] = $contacts['name'];
                $when = date('d M, h:i A', strtotime($entry['date'] . ' ' . $entry['start_time']));
                campus_notify_phones(
                    $pdo,
                    $studentId,
                    "📛 *Attendance*\n\n{$contacts['name']} was marked *absent* for class on {$when}.\n\nPlease contact the college if this is unexpected.",
                    'ATTENDANCE_ABSENT'
                );
                try {
                    (new \Edexcel\Services\CommunicationEventService($pdo))->attendanceAbsent($studentId, [
                        'date' => (string)$entry['date'],
                        'class_id' => (int)($entry['class_id'] ?? 0),
                        'class_name' => (string)($entry['class_name'] ?? $entry['subject_name'] ?? ''),
                        'actor_id' => $userId,
                        'notify_parents' => true,
                    ]);
                } catch (Throwable $e) {
                    error_log('attendance communication hub: ' . $e->getMessage());
                }
            }
        }
        $unregistered = max(0, min(200, (int)($_POST['unregistered_present'] ?? 0)));
        try {
            $pdo->prepare("
                UPDATE timetable
                SET unregistered_present = ?, student_count = ?
                WHERE id = ?
            ")->execute([$unregistered, $registeredPresent + $unregistered, $lessonId]);
        } catch (Throwable $e) {
            error_log('Attendance unregistered count: ' . $e->getMessage());
        }
        $periodYm = date('Y-m', strtotime((string)$entry['date']));
        foreach (array_unique(array_map('intval', array_keys($marks))) as $markedStudentId) {
            if ($markedStudentId > 0) {
                campus_ensure_month_fees($pdo, $markedStudentId, $periodYm);
            }
        }
        $success = 'Attendance saved.'
            . ($unregistered > 0 ? ' ' . $unregistered . ' unregistered student' . ($unregistered === 1 ? '' : 's') . ' included in the class count.' : '')
            . ($absentNames ? ' Parents/students notified for absences.' : '');
        $selectedLesson = $lessonId;
        $date = (string)$entry['date'];
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$sql = "
    SELECT tt.id, tt.date, tt.start_time, tt.end_time, tt.teacher_id, tt.substitute_teacher_id,
           tt.class_id, s.name AS subject_name, c.name AS class_name, t.name AS teacher_name
    FROM timetable tt
    JOIN subjects s ON s.id = tt.subject_id
    JOIN student_classes c ON c.id = tt.class_id
    JOIN teachers t ON t.id = tt.teacher_id
    WHERE tt.deleted_at IS NULL
      AND tt.date = ?
";
$params = [$date];
$hasSubstitute = campus_column_exists($pdo, 'timetable', 'substitute_teacher_id');
if (!$isAdmin && $teacherId > 0) {
    if ($hasSubstitute) {
        $sql .= ' AND (tt.teacher_id = ? OR COALESCE(tt.substitute_teacher_id, 0) = ?)';
        $params[] = $teacherId;
        $params[] = $teacherId;
    } else {
        $sql .= " AND tt.teacher_id = ?";
        $params[] = $teacherId;
    }
}
$sql .= " ORDER BY tt.start_time";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$lessons = $stmt->fetchAll(PDO::FETCH_ASSOC);

$roster = [];
$currentLesson = null;
$unregisteredPresent = 0;
if ($selectedLesson > 0) {
    foreach ($lessons as $lesson) {
        if ((int)$lesson['id'] === $selectedLesson) {
            $currentLesson = $lesson;
            break;
        }
    }
    if ($currentLesson) {
        $classIdStmt = $pdo->prepare("SELECT class_id FROM timetable WHERE id = ?");
        $classIdStmt->execute([$selectedLesson]);
        $classId = (int)$classIdStmt->fetchColumn();
        $currentLesson['class_id'] = $classId;
        $rosterStmt = $pdo->prepare("
            SELECT u.id, COALESCE(sp.full_name, u.username) AS name,
                   sa.status
            FROM student_enrollments se
            JOIN users u ON u.id = se.student_id AND u.deleted_at IS NULL
            LEFT JOIN student_profiles sp ON sp.user_id = u.id
            LEFT JOIN student_attendance sa
                ON sa.student_id = u.id AND sa.timetable_id = ?
            WHERE se.class_id = ?
            ORDER BY name
        ");
        $rosterStmt->execute([$selectedLesson, $classId]);
        $roster = $rosterStmt->fetchAll(PDO::FETCH_ASSOC);
        try {
            $unregStmt = $pdo->prepare("SELECT unregistered_present FROM timetable WHERE id = ?");
            $unregStmt->execute([$selectedLesson]);
            $unregisteredPresent = max(0, (int)$unregStmt->fetchColumn());
        } catch (Throwable $e) {
            $unregisteredPresent = 0;
        }
    }
}

$dropoff = [];
if ($isAdmin) {
    $dropoff = $pdo->query("
        SELECT u.id, COALESCE(sp.full_name, u.username) AS name,
               SUM(sa.status = 'absent') AS absences
        FROM student_attendance sa
        JOIN timetable tt ON tt.id = sa.timetable_id AND tt.deleted_at IS NULL
        JOIN users u ON u.id = sa.student_id
        LEFT JOIN student_profiles sp ON sp.user_id = u.id
        WHERE tt.date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        GROUP BY u.id, name
        HAVING absences >= 3
        ORDER BY absences DESC
        LIMIT 30
    ")->fetchAll(PDO::FETCH_ASSOC);
}

include __DIR__ . '/../includes/header.php';
?>
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1"><i class="bi bi-check2-circle text-primary"></i> Attendance</h1>
            <p class="text-muted mb-0">Mark enrolled students, then add any unregistered walk-ins as a number so the class count stays complete.</p>
        </div>
        <form class="d-flex gap-2" method="get">
            <input type="date" name="date" class="form-control" value="<?= e($date) ?>">
            <button class="btn btn-outline-primary">Go</button>
        </form>
    </div>
    <?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
    <?php if (!empty($newLogin['plain_password'])): ?>
        <div class="alert alert-warning">
            <strong>Give these login details to the student now</strong> (also sent on WhatsApp if sending worked).
            <div class="mt-2">
                Login: <a href="<?= e(student_login_url()) ?>"><?= e(student_login_url()) ?></a><br>
                Username: <code><?= e('+' . (string)$newLogin['phone']) ?></code><br>
                Temporary password: <code><?= e((string)$newLogin['plain_password']) ?></code>
            </div>
            <p class="small mb-0 mt-2">If they lose it, they can open Student Login → Forgot password and use a WhatsApp OTP.</p>
        </div>
    <?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>

    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body">
                    <h5>Classes on <?= e(date('d M Y', strtotime($date))) ?></h5>
                    <?php if (!$lessons): ?>
                        <p class="text-muted mb-0">No classes on this date.</p>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($lessons as $lesson): ?>
                                <a class="list-group-item list-group-item-action <?= (int)$lesson['id'] === $selectedLesson ? 'active' : '' ?>"
                                   href="?date=<?= e($date) ?>&lesson=<?= (int)$lesson['id'] ?>">
                                    <strong><?= e($lesson['subject_name']) ?></strong>
                                    <div class="small"><?= e(date('h:i A', strtotime($lesson['start_time']))) ?> · <?= e($lesson['class_name']) ?></div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php if ($isAdmin && $dropoff): ?>
                <div class="card border-0 shadow-sm rounded-4 mt-4">
                    <div class="card-body">
                        <h5>Drop-off watch</h5>
                        <p class="small text-muted">3+ absences in the last 30 days.</p>
                        <ul class="list-unstyled mb-0">
                            <?php foreach ($dropoff as $row): ?>
                                <li class="d-flex justify-content-between py-1">
                                    <span><?= e($row['name']) ?></span>
                                    <span class="badge bg-danger"><?= (int)$row['absences'] ?> absent</span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body">
                    <?php if (!$currentLesson): ?>
                        <p class="text-muted mb-0">Select a class to mark attendance.</p>
                    <?php else: ?>
                        <h5 class="mb-3"><?= e($currentLesson['subject_name']) ?> · <?= e($currentLesson['class_name']) ?></h5>
                        <form method="post">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="save_attendance">
                            <input type="hidden" name="timetable_id" value="<?= (int)$currentLesson['id'] ?>">
                        <?php if (!$roster): ?>
                            <p class="text-muted">No enrolled students on the list yet. You can still record how many unregistered students attended.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table align-middle">
                                    <thead><tr><th>Student</th><th>Present</th><th>Absent</th><th>Late</th><th class="text-end"></th></tr></thead>
                                    <tbody>
                                    <?php foreach ($roster as $row): $st = $row['status'] ?: 'present'; ?>
                                        <tr>
                                            <td><?= e($row['name']) ?></td>
                                            <?php foreach (['present','absent','late'] as $opt): ?>
                                                <td>
                                                    <input class="form-check-input" type="radio"
                                                           name="status[<?= (int)$row['id'] ?>]"
                                                           value="<?= $opt ?>" <?= $st === $opt ? 'checked' : '' ?>>
                                                </td>
                                            <?php endforeach; ?>
                                            <td class="text-end">
                                                <button
                                                    type="button"
                                                    class="btn btn-sm btn-outline-danger rounded-pill js-remove-student"
                                                    data-student-id="<?= (int)$row['id'] ?>"
                                                    data-student-name="<?= e((string)$row['name']) ?>"
                                                    title="Remove from this class"
                                                    aria-label="Remove <?= e((string)$row['name']) ?> from this class"
                                                ><i class="bi bi-trash"></i></button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                            <?php
                            $registeredPresentUi = 0;
                            foreach ($roster as $row) {
                                $st = (string)($row['status'] ?: 'present');
                                if ($st === 'present' || $st === 'late') {
                                    $registeredPresentUi++;
                                }
                            }
                            $totalUi = $registeredPresentUi + $unregisteredPresent;
                            ?>
                            <div class="border rounded-4 p-3 bg-light mb-3">
                                <label class="form-label fw-semibold mb-1" for="unregistered_present">Unregistered students present</label>
                                <p class="small text-muted mb-2">Walk-ins who are not on this list. This number is added to the class count so the headcount is not missed.</p>
                                <div class="row g-3 align-items-end">
                                    <div class="col-sm-4 col-md-3">
                                        <input class="form-control" type="number" min="0" max="200" id="unregistered_present" name="unregistered_present" value="<?= (int)$unregisteredPresent ?>">
                                    </div>
                                    <div class="col-sm-8 small text-muted">
                                        Enrolled on list: <?= count($roster) ?>
                                        · Unregistered: <?= (int)$unregisteredPresent ?>
                                        · Total count: <?= (int)$totalUi ?>
                                    </div>
                                </div>
                            </div>
                            <button class="btn btn-primary rounded-pill">Save attendance</button>
                            <a class="btn btn-outline-success rounded-pill ms-2" href="<?= e(BASE_URL . 'campus/lesson_fees.php?date=' . urlencode($date) . '&lesson=' . (int)$currentLesson['id']) ?>">Mark class fees</a>
                        </form>

                        <hr class="my-4">
                        <h6 class="fw-bold"><i class="bi bi-person-plus me-1"></i> New student in this class</h6>
                        <p class="small text-muted">Add an existing student with their Google email, or search by name or phone. To register a new walk-in, enter their mobile number.</p>
                        <form method="post" class="row g-3 js-walkin-form"
                              data-lookup-url="<?= e(BASE_URL) ?>ajax/lookup_student.php"
                              data-sms-url="<?= e(BASE_URL) ?>ajax/send_class_join_sms.php">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="register_student">
                            <input type="hidden" name="timetable_id" value="<?= (int)$currentLesson['id'] ?>">
                            <input type="hidden" name="class_id" value="<?= (int)($currentLesson['class_id'] ?? 0) ?>">
                            <input type="hidden" name="student_id" class="js-walkin-student-id" value="">

                            <!-- Search existing student by Google email, name, or phone -->
                            <div class="col-12">
                                <label class="form-label fw-semibold">Search existing student <span class="text-muted fw-normal">(by Google email, name, or phone)</span></label>
                                <div class="position-relative">
                                    <div class="input-group">
                                        <span class="input-group-text border-end-0"><i class="bi bi-search text-muted"></i></span>
                                        <input type="text" class="form-control border-start-0 ps-0 js-student-search-input"
                                               placeholder="Type Google email, name, or mobile number to search…"
                                               autocomplete="off">
                                        <button class="btn btn-outline-secondary js-student-search-clear d-none" type="button" title="Clear search">
                                            <i class="bi bi-x-lg"></i>
                                        </button>
                                    </div>
                                    <div class="dropdown-menu w-100 shadow-lg js-student-search-results p-0 mt-1"
                                         style="max-height: 280px; overflow-y: auto; display: none; z-index: 1050;">
                                    </div>
                                </div>
                            </div>

                            <!-- Selected Student Confirmation Banner -->
                            <div class="col-12 js-selected-student-banner d-none">
                                <div class="alert alert-primary d-flex align-items-center justify-content-between py-2 px-3 mb-0">
                                    <div class="d-flex align-items-center gap-2 overflow-hidden">
                                        <i class="bi bi-person-check-fill fs-5 text-primary flex-shrink-0"></i>
                                        <div class="overflow-hidden">
                                            <strong class="js-selected-student-name"></strong>
                                            <div class="small js-selected-student-meta text-muted text-truncate"></div>
                                        </div>
                                    </div>
                                    <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill js-walkin-clear-selection flex-shrink-0 ms-2">Change</button>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Google email</label>
                                <input class="form-control js-walkin-email js-walkin-google-email-val" name="google_email" type="email" inputmode="email" placeholder="student@gmail.com" autocomplete="email">
                                <div class="form-text">Adds a student who already signs in with Google. Mobile number is not required.</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Student WhatsApp / mobile</label>
                                <input class="form-control js-walkin-whatsapp" name="whatsapp" inputmode="tel" placeholder="077XXXXXXX" autocomplete="tel">
                                <div class="form-text js-walkin-status"></div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Full name</label>
                                <input class="form-control js-walkin-name" name="full_name" placeholder="Student name">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Parent name <span class="text-muted">(optional)</span></label>
                                <input class="form-control js-walkin-parent-name" name="parent_name">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Parent WhatsApp <span class="text-muted">(optional)</span></label>
                                <input class="form-control js-walkin-parent-whatsapp" name="parent_whatsapp" inputmode="tel" placeholder="077XXXXXXX">
                            </div>
                            <div class="col-12">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="mark_present" id="mark_present" checked>
                                    <label class="form-check-label" for="mark_present">Mark present for this lesson</label>
                                </div>
                                <div class="form-check mt-2">
                                    <input class="form-check-input" type="checkbox" name="send_join_sms" value="1" id="send_join_sms" checked>
                                    <label class="form-check-label" for="send_join_sms">Text a direct class join link by SMS after they are added</label>
                                </div>
                            </div>
                            <div class="col-12 js-walkin-sms-wrap d-none">
                                <button type="button" class="btn btn-outline-primary rounded-pill js-walkin-send-sms">
                                    <i class="bi bi-chat-text me-1"></i> Send class join SMS now
                                </button>
                                <div class="form-text js-walkin-sms-status"></div>
                            </div>
                            <div class="col-12">
                                <button class="btn btn-success rounded-pill"><i class="bi bi-person-plus me-1"></i> <span class="js-walkin-submit-label">Register and add to class</span></button>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
<?php if ($currentLesson): ?>
<div class="modal fade" id="removeStudentModal" tabindex="-1" aria-labelledby="removeStudentTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="remove_student">
                <input type="hidden" name="timetable_id" value="<?= (int)$currentLesson['id'] ?>">
                <input type="hidden" name="student_id" id="removeStudentId" value="">
                <div class="modal-header">
                    <h2 class="modal-title h5" id="removeStudentTitle">Remove student</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-3" id="removeStudentText">This student will be removed from this class and this lesson list, then notified.</p>
                    <label class="form-label">Reason</label>
                    <p class="small text-muted">They leave this class (including other lessons in it). The student, other teachers, and admin will see this reason.</p>
                    <?php foreach (campus_teacher_remove_reason_options() as $key => $label): ?>
                        <div class="form-check">
                            <input class="form-check-input js-remove-reason" type="radio" name="reason_preset" id="removeReason_<?= e($key) ?>" value="<?= e($key) ?>" required>
                            <label class="form-check-label" for="removeReason_<?= e($key) ?>"><?= e($label) ?></label>
                        </div>
                    <?php endforeach; ?>
                    <div class="form-check">
                        <input class="form-check-input js-remove-reason" type="radio" name="reason_preset" id="removeReason_other" value="other" required>
                        <label class="form-check-label" for="removeReason_other">Other</label>
                    </div>
                    <div class="mt-3" id="removeReasonExtraWrap">
                        <label class="form-label" for="removeStudentReasonExtra">Extra note <span class="text-muted" id="removeReasonExtraHint">(optional)</span></label>
                        <textarea class="form-control" id="removeStudentReasonExtra" name="reason_extra" rows="2" maxlength="400" placeholder="Add a short note if needed"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Remove and notify</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const modalEl = document.getElementById('removeStudentModal');
    if (!modalEl || !window.bootstrap) {
        return;
    }
    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    const idInput = document.getElementById('removeStudentId');
    const textEl = document.getElementById('removeStudentText');
    const extra = document.getElementById('removeStudentReasonExtra');
    const extraHint = document.getElementById('removeReasonExtraHint');
    function syncOther() {
        const other = document.getElementById('removeReason_other');
        const isOther = !!(other && other.checked);
        if (extra) {
            extra.required = isOther;
            extra.minLength = isOther ? 5 : 0;
            extra.placeholder = isOther ? 'Type the reason' : 'Add a short note if needed';
        }
        if (extraHint) {
            extraHint.textContent = isOther ? '(required)' : '(optional)';
        }
    }
    document.querySelectorAll('.js-remove-reason').forEach(function (el) {
        el.addEventListener('change', syncOther);
    });
    document.querySelectorAll('.js-remove-student').forEach(function (btn) {
        btn.addEventListener('click', function () {
            idInput.value = btn.getAttribute('data-student-id') || '';
            const name = btn.getAttribute('data-student-name') || 'this student';
            textEl.textContent = 'Remove ' + name + ' from this class? They will leave this lesson list and other lessons in this class, and they will be notified.';
            document.querySelectorAll('.js-remove-reason').forEach(function (el) { el.checked = false; });
            if (extra) {
                extra.value = '';
            }
            syncOther();
            modal.show();
        });
    });
});
</script>
<?php endif; ?>
<?php
$walkinLookupJs = __DIR__ . '/../assets/js/campus-student-lookup.js';
if (is_file($walkinLookupJs)):
?>
<script src="<?= e(BASE_URL) ?>assets/js/campus-student-lookup.js?v=<?= filemtime($walkinLookupJs) ?>"></script>
<?php endif; ?>
