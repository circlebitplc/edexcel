<?php
// timetable/export_pdf.php
// Requires dompdf/dompdf: composer require dompdf/dompdf
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_staff();

// Dompdf is optional; if Composer dependencies are installed, PDF export is used.
$vendorAutoload = __DIR__ . '/../vendor/autoload.php';


$is_admin = is_admin();
$session_teacher_id = (int)($_SESSION['teacher_id'] ?? 0);

$type = $_GET['type'] ?? 'teacher';
$id = (int)($_GET['id'] ?? 0);
$week_start = $_GET['week_start'] ?? date('Y-m-d', strtotime('monday this week'));

$days = [];
for ($i = 0; $i < 7; $i++) {
    $days[] = date('Y-m-d', strtotime($week_start . " +$i days"));
}

$where = ["t.deleted_at IS NULL"];
$params = [];
$title = '';

if (!$is_admin) {
    if ($session_teacher_id <= 0) {
        http_response_code(403);
        exit('Teacher account is not linked.');
    }
    if ($type !== 'teacher' || $id !== $session_teacher_id) {
        http_response_code(403);
        exit('You can only export your own timetable.');
    }
}

if ($type === 'teacher' && $id) {
    $where[] = "t.teacher_id = ?";
    $params[] = $id;
    $stmt = $pdo->prepare("SELECT name FROM teachers WHERE id = ?");
    $stmt->execute([$id]);
    $entity = $stmt->fetch();
    $title = "Teacher: " . ($entity ? $entity['name'] : 'Unknown');
} elseif ($type === 'room' && $id) {
    $where[] = "t.room_id = ?";
    $params[] = $id;
    $stmt = $pdo->prepare("SELECT name FROM rooms WHERE id = ?");
    $stmt->execute([$id]);
    $entity = $stmt->fetch();
    $title = "Room: " . ($entity ? $entity['name'] : 'Unknown');
} elseif ($type === 'class' && $id) {
    $where[] = "t.class_id = ?";
    $params[] = $id;
    $stmt = $pdo->prepare("SELECT name FROM student_classes WHERE id = ?");
    $stmt->execute([$id]);
    $entity = $stmt->fetch();
    $title = "Class: " . ($entity ? $entity['name'] : 'Unknown');
} else {
    die('Invalid selection.');
}

$where[] = "t.date >= ? AND t.date <= ?";
$params[] = $days[0];
$params[] = $days[6];

$sql = "SELECT t.*, 
               tc.name as teacher_name, 
               s.name as subject_name, 
               c.name as class_name, 
               r.name as room_name 
        FROM timetable t
        LEFT JOIN teachers tc ON t.teacher_id = tc.id AND tc.deleted_at IS NULL
        LEFT JOIN subjects s ON t.subject_id = s.id AND s.deleted_at IS NULL
        LEFT JOIN student_classes c ON t.class_id = c.id AND c.deleted_at IS NULL
        LEFT JOIN rooms r ON t.room_id = r.id AND r.deleted_at IS NULL
        WHERE " . implode(" AND ", $where) . "
        ORDER BY t.date, t.start_time";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$entries = $stmt->fetchAll();

$start_h = 8;
$end_h = 18;

foreach ($entries as $e) {
    $sh = (int)substr((string)$e['start_time'], 0, 2);
    $eh = (int)substr((string)$e['end_time'], 0, 2);
    if ((int)substr((string)$e['end_time'], 3, 2) > 0) {
        $eh += 1;
    }
    if ($sh < $start_h) {
        $start_h = $sh;
    }
    if ($eh > $end_h) {
        $end_h = $eh;
    }
}
$start_h = max(0, min($start_h, 8));
$end_h   = min(24, max($end_h, 18));

$time_slots = [];
for ($h = $start_h; $h < $end_h; $h++) {
    $time_slots[] = sprintf('%02d:00', $h);
}

$grid = [];
foreach ($entries as $e) {
    $date = $e['date'];
    $sh = (int)substr((string)$e['start_time'], 0, 2);
    $start_slot = sprintf('%02d:00', $sh);
    $grid[$date][$start_slot] = $e;
}

// Build HTML for PDF
ob_start();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Weekly Timetable - <?= $title ?></title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #333; padding: 6px; text-align: center; }
        th { background: #f0f0f0; }
        .free { color: #999; }
        .lesson { background: #d9edf7; padding: 2px; }
        .title { font-size: 16px; font-weight: bold; margin-bottom: 10px; }
    </style>
</head>
<body>
    <div class="title">Edexcel College - Weekly Timetable</div>
    <p><?= $title ?> (Week of <?= date('d M Y', strtotime($week_start)) ?>)</p>
    <table>
        <thead>
            <tr>
                <th>Time</th>
                <?php foreach ($days as $d): ?>
                    <th><?= date('D d M', strtotime($d)) ?></th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($time_slots as $slot): ?>
                <tr>
                    <td><?= $slot ?> - <?= date('H:i', strtotime($slot . ' +1 hour')) ?></td>
                    <?php foreach ($days as $d): ?>
                        <td>
                            <?php if (isset($grid[$d][$slot])): ?>
                                <?php $e = $grid[$d][$slot]; ?>
                                <div class="lesson">
                                    <div><strong><?= htmlspecialchars($e['subject_name']) ?></strong></div>
                                    <div><small><?= htmlspecialchars($e['teacher_name']) ?></small></div>
                                    <div><small><?= htmlspecialchars($e['room_name']) ?></small></div>
                                    <?php if ($type !== 'class'): ?>
                                        <div><small><?= htmlspecialchars($e['class_name']) ?></small></div>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <span class="free">FREE</span>
                            <?php endif; ?>
                        </td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</body>
</html>
<?php
$html = ob_get_clean();

if (is_file($vendorAutoload)) {
    require_once $vendorAutoload;
    if (class_exists('Dompdf\\Dompdf') && class_exists('Dompdf\\Options')) {
        $options = new \Dompdf\Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $dompdf = new \Dompdf\Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();
        $dompdf->stream("timetable_".$type."_".$id."_".date('Y-m-d').".pdf", ['Attachment' => true]);
        exit;
    }
}
// Safe fallback: render a print-ready HTML document so the browser can Save as PDF.
header('Content-Type: text/html; charset=UTF-8');
echo '<!doctype html><html><head><meta charset="utf-8"><title>Printable Timetable</title><style>@media print{.print-btn{display:none!important}}body{font-family:Arial,sans-serif;margin:24px}button{padding:10px 16px;margin-bottom:18px}table{width:100%;border-collapse:collapse}th,td{border:1px solid #333;padding:6px;text-align:center}th{background:#eee}</style></head><body><button class="print-btn" onclick="window.print()">Print / Save as PDF</button>'.$html.'</body></html>';
exit;