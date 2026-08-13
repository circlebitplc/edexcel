<?php
// student/dashboard.php
require_once '../config/database.php';
require_once '../config/auth.php';
require_login();

if ($_SESSION['role'] !== 'student') {
    die('Access denied. Students only.');
}

$student_id = $_SESSION['user_id'];

// Get student's enrolled classes (with whatsapp_link)
$stmt = $pdo->prepare("SELECT c.id, c.name, c.whatsapp_link FROM student_classes c
                       JOIN student_enrollments se ON c.id = se.class_id
                       WHERE se.student_id = ? AND c.deleted_at IS NULL");
$stmt->execute([$student_id]);
$classes = $stmt->fetchAll();

$selected_class = (int)($_GET['class_id'] ?? 0);
if (count($classes) === 1 && !$selected_class) {
    $selected_class = $classes[0]['id'];
}

$week_start = date('Y-m-d', strtotime('monday this week'));
$week_end = date('Y-m-d', strtotime('sunday this week'));
$today = date('Y-m-d');

// Fetch entries for the week
$entries = [];
if ($selected_class) {
    $sql = "SELECT t.date, t.start_time, t.end_time,
                   tc.name as teacher_name,
                   s.name as subject_name,
                   r.name as room_name
            FROM timetable t
            JOIN teachers tc ON t.teacher_id = tc.id
            JOIN subjects s ON t.subject_id = s.id
            JOIN rooms r ON t.room_id = r.id
            WHERE t.class_id = ? 
            AND t.deleted_at IS NULL
            AND t.date BETWEEN ? AND ?
            ORDER BY t.date, t.start_time";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$selected_class, $week_start, $week_end]);
    $entries = $stmt->fetchAll();
}

// Color palette for subjects
$color_palette = ['#4A6CF7', '#10B981', '#F59E0B', '#EF4444', '#8B5CF6', '#EC4899', '#14B8A6', '#F97316', '#6366F1', '#84CC16'];
$subject_color_map = [];
$color_index = 0;
foreach ($entries as $e) {
    if (!isset($subject_color_map[$e['subject_name']])) {
        $subject_color_map[$e['subject_name']] = $color_palette[$color_index % count($color_palette)];
        $color_index++;
    }
}

include '../includes/header.php';
?>



<div class="student-dashboard">
    <h1><i class="bi bi-person-video"></i> My Timetable</h1>

    <?php if (count($classes) === 0): ?>
        <div class="alert alert-warning">You are not enrolled in any classes. Please contact the admin.</div>
    <?php else: ?>

        <!-- ===== WHATSAPP COMMUNITIES ===== -->
        <?php
        $has_whatsapp = false;
        foreach ($classes as $c) {
            if (!empty($c['whatsapp_link'])) { $has_whatsapp = true; break; }
        }
        if ($has_whatsapp): ?>
        <div class="mb-4">
            <h5><i class="bi bi-whatsapp text-success"></i> Join Your Class Communities</h5>
            <div class="row">
                <?php foreach ($classes as $c): 
                    if (empty($c['whatsapp_link'])) continue;
                ?>
                    <div class="col-md-4 mb-2">
                        <div class="whatsapp-card d-flex align-items-center justify-content-between">
                            <div>
                                <iclass="bi bi-whatsapp text-success inline-css-06d51caa1f"></i>
                                <strong><?= htmlspecialchars($c['name']) ?></strong>
                            </div>
                            <a href="<?= htmlspecialchars($c['whatsapp_link']) ?>" target="_blank" class="btn btn-sm btn-success">
                                <i class="bi bi-box-arrow-up-right"></i> Join
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <small class="text-muted">Join the WhatsApp Community to stay updated with announcements and discussions.</small>
        </div>
        <?php endif; ?>

        <!-- ===== CLASS SELECTOR ===== -->
        <div class="row mb-3">
            <div class="col-md-4">
                <label for="classSelect" class="form-label">Select Class</label>
                <select id="classSelect" class="form-select" onchange="window.location.href='?class_id='+this.value">
                    <?php foreach ($classes as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= ($selected_class == $c['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($c['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4 d-flex align-items-center">
                <span class="text-muted">Week of <?= date('d M Y', strtotime($week_start)) ?> – <?= date('d M Y', strtotime($week_end)) ?></span>
            </div>
        </div>

        <?php if ($selected_class && count($entries) === 0): ?>
            <div class="alert alert-info">No classes scheduled for this week. Enjoy your break! 🎉</div>
        <?php elseif ($selected_class): ?>
            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead class="table-dark">
                        <tr>
                            <th>Day</th>
                            <th>Time</th>
                            <th>Teacher</th>
                            <th>Subject</th>
                            <th>Room</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($entries as $e): ?>
                            <tr>
                                <td><?= date('l, d M Y', strtotime($e['date'])) ?></td>
                                <td><?= date('h:i A', strtotime($e['start_time'])) ?> - <?= date('h:i A', strtotime($e['end_time'])) ?></td>
                                <td><?= htmlspecialchars($e['teacher_name']) ?></td>
                                <td><?= htmlspecialchars($e['subject_name']) ?></td>
                                <td><?= htmlspecialchars($e['room_name']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>