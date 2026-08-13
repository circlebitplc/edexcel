<?php
// admin/settings.php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_admin();

$error = '';
$success = '';

// Fetch current settings
$settings = [];
$stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
while ($row = $stmt->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf_token)) {
        $error = 'Invalid security token.';
    } else {
        $institute_name = trim($_POST['institute_name'] ?? '');
        $fee_per_student = (int)($_POST['fee_per_student'] ?? 0);
        $timezone = $_POST['timezone'] ?? '';
        $week_start = $_POST['week_start'] ?? '';
        $class_duration = (int)($_POST['class_duration'] ?? 0);
        $currency_symbol = trim($_POST['currency_symbol'] ?? 'Rs');

        // WhatsApp (Evolution API) settings
        $whatsapp_enabled = isset($_POST['whatsapp_enabled']) ? '1' : '0';
        $evolution_api_url = trim($_POST['evolution_api_url'] ?? 'http://localhost:8080');
        $evolution_api_key = trim($_POST['evolution_api_key'] ?? '');
        $evolution_instance = trim($_POST['evolution_instance'] ?? 'edexcel');
        $evolution_admin_number = trim($_POST['evolution_admin_number'] ?? '');

        // Validate
        if ($fee_per_student <= 0) {
            $error = 'Fee per student must be greater than 0.';
        } elseif ($class_duration <= 0) {
            $error = 'Class duration must be greater than 0.';
        } elseif ($whatsapp_enabled && empty($evolution_api_key)) {
            $error = 'API Key is required when WhatsApp is enabled.';
        } else {
            try {
                $pdo->beginTransaction();
                $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
                $pairs = [
                    ['institute_name', $institute_name],
                    ['fee_per_student', $fee_per_student],
                    ['timezone', $timezone],
                    ['week_start', $week_start],
                    ['class_duration', $class_duration],
                    ['currency_symbol', $currency_symbol],
                    ['whatsapp_enabled', $whatsapp_enabled],
                    ['evolution_api_url', $evolution_api_url],
                    ['evolution_api_key', $evolution_api_key],
                    ['evolution_instance', $evolution_instance],
                    ['evolution_admin_number', $evolution_admin_number]
                ];
                // Also update old keys for backward compatibility if needed
                // We'll keep twilio_whatsapp_to as alias for admin number
                $pairs[] = ['twilio_whatsapp_to', $evolution_admin_number];
                // Optionally clear old Meta keys to avoid confusion (or just update)
                $pairs[] = ['twilio_account_sid', ''];
                $pairs[] = ['twilio_auth_token', ''];
                $pairs[] = ['twilio_whatsapp_from', ''];

                foreach ($pairs as $pair) {
                    $stmt->execute([$pair[0], $pair[1], $pair[1]]);
                }
                $pdo->commit();
                log_audit($pdo, 'update_settings', 'settings', null, null, $_POST);
                $success = 'Settings updated successfully.';
                // Refresh settings
                $settings = [];
                $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
                while ($row = $stmt->fetch()) {
                    $settings[$row['setting_key']] = $row['setting_value'];
                }
            } catch (PDOException $e) {
                $pdo->rollBack();
                $error = 'Error: ' . $e->getMessage();
            }
        }
    }
}

include __DIR__ . '/../includes/header.php';
?>
<h1><i class="bi bi-gear"></i> System Settings</h1>

<?php if ($success): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<form method="POST" class="col-md-8">
    <?= csrf_field() ?>
    <h4>General Settings</h4>
    <div class="mb-3">
        <label for="institute_name" class="form-label">Institute Name</label>
        <input type="text" class="form-control" id="institute_name" name="institute_name" value="<?= htmlspecialchars($settings['institute_name'] ?? 'Edexcel College') ?>">
    </div>
    <div class="mb-3">
        <label for="fee_per_student" class="form-label">Fee per Student (Rs)</label>
        <input type="number" class="form-control" id="fee_per_student" name="fee_per_student" value="<?= htmlspecialchars($settings['fee_per_student'] ?? 500) ?>" min="1">
    </div>
    <div class="mb-3">
        <label for="currency_symbol" class="form-label">Currency Symbol</label>
        <input type="text" class="form-control" id="currency_symbol" name="currency_symbol" value="<?= htmlspecialchars($settings['currency_symbol'] ?? 'Rs') ?>" maxlength="5">
    </div>
    <div class="mb-3">
        <label for="timezone" class="form-label">Timezone</label>
        <select class="form-select" id="timezone" name="timezone">
            <option value="Asia/Colombo" <?= ($settings['timezone'] ?? '') == 'Asia/Colombo' ? 'selected' : '' ?>>Asia/Colombo</option>
            <option value="Asia/Kolkata" <?= ($settings['timezone'] ?? '') == 'Asia/Kolkata' ? 'selected' : '' ?>>Asia/Kolkata</option>
            <option value="UTC" <?= ($settings['timezone'] ?? '') == 'UTC' ? 'selected' : '' ?>>UTC</option>
            <option value="America/New_York" <?= ($settings['timezone'] ?? '') == 'America/New_York' ? 'selected' : '' ?>>America/New_York</option>
            <option value="Europe/London" <?= ($settings['timezone'] ?? '') == 'Europe/London' ? 'selected' : '' ?>>Europe/London</option>
        </select>
    </div>
    <div class="mb-3">
        <label for="week_start" class="form-label">Week Start Day</label>
        <select class="form-select" id="week_start" name="week_start">
            <option value="Monday" <?= ($settings['week_start'] ?? '') == 'Monday' ? 'selected' : '' ?>>Monday</option>
            <option value="Sunday" <?= ($settings['week_start'] ?? '') == 'Sunday' ? 'selected' : '' ?>>Sunday</option>
        </select>
    </div>
    <div class="mb-3">
        <label for="class_duration" class="form-label">Default Class Duration (minutes)</label>
        <input type="number" class="form-control" id="class_duration" name="class_duration" value="<?= htmlspecialchars($settings['class_duration'] ?? 60) ?>" min="30" step="15">
    </div>

    <hr>
    <h4>WhatsApp Notifications (Evolution API)</h4>
    <p class="text-muted">
        Configure <strong>Evolution API</strong> for WhatsApp notifications. 
        Make sure the API is running and accessible.
        <a href="https://doc.evolution-api.com" target="_blank">Documentation</a>
    </p>
    <div class="mb-3">
        <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" id="whatsapp_enabled" name="whatsapp_enabled" <?= ($settings['whatsapp_enabled'] ?? '0') == '1' ? 'checked' : '' ?>>
            <label class="form-check-label" for="whatsapp_enabled">Enable WhatsApp Notifications</label>
        </div>
    </div>
    <div class="mb-3">
        <label for="evolution_api_url" class="form-label">API URL</label>
        <input type="text" class="form-control" id="evolution_api_url" name="evolution_api_url" value="<?= htmlspecialchars($settings['evolution_api_url'] ?? 'http://localhost:8080') ?>" placeholder="http://localhost:8080">
        <small class="text-muted">The URL where Evolution API is running (including port).</small>
    </div>
    <div class="mb-3">
        <label for="evolution_api_key" class="form-label">API Key</label>
        <input type="password" class="form-control" id="evolution_api_key" name="evolution_api_key" value="<?= htmlspecialchars($settings['evolution_api_key'] ?? '') ?>" placeholder="your-api-key">
        <small class="text-muted">The API key set in your Evolution API configuration.</small>
    </div>
    <div class="mb-3">
        <label for="evolution_instance" class="form-label">Instance Name</label>
        <input type="text" class="form-control" id="evolution_instance" name="evolution_instance" value="<?= htmlspecialchars($settings['evolution_instance'] ?? 'edexcel') ?>" placeholder="edexcel">
        <small class="text-muted">The instance name you created in Evolution API.</small>
    </div>
    <div class="mb-3">
        <label for="evolution_admin_number" class="form-label">Admin WhatsApp Number</label>
        <input type="text" class="form-control" id="evolution_admin_number" name="evolution_admin_number" value="<?= htmlspecialchars($settings['evolution_admin_number'] ?? '') ?>" placeholder="94771234567">
        <small class="text-muted">For admin notifications (e.g., payments). Include country code, no '+' or spaces.</small>
    </div>

    <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Save Settings</button>
</form>
<?php include __DIR__ . '/../includes/footer.php'; ?>