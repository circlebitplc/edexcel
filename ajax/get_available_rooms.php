<?php
require_once __DIR__ . '/../config/bootstrap.php';
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
require_login();

$day_of_week = $_GET['day_of_week'] ?? '';
$start_time = $_GET['start_time'] ?? '';
$end_time = $_GET['end_time'] ?? '';

if (empty($day_of_week) || empty($start_time) || empty($end_time)) {
    echo json_encode([]);
    exit();
}

// Calculate the next occurrence date for the selected day
$days = ['Monday'=>1,'Tuesday'=>2,'Wednesday'=>3,'Thursday'=>4,'Friday'=>5,'Saturday'=>6,'Sunday'=>7];
$target = $days[$day_of_week] ?? 1;
$today = date('N');
$diff = $target - $today;
if ($diff < 0) $diff += 7;
$date = date('Y-m-d', strtotime("+$diff days"));

// Get all rooms that are available at that time
$sql = "SELECT r.id, r.name, r.capacity 
        FROM rooms r
        WHERE r.deleted_at IS NULL
        AND r.id NOT IN (
            SELECT DISTINCT t.room_id 
            FROM timetable t 
            WHERE t.date = ? 
            AND ((? < t.end_time AND ? > t.start_time))
            AND t.deleted_at IS NULL
        )
        ORDER BY r.name";

$stmt = $pdo->prepare($sql);
$stmt->execute([$date, $start_time, $end_time]);
$rooms = $stmt->fetchAll();

header('Content-Type: application/json');
echo json_encode($rooms);
