<?php
/*
 * Edexcel College - Student Dashboard Enhancements
 *
 * Read-only student-facing widgets. Data entry can be added later in the
 * admin/teacher portals. Every query is scoped to the logged-in student's
 * enrolled classes where appropriate.
 */

if (!isset($pdo) || !($pdo instanceof PDO)) {
    return;
}

$featureStudentId = (int)($_SESSION['user_id'] ?? 0);
if ($featureStudentId <= 0) {
    return;
}

$featureToday = date('Y-m-d');
$featureNow = date('H:i:s');
$featureWeekEnd = date('Y-m-d', strtotime('+6 days'));

$featureClassIds = [];
try {
    $q = $pdo->prepare("SELECT class_id FROM student_enrollments WHERE student_id = ?");
    $q->execute([$featureStudentId]);
    $featureClassIds = array_values(array_unique(array_map('intval', $q->fetchAll(PDO::FETCH_COLUMN))));
} catch (Throwable $e) {
    $featureClassIds = [];
}

$featurePlaceholders = $featureClassIds ? implode(',', array_fill(0, count($featureClassIds), '?')) : '0';

/* Today's classes */
$featureTodayClasses = [];
if ($featureClassIds) {
    $q = $pdo->prepare("SELECT tt.id, tt.class_id, tt.subject_id, tt.teacher_id,
                               tt.date, tt.start_time, tt.end_time,
                               c.name AS class_name,
                               s.name AS subject_name,
                               tr.name AS teacher_name,
                               r.name AS room_name
                        FROM timetable tt
                        INNER JOIN student_classes c ON c.id = tt.class_id AND c.deleted_at IS NULL
                        INNER JOIN subjects s ON s.id = tt.subject_id AND s.deleted_at IS NULL
                        INNER JOIN teachers tr ON tr.id = tt.teacher_id AND tr.deleted_at IS NULL
                        LEFT JOIN rooms r ON r.id = tt.room_id AND r.deleted_at IS NULL
                        WHERE tt.deleted_at IS NULL
                          AND tt.date = ?
                          AND tt.class_id IN ($featurePlaceholders)
                        ORDER BY tt.start_time ASC, tt.end_time ASC");
    $q->execute(array_merge([$featureToday], $featureClassIds));
    $featureTodayClasses = $q->fetchAll(PDO::FETCH_ASSOC);
}

/* Next class */
$featureNextClass = null;
if ($featureClassIds) {
    $q = $pdo->prepare("SELECT tt.id, tt.class_id, tt.subject_id, tt.teacher_id,
                               tt.date, tt.start_time, tt.end_time,
                               c.name AS class_name,
                               s.name AS subject_name,
                               tr.name AS teacher_name,
                               r.name AS room_name
                        FROM timetable tt
                        INNER JOIN student_classes c ON c.id = tt.class_id AND c.deleted_at IS NULL
                        INNER JOIN subjects s ON s.id = tt.subject_id AND s.deleted_at IS NULL
                        INNER JOIN teachers tr ON tr.id = tt.teacher_id AND tr.deleted_at IS NULL
                        LEFT JOIN rooms r ON r.id = tt.room_id AND r.deleted_at IS NULL
                        WHERE tt.deleted_at IS NULL
                          AND tt.class_id IN ($featurePlaceholders)
                          AND (tt.date > ? OR (tt.date = ? AND tt.start_time >= ?))
                        ORDER BY tt.date ASC, tt.start_time ASC
                        LIMIT 1");
    $q->execute(array_merge($featureClassIds, [$featureToday, $featureToday, $featureNow]));
    $featureNextClass = $q->fetch(PDO::FETCH_ASSOC) ?: null;
}

/* Notifications: global + this student */
$featureNotifications = [];
try {
    $q = $pdo->prepare("SELECT id, title, message, type, link, is_read, created_at
                        FROM student_notifications
                        WHERE (student_id IS NULL OR student_id = ?)
                        ORDER BY created_at DESC
                        LIMIT 8");
    $q->execute([$featureStudentId]);
    $featureNotifications = $q->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $featureNotifications = [];
}

/* Attendance */
$featureAttendance = ['present' => 0, 'absent' => 0, 'late' => 0, 'excused' => 0];
$featureAttendanceTotal = 0;
try {
    $q = $pdo->prepare("SELECT sa.status, COUNT(*) AS total
                        FROM student_attendance sa
                        INNER JOIN timetable tt ON tt.id = sa.timetable_id AND tt.deleted_at IS NULL
                        WHERE sa.student_id = ?
                        GROUP BY sa.status");
    $q->execute([$featureStudentId]);
    foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $status = $row['status'];
        $featureAttendance[$status] = (int)$row['total'];
        $featureAttendanceTotal += (int)$row['total'];
    }
} catch (Throwable $e) {
    $featureAttendanceTotal = 0;
}
$featureAttendancePercent = $featureAttendanceTotal > 0
    ? round((($featureAttendance['present'] + $featureAttendance['late']) / $featureAttendanceTotal) * 100)
    : null;

/* Subject summary from enrolled timetable data */
$featureSubjects = [];
if ($featureClassIds) {
    $q = $pdo->prepare("SELECT s.id AS subject_id, s.name AS subject_name,
                               COUNT(DISTINCT tt.id) AS timetable_count,
                               COUNT(DISTINCT tt.teacher_id) AS teacher_count,
                               GROUP_CONCAT(DISTINCT tr.name ORDER BY tr.name SEPARATOR ', ') AS teacher_names
                        FROM timetable tt
                        INNER JOIN subjects s ON s.id = tt.subject_id AND s.deleted_at IS NULL
                        INNER JOIN teachers tr ON tr.id = tt.teacher_id AND tr.deleted_at IS NULL
                        WHERE tt.deleted_at IS NULL
                          AND tt.class_id IN ($featurePlaceholders)
                        GROUP BY s.id, s.name
                        ORDER BY s.name ASC");
    $q->execute($featureClassIds);
    $featureSubjects = $q->fetchAll(PDO::FETCH_ASSOC);
}

/* Materials */
$featureMaterials = [];
try {
    $params = $featureClassIds ?: [0];
    $q = $pdo->prepare("SELECT m.id, m.title, m.description, m.file_url, m.created_at,
                               s.name AS subject_name, tr.name AS teacher_name
                        FROM student_materials m
                        LEFT JOIN subjects s ON s.id = m.subject_id
                        LEFT JOIN teachers tr ON tr.id = m.teacher_id
                        WHERE (m.class_id IS NULL OR m.class_id IN ($featurePlaceholders))
                        ORDER BY m.created_at DESC
                        LIMIT 6");
    $q->execute($params);
    $featureMaterials = $q->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $featureMaterials = [];
}

/* Homework */
$featureHomework = [];
try {
    $q = $pdo->prepare("SELECT h.id, h.title, h.description, h.due_date, h.link, h.created_at,
                               s.name AS subject_name, tr.name AS teacher_name
                        FROM student_homework h
                        LEFT JOIN subjects s ON s.id = h.subject_id
                        LEFT JOIN teachers tr ON tr.id = h.teacher_id
                        WHERE (h.class_id IS NULL OR h.class_id IN ($featurePlaceholders))
                        ORDER BY (h.due_date IS NULL), h.due_date ASC, h.created_at DESC
                        LIMIT 8");
    $q->execute($params);
    $featureHomework = $q->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $featureHomework = [];
}

/* Exams */
$featureExams = [];
try {
    $q = $pdo->prepare("SELECT e.id, e.title, e.exam_date, e.exam_time, e.location,
                               s.name AS subject_name
                        FROM student_exams e
                        LEFT JOIN subjects s ON s.id = e.subject_id
                        WHERE (e.class_id IS NULL OR e.class_id IN ($featurePlaceholders))
                          AND e.exam_date >= ?
                        ORDER BY e.exam_date ASC, e.exam_time ASC
                        LIMIT 5");
    $q->execute(array_merge($params, [$featureToday]));
    $featureExams = $q->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $featureExams = [];
}

/* College calendar events */
$featureEvents = [];
try {
    $q = $pdo->prepare("SELECT id, title, event_date, start_time, end_time, description
                        FROM student_events
                        WHERE event_date BETWEEN ? AND ?
                        ORDER BY event_date ASC, start_time ASC
                        LIMIT 10");
    $q->execute([$featureToday, $featureWeekEnd]);
    $featureEvents = $q->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $featureEvents = [];
}

/* Progress: latest record per subject */
$featureProgress = [];
try {
    $q = $pdo->prepare("SELECT sp.subject_id, s.name AS subject_name,
                               sp.score, sp.max_score, sp.metric, sp.recorded_at
                        FROM student_progress sp
                        LEFT JOIN subjects s ON s.id = sp.subject_id
                        INNER JOIN (
                            SELECT subject_id, MAX(recorded_at) AS latest_date
                            FROM student_progress
                            WHERE student_id = ?
                              AND published = 1
                            GROUP BY subject_id
                        ) latest ON latest.subject_id = sp.subject_id
                                 AND latest.latest_date = sp.recorded_at
                        WHERE sp.student_id = ?
                          AND sp.published = 1
                        ORDER BY s.name ASC");
    $q->execute([$featureStudentId, $featureStudentId]);
    $featureProgress = $q->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $featureProgress = [];
}

$featureUnreadCount = 0;
foreach ($featureNotifications as $n) {
    if (!(int)$n['is_read']) $featureUnreadCount++;
}

$studentFeatureEsc = static function ($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
};
?>



    <div class="sde-grid sde-grid-top">

        <article class="sde-card sde-next-card">
            <div class="sde-card-head">
                <div>
                    <span class="sde-label"><i class="bi bi-lightning-charge-fill"></i> NEXT CLASS</span>
                    <h3><?= $featureNextClass ? $studentFeatureEsc($featureNextClass['subject_name']) : 'No upcoming class' ?></h3>
                </div>
                <span class="sde-icon"><i class="bi bi-alarm"></i></span>
            </div>
            <?php if ($featureNextClass): ?>
                <div class="sde-next-meta">
                    <strong><?= $studentFeatureEsc($featureNextClass['teacher_name']) ?></strong>
                    <span><?= date('D, d M Y', strtotime($featureNextClass['date'])) ?> · <?= date('h:i A', strtotime($featureNextClass['start_time'])) ?></span>
                    <?php if (!empty($featureNextClass['room_name'])): ?><span><i class="bi bi-door-open"></i> <?= $studentFeatureEsc($featureNextClass['room_name']) ?></span><?php endif; ?>
                </div>
                <div class="sde-countdown" data-next-class="<?= $studentFeatureEsc($featureNextClass['date'].' '.$featureNextClass['start_time']) ?>">--:--:--</div>
                <a href="#student-timetable" class="sde-inline-link">View timetable <i class="bi bi-arrow-right"></i></a>
            <?php else: ?>
                <p class="sde-empty-text">There are no upcoming classes in your enrolled classes.</p>
            <?php endif; ?>
        </article>

        <article class="sde-card">
            <div class="sde-card-head">
                <div><span class="sde-label"><i class="bi bi-calendar-day"></i> TODAY</span><h3><?= count($featureTodayClasses) ?> <?= count($featureTodayClasses) === 1 ? 'class' : 'classes' ?></h3></div>
                <span class="sde-icon"><i class="bi bi-calendar-check"></i></span>
            </div>
            <?php if ($featureTodayClasses): ?>
                <div class="sde-list compact">
                    <?php foreach (array_slice($featureTodayClasses, 0, 4) as $c): ?>
                        <div class="sde-list-row">
                            <span class="sde-time"><?= date('h:i A', strtotime($c['start_time'])) ?></span>
                            <div><strong><?= $studentFeatureEsc($c['subject_name']) ?></strong><small><?= $studentFeatureEsc($c['teacher_name']) ?></small></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?><p class="sde-empty-text">No classes scheduled for today.</p><?php endif; ?>
        </article>

        <article class="sde-card">
            <div class="sde-card-head">
                <div><span class="sde-label"><i class="bi bi-bell"></i> NOTIFICATIONS</span><h3><?= $featureUnreadCount ?> unread</h3></div>
                <span class="sde-icon"><i class="bi bi-bell-fill"></i></span>
            </div>
            <?php if ($featureNotifications): ?>
                <div class="sde-list compact">
                    <?php foreach (array_slice($featureNotifications, 0, 4) as $n): ?>
                        <div class="sde-list-row sde-notification-row <?= !(int)$n['is_read'] ? 'unread' : '' ?>">
                            <span class="sde-notification-dot"></span>
                            <div><strong><?= $studentFeatureEsc($n['title']) ?></strong><small><?= $studentFeatureEsc($n['message']) ?></small></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?><p class="sde-empty-text">You're all caught up. No notifications yet.</p><?php endif; ?>
        </article>

    </div>

    <div class="sde-grid sde-grid-two">
        <article class="sde-card">
            <div class="sde-card-head"><div><span class="sde-label"><i class="bi bi-bar-chart"></i> ATTENDANCE</span><h3><?= $featureAttendancePercent !== null ? $featureAttendancePercent.'%' : 'Not available' ?></h3></div><span class="sde-icon"><i class="bi bi-check2-circle"></i></span></div>
            <?php if ($featureAttendancePercent !== null): ?>
                <div class="sde-progress"><span style="width:<?= max(0,min(100,$featureAttendancePercent)) ?>%"></span></div>
                <div class="sde-attendance-stats"><span><b><?= $featureAttendance['present'] ?></b> Present</span><span><b><?= $featureAttendance['late'] ?></b> Late</span><span><b><?= $featureAttendance['absent'] ?></b> Absent</span><span><b><?= $featureAttendance['excused'] ?></b> Excused</span></div>
            <?php else: ?><p class="sde-empty-text">Attendance will appear here once your teachers start recording attendance.</p><?php endif; ?>
        </article>

        <article class="sde-card">
            <div class="sde-card-head"><div><span class="sde-label"><i class="bi bi-journal-bookmark"></i> MY SUBJECTS</span><h3><?= count($featureSubjects) ?> subjects</h3></div><span class="sde-icon"><i class="bi bi-book"></i></span></div>
            <?php if ($featureSubjects): ?>
                <div class="sde-subject-grid">
                    <?php foreach ($featureSubjects as $subject): ?>
                        <div class="sde-subject-item"><strong><?= $studentFeatureEsc($subject['subject_name']) ?></strong><small><?= (int)$subject['timetable_count'] ?> timetable entries · <?= $studentFeatureEsc($subject['teacher_names']) ?></small></div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?><p class="sde-empty-text">Your subjects will appear after you join classes.</p><?php endif; ?>
        </article>
    </div>

    <div class="sde-grid sde-grid-two">
        <article class="sde-card">
            <div class="sde-card-head"><div><span class="sde-label"><i class="bi bi-folder2-open"></i> STUDY MATERIALS</span><h3>Recent resources</h3></div><span class="sde-icon"><i class="bi bi-file-earmark-text"></i></span></div>
            <?php if ($featureMaterials): ?>
                <div class="sde-resource-list">
                    <?php foreach ($featureMaterials as $m): ?>
                        <div class="sde-resource"><span class="sde-resource-icon"><i class="bi bi-file-earmark-arrow-down"></i></span><div><strong><?= $studentFeatureEsc($m['title']) ?></strong><small><?= $studentFeatureEsc($m['subject_name'] ?: 'College resource') ?><?= $m['teacher_name'] ? ' · '.$studentFeatureEsc($m['teacher_name']) : '' ?></small></div><?php if (!empty($m['file_url'])): ?><a href="<?= $studentFeatureEsc($m['file_url']) ?>" target="_blank" rel="noopener" class="sde-open"><i class="bi bi-box-arrow-up-right"></i></a><?php endif; ?></div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?><p class="sde-empty-text">Study materials uploaded by your teachers will appear here.</p><?php endif; ?>
        </article>

        <article class="sde-card">
            <div class="sde-card-head"><div><span class="sde-label"><i class="bi bi-pencil-square"></i> HOMEWORK</span><h3>Upcoming work</h3></div><span class="sde-icon"><i class="bi bi-clipboard-check"></i></span></div>
            <?php if ($featureHomework): ?>
                <div class="sde-resource-list">
                    <?php foreach ($featureHomework as $h): ?>
                        <div class="sde-resource"><span class="sde-resource-icon"><i class="bi bi-pencil-square"></i></span><div><strong><?= $studentFeatureEsc($h['title']) ?></strong><small><?= $studentFeatureEsc($h['subject_name'] ?: 'College') ?> · <?= $h['due_date'] ? 'Due '.date('d M Y', strtotime($h['due_date'])) : 'No due date' ?></small></div><?php if (!empty($h['link'])): ?><a href="<?= $studentFeatureEsc($h['link']) ?>" target="_blank" rel="noopener" class="sde-open"><i class="bi bi-arrow-up-right"></i></a><?php endif; ?></div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?><p class="sde-empty-text">Homework published by your teachers will appear here.</p><?php endif; ?>
        </article>
    </div>

    <div class="sde-grid sde-grid-three">
        <article class="sde-card">
            <div class="sde-card-head"><div><span class="sde-label"><i class="bi bi-trophy"></i> PROGRESS</span><h3>Latest results</h3></div><span class="sde-icon"><i class="bi bi-graph-up-arrow"></i></span></div>
            <?php if ($featureProgress): ?>
                <div class="sde-progress-list">
                    <?php foreach ($featureProgress as $p): $pct = $p['max_score'] > 0 ? round(($p['score']/$p['max_score'])*100) : 0; ?>
                        <div><div class="sde-progress-title"><strong><?= $studentFeatureEsc($p['subject_name'] ?: 'Subject') ?></strong><span><?= $pct ?>%</span></div><div class="sde-progress"><span style="width:<?= max(0,min(100,$pct)) ?>%"></span></div><small><?= $studentFeatureEsc($p['metric']) ?> · <?= $studentFeatureEsc($p['recorded_at']) ?></small></div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?><p class="sde-empty-text">Your latest test and assessment progress will appear here.</p><?php endif; ?>
        </article>

        <article class="sde-card">
            <div class="sde-card-head"><div><span class="sde-label"><i class="bi bi-mortarboard"></i> EXAMS</span><h3>Upcoming exams</h3></div><span class="sde-icon"><i class="bi bi-hourglass-split"></i></span></div>
            <?php if ($featureExams): ?>
                <div class="sde-resource-list">
                    <?php foreach (array_slice($featureExams,0,4) as $e): $days=max(0,(int)floor((strtotime($e['exam_date'])-strtotime($featureToday))/86400)); ?>
                        <div class="sde-exam"><div class="sde-exam-days"><strong><?= $days ?></strong><small>days</small></div><div><strong><?= $studentFeatureEsc($e['title']) ?></strong><small><?= $studentFeatureEsc($e['subject_name'] ?: 'Exam') ?> · <?= date('d M Y',strtotime($e['exam_date'])) ?></small></div></div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?><p class="sde-empty-text">Upcoming exams will appear here when the college publishes them.</p><?php endif; ?>
        </article>

        <article class="sde-card">
            <div class="sde-card-head"><div><span class="sde-label"><i class="bi bi-calendar-event"></i> CALENDAR</span><h3>Next 7 days</h3></div><span class="sde-icon"><i class="bi bi-calendar3"></i></span></div>
            <?php if ($featureEvents): ?>
                <div class="sde-list compact">
                    <?php foreach (array_slice($featureEvents,0,5) as $ev): ?><div class="sde-list-row"><span class="sde-date-box"><?= date('d',strtotime($ev['event_date'])) ?><small><?= date('M',strtotime($ev['event_date'])) ?></small></span><div><strong><?= $studentFeatureEsc($ev['title']) ?></strong><small><?= $ev['start_time'] ? date('h:i A',strtotime($ev['start_time'])) : 'All day' ?></small></div></div><?php endforeach; ?>
                </div>
            <?php else: ?><p class="sde-empty-text">College events and revision sessions will appear here.</p><?php endif; ?>
        </article>
    </div>

</div>
