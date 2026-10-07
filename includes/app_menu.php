<?php
declare(strict_types=1);

/**
 * Right-side app rail: icon-only until opened, then icon + labels.
 * Used for every logged-in role on every screen size.
 */

$appTab = strtolower(trim((string)($_GET['tab'] ?? '')));

$appGroups = [];
$appSettingsHref = rtrim((string)BASE_URL, '/') . '/settings.php';
$appSettingsActive = ($current_page === 'settings.php' && $current_dir !== 'student')
    || ($is_student && $current_dir === 'student' && ($current_page === 'settings.php' || $appTab === 'settings'));

if ($is_student) {
    $studentDash = BASE_URL . 'student/dashboard.php';
    $appSettingsHref = $studentDash . '?tab=settings';
    // Grouped by student tasks: daily class loop, study work, then account records.
    $appGroups = [
        'My day' => [
            ['href' => $studentDash . '?tab=overview', 'icon' => 'bi-house', 'label' => 'Today', 'hint' => 'Next class, fees, homework, alerts', 'active' => $current_dir === 'student' && $current_page === 'dashboard.php' && ($appTab === '' || $appTab === 'overview')],
            ['href' => $studentDash . '?tab=timetable', 'icon' => 'bi-calendar3', 'label' => 'Timetable', 'hint' => 'This week\'s lessons', 'active' => ($current_dir === 'student' && $appTab === 'timetable') || $current_page === 'class.php'],
            ['href' => $studentDash . '?tab=classes', 'icon' => 'bi-collection', 'label' => 'My classes', 'hint' => 'Subjects you joined', 'active' => $current_dir === 'student' && ($appTab === 'classes' || $current_page === 'all_classes.php')],
            ['href' => $studentDash . '?tab=recordings', 'icon' => 'bi-camera-reels', 'label' => 'Recordings', 'hint' => 'Watch past classes', 'active' => ($current_dir === 'student' && $appTab === 'recordings') || in_array($current_page, ['recording.php', 'lesson.php', 'library_video.php', 'pay_lesson.php', 'payment_result.php', 'payment_receipt.php'], true)],
            ['href' => $studentDash . '?tab=join', 'icon' => 'bi-search', 'label' => 'Find a class', 'hint' => 'Enrol with another teacher', 'active' => $current_dir === 'student' && $appTab === 'join'],
        ],
        'Study' => [
            ['href' => $studentDash . '?tab=services', 'icon' => 'bi-journal-check', 'label' => 'Homework & notes', 'hint' => 'Papers, tasks, notices', 'active' => $current_dir === 'student' && $appTab === 'services'],
            ['href' => BASE_URL . 'student/learning_path.php', 'icon' => 'bi-signpost-split', 'label' => 'Learning path', 'hint' => 'Lessons in order, what is next', 'active' => $current_dir === 'student' && $current_page === 'learning_path.php'],
            ['href' => BASE_URL . 'student/bookmarks.php', 'icon' => 'bi-bookmark', 'label' => 'Bookmarks & notes', 'hint' => 'Saved parts of lessons', 'active' => $current_dir === 'student' && $current_page === 'bookmarks.php'],
            ['href' => BASE_URL . 'student/requests.php', 'icon' => 'bi-life-preserver', 'label' => 'Service requests', 'hint' => 'Ask the college for help', 'active' => $current_dir === 'student' && $current_page === 'requests.php'],
            ['href' => BASE_URL . 'student/progress.php', 'icon' => 'bi-graph-up-arrow', 'label' => 'Progress', 'hint' => 'Marks, attendance, topics', 'active' => $current_dir === 'student' && $current_page === 'progress.php'],
            ['href' => BASE_URL . 'student/report_card.php', 'icon' => 'bi-award', 'label' => 'Report cards', 'hint' => 'Published academic reports', 'active' => $current_dir === 'student' && $current_page === 'report_card.php'],
            ['href' => $studentDash . '?tab=exams', 'icon' => 'bi-journal-text', 'label' => 'Exams', 'hint' => 'Official and college exams', 'active' => $current_dir === 'student' && $appTab === 'exams'],
            ['href' => BASE_URL . 'student/assessment.php', 'icon' => 'bi-ui-checks', 'label' => 'Assessments', 'hint' => 'Class tests and mocks', 'active' => $current_dir === 'student' && in_array($current_page, ['assessment.php', 'assessment_result.php'], true)],
            ['href' => BASE_URL . 'student/assessment_calendar.php', 'icon' => 'bi-calendar2-week', 'label' => 'Exam calendar', 'hint' => 'Upcoming assessments', 'active' => $current_dir === 'student' && $current_page === 'assessment_calendar.php'],
            ['href' => BASE_URL . 'student/exam_prep.php', 'icon' => 'bi-lightning-charge', 'label' => 'Exam prep', 'hint' => 'Revision plans and countdown', 'active' => $current_dir === 'student' && $current_page === 'exam_prep.php'],
            ['href' => BASE_URL . 'student/academic_advisor.php', 'icon' => 'bi-lightbulb', 'label' => 'Academic advisor', 'hint' => 'Personal recommendations', 'active' => $current_dir === 'student' && $current_page === 'academic_advisor.php'],
            ['href' => $studentDash . '?tab=teachers', 'icon' => 'bi-person-badge', 'label' => 'Teachers', 'hint' => 'Your class teachers', 'active' => $current_dir === 'student' && $appTab === 'teachers'],
            ['href' => $studentDash . '?tab=courso', 'icon' => 'bi-stars', 'label' => 'Talk with AI', 'hint' => 'Study help', 'active' => $current_dir === 'student' && $appTab === 'courso'],
        ],
        'Account' => [
            ['href' => $studentDash . '?tab=fees', 'icon' => 'bi-wallet2', 'label' => 'Fees', 'hint' => 'Pay and receipts', 'active' => $current_dir === 'student' && $appTab === 'fees'],
            ['href' => $studentDash . '?tab=attendance', 'icon' => 'bi-check2-circle', 'label' => 'Attendance', 'hint' => 'This month', 'active' => $current_dir === 'student' && $appTab === 'attendance'],
            ['href' => BASE_URL . 'student/notifications.php', 'icon' => 'bi-bell', 'label' => 'Notifications', 'hint' => 'Alerts and updates', 'active' => $current_dir === 'student' && $current_page === 'notifications.php'],
            ['href' => BASE_URL . 'student/history.php', 'icon' => 'bi-clock-history', 'label' => 'Message history', 'hint' => 'Notices, deliveries, threads', 'active' => $current_dir === 'student' && $current_page === 'history.php'],
            ['href' => BASE_URL . 'student/notice_board.php', 'icon' => 'bi-clipboard2-pulse', 'label' => 'Notice board', 'hint' => 'College announcements', 'active' => $current_dir === 'student' && $current_page === 'notice_board.php'],
            ['href' => BASE_URL . 'student/preferences.php', 'icon' => 'bi-sliders', 'label' => 'Alert preferences', 'hint' => 'Channels and categories', 'active' => $current_dir === 'student' && $current_page === 'preferences.php'],
            ['href' => BASE_URL . 'student/onboarding.php', 'icon' => 'bi-door-open', 'label' => 'Onboarding', 'hint' => 'Welcome checklist', 'active' => $current_dir === 'student' && $current_page === 'onboarding.php'],
            ['href' => $studentDash . '?tab=documents', 'icon' => 'bi-file-earmark-person', 'label' => 'My documents', 'hint' => 'ID copies and letters', 'active' => $current_dir === 'student' && $appTab === 'documents'],
        ],
    ];
} elseif ($is_admin) {
    // Grouped by office workflow: day → schedule → people → teaching → money → admissions → messaging → insights → systems.
    $appGroups = [
        'Today' => [
            ['href' => BASE_URL . 'dashboard.php', 'icon' => 'bi-house', 'label' => 'Home', 'hint' => 'Overview and today’s board', 'active' => $current_page === 'dashboard.php' && $current_dir !== 'student' && $current_dir !== 'admin'],
            ['href' => BASE_URL . 'campus/today.php', 'icon' => 'bi-sun', 'label' => 'Today', 'hint' => 'Rooms, headcount, attendance', 'active' => in_array($current_page, ['today.php', 'lesson_ops.php'], true)],
            ['href' => BASE_URL . 'admin/command_center.php', 'icon' => 'bi-speedometer2', 'label' => 'Command center', 'hint' => 'Ops snapshot', 'active' => $current_page === 'command_center.php'],
            ['href' => BASE_URL . 'admin/search.php', 'icon' => 'bi-search', 'label' => 'Global search', 'active' => $current_page === 'search.php'],
        ],
        'Schedule' => [
            ['href' => BASE_URL . 'timetable/index.php', 'icon' => 'bi-calendar-event', 'label' => 'Timetable', 'active' => $current_dir === 'timetable' && !in_array($current_page, ['weekly.php', 'payments.php', 'holidays.php', 'recurring_manage.php', 'teacher_schedule.php'], true)],
            ['href' => BASE_URL . 'timetable/weekly.php', 'icon' => 'bi-calendar-week', 'label' => 'Weekly view', 'active' => $current_page === 'weekly.php'],
            ['href' => BASE_URL . 'admin/timetable_suggestions.php', 'icon' => 'bi-calendar-plus', 'label' => 'Suggestions', 'active' => $current_page === 'timetable_suggestions.php'],
            ['href' => BASE_URL . 'campus/exams.php', 'icon' => 'bi-journal-text', 'label' => 'Exams', 'active' => $current_page === 'exams.php'],
            ['href' => BASE_URL . 'campus/exam_control.php', 'icon' => 'bi-ui-checks-grid', 'label' => 'Exam control', 'active' => in_array($current_page, ['exam_control.php', 'assessment_builder.php', 'assessment_mark.php', 'assessment_analytics.php'], true)],
            ['href' => BASE_URL . 'campus/official_exams.php', 'icon' => 'bi-calendar2-week', 'label' => 'Official timetable', 'active' => $current_page === 'official_exams.php'],
            ['href' => BASE_URL . 'campus/events.php', 'icon' => 'bi-calendar2-event', 'label' => 'Events', 'active' => $current_page === 'events.php'],
            ['href' => BASE_URL . 'admin/teacher_leave.php', 'icon' => 'bi-calendar-x', 'label' => 'Teacher leave', 'active' => $current_page === 'teacher_leave.php'],
        ],
        'People' => [
            ['href' => BASE_URL . 'admin/students.php', 'icon' => 'bi-people', 'label' => 'Students', 'active' => $current_page === 'students.php'],
            ['href' => BASE_URL . 'admin/parent_requests.php', 'icon' => 'bi-person-check', 'label' => 'Parent verification', 'hint' => 'Approve parent↔student links', 'active' => in_array($current_page, ['parent_requests.php', 'parent_invitations.php'], true)],
            ['href' => BASE_URL . 'teachers/index.php', 'icon' => 'bi-person-workspace', 'label' => 'Teachers', 'active' => $current_dir === 'teachers'],
            ['href' => BASE_URL . 'admin/users.php', 'icon' => 'bi-person-gear', 'label' => 'Users', 'active' => $current_page === 'users.php'],
            ['href' => BASE_URL . 'campus/waitlist.php', 'icon' => 'bi-hourglass-split', 'label' => 'Waitlist', 'active' => $current_page === 'waitlist.php'],
            ['href' => BASE_URL . 'admin/support.php', 'icon' => 'bi-life-preserver', 'label' => 'Support tickets', 'active' => $current_page === 'support.php'],
            ['href' => BASE_URL . 'admin/otp_support.php', 'icon' => 'bi-key', 'label' => 'OTP support', 'active' => $current_page === 'otp_support.php'],
        ],
        'Setup' => [
            ['href' => BASE_URL . 'classes/index.php', 'icon' => 'bi-collection', 'label' => 'Classes', 'active' => $current_dir === 'classes'],
            ['href' => BASE_URL . 'admin/class_management.php', 'icon' => 'bi-collection', 'label' => 'Class management', 'active' => $current_page === 'class_management.php'],
            ['href' => BASE_URL . 'subjects/index.php', 'icon' => 'bi-book', 'label' => 'Subjects', 'active' => $current_dir === 'subjects'],
            ['href' => BASE_URL . 'rooms/index.php', 'icon' => 'bi-door-open', 'label' => 'Rooms', 'active' => $current_dir === 'rooms'],
            ['href' => BASE_URL . 'campus/academic.php', 'icon' => 'bi-mortarboard', 'label' => 'Academic', 'active' => $current_page === 'academic.php'],
            ['href' => BASE_URL . 'admin/capacity.php', 'icon' => 'bi-people', 'label' => 'Class capacity', 'active' => $current_page === 'capacity.php'],
            ['href' => BASE_URL . 'admin/homepage-layouts.php', 'icon' => 'bi-layout-wtf', 'label' => 'Homepage', 'active' => $current_page === 'homepage-layouts.php'],
        ],
        'Classroom' => [
            ['href' => BASE_URL . 'admin/classroom.php', 'icon' => 'bi-broadcast', 'label' => 'Online classes', 'active' => $current_dir === 'admin' && $current_page === 'classroom.php'],
            ['href' => BASE_URL . 'campus/attendance.php', 'icon' => 'bi-check2-circle', 'label' => 'Attendance', 'active' => $current_page === 'attendance.php'],
            ['href' => BASE_URL . 'campus/progress.php', 'icon' => 'bi-graph-up-arrow', 'label' => 'Marks', 'active' => $current_page === 'progress.php'],
            ['href' => BASE_URL . 'campus/homework.php', 'icon' => 'bi-file-earmark-pdf', 'label' => 'Papers', 'active' => $current_page === 'homework.php'],
            ['href' => BASE_URL . 'campus/documents.php', 'icon' => 'bi-file-earmark-person', 'label' => 'Documents', 'active' => $current_page === 'documents.php'],
            ['href' => BASE_URL . 'admin/recordings.php', 'icon' => 'bi-camera-reels', 'label' => 'Class recordings', 'active' => ($current_dir === 'admin' && $current_page === 'recordings.php') || $current_page === 'watch_recording.php'],
            ['href' => BASE_URL . 'campus/online_lesson.php', 'icon' => 'bi-list-ol', 'label' => 'Video lessons', 'active' => in_array($current_page, ['online_lesson.php', 'lesson_manage.php'], true)],
            ['href' => BASE_URL . 'campus/lesson_calendar.php', 'icon' => 'bi-calendar3', 'label' => 'Lesson planner', 'active' => $current_page === 'lesson_calendar.php'],
            ['href' => BASE_URL . 'campus/courses.php', 'icon' => 'bi-diagram-2', 'label' => 'Courses', 'active' => $current_page === 'courses.php'],
            ['href' => BASE_URL . 'campus/resource_library.php', 'icon' => 'bi-folder2-open', 'label' => 'Resource library', 'active' => $current_page === 'resource_library.php'],
            ['href' => BASE_URL . 'campus/courso.php', 'icon' => 'bi-stars', 'label' => 'Talk with AI', 'active' => $current_page === 'courso.php'],
            ['href' => BASE_URL . 'teachers/assistant.php', 'icon' => 'bi-lightbulb', 'label' => 'Planning assistant', 'active' => $current_dir === 'teachers' && $current_page === 'assistant.php'],
        ],
        'Finance' => [
            ['href' => BASE_URL . 'campus/lesson_fees.php', 'icon' => 'bi-cash-coin', 'label' => 'Class fees', 'active' => $current_page === 'lesson_fees.php'],
            ['href' => BASE_URL . 'timetable/payments.php', 'icon' => 'bi-wallet2', 'label' => 'Payments', 'active' => in_array($current_page, ['payments.php', 'payment_ledger.php'], true)],
            ['href' => BASE_URL . 'campus/bank_slips.php', 'icon' => 'bi-bank', 'label' => 'Bank slips', 'active' => $current_page === 'bank_slips.php' || $current_page === 'payment_slip.php'],
            ['href' => BASE_URL . 'campus/cash.php', 'icon' => 'bi-cash-stack', 'label' => 'Cash handover', 'active' => $current_page === 'cash.php'],
            ['href' => BASE_URL . 'admin/finance.php', 'icon' => 'bi-cash-stack', 'label' => 'Finance hub', 'active' => $current_page === 'finance.php'],
        ],
        'Admissions' => [
            ['href' => BASE_URL . 'admin/admissions_control.php', 'icon' => 'bi-person-plus', 'label' => 'Admissions', 'active' => in_array($current_page, ['admissions.php', 'admissions_control.php', 'admission_review.php', 'admission_analytics.php', 'leads.php', 'student_lifecycle.php', 'admission_permissions.php'], true)],
            ['href' => BASE_URL . 'admin/risk.php', 'icon' => 'bi-shield-exclamation', 'label' => 'Student risk', 'active' => $current_page === 'risk.php'],
            ['href' => BASE_URL . 'admin/student_success.php', 'icon' => 'bi-graph-up-arrow', 'label' => 'Student success', 'active' => $current_page === 'student_success.php'],
        ],
        'Messages' => [
            ['href' => BASE_URL . 'admin/communications.php', 'icon' => 'bi-megaphone', 'label' => 'Communications', 'active' => in_array($current_page, ['communications.php', 'communication_templates.php', 'communication_threads.php', 'communication_analytics.php'], true)],
            ['href' => BASE_URL . 'admin/phone_contacts.php', 'icon' => 'bi-telephone', 'label' => 'Phone contacts', 'hint' => 'WhatsApp groups & SMS', 'active' => in_array($current_page, ['phone_contacts.php', 'phone_contact_view.php', 'phone_import_history.php'], true)],
            ['href' => BASE_URL . 'admin/announcements.php', 'icon' => 'bi-megaphone', 'label' => 'Announcements', 'active' => $current_page === 'announcements.php'],
            ['href' => BASE_URL . 'admin/whatsapp_bot.php', 'icon' => 'bi-chat-dots', 'label' => 'WhatsApp', 'active' => $current_dir === 'whatsapp' || in_array($current_page, ['whatsapp_bot.php', 'whatsapp_connect.php'], true)],
            ['href' => BASE_URL . 'admin/communication_threads.php', 'icon' => 'bi-chat-left-text', 'label' => 'Threads', 'active' => $current_page === 'communication_threads.php'],
            ['href' => BASE_URL . 'admin/communication_templates.php', 'icon' => 'bi-file-earmark-text', 'label' => 'Templates', 'active' => $current_page === 'communication_templates.php'],
            ['href' => BASE_URL . 'admin/communication_workbench.php', 'icon' => 'bi-person-lines-fill', 'label' => 'Workbench', 'active' => $current_page === 'communication_workbench.php' || ($current_page === 'student360.php' && strtolower(trim((string)($_GET['tab'] ?? ''))) === 'communication')],
            ['href' => BASE_URL . 'admin/communication_ops.php', 'icon' => 'bi-broadcast-pin', 'label' => 'Channel ops', 'active' => $current_page === 'communication_ops.php'],
            ['href' => BASE_URL . 'admin/communication_analytics.php', 'icon' => 'bi-graph-up', 'label' => 'Comm analytics', 'active' => $current_page === 'communication_analytics.php'],
        ],
        'Insights' => [
            ['href' => BASE_URL . 'admin/class_analytics.php', 'icon' => 'bi-graph-up-arrow', 'label' => 'Class page analytics', 'hint' => '/class visitor and conversion report', 'active' => $current_page === 'class_analytics.php'],
            ['href' => BASE_URL . 'admin/bi.php', 'icon' => 'bi-bar-chart', 'label' => 'College BI', 'active' => $current_page === 'bi.php'],
            ['href' => BASE_URL . 'admin/teacher_analytics.php', 'icon' => 'bi-person-workspace', 'label' => 'Teacher analytics', 'active' => $current_page === 'teacher_analytics.php'],
            ['href' => BASE_URL . 'admin/attendance_analytics.php', 'icon' => 'bi-graph-down-arrow', 'label' => 'Attendance analytics', 'active' => $current_page === 'attendance_analytics.php'],
            ['href' => BASE_URL . 'admin/exam_analytics.php', 'icon' => 'bi-clipboard-data', 'label' => 'Exam analytics', 'active' => $current_page === 'exam_analytics.php'],
            ['href' => BASE_URL . 'admin/scheduled_reports.php', 'icon' => 'bi-clock-history', 'label' => 'Scheduled reports', 'active' => $current_page === 'scheduled_reports.php'],
        ],
        'Systems' => [
            ['href' => BASE_URL . 'admin/system_health.php', 'icon' => 'bi-heart-pulse', 'label' => 'System health', 'active' => in_array($current_page, ['system_health.php', 'jobs.php'], true)],
            ['href' => BASE_URL . 'admin/api_health.php', 'icon' => 'bi-activity', 'label' => 'API health', 'active' => $current_page === 'api_health.php'],
            ['href' => BASE_URL . 'admin/automation.php', 'icon' => 'bi-diagram-3', 'label' => 'Automation', 'active' => $current_page === 'automation.php'],
            ['href' => BASE_URL . 'admin/data_center.php', 'icon' => 'bi-arrow-left-right', 'label' => 'Data centre', 'active' => $current_page === 'data_center.php'],
            ['href' => BASE_URL . 'admin/security.php', 'icon' => 'bi-shield-lock', 'label' => 'Security', 'active' => $current_page === 'security.php'],
            ['href' => BASE_URL . 'admin/protection.php', 'icon' => 'bi-shield-check', 'label' => 'Protection', 'active' => $current_page === 'protection.php'],
            ['href' => BASE_URL . 'admin/security_center.php', 'icon' => 'bi-shield-lock', 'label' => 'Security Center', 'active' => $current_page === 'security_center.php'],
            ['href' => BASE_URL . 'admin/backup.php', 'icon' => 'bi-cloud-arrow-up', 'label' => 'Backup', 'active' => $current_page === 'backup.php'],
            ['href' => BASE_URL . 'admin/audit_log.php', 'icon' => 'bi-journal-text', 'label' => 'Audit log', 'active' => $current_page === 'audit_log.php'],
        ],
    ];

    // Present every existing route through six administrator task areas.
    $legacyAdminGroups = $appGroups;
    $withoutLabels = static function (array $items, array $labels): array {
        return array_values(array_filter(
            $items,
            static fn (array $item): bool => !in_array((string)($item['label'] ?? ''), $labels, true)
        ));
    };
    $withLabels = static function (array $items, array $labels): array {
        return array_values(array_filter(
            $items,
            static fn (array $item): bool => in_array((string)($item['label'] ?? ''), $labels, true)
        ));
    };
    $peopleForSystem = ['Users', 'Support tickets', 'OTP support'];
    $peopleForAdmissions = ['Parent verification'];
    $appGroups = [
        'Dashboard' => $legacyAdminGroups['Today'],
        'Academic' => array_merge(
            $legacyAdminGroups['Schedule'],
            $withoutLabels($legacyAdminGroups['People'], array_merge($peopleForSystem, $peopleForAdmissions)),
            $withoutLabels($legacyAdminGroups['Setup'], ['Homepage']),
            $legacyAdminGroups['Classroom']
        ),
        'Finance' => $legacyAdminGroups['Finance'],
        'Admissions' => array_merge(
            $legacyAdminGroups['Admissions'],
            $withLabels($legacyAdminGroups['People'], $peopleForAdmissions)
        ),
        'Communication' => $legacyAdminGroups['Messages'],
        'System' => array_merge(
            $withLabels($legacyAdminGroups['People'], $peopleForSystem),
            $withLabels($legacyAdminGroups['Setup'], ['Homepage']),
            $legacyAdminGroups['Insights'],
            $legacyAdminGroups['Systems']
        ),
    ];
    $appSettingsHref = BASE_URL . 'admin/settings.php';
    $appSettingsActive = $current_dir === 'admin' && in_array($current_page, ['class_analytics.php', 'settings.php', 'backup.php', 'audit_log.php', 'jobs.php', 'system_health.php', 'security.php', 'protection.php', 'security_center.php', 'command_center.php', 'finance.php', 'admissions.php', 'admissions_control.php', 'admission_review.php', 'admission_analytics.php', 'leads.php', 'student_lifecycle.php', 'admission_permissions.php', 'capacity.php', 'teacher_analytics.php', 'attendance_analytics.php', 'communications.php', 'communication_templates.php', 'communication_threads.php', 'communication_analytics.php', 'communication_ops.php', 'communication_workbench.php', 'data_center.php', 'scheduled_reports.php', 'search.php', 'risk.php', 'student_success.php', 'bi.php', 'automation.php', 'timetable_suggestions.php', 'exam_analytics.php', 'student360.php', 'class_management.php', 'teacher_leave.php', 'announcements.php', 'support.php', 'otp_support.php', 'api_health.php', 'homepage-layouts.php'], true)
        || ($current_page === 'settings.php' && $current_dir !== 'student');
} elseif ($is_teacher) {
    $teacherOnlinePaymentOn = false;
    if (isset($pdo) && $pdo instanceof PDO) {
        if (!function_exists('teacher_manual_payment_enabled')) {
            require_once __DIR__ . '/../config/payment_controls.php';
        }
        $teacherOnlinePaymentOn = teacher_manual_payment_enabled($pdo);
    }
    $appGroups = [
        'Today' => [
            ['href' => BASE_URL . 'dashboard.php', 'icon' => 'bi-house', 'label' => 'Home', 'active' => $current_page === 'dashboard.php' && $current_dir !== 'timetable' && $current_dir !== 'campus'],
            ['href' => BASE_URL . 'campus/today.php', 'icon' => 'bi-sun', 'label' => 'Today', 'active' => in_array($current_page, ['today.php', 'lesson_ops.php'], true)],
            ['href' => BASE_URL . 'timetable/teacher_schedule.php', 'icon' => 'bi-calendar-week', 'label' => 'My schedule', 'active' => $current_page === 'teacher_schedule.php'],
            ['href' => BASE_URL . 'timetable/add.php', 'icon' => 'bi-plus-lg', 'label' => 'Add lesson', 'active' => $current_page === 'add.php'],
        ],
        'Teaching' => [
            ['href' => BASE_URL . 'campus/exams.php', 'icon' => 'bi-journal-text', 'label' => 'Exams', 'active' => $current_page === 'exams.php'],
            ['href' => BASE_URL . 'campus/exam_control.php', 'icon' => 'bi-ui-checks-grid', 'label' => 'Exam control', 'active' => in_array($current_page, ['exam_control.php', 'assessment_builder.php', 'assessment_mark.php', 'assessment_analytics.php'], true)],
            ['href' => BASE_URL . 'campus/attendance.php', 'icon' => 'bi-check2-circle', 'label' => 'Attendance', 'active' => $current_page === 'attendance.php'],
            ['href' => BASE_URL . 'campus/lesson_fees.php', 'icon' => 'bi-cash-coin', 'label' => 'Class fees', 'active' => $current_page === 'lesson_fees.php'],
            ...($teacherOnlinePaymentOn
                ? [['href' => BASE_URL . 'campus/lesson_fees.php', 'icon' => 'bi-pencil-square', 'label' => 'Online class payment', 'active' => $current_page === 'lesson_fees.php']]
                : []),
            ['href' => BASE_URL . 'campus/bank_slips.php', 'icon' => 'bi-bank', 'label' => 'Bank slips', 'active' => $current_page === 'bank_slips.php' || $current_page === 'payment_slip.php'],
            ['href' => BASE_URL . 'campus/progress.php', 'icon' => 'bi-graph-up-arrow', 'label' => 'Marks', 'active' => $current_page === 'progress.php'],
            ['href' => BASE_URL . 'campus/homework.php', 'icon' => 'bi-file-earmark-pdf', 'label' => 'Papers', 'active' => $current_page === 'homework.php'],
            ['href' => BASE_URL . 'campus/recordings.php', 'icon' => 'bi-camera-reels', 'label' => 'Class recordings', 'active' => $current_dir === 'campus' && in_array($current_page, ['recordings.php', 'watch_recording.php'], true)],
            ['href' => BASE_URL . 'campus/online_lesson.php', 'icon' => 'bi-list-ol', 'label' => 'Video lessons', 'active' => in_array($current_page, ['online_lesson.php', 'lesson_manage.php'], true)],
            ['href' => BASE_URL . 'campus/lesson_calendar.php', 'icon' => 'bi-calendar3', 'label' => 'Lesson planner', 'active' => $current_page === 'lesson_calendar.php'],
            ['href' => BASE_URL . 'campus/courses.php', 'icon' => 'bi-diagram-2', 'label' => 'Courses', 'active' => $current_page === 'courses.php'],
            ['href' => BASE_URL . 'campus/resource_library.php', 'icon' => 'bi-folder2-open', 'label' => 'Resource library', 'active' => $current_page === 'resource_library.php'],
            ['href' => BASE_URL . 'campus/courso.php', 'icon' => 'bi-stars', 'label' => 'Talk with AI', 'active' => $current_page === 'courso.php'],
            ['href' => BASE_URL . 'campus/video_library.php', 'icon' => 'bi-collection-play', 'label' => 'My video library', 'active' => in_array($current_page, ['video_library.php', 'watch_library.php'], true)],
        ],
        'People' => [
            ['href' => BASE_URL . 'campus/students.php', 'icon' => 'bi-people', 'label' => 'My students', 'active' => $current_page === 'students.php'],
            ['href' => BASE_URL . 'campus/waitlist.php', 'icon' => 'bi-hourglass-split', 'label' => 'Waitlist', 'active' => $current_page === 'waitlist.php'],
        ],
        'More' => [
            ['href' => BASE_URL . 'campus/events.php', 'icon' => 'bi-calendar2-event', 'label' => 'Events', 'active' => $current_page === 'events.php'],
            ['href' => BASE_URL . 'campus/class_status.php', 'icon' => 'bi-megaphone', 'label' => 'Class status', 'active' => $current_page === 'class_status.php'],
            ['href' => BASE_URL . 'teachers/class_communication.php', 'icon' => 'bi-chat-dots', 'label' => 'Class messages', 'active' => $current_page === 'class_communication.php'],
            ['href' => BASE_URL . 'student/notice_board.php', 'icon' => 'bi-clipboard2-pulse', 'label' => 'Notice board', 'active' => $current_page === 'notice_board.php'],
            ['href' => BASE_URL . 'student/notifications.php', 'icon' => 'bi-bell', 'label' => 'Inbox', 'active' => $current_page === 'notifications.php'],
            ['href' => BASE_URL . 'timetable/payments.php', 'icon' => 'bi-wallet2', 'label' => 'Payments', 'active' => in_array($current_page, ['payments.php', 'payment_ledger.php'], true)],
            ['href' => BASE_URL . 'campus/cash.php', 'icon' => 'bi-cash-stack', 'label' => 'Cash handover', 'active' => $current_page === 'cash.php'],
            ['href' => BASE_URL . 'teachers/profile.php', 'icon' => 'bi-person', 'label' => 'My profile', 'active' => $current_page === 'profile.php' || $current_page === 'teacher_profile.php'],
        ],
    ];
} else {
    $appGroups = [
        'College' => [
            ['href' => BASE_URL, 'icon' => 'bi-house', 'label' => 'Home', 'active' => false],
            ['href' => BASE_URL . 'teachers/index.php', 'icon' => 'bi-person-workspace', 'label' => 'Teachers', 'active' => $current_dir === 'teachers'],
            ['href' => rtrim((string)BASE_URL, '/') . '/index.php#timetable', 'icon' => 'bi-calendar3', 'label' => 'Timetable', 'active' => false],
            ['href' => BASE_URL . 'student/register.php', 'icon' => 'bi-mortarboard', 'label' => 'Apply', 'active' => $current_page === 'register.php'],
        ],
        'Sign in' => [
            ['href' => rtrim((string)BASE_URL, '/') . '/index.php#student-login', 'icon' => 'bi-box-arrow-in-right', 'label' => 'Login', 'hint' => 'Student, staff, or parent', 'active' => $current_page === 'login.php'],
        ],
    ];
}

$renderAppLink = static function (array $item): void {
    $label = (string)($item['label'] ?? '');
    $hint = trim((string)($item['hint'] ?? ''));
    $title = $hint !== '' ? $label . ' — ' . $hint : $label;
    $active = !empty($item['active']);
    ?>
    <a class="app-rail-link<?= $active ? ' is-active' : '' ?>" href="<?= htmlspecialchars((string)$item['href'], ENT_QUOTES, 'UTF-8') ?>" title="<?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?>"<?= $active ? ' aria-current="page"' : '' ?>>
        <i class="bi <?= htmlspecialchars((string)$item['icon'], ENT_QUOTES, 'UTF-8') ?>" aria-hidden="true"></i>
        <span class="app-rail-text">
            <span class="app-rail-label"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></span>
            <?php if ($hint !== ''): ?>
            <span class="app-rail-hint"><?= htmlspecialchars($hint, ENT_QUOTES, 'UTF-8') ?></span>
            <?php endif; ?>
        </span>
    </a>
    <?php
};
?>

<aside class="app-rail<?= $is_student ? ' app-rail-student' : '' ?>" id="appRail" aria-label="<?= $is_student ? 'Student menu' : 'Main menu' ?>">
    <div class="app-rail-brand" id="appRailBrand">
        <button class="app-rail-mark" type="button" id="appRailMark" title="Expand or collapse menu" aria-expanded="false">E</button>
        <span class="app-rail-text app-rail-brand-name"><?= $is_student ? 'Student menu' : 'Edexcel College' ?></span>
        <i class="bi bi-caret-down-fill app-rail-caret" aria-hidden="true"></i>
    </div>

    <nav class="app-rail-nav">
        <?php foreach ($appGroups as $groupLabel => $groupItems): ?>
            <div class="app-rail-section" role="group" aria-label="<?= htmlspecialchars((string)$groupLabel, ENT_QUOTES, 'UTF-8') ?>">
                <div class="app-rail-group"><?= htmlspecialchars((string)$groupLabel, ENT_QUOTES, 'UTF-8') ?></div>
                <?php foreach ($groupItems as $item) { $renderAppLink($item); } ?>
            </div>
        <?php endforeach; ?>
    </nav>

    <div class="app-rail-foot">
        <?php if ($is_admin || $is_teacher || $is_student): ?>
        <a class="app-rail-link<?= $appSettingsActive ? ' is-active' : '' ?>" href="<?= htmlspecialchars($appSettingsHref, ENT_QUOTES, 'UTF-8') ?>" title="<?= $is_student ? 'Settings — Phone, password, parent WhatsApp' : 'Settings' ?>"<?= $appSettingsActive ? ' aria-current="page"' : '' ?>>
            <i class="bi bi-gear" aria-hidden="true"></i>
            <span class="app-rail-text">
                <span class="app-rail-label">Settings</span>
                <?php if ($is_student): ?>
                <span class="app-rail-hint">Phone, password, parent WhatsApp</span>
                <?php endif; ?>
            </span>
        </a>
        <a class="app-rail-link app-rail-logout" href="<?= htmlspecialchars(BASE_URL . 'logout.php', ENT_QUOTES, 'UTF-8') ?>" title="Logout">
            <i class="bi bi-box-arrow-right" aria-hidden="true"></i>
            <span class="app-rail-text">
                <span class="app-rail-label">Logout</span>
            </span>
        </a>
        <?php else: ?>
        <a class="app-rail-link" href="<?= htmlspecialchars(rtrim((string)BASE_URL, '/') . '/index.php', ENT_QUOTES, 'UTF-8') ?>" title="Home">
            <i class="bi bi-house" aria-hidden="true"></i>
            <span class="app-rail-text">Home</span>
        </a>
        <a class="app-rail-link" href="<?= htmlspecialchars(rtrim((string)BASE_URL, '/') . '/index.php#student-login', ENT_QUOTES, 'UTF-8') ?>" title="Login">
            <i class="bi bi-box-arrow-in-right" aria-hidden="true"></i>
            <span class="app-rail-text">Login</span>
        </a>
        <?php endif; ?>
    </div>
</aside>
