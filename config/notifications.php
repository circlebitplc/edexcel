<?php
// config/notifications.php
require_once __DIR__ . '/database.php';

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

function send_whatsapp($pdo, $to, $message, $type = 'general') {
    $settings = get_notification_settings($pdo);
    if (empty($settings['whatsapp_enabled']) || $settings['whatsapp_enabled'] != '1') {
        log_notification($pdo, $to, $type, $message, 'failed', 'WhatsApp disabled');
        return false;
    }

    $api_url = $settings['evolution_api_url'] ?? getenv('EVOLUTION_API_URL') ?: 'http://localhost:8080';
    $api_key = $settings['evolution_api_key'] ?? getenv('EVOLUTION_API_KEY') ?: '';
    $instance = $settings['evolution_instance'] ?? getenv('EVOLUTION_INSTANCE') ?: 'edexcel';

    if (empty($api_key)) {
        log_notification($pdo, $to, $type, $message, 'failed', 'API key missing');
        return false;
    }

    $to = preg_replace('/[^0-9]/', '', $to);
    if (empty($to)) {
        log_notification($pdo, $to, $type, $message, 'failed', 'Empty phone');
        return false;
    }

    $to = ltrim($to, '0');
    if (strlen($to) === 9) {
        $to = '94' . $to; // Sri Lanka country code – change if needed
    }
    if (strlen($to) < 10) {
        log_notification($pdo, $to, $type, $message, 'failed', 'Number too short');
        return false;
    }

    $payload = [
        'number' => $to,
        'text' => $message
    ];

    if (!function_exists('curl_init')) {
        log_notification($pdo, $to, $type, $message, 'failed', 'PHP cURL extension is not installed');
        return false;
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api_url . '/message/sendText/' . $instance);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'apikey: ' . $api_key
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($http_code >= 200 && $http_code < 300) {
        log_notification($pdo, $to, $type, $message, 'sent');
        return true;
    } else {
        log_notification($pdo, $to, $type, $message, 'failed', 'HTTP ' . $http_code . ' - ' . substr($response, 0, 200));
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
           "Logged in to view details: " . (defined('APP_URL') ? APP_URL : '') . "timetable/index.php";

    return send_whatsapp($pdo, $teacher_phone, $msg, 'class_' . $action);
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