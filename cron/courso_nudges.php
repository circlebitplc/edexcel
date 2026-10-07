<?php
declare(strict_types=1);

/**
 * Courso AI study / streak nudges. Run hourly from crontab.
 * php cron/courso_nudges.php
 */

date_default_timezone_set('Asia/Colombo');

require_once __DIR__ . '/../config/cron_http_guard.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/campus.php';
require_once __DIR__ . '/../config/courso.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Edexcel\Services\CoursoLearnerService;

if (!($pdo instanceof PDO)) {
    exit(1);
}

ensure_courso_schema($pdo);

$now = new DateTimeImmutable('now', new DateTimeZone('Asia/Colombo'));
$hour = (int)$now->format('H');
$dow = (int)$now->format('w');
$today = $now->format('Y-m-d');

$stmt = $pdo->query("
    SELECT student_id, goals, notify_study, notify_streak, study_days, study_hour
    FROM courso_learner_profiles
    WHERE notify_study = 1 OR notify_streak = 1
");
$rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
$learner = new CoursoLearnerService($pdo);

foreach ($rows as $row) {
    $studentId = (int)$row['student_id'];
    $studyHour = (int)$row['study_hour'];
    $days = array_map('intval', explode(',', (string)$row['study_days']));
    if (!in_array($dow, $days, true)) {
        continue;
    }
    if ($hour !== $studyHour && $hour !== $studyHour + 1) {
        continue;
    }

    $exists = $pdo->prepare('SELECT id FROM courso_nudge_log WHERE student_id = ? AND nudge_date = ? AND kind = ?');
    $exists->execute([$studentId, $today, 'study']);
    if ($exists->fetchColumn()) {
        continue;
    }

    $dates = [];
    try {
        $d = $pdo->prepare("SELECT DISTINCT DATE(created_at) FROM courso_activity WHERE student_id = ? ORDER BY 1 DESC LIMIT 14");
        $d->execute([$studentId]);
        $dates = $d->fetchAll(PDO::FETCH_COLUMN) ?: [];
    } catch (Throwable $e) {
        $dates = [];
    }
    $streak = CoursoLearnerService::streakFromDates($dates, $today);
    $practisedToday = in_array($today, $dates, true);
    if ($practisedToday) {
        continue;
    }

    $title = 'Study time';
    $message = 'A short practice set or recap will keep your learning streak going.';
    if ((int)$row['notify_streak'] === 1 && $streak > 0) {
        $message = 'You are on a ' . $streak . '-day streak. Five questions in Talk with AI will keep it alive.';
    }
    $goal = trim((string)($row['goals'] ?? ''));
    if ($goal !== '') {
        $message .= ' Goal: ' . mb_substr($goal, 0, 120);
    }

    try {
        $pdo->prepare("
            INSERT INTO student_notifications (student_id, title, message, type, link)
            VALUES (?, ?, ?, 'courso', ?)
        ")->execute([
            $studentId,
            $title,
            $message,
            '/student/dashboard.php?tab=courso',
        ]);
        $pdo->prepare("
            INSERT INTO courso_nudge_log (student_id, nudge_date, kind)
            VALUES (?, ?, 'study')
        ")->execute([$studentId, $today]);
    } catch (Throwable $e) {
        error_log('Courso nudge: ' . $e->getMessage());
    }
}
