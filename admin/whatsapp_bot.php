<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/load_env.php';
require_once __DIR__ . '/../config/evolution.php';
require_once __DIR__ . '/../config/whatsapp_gateway.php';
require_once __DIR__ . '/../config/sms_gateway.php';
require_once __DIR__ . '/../config/campus.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Edexcel\Services\EvolutionApiService;
use Edexcel\Services\WhatsAppBotService;
use Edexcel\Services\WhatsAppAiService;

require_admin();
if ($pdo instanceof PDO) {
    ensure_campus_schema($pdo);
}

$error = '';
$success = '';

function bot_normalize_phone(string $phone): string
{
    $phone = preg_replace('/\D+/', '', trim($phone)) ?? '';
    if (str_starts_with($phone, '0') && strlen($phone) === 10) {
        $phone = '94' . substr($phone, 1);
    }
    return $phone;
}

function bot_log_staff_message(PDO $pdo, string $channel, string $phone, string $message, bool $ok, string $errorText = ''): void
{
    try {
        $stmt = $pdo->prepare("
            INSERT INTO staff_message_log (channel, phone, message, status, error_text, sent_by)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $channel,
            $phone,
            $message,
            $ok ? 'sent' : 'failed',
            $ok ? null : substr($errorText, 0, 500),
            (int)($_SESSION['user_id'] ?? 0) ?: null,
        ]);
    } catch (Throwable $e) {
        error_log('staff_message_log: ' . $e->getMessage());
    }
}

function bot_log_whatsapp_message(PDO $pdo, string $phone, string $message, string $messageId = ''): void
{
    try {
        $stmt = $pdo->prepare("
            INSERT INTO whatsapp_bot_messages (phone, direction, message, event_name, message_id)
            VALUES (?, 'outbound', ?, 'ADMIN_REPLY', ?)
        ");
        $stmt->execute([$phone, $message, $messageId]);
    } catch (Throwable $e) {
        error_log('whatsapp_bot_messages admin send: ' . $e->getMessage());
    }
}

function setting(PDO $pdo, string $key, string $default = ''): string {
    $stmt = $pdo->prepare('SELECT setting_value FROM settings WHERE setting_key = ? LIMIT 1');
    $stmt->execute([$key]);
    $value = $stmt->fetchColumn();
    return $value === false ? $default : (string)$value;
}
function save_setting(PDO $pdo, string $key, string $value): void {
    $stmt = $pdo->prepare('INSERT INTO settings (setting_key,setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)');
    $stmt->execute([$key,$value]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token.';
    } else {
        $action = $_POST['action'] ?? '';
        try {
            if ($action === 'save') {
                save_setting($pdo, 'whatsapp_bot_enabled', isset($_POST['enabled']) ? '1' : '0');
                $secret = trim((string)($_POST['webhook_secret'] ?? ''));
                if ($secret === '') {
                    $secret = evolution_ensure_webhook_secret($pdo);
                } else {
                    save_setting($pdo, 'evolution_webhook_secret', $secret);
                }
                $success = 'Bot settings saved.';
            } elseif ($action === 'save_ai') {
                save_setting($pdo, 'whatsapp_ai_enabled', isset($_POST['ai_enabled']) ? '1' : '0');
                $groqKey = trim((string)($_POST['groq_api_key'] ?? ''), " \t\n\r\0\x0B\"'");
                $geminiKey = trim((string)($_POST['gemini_api_key'] ?? ''), " \t\n\r\0\x0B\"'");
                if ($groqKey !== '') {
                    save_setting($pdo, 'groq_api_key', $groqKey);
                }
                if ($geminiKey !== '') {
                    save_setting($pdo, 'gemini_api_key', $geminiKey);
                }
                $success = 'AI chat settings saved.';
            } elseif ($action === 'test_ai') {
                $ai = new WhatsAppAiService($pdo);
                $success = $ai->testConnection();
            } elseif ($action === 'link') {
                $phone = preg_replace('/\D+/', '', trim((string)($_POST['phone'] ?? ''))) ?? '';
                if (str_starts_with($phone, '0') && strlen($phone) === 10) $phone = '94' . substr($phone, 1);
                $studentId = (int)($_POST['student_id'] ?? 0);
                if ($phone === '' || $studentId < 1) throw new RuntimeException('Phone number and student are required.');
                $stmt = $pdo->prepare('INSERT INTO whatsapp_bot_contacts (phone,student_id,active,last_seen_at) VALUES (?,?,1,NULL) ON DUPLICATE KEY UPDATE student_id=VALUES(student_id),active=1');
                $stmt->execute([$phone,$studentId]);
                $success = 'WhatsApp number linked to the student.';
            } elseif ($action === 'unlink') {
                $id = (int)($_POST['id'] ?? 0);
                $stmt = $pdo->prepare('UPDATE whatsapp_bot_contacts SET active=0 WHERE id=?');
                $stmt->execute([$id]);
                $success = 'WhatsApp contact disabled.';
            } elseif ($action === 'webhook') {
                if (whatsapp_provider($pdo) === 'meta') {
                    throw new RuntimeException('Meta Cloud API webhooks are configured in Meta Developer, not here. Use the callback URL below and the verify token from System Settings.');
                }
                $url = trim((string)($_POST['webhook_url'] ?? ''));
                $secret = evolution_ensure_webhook_secret($pdo);
                if ($url === '') throw new RuntimeException('Enter the public webhook URL first.');
                if ($secret === '') throw new RuntimeException('Set a webhook secret first.');
                $url = preg_replace('/[?&]secret=[^&]*/', '', $url) ?? $url;
                $url = rtrim($url, '?&');
                $api = new EvolutionApiService($pdo);
                $api->setWebhook($url, ['MESSAGES_UPSERT'], $secret);
                save_setting($pdo, 'evolution_webhook_url', $url);
                $success = 'Evolution API webhook configured. Secret is sent as a header, not in the URL.';
            } elseif ($action === 'send_message') {
                $phone = bot_normalize_phone((string)($_POST['to_phone'] ?? ''));
                $message = trim((string)($_POST['body'] ?? ''));
                $channel = strtolower(trim((string)($_POST['channel'] ?? 'whatsapp')));
                if (!in_array($channel, ['whatsapp', 'sms', 'both'], true)) {
                    $channel = 'whatsapp';
                }
                if ($phone === '' || !preg_match('/^94(?:7\d{8})$/', $phone)) {
                    throw new RuntimeException('Enter a valid Sri Lankan mobile number, e.g. 0771234567.');
                }
                if ($message === '') {
                    throw new RuntimeException('Type a message first.');
                }
                if (mb_strlen($message) > 2000) {
                    throw new RuntimeException('Keep the message under 2000 characters.');
                }

                $parts = [];
                $failures = [];

                if ($channel === 'whatsapp' || $channel === 'both') {
                    try {
                        $result = whatsapp_sender($pdo)->sendText($phone, $message);
                        $messageId = '';
                        if (is_array($result) && isset($result['key']['id'])) {
                            $messageId = (string)$result['key']['id'];
                        }
                        bot_log_whatsapp_message($pdo, $phone, $message, $messageId);
                        bot_log_staff_message($pdo, 'whatsapp', $phone, $message, true);
                        $parts[] = 'WhatsApp';
                    } catch (Throwable $e) {
                        bot_log_staff_message($pdo, 'whatsapp', $phone, $message, false, $e->getMessage());
                        $failures[] = 'WhatsApp: ' . $e->getMessage();
                    }
                }

                if ($channel === 'sms' || $channel === 'both') {
                    if (sms_send($pdo, $phone, $message)) {
                        bot_log_staff_message($pdo, 'sms', $phone, $message, true);
                        $parts[] = 'SMS';
                    } else {
                        $smsErr = sms_send_last_error() ?: 'The Honor SMS gateway did not accept the message.';
                        bot_log_staff_message($pdo, 'sms', $phone, $message, false, $smsErr);
                        $failures[] = 'SMS: ' . $smsErr;
                    }
                }

                if ($parts !== [] && $failures === []) {
                    $success = 'Sent by ' . implode(' and ', $parts) . ' to ' . $phone . '.';
                } elseif ($parts !== []) {
                    $success = 'Sent by ' . implode(' and ', $parts) . '.';
                    $error = implode(' ', $failures);
                } else {
                    throw new RuntimeException(implode(' ', $failures) ?: 'The message could not be sent.');
                }

                if (function_exists('log_audit')) {
                    log_audit($pdo, 'staff_send_message', 'staff_message_log', null, null, [
                        'phone' => $phone,
                        'channel' => $channel,
                        'ok' => $parts,
                    ]);
                }
            }
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}

$students = $pdo->query("SELECT id,username FROM users WHERE role='student' AND deleted_at IS NULL ORDER BY username")->fetchAll(PDO::FETCH_ASSOC);
$contacts = [];
try {
    $contacts = $pdo->query("
        SELECT w.id, w.phone, w.active, w.last_seen_at, u.username, sp.full_name
        FROM whatsapp_bot_contacts w
        LEFT JOIN users u ON u.id = w.student_id
        LEFT JOIN student_profiles sp ON sp.user_id = u.id
        ORDER BY w.created_at DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $contacts = $pdo->query("SELECT w.id,w.phone,w.active,w.last_seen_at,u.username FROM whatsapp_bot_contacts w LEFT JOIN users u ON u.id=w.student_id ORDER BY w.created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
}
$recentMessages = [];
try {
    $recentMessages = $pdo->query("
        SELECT channel, phone, message, status, error_text, created_at
        FROM staff_message_log
        ORDER BY id DESC
        LIMIT 12
    ")->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
    $recentMessages = [];
}
$enabled = setting($pdo,'whatsapp_bot_enabled','1') === '1';
$aiEnabled = setting($pdo,'whatsapp_ai_enabled','1') === '1';
$groqKey = setting($pdo,'groq_api_key', trim((string)(getenv('GROQ_API_KEY') ?: '')));
$geminiKey = setting($pdo,'gemini_api_key', trim((string)(getenv('GEMINI_API_KEY') ?: '')));
$aiStatus = (new WhatsAppAiService($pdo))->enabled()
    ? 'Free AI chat is on (' . (new WhatsAppAiService($pdo))->providerName() . '). Students can type normal questions.'
    : 'Add a free Groq or Gemini API key below to turn menu chat into AI chat.';
$webhookSecret = evolution_webhook_secret($pdo);
$webhookUrl = setting($pdo,'evolution_webhook_url','');
$defaultWebhookUrl = rtrim(edexcel_public_app_url(), '/') . '/api/whatsapp/webhook.php';
$provider = whatsapp_provider($pdo);
try {
    $bot = new WhatsAppBotService($pdo, whatsapp_sender($pdo));
    $bot->linkRegisteredStudents();
    $contacts = $pdo->query("
        SELECT w.id, w.phone, w.active, w.last_seen_at, u.username, sp.full_name
        FROM whatsapp_bot_contacts w
        LEFT JOIN users u ON u.id = w.student_id
        LEFT JOIN student_profiles sp ON sp.user_id = u.id
        ORDER BY w.created_at DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log('WhatsApp bot page sync: ' . $e->getMessage());
}
$whatsappHealth = ['warning' => false, 'message' => '', 'expires_at' => '', 'days_left' => null];
try {
    require_once __DIR__ . '/../config/ops.php';
    $expires = ops_setting($pdo, 'meta_token_expires_at');
    if ($expires !== '' && strtotime($expires)) {
        $days = (int)floor((strtotime($expires) - time()) / 86400);
        $whatsappHealth = [
            'warning' => $days <= 14,
            'message' => 'Cloud API token expiry is saved from System health.',
            'expires_at' => $expires,
            'days_left' => $days,
        ];
    }
} catch (Throwable $e) {
}

include __DIR__ . '/../includes/header.php';
?>
<div class="container-fluid py-4">
    <div class="mb-4">
        <h1><i class="bi bi-whatsapp text-success"></i> Messages</h1>
        <p class="text-muted mb-0">Send WhatsApp or GSM SMS, and manage the student bot. WhatsApp provider: <strong><?= $provider === 'meta' ? 'Meta Cloud API' : 'Evolution API' ?></strong>. SMS goes out from the Honor/Hutch gateway in System Settings.</p>
    </div>
    <?php if($success): ?><div class="alert alert-success"><?=htmlspecialchars($success)?></div><?php endif; ?>
    <?php if($error): ?><div class="alert alert-danger"><?=htmlspecialchars($error)?></div><?php endif; ?>
    <?php if (!empty($whatsappHealth['warning'])): ?>
        <div class="alert alert-warning">
            WhatsApp Cloud API token expires <?= htmlspecialchars((string)$whatsappHealth['expires_at']) ?>
            (<?= (int)$whatsappHealth['days_left'] ?> days).
            Paste a new API Setup token on <a href="<?= htmlspecialchars(BASE_URL) ?>admin/whatsapp_connect.php">Connect WhatsApp</a>.
        </div>
    <?php endif; ?>

    <?php
        $metaStatus = \Edexcel\Services\MetaEmbeddedSignupService::publicStatus($pdo);
        $metaConnected = !empty($metaStatus['connected']);
    ?>
    <div class="card shadow-sm border-0 rounded-4 mb-4">
        <div class="card-body p-4 d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div>
                <h4 class="mb-1">Connect WhatsApp</h4>
                <p class="text-muted small mb-0">
                    <?php if ($metaConnected && !empty($metaStatus['needs_register'])): ?>
                        Tokens are saved, but Cloud API cannot send yet (account not registered). Open Connect WhatsApp, finish QR pairing, or activate with the two-step PIN.
                    <?php elseif ($metaConnected): ?>
                        Cloud API is linked<?= $metaStatus['display_phone'] !== '' ? ' as ' . htmlspecialchars($metaStatus['display_phone']) : '' ?><?= $metaStatus['onboarding_mode'] === 'coexistence' ? ' (Business app coexistence)' : '' ?>.
                    <?php else: ?>
                        Pair the existing WhatsApp Business app with Cloud API. Staff keep the phone app; the chatbot uses this website’s webhook.
                    <?php endif; ?>
                </p>
            </div>
            <a class="btn <?= ($metaConnected && empty($metaStatus['needs_register'])) ? 'btn-outline-success' : 'btn-warning' ?> rounded-pill" href="<?= htmlspecialchars(BASE_URL . 'admin/whatsapp_connect.php') ?>">
                <i class="bi bi-whatsapp"></i> <?= $metaConnected ? 'Activate Cloud API' : 'Connect WhatsApp' ?>
            </a>
        </div>
    </div>

    <div class="card shadow-sm border-0 rounded-4 mb-4">
        <div class="card-body p-4">
            <h4 class="mb-1">Send a message</h4>
            <p class="text-muted small">Use WhatsApp for chat, or SMS if the student may not be on WhatsApp. You can also send both.</p>
            <form method="post" class="row g-3">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="send_message">
                <div class="col-md-4">
                    <label class="form-label" for="to_phone">Mobile number</label>
                    <input class="form-control" id="to_phone" name="to_phone" value="<?= htmlspecialchars((string)($_POST['to_phone'] ?? '')) ?>" placeholder="0771234567" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="pick_contact">Or pick a linked number</label>
                    <select class="form-select" id="pick_contact">
                        <option value="">Select a contact…</option>
                        <?php foreach ($contacts as $c): ?>
                            <option value="<?= htmlspecialchars((string)$c['phone']) ?>">
                                <?= htmlspecialchars(trim((string)($c['full_name'] ?? '')) !== '' ? (string)$c['full_name'] : (string)($c['username'] ?: $c['phone'])) ?>
                                — <?= htmlspecialchars((string)$c['phone']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Send by</label>
                    <div class="d-flex flex-wrap gap-3 pt-1">
                        <?php $ch = (string)($_POST['channel'] ?? 'whatsapp'); ?>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="channel" id="ch_wa" value="whatsapp" <?= $ch !== 'sms' && $ch !== 'both' ? 'checked' : '' ?>>
                            <label class="form-check-label" for="ch_wa">WhatsApp</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="channel" id="ch_sms" value="sms" <?= $ch === 'sms' ? 'checked' : '' ?>>
                            <label class="form-check-label" for="ch_sms">SMS</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="channel" id="ch_both" value="both" <?= $ch === 'both' ? 'checked' : '' ?>>
                            <label class="form-check-label" for="ch_both">Both</label>
                        </div>
                    </div>
                </div>
                <div class="col-12">
                    <label class="form-label" for="body">Message</label>
                    <textarea class="form-control" id="body" name="body" rows="4" maxlength="2000" required placeholder="Type the message…"><?= htmlspecialchars((string)($_POST['body'] ?? '')) ?></textarea>
                    <div class="form-text">SMS is best kept short. Long texts may split on the phone.</div>
                </div>
                <div class="col-12">
                    <button class="btn btn-success rounded-pill px-4" type="submit"><i class="bi bi-send me-1"></i> Send</button>
                    <a class="btn btn-outline-secondary rounded-pill" href="<?= htmlspecialchars(rtrim((string)BASE_URL, '/') . '/admin/settings.php') ?>">SMS gateway settings</a>
                </div>
            </form>
            <?php if ($recentMessages): ?>
                <hr>
                <h5 class="mb-3">Recent sends</h5>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead><tr><th>When</th><th>Channel</th><th>To</th><th>Message</th><th>Status</th></tr></thead>
                        <tbody>
                        <?php foreach ($recentMessages as $row): ?>
                            <tr>
                                <td class="text-nowrap"><?= htmlspecialchars((string)$row['created_at']) ?></td>
                                <td><?= htmlspecialchars(strtoupper((string)$row['channel'])) ?></td>
                                <td><?= htmlspecialchars((string)$row['phone']) ?></td>
                                <td><?= htmlspecialchars(mb_strlen((string)$row['message']) > 80 ? mb_substr((string)$row['message'], 0, 77) . '…' : (string)$row['message']) ?></td>
                                <td>
                                    <?php if (($row['status'] ?? '') === 'sent'): ?>
                                        <span class="badge text-bg-success">Sent</span>
                                    <?php else: ?>
                                        <span class="badge text-bg-danger" title="<?= htmlspecialchars((string)($row['error_text'] ?? '')) ?>">Failed</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-5">
            <div class="card shadow-sm border-0 rounded-4"><div class="card-body p-4">
                <h4>Bot settings</h4><form method="post"><?=csrf_field()?>
                    <input type="hidden" name="action" value="save">
                    <div class="form-check form-switch mb-3"><input class="form-check-input" type="checkbox" name="enabled" id="enabled" <?=$enabled?'checked':''?>><label class="form-check-label" for="enabled">Enable WhatsApp bot</label></div>
                    <?php if ($provider !== 'meta'): ?>
                    <label class="form-label">Webhook secret</label><input class="form-control mb-3" name="webhook_secret" value="<?=htmlspecialchars($webhookSecret)?>" placeholder="Long random secret" autocomplete="off">
                    <?php endif; ?>
                    <button class="btn btn-success rounded-pill">Save settings</button></form>
                <hr>
                <h5>Free AI chat</h5>
                <p class="small text-muted mb-2"><?= htmlspecialchars($aiStatus) ?></p>
                <p class="small text-muted">Get a free Groq key at <a href="https://console.groq.com/keys" target="_blank" rel="noopener">console.groq.com/keys</a>. This bot skips models your Groq org has blocked (such as GPT-OSS) and uses Qwen or Compound instead. You can also allow models at <a href="https://console.groq.com/settings/limits" target="_blank" rel="noopener">console.groq.com/settings/limits</a>.</p>
                <form method="post" class="mb-2"><?=csrf_field()?>
                    <input type="hidden" name="action" value="save_ai">
                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" name="ai_enabled" id="ai_enabled" <?=$aiEnabled?'checked':''?>>
                        <label class="form-check-label" for="ai_enabled">Enable AI replies</label>
                    </div>
                    <label class="form-label">Groq API key</label>
                    <input class="form-control mb-2" name="groq_api_key" type="password" autocomplete="off" placeholder="<?= $groqKey !== '' ? 'Saved — paste a new key to replace' : 'gsk_…' ?>">
                    <label class="form-label">Gemini API key (optional fallback)</label>
                    <input class="form-control mb-3" name="gemini_api_key" type="password" autocomplete="off" placeholder="<?= $geminiKey !== '' ? 'Saved — paste a new key to replace' : 'Optional' ?>">
                    <button class="btn btn-primary rounded-pill">Save AI settings</button>
                </form>
                <form method="post"><?=csrf_field()?><input type="hidden" name="action" value="test_ai"><button class="btn btn-outline-primary rounded-pill">Test AI connection</button></form>
                <hr>
                <h5>Webhook URL</h5>
                <p class="small text-muted"><?= $provider === 'meta' ? 'Paste this as the Meta webhook callback URL. Subscribe to messages. Verify token is in System Settings.' : 'Use your public HTTPS URL:' ?></p>
                <code><?=htmlspecialchars($webhookUrl ?: $defaultWebhookUrl)?></code>
                <?php if ($provider !== 'meta'): ?>
                <form method="post" class="mt-3"><?=csrf_field()?><input type="hidden" name="action" value="webhook"><input class="form-control mb-2" name="webhook_url" value="<?=htmlspecialchars($webhookUrl ?: $defaultWebhookUrl)?>"><button class="btn btn-outline-success rounded-pill">Configure Evolution Webhook</button></form>
                <?php endif; ?>
            </div></div>
        </div>
        <div class="col-lg-7">
            <div class="card shadow-sm border-0 rounded-4"><div class="card-body p-4">
                <h4>Link a WhatsApp number to a student</h4><p class="text-muted small">This lets the bot answer personalised timetable questions.</p>
                <form method="post" class="row g-2 align-items-end"><?=csrf_field()?><input type="hidden" name="action" value="link"><div class="col-md-4"><label class="form-label">WhatsApp</label><input class="form-control" name="phone" placeholder="0771234567" required></div><div class="col-md-5"><label class="form-label">Student</label><select class="form-select" name="student_id" required><option value="">Select student</option><?php foreach($students as $s): ?><option value="<?=$s['id']?>"><?=htmlspecialchars($s['username'])?></option><?php endforeach; ?></select></div><div class="col-md-3"><button class="btn btn-primary w-100">Link number</button></div></form>
                <hr><h5>Linked numbers</h5><div class="table-responsive"><table class="table align-middle"><thead><tr><th>WhatsApp</th><th>Student</th><th>Last seen</th><th></th></tr></thead><tbody><?php foreach($contacts as $c): ?><tr><td><?=htmlspecialchars($c['phone'])?></td><td><?=htmlspecialchars(trim((string)($c['full_name'] ?? '')) !== '' ? (string)$c['full_name'] : (string)($c['username'] ?: 'Unlinked'))?></td><td><?=htmlspecialchars($c['last_seen_at'] ?: 'Never')?></td><td><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="unlink"><input type="hidden" name="id" value="<?=$c['id']?>"><button class="btn btn-sm btn-outline-danger">Disable</button></form></td></tr><?php endforeach; ?></tbody></table></div>
            </div></div>
        </div>
    </div>
</div>
<script>
(function () {
    var pick = document.getElementById('pick_contact');
    var phone = document.getElementById('to_phone');
    if (!pick || !phone) return;
    pick.addEventListener('change', function () {
        if (pick.value) phone.value = pick.value;
    });
})();
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
