<?php
declare(strict_types=1);
use Edexcel\Services\RecordingAccessService;
$catalogue = $recordingCatalogue ?? [];
$libraryVideos = $libraryVideos ?? [];
$onlineLessonMap = $onlineLessonMap ?? [];
if ($onlineLessonMap === [] && $catalogue !== [] && isset($pdo) && $pdo instanceof PDO) {
    $ids = array_map(static fn (array $row): int => (int)($row['timetable_id'] ?? 0), $catalogue);
    $onlineLessonMap = (new \Edexcel\Services\OnlineLessonService($pdo))->mapForLessons($ids);
}
?>
<div class="mb-4">
    <h3 class="fw-bold mb-1"><i class="bi bi-camera-reels me-2 text-primary"></i>Class recordings</h3>
    <p class="text-muted mb-0">Only students who have paid for the lesson can join the live class or access the class recording. If you have not made the payment yet, please click the <strong>Pay Now</strong> button to complete your payment and get access.</p>
</div>

<div class="card border shadow-sm rounded-4 mb-4">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Subject</th>
                    <th>Teacher</th>
                    <th>Class</th>
                    <th>Attendance</th>
                    <th>Fee</th>
                    <th>Recording</th>
                    <th>Access</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($catalogue as $row): ?>
                <?php
                    $att = (string)($row['attendance_status'] ?? '');
                    $fee = (string)($row['fee_status'] ?? 'pending');
                    $rec = (string)($row['recording_status'] ?? '');
                    $state = (string)($row['access_state'] ?? '');
                    $action = 'View';
                    $href = BASE_URL . 'student/recording.php?id=' . (int)$row['recording_id'];
                    $ol = $onlineLessonMap[(int)($row['timetable_id'] ?? 0)] ?? null;
                    $publishedLesson = $ol && !empty($ol['published']);
                    if ($publishedLesson) {
                        $href = student_online_lesson_url((int)$row['timetable_id'], 0, 'recordings');
                    }
                    if ($state === RecordingAccessService::ACCESS_GRANTED) {
                        $action = $publishedLesson ? 'Open lesson' : 'Watch recording';
                    } elseif ($state === RecordingAccessService::PAYMENT_REQUIRED) {
                        $action = 'Pay Now';
                    } elseif ($state === RecordingAccessService::RECORDING_PROCESSING) {
                        $action = 'Processing';
                    }
                ?>
                <tr>
                    <td><?= student_e(date('d M Y', strtotime((string)$row['date']))) ?></td>
                    <td><?= student_e((string)$row['subject_name']) ?></td>
                    <td><?= student_e((string)$row['teacher_name']) ?></td>
                    <td><?= student_e((string)$row['class_name']) ?></td>
                    <td><?= student_e($att !== '' ? ucfirst($att) : 'Not marked') ?></td>
                    <td><?= student_e($row['covered_by_monthly'] ? 'Paid' : ucfirst($fee)) ?></td>
                    <td><?= student_e(ucfirst($rec)) ?></td>
                    <td><?= student_e(str_replace('_', ' ', strtolower($state))) ?></td>
                    <td>
                        <a class="btn btn-sm btn-primary" href="<?= student_e($href) ?>"><?= student_e($action) ?></a>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if ($catalogue === []): ?>
                <tr><td colspan="9" class="text-muted p-4">No class recordings yet.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($libraryVideos !== []): ?>
<div class="mb-3">
    <h4 class="h5 fw-bold">Teacher resources</h4>
    <p class="text-muted small">Videos your teachers shared with your class. These are not lesson recordings.</p>
</div>
<div class="row g-3">
    <?php foreach ($libraryVideos as $v): ?>
        <div class="col-md-6 col-lg-4">
            <div class="card border shadow-sm rounded-4 h-100">
                <div class="card-body">
                    <h5 class="h6"><?= student_e((string)$v['title']) ?></h5>
                    <div class="small text-muted mb-3"><?= student_e((string)($v['teacher_name'] ?? '')) ?></div>
                    <a class="btn btn-sm btn-outline-primary" href="<?= student_e(BASE_URL . 'student/library_video.php?id=' . (int)$v['id']) ?>">Watch</a>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>
