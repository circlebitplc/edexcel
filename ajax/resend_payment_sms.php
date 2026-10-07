<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_staff();

use Edexcel\Services\TeacherPaymentSmsService;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

try {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        throw new RuntimeException('POST required.');
    }
    if (!is_admin()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Only an administrator can resend a payment SMS.']);
        exit;
    }
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Invalid security token.']);
        exit;
    }

    $kind = (string)($_POST['kind'] ?? '');
    if (!in_array($kind, [TeacherPaymentSmsService::KIND_TIMETABLE, TeacherPaymentSmsService::KIND_PAYOUT], true)) {
        throw new RuntimeException('Invalid payment.');
    }
    $id = (int)($_POST['id'] ?? 0);
    if ($id < 1) {
        throw new RuntimeException('Invalid payment.');
    }
    $action = (string)($_POST['action'] ?? 'preview');
    if ($action === 'preview') {
        $preview = TeacherPaymentSmsService::preview($pdo, $kind, $id);
        echo json_encode([
            'success' => (bool)($preview['success'] ?? false),
            'can_send' => (bool)($preview['can_send'] ?? false),
            'message' => (string)($preview['message'] ?? ''),
            'warning' => (string)($preview['warning'] ?? ''),
            'error' => ($preview['success'] ?? false) ? null : (string)($preview['warning'] ?? 'Payment SMS could not be prepared.'),
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if ($action !== 'send') {
        throw new RuntimeException('Invalid request.');
    }

    $result = $kind === TeacherPaymentSmsService::KIND_PAYOUT
        ? TeacherPaymentSmsService::notifyPayoutPaid($pdo, $id, (int)($_SESSION['user_id'] ?? 0), true)
        : TeacherPaymentSmsService::notifyTimetablePaid($pdo, $id, (int)($_SESSION['user_id'] ?? 0), true);

    $status = (string)($result['status'] ?? 'failed');
    echo json_encode([
        'success' => true,
        'payment_status' => 'paid',
        'sms_status' => $status,
        'sms_notice' => (string)($result['sms_notice'] ?? 'Payment SMS could not be sent.'),
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
