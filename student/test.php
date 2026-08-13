<?php
// diagnostic script – check timetable data

require_once __DIR__ . '/../config/database.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

$today = date('Y-m-d');
$weekStart = date('Y-m-d', strtotime('monday this week'));
$weekEnd   = date('Y-m-d', strtotime('sunday this week'));

echo "<h2>Diagnostic: Timetable for week $weekStart – $weekEnd</h2>";

$sql = "SELECT t.date, t.start_time, t.end_time,
               tc.name AS teacher_name,
               s.name AS subject_name,
               c.name AS class_name,
               r.name AS room_name
        FROM timetable t
        JOIN teachers tc ON t.teacher_id = tc.id
        JOIN subjects s ON t.subject_id = s.id
        JOIN student_classes c ON t.class_id = c.id
        JOIN rooms r ON t.room_id = r.id
        WHERE t.deleted_at IS NULL
          AND t.date BETWEEN ? AND ?
        ORDER BY t.date, t.start_time";

$stmt = $pdo->prepare($sql);
$stmt->execute([$weekStart, $weekEnd]);
$rows = $stmt->fetchAll();

echo "<p>Found " . count($rows) . " active classes this week.</p>";

if (count($rows) > 0) {
    echo "<table border='1'><tr><th>Date</th><th>Time</th><th>Class</th><th>Subject</th><th>Teacher</th><th>Room</th></tr>";
    foreach ($rows as $r) {
        echo "<tr><td>{$r['date']}</td><td>{$r['start_time']}–{$r['end_time']}</td><td>{$r['class_name']}</td><td>{$r['subject_name']}</td><td>{$r['teacher_name']}</td><td>{$r['room_name']}</td></tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color:red'>No active classes in this week.</p>";
}

// Also list all classes (dropdown)
$classes = $pdo->query("SELECT id, name FROM student_classes WHERE deleted_at IS NULL ORDER BY name")->fetchAll();
echo "<h3>Available Classes (" . count($classes) . ")</h3><ul>";
foreach ($classes as $c) {
    echo "<li>ID {$c['id']}: {$c['name']}</li>";
}
echo "</ul>";

// Check cache directory
$cacheDir = __DIR__ . '/../cache/';
echo "<p>Cache directory: $cacheDir</p>";
if (is_dir($cacheDir)) {
    $files = glob($cacheDir . '*.cache');
    echo "<p>Cache files found: " . count($files) . "</p>";
} else {
    echo "<p style='color:orange'>Cache directory does not exist or is not readable.</p>";
}