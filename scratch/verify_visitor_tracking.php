<?php
declare(strict_types=1);

require dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/src/Services/SiteVisitorAnalyticsService.php';

use Edexcel\Services\SiteVisitorAnalyticsService;

echo "=== SITE VISITOR USER TRACKING VERIFICATION ===\n\n";

$testsPassed = 0;
$testsFailed = 0;

function assertTest(bool $condition, string $testName, string $details = '') {
    global $testsPassed, $testsFailed;
    if ($condition) {
        echo " [PASS] $testName\n";
        if ($details) echo "        $details\n";
        $testsPassed++;
    } else {
        echo " [FAIL] $testName\n";
        if ($details) echo "        $details\n";
        $testsFailed++;
    }
}

// 1. Ensure test users exist in database
$admin = $pdo->query("SELECT id, username, google_email FROM users WHERE role = 'admin' AND deleted_at IS NULL LIMIT 1")->fetch(PDO::FETCH_ASSOC);

// If no student exists, insert a temporary test student
$createdStudent = false;
$student = $pdo->query("
    SELECT u.id, u.username, sp.full_name as student_name, sp.email as student_email 
    FROM users u 
    LEFT JOIN student_profiles sp ON sp.user_id = u.id 
    WHERE u.role = 'student' AND u.deleted_at IS NULL 
    LIMIT 1
")->fetch(PDO::FETCH_ASSOC);

if (!$student) {
    $pdo->exec("INSERT INTO users (username, password_hash, role, is_active) VALUES ('test_student_user', 'hash', 'student', 1)");
    $studentUserId = (int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO student_profiles (user_id, full_name, email) VALUES (?, 'Kasun Perera', 'kasun@test.edexcel.college')")->execute([$studentUserId]);
    $createdStudent = true;
    $student = [
        'id' => $studentUserId,
        'username' => 'test_student_user',
        'student_name' => 'Kasun Perera',
        'student_email' => 'kasun@test.edexcel.college'
    ];
}

// If no teacher exists, insert a temporary test teacher
$createdTeacher = false;
$teacher = $pdo->query("
    SELECT u.id, u.username, u.teacher_id, t.name as teacher_name, t.email as teacher_email 
    FROM users u 
    JOIN teachers t ON u.teacher_id = t.id 
    WHERE u.role = 'teacher' AND u.deleted_at IS NULL 
    LIMIT 1
")->fetch(PDO::FETCH_ASSOC);

if (!$teacher) {
    $pdo->exec("INSERT INTO teachers (name, email) VALUES ('Dr. Nimal Fernando', 'nimal@test.edexcel.college')");
    $teacherId = (int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO users (username, password_hash, role, teacher_id, is_active) VALUES ('test_teacher_user', 'hash', 'teacher', ?, 1)")->execute([$teacherId]);
    $teacherUserId = (int)$pdo->lastInsertId();
    $createdTeacher = true;
    $teacher = [
        'id' => $teacherUserId,
        'username' => 'test_teacher_user',
        'teacher_id' => $teacherId,
        'teacher_name' => 'Dr. Nimal Fernando',
        'teacher_email' => 'nimal@test.edexcel.college'
    ];
}

// If no parent exists, insert a temporary test parent
$createdParent = false;
$parent = $pdo->query("SELECT id, name, phone, email FROM parent_accounts LIMIT 1")->fetch(PDO::FETCH_ASSOC);
if (!$parent) {
    $pdo->exec("INSERT INTO parent_accounts (name, phone, email) VALUES ('Mrs. Sunethra Perera', '0771234567', 'parent@test.edexcel.college')");
    $parentId = (int)$pdo->lastInsertId();
    $createdParent = true;
    $parent = [
        'id' => $parentId,
        'name' => 'Mrs. Sunethra Perera',
        'phone' => '0771234567',
        'email' => 'parent@test.edexcel.college'
    ];
}

echo "Active Test Profiles:\n";
echo "  Admin:   ID {$admin['id']} ({$admin['username']})\n";
echo "  Teacher: ID {$teacher['id']} ({$teacher['teacher_name']})\n";
echo "  Student: ID {$student['id']} ({$student['student_name']})\n";
echo "  Parent:  ID {$parent['id']} ({$parent['name']})\n\n";

// --- TEST 1: Guest Visitor Tracking ---
$guestSid = 'test_guest_' . bin2hex(random_bytes(8));
$guestVid = 'vid_' . bin2hex(random_bytes(8));
$guestRes = SiteVisitorAnalyticsService::recordVisit($pdo, [
    'session_id' => $guestSid,
    'visitor_id' => $guestVid,
    'page_url' => '/about-us',
    'page_title' => 'About Us',
    'user_id' => null,
    'user_type' => null
]);
assertTest($guestRes['ok'] === true, 'Test 1.1: Record guest visit returns ok');

$sessGuest = SiteVisitorAnalyticsService::getSessions($pdo, ['q' => $guestSid], 1, 5);
$guestRecord = $sessGuest['records'][0] ?? null;
assertTest(
    $guestRecord !== null &&
    $guestRecord['is_registered'] === false &&
    $guestRecord['display_name'] === 'Guest Visitor' &&
    $guestRecord['display_role'] === 'Guest',
    'Test 1.2: Guest session correctly reports is_registered=false and "Guest Visitor"'
);

// --- TEST 2: Student Visitor Tracking ---
$studentSid = 'test_student_' . bin2hex(random_bytes(8));
$studentVid = 'vid_' . bin2hex(random_bytes(8));
$studentRes = SiteVisitorAnalyticsService::recordVisit($pdo, [
    'session_id' => $studentSid,
    'visitor_id' => $studentVid,
    'page_url' => '/portal/dashboard',
    'page_title' => 'Student Dashboard',
    'user_id' => (int)$student['id'],
    'user_type' => 'student'
]);
assertTest($studentRes['ok'] === true, 'Test 2.1: Record student visit returns ok');

$sessStudent = SiteVisitorAnalyticsService::getSessions($pdo, ['q' => $studentSid], 1, 5);
$studentRecord = $sessStudent['records'][0] ?? null;
assertTest(
    $studentRecord !== null &&
    $studentRecord['is_registered'] === true &&
    $studentRecord['display_name'] === 'Kasun Perera' &&
    $studentRecord['display_role'] === 'Student' &&
    $studentRecord['display_email'] === 'kasun@test.edexcel.college' &&
    str_contains($studentRecord['profile_url'], 'students.php?edit=' . $student['id']),
    'Test 2.2: Student session displays student name "Kasun Perera", role "Student", email, and profile URL'
);

// --- TEST 3: Teacher Visitor Tracking ---
$teacherSid = 'test_teacher_' . bin2hex(random_bytes(8));
$teacherVid = 'vid_' . bin2hex(random_bytes(8));
$teacherRes = SiteVisitorAnalyticsService::recordVisit($pdo, [
    'session_id' => $teacherSid,
    'visitor_id' => $teacherVid,
    'page_url' => '/teacher/classes.php',
    'page_title' => 'My Classes',
    'user_id' => (int)$teacher['id'],
    'user_type' => 'teacher'
]);
assertTest($teacherRes['ok'] === true, 'Test 3.1: Record teacher visit returns ok');

$sessTeacher = SiteVisitorAnalyticsService::getSessions($pdo, ['q' => $teacherSid], 1, 5);
$teacherRecord = $sessTeacher['records'][0] ?? null;
assertTest(
    $teacherRecord !== null &&
    $teacherRecord['is_registered'] === true &&
    $teacherRecord['display_name'] === 'Dr. Nimal Fernando' &&
    $teacherRecord['display_role'] === 'Teacher' &&
    $teacherRecord['display_email'] === 'nimal@test.edexcel.college' &&
    str_contains($teacherRecord['profile_url'], 'teacher_analytics.php?teacher_id=' . $teacher['teacher_id']),
    'Test 3.2: Teacher session displays teacher name, role "Teacher", email, and analytics link'
);

// --- TEST 4: Admin Visitor Tracking ---
$adminSid = 'test_admin_' . bin2hex(random_bytes(8));
$adminVid = 'vid_' . bin2hex(random_bytes(8));
$adminRes = SiteVisitorAnalyticsService::recordVisit($pdo, [
    'session_id' => $adminSid,
    'visitor_id' => $adminVid,
    'page_url' => '/admin/settings.php?tab=site_visitor',
    'page_title' => 'Admin Settings',
    'user_id' => (int)$admin['id'],
    'user_type' => 'admin'
]);
assertTest($adminRes['ok'] === true, 'Test 4.1: Record admin visit returns ok');

$sessAdmin = SiteVisitorAnalyticsService::getSessions($pdo, ['q' => $adminSid], 1, 5);
$adminRecord = $sessAdmin['records'][0] ?? null;
assertTest(
    $adminRecord !== null &&
    $adminRecord['is_registered'] === true &&
    $adminRecord['display_name'] === $admin['username'] &&
    $adminRecord['display_role'] === 'Administrator' &&
    $adminRecord['profile_url'] === 'users.php',
    'Test 4.2: Admin session displays username, role "Administrator", and profile link'
);

// --- TEST 5: Parent Visitor Tracking ---
$parentSid = 'test_parent_' . bin2hex(random_bytes(8));
$parentVid = 'vid_' . bin2hex(random_bytes(8));
$parentRes = SiteVisitorAnalyticsService::recordVisit($pdo, [
    'session_id' => $parentSid,
    'visitor_id' => $parentVid,
    'page_url' => '/parent/dashboard.php',
    'page_title' => 'Parent Portal',
    'user_id' => (int)$parent['id'],
    'user_type' => 'parent'
]);
assertTest($parentRes['ok'] === true, 'Test 5.1: Record parent visit returns ok');

$sessParent = SiteVisitorAnalyticsService::getSessions($pdo, ['q' => $parentSid], 1, 5);
$parentRecord = $sessParent['records'][0] ?? null;
assertTest(
    $parentRecord !== null &&
    $parentRecord['is_registered'] === true &&
    $parentRecord['display_name'] === 'Mrs. Sunethra Perera' &&
    $parentRecord['display_role'] === 'Parent' &&
    $parentRecord['display_email'] === 'parent@test.edexcel.college' &&
    $parentRecord['profile_url'] === 'parent_requests.php',
    'Test 5.2: Parent session displays parent name, role "Parent", email, and parent link'
);

// --- TEST 6: Auto-transition on login (Guest -> Student) ---
$loginTransSid = 'test_trans_' . bin2hex(random_bytes(8));
$loginTransVid = 'vid_' . bin2hex(random_bytes(8));

// Step 1: visit as guest
SiteVisitorAnalyticsService::recordVisit($pdo, [
    'session_id' => $loginTransSid,
    'visitor_id' => $loginTransVid,
    'page_url' => '/portal/login.php',
    'page_title' => 'Login',
    'user_id' => null,
    'user_type' => null
]);
// Step 2: user logs in as student
$transRes = SiteVisitorAnalyticsService::recordVisit($pdo, [
    'session_id' => $loginTransSid,
    'visitor_id' => $loginTransVid,
    'page_url' => '/portal/dashboard.php',
    'page_title' => 'Student Dashboard',
    'user_id' => (int)$student['id'],
    'user_type' => 'student'
]);
assertTest($transRes['ok'] === true && $transRes['session_id'] === $loginTransSid, 'Test 6.1: Login transition updates existing session');

$sessTrans = SiteVisitorAnalyticsService::getSessions($pdo, ['q' => $loginTransSid], 1, 5);
$transRecord = $sessTrans['records'][0] ?? null;
assertTest(
    $transRecord !== null &&
    $transRecord['is_registered'] === true &&
    $transRecord['display_name'] === 'Kasun Perera' &&
    (int)$transRecord['pageviews_count'] === 2,
    'Test 6.2: Session transitioned to student with 2 pageviews recorded'
);

// --- TEST 7: Logout Session Forking (Requirement 13) ---
$authSid = 'test_auth_fork_' . bin2hex(random_bytes(8));
$authVid = 'vid_' . bin2hex(random_bytes(8));

// 1. Visit as registered student
SiteVisitorAnalyticsService::recordVisit($pdo, [
    'session_id' => $authSid,
    'visitor_id' => $authVid,
    'page_url' => '/student/profile.php',
    'page_title' => 'Profile',
    'user_id' => (int)$student['id'],
    'user_type' => 'student'
]);

// 2. User logs out. Same browser sends next pageview with same session_id but user_id=null
$forkRes = SiteVisitorAnalyticsService::recordVisit($pdo, [
    'session_id' => $authSid,
    'visitor_id' => $authVid,
    'page_url' => '/about-us',
    'page_title' => 'About Us (Guest)',
    'user_id' => null,
    'user_type' => null
]);

assertTest(
    $forkRes['session_rotated'] === true && $forkRes['session_id'] !== $authSid,
    'Test 7.1: Logout triggers session rotation with a new session_id'
);

// Verify original session still has user_id = student id
$origStmt = $pdo->prepare("SELECT user_id, user_type FROM site_visitor_sessions WHERE session_id = ?");
$origStmt->execute([$authSid]);
$origRow = $origStmt->fetch(PDO::FETCH_ASSOC);
assertTest(
    $origRow && (int)$origRow['user_id'] === (int)$student['id'] && $origRow['user_type'] === 'student',
    'Test 7.2: Previous registered user session remains intact after logout'
);

// Verify new session has user_id = null
$newStmt = $pdo->prepare("SELECT user_id, user_type FROM site_visitor_sessions WHERE session_id = ?");
$newStmt->execute([$forkRes['session_id']]);
$newRow = $newStmt->fetch(PDO::FETCH_ASSOC);
assertTest(
    $newRow && $newRow['user_id'] === null,
    'Test 7.3: New guest session has user_id=null and is not attributed to student'
);

// --- TEST 8: Search by Student/Teacher/Parent Names ---
$searchNameRes = SiteVisitorAnalyticsService::getSessions($pdo, ['q' => 'Kasun Perera'], 1, 5);
assertTest(
    !empty($searchNameRes['records']) && $searchNameRes['records'][0]['display_name'] === 'Kasun Perera',
    'Test 8.1: Search by student name "Kasun Perera" finds student session'
);

$searchTeacherRes = SiteVisitorAnalyticsService::getSessions($pdo, ['q' => 'Nimal Fernando'], 1, 5);
assertTest(
    !empty($searchTeacherRes['records']) && $searchTeacherRes['records'][0]['display_name'] === 'Dr. Nimal Fernando',
    'Test 8.2: Search by teacher name "Nimal Fernando" finds teacher session'
);

$searchParentRes = SiteVisitorAnalyticsService::getSessions($pdo, ['q' => 'Sunethra'], 1, 5);
assertTest(
    !empty($searchParentRes['records']) && $searchParentRes['records'][0]['display_name'] === 'Mrs. Sunethra Perera',
    'Test 8.3: Search by parent name "Sunethra" finds parent session'
);

// --- TEST 9: Activity & Online Status formatting ---
$onlineSec = 15; // 15 seconds ago
$offlineSec = 3600; // 1 hour ago
assertTest(
    SiteVisitorAnalyticsService::formatSecondsAgo($onlineSec) === 'Just now',
    'Test 9.1: formatSecondsAgo(15) returns "Just now"'
);
assertTest(
    SiteVisitorAnalyticsService::formatSecondsAgo(180) === '3m ago',
    'Test 9.2: formatSecondsAgo(180) returns "3m ago"'
);
assertTest(
    SiteVisitorAnalyticsService::formatSecondsAgo($offlineSec) === '1h ago',
    'Test 9.3: formatSecondsAgo(3600) returns "1h ago"'
);

// Clean up test rows
$allSess = [$guestSid, $studentSid, $teacherSid, $adminSid, $parentSid, $loginTransSid, $authSid, $forkRes['session_id']];
$inStr = "'" . implode("','", $allSess) . "'";
$pdo->exec("DELETE FROM site_visitor_pageviews WHERE session_id IN ($inStr)");
$pdo->exec("DELETE FROM site_visitor_sessions WHERE session_id IN ($inStr)");

if ($createdStudent) {
    $pdo->prepare("DELETE FROM student_profiles WHERE user_id = ?")->execute([$student['id']]);
    $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$student['id']]);
}
if ($createdTeacher) {
    $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$teacher['id']]);
    $pdo->prepare("DELETE FROM teachers WHERE id = ?")->execute([$teacher['teacher_id']]);
}
if ($createdParent) {
    $pdo->prepare("DELETE FROM parent_accounts WHERE id = ?")->execute([$parent['id']]);
}

echo "\nSummary: $testsPassed passed, $testsFailed failed.\n";
if ($testsFailed === 0) {
    echo " ALL TESTS PASSED SUCCESSFULLY (100% PASS RATE)!\n";
} else {
    echo " SOME TESTS FAILED!\n";
    exit(1);
}
