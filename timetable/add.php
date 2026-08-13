<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/notifications.php';
require_login();

$is_admin = is_admin();
$teacher_id = $_SESSION['teacher_id'] ?? null;

// Build teacher list
if ($is_admin) {
    $teachers = $pdo->query("SELECT id, name FROM teachers WHERE deleted_at IS NULL ORDER BY name")->fetchAll();
} else {
    $teachersStmt = $pdo->prepare("SELECT id, name FROM teachers WHERE id = ? AND deleted_at IS NULL");
    $teachersStmt->execute([$teacher_id]);
    $teachers = $teachersStmt->fetchAll();
}

// Pre-fetch all subjects, classes, rooms for dropdowns
$all_subjects = $pdo->query("SELECT id, name FROM subjects WHERE deleted_at IS NULL ORDER BY name")->fetchAll();
$all_classes = $pdo->query("SELECT id, name FROM student_classes WHERE deleted_at IS NULL ORDER BY name")->fetchAll();
$all_rooms = $pdo->query("SELECT id, name, capacity FROM rooms WHERE deleted_at IS NULL ORDER BY name")->fetchAll();

$error = '';
$success = '';

function get_date_for_day($day_name) {
    $days = ['Monday'=>1, 'Tuesday'=>2, 'Wednesday'=>3, 'Thursday'=>4, 'Friday'=>5, 'Saturday'=>6, 'Sunday'=>7];
    if (!isset($days[$day_name])) {
        return false;
    }
    $target = $days[$day_name];
    $today = date('N');
    $diff = $target - $today;
    if ($diff < 0) $diff += 7;
    return date('Y-m-d', strtotime("+$diff days"));
}

// POST mutations are delegated to TimetableCreateService.
// The page keeps its existing form, validation messages, redirects and
// WhatsApp notification behavior.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';

    if (!verify_csrf_token($csrf_token)) {
        $error = 'Invalid security token.';
    } else {
        try {
            $postedTeacherId = (int)($_POST['teacher_id'] ?? 0);
            $teacher_id = $is_admin ? $postedTeacherId : (int)$teacher_id;

            $subject_id = (int)($_POST['subject_id'] ?? 0);
            $class_id = (int)($_POST['class_id'] ?? 0);
            $room_id = (int)($_POST['room_id'] ?? 0);
            $day_of_week = trim((string)($_POST['day_of_week'] ?? ''));
            $start_time = substr(trim((string)($_POST['start_time'] ?? '')), 0, 5);
            $end_time = substr(trim((string)($_POST['end_time'] ?? '')), 0, 5);
            $student_count = (int)($_POST['student_count'] ?? 0);
            $repeat = isset($_POST['repeat_weekly']);
            $repeat_until = trim((string)($_POST['repeat_until'] ?? ''));

            if (empty($day_of_week)) {
                throw new RuntimeException('Please select a day of the week.');
            }

            $date = get_date_for_day($day_of_week);
            if (!$date) {
                throw new RuntimeException('Invalid day of the week.');
            }

            $data = [
                'teacher_id' => $teacher_id,
                'subject_id' => $subject_id,
                'class_id' => $class_id,
                'room_id' => $room_id,
                'student_count' => $student_count,
                'date' => $date,
                'start_time' => $start_time,
                'end_time' => $end_time,
                'payment_status' => 'pending',
                'payment_date' => null,
                'day_of_week' => $day_of_week,
            ];

            $services = TimetableServiceFactory::services($pdo);

            $new_id = $services['create']->create(
                $data,
                static function (array $newData) use ($is_admin, $teacher_id): void {
                    if (!$is_admin && (int)$newData['teacher_id'] !== (int)$teacher_id) {
                        throw new RuntimeException('You can only create lessons for your own teacher account.');
                    }
                },
                $repeat,
                $repeat_until
            );

            // Notification remains outside the transaction so a WhatsApp/API
            // failure never rolls back the successful timetable insert.
            try {
                $stmt = $pdo->prepare("
                    SELECT
                        s.name AS subject_name,
                        c.name AS class_name,
                        r.name AS room_name
                    FROM timetable t
                    JOIN subjects s ON t.subject_id = s.id
                    JOIN student_classes c ON t.class_id = c.id
                    JOIN rooms r ON t.room_id = r.id
                    WHERE t.id = ?
                ");
                $stmt->execute([$new_id]);
                $timetable_data = $stmt->fetch();

                if ($timetable_data) {
                    $timetable_data['date'] = $date;
                    $timetable_data['start_time'] = $start_time;
                    $timetable_data['end_time'] = $end_time;

                    try {
                        notify_class_change($pdo, $teacher_id, 'add', $timetable_data);
                    } catch (Throwable $notificationError) {
                        error_log(
                            "WhatsApp notification failed for new class (ID $new_id): "
                            . $notificationError->getMessage()
                        );
                    }
                }
            } catch (Throwable $notificationLookupError) {
                error_log(
                    "Could not prepare WhatsApp notification for new class (ID $new_id): "
                    . $notificationLookupError->getMessage()
                );
            }

            $msg = "Entry added for " . date('l, d M Y', strtotime($date));
            if ($repeat) {
                $msg .= " (repeats weekly until $repeat_until).";
            }

            $_SESSION['success'] = $msg;
            header('Location: index.php');
            exit();

        } catch (Throwable $e) {
            $error = $e->getMessage();
            error_log('Timetable add error: ' . $e->getMessage());
        }
    }
}
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<h1><i class="bi bi-plus-circle"></i> Add Timetable Entry</h1>

<div class="alert alert-info">
    <i class="bi bi-info-circle"></i> 
    <strong>Smart form:</strong> Select teacher → subjects filter, then subject → classes filter, then day + time → available rooms filter.
</div>

<?php if ($error): ?>
    <div class="alert alert-danger"><?= $error ?></div>
<?php endif; ?>

<div class="timetable-form"><form id="timetableForm" method="POST">
    <?= csrf_field() ?>
    
    <!-- Row 1: Teacher & Subject -->
    <div class="row">
        <div class="col-md-6">
            <div class="mb-3">
                <label for="teacher_id" class="form-label">Teacher <span class="text-danger">*</span></label>
                <?php if ($is_admin): ?>
                    <select class="form-select" id="teacher_id" name="teacher_id" required>
                        <option value="">Select Teacher</option>
                        <?php foreach ($teachers as $t): ?>
                            <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                <?php else: ?>
                    <input type="hidden" name="teacher_id" value="<?= $teacher_id ?>">
                    <p class="form-control-static"><strong><?= htmlspecialchars($teachers[0]['name'] ?? 'Your Teacher') ?></strong></p>
                <?php endif; ?>
            </div>
        </div>
        <div class="col-md-6">
            <div class="mb-3">
                <label for="subject_id" class="form-label">Subject <span class="text-danger">*</span></label>
                <select class="form-select" id="subject_id" name="subject_id" required>
                    <option value="">Select Subject</option>
                    <?php foreach ($all_subjects as $s): ?>
                        <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>

    <!-- Row 2: Class & Day -->
    <div class="row">
        <div class="col-md-6">
            <div class="mb-3">
                <label for="class_id" class="form-label">Class <span class="text-danger">*</span></label>
                <select class="form-select" id="class_id" name="class_id" required>
                    <option value="">Select Class</option>
                    <?php foreach ($all_classes as $c): ?>
                        <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="col-md-6">
            <div class="mb-3">
                <label for="day_of_week" class="form-label">Day of Week <span class="text-danger">*</span></label>
                <select class="form-select" id="day_of_week" name="day_of_week" required>
                    <option value="">Select Day</option>
                    <option value="Monday">Monday</option>
                    <option value="Tuesday">Tuesday</option>
                    <option value="Wednesday">Wednesday</option>
                    <option value="Thursday">Thursday</option>
                    <option value="Friday">Friday</option>
                    <option value="Saturday">Saturday</option>
                    <option value="Sunday">Sunday</option>
                </select>
            </div>
        </div>
    </div>

    <!-- Row 3: Start Time, End Time, Room -->
    <div class="row">
        <div class="col-md-4">
            <div class="mb-3">
                <label for="start_time" class="form-label">Start Time <span class="text-danger">*</span></label>
                <input type="time" class="form-control" id="start_time" name="start_time" required>
            </div>
        </div>
        <div class="col-md-4">
            <div class="mb-3">
                <label for="end_time" class="form-label">End Time <span class="text-danger">*</span></label>
                <input type="time" class="form-control" id="end_time" name="end_time" required>
            </div>
        </div>
        <div class="col-md-4">
            <div class="mb-3">
                <label for="room_id" class="form-label">Available Room <span class="text-danger">*</span></label>
                <select class="form-select" id="room_id" name="room_id" required>
                    <option value="">Select Day and Times first</option>
                </select>
                <small class="text-muted" id="roomStatus">Select day and times to see available rooms</small>
            </div>
        </div>
    </div>

    <!-- Row 4: Students & Repeat -->
    <div class="row">
        <div class="col-md-4">
            <div class="mb-3">
                <label for="student_count" class="form-label">Number of Students</label>
                <input type="number" class="form-control" id="student_count" name="student_count" min="0" value="0">
                <small class="text-muted">Default 0. You can update this after the class.</small>
            </div>
        </div>
        <div class="col-md-8">
            <div class="mb-3">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="repeat_weekly" name="repeat_weekly" onchange="toggleRepeat()">
                    <label class="form-check-label" for="repeat_weekly">Repeat weekly</label>
                </div>
                <div id="repeat_until_container" style="display: none; margin-top:10px;">
                    <label for="repeat_until" class="form-label">Repeat until (end date) <span class="text-danger">*</span></label>
                    <input type="date" class="form-control" id="repeat_until" name="repeat_until">
                    <small class="text-muted">Future weeks will be generated automatically when the week arrives.</small>
                </div>
            </div>
        </div>
    </div>

    <div id="conflictAlert" class="alert alert-danger" style="display: none;"></div>

    <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Save Entry</button>
    <a href="index.php" class="btn btn-secondary">Cancel</a>
</form></div>

<script>
function toggleRepeat() {
    const checkbox = document.getElementById('repeat_weekly');
    const container = document.getElementById('repeat_until_container');
    container.style.display = checkbox.checked ? 'block' : 'none';
}

// ============================================================
// 1. Teacher → Subject filtering (for admin)
// ============================================================
document.getElementById('teacher_id')?.addEventListener('change', function() {
    const teacherId = this.value;
    const subjectSelect = document.getElementById('subject_id');
    const classSelect = document.getElementById('class_id');
    const roomSelect = document.getElementById('room_id');
    
    // Reset dependent fields
    subjectSelect.innerHTML = '<option value="">Select Subject</option>';
    classSelect.innerHTML = '<option value="">Select Class</option>';
    roomSelect.innerHTML = '<option value="">Select Day and Times first</option>';
    document.getElementById('roomStatus').textContent = 'Select day and times to see available rooms';
    
    if (!teacherId) return;
    
    fetch('../ajax/get_subjects.php?teacher_id=' + teacherId)
        .then(response => response.json())
        .then(data => {
            if (data.length === 0) {
                subjectSelect.innerHTML = '<option value="">No subjects assigned</option>';
            } else {
                data.forEach(subject => {
                    const option = document.createElement('option');
                    option.value = subject.id;
                    option.textContent = subject.name;
                    subjectSelect.appendChild(option);
                });
                if (data.length === 1) {
                    subjectSelect.value = data[0].id;
                    // Trigger subject change event to load classes
                    subjectSelect.dispatchEvent(new Event('change'));
                }
            }
        })
        .catch(error => console.error('Error fetching subjects:', error));
});

// ============================================================
// 2. Auto-load subjects for teachers on page load
// ============================================================
document.addEventListener('DOMContentLoaded', function() {
    const teacherInput = document.querySelector('input[name="teacher_id"]');
    if (teacherInput && teacherInput.value) {
        // Teacher is logged in – auto-load subjects
        const teacherId = teacherInput.value;
        loadSubjects(teacherId);
    }
});

function loadSubjects(teacherId) {
    const subjectSelect = document.getElementById('subject_id');
    const classSelect = document.getElementById('class_id');
    const roomSelect = document.getElementById('room_id');
    
    subjectSelect.innerHTML = '<option value="">Loading...</option>';
    classSelect.innerHTML = '<option value="">Select Class</option>';
    roomSelect.innerHTML = '<option value="">Select Day and Times first</option>';
    document.getElementById('roomStatus').textContent = 'Select day and times to see available rooms';
    
    fetch('../ajax/get_subjects.php?teacher_id=' + teacherId)
        .then(response => response.json())
        .then(data => {
            subjectSelect.innerHTML = '<option value="">Select Subject</option>';
            if (data.length === 0) {
                subjectSelect.innerHTML = '<option value="">No subjects assigned</option>';
            } else {
                data.forEach(subject => {
                    const option = document.createElement('option');
                    option.value = subject.id;
                    option.textContent = subject.name;
                    subjectSelect.appendChild(option);
                });
                if (data.length === 1) {
                    subjectSelect.value = data[0].id;
                    // Trigger change event to load classes
                    subjectSelect.dispatchEvent(new Event('change'));
                }
            }
        })
        .catch(error => {
            console.error('Error fetching subjects:', error);
            subjectSelect.innerHTML = '<option value="">Error loading subjects</option>';
        });
}

// ============================================================
// 3. Subject → Class filtering
// ============================================================
document.getElementById('subject_id')?.addEventListener('change', function() {
    const subjectId = this.value;
    const classSelect = document.getElementById('class_id');
    const roomSelect = document.getElementById('room_id');
    
    classSelect.innerHTML = '<option value="">Select Class</option>';
    roomSelect.innerHTML = '<option value="">Select Day and Times first</option>';
    document.getElementById('roomStatus').textContent = 'Select day and times to see available rooms';
    
    if (!subjectId) return;
    
    fetch('../ajax/get_classes.php?subject_id=' + subjectId)
        .then(response => response.json())
        .then(data => {
            if (data.length === 0) {
                classSelect.innerHTML = '<option value="">No classes for this subject</option>';
            } else {
                data.forEach(cls => {
                    const option = document.createElement('option');
                    option.value = cls.id;
                    option.textContent = cls.name;
                    classSelect.appendChild(option);
                });
            }
        })
        .catch(error => console.error('Error fetching classes:', error));
});

// ============================================================
// 4. Day + Start/End → Available Rooms
// ============================================================
function loadAvailableRooms() {
    const day = document.getElementById('day_of_week').value;
    const start = document.getElementById('start_time').value;
    const end = document.getElementById('end_time').value;
    const roomSelect = document.getElementById('room_id');
    const roomStatus = document.getElementById('roomStatus');
    
    if (!day || !start || !end) {
        roomSelect.innerHTML = '<option value="">Select Day and Times first</option>';
        roomStatus.textContent = 'Select day and times to see available rooms';
        return;
    }
    
    if (start >= end) {
        roomSelect.innerHTML = '<option value="">Start time must be before end time</option>';
        roomStatus.textContent = '⚠️ Start time must be before end time';
        return;
    }
    
    roomStatus.textContent = 'Loading available rooms...';
    
    fetch('../ajax/get_available_rooms.php?day_of_week=' + encodeURIComponent(day) + '&start_time=' + start + '&end_time=' + end)
        .then(response => response.json())
        .then(data => {
            roomSelect.innerHTML = '<option value="">Select Room</option>';
            if (data.length === 0) {
                roomSelect.innerHTML = '<option value="">No rooms available</option>';
                roomStatus.textContent = '⚠️ No rooms available at this time';
                roomStatus.style.color = 'red';
            } else {
                data.forEach(room => {
                    const option = document.createElement('option');
                    option.value = room.id;
                    option.textContent = room.name + ' (Capacity: ' + room.capacity + ')';
                    roomSelect.appendChild(option);
                });
                roomStatus.textContent = '✅ ' + data.length + ' room(s) available';
                roomStatus.style.color = 'green';
            }
        })
        .catch(error => {
            console.error('Error fetching rooms:', error);
            roomStatus.textContent = 'Error loading rooms';
            roomStatus.style.color = 'red';
        });
}

// Trigger room load when day or times change
document.getElementById('day_of_week')?.addEventListener('change', loadAvailableRooms);
document.getElementById('start_time')?.addEventListener('change', loadAvailableRooms);
document.getElementById('start_time')?.addEventListener('input', loadAvailableRooms);
document.getElementById('end_time')?.addEventListener('change', loadAvailableRooms);
document.getElementById('end_time')?.addEventListener('input', loadAvailableRooms);

// ============================================================
// 5. Conflict Check (live)
// ============================================================
document.addEventListener('DOMContentLoaded', function() {
    const conflictAlert = document.getElementById('conflictAlert');
    const inputs = ['teacher_id', 'room_id', 'class_id', 'day_of_week', 'start_time', 'end_time'];
    let timeout;
    
    function checkConflicts() {
        const teacher = document.getElementById('teacher_id').value;
        const room = document.getElementById('room_id').value;
        const classId = document.getElementById('class_id').value;
        const day = document.getElementById('day_of_week').value;
        const start = document.getElementById('start_time').value;
        const end = document.getElementById('end_time').value;
        
        if (!teacher || !room || !classId || !day || !start || !end) {
            conflictAlert.style.display = 'none';
            return;
        }
        if (start >= end) {
            conflictAlert.textContent = 'Start time must be before end time.';
            conflictAlert.style.display = 'block';
            return;
        }
        
        fetch(`add.php?check=1&teacher_id=${teacher}&room_id=${room}&class_id=${classId}&day_of_week=${day}&start=${start}&end=${end}`)
            .then(r => r.json())
            .then(data => {
                if (data.conflict) {
                    conflictAlert.textContent = '⚠️ ' + data.message;
                    conflictAlert.style.display = 'block';
                } else {
                    conflictAlert.style.display = 'none';
                }
            })
            .catch(error => console.error('Conflict check error:', error));
    }
    
    inputs.forEach(id => {
        const el = document.getElementById(id);
        if (el) {
            el.addEventListener('change', function() {
                clearTimeout(timeout);
                timeout = setTimeout(checkConflicts, 300);
            });
            el.addEventListener('input', function() {
                clearTimeout(timeout);
                timeout = setTimeout(checkConflicts, 300);
            });
        }
    });
});
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
