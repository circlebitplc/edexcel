<?php
/**
 * export_csv.php
 * Export timetable or payments data as CSV.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/payment.php';
require_once __DIR__ . '/../config/security.php';
require_login();

$type = $_GET['type'] ?? 'timetable'; // 'timetable' or 'payments'
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$teacher_id = (int)($_GET['teacher_id'] ?? 0);
$is_admin = is_admin();
$session_teacher_id = (int)($_SESSION['teacher_id'] ?? 0);

if (!$is_admin) {
    if ($session_teacher_id <= 0) {
        http_response_code(403);
        exit('Teacher account is not linked.');
    }
    $teacher_id = $session_teacher_id;
    if ($type === 'payments') {
        // Teachers may export their own payment history only.
    }
}

if (!in_array($type, ['timetable','payments'], true)) {
    http_response_code(400);
    exit('Invalid export type.');
}

function csv_safe_value($value): string {
    $value = (string)$value;
    if ($value !== '' && in_array($value[0], ['=', '+', '-', '@'], true)) {
        return "'" . $value;
    }
    return $value;
}

// Build query
$where = [];
$params = [];
$filename = '';

if ($type === 'timetable') {
    $filename = 'timetable_export_' . date('Y-m-d');
    $sql = "SELECT t.date, t.start_time, t.end_time, 
                   tc.name as teacher, s.name as subject, 
                   c.name as class, r.name as room,
                   t.student_count, (t.student_count * (
    CASE
        WHEN (
            CASE
                WHEN TIME_TO_SEC(t.end_time) >= TIME_TO_SEC(t.start_time)
                THEN (TIME_TO_SEC(t.end_time) - TIME_TO_SEC(t.start_time)) / 60
                ELSE (TIME_TO_SEC(t.end_time) + 86400 - TIME_TO_SEC(t.start_time)) / 60
            END
        ) <= 150 THEN 500
        WHEN (
            CASE
                WHEN TIME_TO_SEC(t.end_time) >= TIME_TO_SEC(t.start_time)
                THEN (TIME_TO_SEC(t.end_time) - TIME_TO_SEC(t.start_time)) / 60
                ELSE (TIME_TO_SEC(t.end_time) + 86400 - TIME_TO_SEC(t.start_time)) / 60
            END
        ) <= 210 THEN 700
        WHEN (
            CASE
                WHEN TIME_TO_SEC(t.end_time) >= TIME_TO_SEC(t.start_time)
                THEN (TIME_TO_SEC(t.end_time) - TIME_TO_SEC(t.start_time)) / 60
                ELSE (TIME_TO_SEC(t.end_time) + 86400 - TIME_TO_SEC(t.start_time)) / 60
            END
        ) <= 270 THEN 900
        ELSE 1100
    END
)) as revenue,
                   t.payment_status, t.payment_date
            FROM timetable t
            JOIN teachers tc ON t.teacher_id = tc.id
            JOIN subjects s ON t.subject_id = s.id
            JOIN student_classes c ON t.class_id = c.id
            JOIN rooms r ON t.room_id = r.id";
    if ($teacher_id) {
        $where[] = "t.teacher_id = ?";
        $params[] = $teacher_id;
    }
    if (!empty($date_from)) {
        $where[] = "t.date >= ?";
        $params[] = $date_from;
    }
    if (!empty($date_to)) {
        $where[] = "t.date <= ?";
        $params[] = $date_to;
    }
    if (!empty($where)) {
        $sql .= " WHERE " . implode(" AND ", $where);
    }
    $sql .= " ORDER BY t.date DESC, t.start_time ASC";
    
} elseif ($type === 'payments') {
    $filename = 'payments_export_' . date('Y-m-d');
    $sql = "SELECT t.date, t.start_time, t.end_time,
                   tc.name as teacher, s.name as subject,
                   c.name as class, r.name as room,
                   t.student_count, (t.student_count * (
    CASE
        WHEN (
            CASE
                WHEN TIME_TO_SEC(t.end_time) >= TIME_TO_SEC(t.start_time)
                THEN (TIME_TO_SEC(t.end_time) - TIME_TO_SEC(t.start_time)) / 60
                ELSE (TIME_TO_SEC(t.end_time) + 86400 - TIME_TO_SEC(t.start_time)) / 60
            END
        ) <= 150 THEN 500
        WHEN (
            CASE
                WHEN TIME_TO_SEC(t.end_time) >= TIME_TO_SEC(t.start_time)
                THEN (TIME_TO_SEC(t.end_time) - TIME_TO_SEC(t.start_time)) / 60
                ELSE (TIME_TO_SEC(t.end_time) + 86400 - TIME_TO_SEC(t.start_time)) / 60
            END
        ) <= 210 THEN 700
        WHEN (
            CASE
                WHEN TIME_TO_SEC(t.end_time) >= TIME_TO_SEC(t.start_time)
                THEN (TIME_TO_SEC(t.end_time) - TIME_TO_SEC(t.start_time)) / 60
                ELSE (TIME_TO_SEC(t.end_time) + 86400 - TIME_TO_SEC(t.start_time)) / 60
            END
        ) <= 270 THEN 900
        ELSE 1100
    END
)) as revenue,
                   t.payment_status, t.payment_date
            FROM timetable t
            JOIN teachers tc ON t.teacher_id = tc.id
            JOIN subjects s ON t.subject_id = s.id
            JOIN student_classes c ON t.class_id = c.id
            JOIN rooms r ON t.room_id = r.id
            WHERE t.payment_status = 'paid'";
    if ($teacher_id) {
        $where[] = "t.teacher_id = ?";
        $params[] = $teacher_id;
    }
    if (!empty($date_from)) {
        $where[] = "t.payment_date >= ?";
        $params[] = $date_from;
    }
    if (!empty($date_to)) {
        $where[] = "t.payment_date <= ?";
        $params[] = $date_to;
    }
    if (!empty($where)) {
        $sql .= " AND " . implode(" AND ", $where);
    }
    $sql .= " ORDER BY t.payment_date DESC, t.date DESC";
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$entries = $stmt->fetchAll();

// Output CSV
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '.csv"');

$output = fopen('php://output', 'w');
fputcsv($output, ['Date', 'Start', 'End', 'Teacher', 'Subject', 'Class', 'Room', 'Students', 'Revenue (Rs)', 'Status', 'Payment Date']);

foreach ($entries as $e) {
    fputcsv($output, [
        date('d M Y', strtotime($e['date'])),
        date('h:i A', strtotime($e['start_time'])),
        date('h:i A', strtotime($e['end_time'])),
        csv_safe_value($e['teacher']),
        csv_safe_value($e['subject']),
        csv_safe_value($e['class']),
        csv_safe_value($e['room']),
        $e['student_count'],
        $e['revenue'],
        ucfirst($e['payment_status']),
        $e['payment_date'] ? date('d M Y', strtotime($e['payment_date'])) : ''
    ]);
}
fclose($output);
exit;