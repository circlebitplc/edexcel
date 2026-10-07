<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/sms_gateway.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../includes/helpers.php';

use Edexcel\Services\GoogleOAuthService;
use Edexcel\Services\TeacherMigrationService;

require_admin();
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');

$migration = new TeacherMigrationService($pdo);
$adminUserId = (int)($_SESSION['user_id'] ?? 0);
$googleReady = GoogleOAuthService::isPortalEnabled($pdo);
$smsGatewayStatus = $migration->getSmsGatewayStatus();

$message = '';
$error = trim((string)($_GET['error'] ?? ''));
if (isset($_GET['notice'])) {
    if ($_GET['notice'] === 'linked') {
        $message = 'Staff account successfully linked to Google!';
    } elseif ($_GET['notice'] === 'admin_linked') {
        $message = 'Administrator account successfully linked to Google! You can now use "Continue with Google" to sign in.';
    }
}
$generatedInvite = null;

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');
    $csrf = (string)($_POST['csrf_token'] ?? '');

    // AJAX Endpoint for fast single-use token and SMS text generation
    if ($action === 'ajax_create_invite') {
        header('Content-Type: application/json; charset=utf-8');
        if (!verify_csrf_token($csrf)) {
            echo json_encode(['ok' => false, 'message' => 'Your session has expired. Please refresh the page and try again.']);
            exit;
        }

        $targetId = (int)($_POST['user_id'] ?? 0);
        $expectedEmail = trim((string)($_POST['expected_email'] ?? ''));
        $ttlDays = (int)($_POST['ttl_days'] ?? 7);

        $res = $migration->createInvite($targetId, $expectedEmail !== '' ? $expectedEmail : null, $adminUserId, $ttlDays);
        if (!$res['ok']) {
            echo json_encode(['ok' => false, 'message' => $res['message'] ?? 'Could not generate invitation link.']);
            exit;
        }

        $tch = $migration->getTeacherForInvite($targetId);
        $teacherName = trim((string)($tch['teacher_name'] ?? '')) ?: 'Teacher';
        $teacherPhone = trim((string)($tch['teacher_phone'] ?? ''));
        $inviteUrl = (string)($res['invite_url'] ?? '');
        $directOAuthUrl = '';

        $cleanPhone = preg_replace('/[^0-9]/', '', $teacherPhone);
        if (str_starts_with($cleanPhone, '0') && strlen($cleanPhone) === 10) {
            $cleanPhone = '94' . substr($cleanPhone, 1);
        } elseif (strlen($cleanPhone) === 9 && (str_starts_with($cleanPhone, '7') || str_starts_with($cleanPhone, '1') || str_starts_with($cleanPhone, '2'))) {
            $cleanPhone = '94' . $cleanPhone;
        }

        $smsTextYour = "Edexcel College: Dear {$teacherName}, please click this link to link your Google account: {$inviteUrl} . You will be asked to sign in with your Google account.";
        $smsTextTheir = "Edexcel College: Dear {$teacherName}, please click this link to link your Google account: {$inviteUrl} . You will be asked to sign in with their Google account.";
        $smsTextDirect = $smsTextYour;

        $whatsappUrl = $cleanPhone !== ''
            ? 'https://wa.me/' . $cleanPhone . '?text=' . rawurlencode($smsTextYour)
            : '';
        $smsUrl = $teacherPhone !== ''
            ? 'sms:' . rawurlencode($teacherPhone) . '?body=' . rawurlencode($smsTextYour)
            : '';

        echo json_encode([
            'ok' => true,
            'message' => $res['message'],
            'user_id' => $targetId,
            'invite_url' => $inviteUrl,
            'direct_oauth_url' => $directOAuthUrl,
            'expires_at' => $res['expires_at'] ?? '',
            'teacher_name' => $teacherName,
            'teacher_phone' => $teacherPhone,
            'clean_phone' => $cleanPhone,
            'sms_text_your' => $smsTextYour,
            'sms_text_their' => $smsTextTheir,
            'sms_text_direct' => $smsTextDirect,
            'whatsapp_url' => $whatsappUrl,
            'sms_url' => $smsUrl,
        ]);
        exit;
    }

    // AJAX Endpoint for dispatching Google Linking SMS via College SMS Gateway
    if ($action === 'ajax_send_sms_gateway') {
        header('Content-Type: application/json; charset=utf-8');
        if (!verify_csrf_token($csrf)) {
            echo json_encode(['ok' => false, 'message' => 'Your session has expired. Please refresh the page and try again.']);
            exit;
        }

        $targetId = (int)($_POST['user_id'] ?? 0);
        $phoneInput = trim((string)($_POST['phone'] ?? ''));
        $messageText = trim((string)($_POST['message'] ?? ''));

        if ($targetId < 1) {
            echo json_encode(['ok' => false, 'message' => 'Invalid staff account.']);
            exit;
        }

        $tch = $migration->getTeacherForInvite($targetId);
        if (!$tch) {
            echo json_encode(['ok' => false, 'message' => 'Staff account not found.']);
            exit;
        }

        $phone = $phoneInput !== '' ? $phoneInput : (string)($tch['teacher_phone'] ?? '');
        $phone = trim($phone);
        if ($phone === '') {
            echo json_encode(['ok' => false, 'message' => 'Recipient phone number is missing. Please provide a valid mobile number.']);
            exit;
        }

        // If message text was not provided, generate a fresh single-use invite link automatically
        if ($messageText === '') {
            $res = $migration->createInvite($targetId, null, $adminUserId, 7);
            if (!$res['ok']) {
                echo json_encode(['ok' => false, 'message' => $res['message'] ?? 'Could not generate invitation link.']);
                exit;
            }
            $inviteUrl = (string)($res['invite_url'] ?? '');
            $teacherName = trim((string)($tch['teacher_name'] ?? '')) ?: 'Teacher';
            $messageText = "Edexcel College: Dear {$teacherName}, please click this link to link your Google account: {$inviteUrl} . You will be asked to sign in with your Google account.";
        }

        if (!function_exists('sms_send')) {
            require_once __DIR__ . '/../config/sms_gateway.php';
        }

        $gatewayCfg = sms_gateway_config($pdo);
        if (empty($gatewayCfg['sms_gateway_username']) || empty($gatewayCfg['sms_gateway_password'])) {
            echo json_encode([
                'ok' => false,
                'message' => 'SMS Gateway is not configured. Please enter SMS Gateway credentials in Admin Settings.',
            ]);
            exit;
        }

        $sent = sms_send($pdo, $phone, $messageText);
        if (!$sent) {
            $err = sms_send_last_error();
            $migration->logAudit('teacher_oauth_sms_failed', 'users', $targetId, [
                'phone' => $phone,
                'error' => $err,
                'admin_user_id' => $adminUserId,
            ]);
            echo json_encode([
                'ok' => false,
                'message' => $err ?: 'Failed to dispatch SMS through SMS Gateway. Please check gateway status.',
            ]);
            exit;
        }

        $msgId = sms_send_last_id();
        $migration->recordSmsDispatch($targetId, $phone, $msgId, $adminUserId);

        echo json_encode([
            'ok' => true,
            'message' => "SMS successfully queued through SMS Gateway! (Gateway ID: {$msgId})",
            'gateway_msg_id' => $msgId,
            'phone' => $phone,
            'dispatched_at' => date('Y-m-d H:i:s'),
        ]);
        exit;
    }

    if (!verify_csrf_token($csrf)) {
        $error = 'Your session has expired. Please refresh the page and try again.';
    } else {
        if ($action === 'begin_staff_google_link') {
            $targetId = (int)($_POST['user_id'] ?? 0);
            $linkIntent = (string)($_POST['intent'] ?? '');
            if (!in_array($linkIntent, ['admin_link_teacher', 'link_admin'], true)) {
                $error = 'Invalid linking request.';
            } else {
                $target = $migration->eligibleStaffLinkTarget($targetId);
                if ($target === null) {
                    $error = 'That staff account cannot be linked.';
                } else {
                    $_SESSION['admin_linking_teacher_user_id'] = (int)$target['id'];
                    $_SESSION['admin_linking_staff_ready'] = 1;
                    $_SESSION['admin_linking_staff_intent'] = $linkIntent;
                    header('Location: /auth/google/start.php?intent=' . rawurlencode($linkIntent));
                    exit;
                }
            }
        } elseif ($action === 'create_invite') {
            $targetId = (int)($_POST['user_id'] ?? 0);
            $expectedEmail = trim((string)($_POST['expected_email'] ?? ''));
            $ttlDays = (int)($_POST['ttl_days'] ?? 7);

            $res = $migration->createInvite($targetId, $expectedEmail !== '' ? $expectedEmail : null, $adminUserId, $ttlDays);
            if ($res['ok']) {
                $tch = $migration->getTeacherForInvite($targetId);
                $teacherName = trim((string)($tch['teacher_name'] ?? '')) ?: 'Teacher';
                $teacherPhone = trim((string)($tch['teacher_phone'] ?? ''));
                $cleanPhone = preg_replace('/[^0-9]/', '', $teacherPhone);
                if (str_starts_with($cleanPhone, '0') && strlen($cleanPhone) === 10) {
                    $cleanPhone = '94' . substr($cleanPhone, 1);
                } elseif (strlen($cleanPhone) === 9 && (str_starts_with($cleanPhone, '7') || str_starts_with($cleanPhone, '1') || str_starts_with($cleanPhone, '2'))) {
                    $cleanPhone = '94' . $cleanPhone;
                }

                $inviteUrl = (string)($res['invite_url'] ?? '');
                $smsText = "Edexcel College: Dear {$teacherName}, please click this link to link your Google account: {$inviteUrl} . You will be asked to sign in with your Google account.";

                $message = $res['message'];
                $generatedInvite = [
                    'user_id' => $targetId,
                    'token' => $res['token'] ?? '',
                    'url' => $inviteUrl,
                    'expires_at' => $res['expires_at'] ?? '',
                    'expected_email' => $expectedEmail,
                    'teacher_name' => $teacherName,
                    'teacher_phone' => $teacherPhone,
                    'clean_phone' => $cleanPhone,
                    'sms_text' => $smsText,
                    'whatsapp_url' => $cleanPhone !== '' ? 'https://wa.me/' . $cleanPhone . '?text=' . rawurlencode($smsText) : '',
                    'sms_url' => $teacherPhone !== '' ? 'sms:' . rawurlencode($teacherPhone) . '?body=' . rawurlencode($smsText) : '',
                ];
            } else {
                $error = $res['message'];
            }
        } elseif ($action === 'unlink_teacher') {
            $targetId = (int)($_POST['user_id'] ?? 0);
            $reason = trim((string)($_POST['reason'] ?? ''));

            $res = $migration->unlinkTeacher($targetId, $adminUserId, $reason);
            if ($res['ok']) {
                $message = $res['message'];
            } else {
                $error = $res['message'];
            }
        } elseif ($action === 'update_status') {
            $targetId = (int)($_POST['user_id'] ?? 0);
            $newStatus = trim((string)($_POST['status'] ?? ''));
            $notes = trim((string)($_POST['notes'] ?? ''));

            $res = $migration->setStatus($targetId, $newStatus, $notes, $adminUserId);
            if ($res['ok']) {
                $message = $res['message'];
            } else {
                $error = $res['message'];
            }
        } elseif ($action === 'toggle_legacy_login') {
            $disabled = (int)($_POST['legacy_disabled'] ?? 0) === 1;
            $force = isset($_POST['force']) && (int)$_POST['force'] === 1;

            $res = $migration->setLegacyLoginDisabled($disabled, $adminUserId, $force);
            if ($res['ok']) {
                $message = $res['message'];
            } else {
                $error = $res['message'];
            }
        }
    }
}

// Fetch query filters
$search = trim((string)($_GET['q'] ?? ''));
$statusFilter = trim((string)($_GET['status'] ?? 'all'));

$stats = $migration->getTeacherStats();
$teachers = $migration->getTeachersList($search !== '' ? $search : null, $statusFilter);
$auditLogs = $migration->getRecentAuditLogs(30);
$stmtAdmin = $pdo->query("SELECT id, username, google_id, google_email, teacher_oauth_status, teacher_oauth_linked_at FROM users WHERE role = 'admin' AND deleted_at IS NULL ORDER BY id ASC");
$adminAccounts = $stmtAdmin ? $stmtAdmin->fetchAll(PDO::FETCH_ASSOC) : [];

$page_title = 'Staff & Teacher Google OAuth Migration';
$current_page = 'teacher_oauth_migration.php';
require_once __DIR__ . '/../includes/header.php';

$h = static fn(mixed $v) => htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
?>

<style>
    .kpi-card {
        border-radius: 12px;
        padding: 20px;
        border: 1px solid var(--panel-border, #e5e7eb);
        background: var(--surface, #fff);
        box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        display: flex;
        align-items: center;
        gap: 16px;
    }
    .kpi-icon {
        width: 48px;
        height: 48px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.4rem;
    }
    .kpi-val { font-size: 1.8rem; font-weight: 800; line-height: 1; }
    .kpi-lbl { font-size: 0.82rem; color: var(--muted, #64748b); text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600; margin-top: 4px; }
    .status-badge { font-size: 0.78rem; font-weight: 700; padding: 5px 10px; border-radius: 20px; text-transform: uppercase; letter-spacing: 0.4px; }
    .badge-linked { background: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; }
    .badge-not_linked { background: #fef3c7; color: #b45309; border: 1px solid #fde68a; }
    .badge-requires_review { background: #ffedd5; color: #c2410c; border: 1px solid #fed7aa; }
    .badge-disabled { background: #fee2e2; color: #b91c1c; border: 1px solid #fecaca; }
    .teacher-avatar { width: 44px; height: 44px; border-radius: 50%; object-fit: cover; }
    .teacher-avatar-placeholder {
        width: 44px; height: 44px; border-radius: 50%;
        background: var(--primary-soft, rgba(81,97,206,.15));
        color: var(--primary, #5161ce);
        display: flex; align-items: center; justify-content: center;
        font-weight: 800; font-size: 1.1rem;
    }
    .copy-box { background: var(--surface-soft, rgba(0,0,0,.03)); border: 1px dashed var(--panel-border, #cbd5e1); border-radius: 8px; padding: 12px; font-family: monospace; font-size: 0.85rem; word-break: break-all; }
</style>

<div class="container-fluid py-4">
    <!-- Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
        <div>
            <h1 class="h3 mb-1 fw-bold"><i class="bi bi-person-lines-fill text-primary me-2"></i>Teacher Google OAuth Migration</h1>
            <p class="text-muted mb-0">Migrate existing teacher accounts from usernames to verified Google accounts without data loss.</p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <?php if (!$googleReady): ?>
                <span class="badge bg-warning text-dark"><i class="bi bi-exclamation-triangle me-1"></i>Google OAuth Unconfigured</span>
            <?php else: ?>
                <span class="badge bg-success-subtle text-success border border-success-subtle"><i class="bi bi-shield-check me-1"></i>OAuth Ready</span>
            <?php endif; ?>
        </div>
    </div>

    <!-- Feedback Alerts -->
    <?php if ($message !== ''): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i><?= $h($message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-octagon-fill me-2"></i><?= $h($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Newly Generated Invite Modal / Callout -->
    <?php if ($generatedInvite): ?>
        <div class="card border-primary mb-4 shadow-sm">
            <div class="card-header bg-primary text-white d-flex align-items-center justify-content-between">
                <span class="fw-bold"><i class="bi bi-link-45deg me-2"></i>One-Time Invitation Link Generated</span>
                <span class="badge bg-white text-primary">Expires: <?= $h($generatedInvite['expires_at']) ?></span>
            </div>
            <div class="card-body">
                <p class="mb-3">Send this secure one-time link or ready-to-send SMS message to <strong><?= $h($generatedInvite['teacher_name']) ?></strong>. When they open it and sign in with Google, their account will be permanently linked:</p>

                <div class="mb-3">
                    <label class="form-label small fw-semibold text-muted text-uppercase">Google Account Linking Link</label>
                    <div class="input-group">
                        <input type="text" id="inviteUrlField" class="form-control font-monospace" value="<?= $h($generatedInvite['url']) ?>" readonly>
                        <button class="btn btn-outline-primary" type="button" onclick="copyToClipboard(document.getElementById('inviteUrlField').value, this, 'Linking link copied!')"><i class="bi bi-clipboard me-1"></i>Copy Link</button>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-semibold text-muted text-uppercase">Ready-to-Send SMS Message</label>
                    <textarea id="inviteSmsField" class="form-control font-monospace" rows="2" readonly><?= $h($generatedInvite['sms_text']) ?></textarea>
                    <div class="d-flex flex-wrap justify-content-between align-items-center mt-2 gap-2">
                        <span class="small text-muted"><i class="bi bi-chat-dots me-1"></i><?= mb_strlen((string)$generatedInvite['sms_text']) ?> characters</span>
                        <div class="d-flex flex-wrap gap-2">
                            <?php if (!empty($generatedInvite['whatsapp_url'])): ?>
                                <a href="<?= $h($generatedInvite['whatsapp_url']) ?>" target="_blank" class="btn btn-sm btn-outline-success"><i class="bi bi-whatsapp me-1"></i>Send via WhatsApp</a>
                            <?php endif; ?>
                            <?php if (!empty($generatedInvite['sms_url'])): ?>
                                <a href="<?= $h($generatedInvite['sms_url']) ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-phone me-1"></i>Open SMS App</a>
                            <?php endif; ?>
                            <button class="btn btn-sm btn-success" type="button" onclick="copyToClipboard(document.getElementById('inviteSmsField').value, this, 'SMS message copied!')"><i class="bi bi-clipboard-check me-1"></i>Copy SMS Message</button>
                        </div>
                    </div>
                </div>

                <?php if (!empty($generatedInvite['expected_email'])): ?>
                    <div class="small text-muted"><i class="bi bi-shield-lock me-1"></i>Restricted to Google email: <strong><?= $h($generatedInvite['expected_email']) ?></strong></div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- KPI Metric Cards -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-2">
            <div class="kpi-card">
                <div class="kpi-icon bg-secondary-subtle text-secondary"><i class="bi bi-people"></i></div>
                <div>
                    <div class="kpi-val"><?= $stats['total'] ?></div>
                    <div class="kpi-lbl">Total Teachers</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-2">
            <div class="kpi-card">
                <div class="kpi-icon bg-success-subtle text-success"><i class="bi bi-check2-circle"></i></div>
                <div>
                    <div class="kpi-val text-success"><?= $stats['linked'] ?></div>
                    <div class="kpi-lbl">Linked</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-2">
            <div class="kpi-card">
                <div class="kpi-icon bg-warning-subtle text-warning"><i class="bi bi-clock-history"></i></div>
                <div>
                    <div class="kpi-val text-warning"><?= $stats['not_linked'] ?></div>
                    <div class="kpi-lbl">Not Linked</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-2">
            <div class="kpi-card">
                <div class="kpi-icon bg-danger-subtle text-danger"><i class="bi bi-exclamation-triangle"></i></div>
                <div>
                    <div class="kpi-val text-danger"><?= $stats['requires_review'] ?></div>
                    <div class="kpi-lbl">Review Needed</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-2">
            <div class="kpi-card">
                <div class="kpi-icon bg-dark-subtle text-dark"><i class="bi bi-slash-circle"></i></div>
                <div>
                    <div class="kpi-val text-muted"><?= $stats['disabled'] ?></div>
                    <div class="kpi-lbl">Disabled</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-2">
            <div class="kpi-card <?= $stats['legacy_login_disabled'] ? 'border-danger' : 'border-success' ?>">
                <div class="kpi-icon <?= $stats['legacy_login_disabled'] ? 'bg-danger-subtle text-danger' : 'bg-success-subtle text-success' ?>">
                    <i class="bi <?= $stats['legacy_login_disabled'] ? 'bi-lock' : 'bi-unlock' ?>"></i>
                </div>
                <div>
                    <div class="kpi-val" style="font-size:1.1rem;"><?= $stats['legacy_login_disabled'] ? 'Disabled' : 'Active' ?></div>
                    <div class="kpi-lbl">Legacy Login</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Administrator Accounts Google OAuth Status Card -->
    <div class="card mb-4 border-0 shadow-sm" style="background: var(--surface, #fff);">
        <div class="card-header bg-transparent border-bottom d-flex align-items-center justify-content-between py-3">
            <div class="d-flex align-items-center gap-2">
                <i class="bi bi-person-badge-fill text-primary fs-5"></i>
                <h5 class="fw-bold mb-0">Administrator Google Account Linking</h5>
            </div>
            <span class="badge bg-primary-subtle text-primary border border-primary-subtle">Admin Accounts</span>
        </div>
        <div class="card-body p-3">
            <p class="text-muted small mb-3">Administrator accounts sign in using <strong>"Continue with Google"</strong> at the staff login page. Link your Google account below so you can sign in directly with Google.</p>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Admin Username</th>
                            <th>Linked Google Account</th>
                            <th>OAuth Status</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($adminAccounts)): ?>
                            <tr><td colspan="4" class="text-center text-muted py-3">No administrator accounts found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($adminAccounts as $adm): ?>
                                <?php $isLinked = !empty($adm['google_id']) && !empty($adm['google_email']); ?>
                                <tr>
                                    <td>
                                        <strong><?= $h($adm['username']) ?></strong>
                                        <?php if ((int)$adm['id'] === $adminUserId): ?>
                                            <span class="badge bg-info-subtle text-info ms-1">You</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($isLinked): ?>
                                            <div class="d-flex align-items-center gap-2">
                                                <svg style="width:16px;height:16px;" viewBox="0 0 24 24">
                                                    <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                                                    <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                                                    <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z"/>
                                                    <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z"/>
                                                </svg>
                                                <span class="fw-semibold text-primary"><?= $h($adm['google_email']) ?></span>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-muted fst-italic">Not linked</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($isLinked): ?>
                                            <span class="badge bg-success-subtle text-success border border-success-subtle"><i class="bi bi-check-circle me-1"></i>Linked</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning-subtle text-warning border border-warning-subtle"><i class="bi bi-exclamation-triangle me-1"></i>Not Linked</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <?php if ($isLinked): ?>
                                            <div class="d-inline-flex gap-2">
                                                <form method="POST" class="d-inline" onsubmit="return confirm('Re-link or change Google account for administrator <?= $h(addslashes($adm['username'])) ?>?');">
                                                    <input type="hidden" name="csrf_token" value="<?= $h(generate_csrf_token()) ?>">
                                                    <input type="hidden" name="action" value="begin_staff_google_link">
                                                    <input type="hidden" name="intent" value="link_admin">
                                                    <input type="hidden" name="user_id" value="<?= (int)$adm['id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-repeat me-1"></i>Change Google</button>
                                                </form>
                                                <form method="POST" class="d-inline" onsubmit="return confirm('Unlink Google account from administrator <?= $h(addslashes($adm['username'])) ?>?');">
                                                    <input type="hidden" name="csrf_token" value="<?= $h(generate_csrf_token()) ?>">
                                                    <input type="hidden" name="action" value="unlink_teacher">
                                                    <input type="hidden" name="user_id" value="<?= (int)$adm['id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-link-45deg me-1"></i>Unlink</button>
                                                </form>
                                            </div>
                                        <?php else: ?>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Link administrator account <?= $h(addslashes($adm['username'])) ?> with Google now? You will be prompted to sign into Google.');">
                                                <input type="hidden" name="csrf_token" value="<?= $h(generate_csrf_token()) ?>">
                                                <input type="hidden" name="action" value="begin_staff_google_link">
                                                <input type="hidden" name="intent" value="link_admin">
                                                <input type="hidden" name="user_id" value="<?= (int)$adm['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-google me-1"></i>Link with Google</button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Legacy Login Control Banner -->
    <div class="card mb-4 border-0 shadow-sm" style="background: var(--surface, #fff);">
        <div class="card-body p-4 d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div>
                <h5 class="fw-bold mb-1"><i class="bi bi-shield-shaded text-primary me-2"></i>Legacy Username/Password Fallback Gate</h5>
                <p class="text-muted small mb-0">
                    <?php if ($stats['legacy_login_disabled']): ?>
                        <span class="text-danger fw-bold"><i class="bi bi-lock-fill me-1"></i>Legacy login is currently DISABLED.</span> Teachers must sign in using their verified Google accounts.
                    <?php else: ?>
                        <span class="text-success fw-bold"><i class="bi bi-unlock-fill me-1"></i>Legacy login is currently ACTIVE.</span> Teachers can still log in using their old usernames &amp; passwords as a fallback during migration.
                    <?php endif; ?>
                </p>
            </div>
            <div>
                <?php if ($stats['legacy_login_disabled']): ?>
                    <form method="POST" onsubmit="return confirm('Re-enable legacy username/password login for teachers?');" class="d-inline">
                        <input type="hidden" name="csrf_token" value="<?= $h(generate_csrf_token()) ?>">
                        <input type="hidden" name="action" value="toggle_legacy_login">
                        <input type="hidden" name="legacy_disabled" value="0">
                        <button type="submit" class="btn btn-outline-success"><i class="bi bi-unlock me-1"></i>Re-Enable Legacy Login</button>
                    </form>
                <?php else: ?>
                    <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#disableLegacyModal">
                        <i class="bi bi-lock me-1"></i>Disable Legacy Login
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Search & Filter Controls -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-3">
            <form method="GET" class="row g-2 align-items-center">
                <div class="col-md-5">
                    <div class="input-group">
                        <span class="input-group-text bg-transparent border-end-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" name="q" class="form-control border-start-0" placeholder="Search teacher name, username, or email..." value="<?= $h($search) ?>">
                    </div>
                </div>
                <div class="col-md-5">
                    <div class="btn-group w-100" role="group">
                        <a href="?status=all<?= $search !== '' ? '&q=' . rawurlencode($search) : '' ?>" class="btn btn-sm <?= $statusFilter === 'all' ? 'btn-primary' : 'btn-outline-secondary' ?>">All (<?= $stats['total'] ?>)</a>
                        <a href="?status=not_linked<?= $search !== '' ? '&q=' . rawurlencode($search) : '' ?>" class="btn btn-sm <?= $statusFilter === 'not_linked' ? 'btn-primary' : 'btn-outline-secondary' ?>">Not Linked (<?= $stats['not_linked'] ?>)</a>
                        <a href="?status=linked<?= $search !== '' ? '&q=' . rawurlencode($search) : '' ?>" class="btn btn-sm <?= $statusFilter === 'linked' ? 'btn-primary' : 'btn-outline-secondary' ?>">Linked (<?= $stats['linked'] ?>)</a>
                        <a href="?status=requires_review<?= $search !== '' ? '&q=' . rawurlencode($search) : '' ?>" class="btn btn-sm <?= $statusFilter === 'requires_review' ? 'btn-primary' : 'btn-outline-secondary' ?>">Review (<?= $stats['requires_review'] ?>)</a>
                        <a href="?status=disabled<?= $search !== '' ? '&q=' . rawurlencode($search) : '' ?>" class="btn btn-sm <?= $statusFilter === 'disabled' ? 'btn-primary' : 'btn-outline-secondary' ?>">Disabled (<?= $stats['disabled'] ?>)</a>
                    </div>
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-primary btn-sm flex-grow-1">Filter</button>
                    <?php if ($search !== '' || $statusFilter !== 'all'): ?>
                        <a href="teacher_oauth_migration.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-x-lg"></i></a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Teacher Table -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width: 250px;">Teacher</th>
                        <th>Legacy Username</th>
                        <th>Profile Email</th>
                        <th>Linked Google Email</th>
                        <th>Migration Status</th>
                        <th>Linked Details</th>
                        <th class="text-end" style="width: 210px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($teachers)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <i class="bi bi-person-x fs-1 d-block mb-2"></i>
                                No teachers found matching the criteria.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($teachers as $tch): ?>
                            <?php
                                $status = (string)$tch['teacher_oauth_status'];
                                $badgeClass = match($status) {
                                    'linked' => 'badge-linked',
                                    'requires_review' => 'badge-requires_review',
                                    'disabled' => 'badge-disabled',
                                    default => 'badge-not_linked',
                                };
                                $statusText = match($status) {
                                    'linked' => 'Linked',
                                    'requires_review' => 'Requires Review',
                                    'disabled' => 'Disabled',
                                    default => 'Not Linked',
                                };
                            ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-3">
                                        <?php if (!empty($tch['teacher_photo']) && is_file(__DIR__ . '/../' . ltrim($tch['teacher_photo'], '/'))): ?>
                                            <img src="/<?= $h(ltrim($tch['teacher_photo'], '/')) ?>" alt="" class="teacher-avatar">
                                        <?php else: ?>
                                            <div class="teacher-avatar-placeholder">
                                                <?= $h(strtoupper(substr((string)$tch['teacher_name'], 0, 1))) ?>
                                            </div>
                                        <?php endif; ?>
                                        <div>
                                            <div class="fw-bold text-body"><?= $h($tch['teacher_name']) ?></div>
                                            <div class="small text-muted">Teacher ID: #<?= (int)$tch['teacher_id'] ?> · User #<?= (int)$tch['user_id'] ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-secondary-subtle text-secondary font-monospace">
                                        <i class="bi bi-key-fill me-1"></i><?= $h($tch['username']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if (!empty($tch['teacher_email'])): ?>
                                        <a href="mailto:<?= $h($tch['teacher_email']) ?>" class="text-decoration-none small text-body">
                                            <?= $h($tch['teacher_email']) ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted small"><em>None</em></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($tch['google_email'])): ?>
                                        <div class="d-flex align-items-center gap-1 fw-semibold text-success small">
                                            <i class="bi bi-google text-danger"></i> <?= $h($tch['google_email']) ?>
                                        </div>
                                        <?php if (!empty($tch['google_id'])): ?>
                                            <div class="text-muted" style="font-size:0.75rem;">ID: <?= $h(substr((string)$tch['google_id'], 0, 8)) ?>...</div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-muted small"><em>Not linked</em></span>
                                        <?php if (!empty($tch['active_invite'])): ?>
                                            <div class="badge bg-info-subtle text-info border border-info-subtle mt-1" style="font-size:0.7rem;">
                                                <i class="bi bi-envelope me-1"></i>Invite Pending
                                            </div>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="status-badge <?= $badgeClass ?>">
                                        <?= $statusText ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if (!empty($tch['teacher_oauth_linked_at'])): ?>
                                        <div class="small text-muted"><?= $h(date('Y-m-d H:i', strtotime((string)$tch['teacher_oauth_linked_at']))) ?></div>
                                        <?php if (!empty($tch['linked_by_username'])): ?>
                                            <div class="text-muted" style="font-size:0.75rem;">by <?= $h($tch['linked_by_username']) ?></div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-muted small">—</span>
                                    <?php endif; ?>
                                    <?php if (!empty($tch['teacher_oauth_notes'])): ?>
                                        <div class="mt-1" title="<?= $h($tch['teacher_oauth_notes']) ?>">
                                            <i class="bi bi-chat-left-text text-muted" style="cursor:help;"></i>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end" style="white-space: nowrap;">
                                    <div class="d-inline-flex align-items-center gap-1">
                                        <button type="button" class="btn btn-sm btn-outline-success" onclick="openSmsInviteModal(<?= (int)$tch['user_id'] ?>, '<?= $h(addslashes((string)$tch['teacher_name'])) ?>', '<?= $h(addslashes((string)($tch['teacher_phone'] ?? ''))) ?>', '<?= $h(addslashes((string)($tch['teacher_email'] ?? ''))) ?>')" title="Generate Link & Send via SMS / WhatsApp">
                                            <i class="bi bi-chat-text-fill me-1"></i>Copy &amp; SMS
                                        </button>
                                        <div class="btn-group">
                                            <button type="button" class="btn btn-sm btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                                                Actions
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                                                <!-- Action: Copy Link & Send SMS -->
                                                <li>
                                                    <button class="dropdown-item text-success fw-semibold" type="button" onclick="openSmsInviteModal(<?= (int)$tch['user_id'] ?>, '<?= $h(addslashes((string)$tch['teacher_name'])) ?>', '<?= $h(addslashes((string)($tch['teacher_phone'] ?? ''))) ?>', '<?= $h(addslashes((string)($tch['teacher_email'] ?? ''))) ?>')">
                                                        <i class="bi bi-chat-text-fill text-success me-2"></i>Copy Link &amp; Send SMS
                                                    </button>
                                                </li>

                                                <!-- Action: Send via SMS Gateway Direct -->
                                                <li>
                                                    <button class="dropdown-item text-primary fw-semibold" type="button" onclick="sendViaSmsGatewayDirect(<?= (int)$tch['user_id'] ?>, '<?= $h(addslashes((string)$tch['teacher_name'])) ?>', '<?= $h(addslashes((string)($tch['teacher_phone'] ?? ''))) ?>')">
                                                        <i class="bi bi-broadcast text-primary me-2"></i>Send via SMS Gateway
                                                    </button>
                                                </li>

                                                <!-- Action: Copy In-Person Direct Link -->
                                                <li>
                                                    <button class="dropdown-item" type="button" onclick="showToast('In-person linking starts from this page while you are signed in. Use In-Person Google Link.')">
                                                        <i class="bi bi-clipboard-check text-info me-2"></i>In-Person Link Stays on This Page
                                                    </button>
                                                </li>

                                                <!-- Action: Generate Custom Invite -->
                                                <li>
                                                    <button class="dropdown-item" type="button" onclick="openInviteModal(<?= (int)$tch['user_id'] ?>, '<?= $h(addslashes((string)$tch['teacher_name'])) ?>', '<?= $h(addslashes((string)($tch['teacher_email'] ?? ''))) ?>')">
                                                        <i class="bi bi-send text-primary me-2"></i>Custom Expiry Invite...
                                                    </button>
                                                </li>

                                                <!-- Action: In-Person OAuth Direct Link -->
                                                <li>
                                                    <form method="POST" onsubmit="return confirm('Start Google OAuth linking now for <?= $h(addslashes((string)$tch['teacher_name'])) ?>? You will be asked to sign in with their Google account.');">
                                                        <input type="hidden" name="csrf_token" value="<?= $h(generate_csrf_token()) ?>">
                                                        <input type="hidden" name="action" value="begin_staff_google_link">
                                                        <input type="hidden" name="intent" value="admin_link_teacher">
                                                        <input type="hidden" name="user_id" value="<?= (int)$tch['user_id'] ?>">
                                                        <button type="submit" class="dropdown-item">
                                                            <i class="bi bi-person-check text-secondary me-2"></i>In-Person Google Link
                                                        </button>
                                                    </form>
                                                </li>

                                                <li><hr class="dropdown-divider"></li>

                                                <!-- Action: Update Status & Notes -->
                                                <li>
                                                    <button class="dropdown-item" type="button" onclick="openStatusModal(<?= (int)$tch['user_id'] ?>, '<?= $h(addslashes((string)$tch['teacher_name'])) ?>', '<?= $status ?>', '<?= $h(addslashes((string)$tch['teacher_oauth_notes'])) ?>')">
                                                        <i class="bi bi-pencil-square text-secondary me-2"></i>Update Status &amp; Notes
                                                    </button>
                                                </li>

                                                <!-- Action: Unlink Account (if currently linked) -->
                                                <?php if ($status === 'linked' || !empty($tch['google_id'])): ?>
                                                    <li>
                                                        <button class="dropdown-item text-danger" type="button" onclick="openUnlinkModal(<?= (int)$tch['user_id'] ?>, '<?= $h(addslashes((string)$tch['teacher_name'])) ?>', '<?= $h(addslashes((string)$tch['google_email'])) ?>')">
                                                            <i class="bi bi-link-45deg me-2"></i>Unlink / Restore Username
                                                        </button>
                                                    </li>
                                                <?php endif; ?>
                                            </ul>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Audit History Section -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-transparent d-flex align-items-center justify-content-between py-3">
            <h5 class="fw-bold mb-0"><i class="bi bi-journal-text text-primary me-2"></i>Recent Migration Audit Trail</h5>
            <span class="badge bg-secondary-subtle text-secondary"><?= count($auditLogs) ?> entries</span>
        </div>
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0" style="font-size:0.85rem;">
                <thead class="table-light">
                    <tr>
                        <th style="width: 160px;">Date &amp; Time</th>
                        <th>Action</th>
                        <th>Record / Target</th>
                        <th>Actor</th>
                        <th>Details</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($auditLogs)): ?>
                        <tr>
                            <td colspan="5" class="text-center py-4 text-muted">No migration events recorded yet.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($auditLogs as $log): ?>
                            <tr>
                                <td class="text-muted"><?= $h($log['created_at']) ?></td>
                                <td><span class="badge bg-light text-dark border"><?= $h($log['action']) ?></span></td>
                                <td><?= $h($log['table_name']) ?> #<?= (int)$log['record_id'] ?></td>
                                <td><?= $h($log['actor_username'] ?: ('User #' . $log['user_id'])) ?></td>
                                <td>
                                    <code class="text-muted" style="font-size:0.75rem;">
                                        <?= $h(mb_substr((string)$log['details'], 0, 120)) ?>
                                    </code>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal: Generate Invite Link -->
<div class="modal fade" id="inviteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <input type="hidden" name="csrf_token" value="<?= $h(generate_csrf_token()) ?>">
            <input type="hidden" name="action" value="create_invite">
            <input type="hidden" name="user_id" id="modalInviteUserId" value="">
            <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="bi bi-send text-primary me-2"></i>Generate One-Time Linking Invite</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Generate a secure, single-use invitation link for <strong id="modalInviteTeacherName"></strong>.</p>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Expected Google Email (Optional)</label>
                    <input type="email" name="expected_email" id="modalInviteExpectedEmail" class="form-control" placeholder="e.g. teacher@gmail.com or @edexcel.lk">
                    <div class="form-text">If specified, the teacher will only be able to link with this exact Google account.</div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Link Validity</label>
                    <select name="ttl_days" class="form-select">
                        <option value="3">3 Days</option>
                        <option value="7" selected>7 Days (Recommended)</option>
                        <option value="14">14 Days</option>
                        <option value="30">30 Days</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="bi bi-check2 me-1"></i>Generate Link</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Update Status & Notes -->
<div class="modal fade" id="statusModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <input type="hidden" name="csrf_token" value="<?= $h(generate_csrf_token()) ?>">
            <input type="hidden" name="action" value="update_status">
            <input type="hidden" name="user_id" id="modalStatusUserId" value="">
            <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square me-2"></i>Update Status &amp; Notes</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Managing account for <strong id="modalStatusTeacherName"></strong>.</p>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Migration Status</label>
                    <select name="status" id="modalStatusSelect" class="form-select">
                        <option value="not_linked">Not Linked (Legacy Username)</option>
                        <option value="linked">Linked</option>
                        <option value="requires_review">Requires Review / Attention</option>
                        <option value="disabled">Disabled (Block Google Login)</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Administrator Notes</label>
                    <textarea name="notes" id="modalStatusNotes" class="form-control" rows="3" placeholder="Add any relevant notes, review remarks, or reasons..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Unlink / Rollback -->
<div class="modal fade" id="unlinkModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <input type="hidden" name="csrf_token" value="<?= $h(generate_csrf_token()) ?>">
            <input type="hidden" name="action" value="unlink_teacher">
            <input type="hidden" name="user_id" id="modalUnlinkUserId" value="">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title fw-bold"><i class="bi bi-exclamation-triangle-fill me-2"></i>Unlink Google Account &amp; Rollback</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to unlink the Google account for <strong id="modalUnlinkTeacherName"></strong>?</p>
                <div class="alert alert-warning py-2 small">
                    <i class="bi bi-info-circle me-1"></i><strong>Safety guarantee:</strong> The original username, password, timetable records, classes, and profile remain completely untouched. The teacher will immediately resume using their legacy username and password.
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Currently Linked Google Email</label>
                    <input type="text" id="modalUnlinkGoogleEmail" class="form-control" readonly>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Reason for Unlinking (Optional)</label>
                    <input type="text" name="reason" class="form-control" placeholder="e.g. Teacher linked wrong personal account, requested reset">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-danger"><i class="bi bi-arrow-counterclockwise me-1"></i>Confirm Unlink &amp; Restore Legacy Login</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Disable Legacy Login Confirmation -->
<div class="modal fade" id="disableLegacyModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <input type="hidden" name="csrf_token" value="<?= $h(generate_csrf_token()) ?>">
            <input type="hidden" name="action" value="toggle_legacy_login">
            <input type="hidden" name="legacy_disabled" value="1">
            <input type="hidden" name="force" value="1">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title fw-bold"><i class="bi bi-lock-fill me-2"></i>Disable Legacy Password Login</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to disable legacy username and password login for all teachers?</p>
                <?php if ($stats['not_linked'] > 0 || $stats['requires_review'] > 0): ?>
                    <div class="alert alert-danger py-2 small">
                        <i class="bi bi-exclamation-triangle-fill me-1"></i><strong>Warning:</strong> There are currently <strong><?= $stats['not_linked'] + $stats['requires_review'] ?></strong> teacher account(s) that are not linked to Google yet. Disabling legacy login now will prevent them from signing in until an administrator links their accounts.
                    </div>
                <?php else: ?>
                    <div class="alert alert-success py-2 small">
                        <i class="bi bi-check-circle-fill me-1"></i>All active teacher accounts are linked with Google! It is safe to proceed.
                    </div>
                <?php endif; ?>
                <p class="small text-muted mb-0">You can re-enable legacy login at any time using the toggle switch if needed.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-danger"><i class="bi bi-lock-fill me-1"></i>Confirm &amp; Disable Legacy Login</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Dedicated Copy Link & Send SMS Modal -->
<div class="modal fade" id="smsInviteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-light border-bottom">
                <h5 class="modal-title fw-bold text-success">
                    <i class="bi bi-chat-text-fill me-2"></i>Copy Link &amp; Send SMS
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <!-- Teacher Details Card -->
                <div class="p-3 bg-light rounded-3 mb-3 d-flex flex-wrap align-items-center justify-content-between gap-2 border">
                    <div>
                        <div class="small text-muted text-uppercase fw-bold">Teacher Recipient</div>
                        <h5 class="mb-0 fw-bold text-primary" id="smsModalTeacherName">—</h5>
                    </div>
                    <div class="text-end">
                        <div class="small text-muted text-uppercase fw-bold">Contact Number</div>
                        <span id="smsModalTeacherPhone" class="badge bg-secondary-subtle text-secondary font-monospace fs-6">—</span>
                    </div>
                </div>

                <!-- Loading Spinner -->
                <div id="smsModalLoading" class="text-center py-4">
                    <div class="spinner-border text-primary" role="status"></div>
                    <div class="small text-muted mt-2">Generating secure Google linking invitation link...</div>
                </div>

                <!-- Error Notice -->
                <div id="smsModalError" class="alert alert-danger d-none py-2"></div>

                <!-- Modal Content Area -->
                <div id="smsModalContent" class="d-none">
                    <!-- Google Linking Link Field -->
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted text-uppercase">Google Account Linking Link (Single-Use)</label>
                        <div class="input-group">
                            <input type="text" id="smsModalLinkField" class="form-control font-monospace" readonly>
                            <button class="btn btn-outline-primary" type="button" onclick="copyModalField('smsModalLinkField', this, 'Linking link copied to clipboard!')">
                                <i class="bi bi-clipboard me-1"></i>Copy Link
                            </button>
                        </div>
                        <div class="form-text small text-muted">
                            <i class="bi bi-info-circle me-1"></i>When the teacher opens this link on their mobile phone or PC, they will sign in with their Google account to permanently link it.
                        </div>
                    </div>

                    <!-- Ready-to-Send SMS Message -->
                    <div class="mb-3">
                        <div class="d-flex flex-wrap justify-content-between align-items-center mb-1 gap-2">
                            <label class="form-label small fw-semibold text-muted text-uppercase mb-0">Ready-to-Send SMS Message</label>
                            <div class="btn-group btn-group-sm" role="group">
                                <button type="button" class="btn btn-primary active" id="btnPhraseYour" onclick="selectSmsPhrase('your')">
                                    "your Google account"
                                </button>
                                <button type="button" class="btn btn-outline-secondary" id="btnPhraseTheir" onclick="selectSmsPhrase('their')">
                                    "their Google account"
                                </button>
                                <button type="button" class="btn btn-outline-secondary" id="btnPhraseDirect" onclick="selectSmsPhrase('direct')">
                                    In-Person Link
                                </button>
                            </div>
                        </div>
                        <textarea id="smsModalTextField" class="form-control font-monospace" rows="3" oninput="updateSmsCharCount()"></textarea>
                        <div class="d-flex flex-wrap justify-content-between align-items-center mt-2 gap-2">
                            <span class="small text-muted" id="smsModalCharCount">0 characters</span>
                            <button class="btn btn-success" type="button" onclick="copyModalField('smsModalTextField', this, 'SMS message copied to clipboard!')">
                                <i class="bi bi-clipboard-check me-1"></i>Copy SMS Message
                            </button>
                        </div>
                    </div>

                    <!-- Primary Action: Send via College SMS Gateway -->
                    <div class="card border-primary-subtle bg-primary bg-opacity-10 mb-3 shadow-sm">
                        <div class="card-body p-3">
                            <div class="d-flex flex-wrap justify-content-between align-items-center mb-2 gap-2">
                                <div class="d-flex align-items-center gap-2">
                                    <i class="bi bi-broadcast fs-5 text-primary"></i>
                                    <span class="fw-bold text-primary text-uppercase small">College SMS Gateway</span>
                                </div>
                                <?php if (!empty($smsGatewayStatus['ready'])): ?>
                                    <span class="badge bg-success text-white">
                                        <i class="bi bi-check-circle me-1"></i>Gateway Ready (<?= $h(strtoupper((string)($smsGatewayStatus['mode'] ?? 'cloud'))) ?>)
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-warning text-dark">
                                        <i class="bi bi-exclamation-triangle me-1"></i>Gateway Not Configured
                                    </span>
                                <?php endif; ?>
                            </div>

                            <div class="row g-2 align-items-center mb-2">
                                <div class="col-md-7">
                                    <label class="form-label small fw-semibold text-muted text-uppercase mb-1">Recipient Mobile Number</label>
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text bg-white"><i class="bi bi-telephone text-muted"></i></span>
                                        <input type="text" id="smsModalGatewayPhone" class="form-control font-monospace" placeholder="e.g. 0771234567 or +94771234567" title="Recipient mobile number for SMS gateway">
                                    </div>
                                    <div class="form-text small text-muted" style="font-size: 0.72rem;">
                                        Delivery destination for automated SMS dispatch through college gateway.
                                    </div>
                                </div>
                                <div class="col-md-5 d-flex align-items-end">
                                    <button type="button" id="btnSendSmsGateway" class="btn btn-primary btn-sm w-100 fw-bold py-2" onclick="sendInviteViaSmsGateway()">
                                        <i class="bi bi-broadcast me-1"></i>Send via SMS Gateway
                                    </button>
                                </div>
                            </div>

                            <!-- Live Dispatch Feedback -->
                            <div id="smsGatewayAlert" class="d-none alert mb-0 py-2 small" role="alert"></div>
                        </div>
                    </div>

                    <!-- Quick Share Actions -->
                    <div class="p-3 bg-light rounded-3 mb-3 border">
                        <div class="small fw-bold text-muted text-uppercase mb-2"><i class="bi bi-share-fill me-1"></i>Manual Share Options</div>
                        <div class="d-flex flex-wrap gap-2">
                            <a id="smsModalWhatsAppBtn" href="#" target="_blank" class="btn btn-outline-success flex-grow-1">
                                <i class="bi bi-whatsapp me-1"></i>Send via WhatsApp
                            </a>
                            <a id="smsModalSmsAppBtn" href="#" class="btn btn-outline-primary flex-grow-1">
                                <i class="bi bi-phone me-1"></i>Open Mobile SMS App
                            </a>
                        </div>
                    </div>

                    <!-- In-Person Link Accordion -->
                    <div class="accordion" id="smsModalAccordion">
                        <div class="accordion-item border rounded-3 overflow-hidden">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed py-2 text-secondary small bg-light" type="button" data-bs-toggle="collapse" data-bs-target="#collapseInPersonLink">
                                    <i class="bi bi-link-45deg me-1"></i>Direct In-Person OAuth Link (Admin Device Only)
                                </button>
                            </h2>
                            <div id="collapseInPersonLink" class="accordion-collapse collapse" data-bs-parent="#smsModalAccordion">
                                <div class="accordion-body p-3">
                                    <div class="input-group input-group-sm">
                                        <input type="text" id="smsModalDirectLinkField" class="form-control font-monospace" readonly>
                                        <button class="btn btn-outline-secondary" type="button" onclick="copyModalField('smsModalDirectLinkField', this, 'Direct link copied!')">
                                            <i class="bi bi-clipboard me-1"></i>Copy Direct Link
                                        </button>
                                    </div>
                                    <div class="form-text small text-muted mt-2">
                                        In-person linking is started with the In-Person Google Link button on this page. A copied URL cannot choose the staff account.
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light border-top d-flex justify-content-between">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-outline-success" onclick="copyModalField('smsModalTextField', this, 'SMS message copied to clipboard!')">
                        <i class="bi bi-clipboard-check me-1"></i>Copy SMS Text
                    </button>
                    <button type="button" class="btn btn-primary fw-bold" onclick="sendInviteViaSmsGateway()">
                        <i class="bi bi-broadcast me-1"></i>Send via SMS Gateway
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Toast Container for instant clipboard notifications -->
<div class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index: 9999;">
    <div id="appToast" class="toast align-items-center text-white bg-dark border-0 shadow" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="d-flex">
            <div class="toast-body" id="appToastBody">
                <i class="bi bi-check-circle-fill text-success me-2"></i>Copied to clipboard!
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
    </div>
</div>

<script>
var currentSmsData = null;
var currentSmsMode = 'your';

function openSmsInviteModal(userId, name, phone, email) {
    document.getElementById('smsModalTeacherName').textContent = name;
    document.getElementById('smsModalTeacherPhone').textContent = phone || 'No phone recorded';
    document.getElementById('smsModalLoading').classList.remove('d-none');
    document.getElementById('smsModalError').classList.add('d-none');
    document.getElementById('smsModalContent').classList.add('d-none');

    var modalEl = document.getElementById('smsInviteModal');
    var modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    modal.show();

    // Call AJAX endpoint to create the invite
    var formData = new FormData();
    formData.append('action', 'ajax_create_invite');
    formData.append('csrf_token', '<?= $h(generate_csrf_token()) ?>');
    formData.append('user_id', userId);
    formData.append('ttl_days', '7');

    fetch('teacher_oauth_migration.php', {
        method: 'POST',
        body: formData,
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(function(res) { return res.json(); })
    .then(function(data) {
        document.getElementById('smsModalLoading').classList.add('d-none');
        if (!data.ok) {
            document.getElementById('smsModalError').textContent = data.message || 'Could not generate invite link.';
            document.getElementById('smsModalError').classList.remove('d-none');
            return;
        }

        currentSmsData = data;
        currentSmsMode = 'your';

        document.getElementById('smsModalLinkField').value = data.invite_url || '';
        document.getElementById('smsModalDirectLinkField').value = data.direct_oauth_url || '';
        document.getElementById('smsModalTextField').value = data.sms_text_your || '';

        var phoneInput = document.getElementById('smsModalGatewayPhone');
        if (phoneInput) {
            phoneInput.value = data.clean_phone || data.teacher_phone || '';
        }
        var alertEl = document.getElementById('smsGatewayAlert');
        if (alertEl) {
            alertEl.className = 'd-none alert mb-0 py-2 small';
            alertEl.innerHTML = '';
        }

        updateSmsCharCount();
        updateShareLinks();
        selectSmsPhrase('your', false);

        document.getElementById('smsModalContent').classList.remove('d-none');
    })
    .catch(function(err) {
        document.getElementById('smsModalLoading').classList.add('d-none');
        document.getElementById('smsModalError').textContent = 'Network or server error while generating invite.';
        document.getElementById('smsModalError').classList.remove('d-none');
    });
}

function selectSmsPhrase(mode, updateText) {
    if (updateText === undefined) updateText = true;
    currentSmsMode = mode;
    var btnYour = document.getElementById('btnPhraseYour');
    var btnTheir = document.getElementById('btnPhraseTheir');
    var btnDirect = document.getElementById('btnPhraseDirect');

    btnYour.classList.remove('active', 'btn-primary');
    btnTheir.classList.remove('active', 'btn-primary');
    btnDirect.classList.remove('active', 'btn-primary');
    btnYour.classList.add('btn-outline-secondary');
    btnTheir.classList.add('btn-outline-secondary');
    btnDirect.classList.add('btn-outline-secondary');

    if (mode === 'their') {
        btnTheir.classList.add('active', 'btn-primary');
        btnTheir.classList.remove('btn-outline-secondary');
        if (updateText && currentSmsData) {
            document.getElementById('smsModalTextField').value = currentSmsData.sms_text_their;
        }
    } else if (mode === 'direct') {
        btnDirect.classList.add('active', 'btn-primary');
        btnDirect.classList.remove('btn-outline-secondary');
        if (updateText && currentSmsData) {
            document.getElementById('smsModalTextField').value = currentSmsData.sms_text_direct;
        }
    } else {
        btnYour.classList.add('active', 'btn-primary');
        btnYour.classList.remove('btn-outline-secondary');
        if (updateText && currentSmsData) {
            document.getElementById('smsModalTextField').value = currentSmsData.sms_text_your;
        }
    }

    updateSmsCharCount();
    updateShareLinks();
}

function updateSmsCharCount() {
    var text = document.getElementById('smsModalTextField').value || '';
    var len = text.length;
    var countEl = document.getElementById('smsModalCharCount');
    if (countEl) {
        var smsCount = Math.ceil(len / 160) || 1;
        countEl.textContent = len + ' characters (' + smsCount + ' SMS)';
    }
    updateShareLinks();
}

function updateShareLinks() {
    if (!currentSmsData) return;
    var text = document.getElementById('smsModalTextField').value || '';
    var waBtn = document.getElementById('smsModalWhatsAppBtn');
    var smsBtn = document.getElementById('smsModalSmsAppBtn');

    if (currentSmsData.clean_phone) {
        waBtn.href = 'https://wa.me/' + encodeURIComponent(currentSmsData.clean_phone) + '?text=' + encodeURIComponent(text);
        waBtn.classList.remove('disabled');
    } else {
        waBtn.href = '#';
        waBtn.classList.add('disabled');
    }

    if (currentSmsData.teacher_phone) {
        smsBtn.href = 'sms:' + encodeURIComponent(currentSmsData.teacher_phone) + '?body=' + encodeURIComponent(text);
        smsBtn.classList.remove('disabled');
    } else {
        smsBtn.href = '#';
        smsBtn.classList.add('disabled');
    }
}

function sendInviteViaSmsGateway() {
    if (!currentSmsData || !currentSmsData.user_id) {
        showToast('Staff user ID is missing.');
        return;
    }

    var phoneInput = document.getElementById('smsModalGatewayPhone');
    var phone = phoneInput ? phoneInput.value.trim() : '';
    if (!phone) {
        showToast('Please enter a valid recipient phone number.');
        if (phoneInput) phoneInput.focus();
        return;
    }

    var msg = document.getElementById('smsModalTextField') ? document.getElementById('smsModalTextField').value.trim() : '';
    if (!msg) {
        showToast('SMS message text cannot be empty.');
        return;
    }

    var alertEl = document.getElementById('smsGatewayAlert');
    var btn = document.getElementById('btnSendSmsGateway');
    var origBtnText = btn ? btn.innerHTML : '';

    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status"></span>Dispatching...';
    }
    if (alertEl) {
        alertEl.className = 'alert alert-info mb-0 py-2 small';
        alertEl.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>Connecting to College SMS Gateway...';
    }

    var formData = new FormData();
    formData.append('action', 'ajax_send_sms_gateway');
    formData.append('csrf_token', '<?= $h(generate_csrf_token()) ?>');
    formData.append('user_id', currentSmsData.user_id);
    formData.append('phone', phone);
    formData.append('message', msg);

    fetch('teacher_oauth_migration.php', {
        method: 'POST',
        body: formData,
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(function(res) { return res.json(); })
    .then(function(data) {
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = origBtnText;
        }
        if (data.ok) {
            if (alertEl) {
                alertEl.className = 'alert alert-success mb-0 py-2 small';
                alertEl.innerHTML = '<i class="bi bi-check-circle-fill me-1"></i><strong>SMS Dispatched!</strong> ' + (data.message || 'Delivery queued.') + 
                    (data.gateway_msg_id ? ' <span class="badge bg-success-subtle text-success ms-1">ID: ' + data.gateway_msg_id + '</span>' : '');
            }
            showToast('SMS dispatched successfully via College SMS Gateway!');
        } else {
            if (alertEl) {
                alertEl.className = 'alert alert-danger mb-0 py-2 small';
                alertEl.innerHTML = '<i class="bi bi-exclamation-triangle-fill me-1"></i><strong>Failed:</strong> ' + (data.message || 'Gateway dispatch failed.');
            }
            showToast(data.message || 'SMS Gateway dispatch failed');
        }
    })
    .catch(function(err) {
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = origBtnText;
        }
        if (alertEl) {
            alertEl.className = 'alert alert-danger mb-0 py-2 small';
            alertEl.innerHTML = '<i class="bi bi-exclamation-triangle-fill me-1"></i>Network or server error while sending SMS.';
        }
        showToast('Network error while dispatching SMS');
    });
}

function sendViaSmsGatewayDirect(userId, name, phone) {
    if (!phone) {
        showToast('No phone on file for ' + name + '. Opening SMS console...');
        openSmsInviteModal(userId, name, phone, '');
        return;
    }

    if (!confirm('Dispatch Google linking invitation SMS to ' + name + ' (' + phone + ') via College SMS Gateway now?')) {
        return;
    }

    showToast('Dispatching SMS to ' + phone + '...');

    var formData = new FormData();
    formData.append('action', 'ajax_send_sms_gateway');
    formData.append('csrf_token', '<?= $h(generate_csrf_token()) ?>');
    formData.append('user_id', userId);
    formData.append('phone', phone);
    formData.append('message', ''); // empty message triggers automatic single-use link generation

    fetch('teacher_oauth_migration.php', {
        method: 'POST',
        body: formData,
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(function(res) { return res.json(); })
    .then(function(data) {
        if (data.ok) {
            showToast('SMS dispatched to ' + name + '! (Gateway ID: ' + (data.gateway_msg_id || 'Queued') + ')');
        } else {
            showToast('SMS Gateway failed: ' + (data.message || 'Unknown error'));
        }
    })
    .catch(function(err) {
        showToast('Network error while dispatching SMS');
    });
}

function copyModalField(fieldId, btnEl, successMsg) {
    var field = document.getElementById(fieldId);
    if (!field) return;
    copyToClipboard(field.value, btnEl, successMsg);
}

function copyInPersonLink(userId, name) {
    showToast('In-person linking starts from this page while you are signed in. Use In-Person Google Link.');
}

function copyToClipboard(text, btnEl, successMsg) {
    if (!text) return;
    function notifySuccess() {
        if (btnEl) {
            var orig = btnEl.innerHTML;
            btnEl.innerHTML = '<i class="bi bi-check-lg me-1"></i>Copied!';
            btnEl.classList.add('btn-success');
            setTimeout(function() {
                btnEl.innerHTML = orig;
                btnEl.classList.remove('btn-success');
            }, 2000);
        }
        showToast(successMsg || 'Copied to clipboard!');
    }

    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(text).then(notifySuccess).catch(function() {
            execCommandFallback(text, notifySuccess);
        });
    } else {
        execCommandFallback(text, notifySuccess);
    }
}

function execCommandFallback(text, cb) {
    var ta = document.createElement('textarea');
    ta.value = text;
    ta.style.position = 'fixed';
    ta.style.top = '0';
    ta.style.left = '0';
    ta.style.opacity = '0';
    document.body.appendChild(ta);
    ta.focus();
    ta.select();
    try {
        document.execCommand('copy');
        if (cb) cb();
    } catch (e) {
        prompt('Copy manually (Ctrl+C):', text);
    }
    document.body.removeChild(ta);
}

function showToast(msg) {
    var toastEl = document.getElementById('appToast');
    if (!toastEl) {
        alert(msg);
        return;
    }
    document.getElementById('appToastBody').innerHTML = '<i class="bi bi-check-circle-fill text-success me-2"></i>' + msg;
    var toast = bootstrap.Toast.getOrCreateInstance(toastEl, { delay: 3000 });
    toast.show();
}

function openInviteModal(userId, name, email) {
    document.getElementById('modalInviteUserId').value = userId;
    document.getElementById('modalInviteTeacherName').textContent = name;
    document.getElementById('modalInviteExpectedEmail').value = email || '';
    new bootstrap.Modal(document.getElementById('inviteModal')).show();
}

function openStatusModal(userId, name, status, notes) {
    document.getElementById('modalStatusUserId').value = userId;
    document.getElementById('modalStatusTeacherName').textContent = name;
    document.getElementById('modalStatusSelect').value = status || 'not_linked';
    document.getElementById('modalStatusNotes').value = notes || '';
    new bootstrap.Modal(document.getElementById('statusModal')).show();
}

function openUnlinkModal(userId, name, googleEmail) {
    document.getElementById('modalUnlinkUserId').value = userId;
    document.getElementById('modalUnlinkTeacherName').textContent = name;
    document.getElementById('modalUnlinkGoogleEmail').value = googleEmail || '';
    new bootstrap.Modal(document.getElementById('unlinkModal')).show();
}

function copyInviteUrl() {
    var field = document.getElementById('inviteUrlField');
    if (!field) return;
    copyToClipboard(field.value, null, 'Invitation link copied to clipboard!');
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

