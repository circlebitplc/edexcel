<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_staff();

header('Content-Type: application/json; charset=utf-8');

try {
    if($_SERVER['REQUEST_METHOD']!=='POST') throw new RuntimeException('POST required.');
    if(!verify_csrf_token($_POST['csrf_token']??'')) throw new RuntimeException('Invalid security token.');
    if(!is_admin()) throw new RuntimeException('Only an administrator can mark a lesson as paid.');

    $id=(int)($_POST['entry_id']??0);
    $services=TimetableServiceFactory::services($pdo);
    $result=$services['payment']->markPaid($id, static function(array $entry): void {
                if (!is_admin()) throw new RuntimeException('Only an administrator can mark a lesson as paid.');
            });

    $sms = is_array($result['sms'] ?? null) ? $result['sms'] : [];
    $smsStatus = (string)($sms['status'] ?? 'failed');
    $smsNotice = (string)($sms['sms_notice'] ?? 'Payment SMS could not be sent.');
    $smsSent = $smsStatus === 'sent' || $smsStatus === 'resent' || $smsStatus === 'already_sent';

    echo json_encode([
        'success'=>true,
        'id'=>$id,
        'payment_status'=>'paid',
        'payment_date'=>$result['payment_date'],
        'amount'=>$result['amount'],
        'sms_status'=>$smsStatus,
        'sms_notice'=>$smsNotice,
        'sms_detail'=>(string)($sms['sms_detail'] ?? ''),
        'notification_sent'=>$smsSent,
        'notification_message'=>$smsSent ? null : $smsNotice
    ]);
} catch(Throwable $e) {
    http_response_code(400);
    echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
}
