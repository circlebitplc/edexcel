<?php
declare(strict_types=1);

date_default_timezone_set('Asia/Colombo');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/campus.php';
require_once __DIR__ . '/../config/classroom.php';
require_once __DIR__ . '/../includes/helpers.php';

ensure_campus_schema($pdo);
if (function_exists('ensure_classroom_schema')) {
    ensure_classroom_schema($pdo);
}

$now = new DateTimeImmutable('now', new DateTimeZone('Asia/Colombo'));
$hour = (int)$now->format('H');
$force = in_array('--evening', $argv ?? [], true);

if (!$force && $hour !== 18) {
    exit(0);
}

$today = $now->format('Y-m-d');
$tomorrow = $now->modify('+1 day')->format('Y-m-d');

$students = $pdo->query("
    SELECT id FROM users
    WHERE role = 'student' AND deleted_at IS NULL AND is_active = 1
")->fetchAll(PDO::FETCH_COLUMN);

foreach ($students as $studentId) {
    $studentId = (int)$studentId;
    try {
        $exists = $pdo->prepare("SELECT id FROM parent_digest_log WHERE student_id = ? AND digest_date = ?");
        $exists->execute([$studentId, $today]);
        if ($exists->fetchColumn()) {
            continue;
        }

        campus_ensure_month_fees($pdo, $studentId);
        $contacts = campus_student_contacts($pdo, $studentId);
        if ($contacts['student_phone'] === '' && $contacts['parent_phone'] === '') {
            continue;
        }

        $classIds = $pdo->prepare("SELECT class_id FROM student_enrollments WHERE student_id = ?");
        $classIds->execute([$studentId]);
        $ids = array_map('intval', $classIds->fetchAll(PDO::FETCH_COLUMN) ?: []);

        $lines = ["👨‍👩‍👧 *Edexcel College — evening note*", "Student: *{$contacts['name']}*", ""];

        $classLines = [];
        if ($ids) {
            $in = implode(',', array_fill(0, count($ids), '?'));
            $q = $pdo->prepare("
                SELECT tt.date, tt.start_time, tt.end_time, tt.lesson_status, tt.delivery_mode,
                       tt.id AS timetable_id, om.public_id,
                       s.name AS subject_name, r.name AS room_name, t.name AS teacher_name,
                       st.name AS substitute_name, tt.cancel_reason
                FROM timetable tt
                JOIN subjects s ON s.id = tt.subject_id
                JOIN rooms r ON r.id = tt.room_id
                JOIN teachers t ON t.id = tt.teacher_id
                LEFT JOIN teachers st ON st.id = tt.substitute_teacher_id
                LEFT JOIN online_meetings om ON om.timetable_id = tt.id
                WHERE tt.deleted_at IS NULL
                  AND tt.date = ?
                  AND tt.class_id IN ($in)
                ORDER BY tt.start_time
            ");
            $q->execute(array_merge([$tomorrow], $ids));
            foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $status = $row['lesson_status'] ?? 'scheduled';
                $time = date('h:i A', strtotime($row['start_time']));
                if ($status === 'cancelled') {
                    $classLines[] = "🚫 {$time} {$row['subject_name']} — cancelled";
                } else {
                    $teacher = $row['substitute_name'] ?: $row['teacher_name'];
                    $place = function_exists('classroom_lesson_place_line')
                        ? classroom_lesson_place_line($row)
                        : (string)($row['room_name'] ?? '');
                    $classLines[] = "🗓️ {$time} {$row['subject_name']} · {$place} · {$teacher}";
                }
            }
        }
        $lines[] = "*Tomorrow*";
        $lines[] = $classLines ? implode("\n", $classLines) : "No classes scheduled.";
        $lines[] = "";

        $wallet = campus_fee_summary($pdo, $studentId);
        $lines[] = "*Fees*";
        if ($wallet['due'] > 0) {
            $lines[] = "Due: Rs " . number_format($wallet['due']);
            foreach ($wallet['breakdown'] ?? [] as $group) {
                $lines[] = ($group['class_name'] ?? 'Class') . ' — Rs ' . number_format((float)($group['total'] ?? 0));
                foreach ($group['lessons'] ?? [] as $lesson) {
                    $d = (string)($lesson['date'] ?? '');
                    $when = $d !== '' ? date('d M', strtotime($d)) : '';
                    $lines[] = "  {$when} · Rs " . number_format((float)($lesson['amount'] ?? 0));
                }
            }
        } else {
            $lines[] = "No outstanding class fees.";
        }
        $lines[] = "";

        $att = $pdo->prepare("
            SELECT sa.status, s.name AS subject_name, tt.start_time
            FROM student_attendance sa
            JOIN timetable tt ON tt.id = sa.timetable_id
            JOIN subjects s ON s.id = tt.subject_id
            WHERE sa.student_id = ? AND tt.date = ?
            ORDER BY tt.start_time
        ");
        $att->execute([$studentId, $today]);
        $attRows = $att->fetchAll(PDO::FETCH_ASSOC);
        $lines[] = "*Today's attendance*";
        if ($attRows) {
            foreach ($attRows as $row) {
                $mark = $row['status'] === 'present' ? '✅' : ($row['status'] === 'absent' ? '📛' : '⏰');
                $lines[] = "{$mark} {$row['subject_name']} — {$row['status']}";
            }
        } else {
            $lines[] = "Not marked yet.";
        }

        $fullMessage = implode("\n", $lines);
        $studentLines = [];
        $skipFees = false;
        foreach ($lines as $line) {
            if ($line === '*Fees*') {
                $skipFees = true;
                continue;
            }
            if ($skipFees) {
                if ($line === '') {
                    $skipFees = false;
                }
                continue;
            }
            $studentLines[] = $line;
        }
        $studentMessage = implode("\n", $studentLines);

        $parentToken = function_exists('classroom_parent_view_token')
            ? classroom_parent_view_token($pdo, $studentId)
            : '';
        if ($parentToken !== '' && function_exists('classroom_parent_page_url')) {
            $fullMessage .= "\n\nParent view: " . classroom_parent_page_url($parentToken);
        }

        $sent = campus_notify_digest($pdo, $studentId, $fullMessage, $studentMessage);
        if ($sent < 1) {
            throw new RuntimeException('No WhatsApp digest messages were delivered.');
        }
        $pdo->prepare("
            INSERT INTO parent_digest_log (student_id, digest_date, status)
            VALUES (?, ?, 'sent')
            ON DUPLICATE KEY UPDATE status = 'sent', error = NULL
        ")->execute([$studentId, $today]);
    } catch (Throwable $e) {
        error_log('Parent digest failed for ' . $studentId . ': ' . $e->getMessage());
        try {
            $pdo->prepare("
                INSERT INTO parent_digest_log (student_id, digest_date, status, error)
                VALUES (?, ?, 'failed', ?)
                ON DUPLICATE KEY UPDATE status = 'failed', error = VALUES(error)
            ")->execute([$studentId, $today, $e->getMessage()]);
        } catch (Throwable $ignore) {
        }
    }
}
