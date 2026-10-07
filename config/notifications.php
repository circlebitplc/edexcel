<?php
// config/notifications.php
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/evolution.php';
require_once __DIR__ . '/whatsapp_gateway.php';
require_once __DIR__ . '/../vendor/autoload.php';

function get_notification_settings($pdo) {
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings 
                         WHERE setting_key IN ('whatsapp_enabled', 'evolution_api_url', 'evolution_api_key', 'evolution_instance', 'evolution_admin_number')");
    $settings = [];
    while ($row = $stmt->fetch()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    $stmt = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'twilio_whatsapp_to'");
    $row = $stmt->fetch();
    if ($row) {
        $settings['evolution_admin_number'] = $row['setting_value'] ?? '';
    }
    return $settings;
}

function log_notification($pdo, $recipient, $type, $message, $status, $error = null) {
    // Remove 4-byte UTF-8 characters (emojis) to avoid utf8 column errors
    $message = preg_replace('/[\x{10000}-\x{10FFFF}]/u', '', $message);
    // Also remove any other invalid characters and truncate if needed
    $message = function_exists('mb_convert_encoding') ? mb_convert_encoding($message, 'UTF-8', 'UTF-8') : $message;
    // Optionally trim to prevent overflow of the column
    if (strlen($message) > 65535) {
        $message = substr($message, 0, 65535);
    }
    if ($error && strlen($error) > 65535) {
        $error = substr($error, 0, 65535);
    }
    $stmt = $pdo->prepare("INSERT INTO notifications_log (recipient, type, message, status, error) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$recipient, $type, $message, $status, $error]);
    return $pdo->lastInsertId();
}

function update_notification_status($pdo, $log_id, $status, $error = null) {
    if ($error !== null) {
        $stmt = $pdo->prepare("UPDATE notifications_log SET status = ?, error = ?, sent_at = IF(? = 'sent', NOW(), sent_at) WHERE id = ?");
        $stmt->execute([$status, $error, $status, $log_id]);
    } else {
        $stmt = $pdo->prepare("UPDATE notifications_log SET status = ?, sent_at = IF(? = 'sent', NOW(), sent_at) WHERE id = ?");
        $stmt->execute([$status, $status, $log_id]);
    }
}

function send_whatsapp_last_provider_id(?string $set = null): string
{
    static $last = '';
    if ($set !== null) {
        $last = $set;
    }
    return $last;
}

function send_whatsapp_last_error(?string $set = null): string
{
    static $last = '';
    if ($set !== null) {
        $last = $set;
    }
    return $last;
}

function send_whatsapp($pdo, $to, $message, $type = 'general') {
    send_whatsapp_last_provider_id('');
    send_whatsapp_last_error('');
    $settings = get_notification_settings($pdo);
    if (empty($settings['whatsapp_enabled']) || $settings['whatsapp_enabled'] != '1') {
        send_whatsapp_last_error('WhatsApp is turned off in Settings.');
        log_notification($pdo, $to, $type, $message, 'failed', 'WhatsApp disabled');
        return false;
    }

    $to = preg_replace('/[^0-9]/', '', $to);
    if (empty($to)) {
        send_whatsapp_last_error('Phone number is missing.');
        log_notification($pdo, $to, $type, $message, 'failed', 'Empty phone');
        return false;
    }

    $to = ltrim($to, '0');
    if (strlen($to) === 9) {
        $to = '94' . $to;
    }
    if (strlen($to) < 10) {
        send_whatsapp_last_error('Phone number is too short.');
        log_notification($pdo, $to, $type, $message, 'failed', 'Number too short');
        return false;
    }

    try {
        $opt = $pdo->prepare("SELECT opted_out FROM whatsapp_bot_contacts WHERE phone = ? LIMIT 1");
        $opt->execute([$to]);
        if ((int)$opt->fetchColumn() === 1) {
            send_whatsapp_last_error('This contact has opted out of WhatsApp messages.');
            log_notification($pdo, $to, $type, $message, 'failed', 'opted_out');
            return false;
        }
    } catch (Throwable $e) {
    }

    try {
        $api = whatsapp_sender($pdo);
        $resp = $api->sendText($to, $message);
        $wamid = '';
        if (is_array($resp)) {
            $wamid = (string)($resp['messages'][0]['id'] ?? $resp['key']['id'] ?? $resp['messageId'] ?? $resp['id'] ?? '');
        }
        if ($wamid !== '') {
            send_whatsapp_last_provider_id($wamid);
        }
        log_notification($pdo, $to, $type, $message, 'sent');
        return true;
    } catch (Throwable $e) {
        $friendly = 'WhatsApp could not send this message. It will retry from the outbox if configured.';
        send_whatsapp_last_error($friendly);
        log_notification($pdo, $to, $type, $message, 'failed', $e->getMessage());
        try {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS whatsapp_outbox (
                    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    phone VARCHAR(20) NOT NULL,
                    message TEXT NOT NULL,
                    type VARCHAR(50) NOT NULL DEFAULT 'general',
                    attempts INT NOT NULL DEFAULT 0,
                    next_attempt_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    last_error TEXT NULL,
                    sent_at DATETIME NULL,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    KEY idx_outbox_next (sent_at, next_attempt_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
            $pdo->prepare("INSERT INTO whatsapp_outbox (phone, message, type, next_attempt_at) VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 2 MINUTE))")
                ->execute([$to, $message, $type]);
        } catch (Throwable $ignored) {
        }
        return false;
    }
}

function notify_class_change($pdo, $teacher_id, $action, $timetable_data) {
    $stmt = $pdo->prepare("SELECT phone FROM teachers WHERE id = ?");
    $stmt->execute([$teacher_id]);
    $teacher = $stmt->fetch();
    if (!$teacher || empty($teacher['phone'])) {
        return false;
    }

    $teacher_phone = $teacher['phone'];
    $date = date('D, d M Y', strtotime($timetable_data['date']));
    $time = date('h:i A', strtotime($timetable_data['start_time'])) . ' - ' . date('h:i A', strtotime($timetable_data['end_time']));
    $subject = $timetable_data['subject_name'] ?? $timetable_data['subject_id'];
    $class = $timetable_data['class_name'] ?? $timetable_data['class_id'];
    $room = $timetable_data['room_name'] ?? $timetable_data['room_id'];

    $action_text = [
        'add' => 'added',
        'edit' => 'updated',
        'delete' => 'deleted'
    ][$action] ?? 'changed';

    $msg = "📚 *Class $action_text*\n\n" .
           "📅 Date: $date\n" .
           "⏰ Time: $time\n" .
           "📖 Subject: $subject\n" .
           "👥 Class: $class\n" .
           "🏫 Room: $room\n\n" .
           "Logged in to view details: " . edexcel_public_app_url() . "/timetable/index.php";

    return send_whatsapp($pdo, $teacher_phone, $msg, 'class_' . $action);
}

function notify_class_students(PDO $pdo, int $classId, string $action, array $timetable_data): void
{
    require_once __DIR__ . '/campus.php';
    if ($classId < 1) {
        return;
    }
    $date = date('D, d M Y', strtotime((string)($timetable_data['date'] ?? 'now')));
    $time = '';
    if (!empty($timetable_data['start_time'])) {
        $time = date('h:i A', strtotime((string)$timetable_data['start_time']));
        if (!empty($timetable_data['end_time'])) {
            $time .= ' - ' . date('h:i A', strtotime((string)$timetable_data['end_time']));
        }
    }
    $subject = (string)($timetable_data['subject_name'] ?? '');
    $class = (string)($timetable_data['class_name'] ?? '');
    $action_text = ['add' => 'added', 'edit' => 'updated', 'delete' => 'removed'][$action] ?? 'changed';
    $msg = "Timetable {$action_text}\n\n{$subject}\n{$class}\n{$date}" . ($time !== '' ? "\n{$time}" : '');
    $ids = campus_class_student_ids($pdo, $classId);
    campus_notify_students($pdo, $ids, $msg, 'TIMETABLE');
    campus_portal_notify($pdo, $ids, 'Timetable ' . $action_text, trim($subject . ' · ' . $date), 'dashboard.php?tab=timetable');
}

function notify_payment($pdo, $teacher_id, $amount) {
    $stmt = $pdo->prepare("SELECT phone, name FROM teachers WHERE id = ?");
    $stmt->execute([$teacher_id]);
    $teacher = $stmt->fetch();

    if (!$teacher || empty($teacher['phone'])) {
        $error = "Teacher phone missing for ID $teacher_id";
        log_notification($pdo, 'unknown', 'payment', 'Payment notification failed', 'failed', $error);
        error_log($error);
        return false;
    }

    $teacher_phone = $teacher['phone'];
    $msg = "💳 *Payment Received*\n\n" .
           "Teacher: " . $teacher['name'] . "\n" .
           "Amount: Rs " . number_format($amount) . "\n" .
           "Date: " . date('d M Y') . "\n\n" .
           "Thank you!";

    $settings = get_notification_settings($pdo);
    $admin_phone = $settings['evolution_admin_number'] ?? '';
    if (!empty($admin_phone)) {
        send_whatsapp($pdo, $admin_phone, $msg, 'payment_admin');
    }

    return send_whatsapp($pdo, $teacher_phone, $msg, 'payment');
}

function send_class_reminder($pdo, $entry) {
    $teacher_id = $entry['teacher_id'];
    $stmt = $pdo->prepare("SELECT phone, name FROM teachers WHERE id = ?");
    $stmt->execute([$teacher_id]);
    $teacher = $stmt->fetch();
    if (!$teacher || empty($teacher['phone'])) {
        return false;
    }

    $date = date('D, d M Y', strtotime($entry['date']));
    $time = date('h:i A', strtotime($entry['start_time'])) . ' - ' . date('h:i A', strtotime($entry['end_time']));
    $subject = $entry['subject_name'] ?? $entry['subject_id'];
    $class = $entry['class_name'] ?? $entry['class_id'];
    $room = $entry['room_name'] ?? $entry['room_id'];

    $msg = "🔔 *Upcoming Class Reminder*\n\n" .
           "Hello " . $teacher['name'] . ",\n\n" .
           "You have a class tomorrow:\n\n" .
           "📅 Date: $date\n" .
           "⏰ Time: $time\n" .
           "📖 Subject: $subject\n" .
           "👥 Class: $class\n" .
           "🏫 Room: $room\n\n" .
           "Please be prepared.";

    return send_whatsapp($pdo, $teacher['phone'], $msg, 'reminder');
}