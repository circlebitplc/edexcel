<?php
declare(strict_types=1);

/**
 * One shared data load for every public homepage layout.
 *
 * @return array<string, mixed>
 */
function homepage_load_data(?PDO $pdo): array
{
    $weekOffset = filter_input(INPUT_GET, 'timetable_week', FILTER_VALIDATE_INT);
    if ($weekOffset === false || $weekOffset === null) {
        $weekOffset = 0;
    }
    $weekOffset = max(-52, min(52, (int)$weekOffset));

    $hp = [
        'institute' => 'Edexcel College',
        'tagline' => 'Pearson Edexcel IGCSE & International A Level',
        'city' => 'Kandy, Sri Lanka',
        'stats' => [
            'teachers' => 0,
            'students' => 0,
            'classes' => 0,
            'today' => 0,
            'subjects' => 0,
            'years' => 12,
            'success_rate' => 96,
            'attendance' => 94,
            'experience' => 10,
        ],
        'teachers' => [],
        'programmes' => [],
        'qualifications' => [],
        'subjects' => [],
        'todayLessons' => [],
        'upcomingLessons' => [],
        'weeklyTimetable' => [],
        'weekStart' => null,
        'weekEnd' => null,
        'weekOffset' => $weekOffset,
        'events' => [],
        'news' => [],
        'achievements' => [],
        'announcements' => [],
        'testimonials' => homepage_default_testimonials(),
    ];

    if (!($pdo instanceof PDO)) {
        return $hp;
    }

    try {
        if (function_exists('ops_setting')) {
            $name = college_brand_name(ops_setting($pdo, 'institute_name', 'Edexcel College'));
            $hp['institute'] = $name;
            $raw = trim(ops_setting($pdo, 'institute_name', ''));
            if ($raw !== '' && $raw !== $name) {
                try {
                    ops_save_setting($pdo, 'institute_name', $name);
                } catch (Throwable $e) {
                    // ignore persist failure
                }
            }
        }
    } catch (Throwable $e) {
    }

    $hp['stats']['teachers'] = homepage_count($pdo, "SELECT COUNT(*) FROM teachers WHERE deleted_at IS NULL AND LOWER(name) <> 'default teacher'");
    $hp['stats']['students'] = homepage_count($pdo, "SELECT COUNT(DISTINCT id) FROM users WHERE role = 'student'");
    $hp['stats']['classes'] = homepage_count($pdo, 'SELECT COUNT(*) FROM student_classes WHERE deleted_at IS NULL');
    $hp['stats']['today'] = homepage_count($pdo, 'SELECT COUNT(*) FROM timetable WHERE deleted_at IS NULL AND date = CURDATE()');
    $hp['stats']['subjects'] = homepage_count($pdo, 'SELECT COUNT(*) FROM subjects WHERE deleted_at IS NULL');

    try {
        $year = (int)$pdo->query("SELECT YEAR(MIN(created_at)) FROM teachers WHERE deleted_at IS NULL")->fetchColumn();
        if ($year >= 2000 && $year <= (int)date('Y')) {
            $hp['stats']['years'] = max(1, (int)date('Y') - $year);
        }
    } catch (Throwable $e) {
    }

    try {
        $exp = (int)$pdo->query('SELECT MAX(experience_years) FROM teacher_profiles WHERE experience_years IS NOT NULL')->fetchColumn();
        if ($exp > 0) {
            $hp['stats']['experience'] = $exp;
        }
    } catch (Throwable $e) {
    }

    try {
        $avg = $pdo->query('SELECT AVG(score / NULLIF(max_score, 0) * 100) FROM student_progress WHERE max_score > 0 AND published = 1')->fetchColumn();
        if ($avg !== false && $avg !== null && (float)$avg > 0) {
            $hp['stats']['success_rate'] = (int)round((float)$avg);
        }
    } catch (Throwable $e) {
        try {
            $avg = $pdo->query('SELECT AVG(score / NULLIF(max_score, 0) * 100) FROM student_progress WHERE max_score > 0')->fetchColumn();
            if ($avg !== false && $avg !== null && (float)$avg > 0) {
                $hp['stats']['success_rate'] = (int)round((float)$avg);
            }
        } catch (Throwable $e2) {
        }
    }

    try {
        $att = $pdo->query("
            SELECT
                SUM(status IN ('present','late')) AS present_n,
                COUNT(*) AS total_n
            FROM student_attendance
        ")->fetch(PDO::FETCH_ASSOC);
        $totalN = (int)($att['total_n'] ?? 0);
        if ($totalN > 0) {
            $hp['stats']['attendance'] = (int)round(((int)($att['present_n'] ?? 0) / $totalN) * 100);
        }
    } catch (Throwable $e) {
        $hp['stats']['attendance'] = (int)($hp['stats']['success_rate'] ?? 94);
    }

    $hp['teachers'] = homepage_load_teachers($pdo);
    $hp['programmes'] = homepage_load_programmes($pdo);
    $hp['qualifications'] = homepage_load_qualifications($pdo);
    $hp['subjects'] = homepage_load_subjects($pdo);

    $week = homepage_load_weekly_timetable($pdo, $weekOffset);
    $hp['weeklyTimetable'] = $week['days'];
    $hp['weekStart'] = $week['start'];
    $hp['weekEnd'] = $week['end'];
    $today = date('Y-m-d');
    $hp['todayLessons'] = $hp['weeklyTimetable'][$today] ?? [];
    if ($hp['todayLessons'] === []) {
        $hp['todayLessons'] = homepage_load_today_lessons($pdo);
    }
    $hp['upcomingLessons'] = homepage_flatten_upcoming($hp['weeklyTimetable'], 8);

    $events = homepage_load_events($pdo);
    $hp['events'] = $events;
    $hp['news'] = $events;
    $hp['announcements'] = array_slice($events, 0, 5);
    $hp['achievements'] = homepage_load_achievements($pdo, $hp['teachers']);

    return $hp;
}

/**
 * @return list<array{quote:string,name:string,role:string}>
 */
function homepage_default_testimonials(): array
{
    return [
        [
            'quote' => 'The timetable, recordings and parent updates made it easy to support my child through IAL.',
            'name' => 'Parent, Kandy',
            'role' => 'IAL Mathematics',
        ],
        [
            'quote' => 'Teachers here explain Pearson papers clearly and the student portal keeps every class in one place.',
            'name' => 'Current student',
            'role' => 'IGCSE Science',
        ],
        [
            'quote' => 'A serious college atmosphere with modern tools — Talk with AI, live classes, and real exam planning.',
            'name' => 'Alumni parent',
            'role' => 'Edexcel pathway',
        ],
    ];
}

function homepage_count(PDO $pdo, string $sql): int
{
    try {
        return (int)$pdo->query($sql)->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}

/**
 * Distinct enrolled students per teacher (timetable classes, class tutor, enrollment tutor).
 *
 * @return array<int, int>
 */
function homepage_teacher_student_counts(PDO $pdo): array
{
    $parts = [];
    $candidates = [
        "
            SELECT tt.teacher_id AS teacher_id, se.student_id AS student_id
            FROM timetable tt
            INNER JOIN student_enrollments se ON se.class_id = tt.class_id
            INNER JOIN student_classes c ON c.id = tt.class_id AND c.deleted_at IS NULL
            WHERE tt.deleted_at IS NULL
              AND tt.teacher_id IS NOT NULL AND tt.teacher_id > 0
        ",
        "
            SELECT c.teacher_id AS teacher_id, se.student_id AS student_id
            FROM student_classes c
            INNER JOIN student_enrollments se ON se.class_id = c.id
            WHERE c.deleted_at IS NULL
              AND c.teacher_id IS NOT NULL AND c.teacher_id > 0
        ",
        "
            SELECT se.teacher_id AS teacher_id, se.student_id AS student_id
            FROM student_enrollments se
            WHERE se.teacher_id IS NOT NULL AND se.teacher_id > 0
        ",
    ];
    foreach ($candidates as $part) {
        try {
            $pdo->query($part . ' LIMIT 1');
            $parts[] = $part;
        } catch (Throwable $e) {
        }
    }
    if ($parts === []) {
        return [];
    }

    $counts = [];
    try {
        $sql = '
            SELECT teacher_id, COUNT(DISTINCT student_id) AS student_count
            FROM (' . implode(' UNION ', $parts) . ') teacher_students
            GROUP BY teacher_id
        ';
        foreach ($pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $id = (int)($row['teacher_id'] ?? 0);
            if ($id > 0) {
                $counts[$id] = (int)($row['student_count'] ?? 0);
            }
        }
    } catch (Throwable $e) {
        error_log('homepage_teacher_student_counts: ' . $e->getMessage());
    }
    return $counts;
}

/**
 * Same public-profile fields as teachers/profile.php.
 */
function homepage_teacher_profile_percent(array $teacher): int
{
    $checks = [
        trim((string)($teacher['bio'] ?? '')) !== '',
        trim((string)($teacher['qualifications'] ?? '')) !== '',
        ($teacher['experience_years'] ?? null) !== null && ($teacher['experience_years'] ?? '') !== '',
        trim((string)($teacher['achievements'] ?? '')) !== '',
        trim((string)($teacher['profile_background'] ?? '')) !== '',
    ];
    $total = count($checks);
    if ($total < 1) {
        return 0;
    }
    return (int)round((count(array_filter($checks)) / $total) * 100);
}

/**
 * @return list<array<string, mixed>>
 */
function homepage_load_teachers(PDO $pdo): array
{
    $queries = [
        "
            SELECT
                t.id,
                t.name,
                t.email,
                t.phone,
                t.photo,
                tp.bio,
                tp.qualifications,
                tp.experience_years,
                tp.achievements,
                tp.profile_background,
                COALESCE(tc.commission_percentage, 70) AS commission_percentage,
                GROUP_CONCAT(DISTINCT subject_pool.name ORDER BY subject_pool.name SEPARATOR ', ') AS subjects
            FROM teachers t
            LEFT JOIN teacher_profiles tp ON tp.teacher_id = t.id
            LEFT JOIN teacher_commission tc ON tc.teacher_id = t.id
            LEFT JOIN (
                SELECT ts.teacher_id, s.name
                FROM teacher_subjects ts
                INNER JOIN subjects s ON s.id = ts.subject_id AND s.deleted_at IS NULL
                UNION
                SELECT tt2.teacher_id, s2.name
                FROM timetable tt2
                INNER JOIN subjects s2 ON s2.id = tt2.subject_id AND s2.deleted_at IS NULL
                WHERE tt2.deleted_at IS NULL
            ) subject_pool ON subject_pool.teacher_id = t.id
            WHERE t.deleted_at IS NULL
              AND LOWER(t.name) <> 'default teacher'
            GROUP BY t.id, t.name, t.email, t.phone, t.photo, tp.bio, tp.qualifications,
                     tp.experience_years, tp.achievements, tp.profile_background, tc.commission_percentage
        ",
        "
            SELECT
                t.id,
                t.name,
                t.email,
                t.phone,
                t.photo,
                tp.bio,
                tp.qualifications,
                tp.experience_years,
                tp.achievements,
                tp.profile_background,
                70 AS commission_percentage,
                GROUP_CONCAT(DISTINCT subject_pool.name ORDER BY subject_pool.name SEPARATOR ', ') AS subjects
            FROM teachers t
            LEFT JOIN teacher_profiles tp ON tp.teacher_id = t.id
            LEFT JOIN (
                SELECT ts.teacher_id, s.name
                FROM teacher_subjects ts
                INNER JOIN subjects s ON s.id = ts.subject_id AND s.deleted_at IS NULL
                UNION
                SELECT tt2.teacher_id, s2.name
                FROM timetable tt2
                INNER JOIN subjects s2 ON s2.id = tt2.subject_id AND s2.deleted_at IS NULL
                WHERE tt2.deleted_at IS NULL
            ) subject_pool ON subject_pool.teacher_id = t.id
            WHERE t.deleted_at IS NULL
              AND LOWER(t.name) <> 'default teacher'
            GROUP BY t.id, t.name, t.email, t.phone, t.photo, tp.bio, tp.qualifications,
                     tp.experience_years, tp.achievements, tp.profile_background
        ",
        "
            SELECT
                t.id,
                t.name,
                t.email,
                t.phone,
                t.photo,
                tp.bio,
                tp.qualifications,
                tp.experience_years,
                tp.achievements,
                '' AS profile_background,
                70 AS commission_percentage,
                GROUP_CONCAT(DISTINCT subject_pool.name ORDER BY subject_pool.name SEPARATOR ', ') AS subjects
            FROM teachers t
            LEFT JOIN teacher_profiles tp ON tp.teacher_id = t.id
            LEFT JOIN (
                SELECT ts.teacher_id, s.name
                FROM teacher_subjects ts
                INNER JOIN subjects s ON s.id = ts.subject_id AND s.deleted_at IS NULL
                UNION
                SELECT tt2.teacher_id, s2.name
                FROM timetable tt2
                INNER JOIN subjects s2 ON s2.id = tt2.subject_id AND s2.deleted_at IS NULL
                WHERE tt2.deleted_at IS NULL
            ) subject_pool ON subject_pool.teacher_id = t.id
            WHERE t.deleted_at IS NULL
              AND LOWER(t.name) <> 'default teacher'
            GROUP BY t.id, t.name, t.email, t.phone, t.photo, tp.bio, tp.qualifications, tp.experience_years, tp.achievements
        ",
    ];

    $rows = [];
    $lastError = '';
    foreach ($queries as $sql) {
        try {
            $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
            $lastError = '';
            break;
        } catch (Throwable $e) {
            $lastError = $e->getMessage();
        }
    }
    if ($lastError !== '') {
        error_log('homepage_load_teachers: ' . $lastError);
        return [];
    }

    $studentCounts = homepage_teacher_student_counts($pdo);

    foreach ($rows as &$row) {
        $id = (int)($row['id'] ?? 0);
        $name = (string)($row['name'] ?? 'Teacher');
        $row['photo_path'] = teacherPhotoPath($id, $row['photo'] ?? null);
        $row['initials'] = teacherInitials($name);
        $row['avatar_color'] = teacherAvatarColor($name);
        $row['subject_list'] = function_exists('parseSubjects')
            ? parseSubjects($row['subjects'] ?? '')
            : array_values(array_filter(array_map('trim', explode(',', (string)($row['subjects'] ?? '')))));
        $row['whatsapp'] = function_exists('whatsappUrl') ? whatsappUrl($row['phone'] ?? null) : null;
        $row['profile_url'] = rtrim((string)BASE_URL, '/') . '/teachers/teacher_profile.php?id=' . $id;
        $row['student_count'] = $studentCounts[$id] ?? 0;
        $row['profile_percent'] = homepage_teacher_profile_percent($row);
        $row['commission_percentage'] = (float)($row['commission_percentage'] ?? 70);
        if (!function_exists('seo_teacher_photo_alt')) {
            require_once __DIR__ . '/seo.php';
        }
        $row['photo_alt'] = seo_teacher_photo_alt($name, $row['subject_list'] ?? []);
        $row['subject_label'] = seo_teacher_subject_label($row['subject_list'] ?? []);
    }
    unset($row);

    usort($rows, static function (array $a, array $b): int {
        $byStudents = ((int)($b['student_count'] ?? 0)) <=> ((int)($a['student_count'] ?? 0));
        if ($byStudents !== 0) {
            return $byStudents;
        }
        $byProfile = ((int)($b['profile_percent'] ?? 0)) <=> ((int)($a['profile_percent'] ?? 0));
        if ($byProfile !== 0) {
            return $byProfile;
        }
        $byCommission = ((float)($b['commission_percentage'] ?? 0)) <=> ((float)($a['commission_percentage'] ?? 0));
        if ($byCommission !== 0) {
            return $byCommission;
        }
        return strcasecmp((string)($a['name'] ?? ''), (string)($b['name'] ?? ''));
    });

    return $rows;
}

/**
 * @return list<array<string, mixed>>
 */
function homepage_load_programmes(PDO $pdo): array
{
    try {
        return $pdo->query('SELECT id, name, description FROM student_classes WHERE deleted_at IS NULL ORDER BY name ASC LIMIT 24')->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * @return list<array<string, mixed>>
 */
function homepage_load_qualifications(PDO $pdo): array
{
    try {
        $rows = $pdo->query('SELECT id, name, code FROM qualifications ORDER BY name ASC')->fetchAll(PDO::FETCH_ASSOC) ?: [];
        if ($rows !== []) {
            return $rows;
        }
    } catch (Throwable $e) {
    }
    return [
        ['id' => 0, 'name' => 'IGCSE', 'code' => 'IGCSE'],
        ['id' => 0, 'name' => 'International A Level', 'code' => 'IAL'],
    ];
}

/**
 * @return list<array<string, mixed>>
 */
function homepage_load_subjects(PDO $pdo): array
{
    try {
        return $pdo->query('SELECT id, name FROM subjects WHERE deleted_at IS NULL ORDER BY name ASC LIMIT 24')->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * @return array{start:?string,end:?string,days:array<string, list<array<string,mixed>>>}
 */
function homepage_load_weekly_timetable(PDO $pdo, int $weekOffset): array
{
    $empty = ['start' => null, 'end' => null, 'days' => []];
    try {
        $weekStart = (string)$pdo->query("
            SELECT DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY)
        ")->fetchColumn();
        if ($weekOffset !== 0) {
            $weekStart = date('Y-m-d', strtotime(($weekOffset > 0 ? '+' : '') . ($weekOffset * 7) . ' days', strtotime($weekStart)));
        }
        $weekEnd = date('Y-m-d', strtotime('+6 days', strtotime($weekStart)));

        $sql = "
            SELECT
                tt.id, tt.date, tt.start_time, tt.end_time,
                tt.class_id, tt.teacher_id, tt.subject_id, tt.room_id,
                sc.name AS class_name,
                s.name AS subject_name,
                t.name AS teacher_name,
                r.name AS room_name
            FROM timetable tt
            INNER JOIN student_classes sc ON sc.id = tt.class_id AND sc.deleted_at IS NULL
            INNER JOIN subjects s ON s.id = tt.subject_id AND s.deleted_at IS NULL
            INNER JOIN teachers t ON t.id = tt.teacher_id AND t.deleted_at IS NULL AND LOWER(t.name) <> 'default teacher'
            LEFT JOIN rooms r ON r.id = tt.room_id
            WHERE tt.deleted_at IS NULL
              AND tt.date BETWEEN :week_start AND :week_end
            ORDER BY tt.date ASC, tt.start_time ASC, tt.end_time ASC, sc.name ASC
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':week_start' => $weekStart, ':week_end' => $weekEnd]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $days = [];
        for ($day = 0; $day < 7; $day++) {
            $date = date('Y-m-d', strtotime('+' . $day . ' days', strtotime($weekStart)));
            $days[$date] = [];
        }
        foreach ($rows as $row) {
            $date = (string)($row['date'] ?? '');
            if (isset($days[$date])) {
                $days[$date][] = $row;
            }
        }
        return ['start' => $weekStart, 'end' => $weekEnd, 'days' => $days];
    } catch (Throwable $e) {
        error_log('homepage_load_weekly_timetable: ' . $e->getMessage());
        return $empty;
    }
}

/**
 * @return list<array<string, mixed>>
 */
function homepage_load_today_lessons(PDO $pdo): array
{
    try {
        $stmt = $pdo->query("
            SELECT
                tt.id, tt.date, tt.start_time, tt.end_time,
                sc.name AS class_name,
                s.name AS subject_name,
                t.name AS teacher_name,
                r.name AS room_name
            FROM timetable tt
            INNER JOIN student_classes sc ON sc.id = tt.class_id AND sc.deleted_at IS NULL
            INNER JOIN subjects s ON s.id = tt.subject_id AND s.deleted_at IS NULL
            INNER JOIN teachers t ON t.id = tt.teacher_id AND t.deleted_at IS NULL
            LEFT JOIN rooms r ON r.id = tt.room_id
            WHERE tt.deleted_at IS NULL AND tt.date = CURDATE()
            ORDER BY tt.start_time ASC
            LIMIT 12
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * @param array<string, list<array<string,mixed>>> $weekly
 * @return list<array<string, mixed>>
 */
function homepage_flatten_upcoming(array $weekly, int $limit = 8): array
{
    $out = [];
    $now = date('Y-m-d H:i:s');
    foreach ($weekly as $date => $lessons) {
        foreach ($lessons as $lesson) {
            $end = $date . ' ' . substr((string)($lesson['end_time'] ?? '00:00:00'), 0, 8);
            if ($end >= $now) {
                $out[] = $lesson;
            }
            if (count($out) >= $limit) {
                return $out;
            }
        }
    }
    if ($out === []) {
        foreach ($weekly as $lessons) {
            foreach ($lessons as $lesson) {
                $out[] = $lesson;
                if (count($out) >= $limit) {
                    return $out;
                }
            }
        }
    }
    return $out;
}

/**
 * @return list<array<string, mixed>>
 */
function homepage_load_events(PDO $pdo): array
{
    try {
        return $pdo->query("
            SELECT id, title, event_date, start_time, end_time, description
            FROM student_events
            WHERE event_date >= DATE_SUB(CURDATE(), INTERVAL 14 DAY)
            ORDER BY event_date ASC, start_time ASC
            LIMIT 12
        ")->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * @param list<array<string, mixed>> $teachers
 * @return list<array<string, mixed>>
 */
function homepage_load_achievements(PDO $pdo, array $teachers): array
{
    $items = [];
    try {
        $sql = "
            SELECT sp.metric, sp.score, sp.max_score, sp.note, sp.recorded_at,
                   c.name AS class_name, s.name AS subject_name
            FROM student_progress sp
            LEFT JOIN student_classes c ON c.id = sp.class_id
            LEFT JOIN subjects s ON s.id = sp.subject_id
            WHERE sp.max_score > 0 AND sp.published = 1
            ORDER BY (sp.score / sp.max_score) DESC, sp.recorded_at DESC
            LIMIT 8
        ";
        try {
            $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $ePub) {
            $rows = $pdo->query(str_replace(' AND sp.published = 1', '', $sql))->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }
        foreach ($rows as $row) {
            $max = (float)$row['max_score'];
            $pct = $max > 0 ? round(((float)$row['score'] / $max) * 100) : 0;
            $label = trim((string)($row['subject_name'] ?? $row['class_name'] ?? $row['metric'] ?? 'Result'));
            $items[] = [
                'title' => $label,
                'detail' => trim((string)($row['metric'] ?? 'Assessment')) . ' · ' . $pct . '%',
                'kind' => 'result',
            ];
        }
    } catch (Throwable $e) {
    }

    foreach ($teachers as $teacher) {
        $text = trim((string)($teacher['achievements'] ?? ''));
        if ($text === '') {
            continue;
        }
        $items[] = [
            'title' => (string)$teacher['name'],
            'detail' => homepage_clip($text, 140),
            'kind' => 'teacher',
        ];
        if (count($items) >= 10) {
            break;
        }
    }

    if ($items === []) {
        $items = [
            ['title' => 'Pearson Edexcel pathway', 'detail' => 'IGCSE and International A Level teaching in Kandy.', 'kind' => 'college'],
            ['title' => 'Exam-ready teaching', 'detail' => 'Official timetable import and student paper planner.', 'kind' => 'college'],
            ['title' => 'Digital learning', 'detail' => 'Portal, recordings, live class and Talk with AI.', 'kind' => 'college'],
        ];
    }

    return $items;
}
