<?php
// timetable/reports.php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_admin(); // Only admin can view reports

// Get statistics
$stats = [];

// Total lessons
$stats['total_lessons'] = $pdo->query("SELECT COUNT(*) FROM timetable")->fetchColumn();

// Lessons per teacher
$stats['per_teacher'] = $pdo->query("SELECT tc.name, COUNT(t.id) as count 
                                      FROM teachers tc 
                                      LEFT JOIN timetable t ON tc.id = t.teacher_id 
                                      GROUP BY tc.id 
                                      ORDER BY count DESC")->fetchAll();

// Lessons per subject
$stats['per_subject'] = $pdo->query("SELECT s.name, COUNT(t.id) as count 
                                      FROM subjects s 
                                      LEFT JOIN timetable t ON s.id = t.subject_id 
                                      GROUP BY s.id 
                                      ORDER BY count DESC")->fetchAll();

// Lessons per room
$stats['per_room'] = $pdo->query("SELECT r.name, COUNT(t.id) as count 
                                   FROM rooms r 
                                   LEFT JOIN timetable t ON r.id = t.room_id 
                                   GROUP BY r.id 
                                   ORDER BY count DESC")->fetchAll();

// Lessons per class
$stats['per_class'] = $pdo->query("SELECT c.name, COUNT(t.id) as count 
                                    FROM student_classes c 
                                    LEFT JOIN timetable t ON c.id = t.class_id 
                                    GROUP BY c.id 
                                    ORDER BY count DESC")->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<h1 class="mb-4"><i class="bi bi-bar-chart"></i> Reports & Statistics</h1>

<div class="row">
    <div class="col-md-3 mb-3">
        <div class="card text-white bg-primary">
            <div class="card-body">
                <h5 class="card-title">Total Lessons</h5>
                <p class="card-text display-6"><?= $stats['total_lessons'] ?></p>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="card text-white bg-success">
            <div class="card-body">
                <h5 class="card-title">Teachers</h5>
                <p class="card-text display-6"><?= $pdo->query("SELECT COUNT(*) FROM teachers")->fetchColumn() ?></p>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="card text-white bg-info">
            <div class="card-body">
                <h5 class="card-title">Subjects</h5>
                <p class="card-text display-6"><?= $pdo->query("SELECT COUNT(*) FROM subjects")->fetchColumn() ?></p>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="card text-white bg-warning">
            <div class="card-body">
                <h5 class="card-title">Rooms</h5>
                <p class="card-text display-6"><?= $pdo->query("SELECT COUNT(*) FROM rooms")->fetchColumn() ?></p>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-6 mb-4">
        <div class="card">
            <div class="card-header">Lessons per Teacher</div>
            <div class="card-body">
                <canvas id="teacherChart" height="200"></canvas>
            </div>
        </div>
    </div>
    <div class="col-md-6 mb-4">
        <div class="card">
            <div class="card-header">Lessons per Subject</div>
            <div class="card-body">
                <canvas id="subjectChart" height="200"></canvas>
            </div>
        </div>
    </div>
</div>
<div class="row">
    <div class="col-md-6 mb-4">
        <div class="card">
            <div class="card-header">Lessons per Room</div>
            <div class="card-body">
                <canvas id="roomChart" height="200"></canvas>
            </div>
        </div>
    </div>
    <div class="col-md-6 mb-4">
        <div class="card">
            <div class="card-header">Lessons per Class</div>
            <div class="card-body">
                <canvas id="classChart" height="200"></canvas>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Teacher chart
const teacherData = <?= json_encode(array_map(function($item) { return ['label' => $item['name'], 'value' => (int)$item['count']]; }, $stats['per_teacher'])) ?>;
const teacherCtx = document.getElementById('teacherChart').getContext('2d');
new Chart(teacherCtx, {
    type: 'bar',
    data: {
        labels: teacherData.map(d => d.label),
        datasets: [{
            label: 'Lessons',
            data: teacherData.map(d => d.value),
            backgroundColor: 'rgba(54, 162, 235, 0.6)'
        }]
    },
    options: { responsive: true, maintainAspectRatio: false }
});

// Subject chart
const subjectData = <?= json_encode(array_map(function($item) { return ['label' => $item['name'], 'value' => (int)$item['count']]; }, $stats['per_subject'])) ?>;
const subjectCtx = document.getElementById('subjectChart').getContext('2d');
new Chart(subjectCtx, {
    type: 'bar',
    data: {
        labels: subjectData.map(d => d.label),
        datasets: [{
            label: 'Lessons',
            data: subjectData.map(d => d.value),
            backgroundColor: 'rgba(255, 99, 132, 0.6)'
        }]
    },
    options: { responsive: true, maintainAspectRatio: false }
});

// Room chart
const roomData = <?= json_encode(array_map(function($item) { return ['label' => $item['name'], 'value' => (int)$item['count']]; }, $stats['per_room'])) ?>;
const roomCtx = document.getElementById('roomChart').getContext('2d');
new Chart(roomCtx, {
    type: 'bar',
    data: {
        labels: roomData.map(d => d.label),
        datasets: [{
            label: 'Lessons',
            data: roomData.map(d => d.value),
            backgroundColor: 'rgba(75, 192, 192, 0.6)'
        }]
    },
    options: { responsive: true, maintainAspectRatio: false }
});

// Class chart
const classData = <?= json_encode(array_map(function($item) { return ['label' => $item['name'], 'value' => (int)$item['count']]; }, $stats['per_class'])) ?>;
const classCtx = document.getElementById('classChart').getContext('2d');
new Chart(classCtx, {
    type: 'bar',
    data: {
        labels: classData.map(d => d.label),
        datasets: [{
            label: 'Lessons',
            data: classData.map(d => d.value),
            backgroundColor: 'rgba(153, 102, 255, 0.6)'
        }]
    },
    options: { responsive: true, maintainAspectRatio: false }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>