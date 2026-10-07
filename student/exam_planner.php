<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/config.php';

use Edexcel\Services\OfficialExamService;

require_student();

$studentId = (int)($_SESSION['user_id'] ?? $_SESSION['student_id'] ?? 0);

if (($_GET['action'] ?? '') === 'ics' && $studentId > 0) {
    try {
        $service = new OfficialExamService($pdo);
        $service->ensureSchema();
        $ics = $service->icsCalendar($service->studentTimetable($studentId));
        header('Content-Type: text/calendar; charset=utf-8');
        header('Content-Disposition: attachment; filename="edexcel-exam-timetable.ics"');
        echo $ics;
    } catch (Throwable $e) {
        $_SESSION['student_error'] = 'Could not build the calendar file.';
        header('Location: dashboard.php?tab=exams');
    }
    exit;
}

$returnSeries = (int)($_POST['series'] ?? 0);
$returnSubject = trim((string)($_POST['subject'] ?? ''));
$returnQ = trim((string)($_POST['q'] ?? ''));
$returnUrl = 'dashboard.php?tab=exams';
if ($returnSeries > 0) {
    $returnUrl .= '&series=' . $returnSeries;
}
if ($returnSubject !== '') {
    $returnUrl .= '&subject=' . rawurlencode($returnSubject);
}
if ($returnQ !== '') {
    $returnUrl .= '&q=' . rawurlencode($returnQ);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $studentId < 1) {
    header('Location: dashboard.php?tab=exams');
    exit;
}

if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $_SESSION['student_error'] = 'Your security token expired. Please refresh and try again.';
    header('Location: ' . $returnUrl);
    exit;
}

try {
    $service = new OfficialExamService($pdo);
    $service->ensureSchema();
    $action = (string)($_POST['action'] ?? '');
    if ($action === 'add') {
        $service->addSelection($studentId, (int)($_POST['exam_id'] ?? 0));
        $_SESSION['student_success'] = 'Exam added to your timetable.';
    } elseif ($action === 'add_many') {
        $ids = array_map('intval', (array)($_POST['exam_ids'] ?? []));
        $added = $service->addSelections($studentId, $ids);
        $_SESSION['student_success'] = $added > 0
            ? ($added === 1 ? '1 exam added to your timetable.' : $added . ' exams added to your timetable.')
            : 'Those exams were already on your timetable.';
    } elseif ($action === 'add_subject') {
        $added = $service->addSubjectPapers(
            $studentId,
            (int)($_POST['series'] ?? 0),
            (string)($_POST['subject_name'] ?? '')
        );
        $_SESSION['student_success'] = $added > 0
            ? ($added === 1 ? '1 paper added for that subject.' : $added . ' papers added for that subject.')
            : 'Those papers were already on your timetable.';
    } elseif ($action === 'remove') {
        $service->removeSelection($studentId, (int)($_POST['exam_id'] ?? 0));
        $_SESSION['student_success'] = 'Exam removed from your timetable.';
    } else {
        $_SESSION['student_error'] = 'Unknown action.';
    }
} catch (Throwable $e) {
    $_SESSION['student_error'] = $e->getMessage();
}

header('Location: ' . $returnUrl);
exit;
