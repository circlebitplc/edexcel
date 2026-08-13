<?php
// includes/notifications.php
// Functions to get notifications for the dashboard

function get_upcoming_lessons($pdo, $teacher_id = null) {
    $now = date('Y-m-d H:i:s');
    $tomorrow = date('Y-m-d H:i:s', strtotime('+24 hours'));
    $params = [$now, $tomorrow];
    $sql = "SELECT t.*, tc.name as teacher_name, s.name as subject_name, c.name as class_name, r.name as room_name 
            FROM timetable t
            JOIN teachers tc ON t.teacher_id = tc.id
            JOIN subjects s ON t.subject_id = s.id
            JOIN student_classes c ON t.class_id = c.id
            JOIN rooms r ON t.room_id = r.id
            WHERE CONCAT(t.date, ' ', t.start_time) >= ? AND CONCAT(t.date, ' ', t.start_time) < ?";
    if ($teacher_id) {
        $sql .= " AND t.teacher_id = ?";
        $params[] = $teacher_id;
    }
    $sql .= " ORDER BY t.date, t.start_time LIMIT 10";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function get_conflicts_next_week($pdo) {
    // Check for overlapping entries in the next 7 days
    $start = date('Y-m-d');
    $end = date('Y-m-d', strtotime('+7 days'));
    $stmt = $pdo->prepare("SELECT t1.*, t2.id as conflict_id, tc1.name as teacher1, tc2.name as teacher2,
                                  r1.name as room1, r2.name as room2,
                                  c1.name as class1, c2.name as class2
                           FROM timetable t1
                           JOIN timetable t2 ON t1.id < t2.id
                           JOIN teachers tc1 ON t1.teacher_id = tc1.id
                           JOIN teachers tc2 ON t2.teacher_id = tc2.id
                           JOIN rooms r1 ON t1.room_id = r1.id
                           JOIN rooms r2 ON t2.room_id = r2.id
                           JOIN student_classes c1 ON t1.class_id = c1.id
                           JOIN student_classes c2 ON t2.class_id = c2.id
                           WHERE t1.date BETWEEN ? AND ?
                             AND t2.date BETWEEN ? AND ?
                             AND (
                               (t1.teacher_id = t2.teacher_id) OR
                               (t1.room_id = t2.room_id) OR
                               (t1.class_id = t2.class_id)
                             )
                             AND (
                               (t1.start_time < t2.end_time AND t1.end_time > t2.start_time)
                             )
                           LIMIT 20");
    $stmt->execute([$start, $end, $start, $end]);
    return $stmt->fetchAll();
}