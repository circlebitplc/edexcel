<?php
declare(strict_types=1);

namespace Edexcel\Services;

use PDO;
use RuntimeException;
use Throwable;

/**
 * Manual bank-transfer slips for a lesson fee. Access stays locked until staff verify.
 */
final class BankTransferService
{
    public const GATEWAY = 'bank';
    public const MAX_BYTES = 8388608;
    public const ALLOWED_EXT = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];

    public function __construct(private PDO $pdo)
    {
        if (function_exists('ensure_recordings_schema')) {
            ensure_recordings_schema($this->pdo);
        }
    }

    public static function storageDir(): string
    {
        return dirname(__DIR__, 2) . '/files/payment_slips';
    }

    public static function isAllowedSlip(string $mime, string $filename): bool
    {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if (!in_array($ext, self::ALLOWED_EXT, true)) {
            return false;
        }
        $mime = strtolower(trim($mime));
        $ok = [
            'image/jpeg' => true,
            'image/png' => true,
            'image/webp' => true,
            'application/pdf' => true,
            'application/octet-stream' => $ext === 'pdf',
        ];
        return !empty($ok[$mime]);
    }

    /**
     * @param array<string,mixed> $lesson
     * @param array<string,mixed> $file $_FILES['slip']
     * @return array<string,mixed>
     */
    public function submitSlip(
        int $studentId,
        array $lesson,
        array $file,
        float $amount,
        ?int $recordingId = null,
        ?int $lessonFeeId = null,
        ?int $parentId = null,
        string $note = ''
    ): array {
        if ($studentId < 1 || StudentLessonFeeService::timetableIdFrom($lesson) < 1) {
            throw new RuntimeException('Missing student or lesson.');
        }
        if ($amount <= 0) {
            throw new RuntimeException('This lesson has no fee to pay.');
        }
        if (!function_exists('bank_transfer_ready')) {
            require_once dirname(__DIR__, 2) . '/config/payment.php';
        }
        if (!bank_transfer_ready($this->pdo)) {
            throw new RuntimeException('Bank slip payment is turned off.');
        }
        $stored = $this->storeUpload($file);
        $payments = new PaymentTransactionService($this->pdo);
        try {
            $txn = $payments->recordBankSlip(
                $studentId,
                $lesson,
                $amount,
                $stored['path'],
                $stored['original'],
                $recordingId,
                $lessonFeeId,
                $parentId,
                $note
            );
        } catch (Throwable $e) {
            $this->deleteStored($stored['path']);
            throw $e;
        }

        $this->notifySubmitted($txn, $lesson, $studentId);
        return $txn;
    }

    /**
     * @return array<string,mixed>
     */
    public function verify(int $transactionId, int $staffUserId): array
    {
        $payments = new PaymentTransactionService($this->pdo);
        $txn = $payments->findById($transactionId);
        if (!$txn || strtolower((string)$txn['gateway']) !== self::GATEWAY) {
            throw new RuntimeException('Bank slip not found.');
        }
        if (strtolower((string)$txn['status']) === 'paid') {
            return $txn;
        }
        if (!in_array(strtolower((string)$txn['status']), ['pending', 'initiated'], true)) {
            throw new RuntimeException('That slip can no longer be confirmed.');
        }

        $lessonStmt = $this->pdo->prepare("
            SELECT tt.*, s.name AS subject_name, c.name AS class_name
            FROM timetable tt
            JOIN subjects s ON s.id = tt.subject_id
            JOIN student_classes c ON c.id = tt.class_id
            WHERE tt.id = ?
            LIMIT 1
        ");
        $lessonStmt->execute([(int)$txn['timetable_id']]);
        $lesson = $lessonStmt->fetch(PDO::FETCH_ASSOC);
        if (!$lesson) {
            throw new RuntimeException('Lesson not found.');
        }
        if (!$this->staffMayManage($staffUserId, $lesson)) {
            throw new RuntimeException('You cannot confirm this slip.');
        }

        $fees = new StudentLessonFeeService($this->pdo);
        $resolved = $fees->resolve((int)$txn['student_id'], $lesson, isset($txn['recording_id']) ? (int)$txn['recording_id'] : null);
        if (StudentLessonFeeService::isUnlocked((string)$resolved['status'], (bool)$resolved['covered_by_monthly'])) {
            throw new RuntimeException('This lesson is already paid. Reject this slip if it is a duplicate.');
        }
        $payments->markBankVerified($transactionId, $staffUserId);
        $fees->markPaid(
            (int)$txn['student_id'],
            (int)$txn['timetable_id'],
            (float)$txn['amount'],
            $transactionId,
            isset($txn['recording_id']) ? (int)$txn['recording_id'] : null
        );
        $fresh = $payments->findById($transactionId) ?? $txn;
        $payments->notifyBankVerified($fresh);
        return $fresh;
    }

    public function reject(int $transactionId, int $staffUserId, string $reason = ''): void
    {
        $payments = new PaymentTransactionService($this->pdo);
        $txn = $payments->findById($transactionId);
        if (!$txn || strtolower((string)$txn['gateway']) !== self::GATEWAY) {
            throw new RuntimeException('Bank slip not found.');
        }
        if (strtolower((string)$txn['status']) === 'paid') {
            throw new RuntimeException('A confirmed slip cannot be rejected. Unmark the class fee first.');
        }
        $lessonStmt = $this->pdo->prepare('SELECT * FROM timetable WHERE id = ? LIMIT 1');
        $lessonStmt->execute([(int)$txn['timetable_id']]);
        $lesson = $lessonStmt->fetch(PDO::FETCH_ASSOC) ?: [];
        if ($lesson !== [] && !$this->staffMayManage($staffUserId, $lesson)) {
            throw new RuntimeException('You cannot reject this slip.');
        }
        $reason = trim($reason);
        if ($reason === '') {
            $reason = 'Slip was not accepted. Please upload a clearer copy or pay with the card.';
        }
        $payments->rejectBankSlip($transactionId, $staffUserId, $reason);
        $this->notifyRejected((int)$txn['student_id'], (int)$txn['timetable_id'], $reason);
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function pendingList(?int $teacherId = null): array
    {
        $sql = "
            SELECT
                p.*,
                s.name AS subject_name,
                c.name AS class_name,
                tt.date,
                tt.start_time,
                tt.teacher_id,
                tt.substitute_teacher_id,
                COALESCE(NULLIF(TRIM(sp.full_name), ''), u.username) AS student_name
            FROM payment_transactions p
            JOIN timetable tt ON tt.id = p.timetable_id
            JOIN subjects s ON s.id = tt.subject_id
            JOIN student_classes c ON c.id = tt.class_id
            JOIN users u ON u.id = p.student_id
            LEFT JOIN student_profiles sp ON sp.user_id = u.id
            WHERE p.gateway = 'bank' AND p.status = 'pending'
        ";
        $params = [];
        if ($teacherId !== null && $teacherId > 0) {
            $sql .= ' AND (tt.teacher_id = ? OR tt.substitute_teacher_id = ?)';
            $params[] = $teacherId;
            $params[] = $teacherId;
        }
        $sql .= ' ORDER BY p.id ASC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @return array<string,mixed>|null
     */
    public function openSlip(int $studentId, int $timetableId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM payment_transactions
            WHERE student_id = ? AND timetable_id = ?
              AND gateway = 'bank' AND status = 'pending'
            ORDER BY id DESC
            LIMIT 1
        ");
        $stmt->execute([$studentId, $timetableId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * @param array<string,mixed> $txn
     */
    public function staffMayViewTxn(int $staffUserId, array $txn): bool
    {
        $lessonStmt = $this->pdo->prepare('SELECT * FROM timetable WHERE id = ? LIMIT 1');
        $lessonStmt->execute([(int)($txn['timetable_id'] ?? 0)]);
        $lesson = $lessonStmt->fetch(PDO::FETCH_ASSOC);
        return is_array($lesson) && $this->staffMayManage($staffUserId, $lesson);
    }

    /**
     * @param array<string,mixed> $lesson
     */
    public function staffMayManage(int $staffUserId, array $lesson): bool
    {
        if ($staffUserId < 1) {
            return false;
        }
        if (function_exists('is_admin') && is_admin()) {
            return true;
        }
        $teacherId = 0;
        if (function_exists('is_teacher') && is_teacher()) {
            $teacherId = (int)($_SESSION['teacher_id'] ?? 0);
        }
        if ($teacherId < 1) {
            return false;
        }
        return RecordingAccessService::teacherCanManageLesson(
            $teacherId,
            (int)($lesson['teacher_id'] ?? $lesson['lesson_teacher_id'] ?? 0),
            false,
            (int)($lesson['substitute_teacher_id'] ?? 0)
        );
    }

    /**
     * @param array<string,mixed> $file
     * @return array{path:string,original:string}
     */
    private function storeUpload(array $file): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Please choose a photo or PDF of the bank slip.');
        }
        $tmp = (string)($file['tmp_name'] ?? '');
        $original = basename((string)($file['name'] ?? 'slip'));
        $size = (int)($file['size'] ?? 0);
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            throw new RuntimeException('The slip upload failed. Try again.');
        }
        if ($size < 1 || $size > self::MAX_BYTES) {
            throw new RuntimeException('The slip must be a photo or PDF under 8 MB.');
        }
        $mime = '';
        if (class_exists(\finfo::class)) {
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $mime = (string)$finfo->file($tmp);
        }
        if ($mime === '') {
            $mime = (string)($file['type'] ?? '');
        }
        if (!self::isAllowedSlip($mime, $original)) {
            throw new RuntimeException('Upload a JPG, PNG, WEBP, or PDF of the bank slip.');
        }
        $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
        if ($ext === 'jpeg') {
            $ext = 'jpg';
        }
        $dir = self::storageDir();
        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new RuntimeException('Could not save the slip. Ask the office to check file storage.');
        }
        $name = bin2hex(random_bytes(16)) . '.' . $ext;
        $dest = $dir . '/' . $name;
        if (!@move_uploaded_file($tmp, $dest)) {
            throw new RuntimeException('Could not save the slip. Try again.');
        }
        @chmod($dest, 0640);
        return ['path' => $name, 'original' => mb_substr($original, 0, 180)];
    }

    private function deleteStored(string $name): void
    {
        $name = basename($name);
        if ($name === '' || $name === '.' || $name === '..') {
            return;
        }
        $path = self::storageDir() . '/' . $name;
        if (is_file($path)) {
            @unlink($path);
        }
    }

    /**
     * @param array<string,mixed> $txn
     * @param array<string,mixed> $lesson
     */
    private function notifySubmitted(array $txn, array $lesson, int $studentId): void
    {
        $subject = (string)($lesson['subject_name'] ?? 'class');
        $when = !empty($lesson['date']) ? date('d M Y', strtotime((string)$lesson['date'])) : '';
        $amount = number_format((float)($txn['amount'] ?? 0), 2);
        if (function_exists('campus_portal_notify')) {
            campus_portal_notify(
                $this->pdo,
                [$studentId],
                'Bank slip received',
                'Your bank slip for ' . $subject . ($when !== '' ? ' on ' . $when : '') . ' is with the office. The class unlocks after they confirm it.',
                'class.php?lesson=' . (int)($txn['timetable_id'] ?? 0)
            );
        }
        if (function_exists('campus_notify_admins')) {
            campus_notify_admins(
                $this->pdo,
                'Bank slip to verify',
                $subject . ($when !== '' ? ' · ' . $when : '') . ' · Rs ' . $amount,
                'campus/bank_slips.php'
            );
        }
    }

    private function notifyRejected(int $studentId, int $timetableId, string $reason): void
    {
        if (!function_exists('campus_portal_notify')) {
            return;
        }
        campus_portal_notify(
            $this->pdo,
            [$studentId],
            'Bank slip not accepted',
            $reason,
            'class.php?lesson=' . $timetableId
        );
    }
}
