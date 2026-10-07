<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/config.php';

require_staff();
ensure_campus_schema($pdo);

$isAdmin = is_admin();
$teacherId = (int)($_SESSION['teacher_id'] ?? 0);
$error = '';
$success = '';

$wantsJson = $_SERVER['REQUEST_METHOD'] === 'POST' && (
    is_ajax_request()
    || (string)($_POST['ajax'] ?? '') === '1'
);

function waitlist_staff_can_access_class(PDO $pdo, int $classId, bool $isAdmin, int $teacherId): bool
{
    if ($classId < 1) {
        return false;
    }
    if ($isAdmin) {
        $stmt = $pdo->prepare("SELECT id FROM student_classes WHERE id = ? AND deleted_at IS NULL LIMIT 1");
        $stmt->execute([$classId]);
        return (bool)$stmt->fetchColumn();
    }
    if ($teacherId < 1) {
        return false;
    }
    $stmt = $pdo->prepare("
        SELECT c.id
        FROM student_classes c
        WHERE c.id = ?
          AND c.deleted_at IS NULL
          AND (
            EXISTS (
                SELECT 1 FROM timetable tt
                WHERE tt.class_id = c.id AND tt.teacher_id = ? AND tt.deleted_at IS NULL
            )
            OR EXISTS (
                SELECT 1 FROM subject_classes sc
                JOIN teacher_subjects ts ON ts.subject_id = sc.subject_id
                WHERE sc.class_id = c.id AND ts.teacher_id = ?
            )
          )
        LIMIT 1
    ");
    $stmt->execute([$classId, $teacherId, $teacherId]);
    return (bool)$stmt->fetchColumn();
}

function waitlist_class_payload(PDO $pdo, int $classId): ?array
{
    $stmt = $pdo->prepare("
        SELECT
            c.id,
            c.name,
            c.capacity,
            (SELECT COUNT(*) FROM student_enrollments se WHERE se.class_id = c.id) AS enrolled,
            (SELECT COUNT(*) FROM student_waitlist w WHERE w.class_id = c.id) AS waiting
        FROM student_classes c
        WHERE c.id = ? AND c.deleted_at IS NULL
        LIMIT 1
    ");
    $stmt->execute([$classId]);
    $class = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$class) {
        return null;
    }
    $wstmt = $pdo->prepare("
        SELECT
            w.student_id,
            w.created_at,
            COALESCE(p.full_name, u.username) AS student_name,
            u.username AS phone
        FROM student_waitlist w
        JOIN users u ON u.id = w.student_id
        LEFT JOIN student_profiles p ON p.user_id = w.student_id
        WHERE w.class_id = ?
        ORDER BY w.created_at ASC, w.id ASC
    ");
    $wstmt->execute([$classId]);
    $waiters = $wstmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $capRaw = $class['capacity'];
    $cap = ($capRaw === null || $capRaw === '') ? '' : (string)(int)$capRaw;
    $enrolled = (int)$class['enrolled'];
    $waiting = (int)$class['waiting'];
    $full = $cap !== '' && $enrolled >= (int)$cap;
    return [
        'id' => (int)$class['id'],
        'name' => (string)$class['name'],
        'enrolled' => $enrolled,
        'capacity' => $cap,
        'waiting' => $waiting,
        'full' => $full,
        'waiters' => array_map(static function (array $row): array {
            return [
                'student_id' => (int)$row['student_id'],
                'student_name' => (string)$row['student_name'],
                'phone' => (string)$row['phone'],
                'created_at' => (string)$row['created_at'],
            ];
        }, $waiters),
    ];
}

$postedClassId = 0;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
            throw new RuntimeException('Security token expired.');
        }
        $action = (string)($_POST['action'] ?? '');
        $classId = (int)($_POST['class_id'] ?? 0);
        $postedClassId = $classId;
        if ($classId < 1) {
            throw new RuntimeException('Choose a class.');
        }
        if (!waitlist_staff_can_access_class($pdo, $classId, $isAdmin, $teacherId)) {
            throw new RuntimeException('You cannot change this class.');
        }

        if ($action === 'capacity') {
            $raw = trim((string)($_POST['capacity'] ?? ''));
            $capacity = $raw === '' ? null : max(0, (int)$raw);
            if ($capacity === 0) {
                $capacity = null;
            }
            $pdo->prepare("UPDATE student_classes SET capacity = ? WHERE id = ?")->execute([$capacity, $classId]);
            $success = $capacity === null
                ? 'This class now has no seat limit.'
                : 'Capacity set to ' . $capacity . ' students.';
        } elseif ($action === 'promote') {
            $studentId = (int)($_POST['student_id'] ?? 0);
            $promoted = campus_promote_next_waitlist($pdo, $classId, $studentId > 0 ? $studentId : null);
            if (!$promoted) {
                throw new RuntimeException('No seat to offer (class may be full, or waitlist is empty).');
            }
            $success = 'Student enrolled from the waitlist.';
        } elseif ($action === 'remove') {
            $studentId = (int)($_POST['student_id'] ?? 0);
            if ($studentId < 1) {
                throw new RuntimeException('Student missing.');
            }
            campus_waitlist_leave($pdo, $studentId, $classId);
            $success = 'Removed from waitlist.';
        } else {
            throw new RuntimeException('Unknown action.');
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }

    if ($wantsJson) {
        header('Content-Type: application/json; charset=utf-8');
        $payload = [
            'ok' => $error === '',
            'message' => $error !== '' ? $error : $success,
        ];
        if ($postedClassId > 0 && waitlist_staff_can_access_class($pdo, $postedClassId, $isAdmin, $teacherId)) {
            $card = waitlist_class_payload($pdo, $postedClassId);
            if ($card) {
                $payload['card'] = $card;
            }
        }
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        exit;
    }
}

$classSql = "
    SELECT
        c.id,
        c.name,
        c.capacity,
        (SELECT COUNT(*) FROM student_enrollments se WHERE se.class_id = c.id) AS enrolled,
        (SELECT COUNT(*) FROM student_waitlist w WHERE w.class_id = c.id) AS waiting
    FROM student_classes c
    WHERE c.deleted_at IS NULL
";
$params = [];
if (!$isAdmin && $teacherId > 0) {
    $classSql .= "
        AND (
            EXISTS (
                SELECT 1 FROM timetable tt
                WHERE tt.class_id = c.id AND tt.teacher_id = ? AND tt.deleted_at IS NULL
            )
            OR EXISTS (
                SELECT 1 FROM subject_classes sc
                JOIN teacher_subjects ts ON ts.subject_id = sc.subject_id
                WHERE sc.class_id = c.id AND ts.teacher_id = ?
            )
        )
    ";
    $params[] = $teacherId;
    $params[] = $teacherId;
}
$classSql .= " ORDER BY waiting DESC, c.name";
$stmt = $pdo->prepare($classSql);
$stmt->execute($params);
$classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

$waiters = [];
if ($classes) {
    $ids = array_map(static fn($row) => (int)$row['id'], $classes);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $wstmt = $pdo->prepare("
        SELECT
            w.class_id,
            w.student_id,
            w.created_at,
            COALESCE(p.full_name, u.username) AS student_name,
            u.username AS phone
        FROM student_waitlist w
        JOIN users u ON u.id = w.student_id
        LEFT JOIN student_profiles p ON p.user_id = w.student_id
        WHERE w.class_id IN ($placeholders)
        ORDER BY w.created_at ASC, w.id ASC
    ");
    $wstmt->execute($ids);
    foreach ($wstmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $waiters[(int)$row['class_id']][] = $row;
    }
}

include __DIR__ . '/../includes/header.php';
$csrfToken = htmlspecialchars(generate_csrf_token(), ENT_QUOTES, 'UTF-8');
?>
<div class="container-fluid py-4" id="waitlist-page" data-csrf="<?= $csrfToken ?>">
    <h1 class="h3 mb-1"><i class="bi bi-hourglass-split text-warning"></i> Class waitlist</h1>
    <p class="text-muted">Set a class size. When a class is full, students join a queue. If someone leaves, the next student gets a 24-hour WhatsApp offer to join — they are not enrolled automatically.</p>
    <div id="waitlist-flash" class="d-none"></div>

    <?php foreach ($classes as $c):
        $cid = (int)$c['id'];
        $queue = $waiters[$cid] ?? [];
        $cap = $c['capacity'] === null || $c['capacity'] === '' ? '' : (string)(int)$c['capacity'];
        $full = $cap !== '' && (int)$c['enrolled'] >= (int)$cap;
    ?>
        <div class="card border-0 shadow-sm rounded-4 mb-3 js-waitlist-card" data-class-id="<?= $cid ?>">
            <div class="card-body">
                <div class="d-flex flex-wrap justify-content-between gap-3 mb-3">
                    <div>
                        <h2 class="h5 mb-1"><?= e($c['name']) ?></h2>
                        <div class="small text-muted js-waitlist-meta"></div>
                    </div>
                    <form method="post" class="d-flex gap-2 align-items-end js-waitlist-form">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="capacity">
                        <input type="hidden" name="class_id" value="<?= $cid ?>">
                        <div>
                            <label class="form-label small mb-0">Seat limit</label>
                            <input class="form-control js-capacity-input" type="number" min="1" name="capacity" value="<?= e($cap) ?>" placeholder="Unlimited" style="width:8rem">
                        </div>
                        <button class="btn btn-outline-primary js-save-btn" type="submit">Save</button>
                    </form>
                </div>
                <div class="js-waitlist-queue"></div>
            </div>
        </div>
        <script type="application/json" class="js-waitlist-seed"><?= json_encode([
            'id' => $cid,
            'name' => (string)$c['name'],
            'enrolled' => (int)$c['enrolled'],
            'capacity' => $cap,
            'waiting' => (int)$c['waiting'],
            'full' => $full,
            'waiters' => array_map(static fn($row) => [
                'student_id' => (int)$row['student_id'],
                'student_name' => (string)$row['student_name'],
                'phone' => (string)$row['phone'],
                'created_at' => (string)$row['created_at'],
            ], $queue),
        ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
    <?php endforeach; ?>
    <?php if (!$classes): ?>
        <p class="text-muted">No classes found.</p>
    <?php endif; ?>
</div>
<script>
(function () {
    var page = document.getElementById('waitlist-page');
    if (!page) {
        return;
    }
    var csrf = page.getAttribute('data-csrf') || '';

    function flash(ok, message) {
        var box = document.getElementById('waitlist-flash');
        if (!box) {
            return;
        }
        box.className = 'alert ' + (ok ? 'alert-success' : 'alert-danger');
        box.textContent = message || (ok ? 'Saved.' : 'Could not save.');
        box.classList.remove('d-none');
        window.clearTimeout(box._hide);
        box._hide = window.setTimeout(function () {
            box.classList.add('d-none');
        }, 4000);
    }

    function setText(el, text) {
        if (el) {
            el.textContent = text;
        }
    }

    function renderCard(cardEl, data) {
        var cap = data.capacity == null ? '' : String(data.capacity);
        var meta = cardEl.querySelector('.js-waitlist-meta');
        var parts = [String(data.enrolled) + ' enrolled'];
        if (cap !== '') {
            parts.push('/ ' + cap + ' seats');
        } else {
            parts.push('· no limit');
        }
        parts.push('· ' + String(data.waiting) + ' waiting');
        if (meta) {
            meta.textContent = '';
            meta.appendChild(document.createTextNode(parts.join(' ')));
            if (data.full) {
                meta.appendChild(document.createTextNode(' '));
                var badge = document.createElement('span');
                badge.className = 'badge bg-warning text-dark';
                badge.textContent = 'Full';
                meta.appendChild(badge);
            }
        }
        var capInput = cardEl.querySelector('.js-capacity-input');
        if (capInput && document.activeElement !== capInput) {
            capInput.value = cap;
        }
        var queue = cardEl.querySelector('.js-waitlist-queue');
        if (!queue) {
            return;
        }
        queue.textContent = '';
        var waiters = data.waiters || [];
        if (!waiters.length) {
            var empty = document.createElement('p');
            empty.className = 'text-muted mb-0';
            empty.textContent = 'No one on the waitlist.';
            queue.appendChild(empty);
            return;
        }
        var wrap = document.createElement('div');
        wrap.className = 'table-responsive';
        var table = document.createElement('table');
        table.className = 'table table-sm align-middle mb-0';
        table.innerHTML = '<thead><tr><th>#</th><th>Student</th><th>Joined queue</th><th></th></tr></thead>';
        var tbody = document.createElement('tbody');
        waiters.forEach(function (row, i) {
            var tr = document.createElement('tr');
            var tdN = document.createElement('td');
            tdN.textContent = String(i + 1);
            var tdName = document.createElement('td');
            tdName.appendChild(document.createTextNode(row.student_name || ''));
            var phone = document.createElement('div');
            phone.className = 'small text-muted';
            phone.textContent = row.phone || '';
            tdName.appendChild(phone);
            var tdWhen = document.createElement('td');
            tdWhen.className = 'small';
            tdWhen.textContent = row.created_at || '';
            var tdAct = document.createElement('td');
            tdAct.className = 'text-end';

            tdAct.appendChild(actionForm('promote', data.id, row.student_id, 'btn btn-sm btn-success', 'Enrol', data.full));
            tdAct.appendChild(document.createTextNode(' '));
            tdAct.appendChild(actionForm('remove', data.id, row.student_id, 'btn btn-sm btn-outline-danger', 'Remove', false, 'Remove from waitlist?'));

            tr.appendChild(tdN);
            tr.appendChild(tdName);
            tr.appendChild(tdWhen);
            tr.appendChild(tdAct);
            tbody.appendChild(tr);
        });
        table.appendChild(tbody);
        wrap.appendChild(table);
        queue.appendChild(wrap);
    }

    function hiddenInput(name, value) {
        var input = document.createElement('input');
        input.type = 'hidden';
        input.name = name;
        input.value = value;
        return input;
    }

    function actionForm(action, classId, studentId, btnClass, label, disabled, confirmText) {
        var form = document.createElement('form');
        form.method = 'post';
        form.className = 'd-inline js-waitlist-form';
        if (confirmText) {
            form.setAttribute('data-confirm', confirmText);
        }
        form.appendChild(hiddenInput('csrf_token', csrf));
        form.appendChild(hiddenInput('action', action));
        form.appendChild(hiddenInput('class_id', String(classId)));
        form.appendChild(hiddenInput('student_id', String(studentId)));
        var btn = document.createElement('button');
        btn.type = 'submit';
        btn.className = btnClass;
        btn.textContent = label;
        if (disabled) {
            btn.disabled = true;
        }
        form.appendChild(btn);
        return form;
    }

    function seedCards() {
        document.querySelectorAll('.js-waitlist-card').forEach(function (cardEl) {
            var seed = cardEl.nextElementSibling;
            if (!seed || !seed.classList.contains('js-waitlist-seed')) {
                return;
            }
            try {
                renderCard(cardEl, JSON.parse(seed.textContent || '{}'));
            } catch (err) {
                return;
            }
            seed.remove();
        });
    }

    async function submitForm(form) {
        var confirmText = form.getAttribute('data-confirm');
        if (confirmText && !window.confirm(confirmText)) {
            return;
        }
        var body = new FormData(form);
        body.set('ajax', '1');
        var btn = form.querySelector('button[type="submit"], .js-save-btn');
        if (btn) {
            btn.disabled = true;
        }
        try {
            var res = await fetch(window.location.pathname, {
                method: 'POST',
                body: body,
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
            });
            var data = await res.json();
            flash(!!data.ok, data.message || '');
            if (data.card) {
                var id = String(data.card.id);
                var cardEl = page.querySelector('.js-waitlist-card[data-class-id="' + id + '"]');
                if (cardEl) {
                    renderCard(cardEl, data.card);
                }
            }
        } catch (err) {
            flash(false, 'Could not save. Please try again.');
        } finally {
            if (btn && form.querySelector('[name="action"]') && form.querySelector('[name="action"]').value === 'capacity') {
                btn.disabled = false;
            }
        }
    }

    page.addEventListener('submit', function (event) {
        var form = event.target.closest('.js-waitlist-form');
        if (!form || !page.contains(form)) {
            return;
        }
        event.preventDefault();
        submitForm(form);
    });

    seedCards();
})();
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
