<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/abuse.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/load_env.php';
require_once __DIR__ . '/../../config/evolution.php';
require_once __DIR__ . '/../../config/whatsapp_gateway.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use Edexcel\Services\WhatsAppBotService;

$isGet = ($_SERVER['REQUEST_METHOD'] ?? '') === 'GET';
$hubMode = (string)($_GET['hub_mode'] ?? $_GET['hub.mode'] ?? '');
$hubToken = (string)($_GET['hub_verify_token'] ?? $_GET['hub.verify_token'] ?? '');
$hubChallenge = (string)($_GET['hub_challenge'] ?? $_GET['hub.challenge'] ?? '');

if ($isGet && $hubMode === 'subscribe') {
    $expected = meta_cloud_credentials($pdo)['verify_token'];
    if ($expected !== '' && hash_equals($expected, $hubToken)) {
        header('Content-Type: text/plain; charset=utf-8');
        echo $hubChallenge;
        exit;
    }
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

header('Content-Type: application/json; charset=utf-8');

$raw = file_get_contents('php://input') ?: '';
$data = json_decode($raw, true);
$isMeta = is_array($data) && (($data['object'] ?? '') === 'whatsapp_business_account');

if ($isMeta) {
    $appSecret = trim((string)(getenv('META_APP_SECRET') ?: evolution_setting($pdo, 'meta_app_secret')));
    $sig = (string)($_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '');
    if ($appSecret === '') {
        http_response_code(401);
        echo json_encode(['ok' => false, 'error' => 'Webhook is not configured']);
        exit;
    }
    $expected = 'sha256=' . hash_hmac('sha256', $raw, $appSecret);
    if ($sig === '' || !hash_equals($expected, $sig)) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'error' => 'Invalid signature']);
        exit;
    }
} elseif (!$isMeta) {
    $expectedSecret = evolution_webhook_secret($pdo);
    $providedSecret = trim((string)(
        $_SERVER['HTTP_X_EVOLUTION_WEBHOOK_SECRET']
        ?? $_SERVER['HTTP_X_WEBHOOK_SECRET']
        ?? ($_GET['secret'] ?? '')
    ));

    if ($expectedSecret === '' || !hash_equals($expectedSecret, $providedSecret)) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => true, 'service' => 'edexcel-whatsapp-bot']);
    exit;
}

if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid JSON']);
    exit;
}

// Delivery receipts must be recorded even when the conversational bot is disabled.
if ($isMeta) {
    try {
        wa_meta_process_statuses($pdo, $data);
    } catch (Throwable $e) {
        error_log('WhatsApp status webhook: ' . $e->getMessage());
    }
}

if (!evolution_bot_enabled($pdo)) {
    echo json_encode(['ok' => true, 'ignored' => 'disabled', 'statuses' => $isMeta ? 'processed' : null]);
    exit;
}

$jobs = [];

if ($isMeta) {
    $jobs = wa_meta_inbound_jobs($data);
    try {
        wa_meta_ingest_coexistence($pdo, $data);
    } catch (Throwable $e) {
    }
} else {
    $event = strtoupper((string)($data['event'] ?? ''));
    $event = str_replace('.', '_', $event);
    // Evolution delivery/update events — map when message ids are present.
    if (in_array($event, ['MESSAGES_UPDATE', 'SEND_MESSAGE_UPDATE', 'MESSAGES_SET', 'CHATS_UPDATE'], true)) {
        try {
            wa_evolution_process_statuses($pdo, $data, $event);
        } catch (Throwable $e) {
            error_log('Evolution status webhook: ' . $e->getMessage());
        }
        echo json_encode(['ok' => true, 'processed' => 'status']);
        exit;
    }
    if (!in_array($event, ['MESSAGES_UPSERT', 'MESSAGES_UPSERTED'], true)) {
        echo json_encode(['ok' => true, 'ignored' => $event]);
        exit;
    }

    $messages = [];
    if (isset($data['data']['messages']) && is_array($data['data']['messages'])) {
        $messages = $data['data']['messages'];
    } elseif (isset($data['messages']) && is_array($data['messages'])) {
        $messages = $data['messages'];
    } elseif (isset($data['data']) && is_array($data['data'])) {
        $messages = [$data['data']];
    } else {
        $messages = [$data];
    }

    foreach ($messages as $message) {
        if (!is_array($message) || wa_message_from_me($message)) {
            continue;
        }

        $remoteJid = (string)($message['key']['remoteJid'] ?? '');
        if (
            $remoteJid === ''
            || str_ends_with($remoteJid, '@g.us')
            || str_contains($remoteJid, 'status@')
            || str_contains($remoteJid, '@broadcast')
        ) {
            continue;
        }

        $text = (string)(
            $message['message']['conversation']
            ?? $message['message']['extendedTextMessage']['text']
            ?? $message['message']['imageMessage']['caption']
            ?? $message['message']['videoMessage']['caption']
            ?? $message['message']['buttonsResponseMessage']['selectedDisplayText']
            ?? ''
        );
        $phone = wa_message_phone($message);
        if (trim($text) === '' || $phone === '') {
            continue;
        }

        $jobs[] = [
            'phone' => $phone,
            'text' => $text,
            'event' => $event,
            'message_id' => (string)($message['key']['id'] ?? ''),
            'payload' => $message,
        ];
    }
}

http_response_code(200);
echo json_encode(['ok' => true]);
if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
} else {
    ignore_user_abort(true);
    if (ob_get_level() > 0) {
        ob_end_flush();
    }
    flush();
}

try {
    try {
        $pdo->exec("ALTER TABLE whatsapp_bot_contacts ADD COLUMN opted_out TINYINT(1) NOT NULL DEFAULT 0");
    } catch (Throwable $e) {
    }
    $filtered = [];
    foreach ($jobs as $job) {
        $phone = preg_replace('/\D+/', '', (string)($job['phone'] ?? '')) ?? '';
        $norm = strtolower(trim((string)($job['text'] ?? '')));
        if (in_array($norm, ['stop', 'unsubscribe', 'opt out', 'opt-out', 'stop promotions'], true)) {
            $pdo->prepare("
                INSERT INTO whatsapp_bot_contacts (phone, opted_out, active)
                VALUES (?, 1, 0)
                ON DUPLICATE KEY UPDATE opted_out = 1, active = 0
            ")->execute([$phone]);
            try {
                whatsapp_sender($pdo)->sendText($phone, 'You are unsubscribed from college WhatsApp notices. Send START to receive them again.');
            } catch (Throwable $e) {
            }
            continue;
        }
        if (in_array($norm, ['start', 'subscribe'], true)) {
            $pdo->prepare("UPDATE whatsapp_bot_contacts SET opted_out = 0, active = 1 WHERE phone = ?")->execute([$phone]);
        }
        $opt = $pdo->prepare("SELECT opted_out FROM whatsapp_bot_contacts WHERE phone = ? LIMIT 1");
        $opt->execute([$phone]);
        if ((int)$opt->fetchColumn() === 1 && !in_array($norm, ['start', 'subscribe'], true)) {
            continue;
        }
        $filtered[] = $job;
    }
    $bot = new WhatsAppBotService($pdo, whatsapp_sender($pdo));
    foreach ($filtered as $job) {
        $bot->handle($job['phone'], $job['text'], [
            'event' => $job['event'],
            'message_id' => $job['message_id'],
            'payload' => $job['payload'],
        ]);
    }
} catch (Throwable $e) {
    error_log('WhatsApp webhook error: ' . $e->getMessage());
}

function wa_meta_inbound_jobs(array $data): array
{
    $jobs = [];
    foreach ($data['entry'] ?? [] as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        foreach ($entry['changes'] ?? [] as $change) {
            if (!is_array($change)) {
                continue;
            }
            $field = (string)($change['field'] ?? '');
            if ($field !== '' && $field !== 'messages') {
                continue;
            }
            $value = $change['value'] ?? [];
            if (!is_array($value) || !isset($value['messages']) || !is_array($value['messages'])) {
                continue;
            }
            foreach ($value['messages'] as $message) {
                if (!is_array($message)) {
                    continue;
                }
                $phone = preg_replace('/\D+/', '', (string)($message['from'] ?? '')) ?? '';
                if (str_starts_with($phone, '0') && strlen($phone) === 10) {
                    $phone = '94' . substr($phone, 1);
                }
                $text = wa_meta_message_text($message);
                if ($phone === '' || strlen($phone) < 10 || trim($text) === '') {
                    continue;
                }
                $jobs[] = [
                    'phone' => $phone,
                    'text' => $text,
                    'event' => 'META_MESSAGES',
                    'message_id' => (string)($message['id'] ?? ''),
                    'payload' => $message,
                ];
            }
        }
    }

    return $jobs;
}

function wa_meta_ingest_coexistence(PDO $pdo, array $data): void
{
    try {
        $stmt = $pdo->prepare("
            INSERT INTO whatsapp_bot_contacts (phone, active)
            VALUES (?, 1)
            ON DUPLICATE KEY UPDATE active = 1
        ");
    } catch (Throwable $e) {
        return;
    }

    foreach ($data['entry'] ?? [] as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        foreach ($entry['changes'] ?? [] as $change) {
            if (!is_array($change) || (string)($change['field'] ?? '') !== 'smb_app_state_sync') {
                continue;
            }
            $value = $change['value'] ?? [];
            if (!is_array($value)) {
                continue;
            }
            $items = $value['state_sync'] ?? $value['contacts'] ?? [];
            if (!is_array($items)) {
                continue;
            }
            foreach ($items as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $contact = is_array($item['contact'] ?? null) ? $item['contact'] : $item;
                $phone = preg_replace('/\D+/', '', (string)($contact['phone_number'] ?? $contact['phone'] ?? '')) ?? '';
                if (str_starts_with($phone, '0') && strlen($phone) === 10) {
                    $phone = '94' . substr($phone, 1);
                }
                if ($phone === '' || strlen($phone) < 10) {
                    continue;
                }
                try {
                    $stmt->execute([$phone]);
                } catch (Throwable $e) {
                }
            }
        }
    }
}

function wa_meta_message_text(array $message): string
{
    $type = (string)($message['type'] ?? '');
    if ($type === 'text') {
        return (string)($message['text']['body'] ?? '');
    }
    if ($type === 'button') {
        return (string)($message['button']['text'] ?? $message['button']['payload'] ?? '');
    }
    if ($type === 'interactive') {
        $interactive = $message['interactive'] ?? [];
        return (string)(
            $interactive['button_reply']['title']
            ?? $interactive['list_reply']['title']
            ?? $interactive['button_reply']['id']
            ?? $interactive['list_reply']['id']
            ?? ''
        );
    }

    return (string)(
        $message['image']['caption']
        ?? $message['video']['caption']
        ?? $message['document']['caption']
        ?? ''
    );
}

function wa_message_from_me(array $message): bool
{
    $value = $message['key']['fromMe'] ?? false;
    if ($value === true || $value === 1) {
        return true;
    }
    if (is_string($value)) {
        return in_array(strtolower($value), ['1', 'true', 'yes'], true);
    }

    return false;
}

function wa_message_phone(array $message): string
{
    $candidates = [
        $message['key']['senderPn'] ?? '',
        $message['senderPn'] ?? '',
        $message['key']['remoteJidAlt'] ?? '',
        $message['key']['participantAlt'] ?? '',
        $message['key']['participant'] ?? '',
        $message['key']['remoteJid'] ?? '',
    ];

    foreach ($candidates as $jid) {
        $jid = trim((string)$jid);
        if ($jid === '' || str_contains($jid, '@g.us') || str_contains($jid, '@lid')) {
            continue;
        }
        $phone = preg_replace('/@.*$/', '', $jid) ?? $jid;
        $phone = preg_replace('/:\d+$/', '', $phone) ?? $phone;
        $phone = preg_replace('/\D+/', '', $phone) ?? '';
        if (strlen($phone) >= 10) {
            if (str_starts_with($phone, '0') && strlen($phone) === 10) {
                $phone = '94' . substr($phone, 1);
            }
            return $phone;
        }
    }

    return '';
}

function wa_meta_process_statuses(PDO $pdo, array $data): int
{
    $hub = new \Edexcel\Services\CommunicationHubService($pdo);
    $n = 0;
    foreach ($data['entry'] ?? [] as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        foreach ($entry['changes'] ?? [] as $change) {
            if (!is_array($change)) {
                continue;
            }
            $value = $change['value'] ?? [];
            if (!is_array($value) || empty($value['statuses']) || !is_array($value['statuses'])) {
                continue;
            }
            foreach ($value['statuses'] as $status) {
                if (!is_array($status)) {
                    continue;
                }
                $id = (string)($status['id'] ?? '');
                $st = (string)($status['status'] ?? '');
                if ($id === '' || $st === '') {
                    continue;
                }
                $err = '';
                if (!empty($status['errors'][0]['message'])) {
                    $err = (string)$status['errors'][0]['message'];
                }
                $hub->recordProviderStatus($id, $st, 'whatsapp', $err !== '' ? $err : ('Meta status '.$st));
                $n++;
            }
        }
    }
    return $n;
}

function wa_evolution_process_statuses(PDO $pdo, array $data, string $event): int
{
    $hub = new \Edexcel\Services\CommunicationHubService($pdo);
    $n = 0;
    $candidates = [];
    if (isset($data['data']) && is_array($data['data'])) {
        $candidates[] = $data['data'];
        if (isset($data['data']['key']) || isset($data['data']['status'])) {
            $candidates[] = $data['data'];
        }
        if (isset($data['data']['messages']) && is_array($data['data']['messages'])) {
            foreach ($data['data']['messages'] as $m) {
                if (is_array($m)) {
                    $candidates[] = $m;
                }
            }
        }
    }
    foreach ($candidates as $message) {
        $id = (string)($message['key']['id'] ?? $message['id'] ?? $message['messageId'] ?? '');
        $st = (string)($message['status'] ?? $message['update']['status'] ?? $event);
        if ($id === '') {
            continue;
        }
        $hub->recordProviderStatus($id, $st, 'whatsapp', 'Evolution '.$event);
        $n++;
    }
    return $n;
}
