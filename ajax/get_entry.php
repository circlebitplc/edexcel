<?php
require_once __DIR__ . '/../config/bootstrap.php';
require_login();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

try {
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) throw new RuntimeException('Invalid lesson ID.');

    $is_admin = is_admin();
    $teacher_id = isset($_SESSION['teacher_id']) ? (int)$_SESSION['teacher_id'] : 0;

    $stmt = $pdo->prepare("SELECT t.*, tc.name AS teacher_name, s.name AS subject_name, c.name AS class_name, r.name AS room_name
                           FROM timetable t
                           LEFT JOIN teachers tc ON tc.id=t.teacher_id AND tc.deleted_at IS NULL
                           LEFT JOIN subjects s ON s.id=t.subject_id AND s.deleted_at IS NULL
                           LEFT JOIN student_classes c ON c.id=t.class_id AND c.deleted_at IS NULL
                           LEFT JOIN rooms r ON r.id=t.room_id AND r.deleted_at IS NULL
                           WHERE t.id=? AND t.deleted_at IS NULL");
    $stmt->execute([$id]);
    $entry = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$entry) throw new RuntimeException('Lesson not found.');

    if (!$is_admin && ((int)$entry['teacher_id'] !== $teacher_id)) {
        throw new RuntimeException('You do not have permission to edit this lesson.');
    }
    if (!empty($entry['is_locked'])) throw new RuntimeException('This lesson is locked and cannot be edited.');

    if ($is_admin) {
        $teachers = $pdo->query("SELECT id,name FROM teachers WHERE deleted_at IS NULL ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $st = $pdo->prepare("SELECT id,name FROM teachers WHERE id=? AND deleted_at IS NULL");
        $st->execute([$teacher_id]);
        $teachers = $st->fetchAll(PDO::FETCH_ASSOC);
    }

    $subjects = $pdo->query("SELECT id,name FROM subjects WHERE deleted_at IS NULL ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
    $classes = $pdo->query("SELECT id,name FROM student_classes WHERE deleted_at IS NULL ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
    $rooms = $pdo->query("SELECT id,name FROM rooms WHERE deleted_at IS NULL ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

    $st = $pdo->prepare("SELECT end_date FROM recurring_schedules
                         WHERE teacher_id=? AND subject_id=? AND class_id=? AND room_id=?
                           AND day_of_week=? AND start_time=? AND end_time=?
                           AND start_date<=? AND end_date>=?
                         ORDER BY id DESC LIMIT 1");
    $day = date('l', strtotime($entry['date']));
    $st->execute([
        $entry['teacher_id'], $entry['subject_id'], $entry['class_id'], $entry['room_id'],
        $day, $entry['start_time'], $entry['end_time'], $entry['date'], $entry['date']
    ]);
    $recurring = $st->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'is_admin' => $is_admin,
        'entry' => $entry,
        'teachers' => $teachers,
        'subjects' => $subjects,
        'classes' => $classes,
        'rooms' => $rooms,
        'has_recurring' => (bool)$recurring,
        'recurring_end_date' => $recurring['end_date'] ?? ''
    ]);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
}
