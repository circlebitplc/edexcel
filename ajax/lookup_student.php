<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_staff();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$q = trim((string)($_GET['q'] ?? $_GET['query'] ?? ''));
$studentId = (int)($_GET['student_id'] ?? 0);
$phone = trim((string)($_GET['whatsapp'] ?? $_GET['phone'] ?? ''));
$classId = (int)($_GET['class_id'] ?? 0);
$teacherId = (int)($_SESSION['teacher_id'] ?? 0);
$isAdmin = is_admin();
$canSeeParentWa = $isAdmin || ($classId > 0 && campus_staff_can_access_class($pdo, $classId, false, $teacherId));

if (!isset($_SESSION['lookup_rl']) || !is_array($_SESSION['lookup_rl'])) {
    $_SESSION['lookup_rl'] = [];
}
$now = time();
$bucket = array_values(array_filter(
    $_SESSION['lookup_rl'],
    static fn ($t) => is_int($t) && ($now - $t) < 60
));
if (count($bucket) >= 40) {
    http_response_code(429);
    echo json_encode(['ok' => false, 'found' => false, 'error' => 'Please wait a moment and try again.']);
    exit;
}
$bucket[] = $now;
$_SESSION['lookup_rl'] = $bucket;

try {
    // 1. Multi-field search (Google login email, name, phone)
    if ($q !== '') {
        if (!campus_staff_may_search_students($pdo, $isAdmin, $teacherId, $classId)) {
            echo json_encode(['ok' => true, 'results' => []]);
            exit;
        }
        $results = campus_search_students($pdo, $q, 15, $classId);
        if (!$isAdmin && !campus_lookup_query_is_exact($q)) {
            $results = array_values(array_filter(
                $results,
                fn (array $row): bool => campus_staff_may_lookup_student($pdo, (int)($row['id'] ?? 0), false, $teacherId)
            ));
        }
        if (!$canSeeParentWa) {
            foreach ($results as &$r) {
                $r['parent_whatsapp'] = '';
            }
            unset($r);
        }
        echo json_encode(['ok' => true, 'results' => $results]);
        exit;
    }

    // 2. Lookup single student by student_id
    if ($studentId > 0) {
        if (!campus_staff_may_lookup_student($pdo, $studentId, $isAdmin, $teacherId)) {
            echo json_encode(['ok' => true, 'found' => false]);
            exit;
        }
        $stmt = $pdo->prepare("
            SELECT
                u.id,
                COALESCE(NULLIF(TRIM(sp.full_name), ''), u.username) AS full_name,
                COALESCE(NULLIF(TRIM(u.google_email), ''), NULLIF(TRIM(sp.email), ''), '') AS google_email,
                COALESCE(NULLIF(TRIM(sp.whatsapp_number), ''), u.username, '') AS whatsapp_raw,
                COALESCE(sp.parent_name, '') AS parent_name,
                COALESCE(sp.parent_whatsapp, '') AS parent_whatsapp
            FROM users u
            LEFT JOIN student_profiles sp ON sp.user_id = u.id
            WHERE u.id = ? AND u.role = 'student' AND u.deleted_at IS NULL
            LIMIT 1
        ");
        $stmt->execute([$studentId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            echo json_encode(['ok' => true, 'found' => false]);
            exit;
        }

        $alreadyEnrolled = false;
        if ($classId > 0) {
            $chk = $pdo->prepare('SELECT id FROM student_enrollments WHERE student_id = ? AND class_id = ? LIMIT 1');
            $chk->execute([$studentId, $classId]);
            $alreadyEnrolled = (bool)$chk->fetchColumn();
        }

        $rawPhone = trim((string)$row['whatsapp_raw']);
        $cleanedPhone = '';
        $phoneDigits = preg_replace('/\D+/', '', $rawPhone) ?? '';
        if (strlen($phoneDigits) >= 9) {
            $formattedLk = campus_lk_whatsapp($rawPhone);
            $cleanedPhone = $formattedLk !== '' ? ('0' . substr($formattedLk, 2)) : $rawPhone;
        }

        echo json_encode([
            'ok' => true,
            'found' => true,
            'student_id' => (int)$row['id'],
            'full_name' => trim((string)$row['full_name']),
            'google_email' => trim((string)$row['google_email']),
            'whatsapp' => $cleanedPhone,
            'parent_name' => trim((string)$row['parent_name']),
            'parent_whatsapp' => $canSeeParentWa ? trim((string)$row['parent_whatsapp']) : '',
            'already_enrolled' => $alreadyEnrolled,
        ]);
        exit;
    }

    // 3. Lookup by WhatsApp / phone
    if ($phone !== '') {
        if (!campus_staff_may_search_students($pdo, $isAdmin, $teacherId, $classId)) {
            echo json_encode(['ok' => true, 'found' => false]);
            exit;
        }
        $found = campus_find_student_by_whatsapp($pdo, $phone);
        if (!$found) {
            echo json_encode(['ok' => true, 'found' => false]);
            exit;
        }

        $alreadyEnrolled = false;
        if ($classId > 0) {
            $chk = $pdo->prepare('SELECT id FROM student_enrollments WHERE student_id = ? AND class_id = ? LIMIT 1');
            $chk->execute([(int)$found['id'], $classId]);
            $alreadyEnrolled = (bool)$chk->fetchColumn();
        }

        $payload = [
            'ok' => true,
            'found' => true,
            'student_id' => (int)$found['id'],
            'full_name' => $found['full_name'],
            'google_email' => $found['google_email'] ?? '',
            'whatsapp' => $found['whatsapp'] ?? $phone,
            'parent_name' => $found['parent_name'],
            'already_enrolled' => $alreadyEnrolled,
        ];
        if ($canSeeParentWa) {
            $payload['parent_whatsapp'] = $found['parent_whatsapp'];
        }
        echo json_encode($payload);
        exit;
    }

    // Default: empty query
    echo json_encode(['ok' => true, 'results' => []]);
} catch (Throwable $e) {
    error_log('lookup_student: ' . $e->getMessage());
    http_response_code(400);
    echo json_encode(['ok' => false, 'found' => false, 'error' => 'Lookup failed.']);
}
