<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/notifications.php';
require_login();

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

    $notificationSent=false;
    $notificationMessage=null;
    try {
        $notificationSent=(bool)notify_payment(
            $pdo,(int)$result['teacher_id'],(float)$result['amount']
        );
        if(!$notificationSent) {
            $notificationMessage='Payment was marked as paid, but the WhatsApp notification was not sent.';
        }
    } catch(Throwable $notificationError) {
        error_log('Payment notification failed for timetable ID '.$id.': '.$notificationError->getMessage());
        $notificationMessage='Payment was marked as paid, but the WhatsApp notification failed.';
    }

    echo json_encode([
        'success'=>true,
        'id'=>$id,
        'payment_status'=>'paid',
        'payment_date'=>$result['payment_date'],
        'amount'=>$result['amount'],
        'notification_sent'=>$notificationSent,
        'notification_message'=>$notificationMessage
    ]);
} catch(Throwable $e) {
    http_response_code(400);
    echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
}
