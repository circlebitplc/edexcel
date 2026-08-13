<?php
// Include database connection (adjust path if needed)
require_once __DIR__ . '/../config/database.php';   // expects $pdo object

$teacher_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$teacher_id) {
    die('Teacher ID required.');
}

// Fetch teacher details
$stmt = $pdo->prepare("SELECT id, name, email, phone FROM teachers WHERE id = ? AND deleted_at IS NULL");
$stmt->execute([$teacher_id]);
$teacher = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$teacher) {
    die('Teacher not found.');
}

// Fetch subjects
$stmt = $pdo->prepare("SELECT s.id, s.name FROM subjects s JOIN teacher_subjects ts ON s.id = ts.subject_id WHERE ts.teacher_id = ?");
$stmt->execute([$teacher_id]);
$subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --- Current week range (Monday to Sunday) ---
$monday = date('Y-m-d', strtotime('monday this week'));
$sunday = date('Y-m-d', strtotime('sunday this week'));

// Fetch timetable for the current week with WhatsApp group links
$sql = "
    SELECT
        t.id,
        t.date,
        t.start_time,
        t.end_time,
        r.name AS room_name,
        c.name AS class_name,
        sub.name AS subject_name,
        COALESCE(cw_specific.whatsapp_link, cw_general.whatsapp_link) AS whatsapp_link
    FROM timetable t
    JOIN student_classes c ON t.class_id = c.id
    JOIN subjects sub ON t.subject_id = sub.id
    JOIN rooms r ON t.room_id = r.id
    LEFT JOIN class_teacher_whatsapp cw_specific
        ON cw_specific.class_id = t.class_id
        AND cw_specific.teacher_id = t.teacher_id
        AND cw_specific.subject_id = t.subject_id
    LEFT JOIN class_teacher_whatsapp cw_general
        ON cw_general.class_id = t.class_id
        AND cw_general.teacher_id = t.teacher_id
        AND cw_general.subject_id IS NULL
    WHERE t.teacher_id = ?
      AND t.deleted_at IS NULL
      AND t.date BETWEEN ? AND ?
    ORDER BY WEEKDAY(t.date) ASC, t.start_time ASC
";
$stmt = $pdo->prepare($sql);
$stmt->execute([$teacher_id, $monday, $sunday]);
$timetable = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Teacher Profile - <?= htmlspecialchars($teacher['name']) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<style>
:root{--page-bg:#f5f7fc;--surface:#fff;--surface-soft:#f6f8fd;--text:#172033;--muted:#6b7280;--primary:#5161ce;--primary-dark:#4050b7;--accent:#f8b400;--border:rgba(23,32,51,.09);--shadow:0 18px 45px rgba(23,32,51,.10);--shadow-xs:0 5px 18px rgba(23,32,51,.06);--radius:18px}
[data-bs-theme=dark]{--page-bg:#10131b;--surface:#191e2b;--surface-soft:#222838;--text:#f5f7fb;--muted:#aeb8cb;--primary:#6878e3;--primary-dark:#5364d1;--border:rgba(255,255,255,.08);--shadow:0 18px 45px rgba(0,0,0,.3);--shadow-xs:0 5px 18px rgba(0,0,0,.2)}
*{box-sizing:border-box}body{margin:0;min-height:100vh;background:linear-gradient(135deg,rgba(248,180,0,.07),transparent 28%),linear-gradient(225deg,rgba(81,97,206,.08),transparent 34%),var(--page-bg);color:var(--text);font-family:Inter,system-ui,sans-serif;line-height:1.6}.shell{max-width:1120px;margin:auto;padding:28px 18px 48px}.topbar{display:flex;justify-content:space-between;align-items:center;margin-bottom:18px;gap:10px}.brand{font-weight:800;color:var(--text);text-decoration:none}.back{display:inline-flex;align-items:center;gap:7px;padding:8px 12px;border-radius:10px;background:var(--surface);border:1px solid var(--border);color:var(--text);text-decoration:none;font-size:.82rem;font-weight:700;box-shadow:var(--shadow-xs)}.profile{background:var(--surface);border:1px solid var(--border);border-radius:24px;box-shadow:var(--shadow);padding:28px;display:flex;align-items:center;gap:24px;margin-bottom:25px}.avatar{width:138px;height:138px;border-radius:50%;overflow:hidden;flex:none;background:var(--surface-soft);border:5px solid var(--surface-soft);box-shadow:var(--shadow-xs);display:grid;place-items:center}.avatar img{width:100%;height:100%;object-fit:cover}.avatar span{font-size:2rem;font-weight:800;color:var(--primary)}.info{min-width:0}.info h1{margin:0 0 3px;font-size:clamp(1.55rem,3vw,2.25rem);font-weight:850;line-height:1.2}.role{color:var(--muted);font-size:.88rem;margin-bottom:12px}.detail{display:flex;gap:8px;align-items:center;margin:5px 0;color:var(--muted);font-size:.86rem}.detail strong{color:var(--text);min-width:70px}.detail a{color:var(--primary);text-decoration:none}.tags{display:flex;flex-wrap:wrap;gap:6px;margin-top:12px}.tag{background:rgba(81,97,206,.09);color:var(--primary);border:1px solid rgba(81,97,206,.12);border-radius:999px;padding:4px 9px;font-size:.7rem;font-weight:700}.tag.empty{color:var(--muted);background:var(--surface-soft);border-color:var(--border)}.section-head{display:flex;align-items:end;justify-content:space-between;gap:12px;margin:0 0 12px}.section-head h2{font-size:1.15rem;font-weight:800;margin:0}.section-head span{color:var(--muted);font-size:.78rem}.week{background:var(--surface);border:1px solid var(--border);border-radius:18px;box-shadow:var(--shadow-xs);overflow:hidden}.table-scroll{overflow-x:auto}.week table{width:100%;min-width:760px;border-collapse:collapse}.week th{background:var(--surface-soft);color:var(--muted);padding:12px 15px;font-size:.68rem;text-transform:uppercase;letter-spacing:.05em;text-align:left;border-bottom:1px solid var(--border)}.week td{padding:13px 15px;border-bottom:1px solid var(--border);font-size:.83rem;vertical-align:middle}.week tbody tr:hover{background:rgba(81,97,206,.035)}.week tbody tr:last-child td{border-bottom:0}.whatsapp{display:inline-flex;align-items:center;gap:5px;background:#25d366;color:#fff;text-decoration:none;border-radius:999px;padding:6px 11px;font-size:.7rem;font-weight:700}.muted{color:var(--muted)}.empty{padding:40px 20px;text-align:center;color:var(--muted)}@media(max-width:640px){.shell{padding:18px 12px 35px}.profile{padding:22px 16px;flex-direction:column;text-align:center}.avatar{width:112px;height:112px}.detail{justify-content:center}.tags{justify-content:center}.topbar{margin-bottom:12px}}
</style>
</head>
<body>
<div class="shell">
  <div class="topbar"><a class="brand" href="<?= htmlspecialchars(rtrim(dirname($_SERVER['SCRIPT_NAME']), '/')) ?>/index.php"><i class="bi bi-mortarboard-fill"></i> Teacher Directory</a><a class="back" href="index.php"><i class="bi bi-arrow-left"></i> Back</a></div>
  <section class="profile">
    <?php
      $photoFile = '';
      foreach (['png','jpg','jpeg','webp','gif'] as $ext) {
        $candidate = __DIR__ . '/../assets/images/teachers/' . $teacher['id'] . '.' . $ext;
        if (file_exists($candidate)) { $photoFile = $teacher['id'] . '.' . $ext; break; }
      }
      $photoUrl = $photoFile ? '../assets/images/teachers/' . $photoFile : '';
      $parts = preg_split('/\s+/', trim($teacher['name']));
      $initials = strtoupper(substr($parts[0] ?? '',0,1) . (count($parts)>1 ? substr(end($parts),0,1) : ''));
    ?>
    <div class="avatar"><?php if($photoUrl): ?><img src="<?= htmlspecialchars($photoUrl) ?>" alt="<?= htmlspecialchars($teacher['name']) ?>"><?php else: ?><span><?= htmlspecialchars($initials ?: '?') ?></span><?php endif; ?></div>
    <div class="info">
      <h1><?= htmlspecialchars($teacher['name']) ?></h1><div class="role">Teacher</div>
      <?php if(!empty($teacher['email'])): ?><div class="detail"><strong><i class="bi bi-envelope"></i> Email</strong><a href="mailto:<?= htmlspecialchars($teacher['email']) ?>"><?= htmlspecialchars($teacher['email']) ?></a></div><?php endif; ?>
      <?php if(!empty($teacher['phone'])): ?><div class="detail"><strong><i class="bi bi-whatsapp"></i> WhatsApp</strong><span><?= htmlspecialchars($teacher['phone']) ?></span></div><?php endif; ?>
      <div class="tags"><?php if($subjects): foreach($subjects as $subject): ?><span class="tag"><?= htmlspecialchars($subject['name']) ?></span><?php endforeach; else: ?><span class="tag empty">No subjects assigned</span><?php endif; ?></div>
    </div>
  </section>
  <div class="section-head"><h2><i class="bi bi-calendar-week"></i> This Week's Classes</h2><span><?= date('d M',strtotime($monday)) ?> – <?= date('d M',strtotime($sunday)) ?></span></div>
  <section class="week">
  <?php if($timetable): ?><div class="table-scroll"><table><thead><tr><th>Day</th><th>Time</th><th>Subject</th><th>Class</th><th>Room</th><th>WhatsApp</th></tr></thead><tbody>
  <?php foreach($timetable as $row): ?><tr><td><strong><?= date('l',strtotime($row['date'])) ?></strong></td><td><?= date('H:i',strtotime($row['start_time'])) ?> – <?= date('H:i',strtotime($row['end_time'])) ?></td><td><?= htmlspecialchars($row['subject_name']) ?></td><td><?= htmlspecialchars($row['class_name']) ?></td><td><?= htmlspecialchars($row['room_name']) ?></td><td><?php if($row['whatsapp_link']): ?><a class="whatsapp" href="<?= htmlspecialchars($row['whatsapp_link']) ?>" target="_blank" rel="noopener"><i class="bi bi-whatsapp"></i> Join</a><?php else: ?><span class="muted">—</span><?php endif; ?></td></tr><?php endforeach; ?>
  </tbody></table></div><?php else: ?><div class="empty"><i class="bi bi-calendar-x fs-2 d-block mb-2"></i>No classes scheduled for this week.</div><?php endif; ?>
  </section>
</div>
</body></html>
