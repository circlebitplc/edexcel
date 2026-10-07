<?php
// admin/settings.php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/evolution.php';
require_once __DIR__ . '/../config/whatsapp_gateway.php';
require_once __DIR__ . '/../config/sms_gateway.php';
require_once __DIR__ . '/../config/payment.php';
require_once __DIR__ . '/../config/campus.php';
require_once __DIR__ . '/../config/bunny.php';
require_once __DIR__ . '/../config/onepay.php';
require_once __DIR__ . '/../config/livekit.php';
require_once __DIR__ . '/../config/classroom.php';
require_once __DIR__ . '/../includes/college_contact.php';
require_once __DIR__ . '/../src/Services/UserDeletionService.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Edexcel\Services\MetaEmbeddedSignupService;
use Edexcel\Services\UserDeletionService;

require_admin();
evolution_credentials($pdo);

$publicAppUrl = rtrim(edexcel_public_app_url(), '/');
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
    } elseif (($_POST['action'] ?? '') === 'delete_user_confirm') {
        try {
            if (empty($_POST['confirm_checkbox'])) {
                $error = 'Please check the confirmation box in Step 1 to confirm permanent deletion.';
            } else {
                $userDeletion = new UserDeletionService($pdo);
                $targetUserId = (int)($_POST['target_user_id'] ?? 0);
                $confirmId = trim((string)($_POST['confirm_identifier'] ?? ''));
                $reason = trim((string)($_POST['deletion_reason'] ?? ''));
                $accountType = trim((string)($_POST['account_type'] ?? 'user')) === 'parent' ? 'parent' : 'user';
                $currentAdminId = (int)($_SESSION['user_id'] ?? 0);

                $delRes = $userDeletion->deleteUser($targetUserId, $currentAdminId, $confirmId, $reason, $accountType);
                if ($delRes['ok']) {
                    $success = $delRes['message'];
                    $_POST['target_user_id'] = 0;
                    unset($_GET['user_id'], $_GET['account_type'], $_GET['q'], $_POST['user_query']);
                    $userDeletionQuery = '';
                    $selectedUserId = 0;
                } else {
                    $error = $delRes['message'];
                }
            }
        } catch (Throwable $e) {
            $error = 'Deletion failed: ' . $e->getMessage();
        }
        $_POST['settings_panel'] = 'delete_user';
    } elseif (($_POST['action'] ?? '') === 'reset_meta_cloud') {
        try {
            (new MetaEmbeddedSignupService($pdo))->disconnect();
            $wipe = $pdo->prepare(
                "INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?"
            );
            foreach ([
                'meta_access_token',
                'meta_phone_number_id',
                'meta_webhook_verify_token',
                'meta_graph_version',
                'meta_app_id',
                'meta_app_secret',
                'meta_embedded_signup_config_id',
                'meta_waba_id',
                'meta_display_phone_number',
                'meta_onboarding_mode',
                'meta_connected_at',
                'meta_is_on_biz_app',
                'meta_platform_type',
                'meta_phone_status',
                'meta_code_verification_status',
                'meta_register_blocked_until',
                'meta_smb_sync_request_id',
                'meta_history_sync_request_id',
                'whatsapp_provider',
            ] as $key) {
                $value = $key === 'whatsapp_provider' ? 'evolution' : '';
                $wipe->execute([$key, $value, $value]);
            }
            log_audit($pdo, 'reset_meta_cloud', 'settings', null, null, ['cleared' => true]);
            $success = 'Cloud API settings were cleared. Open Connect WhatsApp to link a number from scratch.';
            $_POST['settings_panel'] = 'meta';
            $settings = [];
            $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
            while ($row = $stmt->fetch()) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    } elseif (in_array((string)($_POST['action'] ?? ''), ['test_bunny', 'save_bunny', 'test_onepay', 'save_onepay', 'save_bank', 'save_payment_controls', 'test_livekit', 'save_livekit', 'save_handwriting', 'test_handwriting', 'save_oauth', 'test_oauth'], true)) {
        $action = (string)$_POST['action'];
        $keep = static function (array $settings, string $postKey, string $settingKey): string {
            $posted = trim((string)($_POST[$postKey] ?? ''));
            return $posted !== '' ? $posted : (string)($settings[$settingKey] ?? '');
        };
        try {
            if (in_array($action, ['test_bunny', 'save_bunny'], true)) {
                recordings_save_setting($pdo, 'bunny_enabled', isset($_POST['bunny_enabled']) ? '1' : '0');
                recordings_save_setting($pdo, 'bunny_api_base_url', trim((string)($_POST['bunny_api_base_url'] ?? '')) ?: 'https://video.bunnycdn.com');
                recordings_save_setting($pdo, 'bunny_embed_base_url', trim((string)($_POST['bunny_embed_base_url'] ?? '')) ?: 'https://iframe.mediadelivery.net');
                recordings_save_setting($pdo, 'recording_retention_enabled', isset($_POST['recording_retention_enabled']) ? '1' : '0');
                recordings_save_setting($pdo, 'recording_retention_days', (string)max(1, (int)($_POST['recording_retention_days'] ?? 365)));
                if (!bunny_secret_configured_via_env('BUNNY_ACCOUNT_API_KEY')) {
                    recordings_save_setting($pdo, 'bunny_account_api_key', $keep($settings, 'bunny_account_api_key', 'bunny_account_api_key'));
                }
                $_POST['settings_panel'] = 'bunny';
                log_audit($pdo, 'update_bunny_settings', 'settings', null, null, ['per_teacher_libraries' => true]);
                $success = 'Bunny settings saved.';
                if ($action === 'test_bunny') {
                    $test = (new \Edexcel\Services\TeacherBunnyLibraryService($pdo))->testAccountConnection();
                    if ($test['ok']) {
                        $success = $test['message'];
                    } else {
                        $success = '';
                        $error = $test['message'];
                    }
                }
            } elseif (in_array($action, ['test_onepay', 'save_onepay'], true)) {
                recordings_save_setting($pdo, 'onepay_enabled', isset($_POST['onepay_enabled']) ? '1' : '0');
                $env = strtolower(trim((string)($_POST['onepay_environment'] ?? 'production'))) === 'sandbox' ? 'sandbox' : 'production';
                recordings_save_setting($pdo, 'onepay_environment', $env);
                recordings_save_setting($pdo, 'onepay_app_id', trim((string)($_POST['onepay_app_id'] ?? '')));
                recordings_save_setting($pdo, 'onepay_api_url', trim((string)($_POST['onepay_api_url'] ?? '')) ?: 'https://api.onepay.lk');
                recordings_save_setting($pdo, 'onepay_currency', strtoupper(trim((string)($_POST['onepay_currency'] ?? 'LKR'))) ?: 'LKR');
                if (!bunny_secret_configured_via_env('ONEPAY_APP_TOKEN')) {
                    recordings_save_setting($pdo, 'onepay_app_token', $keep($settings, 'onepay_app_token', 'onepay_app_token'));
                }
                if (!bunny_secret_configured_via_env('ONEPAY_HASH_SALT')) {
                    recordings_save_setting($pdo, 'onepay_hash_salt', $keep($settings, 'onepay_hash_salt', 'onepay_hash_salt'));
                }
                $_POST['settings_panel'] = 'onepay';
                log_audit($pdo, 'update_onepay_settings', 'settings', null, null, ['onepay_app_id' => trim((string)($_POST['onepay_app_id'] ?? ''))]);
                $success = 'OnePay settings saved.';
                if ($action === 'test_onepay') {
                    $test = (new \Edexcel\Services\OnePayService($pdo))->testConnection();
                    if ($test['ok']) {
                        $success = $test['message'];
                    } else {
                        $success = '';
                        $error = $test['message'];
                    }
                }
            } elseif ($action === 'save_payment_controls') {
                $_POST['settings_panel'] = 'teacher-manual';
                $oldManual = teacher_manual_payment_enabled($pdo) ? '1' : '0';
                $newManual = isset($_POST['teacher_manual_payment']) ? '1' : '0';
                recordings_save_setting($pdo, 'teacher_manual_payment_enabled', $newManual);
                log_audit($pdo, 'update_teacher_manual_payment', 'settings', null, [
                    'teacher_manual_payment' => $oldManual,
                ], [
                    'teacher_manual_payment' => $newManual,
                ]);
                $success = 'Teacher manual payment setting saved.';
            } elseif ($action === 'save_bank') {
                $oldBank = !empty(bank_transfer_config($pdo)['enabled']) ? '1' : '0';
                $newBank = isset($_POST['bank_transfer_enabled']) ? '1' : '0';
                recordings_save_setting($pdo, 'bank_transfer_enabled', $newBank);
                recordings_save_setting($pdo, 'bank_name', trim((string)($_POST['bank_name'] ?? '')));
                recordings_save_setting($pdo, 'bank_account_name', trim((string)($_POST['bank_account_name'] ?? '')));
                recordings_save_setting($pdo, 'bank_account_number', trim((string)($_POST['bank_account_number'] ?? '')));
                recordings_save_setting($pdo, 'bank_branch', trim((string)($_POST['bank_branch'] ?? '')));
                recordings_save_setting($pdo, 'bank_instructions', trim((string)($_POST['bank_instructions'] ?? '')));
                $_POST['settings_panel'] = 'bank';
                log_audit($pdo, 'update_bank_transfer_settings', 'settings', null, [
                    'bank_transfer_enabled' => $oldBank,
                ], [
                    'bank_name' => trim((string)($_POST['bank_name'] ?? '')),
                    'bank_transfer_enabled' => $newBank,
                ]);
                $success = 'Bank transfer settings saved.';
            } elseif (in_array($action, ['test_livekit', 'save_livekit'], true)) {
                recordings_save_setting($pdo, 'classroom_enabled', isset($_POST['classroom_enabled']) ? '1' : '0');
                recordings_save_setting($pdo, 'livekit_enabled', isset($_POST['livekit_enabled']) ? '1' : '0');
                recordings_save_setting($pdo, 'livekit_url', livekit_ws_url(trim((string)($_POST['livekit_url'] ?? ''))));
                recordings_save_setting($pdo, 'classroom_max_participants', (string)max(2, min(200, (int)($_POST['classroom_max_participants'] ?? 50))));
                recordings_save_setting($pdo, 'classroom_join_early_minutes', (string)max(0, min(120, (int)($_POST['classroom_join_early_minutes'] ?? 15))));
                recordings_save_setting($pdo, 'classroom_join_late_minutes', (string)max(0, min(120, (int)($_POST['classroom_join_late_minutes'] ?? 15))));
                recordings_save_setting($pdo, 'classroom_present_minutes', (string)max(1, min(180, (int)($_POST['classroom_present_minutes'] ?? 20))));
                recordings_save_setting($pdo, 'classroom_late_grace_minutes', (string)max(0, min(60, (int)($_POST['classroom_late_grace_minutes'] ?? 10))));
                recordings_save_setting($pdo, 'classroom_student_camera', isset($_POST['classroom_student_camera']) ? '1' : '0');
                recordings_save_setting($pdo, 'classroom_student_mic', isset($_POST['classroom_student_mic']) ? '1' : '0');
                recordings_save_setting($pdo, 'classroom_student_screenshare', isset($_POST['classroom_student_screenshare']) ? '1' : '0');
                recordings_save_setting($pdo, 'classroom_chat_enabled', isset($_POST['classroom_chat_enabled']) ? '1' : '0');
                recordings_save_setting($pdo, 'classroom_whiteboard_enabled', isset($_POST['classroom_whiteboard_enabled']) ? '1' : '0');
                recordings_save_setting($pdo, 'classroom_pdf_whiteboard_enabled', isset($_POST['classroom_pdf_whiteboard_enabled']) ? '1' : '0');
                recordings_save_setting($pdo, 'classroom_pdf_max_mb', (string)max(1, min(100, (int)($_POST['classroom_pdf_max_mb'] ?? 30))));
                recordings_save_setting($pdo, 'classroom_pdf_max_per_session', (string)max(1, min(20, (int)($_POST['classroom_pdf_max_per_session'] ?? 5))));
                recordings_save_setting($pdo, 'classroom_student_pdf_download', isset($_POST['classroom_student_pdf_download']) ? '1' : '0');
                recordings_save_setting($pdo, 'classroom_auto_attendance', isset($_POST['classroom_auto_attendance']) ? '1' : '0');
                recordings_save_setting($pdo, 'classroom_waiting_room', isset($_POST['classroom_waiting_room']) ? '1' : '0');
                recordings_save_setting($pdo, 'classroom_auto_record', isset($_POST['classroom_auto_record']) ? '1' : '0');
                if (!bunny_secret_configured_via_env('LIVEKIT_API_KEY')) {
                    recordings_save_setting($pdo, 'livekit_api_key', $keep($settings, 'livekit_api_key', 'livekit_api_key'));
                }
                if (!bunny_secret_configured_via_env('LIVEKIT_API_SECRET')) {
                    recordings_save_setting($pdo, 'livekit_api_secret', $keep($settings, 'livekit_api_secret', 'livekit_api_secret'));
                }
                recordings_save_setting($pdo, 'livekit_s3_endpoint', trim((string)($_POST['livekit_s3_endpoint'] ?? '')));
                recordings_save_setting($pdo, 'livekit_s3_internal_endpoint', trim((string)($_POST['livekit_s3_internal_endpoint'] ?? '')));
                $s3Bucket = trim((string)($_POST['livekit_s3_bucket'] ?? ''));
                recordings_save_setting($pdo, 'livekit_s3_bucket', $s3Bucket !== '' ? $s3Bucket : 'livekit');
                recordings_save_setting($pdo, 'livekit_s3_region', trim((string)($_POST['livekit_s3_region'] ?? '')) ?: 'us-east-1');
                recordings_save_setting($pdo, 'livekit_s3_force_path_style', isset($_POST['livekit_s3_force_path_style']) ? '1' : '0');
                if (!bunny_secret_configured_via_env('LIVEKIT_S3_ACCESS_KEY')) {
                    recordings_save_setting($pdo, 'livekit_s3_access_key', $keep($settings, 'livekit_s3_access_key', 'livekit_s3_access_key'));
                }
                if (!bunny_secret_configured_via_env('LIVEKIT_S3_SECRET')) {
                    recordings_save_setting($pdo, 'livekit_s3_secret', $keep($settings, 'livekit_s3_secret', 'livekit_s3_secret'));
                }
                $_POST['settings_panel'] = 'classroom';
                log_audit($pdo, 'update_classroom_settings', 'settings', null, null, ['livekit_url' => trim((string)($_POST['livekit_url'] ?? ''))]);
                $success = 'Live classroom settings saved.';
                if ($action === 'test_livekit') {
                    $test = \Edexcel\Services\LiveKitRoomService::fromConfig(livekit_config($pdo))->testConnection();
                    if ($test['ok']) {
                        $success = $test['message'];
                    } else {
                        $success = '';
                        $error = $test['message'];
                    }
                }
            } elseif (in_array($action, ['save_handwriting', 'test_handwriting'], true)) {
                recordings_save_setting($pdo, 'handwriting_enabled', isset($_POST['handwriting_enabled']) ? '1' : '0');
                $model = trim((string)($_POST['classroom_h2t_gemini_model'] ?? ''));
                if ($model === '') {
                    $model = 'gemini-2.0-flash';
                }
                $model = preg_replace('/[^a-zA-Z0-9._\-\/]/', '', $model) ?? 'gemini-2.0-flash';
                recordings_save_setting($pdo, 'classroom_h2t_gemini_model', $model !== '' ? $model : 'gemini-2.0-flash');
                if (!bunny_secret_configured_via_env('GEMINI_API_KEY') && !bunny_secret_configured_via_env('HANDWRITING_GEMINI_API_KEY')) {
                    recordings_save_setting(
                        $pdo,
                        'handwriting_gemini_api_key',
                        $keep($settings, 'handwriting_gemini_api_key', 'handwriting_gemini_api_key')
                    );
                }
                $_POST['settings_panel'] = 'handwriting';
                log_audit($pdo, 'update_handwriting_settings', 'settings', null, null, [
                    'handwriting_enabled' => isset($_POST['handwriting_enabled']) ? 1 : 0,
                    'model' => $model,
                ]);
                $success = 'Handwriting recognition settings saved.';
                if ($action === 'test_handwriting') {
                    $test = \Edexcel\Services\HandwritingRecognitionService::testConfiguration($pdo);
                    if ($test['ok']) {
                        $success = $test['message'];
                    } else {
                        $success = '';
                        $error = $test['message'];
                    }
                }
            } elseif (in_array($action, ['save_oauth', 'test_oauth'], true)) {
                recordings_save_setting($pdo, 'google_oauth_enabled', isset($_POST['google_oauth_enabled']) ? '1' : '0');
                if (!bunny_secret_configured_via_env('GOOGLE_CLIENT_ID')) {
                    recordings_save_setting($pdo, 'google_client_id', trim((string)($_POST['google_client_id'] ?? '')));
                }
                if (!bunny_secret_configured_via_env('GOOGLE_CLIENT_SECRET')) {
                    recordings_save_setting(
                        $pdo,
                        'google_client_secret',
                        $keep($settings, 'google_client_secret', 'google_client_secret')
                    );
                }
                if (!bunny_secret_configured_via_env('GOOGLE_CALLBACK_URL')) {
                    $callback = trim((string)($_POST['google_callback_url'] ?? ''));
                    if ($callback === '') {
                        $callback = rtrim($publicAppUrl, '/') . '/auth/google/callback.php';
                    }
                    recordings_save_setting($pdo, 'google_callback_url', $callback);
                }
                $_POST['settings_panel'] = 'oauth';
                log_audit($pdo, 'update_google_oauth_settings', 'settings', null, null, [
                    'enabled' => isset($_POST['google_oauth_enabled']) ? 1 : 0,
                    'client_id_set' => trim((string)($_POST['google_client_id'] ?? ($settings['google_client_id'] ?? ''))) !== ''
                        || bunny_secret_configured_via_env('GOOGLE_CLIENT_ID'),
                ]);
                $success = 'Google OAuth settings saved.';
                if ($action === 'test_oauth') {
                    $oauth = \Edexcel\Services\GoogleOAuthService::fromConfig($pdo);
                    if ($oauth->isConfigured()) {
                        $success = 'Google OAuth looks configured. Callback URL: ' . $oauth->callbackUrl();
                    } else {
                        $success = '';
                        $error = 'Google OAuth is incomplete. Client ID, client secret, and callback URL are required.';
                    }
                }
            }
            $settings = [];
            $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
            while ($row = $stmt->fetch()) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }
        } catch (Throwable $e) {
            $error = 'Could not save settings.';
        }
    } else {
        $institute_name = college_brand_name(trim($_POST['institute_name'] ?? ''));
        $contact_email = trim((string)($_POST['contact_email'] ?? ''));
        $contact_phone = trim((string)($_POST['contact_phone'] ?? ''));
        $contact_address = trim((string)($_POST['contact_address'] ?? ''));
        $contact_hours = trim((string)($_POST['contact_hours'] ?? ''));
        $fee_per_student = (int)($_POST['fee_per_student'] ?? 0);
        $online_institute_fee = round((float)($_POST['online_institute_fee'] ?? 500), 2);
        $in_college_institute_fee = round((float)($_POST['in_college_institute_fee'] ?? 500), 2);
        $online_handling_percent = round((float)($_POST['online_transaction_handling_percent'] ?? 6), 2);
        $timezone = $_POST['timezone'] ?? '';
        $week_start = $_POST['week_start'] ?? '';
        $class_duration = (int)($_POST['class_duration'] ?? 0);
        $currency_symbol = payment_normalize_currency_symbol((string)($_POST['currency_symbol'] ?? 'Rs'));

        $whatsapp_enabled = isset($_POST['whatsapp_enabled']) ? '1' : '0';
        $whatsapp_provider = whatsapp_provider($pdo);
        $cred = evolution_credentials($pdo);
        $evolution_api_url = evolution_normalize_api_url(trim($_POST['evolution_api_url'] ?? ''));
        $evolution_api_key = trim($_POST['evolution_api_key'] ?? '');
        $evolution_instance = trim($_POST['evolution_instance'] ?? 'edexcel');
        $evolution_admin_number = trim($_POST['evolution_admin_number'] ?? '');
        $student_otp_channel = strtolower(trim((string)($_POST['student_otp_channel'] ?? 'whatsapp'))) === 'sms' ? 'sms' : 'whatsapp';
        $sms_gateway_mode = strtolower(trim((string)($_POST['sms_gateway_mode'] ?? 'cloud'))) === 'local' ? 'local' : 'cloud';
        $sms_gateway_url = trim((string)($_POST['sms_gateway_url'] ?? ''));
        $sms_gateway_username = trim((string)($_POST['sms_gateway_username'] ?? ''));
        $sms_gateway_password = (string)($_POST['sms_gateway_password'] ?? '');
        $sms_gateway_sim = trim((string)($_POST['sms_gateway_sim'] ?? ''));
        $smsSaved = sms_gateway_config($pdo);
        if ($sms_gateway_url === '') {
            $sms_gateway_url = $smsSaved['sms_gateway_url'];
        }
        if ($sms_gateway_username === '') {
            $sms_gateway_username = $smsSaved['sms_gateway_username'];
        }
        if ($sms_gateway_password === '') {
            $sms_gateway_password = $smsSaved['sms_gateway_password'];
        }
        if ($sms_gateway_sim !== '' && !ctype_digit($sms_gateway_sim)) {
            $sms_gateway_sim = '';
        }
        $sms_gateway_url = sms_normalize_gateway_url($sms_gateway_url, $sms_gateway_mode);
        $sms_gateway_username = trim($sms_gateway_username);
        if ($sms_gateway_password !== '') {
            $sms_gateway_password = trim($sms_gateway_password);
        }
        $sms_gateway_device_id = trim((string)($_POST['sms_gateway_device_id'] ?? ''));
        $sms_webhook_secret_in = trim((string)($_POST['sms_webhook_secret'] ?? ''));
        $sms_webhook_secret = $sms_webhook_secret_in !== ''
            ? $sms_webhook_secret_in
            : trim((string)sms_setting($pdo, 'sms_webhook_secret', ''));
        if ($evolution_api_url === '' || edexcel_is_loopback_url($evolution_api_url)) {
            $evolution_api_url = $cred['url'];
        }
        if ($evolution_api_key === '') {
            $evolution_api_key = $cred['key'];
        }
        if ($evolution_instance === '') {
            $evolution_instance = $cred['instance'] ?: 'edexcel';
        }

        // Validate
        if ($fee_per_student <= 0) {
            $error = 'Fee per student must be greater than 0.';
        } elseif ($online_institute_fee < 0 || $in_college_institute_fee < 0) {
            $error = 'Institute fees cannot be negative.';
        } elseif ($online_handling_percent < 0 || $online_handling_percent > 100) {
            $error = 'Online transaction and handling fee must be between 0 and 100 percent.';
        } elseif ($contact_email !== '' && !filter_var($contact_email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Contact email is not valid.';
        } elseif ($class_duration <= 0) {
            $error = 'Class duration must be greater than 0.';
        } elseif ($whatsapp_enabled && $whatsapp_provider === 'evolution' && $evolution_api_key === '') {
            $error = 'Evolution API key is required when WhatsApp is enabled with Evolution.';
        } elseif ($whatsapp_enabled && $whatsapp_provider === 'evolution' && $evolution_api_url === '') {
            $error = 'Evolution API URL is invalid. Use http://127.0.0.1:8080 (the Evolution server), not the college website.';
        } elseif ($student_otp_channel === 'sms' && ($sms_gateway_username === '' || $sms_gateway_password === '')) {
            $error = 'SMS Gateway username and password are required when student OTP is sent by SMS.';
        } elseif ($student_otp_channel === 'sms' && $sms_gateway_mode === 'cloud' && !preg_match('#^https://#i', $sms_gateway_url)) {
            $error = 'Cloud SMS Gateway URL must start with https://';
        } else {
            try {
                $pdo->beginTransaction();
                $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
                $pairs = [
                    ['institute_name', $institute_name],
                    ['contact_email', $contact_email],
                    ['contact_phone', $contact_phone],
                    ['contact_address', $contact_address],
                    ['contact_hours', $contact_hours],
                    ['fee_per_student', $fee_per_student],
                    ['online_institute_fee', number_format($online_institute_fee, 2, '.', '')],
                    ['in_college_institute_fee', number_format($in_college_institute_fee, 2, '.', '')],
                    ['online_transaction_handling_rate', number_format($online_handling_percent / 100, 4, '.', '')],
                    ['timezone', $timezone],
                    ['week_start', $week_start],
                    ['class_duration', $class_duration],
                    ['currency_symbol', $currency_symbol],
                    ['whatsapp_enabled', $whatsapp_enabled],
                    ['evolution_api_url', $evolution_api_url],
                    ['evolution_api_key', $evolution_api_key],
                    ['evolution_instance', $evolution_instance],
                    ['evolution_admin_number', $evolution_admin_number],
                    ['student_otp_channel', $student_otp_channel],
                    ['sms_gateway_mode', $sms_gateway_mode],
                    ['sms_gateway_url', $sms_gateway_url],
                    ['sms_gateway_username', $sms_gateway_username],
                    ['sms_gateway_password', $sms_gateway_password],
                    ['sms_gateway_sim', $sms_gateway_sim],
                    ['sms_gateway_device_id', $sms_gateway_device_id],
                    ['sms_webhook_secret', $sms_webhook_secret],
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
                $auditPost = $_POST;
                unset($auditPost['sms_gateway_password'], $auditPost['evolution_api_key'], $auditPost['sms_webhook_secret']);
                log_audit($pdo, 'update_settings', 'settings', null, null, $auditPost);
                $success = 'Settings updated successfully.';
                if (!empty($_POST['sms_gateway_test'])) {
                    $testPhone = preg_replace('/\D+/', '', (string)($_POST['sms_test_phone'] ?? '')) ?? '';
                    if (str_starts_with($testPhone, '0') && strlen($testPhone) === 10) {
                        $testPhone = '94' . substr($testPhone, 1);
                    }
                    if (!preg_match('/^94(?:7\d{8})$/', $testPhone)) {
                        $success = '';
                        $error = 'Settings saved, but the test SMS number is invalid. Use a Sri Lankan mobile such as 0771234567.';
                    } elseif (sms_send($pdo, $testPhone, "Edexcel College test SMS. Gateway OK.")) {
                        $success = 'Settings saved. A test SMS was accepted by the gateway.';
                    } else {
                        $success = '';
                        $detail = sms_send_last_error();
                        $error = 'Settings saved, but the test SMS could not be sent.'
                            . ($detail !== '' ? ' ' . $detail : ' Check Cloud Server is Online on the phone and the gateway username/password.');
                    }
                }
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

$openPanel = (string)($_POST['settings_panel'] ?? ($_GET['tab'] ?? 'general'));
$allowedPanels = ['general', 'whatsapp', 'evolution', 'meta', 'otp', 'ipromo', 'bunny', 'onepay', 'bank', 'teacher-manual', 'classroom', 'handwriting', 'oauth', 'appearance', 'delete_user', 'site_visitors', 'site_visitor'];
if (!in_array($openPanel, $allowedPanels, true)) {
    $openPanel = 'general';
}
if ($error !== '') {
    $err = strtolower($error);
    if (str_contains($err, 'sms') || str_contains($err, 'otp')) {
        $openPanel = 'otp';
    } elseif (str_contains($err, 'bunny')) {
        $openPanel = 'bunny';
    } elseif ((string)($_POST['settings_panel'] ?? '') === 'teacher-manual') {
        $openPanel = 'teacher-manual';
    } elseif (str_contains($err, 'onepay') || str_contains($err, 'payment')) {
        $openPanel = 'onepay';
    } elseif (str_contains($err, 'bank')) {
        $openPanel = 'bank';
    } elseif (str_contains($err, 'oauth') || str_contains($err, 'google')) {
        $openPanel = 'oauth';
    } elseif (str_contains($err, 'handwriting') || str_contains($err, 'gemini')) {
        $openPanel = 'handwriting';
    } elseif (str_contains($err, 'livekit') || str_contains($err, 'classroom')) {
        $openPanel = 'classroom';
    } elseif (str_contains($err, 'evolution')) {
        $openPanel = 'evolution';
    } elseif (str_contains($err, 'whatsapp') || str_contains($err, 'cloud api')) {
        $openPanel = 'whatsapp';
    } elseif (str_contains($err, 'delete') || str_contains($err, 'user account')) {
        $openPanel = 'delete_user';
    }
} elseif ($success !== '' && str_contains(strtolower($success), 'cloud api')) {
    $openPanel = 'meta';
} elseif ($success !== '' && str_contains(strtolower($success), 'handwriting')) {
    $openPanel = 'handwriting';
} elseif ($success !== '' && str_contains(strtolower($success), 'oauth')) {
    $openPanel = 'oauth';
} elseif ($success !== '' && (str_contains(strtolower($success), 'deleted') || str_contains(strtolower($success), 'deactivated'))) {
    $openPanel = 'delete_user';
}

$tabForPanel = [
    'general' => 'college',
    'whatsapp' => 'messaging',
    'evolution' => 'messaging',
    'meta' => 'messaging',
    'otp' => 'otp',
    'ipromo' => 'ipromo',
    'onepay' => 'payments',
    'bank' => 'payments',
    'teacher-manual' => 'payments',
    'bunny' => 'recordings',
    'classroom' => 'classroom',
    'handwriting' => 'handwriting',
    'oauth' => 'oauth',
    'appearance' => 'appearance',
    'delete_user' => 'delete_user',
    'site_visitors' => 'site_visitors',
    'site_visitor' => 'site_visitors',
];
$activeTab = $tabForPanel[$openPanel] ?? 'college';

$bunnyCfg = bunny_config($pdo);
$onepayCfg = onepay_config($pdo);
$bankCfg = bank_transfer_config($pdo);
$lkCfg = livekit_config($pdo);
$classSet = classroom_settings($pdo);
ensure_recordings_schema($pdo);
$teachersMissingBunny = (new \Edexcel\Services\TeacherBunnyLibraryService($pdo))->teachersMissingLibrary();

// iPromo SMS settings
$ipromoCfg      = ipromo_config($pdo);
$ipromoEnabled  = ipromo_enabled($pdo);
$ipromoConfigured = ipromo_configured($pdo);
$ipromoProvider = sms_active_provider($pdo);

$oauthSvc = \Edexcel\Services\GoogleOAuthService::fromConfig($pdo);
$oauthClientIdEnv = bunny_secret_configured_via_env('GOOGLE_CLIENT_ID');
$oauthSecretEnv = bunny_secret_configured_via_env('GOOGLE_CLIENT_SECRET');
$oauthCallbackEnv = bunny_secret_configured_via_env('GOOGLE_CALLBACK_URL');
$oauthEnabledRaw = trim((string)($settings['google_oauth_enabled'] ?? ''));
if ($oauthEnabledRaw === '' && getenv('GOOGLE_OAUTH_ENABLED') !== false && getenv('GOOGLE_OAUTH_ENABLED') !== '') {
    $oauthEnabledRaw = (string)getenv('GOOGLE_OAUTH_ENABLED');
}
$oauthEnabled = $oauthEnabledRaw === ''
    ? $oauthSvc->isConfigured()
    : ($oauthEnabledRaw === '1' || strtolower($oauthEnabledRaw) === 'true');
$oauthClientId = $oauthClientIdEnv
    ? $oauthSvc->clientId()
    : (string)($settings['google_client_id'] ?? $oauthSvc->clientId());
$oauthCallback = $oauthCallbackEnv
    ? $oauthSvc->callbackUrl()
    : (string)($settings['google_callback_url'] ?? $oauthSvc->callbackUrl());
if ($oauthCallback === '') {
    $oauthCallback = rtrim($publicAppUrl, '/') . '/auth/google/callback.php';
}
$oauthSecretSaved = trim((string)($settings['google_client_secret'] ?? '')) !== '' || $oauthSecretEnv;
$oauthReady = $oauthSvc->isConfigured();

$publicEmail = trim((string)($settings['contact_email'] ?? ''));
if ($publicEmail === '' || strcasecmp($publicEmail, 'info@edexcel.lk') === 0) {
    $publicEmail = 'info@edexcel.college';
}
$publicAddress = trim((string)($settings['contact_address'] ?? ''));
if ($publicAddress === '' || str_contains($publicAddress, 'Central Province')) {
    $publicAddress = "No 83 Katugatota Road, Kandy\nSri Lanka";
}

$metaStatus = MetaEmbeddedSignupService::publicStatus($pdo);
$metaLinked = !empty($metaStatus['connected']);
$otpCh = (($settings['student_otp_channel'] ?? 'whatsapp') === 'sms') ? 'sms' : 'whatsapp';
$smsMode = (($settings['sms_gateway_mode'] ?? 'cloud') === 'local') ? 'local' : 'cloud';

$hwKeySaved = trim((string)($settings['handwriting_gemini_api_key'] ?? ''));
$hwKeyEnv = bunny_secret_configured_via_env('HANDWRITING_GEMINI_API_KEY')
    || bunny_secret_configured_via_env('GEMINI_API_KEY');
$hwEnabled = ($settings['handwriting_enabled'] ?? '1') !== '0';
$hwModel = trim((string)($settings['classroom_h2t_gemini_model'] ?? 'gemini-2.0-flash'));
if ($hwModel === '') {
    $hwModel = 'gemini-2.0-flash';
}
$hwReady = $hwKeyEnv || $hwKeySaved !== '' || trim((string)($settings['gemini_api_key'] ?? '')) !== '';

$base = htmlspecialchars(rtrim((string)BASE_URL, '/'), ENT_QUOTES, 'UTF-8');
$settingsUrl = $base . '/admin/settings.php';

$userDeletionSvc = new UserDeletionService($pdo);
$userDeletionQuery = trim((string)($_GET['q'] ?? ($_POST['user_query'] ?? '')));
$selectedUserId = (int)($_GET['user_id'] ?? ($_POST['target_user_id'] ?? 0));
$selectedAccountType = trim((string)($_GET['account_type'] ?? ($_POST['account_type'] ?? 'user'))) === 'parent' ? 'parent' : 'user';

$deletionSearchResults = [];
if ($userDeletionQuery !== '') {
    $deletionSearchResults = $userDeletionSvc->searchUsers($userDeletionQuery);
}

$selectedUser = null;
if ($selectedUserId > 0) {
    $selectedUser = $userDeletionSvc->getUserDetails($selectedUserId, $selectedAccountType);
} elseif ($userDeletionQuery !== '' && count($deletionSearchResults) === 1 && empty($deletionSearchResults[0]['is_deleted'])) {
    $selectedUser = $deletionSearchResults[0];
}

$tabStudents = [];
if ($activeTab === 'delete_user' || $openPanel === 'delete_user') {
    try {
        $stQuery = $pdo->query("
            SELECT
                u.id AS user_id,
                u.username,
                u.role,
                u.account_status,
                u.is_active,
                u.created_at,
                u.deleted_at,
                COALESCE(NULLIF(TRIM(sp.full_name), ''), '') AS full_name,
                COALESCE(NULLIF(TRIM(sp.email), ''), NULLIF(TRIM(u.google_email), ''), '') AS primary_email,
                COALESCE(NULLIF(TRIM(sp.whatsapp_number), ''), '') AS phone,
                GROUP_CONCAT(DISTINCT c.name ORDER BY c.name SEPARATOR ', ') AS classes
            FROM users u
            LEFT JOIN student_profiles sp ON sp.user_id = u.id
            LEFT JOIN student_enrollments se ON se.student_id = u.id
            LEFT JOIN student_classes c ON c.id = se.class_id AND c.deleted_at IS NULL
            WHERE u.role = 'student'
            GROUP BY u.id
            ORDER BY
                CASE WHEN u.deleted_at IS NOT NULL THEN 1 ELSE 0 END ASC,
                u.username ASC
        ");
        $tabStudents = $stQuery->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        error_log('Error loading students for delete_user tab: ' . $e->getMessage());
        $tabStudents = [];
    }
}

$selectedUserImpact = null;
$selectedUserCanDelete = ['allowed' => false, 'reason' => ''];
if ($selectedUser) {
    $currentAdminId = (int)($_SESSION['user_id'] ?? 0);
    $selectedUserCanDelete = $userDeletionSvc->canDeleteUser(
        (int)$selectedUser['user_id'],
        $currentAdminId,
        $selectedUser['account_type']
    );
    $selectedUserImpact = $userDeletionSvc->getRelatedRecordsImpact(
        (int)$selectedUser['user_id'],
        (string)$selectedUser['role'],
        $selectedUser['account_type']
    );
}

$navItems = [
    'college' => ['icon' => 'bi-building', 'label' => 'College', 'hint' => 'Name, contact, fees, timetable defaults'],
    'messaging' => ['icon' => 'bi-whatsapp', 'label' => 'Messaging', 'hint' => 'WhatsApp sending and Cloud API'],
    'otp' => ['icon' => 'bi-shield-lock', 'label' => 'Student OTP', 'hint' => 'Registration and login codes'],
    'ipromo' => ['icon' => 'bi-chat-dots', 'label' => 'iPromo SMS', 'hint' => 'iPromo Marketing SMS gateway (Sri Lanka)'],
    'oauth' => ['icon' => 'bi-google', 'label' => 'OAuth', 'hint' => 'Google sign-in for students and parents'],
    'payments' => ['icon' => 'bi-wallet2', 'label' => 'Payments', 'hint' => 'OnePay, bank slip, and teacher payments'],
    'classroom' => ['icon' => 'bi-broadcast', 'label' => 'Live classroom', 'hint' => 'LiveKit and class rules'],
    'recordings' => ['icon' => 'bi-camera-reels', 'label' => 'Recordings', 'hint' => 'Bunny Stream libraries'],
    'handwriting' => ['icon' => 'bi-textarea-t', 'label' => 'Handwriting', 'hint' => 'Whiteboard to text'],
    'appearance' => ['icon' => 'bi-palette', 'label' => 'Appearance', 'hint' => 'Theme and homepage'],
    'delete_user' => ['icon' => 'bi-trash3', 'label' => 'Delete User', 'hint' => 'Find and permanently remove a user account'],
    'site_visitors' => ['icon' => 'bi-graph-up-arrow', 'label' => 'Site Visitors', 'hint' => 'Traffic analytics, pageviews, sources'],
];
?>

<div class="admin-dashboard settings-admin">
    <header class="settings-hero welcome-section">
        <div class="settings-hero-main">
            <div>
                <h1><i class="bi bi-gear" aria-hidden="true"></i> System settings</h1>
                <p class="settings-hero-sub">
                    Configure college details, messaging, payments, and classroom tools.
                    Everyday options come first; advanced keys and gateways are labelled clearly.
                </p>
            </div>
            <div class="settings-hero-actions">
                <a class="btn btn-outline-primary btn-sm" href="<?= $base ?>/admin/homepage-layouts.php">
                    <i class="bi bi-layout-wtf"></i> Homepage layouts
                </a>
                <a class="btn btn-outline-secondary btn-sm" href="<?= $base ?>/admin/whatsapp_connect.php">
                    <i class="bi bi-whatsapp"></i> Connect WhatsApp
                </a>
                <a class="btn btn-outline-secondary btn-sm" href="<?= $base ?>/dashboard.php">
                    <i class="bi bi-house"></i> Dashboard
                </a>
            </div>
        </div>
    </header>

    <?php if ($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="settings-shell">
        <nav class="settings-nav" aria-label="Settings sections">
            <p class="settings-nav-label">Browse by task</p>
            <?php foreach ($navItems as $tabId => $item): ?>
                <button
                    type="button"
                    class="settings-nav-link<?= $activeTab === $tabId ? ' is-active' : '' ?>"
                    data-settings-tab="<?= htmlspecialchars($tabId, ENT_QUOTES, 'UTF-8') ?>"
                    aria-pressed="<?= $activeTab === $tabId ? 'true' : 'false' ?>"
                >
                    <i class="bi <?= htmlspecialchars($item['icon'], ENT_QUOTES, 'UTF-8') ?>" aria-hidden="true"></i>
                    <span class="settings-nav-text">
                        <span class="settings-nav-title"><?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?></span>
                        <span class="settings-nav-hint"><?= htmlspecialchars($item['hint'], ENT_QUOTES, 'UTF-8') ?></span>
                    </span>
                </button>
            <?php endforeach; ?>
        </nav>

        <div class="settings-content">

            <!-- Main shared form: College + Messaging + OTP -->
            <form method="POST" id="settingsForm" class="settings-main-form">
                <?= csrf_field() ?>
                <input type="hidden" name="settings_panel" id="settingsPanel" value="<?= htmlspecialchars($openPanel, ENT_QUOTES, 'UTF-8') ?>">

                <section class="settings-tab<?= $activeTab === 'college' ? ' is-active' : '' ?>" data-tab-panel="college"<?= $activeTab === 'college' ? '' : ' hidden' ?>>
                    <div class="settings-tab-head">
                        <h2>College</h2>
                        <p>Public details families see, plus default fee and timetable preferences.</p>
                    </div>

                    <article class="settings-card">
                        <div class="settings-card-head">
                            <h3><i class="bi bi-building"></i> College profile</h3>
                            <p>Shown on the website, receipts, and payment provider reviews.</p>
                        </div>
                        <div class="settings-card-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="institute_name" class="form-label">Institute name</label>
                                    <input type="text" class="form-control" id="institute_name" name="institute_name" value="<?= htmlspecialchars(college_brand_name((string)($settings['institute_name'] ?? ''))) ?>">
                                </div>
                                <div class="col-md-6">
                                    <label for="contact_hours" class="form-label">Office hours</label>
                                    <input type="text" class="form-control" id="contact_hours" name="contact_hours" value="<?= htmlspecialchars($settings['contact_hours'] ?? 'Monday–Friday 8:00–18:00 · Saturday 8:00–14:00') ?>">
                                </div>
                                <div class="col-md-6">
                                    <label for="contact_email" class="form-label">Public email</label>
                                    <input type="email" class="form-control" id="contact_email" name="contact_email" value="<?= htmlspecialchars($publicEmail) ?>" autocomplete="off">
                                </div>
                                <div class="col-md-6">
                                    <label for="contact_phone" class="form-label">Public telephone</label>
                                    <input type="text" class="form-control" id="contact_phone" name="contact_phone" value="<?= htmlspecialchars($settings['contact_phone'] ?? '+94 78 585 8585') ?>" autocomplete="off">
                                </div>
                                <div class="col-12">
                                    <label for="contact_address" class="form-label">Business address</label>
                                    <textarea class="form-control" id="contact_address" name="contact_address" rows="3" placeholder="Building / street, Kandy"><?= htmlspecialchars($publicAddress) ?></textarea>
                                    <div class="form-text">OnePay needs the same street address as on your business registration or utility bill. A city-only address will be rejected.</div>
                                </div>
                            </div>
                        </div>
                    </article>

                    <article class="settings-card">
                        <div class="settings-card-head">
                            <h3><i class="bi bi-cash-coin"></i> Fees &amp; timetable defaults</h3>
                            <p>Used when scheduling lessons and showing amounts.</p>
                        </div>
                        <div class="settings-card-body">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label for="fee_per_student" class="form-label">Default fee per student (Rs)</label>
                                    <input type="number" class="form-control" id="fee_per_student" name="fee_per_student" value="<?= htmlspecialchars($settings['fee_per_student'] ?? 500) ?>" min="1">
                                    <div class="form-text">Can be overridden per lesson.</div>
                                </div>
                                <div class="col-md-4">
                                    <label for="currency_symbol" class="form-label">Currency symbol</label>
                                    <input type="text" class="form-control" id="currency_symbol" name="currency_symbol" value="<?= htmlspecialchars($settings['currency_symbol'] ?? 'Rs') ?>" maxlength="5" autocomplete="off" placeholder="Rs">
                                </div>
                                <div class="col-md-4">
                                    <label for="online_institute_fee" class="form-label">Online institute fee (Rs / student)</label>
                                    <input type="number" class="form-control" id="online_institute_fee" name="online_institute_fee" value="<?= htmlspecialchars($settings['online_institute_fee'] ?? '500.00') ?>" min="0" step="0.01">
                                    <div class="form-text">Taken from the online class fee. The student still pays the class fee entered by the teacher.</div>
                                </div>
                                <div class="col-md-4">
                                    <label for="in_college_institute_fee" class="form-label">In-college institute fee (Rs / student)</label>
                                    <input type="number" class="form-control" id="in_college_institute_fee" name="in_college_institute_fee" value="<?= htmlspecialchars($settings['in_college_institute_fee'] ?? '500.00') ?>" min="0" step="0.01">
                                    <div class="form-text">Used for new in-college classes. Older classes keep their original duration fee. No handling fee.</div>
                                </div>
                                <div class="col-md-4">
                                    <?php
                                    $handlingPercent = ((float)($settings['online_transaction_handling_rate'] ?? 0.06)) * 100;
                                    $handlingPercentLabel = rtrim(rtrim(number_format($handlingPercent, 2, '.', ''), '0'), '.');
                                    ?>
                                    <label for="online_transaction_handling_percent" class="form-label">Online handling fee (%)</label>
                                    <input type="number" class="form-control" id="online_transaction_handling_percent" name="online_transaction_handling_percent" value="<?= htmlspecialchars($handlingPercentLabel !== '' ? $handlingPercentLabel : '6') ?>" min="0" max="100" step="0.01">
                                    <div class="form-text">Percent of the online class fee. Saved classes and past payments keep their original amounts.</div>
                                </div>
                                <div class="col-md-4">
                                    <label for="class_duration" class="form-label">Default class duration (minutes)</label>
                                    <input type="number" class="form-control" id="class_duration" name="class_duration" value="<?= htmlspecialchars($settings['class_duration'] ?? 60) ?>" min="30" step="15">
                                </div>
                                <div class="col-md-6">
                                    <label for="timezone" class="form-label">Timezone</label>
                                    <select class="form-select" id="timezone" name="timezone">
                                        <option value="Asia/Colombo" <?= ($settings['timezone'] ?? '') == 'Asia/Colombo' ? 'selected' : '' ?>>Asia/Colombo</option>
                                        <option value="Asia/Kolkata" <?= ($settings['timezone'] ?? '') == 'Asia/Kolkata' ? 'selected' : '' ?>>Asia/Kolkata</option>
                                        <option value="UTC" <?= ($settings['timezone'] ?? '') == 'UTC' ? 'selected' : '' ?>>UTC</option>
                                        <option value="America/New_York" <?= ($settings['timezone'] ?? '') == 'America/New_York' ? 'selected' : '' ?>>America/New_York</option>
                                        <option value="Europe/London" <?= ($settings['timezone'] ?? '') == 'Europe/London' ? 'selected' : '' ?>>Europe/London</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label for="week_start" class="form-label">Week start day</label>
                                    <select class="form-select" id="week_start" name="week_start">
                                        <option value="Monday" <?= ($settings['week_start'] ?? '') == 'Monday' ? 'selected' : '' ?>>Monday</option>
                                        <option value="Sunday" <?= ($settings['week_start'] ?? '') == 'Sunday' ? 'selected' : '' ?>>Sunday</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </article>

                    <article class="settings-card settings-card-link">
                        <div class="settings-card-head">
                            <h3><i class="bi bi-layout-wtf"></i> Public homepage</h3>
                            <p>Choose which homepage design visitors see (10 layouts).</p>
                        </div>
                        <div class="settings-card-body settings-card-actions">
                            <a class="btn btn-primary" href="<?= $base ?>/admin/homepage-layouts.php">Open homepage layouts</a>
                        </div>
                    </article>
                </section>

                <section class="settings-tab<?= $activeTab === 'messaging' ? ' is-active' : '' ?>" data-tab-panel="messaging"<?= $activeTab === 'messaging' ? '' : ' hidden' ?>>
                    <div class="settings-tab-head">
                        <h2>Messaging</h2>
                        <p>Turn WhatsApp on for the college, connect Meta Cloud API, or use Evolution as a fallback.</p>
                    </div>

                    <article class="settings-card<?= in_array($openPanel, ['whatsapp'], true) ? ' is-focus' : '' ?>" id="card-whatsapp">
                        <div class="settings-card-head">
                            <h3><i class="bi bi-whatsapp"></i> WhatsApp sending</h3>
                            <p>Master switch and the office number for payment alerts.</p>
                        </div>
                        <div class="settings-card-body">
                            <p class="settings-note">Webhook for Connect WhatsApp: <code><?= htmlspecialchars($publicAppUrl) ?>/api/whatsapp/webhook.php</code></p>
                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" id="whatsapp_enabled" name="whatsapp_enabled" <?= ($settings['whatsapp_enabled'] ?? '0') == '1' ? 'checked' : '' ?>>
                                <label class="form-check-label" for="whatsapp_enabled">Enable WhatsApp sending</label>
                            </div>
                            <div class="mb-0">
                                <label for="evolution_admin_number" class="form-label">Admin WhatsApp number</label>
                                <input type="text" class="form-control" id="evolution_admin_number" name="evolution_admin_number" value="<?= htmlspecialchars($settings['evolution_admin_number'] ?? '') ?>" placeholder="94771234567">
                                <div class="form-text">For admin notifications (for example payments). Include country code, no “+” or spaces.</div>
                            </div>
                        </div>
                    </article>

                    <article class="settings-card<?= $openPanel === 'meta' ? ' is-focus' : '' ?>" id="card-meta">
                        <div class="settings-card-head">
                            <h3><i class="bi bi-cloud"></i> Meta Cloud API</h3>
                            <p>Preferred way to link the college WhatsApp number.</p>
                        </div>
                        <div class="settings-card-body">
                            <p class="mb-3">Cloud API is no longer edited as raw fields here. Link a number from Connect WhatsApp, or clear a previous connection and start over.</p>
                            <div class="alert <?= $metaLinked ? 'alert-success' : 'alert-secondary' ?> mb-3">
                                <?php if ($metaLinked): ?>
                                    Linked<?= !empty($metaStatus['display_phone']) ? ' as ' . htmlspecialchars((string)$metaStatus['display_phone']) : '' ?>.
                                    Provider: Meta Cloud API<?= ($metaStatus['onboarding_mode'] ?? '') === 'coexistence' ? ' (coexistence)' : '' ?>.
                                <?php else: ?>
                                    No Cloud API connection is saved. This is a fresh start.
                                <?php endif; ?>
                            </div>
                            <p class="settings-note mb-3">Webhook: <code><?= htmlspecialchars($publicAppUrl) ?>/api/whatsapp/webhook.php</code></p>
                            <div class="d-flex flex-wrap gap-2">
                                <a class="btn btn-success" href="<?= htmlspecialchars(BASE_URL . 'admin/whatsapp_connect.php') ?>">
                                    <i class="bi bi-whatsapp"></i> Connect WhatsApp
                                </a>
                                <button
                                    type="submit"
                                    class="btn btn-outline-danger"
                                    name="action"
                                    value="reset_meta_cloud"
                                    data-confirm="Clear all saved Cloud API tokens and app settings, then start over? This cannot be undone."
                                >
                                    <i class="bi bi-trash"></i> Clear Cloud API and start over
                                </button>
                            </div>
                        </div>
                    </article>

                    <article class="settings-card settings-card-advanced<?= $openPanel === 'evolution' ? ' is-focus' : '' ?>" id="card-evolution">
                        <div class="settings-card-head">
                            <h3><i class="bi bi-hdd-network"></i> Advanced · Evolution API</h3>
                            <p>Only needed when WhatsApp is not connected through Connect WhatsApp. <a href="https://doc.evolution-api.com" target="_blank" rel="noopener">Documentation</a></p>
                        </div>
                        <div class="settings-card-body">
                            <div class="row g-3">
                                <div class="col-md-12">
                                    <label for="evolution_api_url" class="form-label">API URL</label>
                                    <input type="text" class="form-control" id="evolution_api_url" name="evolution_api_url" value="<?= htmlspecialchars(evolution_normalize_api_url((string)($settings['evolution_api_url'] ?? '')) ?: 'http://127.0.0.1:8080') ?>" placeholder="http://127.0.0.1:8080">
                                    <div class="form-text">Evolution server address only, for example <code>http://127.0.0.1:8080</code>. Do not use the college website.</div>
                                </div>
                                <div class="col-md-6">
                                    <label for="evolution_api_key" class="form-label">API key</label>
                                    <input type="password" class="form-control" id="evolution_api_key" name="evolution_api_key" value="" placeholder="<?= !empty($settings['evolution_api_key']) ? 'Saved — leave blank to keep' : 'your-api-key' ?>" autocomplete="off">
                                    <div class="form-text">Leave blank to keep the saved key.</div>
                                </div>
                                <div class="col-md-6">
                                    <label for="evolution_instance" class="form-label">Instance name</label>
                                    <input type="text" class="form-control" id="evolution_instance" name="evolution_instance" value="<?= htmlspecialchars($settings['evolution_instance'] ?? 'edexcel') ?>" placeholder="edexcel">
                                </div>
                            </div>
                        </div>
                    </article>
                </section>

                <section class="settings-tab<?= $activeTab === 'otp' ? ' is-active' : '' ?>" data-tab-panel="otp"<?= $activeTab === 'otp' ? '' : ' hidden' ?>>
                    <div class="settings-tab-head">
                        <h2>Student OTP</h2>
                        <p>How students receive the 6-digit code for registration and forgot-password login. Teacher login stays on WhatsApp.</p>
                    </div>

                    <article class="settings-card">
                        <div class="settings-card-head">
                            <h3><i class="bi bi-phone"></i> Delivery channel</h3>
                            <p>The phone number is still stored as the student username.</p>
                        </div>
                        <div class="settings-card-body">
                            <div class="settings-choice-grid">
                                <label class="settings-choice<?= $otpCh === 'whatsapp' ? ' is-selected' : '' ?>" for="otp_channel_whatsapp">
                                    <input class="form-check-input" type="radio" name="student_otp_channel" id="otp_channel_whatsapp" value="whatsapp" <?= $otpCh === 'whatsapp' ? 'checked' : '' ?>>
                                    <span class="settings-choice-title"><i class="bi bi-whatsapp"></i> WhatsApp OTP</span>
                                    <span class="settings-choice-desc">Uses your connected WhatsApp channel.</span>
                                </label>
                                <label class="settings-choice<?= $otpCh === 'sms' ? ' is-selected' : '' ?>" for="otp_channel_sms">
                                    <input class="form-check-input" type="radio" name="student_otp_channel" id="otp_channel_sms" value="sms" <?= $otpCh === 'sms' ? 'checked' : '' ?>>
                                    <span class="settings-choice-title"><i class="bi bi-chat-text"></i> SMS OTP</span>
                                    <span class="settings-choice-desc">Honor X9c / Hutch via SMS Gateway for Android.</span>
                                </label>
                            </div>
                        </div>
                    </article>

                    <article class="settings-card settings-card-advanced">
                        <div class="settings-card-head">
                            <h3><i class="bi bi-router"></i> SMS gateway setup</h3>
                            <p>Only required when SMS OTP is selected.</p>
                        </div>
                        <div class="settings-card-body">
                            <div class="alert alert-secondary small">
                                <strong>Do not swap these URLs.</strong><br>
                                On the Honor app Settings: keep <code>https://api.sms-gate.app/mobile/v1</code>. Turn Cloud Server on, allow SMS, wait until username and password appear on HOME.<br>
                                On this website only: use <code>https://api.sms-gate.app/3rdparty/v1</code> and paste those credentials.<br>
                                Dual-SIM: set default SMS SIM to Hutch, and set SIM number below to that slot (1 or 2).
                            </div>
                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="sms_gateway_mode" id="sms_mode_cloud" value="cloud" <?= $smsMode === 'cloud' ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="sms_mode_cloud">Cloud (<code>api.sms-gate.app</code>)</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="sms_gateway_mode" id="sms_mode_local" value="local" <?= $smsMode === 'local' ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="sms_mode_local">Local device URL (only if this server can reach the phone)</label>
                                </div>
                            </div>
                            <div class="row g-3">
                                <div class="col-md-8">
                                    <label for="sms_gateway_url" class="form-label">Gateway URL</label>
                                    <input type="text" class="form-control" id="sms_gateway_url" name="sms_gateway_url" value="<?= htmlspecialchars($settings['sms_gateway_url'] ?? 'https://api.sms-gate.app/3rdparty/v1') ?>" placeholder="https://api.sms-gate.app/3rdparty/v1">
                                    <div class="form-text">Website send URL only. Never put this <code>3rdparty</code> address into the phone app.</div>
                                </div>
                                <div class="col-md-4">
                                    <label for="sms_gateway_device_id" class="form-label">Device ID (optional)</label>
                                    <input type="text" class="form-control" id="sms_gateway_device_id" name="sms_gateway_device_id" value="<?= htmlspecialchars($settings['sms_gateway_device_id'] ?? '') ?>" placeholder="From the app Settings → Device ID" autocomplete="off">
                                </div>
                                <div class="col-md-6">
                                    <label for="sms_gateway_username" class="form-label">Gateway username</label>
                                    <input type="text" class="form-control" id="sms_gateway_username" name="sms_gateway_username" value="<?= htmlspecialchars($settings['sms_gateway_username'] ?? '') ?>" autocomplete="off">
                                </div>
                                <div class="col-md-6">
                                    <label for="sms_gateway_password" class="form-label">Gateway password</label>
                                    <input type="password" class="form-control" id="sms_gateway_password" name="sms_gateway_password" value="" placeholder="<?= !empty($settings['sms_gateway_password']) ? 'Saved — leave blank to keep' : 'From the app Cloud Server section' ?>" autocomplete="new-password">
                                    <div class="form-text">Leave blank to keep the saved password.</div>
                                </div>
                                <div class="col-md-4">
                                    <label for="sms_gateway_sim" class="form-label">SIM number (optional)</label>
                                    <input type="number" class="form-control" id="sms_gateway_sim" name="sms_gateway_sim" value="<?= htmlspecialchars($settings['sms_gateway_sim'] ?? '') ?>" min="1" max="2" placeholder="Leave blank for default">
                                    <div class="form-text">Required on dual-SIM Honor. Use the Hutch slot (1 or 2).</div>
                                </div>
                                <div class="col-md-8">
                                    <label for="sms_webhook_secret" class="form-label">SMS delivery webhook secret</label>
                                    <input type="password" class="form-control" id="sms_webhook_secret" name="sms_webhook_secret" value="" placeholder="<?= !empty($settings['sms_webhook_secret']) ? 'Saved — leave blank to keep' : 'Shared secret for delivery callbacks' ?>" autocomplete="new-password">
                                    <div class="form-text">Preferred callback sends header <code>X-SMS-Webhook-Secret</code> to <code><?= htmlspecialchars($publicAppUrl) ?>/api/sms/webhook.php</code>. The <code>?secret=</code> query fallback stays until the SMS gateway is switched, then remove it. The saved secret is not shown here.</div>
                                </div>
                                <div class="col-md-6">
                                    <label for="sms_test_phone" class="form-label">Test SMS number</label>
                                    <input type="text" class="form-control" id="sms_test_phone" name="sms_test_phone" value="" placeholder="077XXXXXXX" autocomplete="off">
                                </div>
                                <div class="col-md-6 d-flex align-items-end">
                                    <button type="submit" name="sms_gateway_test" value="1" class="btn btn-outline-secondary w-100" data-settings-panel-set="otp">
                                        <i class="bi bi-send"></i> Save and send test SMS
                                    </button>
                                </div>
                            </div>
                        </div>
                    </article>

                    <?php include __DIR__ . '/views/sms_devices_section.php'; ?>
                </section>

                <div class="settings-save-bar" data-save-for="college messaging otp"<?= in_array($activeTab, ['college', 'messaging', 'otp'], true) ? '' : ' hidden' ?>>
                    <div class="settings-save-hint">Saves College, Messaging, and Student OTP together.</div>
                    <button type="submit" class="btn btn-primary btn-lg"><i class="bi bi-save"></i> Save settings</button>
                </div>
            </form>

            <!-- ══════════════════════════════════════════════════════════════ -->
            <!-- iPromo SMS Gateway — standalone AJAX form (not the main form) -->
            <!-- ══════════════════════════════════════════════════════════════ -->
            <section class="settings-tab<?= $activeTab === 'ipromo' ? ' is-active' : '' ?>" data-tab-panel="ipromo"<?= $activeTab === 'ipromo' ? '' : ' hidden' ?>>
                <div class="settings-tab-head">
                    <h2>iPromo SMS Gateway</h2>
                    <p>Configure the iPromo Marketing SMS provider (Sri Lanka). This is a separate outbound SMS channel from the SMS-Gate Android app.</p>
                </div>

                <div id="ipromoAlert" class="mb-3" hidden></div>

                <!-- ── Master enable/disable ─────────────────────────────── -->
                <article class="settings-card">
                    <div class="settings-card-head">
                        <h3><i class="bi bi-toggle-on"></i> SMS Gateway switch</h3>
                        <p>Controls whether iPromo SMS is active. When OFF, no SMS is sent through iPromo regardless of credentials.</p>
                    </div>
                    <div class="settings-card-body">
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <div class="form-check form-switch mb-0">
                                <input class="form-check-input" type="checkbox" role="switch" id="ipromoEnabledToggle"
                                       <?= $ipromoEnabled ? 'checked' : '' ?>>
                                <label class="form-check-label fw-semibold" for="ipromoEnabledToggle">
                                    Enable iPromo SMS Gateway
                                </label>
                            </div>
                            <span id="ipromoStatusBadge" class="badge <?= $ipromoEnabled ? 'bg-success' : 'bg-secondary' ?>">
                                <?= $ipromoEnabled ? 'Enabled' : 'Disabled' ?>
                            </span>
                        </div>
                        <?php if (!$ipromoEnabled): ?>
                        <div class="alert alert-secondary small mb-0">
                            <i class="bi bi-info-circle"></i> iPromo SMS is currently <strong>disabled</strong>. All SMS sending functions will skip iPromo until you enable it.
                        </div>
                        <?php endif; ?>
                    </div>
                </article>

                <!-- ── Active provider selector ──────────────────────────── -->
                <article class="settings-card">
                    <div class="settings-card-head">
                        <h3><i class="bi bi-diagram-3"></i> Active SMS provider</h3>
                        <p>Which provider handles SMS when <code>sms_dispatch()</code> is called. The existing SMS-Gate Android app is unaffected by this choice.</p>
                    </div>
                    <div class="settings-card-body">
                        <div class="settings-choice-grid">
                            <label class="settings-choice<?= $ipromoProvider === 'sms-gate' ? ' is-selected' : '' ?>" for="smsProviderSmsGate" id="smsProviderSmsGateLabel">
                                <input class="form-check-input" type="radio" name="sms_provider_sel" id="smsProviderSmsGate" value="sms-gate"
                                       <?= $ipromoProvider === 'sms-gate' ? 'checked' : '' ?>>
                                <span class="settings-choice-title"><i class="bi bi-phone"></i> SMS-Gate (Android)</span>
                                <span class="settings-choice-desc">Existing Honor/Hutch Android phone gateway. No change to current behaviour.</span>
                            </label>
                            <label class="settings-choice<?= $ipromoProvider === 'ipromo' ? ' is-selected' : '' ?>" for="smsProviderIpromo" id="smsProviderIpromoLabel">
                                <input class="form-check-input" type="radio" name="sms_provider_sel" id="smsProviderIpromo" value="ipromo"
                                       <?= $ipromoProvider === 'ipromo' ? 'checked' : '' ?>>
                                <span class="settings-choice-title"><i class="bi bi-chat-dots"></i> iPromo Marketing</span>
                                <span class="settings-choice-desc">Cloud SMS API. iPromo must also be enabled above.</span>
                            </label>
                        </div>
                    </div>
                </article>

                <!-- ── iPromo credentials ─────────────────────────────────── -->
                <article class="settings-card">
                    <div class="settings-card-head">
                        <h3><i class="bi bi-key"></i> iPromo API credentials</h3>
                        <p>From your <a href="https://console.ipromo.lk" target="_blank" rel="noopener">iPromo console</a>. Credentials are stored in the database and never exposed in HTML or logs.</p>
                    </div>
                    <div class="settings-card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="ipromoUsername" class="form-label">API Username</label>
                                <input type="text" class="form-control" id="ipromoUsername" name="ipromo_username"
                                       value="<?= htmlspecialchars($ipromoCfg['username']) ?>"
                                       placeholder="Your iPromo account username"
                                       autocomplete="off">
                            </div>
                            <div class="col-md-6">
                                <label for="ipromoApiKey" class="form-label">API Key</label>
                                <input type="password" class="form-control" id="ipromoApiKey" name="ipromo_api_key"
                                       value=""
                                       placeholder="<?= $ipromoCfg['api_key'] !== '' ? 'Saved — leave blank to keep' : 'Your iPromo API key' ?>"
                                       autocomplete="new-password">
                                <div class="form-text">Leave blank to keep the saved key.</div>
                            </div>
                            <div class="col-md-6">
                                <label for="ipromoSenderId" class="form-label">Sender ID</label>
                                <input type="text" class="form-control" id="ipromoSenderId" name="ipromo_sender_id"
                                       value="<?= htmlspecialchars($ipromoCfg['sender_id']) ?>"
                                       placeholder="Registered sender name (e.g. EDEXCEL)"
                                       maxlength="20">
                                <div class="form-text">Must be a registered sender ID in your iPromo account.</div>
                            </div>
                            <div class="col-md-6">
                                <label for="ipromoApiUrl" class="form-label">API Base URL</label>
                                <input type="url" class="form-control" id="ipromoApiUrl" name="ipromo_api_url"
                                       value="<?= htmlspecialchars($ipromoCfg['url']) ?>"
                                       placeholder="https://console.ipromo.lk/api/v3/sms/send">
                                <div class="form-text">Leave as default unless iPromo support provides a different endpoint.</div>
                            </div>
                        </div>

                        <?php if ($ipromoConfigured): ?>
                        <div class="alert alert-success small mt-3 mb-0">
                            <i class="bi bi-check-circle"></i>
                            iPromo credentials are configured.
                            Username: <strong><?= htmlspecialchars($ipromoCfg['username']) ?></strong> |
                            Sender: <strong><?= htmlspecialchars($ipromoCfg['sender_id']) ?></strong> |
                            Key: <strong>*****</strong>
                        </div>
                        <?php else: ?>
                        <div class="alert alert-warning small mt-3 mb-0">
                            <i class="bi bi-exclamation-triangle"></i> iPromo credentials are incomplete. Enter username, API key, and Sender ID.
                        </div>
                        <?php endif; ?>
                    </div>
                </article>

                <!-- ── Save settings + Test SMS ─────────────────────────── -->
                <article class="settings-card">
                    <div class="settings-card-head">
                        <h3><i class="bi bi-send"></i> Save &amp; Test</h3>
                    </div>
                    <div class="settings-card-body">
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label for="ipromoTestPhone" class="form-label">Test mobile number</label>
                                <input type="text" class="form-control" id="ipromoTestPhone" name="test_phone"
                                       placeholder="077XXXXXXX" autocomplete="off" maxlength="15">
                                <div class="form-text">Sri Lankan mobile number. Max 5 test messages per 5 minutes.</div>
                            </div>
                            <div class="col-md-6">
                                <label for="ipromoTestMsg" class="form-label">Test message</label>
                                <input type="text" class="form-control" id="ipromoTestMsg" name="test_message"
                                       value="Edexcel College iPromo SMS test. Gateway OK."
                                       maxlength="160">
                            </div>
                        </div>

                        <div class="d-flex gap-2 flex-wrap">
                            <button type="button" class="btn btn-primary" id="ipromoSaveBtn">
                                <i class="bi bi-save"></i> Save iPromo Settings
                            </button>
                            <button type="button" class="btn btn-outline-secondary" id="ipromoTestBtn">
                                <i class="bi bi-send"></i> Save &amp; Send Test SMS
                            </button>
                            <a href="<?= $base ?>/admin/bulk_sms.php" class="btn btn-outline-primary btn-sm ms-auto align-self-center">
                                <i class="bi bi-chat-left-dots"></i> Go to Bulk SMS
                            </a>
                            <a href="<?= $base ?>/admin/sms_logs.php?provider=ipromo" class="btn btn-outline-info btn-sm align-self-center">
                                <i class="bi bi-list-ul"></i> View SMS Logs
                            </a>
                        </div>

                        <div id="ipromoResult" class="mt-3" hidden></div>
                    </div>
                </article>
            </section><!-- /ipromo -->

            <!-- Payments -->

            <section class="settings-tab<?= $activeTab === 'payments' ? ' is-active' : '' ?>" data-tab-panel="payments"<?= $activeTab === 'payments' ? '' : ' hidden' ?>>
                <div class="settings-tab-head">
                    <h2>Payments</h2>
                    <p>Online card payments, bank transfer, and whether teachers can mark online-class students as paid.</p>
                </div>

                <article class="settings-card<?= $openPanel === 'onepay' ? ' is-focus' : '' ?>" id="card-onepay">
                    <div class="settings-card-head">
                        <h3><i class="bi bi-credit-card"></i> OnePay</h3>
                        <p>Official OnePay v3 redirection. Confirmed via transaction status API, not the browser return page.</p>
                    </div>
                    <div class="settings-card-body">
                        <form method="POST">
                            <?= csrf_field() ?>
                            <input type="hidden" name="settings_panel" value="onepay">
                            <p class="settings-note small">Public pages OnePay reviews:
                                <a href="<?= $base ?>/contact.php">Contact</a>,
                                <a href="<?= $base ?>/terms.php">Terms</a>,
                                <a href="<?= $base ?>/privacy.php">Privacy</a>,
                                <a href="<?= $base ?>/refund.php">Refund</a>.
                            </p>
                            <p class="settings-note small mb-1">Return URL: <code><?= htmlspecialchars($onepayCfg['return_url']) ?></code></p>
                            <p class="settings-note small mb-3">Callback URL: <code><?= htmlspecialchars($onepayCfg['webhook_url']) ?></code></p>
                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" id="onepay_enabled" name="onepay_enabled" <?= !empty($onepayCfg['enabled']) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="onepay_enabled">Enable OnePay</label>
                            </div>
                            <div class="mb-3">
                                <span class="form-label d-block">Environment</span>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="onepay_environment" id="onepay_sandbox" value="sandbox" <?= $onepayCfg['environment'] === 'sandbox' ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="onepay_sandbox">Sandbox</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="onepay_environment" id="onepay_production" value="production" <?= $onepayCfg['environment'] !== 'sandbox' ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="onepay_production">Production</label>
                                </div>
                            </div>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">App ID</label>
                                    <input class="form-control" name="onepay_app_id" value="<?= htmlspecialchars($onepayCfg['app_id']) ?>" autocomplete="off">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Currency</label>
                                    <input class="form-control" name="onepay_currency" value="<?= htmlspecialchars($onepayCfg['currency']) ?>" maxlength="3">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">App token (Authorization header)</label>
                                    <input type="password" class="form-control" name="onepay_app_token" value="" placeholder="<?= $onepayCfg['app_token'] !== '' ? 'Saved — leave blank to keep' : '' ?>" autocomplete="new-password">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Hash salt</label>
                                    <input type="password" class="form-control" name="onepay_hash_salt" value="" placeholder="<?= $onepayCfg['hash_salt'] !== '' ? 'Saved — leave blank to keep' : '' ?>" autocomplete="new-password">
                                    <div class="form-text">Hash is <code>sha256(app_id + currency + amount + HASH_SALT)</code>.</div>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">API URL</label>
                                    <input class="form-control" name="onepay_api_url" value="<?= htmlspecialchars($onepayCfg['api_url']) ?>">
                                </div>
                            </div>
                            <div class="settings-form-actions">
                                <button class="btn btn-primary" type="submit" name="action" value="save_onepay"><i class="bi bi-save"></i> Save OnePay</button>
                                <button class="btn btn-outline-secondary" type="submit" name="action" value="test_onepay"><i class="bi bi-plug"></i> Test connection</button>
                            </div>
                        </form>
                    </div>
                </article>

                <article class="settings-card<?= $openPanel === 'bank' ? ' is-focus' : '' ?>" id="card-bank">
                    <div class="settings-card-head">
                        <h3><i class="bi bi-bank"></i> Bank transfer</h3>
                        <p>Account details shown so families can transfer and upload a slip. Staff confirm on Campus → Bank slips.</p>
                    </div>
                    <div class="settings-card-body">
                        <form method="POST">
                            <?= csrf_field() ?>
                            <input type="hidden" name="settings_panel" value="bank">
                            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                                <span class="badge <?= !empty($bankCfg['enabled']) ? 'text-bg-success' : 'text-bg-secondary' ?>"><?= !empty($bankCfg['enabled']) ? 'Enabled' : 'Disabled' ?></span>
                            </div>
                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" role="switch" id="bank_transfer_enabled" name="bank_transfer_enabled" <?= !empty($bankCfg['enabled']) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="bank_transfer_enabled">Allow bank-transfer slip uploads</label>
                            </div>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Bank</label>
                                    <input class="form-control" name="bank_name" value="<?= htmlspecialchars($bankCfg['bank_name']) ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Branch</label>
                                    <input class="form-control" name="bank_branch" value="<?= htmlspecialchars($bankCfg['branch']) ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Account name</label>
                                    <input class="form-control" name="bank_account_name" value="<?= htmlspecialchars($bankCfg['account_name']) ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Account number</label>
                                    <input class="form-control" name="bank_account_number" value="<?= htmlspecialchars($bankCfg['account_number']) ?>" autocomplete="off">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Instructions shown to families</label>
                                    <textarea class="form-control" name="bank_instructions" rows="3"><?= htmlspecialchars($bankCfg['instructions']) ?></textarea>
                                </div>
                            </div>
                            <div class="settings-form-actions">
                                <button class="btn btn-primary" type="submit" name="action" value="save_bank"><i class="bi bi-save"></i> Save bank details</button>
                                <a class="btn btn-outline-secondary" href="<?= htmlspecialchars(BASE_URL) ?>campus/bank_slips.php">Open bank slips</a>
                            </div>
                        </form>
                    </div>
                </article>

                <?php $teacherManualOn = teacher_manual_payment_enabled($pdo); ?>
                <article class="settings-card<?= $openPanel === 'teacher-manual' ? ' is-focus' : '' ?>" id="card-teacher-manual">
                    <div class="settings-card-head">
                        <h3><i class="bi bi-person-check"></i> Teacher Manual Payment — Online Classes</h3>
                        <p>This switch is separate from OnePay and bank transfer. Turning it off does not change those methods or existing payments.</p>
                    </div>
                    <div class="settings-card-body">
                        <form method="POST">
                            <?= csrf_field() ?>
                            <input type="hidden" name="settings_panel" value="teacher-manual">
                            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                                <div class="fw-semibold">Allow teachers to manually mark online-class students as paid</div>
                                <span class="badge <?= $teacherManualOn ? 'text-bg-success' : 'text-bg-secondary' ?>"><?= $teacherManualOn ? 'Enabled' : 'Disabled' ?></span>
                            </div>
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" role="switch" id="teacher_manual_payment" name="teacher_manual_payment" <?= $teacherManualOn ? 'checked' : '' ?>>
                                <label class="form-check-label" for="teacher_manual_payment">ON / OFF</label>
                            </div>
                            <p class="small text-muted mb-3">When enabled, teachers can manually mark students as paid for online classes. When disabled, teachers cannot manually record or mark online-class payments as paid.</p>
                            <button class="btn btn-primary" type="submit" name="action" value="save_payment_controls"><i class="bi bi-save"></i> Save teacher manual payment</button>
                        </form>
                    </div>
                </article>
            </section>

            <!-- Live classroom -->
            <section class="settings-tab<?= $activeTab === 'classroom' ? ' is-active' : '' ?>" data-tab-panel="classroom"<?= $activeTab === 'classroom' ? '' : ' hidden' ?>>
                <div class="settings-tab-head">
                    <h2>Live classroom</h2>
                    <p>Video runs on LiveKit. This site stays on edexcel.college. Students never see API keys.</p>
                </div>
                <form method="POST">
                    <?= csrf_field() ?>
                    <input type="hidden" name="settings_panel" value="classroom">

                    <article class="settings-card">
                        <div class="settings-card-head">
                            <h3><i class="bi bi-broadcast"></i> Connection</h3>
                            <p>Turn live classes on and point at LiveKit Cloud or your VPS.</p>
                        </div>
                        <div class="settings-card-body">
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" id="classroom_enabled" name="classroom_enabled" <?= !empty($classSet['enabled']) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="classroom_enabled">Turn on live classes</label>
                            </div>
                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" id="livekit_enabled" name="livekit_enabled" <?= !empty($lkCfg['enabled']) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="livekit_enabled">Connect to LiveKit</label>
                            </div>
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label">LiveKit URL</label>
                                    <input class="form-control" name="livekit_url" value="<?= htmlspecialchars($lkCfg['url']) ?>" placeholder="wss://live.kandy.edexcel.college:8443" autocomplete="off">
                                    <div class="form-text">Cloud: <code>wss://….livekit.cloud</code>. VPS: <code>wss://live.kandy.edexcel.college:8443</code>.</div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">API key</label>
                                    <input type="password" class="form-control" name="livekit_api_key" value="" placeholder="<?= $lkCfg['api_key'] !== '' ? 'Saved — leave blank to keep' : 'API key' ?>" autocomplete="new-password">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">API secret</label>
                                    <input type="password" class="form-control" name="livekit_api_secret" value="" placeholder="<?= $lkCfg['api_secret'] !== '' ? 'Saved — leave blank to keep' : 'API secret' ?>" autocomplete="new-password">
                                </div>
                            </div>
                        </div>
                    </article>

                    <article class="settings-card">
                        <div class="settings-card-head">
                            <h3><i class="bi bi-clock"></i> Join window &amp; attendance</h3>
                        </div>
                        <div class="settings-card-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Maximum people in a class</label>
                                    <input type="number" class="form-control" name="classroom_max_participants" min="2" max="200" value="<?= (int)$classSet['max_participants'] ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Students may join this many minutes early</label>
                                    <input type="number" class="form-control" name="classroom_join_early_minutes" min="0" max="120" value="<?= (int)$classSet['join_early_minutes'] ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Disable Join this many minutes after class ends</label>
                                    <input type="number" class="form-control" name="classroom_join_late_minutes" min="0" max="120" value="<?= (int)($classSet['join_late_minutes'] ?? 15) ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Minutes in class to count as present</label>
                                    <input type="number" class="form-control" name="classroom_present_minutes" min="1" max="180" value="<?= (int)$classSet['present_minutes'] ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Late after this many minutes</label>
                                    <input type="number" class="form-control" name="classroom_late_grace_minutes" min="0" max="60" value="<?= (int)$classSet['late_grace_minutes'] ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Max PDF upload size (MB)</label>
                                    <input type="number" class="form-control" name="classroom_pdf_max_mb" min="1" max="100" value="<?= (int)($classSet['pdf_max_mb'] ?? 30) ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Max PDFs per live class session</label>
                                    <input type="number" class="form-control" name="classroom_pdf_max_per_session" min="1" max="20" value="<?= (int)($classSet['pdf_max_per_session'] ?? 5) ?>">
                                </div>
                            </div>
                        </div>
                    </article>

                    <article class="settings-card">
                        <div class="settings-card-head">
                            <h3><i class="bi bi-sliders"></i> Student permissions &amp; features</h3>
                        </div>
                        <div class="settings-card-body settings-check-grid">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="classroom_student_camera" name="classroom_student_camera" <?= !empty($classSet['student_camera']) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="classroom_student_camera">Students may use camera</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="classroom_student_mic" name="classroom_student_mic" <?= !empty($classSet['student_mic']) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="classroom_student_mic">Students may use microphone</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="classroom_student_screenshare" name="classroom_student_screenshare" <?= !empty($classSet['student_screenshare']) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="classroom_student_screenshare">Students may share their screen</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="classroom_chat_enabled" name="classroom_chat_enabled" <?= !empty($classSet['chat_enabled']) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="classroom_chat_enabled">Chat</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="classroom_whiteboard_enabled" name="classroom_whiteboard_enabled" <?= !empty($classSet['whiteboard_enabled']) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="classroom_whiteboard_enabled">Whiteboard</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="classroom_pdf_whiteboard_enabled" name="classroom_pdf_whiteboard_enabled" <?= !empty($classSet['pdf_whiteboard_enabled']) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="classroom_pdf_whiteboard_enabled">Interactive PDF whiteboard</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="classroom_student_pdf_download" name="classroom_student_pdf_download" <?= !empty($classSet['student_pdf_download']) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="classroom_student_pdf_download">Students may download lesson PDF</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="classroom_auto_attendance" name="classroom_auto_attendance" <?= !empty($classSet['auto_attendance']) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="classroom_auto_attendance">Save attendance when the teacher ends class</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="classroom_waiting_room" name="classroom_waiting_room" <?= !empty($classSet['waiting_room']) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="classroom_waiting_room">Waiting room (admit after class is live)</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="classroom_auto_record" name="classroom_auto_record" <?= !empty($classSet['auto_record']) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="classroom_auto_record">Record live classes into Bunny</label>
                            </div>
                        </div>
                    </article>

                    <article class="settings-card settings-card-advanced">
                        <div class="settings-card-head">
                            <h3><i class="bi bi-hdd-stack"></i> Advanced · Recording storage (VPS)</h3>
                            <p>Self-hosted egress writes to MinIO / S3, then Bunny fetches a short-lived HTTPS link. Without this, live class still works; recording is skipped.</p>
                        </div>
                        <div class="settings-card-body">
                            <?php if (!empty($classSet['auto_record']) && !livekit_is_cloud($lkCfg) && !livekit_s3_configured($lkCfg)): ?>
                                <div class="alert alert-warning small">Record live classes is on, but MinIO/S3 is not filled in. Classes will run without a Bunny recording until you save the storage fields.</div>
                            <?php endif; ?>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Public S3 URL (HTTPS)</label>
                                    <input class="form-control" name="livekit_s3_endpoint" value="<?= htmlspecialchars($lkCfg['s3_endpoint']) ?>" placeholder="https://s3.live.kandy.edexcel.college" autocomplete="off">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Internal S3 URL (optional)</label>
                                    <input class="form-control" name="livekit_s3_internal_endpoint" value="<?= htmlspecialchars($lkCfg['s3_internal_endpoint']) ?>" placeholder="http://minio:9000" autocomplete="off">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Bucket</label>
                                    <input class="form-control" name="livekit_s3_bucket" value="<?= htmlspecialchars($lkCfg['s3_bucket']) ?>" placeholder="livekit" autocomplete="off">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Region</label>
                                    <input class="form-control" name="livekit_s3_region" value="<?= htmlspecialchars($lkCfg['s3_region']) ?>" placeholder="us-east-1" autocomplete="off">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">S3 access key</label>
                                    <input type="password" class="form-control" name="livekit_s3_access_key" value="" placeholder="<?= $lkCfg['s3_access_key'] !== '' ? 'Saved — leave blank to keep' : 'MinIO user / access key' ?>" autocomplete="new-password">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">S3 secret</label>
                                    <input type="password" class="form-control" name="livekit_s3_secret" value="" placeholder="<?= $lkCfg['s3_secret'] !== '' ? 'Saved — leave blank to keep' : 'MinIO password / secret' ?>" autocomplete="new-password">
                                </div>
                                <div class="col-12">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="livekit_s3_force_path_style" name="livekit_s3_force_path_style" <?= !empty($lkCfg['s3_force_path_style']) ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="livekit_s3_force_path_style">Path-style URLs (leave on for MinIO)</label>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">LiveKit webhook URL</label>
                                    <input class="form-control" type="url" readonly value="<?= htmlspecialchars(classroom_livekit_webhook_url()) ?>">
                                    <div class="form-text">
                                        <?php if (livekit_is_cloud($lkCfg)): ?>
                                            Paste this in LiveKit Cloud → Settings → Webhooks.
                                        <?php else: ?>
                                            On a self-hosted VPS this belongs in <code>livekit.yaml</code> (<code>webhook.urls</code>).
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </article>

                    <div class="settings-form-actions sticky-actions">
                        <button class="btn btn-primary btn-lg" type="submit" name="action" value="save_livekit"><i class="bi bi-save"></i> Save live classroom</button>
                        <button class="btn btn-outline-secondary" type="submit" name="action" value="test_livekit"><i class="bi bi-plug"></i> Test connection</button>
                    </div>
                </form>
            </section>

            <!-- Recordings / Bunny -->
            <section class="settings-tab<?= $activeTab === 'recordings' ? ' is-active' : '' ?>" data-tab-panel="recordings"<?= $activeTab === 'recordings' ? '' : ' hidden' ?>>
                <div class="settings-tab-head">
                    <h2>Recordings</h2>
                    <p>Bunny Stream account settings. Each teacher needs their own library ID under Teachers → Edit.</p>
                </div>
                <article class="settings-card">
                    <div class="settings-card-head">
                        <h3><i class="bi bi-play-btn"></i> Bunny.net</h3>
                        <p>Save the account API key here. Do not share one library across teachers.</p>
                    </div>
                    <div class="settings-card-body">
                        <form method="POST">
                            <?= csrf_field() ?>
                            <input type="hidden" name="settings_panel" value="bunny">
                            <p class="settings-note small mb-1">Set this webhook URL on every teacher library:</p>
                            <p class="settings-note mb-3"><code><?= htmlspecialchars($bunnyCfg['webhook_url']) ?></code></p>
                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" id="bunny_enabled" name="bunny_enabled" <?= !empty($bunnyCfg['enabled']) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="bunny_enabled">Enable Bunny recordings</label>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Account API key</label>
                                <input type="password" class="form-control" name="bunny_account_api_key" value="" placeholder="<?= $bunnyCfg['account_api_key'] !== '' ? 'Saved — leave blank to keep' : 'Account AccessKey from bunny.net (not a library key)' ?>" autocomplete="new-password">
                                <?php if (bunny_secret_configured_via_env('BUNNY_ACCOUNT_API_KEY')): ?>
                                    <div class="form-text">Using BUNNY_ACCOUNT_API_KEY from the server environment.</div>
                                <?php endif; ?>
                            </div>
                            <?php if ($teachersMissingBunny !== []): ?>
                                <div class="alert alert-warning small">
                                    Teachers without a unique library:
                                    <?php foreach ($teachersMissingBunny as $missing): ?>
                                        <a href="<?= htmlspecialchars(BASE_URL . 'teachers/edit.php?id=' . (int)$missing['id']) ?>"><?= htmlspecialchars((string)$missing['name']) ?></a><?= $missing !== end($teachersMissingBunny) ? ', ' : '' ?>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" id="recording_retention_enabled" name="recording_retention_enabled" <?= !empty($bunnyCfg['retention_enabled']) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="recording_retention_enabled">Mark old recordings for deletion (does not delete Bunny files automatically)</label>
                            </div>
                            <div class="mb-3" style="max-width:12rem">
                                <label class="form-label">Retention days</label>
                                <input type="number" class="form-control" name="recording_retention_days" min="1" value="<?= (int)$bunnyCfg['retention_days'] ?>">
                            </div>
                            <div class="settings-form-actions">
                                <button class="btn btn-primary" type="submit" name="action" value="save_bunny"><i class="bi bi-save"></i> Save Bunny</button>
                                <button class="btn btn-outline-secondary" type="submit" name="action" value="test_bunny"><i class="bi bi-plug"></i> Test connection</button>
                            </div>
                        </form>
                    </div>
                </article>
            </section>

            <!-- Handwriting -->
            <section class="settings-tab<?= $activeTab === 'handwriting' ? ' is-active' : '' ?>" data-tab-panel="handwriting"<?= $activeTab === 'handwriting' ? '' : ' hidden' ?>>
                <div class="settings-tab-head">
                    <h2>Handwriting to text</h2>
                    <p>Powers the classroom whiteboard convert tool. Recognition runs on the server; teachers never see the API key.</p>
                </div>
                <article class="settings-card">
                    <div class="settings-card-body">
                        <form method="POST">
                            <?= csrf_field() ?>
                            <input type="hidden" name="settings_panel" value="handwriting">
                            <p class="settings-note mb-3">
                                Get a free key from
                                <a href="https://aistudio.google.com/apikey" target="_blank" rel="noopener">Google AI Studio</a>.
                            </p>
                            <?php if ($hwReady): ?>
                                <div class="alert alert-success small py-2">Handwriting API key is configured<?= $hwKeyEnv ? ' via the server environment' : '' ?>.</div>
                            <?php else: ?>
                                <div class="alert alert-warning small py-2">No handwriting API key yet. Conversion will show “not configured” until you save a Gemini key below.</div>
                            <?php endif; ?>
                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" id="handwriting_enabled" name="handwriting_enabled" <?= $hwEnabled ? 'checked' : '' ?>>
                                <label class="form-check-label" for="handwriting_enabled">Enable handwriting recognition on the whiteboard</label>
                            </div>
                            <div class="row g-3">
                                <div class="col-md-7">
                                    <label class="form-label" for="handwriting_gemini_api_key">Gemini API key</label>
                                    <input type="password" class="form-control" id="handwriting_gemini_api_key" name="handwriting_gemini_api_key" value="" placeholder="<?= ($hwKeySaved !== '' || $hwKeyEnv) ? 'Saved — leave blank to keep' : 'Paste Gemini API key' ?>" autocomplete="new-password" <?= $hwKeyEnv ? 'disabled' : '' ?>>
                                    <?php if ($hwKeyEnv): ?>
                                        <div class="form-text">Using environment <code>HANDWRITING_GEMINI_API_KEY</code> or <code>GEMINI_API_KEY</code>.</div>
                                    <?php else: ?>
                                        <div class="form-text">Falls back to the shared WhatsApp Gemini key if empty.</div>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-5">
                                    <label class="form-label" for="classroom_h2t_gemini_model">Gemini model</label>
                                    <input type="text" class="form-control" id="classroom_h2t_gemini_model" name="classroom_h2t_gemini_model" value="<?= htmlspecialchars($hwModel) ?>" placeholder="gemini-2.0-flash" autocomplete="off">
                                    <div class="form-text">Must support vision. Default: <code>gemini-2.0-flash</code>.</div>
                                </div>
                            </div>
                            <div class="settings-form-actions">
                                <button class="btn btn-primary" type="submit" name="action" value="save_handwriting"><i class="bi bi-save"></i> Save handwriting</button>
                                <button class="btn btn-outline-secondary" type="submit" name="action" value="test_handwriting"><i class="bi bi-plug"></i> Test API</button>
                            </div>
                        </form>
                    </div>
                </article>
            </section>

            <!-- Google OAuth -->
            <section class="settings-tab<?= $activeTab === 'oauth' ? ' is-active' : '' ?>" data-tab-panel="oauth"<?= $activeTab === 'oauth' ? '' : ' hidden' ?>>
                <div class="settings-tab-head">
                    <h2>Google OAuth</h2>
                    <p>Student &amp; Parent Portal sign-in. Never creates admin or teacher accounts. Parent access still requires verification.</p>
                </div>
                <article class="settings-card">
                    <div class="settings-card-body">
                        <form method="POST">
                            <?= csrf_field() ?>
                            <input type="hidden" name="settings_panel" value="oauth">
                            <?php if ($oauthReady && $oauthEnabled): ?>
                                <div class="alert alert-success small py-2">Google sign-in is ready<?= ($oauthClientIdEnv || $oauthSecretEnv || $oauthCallbackEnv) ? ' (environment overrides apply)' : '' ?>.</div>
                            <?php elseif ($oauthReady && !$oauthEnabled): ?>
                                <div class="alert alert-warning small py-2">Credentials are saved but Google sign-in is disabled.</div>
                            <?php else: ?>
                                <div class="alert alert-warning small py-2">Google OAuth is not fully configured yet. Add Client ID and Client Secret below (or in server <code>.env</code>).</div>
                            <?php endif; ?>

                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" id="google_oauth_enabled" name="google_oauth_enabled" <?= $oauthEnabled ? 'checked' : '' ?>>
                                <label class="form-check-label" for="google_oauth_enabled">Enable “Continue with Google” on the Student &amp; Parent Portal</label>
                            </div>

                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label" for="google_client_id">Google Client ID</label>
                                    <input type="text" class="form-control" id="google_client_id" name="google_client_id"
                                           value="<?= htmlspecialchars($oauthClientId, ENT_QUOTES, 'UTF-8') ?>"
                                           placeholder="xxxx.apps.googleusercontent.com"
                                           autocomplete="off" <?= $oauthClientIdEnv ? 'readonly' : '' ?>>
                                    <?php if ($oauthClientIdEnv): ?>
                                        <div class="form-text">Locked by environment <code>GOOGLE_CLIENT_ID</code>.</div>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="google_client_secret">Google Client Secret</label>
                                    <input type="password" class="form-control" id="google_client_secret" name="google_client_secret" value=""
                                           placeholder="<?= $oauthSecretSaved ? 'Saved — leave blank to keep' : 'Client secret from Google Cloud Console' ?>"
                                           autocomplete="new-password" <?= $oauthSecretEnv ? 'disabled' : '' ?>>
                                    <?php if ($oauthSecretEnv): ?>
                                        <div class="form-text">Using environment <code>GOOGLE_CLIENT_SECRET</code>.</div>
                                    <?php else: ?>
                                        <div class="form-text">Never shown after save. Leave blank to keep the current secret.</div>
                                    <?php endif; ?>
                                </div>
                                <div class="col-12">
                                    <label class="form-label" for="google_callback_url">Callback URL</label>
                                    <input type="url" class="form-control" id="google_callback_url" name="google_callback_url"
                                           value="<?= htmlspecialchars($oauthCallbackEnv ? '' : $oauthCallback, ENT_QUOTES, 'UTF-8') ?>"
                                           placeholder="<?= htmlspecialchars(rtrim($publicAppUrl, '/') . '/auth/google/callback.php', ENT_QUOTES, 'UTF-8') ?>"
                                           autocomplete="off" <?= $oauthCallbackEnv ? 'disabled' : '' ?>>
                                    <?php if ($oauthCallbackEnv): ?>
                                        <div class="form-text">Using environment <code>GOOGLE_CALLBACK_URL</code>: <code><?= htmlspecialchars($oauthCallback, ENT_QUOTES, 'UTF-8') ?></code></div>
                                    <?php else: ?>
                                        <div class="form-text">Must match the Authorized redirect URI in Google Cloud Console exactly.</div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="settings-note mt-3 mb-0">
                                <strong>Google Cloud Console</strong>
                                <ol class="mb-0 mt-1 ps-3">
                                    <li>Create an OAuth 2.0 Client ID (Web application).</li>
                                    <li>Add authorized redirect URI: <code><?= htmlspecialchars($oauthCallback !== '' ? $oauthCallback : (rtrim($publicAppUrl, '/') . '/auth/google/callback.php'), ENT_QUOTES, 'UTF-8') ?></code></li>
                                    <li>Portal entry: <a href="<?= htmlspecialchars(rtrim($publicAppUrl, '/') . '/portal/login.php', ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener"><?= htmlspecialchars(rtrim($publicAppUrl, '/') . '/portal/login.php', ENT_QUOTES, 'UTF-8') ?></a></li>
                                    <li>Parent link approvals: <a href="<?= $base ?>/admin/parent_requests.php">Parent verification</a></li>
                                </ol>
                            </div>

                            <div class="settings-form-actions">
                                <button class="btn btn-primary" type="submit" name="action" value="save_oauth"><i class="bi bi-save"></i> Save OAuth</button>
                                <button class="btn btn-outline-secondary" type="submit" name="action" value="test_oauth"><i class="bi bi-plug"></i> Check configuration</button>
                                <a class="btn btn-outline-primary" href="<?= htmlspecialchars(rtrim($publicAppUrl, '/') . '/portal/login.php', ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">Open portal</a>
                            </div>
                        </form>
                    </div>
                </article>
            </section>

            <!-- Appearance -->
            <section class="settings-tab<?= $activeTab === 'appearance' ? ' is-active' : '' ?>" data-tab-panel="appearance"<?= $activeTab === 'appearance' ? '' : ' hidden' ?>>
                <div class="settings-tab-head">
                    <h2>Appearance</h2>
                    <p>Theme colours and the public homepage visitors see.</p>
                </div>
                <article class="settings-card">
                    <div class="settings-card-head">
                        <h3><i class="bi bi-palette"></i> Theme</h3>
                    </div>
                    <div class="settings-card-body">
                        <?php if (function_exists('app_theme_render_settings_section')) {
                            app_theme_render_settings_section();
                        } else { ?>
                            <p class="text-muted mb-0">Theme controls are not available on this install.</p>
                        <?php } ?>
                    </div>
                </article>
                <article class="settings-card settings-card-link">
                    <div class="settings-card-head">
                        <h3><i class="bi bi-layout-wtf"></i> Homepage layouts</h3>
                        <p>Pick which of the 10 homepage designs the public site uses.</p>
                    </div>
                    <div class="settings-card-body settings-card-actions">
                        <a class="btn btn-primary" href="<?= $base ?>/admin/homepage-layouts.php">Open homepage layouts</a>
                    </div>
                </article>
            </section>

            <!-- Delete User -->
            <section class="settings-tab<?= $activeTab === 'delete_user' ? ' is-active' : '' ?>" data-tab-panel="delete_user"<?= $activeTab === 'delete_user' ? '' : ' hidden' ?>>
                <div class="settings-tab-head">
                    <h2>Delete User</h2>
                    <p>Find and permanently remove a user account while preserving historical academic, financial, and attendance records.</p>
                </div>

                <!-- Search Card -->
                <article class="settings-card">
                    <div class="settings-card-head">
                        <h3><i class="bi bi-search"></i> Search user account</h3>
                        <p>Search by entering an email address or username.</p>
                    </div>
                    <div class="settings-card-body">
                        <form method="GET" action="<?= $settingsUrl ?>" class="row g-3 align-items-end">
                            <input type="hidden" name="tab" value="delete_user">
                            <div class="col-md-9 col-sm-8">
                                <label for="delete_user_query" class="form-label fw-semibold">Email address or username</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-person-bounding-box"></i></span>
                                    <input type="text" class="form-control" id="delete_user_query" name="q"
                                           value="<?= htmlspecialchars($userDeletionQuery, ENT_QUOTES, 'UTF-8') ?>"
                                           placeholder="e.g. user@edexcel.college or student_username" autocomplete="off">
                                </div>
                                <div class="form-text">Searches students, teachers, administrators, and parent accounts.</div>
                            </div>
                            <div class="col-md-3 col-sm-4 d-flex gap-2">
                                <button type="submit" class="btn btn-primary flex-grow-1">
                                    <i class="bi bi-search"></i> Search
                                </button>
                                <?php if ($userDeletionQuery !== ''): ?>
                                    <a href="<?= $settingsUrl ?>?tab=delete_user" class="btn btn-outline-secondary" title="Clear search">
                                        <i class="bi bi-x-lg"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </article>

                <!-- Multiple Matches Table -->
                <?php if (!empty($deletionSearchResults) && count($deletionSearchResults) > 1 && !$selectedUser): ?>
                <article class="settings-card">
                    <div class="settings-card-head">
                        <h3><i class="bi bi-people"></i> Matching accounts (<?= count($deletionSearchResults) ?> found)</h3>
                        <p>Multiple accounts match your search. Select the specific account you wish to inspect.</p>
                    </div>
                    <div class="settings-card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th class="ps-3">Account Details</th>
                                        <th>Role</th>
                                        <th>Status</th>
                                        <th>Registered</th>
                                        <th class="text-end pe-3">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($deletionSearchResults as $m): ?>
                                    <tr>
                                        <td class="ps-3">
                                            <div class="fw-bold"><?= htmlspecialchars($m['full_name']) ?></div>
                                            <div class="small text-muted">
                                                <?= htmlspecialchars($m['primary_email'] ?: 'No email') ?>
                                                <?php if (!empty($m['username'])): ?> · <span class="badge bg-secondary-subtle text-body">@<?= htmlspecialchars($m['username']) ?></span><?php endif; ?>
                                                <?php if (!empty($m['phone'])): ?> · <span><?= htmlspecialchars($m['phone']) ?></span><?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?= $m['role'] === 'admin' ? 'danger' : ($m['role'] === 'teacher' ? 'info' : ($m['role'] === 'parent' ? 'warning text-dark' : 'primary')) ?>">
                                                <?= htmlspecialchars(ucfirst($m['role'])) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($m['is_deleted']): ?>
                                                <span class="badge bg-secondary">Deleted</span>
                                            <?php elseif ($m['is_active']): ?>
                                                <span class="badge bg-success">Active</span>
                                            <?php else: ?>
                                                <span class="badge bg-warning text-dark">Inactive</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <small class="text-muted"><?= htmlspecialchars(substr((string)$m['created_at'], 0, 10)) ?></small>
                                        </td>
                                        <td class="text-end pe-3">
                                            <a href="<?= $settingsUrl ?>?tab=delete_user&q=<?= urlencode($userDeletionQuery) ?>&user_id=<?= (int)$m['user_id'] ?>&account_type=<?= urlencode($m['account_type']) ?>" class="btn btn-outline-primary btn-sm">
                                                <i class="bi bi-person-gear"></i> Inspect account
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </article>
                <?php elseif ($userDeletionQuery !== '' && empty($deletionSearchResults)): ?>
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle"></i> No accounts found matching &ldquo;<strong><?= htmlspecialchars($userDeletionQuery) ?></strong>&rdquo;. Please check the email address or username and try again.
                </div>
                <?php endif; ?>

                <!-- Student Accounts List & Filter -->
                <?php if (!$selectedUser): ?>
                <article class="settings-card mt-4" data-live-scope>
                    <div class="settings-card-head d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div>
                            <h3><i class="bi bi-people-fill"></i> Student Accounts</h3>
                            <p class="mb-0 text-muted">
                                Showing <strong data-live-count><?= count($tabStudents) ?></strong> of
                                <strong data-live-total><?= count($tabStudents) ?></strong> student<span data-live-plural><?= count($tabStudents) === 1 ? '' : 's' ?></span>
                            </p>
                        </div>
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill">
                                <?= count(array_filter($tabStudents, fn($s) => empty($s['deleted_at']))) ?> Active
                            </span>
                            <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill">
                                <?= count(array_filter($tabStudents, fn($s) => empty($s['deleted_at']) && !empty($s['classes']))) ?> Enrolled
                            </span>
                            <?php $delCount = count(array_filter($tabStudents, fn($s) => !empty($s['deleted_at']))); ?>
                            <?php if ($delCount > 0): ?>
                                <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle rounded-pill">
                                    <?= $delCount ?> Deleted
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="settings-card-body">
                        <!-- Live Search Bar & Filter Chips -->
                        <div class="row g-2 mb-3 align-items-center">
                            <div class="col-lg-6 col-md-5 col-12">
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                                    <input type="search" class="form-control" placeholder="Filter by name, username, phone, or class..." data-live-search autocomplete="off">
                                    <button class="btn btn-outline-secondary" type="button" data-live-clear title="Clear filter">
                                        <i class="bi bi-x-lg"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="col-lg-6 col-md-7 col-12 d-flex align-items-center gap-2 flex-wrap justify-content-md-end">
                                <div class="btn-group btn-group-sm" role="group" aria-label="Status filter">
                                    <button type="button" class="btn btn-outline-secondary active" data-live-chip data-live-key="status" data-live-value="all">All</button>
                                    <button type="button" class="btn btn-outline-secondary" data-live-chip data-live-key="status" data-live-value="active">Active</button>
                                    <button type="button" class="btn btn-outline-secondary" data-live-chip data-live-key="status" data-live-value="deleted">Deleted</button>
                                </div>
                                <div class="btn-group btn-group-sm" role="group" aria-label="Enrollment filter">
                                    <button type="button" class="btn btn-outline-secondary active" data-live-chip data-live-key="enrolled" data-live-value="all">All</button>
                                    <button type="button" class="btn btn-outline-secondary" data-live-chip data-live-key="enrolled" data-live-value="yes">Enrolled</button>
                                    <button type="button" class="btn btn-outline-secondary" data-live-chip data-live-key="enrolled" data-live-value="no">Not Enrolled</button>
                                </div>
                            </div>
                        </div>

                        <!-- Student List Table -->
                        <?php if (empty($tabStudents)): ?>
                            <div class="p-4 text-center text-muted">
                                <i class="bi bi-person-x fs-3 d-block mb-2"></i>
                                <div>No student accounts found in the database.</div>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th class="ps-3">Student</th>
                                            <th>Full Name</th>
                                            <th>Classes</th>
                                            <th>Status</th>
                                            <th>Registered</th>
                                            <th class="text-end pe-3">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($tabStudents as $st): ?>
                                        <?php
                                        $isDel = !empty($st['deleted_at']);
                                        $hasClasses = !empty($st['classes']);
                                        $stName = trim((string)$st['full_name']);
                                        $searchHaystack = strtolower(
                                            $st['username'] . ' ' .
                                            $stName . ' ' .
                                            ($st['primary_email'] ?? '') . ' ' .
                                            ($st['phone'] ?? '') . ' ' .
                                            ($st['classes'] ?? '') . ' ' .
                                            ($isDel ? 'deleted inactive' : 'active') . ' ' .
                                            ($hasClasses ? 'enrolled' : 'not enrolled')
                                        );
                                        ?>
                                        <tr data-live-item
                                            data-status="<?= $isDel ? 'deleted' : 'active' ?>"
                                            data-enrolled="<?= $hasClasses ? 'yes' : 'no' ?>"
                                            data-search="<?= htmlspecialchars($searchHaystack, ENT_QUOTES, 'UTF-8') ?>">
                                            <td class="ps-3">
                                                <div class="fw-bold">
                                                    <?= htmlspecialchars($st['username']) ?>
                                                </div>
                                                <div class="small text-muted">
                                                    <?php if (!empty($st['phone'])): ?>
                                                        <i class="bi bi-telephone text-muted me-1"></i><?= htmlspecialchars($st['phone']) ?>
                                                    <?php elseif (!empty($st['primary_email'])): ?>
                                                        <i class="bi bi-envelope text-muted me-1"></i><?= htmlspecialchars($st['primary_email']) ?>
                                                    <?php else: ?>
                                                        ID #<?= (int)$st['user_id'] ?>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td>
                                                <?= $stName !== '' ? htmlspecialchars($stName) : '<span class="text-muted">—</span>' ?>
                                            </td>
                                            <td>
                                                <?php if ($hasClasses): ?>
                                                    <span class="badge bg-primary-subtle text-primary text-wrap" style="max-width:280px; text-align:left;">
                                                        <?= htmlspecialchars($st['classes']) ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-light text-muted border">Not enrolled</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($isDel): ?>
                                                    <span class="badge bg-secondary">Deleted</span>
                                                <?php elseif (!empty($st['is_active'])): ?>
                                                    <span class="badge bg-success">Active</span>
                                                <?php else: ?>
                                                    <span class="badge bg-warning text-dark">Inactive</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <small class="text-muted"><?= htmlspecialchars(substr((string)$st['created_at'], 0, 10)) ?></small>
                                            </td>
                                            <td class="text-end pe-3 text-nowrap">
                                                <a href="<?= $settingsUrl ?>?tab=delete_user&user_id=<?= (int)$st['user_id'] ?>&account_type=user"
                                                   class="btn <?= $isDel ? 'btn-outline-secondary' : 'btn-outline-danger' ?> btn-sm"
                                                   title="<?= $isDel ? 'Inspect deleted account' : 'Inspect and delete user' ?>">
                                                    <i class="bi <?= $isDel ? 'bi-person-gear' : 'bi-trash3' ?> me-1"></i> <?= $isDel ? 'Inspect' : 'Delete' ?>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <div class="p-4 text-center text-muted d-none" data-live-empty>
                                <i class="bi bi-search fs-3 d-block mb-2 text-secondary"></i>
                                <div>No matching students found</div>
                                <small class="text-muted">Try changing your search query or filter chip.</small>
                            </div>
                        <?php endif; ?>
                    </div>
                </article>
                <?php endif; ?>

                <!-- Selected User Details & Confirmation -->
                <?php if ($selectedUser): ?>
                <article class="settings-card">
                    <div class="settings-card-head d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div>
                            <h3><i class="bi bi-person-badge"></i> Account details</h3>
                            <p>Review the user&rsquo;s current identity and status before proceeding.</p>
                        </div>
                        <a href="<?= $settingsUrl ?>?tab=delete_user" class="btn btn-outline-secondary btn-sm">
                            <i class="bi bi-arrow-left"></i> Back to student list
                        </a>
                    </div>
                    <div class="settings-card-body">
                        <div class="row g-3">
                            <div class="col-md-4 col-sm-6">
                                <label class="form-label text-muted small mb-1">Full name</label>
                                <div class="fw-bold fs-6"><?= htmlspecialchars($selectedUser['full_name']) ?></div>
                            </div>
                            <div class="col-md-4 col-sm-6">
                                <label class="form-label text-muted small mb-1">Email address</label>
                                <div class="fw-bold"><?= htmlspecialchars($selectedUser['primary_email'] ?: 'None') ?></div>
                            </div>
                            <div class="col-md-4 col-sm-6">
                                <label class="form-label text-muted small mb-1">Username</label>
                                <div><?= !empty($selectedUser['username']) ? '<code>@' . htmlspecialchars($selectedUser['username']) . '</code>' : '<span class="text-muted">Not applicable</span>' ?></div>
                            </div>
                            <div class="col-md-3 col-sm-6">
                                <label class="form-label text-muted small mb-1">Role</label>
                                <div>
                                    <span class="badge bg-<?= $selectedUser['role'] === 'admin' ? 'danger' : ($selectedUser['role'] === 'teacher' ? 'info' : ($selectedUser['role'] === 'parent' ? 'warning text-dark' : 'primary')) ?> px-2 py-1">
                                        <?= htmlspecialchars(ucfirst($selectedUser['role'])) ?>
                                    </span>
                                </div>
                            </div>
                            <div class="col-md-3 col-sm-6">
                                <label class="form-label text-muted small mb-1">Account status</label>
                                <div>
                                    <?php if ($selectedUser['is_deleted']): ?>
                                        <span class="badge bg-secondary">Deleted / Inactive</span>
                                    <?php elseif ($selectedUser['is_active']): ?>
                                        <span class="badge bg-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning text-dark">Inactive</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="col-md-3 col-sm-6">
                                <label class="form-label text-muted small mb-1">Registration date</label>
                                <div class="text-body"><?= htmlspecialchars($selectedUser['created_at'] ? date('M j, Y H:i', strtotime($selectedUser['created_at'])) : 'Unknown') ?></div>
                            </div>
                            <div class="col-md-3 col-sm-6">
                                <label class="form-label text-muted small mb-1">Account ID</label>
                                <div><code>#<?= (int)$selectedUser['user_id'] ?> (<?= htmlspecialchars($selectedUser['account_type']) ?>)</code></div>
                            </div>
                        </div>
                    </div>
                </article>

                <!-- Impact & Data Retention Policy -->
                <article class="settings-card">
                    <div class="settings-card-head">
                        <h3><i class="bi bi-shield-check"></i> Impact summary &amp; data retention policy</h3>
                        <p>Institutional integrity and regulatory compliance: academic and accounting records are safely preserved.</p>
                    </div>
                    <div class="settings-card-body">
                        <div class="row g-4">
                            <div class="col-md-6">
                                <h4 class="h6 text-success fw-bold d-flex align-items-center gap-2 mb-2">
                                    <i class="bi bi-check-circle-fill"></i> Records retained for compliance
                                </h4>
                                <p class="small text-muted mb-2">These records are preserved in audit logs and archives:</p>
                                <ul class="list-group list-group-flush small">
                                <?php if (!empty($selectedUserImpact['retained_records'])): ?>
                                    <?php foreach ($selectedUserImpact['retained_records'] as $rec): ?>
                                        <li class="list-group-item px-0 py-2 d-flex justify-content-between align-items-center">
                                            <div>
                                                <div class="fw-semibold"><?= htmlspecialchars($rec['category']) ?></div>
                                                <div class="text-muted" style="font-size:0.78rem;"><?= htmlspecialchars($rec['description']) ?></div>
                                            </div>
                                            <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill">
                                                <?= (int)$rec['count'] ?> record<?= (int)$rec['count'] === 1 ? '' : 's' ?>
                                            </span>
                                        </li>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <li class="list-group-item px-0 py-2 text-muted">No associated academic or financial history found.</li>
                                <?php endif; ?>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <h4 class="h6 text-danger fw-bold d-flex align-items-center gap-2 mb-2">
                                    <i class="bi bi-x-octagon-fill"></i> Access &amp; credentials to be purged
                                </h4>
                                <p class="small text-muted mb-2">All login methods and active authorizations will be permanently erased:</p>
                                <ul class="list-group list-group-flush small">
                                <?php if (!empty($selectedUserImpact['revoked_items'])): ?>
                                    <?php foreach ($selectedUserImpact['revoked_items'] as $rev): ?>
                                        <li class="list-group-item px-0 py-2">
                                            <div class="fw-semibold text-danger"><?= htmlspecialchars($rev['category']) ?></div>
                                            <div class="text-muted" style="font-size:0.78rem;"><?= htmlspecialchars($rev['description']) ?></div>
                                        </li>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                                </ul>
                            </div>
                        </div>
                    </div>
                </article>

                <!-- Confirmation Box -->
                <?php if (!$selectedUserCanDelete['allowed']): ?>
                    <div class="alert alert-danger d-flex align-items-center gap-3">
                        <i class="bi bi-shield-slash-fill fs-3"></i>
                        <div>
                            <h5 class="alert-heading mb-1">Cannot Delete This Account</h5>
                            <p class="mb-0"><?= htmlspecialchars($selectedUserCanDelete['reason']) ?></p>
                        </div>
                    </div>
                <?php else: ?>
                    <article class="settings-card border-danger shadow-sm">
                        <div class="settings-card-head bg-danger-subtle text-danger-emphasis d-flex align-items-center justify-content-between flex-wrap gap-2">
                            <div>
                                <h3 class="text-danger mb-1"><i class="bi bi-exclamation-triangle-fill"></i> Permanently delete account confirmation</h3>
                                <p class="text-danger-emphasis mb-0">This operation cannot be undone. Please complete both verification steps below.</p>
                            </div>
                            <span class="badge bg-danger text-white px-2 py-1"><i class="bi bi-shield-lock-fill me-1"></i> Irreversible Action</span>
                        </div>
                        <div class="settings-card-body pt-3">
                            <form method="POST" id="deleteUserForm" action="<?= $settingsUrl ?>?tab=delete_user" novalidate>
                                <?= csrf_field() ?>
                                <input type="hidden" name="settings_panel" value="delete_user">
                                <input type="hidden" name="action" value="delete_user_confirm">
                                <input type="hidden" name="target_user_id" value="<?= (int)$selectedUser['user_id'] ?>">
                                <input type="hidden" name="account_type" value="<?= htmlspecialchars($selectedUser['account_type']) ?>">

                                <?php
                                $expectedMatches = array_values(array_filter([
                                    !empty($selectedUser['username']) ? (string)$selectedUser['username'] : null,
                                    !empty($selectedUser['primary_email']) ? (string)$selectedUser['primary_email'] : null,
                                ]));
                                if (empty($expectedMatches)) {
                                    $expectedMatches = [(string)$selectedUser['user_id']];
                                }
                                ?>

                                <!-- Step 1 Container -->
                                <div class="p-3 mb-3 rounded-3 border" id="step1Container" style="background-color: #fff8f8; border-color: #f5c2c7 !important;">
                                    <div class="d-flex align-items-start gap-3">
                                        <div class="pt-1">
                                            <input class="form-check-input" type="checkbox" id="confirmDeletionCheck" name="confirm_checkbox" value="1" style="width: 1.35rem; height: 1.35rem; cursor: pointer;">
                                        </div>
                                        <div class="flex-grow-1">
                                            <label class="form-check-label fw-bold text-danger user-select-none mb-1 d-block" for="confirmDeletionCheck" style="cursor: pointer;">
                                                <span class="badge bg-danger me-1">Step 1</span>
                                                I confirm that I want to permanently delete this user account and revoke all login privileges.
                                            </label>
                                            <div class="small text-muted">
                                                All active sessions, passwords, and access credentials will be immediately terminated.
                                            </div>
                                            <div id="step1Feedback" class="small text-danger fw-bold mt-2" style="display: none;">
                                                <i class="bi bi-exclamation-octagon-fill me-1"></i> Checkbox must be checked to enable deletion.
                                            </div>
                                        </div>
                                        <div id="step1Indicator" class="flex-shrink-0">
                                            <span class="badge bg-secondary-subtle text-secondary border">Step 1 Pending</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Step 2 Container -->
                                <div class="p-3 mb-3 rounded-3 border" id="step2Container" style="background-color: #fdfdfd; border-color: #dee2e6 !important;">
                                    <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                                        <label for="confirmIdentifierInput" class="form-label fw-bold mb-0">
                                            <span class="badge bg-danger me-1">Step 2</span>
                                            Confirm username or email address:
                                        </label>
                                        <div class="d-flex align-items-center gap-1 flex-wrap">
                                            <span class="small text-muted me-1">Click to auto-fill:</span>
                                            <?php foreach ($expectedMatches as $m): ?>
                                                <button type="button" class="btn btn-sm btn-outline-danger py-0 px-2 fill-identifier-btn" data-value="<?= htmlspecialchars($m) ?>" title="Click to fill automatically">
                                                    <i class="bi bi-arrow-down-short"></i> <code><?= htmlspecialchars($m) ?></code>
                                                </button>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>

                                    <div class="input-group">
                                        <span class="input-group-text bg-light"><i class="bi bi-shield-check"></i></span>
                                        <input type="text" class="form-control" id="confirmIdentifierInput" name="confirm_identifier"
                                               placeholder="Type or click username/email above" autocomplete="off" required>
                                        <button class="btn btn-outline-secondary" type="button" id="clearIdentifierBtn" title="Clear input">
                                            <i class="bi bi-x-lg"></i>
                                        </button>
                                    </div>

                                    <div class="d-flex justify-content-between align-items-center mt-2 flex-wrap gap-2">
                                        <div class="small text-muted" id="confirmIdentifierHelp">
                                            Must match exactly: <?php foreach ($expectedMatches as $i => $m): ?><?= $i > 0 ? ' or ' : '' ?><code><?= htmlspecialchars($m) ?></code><?php endforeach; ?>
                                        </div>
                                        <div id="step2Indicator">
                                            <span class="badge bg-secondary-subtle text-secondary border">Step 2 Pending</span>
                                        </div>
                                    </div>
                                    <div id="step2Feedback" class="small text-danger fw-bold mt-2" style="display: none;">
                                        <i class="bi bi-exclamation-octagon-fill me-1"></i> The entered identifier does not match. Click one of the badges above to auto-fill.
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="deletionReasonInput" class="form-label fw-semibold">Reason for deletion (optional)</label>
                                    <textarea class="form-control" id="deletionReasonInput" name="deletion_reason" rows="2"
                                              placeholder="e.g. Student requested account closure, left institution, duplicate account..."></textarea>
                                    <div class="form-text">Will be recorded in the system audit logs for administrative transparency.</div>
                                </div>

                                <!-- Status Summary Bar -->
                                <div id="deletionRequirementAlert" class="alert alert-warning py-2 px-3 small d-flex align-items-center gap-2 mb-3">
                                    <i class="bi bi-exclamation-circle-fill text-warning fs-5 flex-shrink-0" id="deletionAlertIcon"></i>
                                    <div id="deletionAlertText" class="flex-grow-1">
                                        Please complete <strong>Step 1</strong> (check the box) and <strong>Step 2</strong> (enter username or email) before deleting.
                                    </div>
                                </div>

                                <div class="d-flex align-items-center gap-2">
                                    <button type="submit" id="btnPermanentlyDeleteUser" class="btn btn-secondary px-4 py-2 fw-semibold">
                                        <i class="bi bi-trash3-fill me-1"></i> Permanently Delete User
                                    </button>
                                    <a href="<?= $settingsUrl ?>?tab=delete_user" class="btn btn-outline-secondary">
                                        Cancel
                                    </a>
                                </div>
                            </form>
                        </div>
                    </article>
                <?php endif; ?>
                <?php endif; ?>
            </section>

            <section class="settings-tab<?= $activeTab === 'site_visitors' ? ' is-active' : '' ?>" data-tab-panel="site_visitors"<?= $activeTab === 'site_visitors' ? '' : ' hidden' ?>>
                <?php include __DIR__ . '/views/site_visitors_tab.php'; ?>
            </section>

        </div>
    </div>
</div>

<style>
.settings-admin{max-width:1180px;margin:0 auto}
.settings-hero{margin-bottom:1rem}
.settings-hero-main{display:flex;justify-content:space-between;align-items:flex-start;gap:1rem;flex-wrap:wrap}
.settings-hero h1{margin:0;font-size:clamp(1.35rem,2.5vw,1.85rem);font-weight:800;letter-spacing:-.03em;display:flex;align-items:center;gap:.45rem}
.settings-hero-sub{margin:.4rem 0 0;max-width:42rem;color:var(--muted,#6c757d);font-size:.88rem;line-height:1.45}
.settings-hero-actions{display:flex;flex-wrap:wrap;gap:.4rem}
.settings-shell{display:grid;grid-template-columns:240px minmax(0,1fr);gap:1.25rem;align-items:start}
.settings-nav{position:sticky;top:74px;display:flex;flex-direction:column;gap:.35rem;padding:.75rem;border:1px solid var(--panel-border,rgba(0,0,0,.08));border-radius:16px;background:var(--surface,#fff);box-shadow:var(--shadow-sm,0 1px 2px rgba(0,0,0,.04))}
.settings-nav-label{margin:0 .35rem .35rem;font-size:.68rem;font-weight:800;letter-spacing:.06em;text-transform:uppercase;color:var(--muted,#6c757d)}
.settings-nav-link{display:flex;align-items:flex-start;gap:.65rem;width:100%;text-align:left;border:1px solid transparent;border-radius:12px;background:transparent;color:var(--text,inherit);padding:.65rem .7rem;cursor:pointer;transition:background .15s,border-color .15s}
.settings-nav-link i{margin-top:.15rem;font-size:1.05rem;opacity:.9}
.settings-nav-text{display:flex;flex-direction:column;gap:.1rem;min-width:0}
.settings-nav-title{font-size:.9rem;font-weight:800}
.settings-nav-hint{font-size:.72rem;color:var(--muted,#6c757d);line-height:1.3}
.settings-nav-link:hover{background:var(--surface-soft,rgba(0,0,0,.04))}
.settings-nav-link.is-active{background:var(--primary-soft,rgba(81,97,206,.12));border-color:rgba(81,97,206,.22);color:var(--primary,#5161ce)}
.settings-nav-link.is-active .settings-nav-hint{color:inherit;opacity:.8}
.settings-content{min-width:0}
.settings-tab-head{margin-bottom:1rem}
.settings-tab-head h2{margin:0;font-size:1.15rem;font-weight:800;letter-spacing:-.02em}
.settings-tab-head p{margin:.25rem 0 0;color:var(--muted,#6c757d);font-size:.86rem}
.settings-card{margin-bottom:1rem;border:1px solid var(--panel-border,rgba(0,0,0,.08));border-radius:16px;background:var(--surface,#fff);box-shadow:var(--shadow-sm,0 1px 2px rgba(0,0,0,.04));overflow:hidden}
.settings-card.is-focus{border-color:rgba(81,97,206,.35);box-shadow:0 0 0 3px rgba(81,97,206,.12)}
.settings-card-advanced{border-style:dashed}
.settings-card-head{padding:1rem 1.15rem .35rem}
.settings-card-head h3{margin:0;font-size:.98rem;font-weight:800;display:flex;align-items:center;gap:.4rem}
.settings-card-head p{margin:.3rem 0 0;font-size:.8rem;color:var(--muted,#6c757d)}
.settings-card-body{padding:.65rem 1.15rem 1.15rem}
.settings-note{color:var(--muted,#6c757d);font-size:.82rem}
.settings-choice-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.75rem}
.settings-choice{display:flex;flex-direction:column;gap:.25rem;padding:.9rem 1rem;border:1px solid var(--panel-border,rgba(0,0,0,.1));border-radius:12px;cursor:pointer;background:var(--surface-soft,rgba(0,0,0,.02))}
.settings-choice.is-selected,.settings-choice:has(input:checked){border-color:rgba(81,97,206,.4);background:var(--primary-soft,rgba(81,97,206,.1))}
.settings-choice-title{font-weight:800;font-size:.92rem;display:flex;align-items:center;gap:.35rem}
.settings-choice-desc{font-size:.78rem;color:var(--muted,#6c757d)}
.settings-check-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.55rem .9rem}
.settings-form-actions{display:flex;flex-wrap:wrap;gap:.5rem;margin-top:1rem;padding-top:.25rem}
.settings-card-actions{display:flex;align-items:center}
.settings-save-bar{position:sticky;bottom:12px;z-index:4;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:.75rem;padding:12px 14px;margin-top:.5rem;border:1px solid var(--panel-border,rgba(0,0,0,.08));border-radius:14px;background:var(--surface,#fff);box-shadow:var(--shadow-md,0 8px 24px rgba(0,0,0,.08))}
.settings-save-hint{font-size:.78rem;color:var(--muted,#6c757d)}
.sticky-actions{position:sticky;bottom:12px;z-index:3;padding:12px 14px;border:1px solid var(--panel-border,rgba(0,0,0,.08));border-radius:14px;background:var(--surface,#fff);box-shadow:var(--shadow-md,0 8px 24px rgba(0,0,0,.08))}
@media(max-width:991px){
  .settings-shell{grid-template-columns:1fr}
  .settings-nav{position:static;flex-direction:row;flex-wrap:nowrap;overflow-x:auto;gap:.4rem;-webkit-overflow-scrolling:touch}
  .settings-nav-label{display:none}
  .settings-nav-link{flex:0 0 auto;max-width:11.5rem}
  .settings-nav-hint{display:none}
}
@media(max-width:576px){
  .settings-choice-grid,.settings-check-grid{grid-template-columns:1fr}
  .settings-save-bar{flex-direction:column;align-items:stretch}
  .settings-save-bar .btn{width:100%}
}
.fill-identifier-btn{transition:all .15s ease;cursor:pointer}
.fill-identifier-btn:hover{transform:translateY(-1px);box-shadow:0 2px 4px rgba(220,53,69,.2)}
.transition-all{transition:all .2s ease}
@keyframes pulseDanger{0%,100%{box-shadow:0 0 0 0 rgba(220,53,69,0)}50%{box-shadow:0 0 0 4px rgba(220,53,69,.25)}}
.pulse-danger{animation:pulseDanger .8s ease-in-out 2}
</style>

<script>
(function () {
    var panelInput = document.getElementById('settingsPanel');
    var tabToPanel = {
        college: 'general',
        messaging: 'whatsapp',
        otp: 'otp',
        oauth: 'oauth',
        payments: 'onepay',
        classroom: 'classroom',
        recordings: 'bunny',
        handwriting: 'handwriting',
        appearance: 'appearance',
        delete_user: 'delete_user',
        site_visitors: 'site_visitors'
    };
    var saveBar = document.querySelector('.settings-save-bar');
    var sharedTabs = { college: 1, messaging: 1, otp: 1 };

    function activateTab(tabId, pushUrl) {
        document.querySelectorAll('.settings-nav-link').forEach(function (btn) {
            var on = btn.getAttribute('data-settings-tab') === tabId;
            btn.classList.toggle('is-active', on);
            btn.setAttribute('aria-pressed', on ? 'true' : 'false');
        });
        document.querySelectorAll('[data-tab-panel]').forEach(function (panel) {
            var on = panel.getAttribute('data-tab-panel') === tabId;
            panel.classList.toggle('is-active', on);
            if (on) {
                panel.removeAttribute('hidden');
            } else {
                panel.setAttribute('hidden', '');
            }
        });
        if (saveBar) {
            if (sharedTabs[tabId]) {
                saveBar.removeAttribute('hidden');
            } else {
                saveBar.setAttribute('hidden', '');
            }
        }
        if (panelInput && tabToPanel[tabId]) {
            panelInput.value = tabToPanel[tabId];
        }
        if (pushUrl && window.history && window.history.replaceState) {
            var url = new URL(window.location.href);
            url.searchParams.set('tab', tabToPanel[tabId] || tabId);
            window.history.replaceState({}, '', url.toString());
        }
    }

    document.querySelectorAll('.settings-nav-link').forEach(function (btn) {
        btn.addEventListener('click', function () {
            activateTab(btn.getAttribute('data-settings-tab') || 'college', true);
        });
    });

    document.querySelectorAll('[data-settings-panel-set]').forEach(function (el) {
        el.addEventListener('click', function () {
            if (panelInput) panelInput.value = el.getAttribute('data-settings-panel-set') || panelInput.value;
        });
    });

    // Keep radio choice cards visually selected
    document.querySelectorAll('.settings-choice input[type="radio"]').forEach(function (radio) {
        radio.addEventListener('change', function () {
            document.querySelectorAll('input[name="' + radio.name + '"]').forEach(function (r) {
                var label = r.closest('.settings-choice');
                if (label) label.classList.toggle('is-selected', r.checked);
            });
        });
    });

    // Focus deep-linked cards (meta / evolution / bank / onepay)
    var focusId = {
        meta: 'card-meta',
        evolution: 'card-evolution',
        whatsapp: 'card-whatsapp',
        onepay: 'card-onepay',
        bank: 'card-bank',
        'teacher-manual': 'card-teacher-manual'
    }[<?= json_encode($openPanel) ?>];
    if (focusId) {
        var el = document.getElementById(focusId);
        if (el) {
            setTimeout(function () { el.scrollIntoView({ behavior: 'smooth', block: 'start' }); }, 120);
        }
    }

    // Live validation & interactive feedback for Delete User confirmation
    var confirmCheck = document.getElementById('confirmDeletionCheck');
    var confirmInput = document.getElementById('confirmIdentifierInput');
    var clearInputBtn = document.getElementById('clearIdentifierBtn');
    var deleteBtn = document.getElementById('btnPermanentlyDeleteUser');
    var deleteForm = document.getElementById('deleteUserForm');
    var step1Container = document.getElementById('step1Container');
    var step2Container = document.getElementById('step2Container');
    var step1Feedback = document.getElementById('step1Feedback');
    var step2Feedback = document.getElementById('step2Feedback');
    var step1Indicator = document.getElementById('step1Indicator');
    var step2Indicator = document.getElementById('step2Indicator');
    var reqAlert = document.getElementById('deletionRequirementAlert');
    var reqIcon = document.getElementById('deletionAlertIcon');
    var reqText = document.getElementById('deletionAlertText');
    var quickFillBtns = document.querySelectorAll('.fill-identifier-btn');

    if (confirmCheck && confirmInput && deleteBtn && deleteForm) {
        var validTargets = <?= json_encode(array_values(array_map('strtolower', $expectedMatches ?? []))) ?>;
        var targetUserName = <?= json_encode($selectedUser['full_name'] ?? '') ?>;
        var targetUserIdentifier = <?= json_encode($expectedMatches[0] ?? '') ?>;

        function checkDeletionFormValid() {
            var typed = (confirmInput.value || '').trim().toLowerCase();
            var isChecked = confirmCheck.checked;
            var isMatched = typed !== '' && validTargets.indexOf(typed) !== -1;

            // Update Step 1 visual state
            if (isChecked) {
                if (step1Indicator) step1Indicator.innerHTML = '<span class="badge bg-success"><i class="bi bi-check-circle-fill me-1"></i>Step 1 Done</span>';
                if (step1Container) step1Container.style.borderColor = '#198754';
                if (step1Feedback) step1Feedback.style.display = 'none';
            } else {
                if (step1Indicator) step1Indicator.innerHTML = '<span class="badge bg-secondary-subtle text-secondary border">Step 1 Pending</span>';
                if (step1Container) step1Container.style.borderColor = '#f5c2c7';
            }

            // Update Step 2 visual state
            if (isMatched) {
                if (step2Indicator) step2Indicator.innerHTML = '<span class="badge bg-success"><i class="bi bi-check-circle-fill me-1"></i>Step 2 Matched</span>';
                if (step2Container) step2Container.style.borderColor = '#198754';
                confirmInput.classList.remove('is-invalid');
                confirmInput.classList.add('is-valid');
                if (step2Feedback) step2Feedback.style.display = 'none';
            } else {
                if (step2Indicator) step2Indicator.innerHTML = '<span class="badge bg-secondary-subtle text-secondary border">Step 2 Pending</span>';
                if (step2Container) step2Container.style.borderColor = '#dee2e6';
                confirmInput.classList.remove('is-valid');
                if (typed !== '') {
                    confirmInput.classList.add('is-invalid');
                    if (step2Feedback) step2Feedback.style.display = 'block';
                } else {
                    confirmInput.classList.remove('is-invalid');
                    if (step2Feedback) step2Feedback.style.display = 'none';
                }
            }

            // Update Overall Alert & Delete Button
            if (isChecked && isMatched) {
                if (reqAlert) reqAlert.className = 'alert alert-success py-2 px-3 small d-flex align-items-center gap-2 mb-3';
                if (reqIcon) reqIcon.className = 'bi bi-check-circle-fill text-success fs-5 flex-shrink-0';
                if (reqText) reqText.innerHTML = '<strong>All verification steps passed!</strong> Click &ldquo;Permanently Delete User&rdquo; below to execute deletion.';

                deleteBtn.className = 'btn btn-danger px-4 py-2 fw-semibold shadow-sm';
                deleteBtn.style.cursor = 'pointer';
                deleteBtn.title = 'Click to permanently delete this user account';
            } else {
                if (reqAlert) reqAlert.className = 'alert alert-warning py-2 px-3 small d-flex align-items-center gap-2 mb-3';
                if (reqIcon) reqIcon.className = 'bi bi-exclamation-circle-fill text-warning fs-5 flex-shrink-0';

                if (reqText) {
                    if (!isChecked && !isMatched) {
                        reqText.innerHTML = 'Please complete <strong>Step 1</strong> (tick the checkbox) and <strong>Step 2</strong> (enter or click username/email).';
                    } else if (!isChecked) {
                        reqText.innerHTML = '<strong>Almost ready:</strong> Please tick the <strong>Step 1</strong> confirmation checkbox above.';
                    } else {
                        reqText.innerHTML = '<strong>Almost ready:</strong> Please complete <strong>Step 2</strong> by typing or clicking the username/email.';
                    }
                }

                deleteBtn.className = 'btn btn-secondary px-4 py-2 fw-semibold opacity-75';
                deleteBtn.style.cursor = 'pointer';
                deleteBtn.title = 'Complete Step 1 and Step 2 to enable deletion';
            }

            return isChecked && isMatched;
        }

        // Quick fill buttons
        quickFillBtns.forEach(function (btn) {
            btn.addEventListener('click', function () {
                confirmInput.value = btn.getAttribute('data-value') || '';
                checkDeletionFormValid();
                confirmInput.focus();
            });
        });

        // Clear input button
        if (clearInputBtn) {
            clearInputBtn.addEventListener('click', function () {
                confirmInput.value = '';
                checkDeletionFormValid();
                confirmInput.focus();
            });
        }

        confirmCheck.addEventListener('change', checkDeletionFormValid);
        confirmInput.addEventListener('input', checkDeletionFormValid);
        confirmInput.addEventListener('paste', function () {
            setTimeout(checkDeletionFormValid, 50);
        });

        // Intercept button click: if not valid, explain WHY instead of doing nothing!
        deleteBtn.addEventListener('click', function (e) {
            var isChecked = confirmCheck.checked;
            var typed = (confirmInput.value || '').trim().toLowerCase();
            var isMatched = typed !== '' && validTargets.indexOf(typed) !== -1;

            if (!isChecked) {
                e.preventDefault();
                if (step1Feedback) step1Feedback.style.display = 'block';
                if (step1Container) {
                    step1Container.classList.add('pulse-danger');
                    setTimeout(function () { step1Container.classList.remove('pulse-danger'); }, 1600);
                    step1Container.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
                confirmCheck.focus();
                return;
            }

            if (!isMatched) {
                e.preventDefault();
                if (step2Feedback) step2Feedback.style.display = 'block';
                confirmInput.classList.add('is-invalid');
                if (step2Container) {
                    step2Container.classList.add('pulse-danger');
                    setTimeout(function () { step2Container.classList.remove('pulse-danger'); }, 1600);
                    step2Container.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
                confirmInput.focus();
                return;
            }

            // Both valid: ask for final browser confirmation
            var confirmMsg = "Are you sure you want to permanently delete this user account?\n\n" +
                             "User: " + (targetUserName || targetUserIdentifier) + "\n\n" +
                             "This action is permanent and CANNOT be undone.";
            if (!window.confirm(confirmMsg)) {
                e.preventDefault();
                return;
            }

            // Confirmed: show loading spinner and submit
            deleteBtn.disabled = true;
            deleteBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span> Permanently Deleting...';
            deleteForm.submit();
        });

        // Run validation immediately on page load
        checkDeletionFormValid();
    }
})();
</script>

<script>
// ─── iPromo SMS Settings Panel ────────────────────────────────────────────────
(function () {
    'use strict';

    const csrf  = document.querySelector('meta[name="csrf-token"]')?.content
               || document.querySelector('[name="csrf_token"]')?.value
               || '';

    // Helper: get CSRF from any hidden field on page
    function getCsrf() {
        const el = document.querySelector('input[name="csrf_token"]');
        return el ? el.value : '';
    }

    function showIpromoResult(ok, msg, container) {
        if (!container) return;
        container.hidden = false;
        container.className = 'mt-3 alert ' + (ok ? 'alert-success' : 'alert-danger');
        container.textContent = msg;
    }

    function ipromoFormData(action) {
        const fd = new FormData();
        fd.append('action',          action);
        fd.append('csrf_token',      getCsrf());
        fd.append('ipromo_enabled',  document.getElementById('ipromoEnabledToggle')?.checked ? '1' : '0');
        fd.append('ipromo_api_url',  document.getElementById('ipromoApiUrl')?.value || '');
        fd.append('ipromo_username', document.getElementById('ipromoUsername')?.value || '');
        fd.append('ipromo_api_key',  document.getElementById('ipromoApiKey')?.value || '');
        fd.append('ipromo_sender_id',document.getElementById('ipromoSenderId')?.value || '');
        fd.append('test_phone',      document.getElementById('ipromoTestPhone')?.value || '');
        fd.append('test_message',    document.getElementById('ipromoTestMsg')?.value || '');
        // Active provider
        const provSel = document.querySelector('input[name="sms_provider_sel"]:checked');
        fd.append('sms_provider', provSel ? provSel.value : 'sms-gate');
        return fd;
    }

    function doIpromoRequest(action, btn) {
        const resultEl = document.getElementById('ipromoResult');
        if (resultEl) { resultEl.hidden = true; }
        if (btn) { btn.disabled = true; }

        fetch('<?= $base ?>/ajax/save_ipromo_settings.php', {
            method: 'POST',
            body: ipromoFormData(action),
            credentials: 'same-origin',
        })
        .then(r => r.json())
        .then(data => {
            if (btn) { btn.disabled = false; }
            const ok  = !!data.ok;
            const msg = data.message || data.error || (ok ? 'Done.' : 'Request failed.');
            showIpromoResult(ok, msg, resultEl);
            // Update badge
            const badge = document.getElementById('ipromoStatusBadge');
            if (badge && data.ok) {
                const enabled = document.getElementById('ipromoEnabledToggle')?.checked;
                badge.textContent  = enabled ? 'Enabled' : 'Disabled';
                badge.className    = 'badge ' + (enabled ? 'bg-success' : 'bg-secondary');
            }
            // Clear API key field after successful save
            if (ok && action !== 'test_ipromo') {
                const keyEl = document.getElementById('ipromoApiKey');
                if (keyEl) { keyEl.value = ''; keyEl.placeholder = 'Saved — leave blank to keep'; }
            }
        })
        .catch(err => {
            if (btn) { btn.disabled = false; }
            showIpromoResult(false, 'Network error: ' + err.message, resultEl);
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        const saveBtn = document.getElementById('ipromoSaveBtn');
        const testBtn = document.getElementById('ipromoTestBtn');

        if (saveBtn) {
            saveBtn.addEventListener('click', function () {
                doIpromoRequest('save_ipromo', saveBtn);
            });
        }
        if (testBtn) {
            testBtn.addEventListener('click', function () {
                doIpromoRequest('test_ipromo', testBtn);
            });
        }

        // Toggle badge update on checkbox change (immediate visual feedback)
        const toggle = document.getElementById('ipromoEnabledToggle');
        if (toggle) {
            toggle.addEventListener('change', function () {
                const badge = document.getElementById('ipromoStatusBadge');
                if (badge) {
                    badge.textContent = toggle.checked ? 'Enabled' : 'Disabled';
                    badge.className   = 'badge ' + (toggle.checked ? 'bg-success' : 'bg-secondary');
                }
            });
        }

        // Provider radio — update settings-choice selected class
        document.querySelectorAll('input[name="sms_provider_sel"]').forEach(function (radio) {
            radio.addEventListener('change', function () {
                document.querySelectorAll('input[name="sms_provider_sel"]').forEach(function (r) {
                    r.closest('label')?.classList.remove('is-selected');
                });
                radio.closest('label')?.classList.add('is-selected');
            });
        });
    });
}());
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
