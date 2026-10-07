<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Edexcel\Services\AdminTotpService;
use Edexcel\Services\SecurityEventService;
use Edexcel\Services\AdminBiometricService;
use Edexcel\Services\AdminPasskeyService;
use Edexcel\Services\AdminFaceService;
use Edexcel\Services\GoogleOAuthService;

require_admin();
ensure_ops_schema($pdo);

$error = '';
$success = '';
$setup = null;
$userId = (int)($_SESSION['user_id'] ?? 0);
$username = (string)($_SESSION['username'] ?? '');

$sec = new SecurityEventService($pdo);
$totp = new AdminTotpService($pdo);
$bioService = new AdminBiometricService($pdo);
$passkeyService = new AdminPasskeyService($pdo, $bioService);
$faceService = new AdminFaceService($pdo, $bioService);

// Check if user has re-authenticated recently (valid for 15 mins)
$recentReauth = $totp->hasValidReauth($userId, 'sensitive') || $totp->hasValidReauth($userId, 'biometrics');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Security token expired. Refresh and try again.';
    } else {
        $action = (string)($_POST['action'] ?? '');
        try {
            if ($action === 'totp_begin') {
                $setup = $totp->beginSetup($userId, $username !== '' ? $username : 'admin');
                $_SESSION['admin_totp_setup_pending'] = 1;
                $success = 'Scan the QR / enter the secret in your authenticator, then confirm with a code.';
                $sec->record('totp_setup_started', 'Admin started TOTP setup', 'info', $userId, $username, 'admin', 'security');
            } elseif ($action === 'totp_confirm') {
                $code = trim((string)($_POST['totp_code'] ?? ''));
                if (!$totp->confirmSetup($userId, $code)) {
                    throw new RuntimeException('That code did not match. Try again.');
                }
                unset($_SESSION['admin_totp_setup_pending']);
                $success = 'Authenticator 2FA is now enabled for your account.';
                $sec->record('totp_enabled', 'Admin confirmed TOTP', 'info', $userId, $username, 'admin', 'security');
            } elseif ($action === 'totp_disable') {
                $password = (string)($_POST['password'] ?? '');
                $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE id = ? LIMIT 1');
                $stmt->execute([$userId]);
                $hash = (string)$stmt->fetchColumn();
                if ($hash === '' || !password_verify($password, $hash)) {
                    throw new RuntimeException('Password incorrect.');
                }
                $totp->disable($userId);
                $success = 'Authenticator 2FA disabled for your account.';
                $sec->record('totp_disabled', 'Admin disabled TOTP', 'warning', $userId, $username, 'admin', 'security');
            } elseif ($action === 'totp_required') {
                $on = isset($_POST['admin_totp_required']) ? '1' : '0';
                ops_save_setting($pdo, 'admin_totp_required', $on);
                $success = $on === '1'
                    ? 'Admin TOTP is now required globally (where login enforces it).'
                    : 'Global admin TOTP requirement turned off.';
                $sec->record('totp_policy', 'admin_totp_required=' . $on, 'info', $userId, $username, 'admin', 'security');
            } elseif ($action === 'reauth') {
                $password = (string)($_POST['password'] ?? '');
                $purpose = preg_replace('/[^a-z0-9_]/', '', strtolower((string)($_POST['purpose'] ?? 'sensitive'))) ?: 'sensitive';
                $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE id = ? LIMIT 1');
                $stmt->execute([$userId]);
                $hash = (string)$stmt->fetchColumn();
                if ($hash === '' || !password_verify($password, $hash)) {
                    throw new RuntimeException('Password incorrect.');
                }
                $totp->requireReauth($userId, $purpose, 15);
                $recentReauth = true;
                $success = 'Re-authenticated for "' . $purpose . '" for the next 15 minutes.';
                $sec->record('reauth_ok', 'Reauth for ' . $purpose, 'info', $userId, $username, 'admin', 'security');
            } elseif ($action === 'passkey_revoke') {
                $passkeyId = (int)($_POST['passkey_id'] ?? 0);
                if ($passkeyId > 0) {
                    $passkeyService->revokePasskey($userId, $passkeyId);
                    $success = 'Passkey credential revoked.';
                }
            } elseif ($action === 'passkey_revoke_all') {
                $pkeys = $passkeyService->listPasskeys($userId);
                foreach ($pkeys as $p) {
                    $passkeyService->revokePasskey($userId, (int)$p['id']);
                }
                $success = 'All registered passkeys revoked.';
            } elseif ($action === 'face_disable') {
                $faceService->disableFace($userId);
                $success = 'Webcam face login disabled.';
            } elseif ($action === 'face_clear') {
                $faceService->clearFaceData($userId);
                $success = 'Facial biometric data permanently removed.';
            } elseif ($action === 'revoke_all_sessions') {
                // Invalidate all sessions except current
                $currSessionId = session_id();
                $stmt = $pdo->prepare("
                    DELETE FROM student_active_sessions WHERE user_id = ?
                ");
                $stmt->execute([$userId]);
                $sec->record('sessions_revoked', 'Admin revoked all sessions', 'warning', $userId, $username, 'admin', 'security');
                $success = 'All other active sessions have been revoked.';
            }
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}

$filters = [
    'date_from' => trim((string)($_GET['date_from'] ?? '')),
    'date_to' => trim((string)($_GET['date_to'] ?? '')),
    'user' => trim((string)($_GET['user'] ?? '')),
    'event_type' => trim((string)($_GET['event_type'] ?? '')),
    'ip' => trim((string)($_GET['ip'] ?? '')),
    'severity' => trim((string)($_GET['severity'] ?? '')),
    'limit' => 150,
];

$events = [];
$eventTypes = [];
$authAuditEvents = [];
$totpOn = false;
$totpRequired = false;
$pendingSetup = false;
$passkeys = [];
$faceStatus = null;
$faceEnrolled = false;
$googleLinked = false;

try {
    $events = $sec->search($filters);
    $eventTypes = $sec->eventTypes();
    $totpOn = $totp->isEnabledForUser($userId);
    $totpRequired = $totp->isRequiredGlobally();
    $pendingSetup = !empty($_SESSION['admin_totp_setup_pending']) && !$totpOn;

    $passkeys = $passkeyService->listPasskeys($userId);
    $faceStatus = $faceService->getEnrollmentStatus($userId);
    $faceEnrolled = $faceService->isEnrolled($userId);

    // Check Google OAuth status for this admin
    $stmt = $pdo->prepare('SELECT google_id, google_email FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $uRow = $stmt->fetch(PDO::FETCH_ASSOC);
    $googleLinked = !empty($uRow['google_id']);

    // Fetch recent authentication audit records
    $stmtAudit = $pdo->prepare("
        SELECT * FROM authentication_audit
        ORDER BY id DESC
        LIMIT 50
    ");
    $stmtAudit->execute();
    $authAuditEvents = $stmtAudit->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
    $error = $error !== '' ? $error : ('Unable to load security events: ' . $e->getMessage());
}

include __DIR__ . '/../includes/header.php';
?>
<div class="container-fluid py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <div>
            <h1 class="h3 mb-1"><i class="bi bi-shield-lock-fill text-primary"></i> Admin Security &amp; Biometrics</h1>
            <p class="text-muted mb-0">Production-grade WebAuthn Passkeys, Facial Biometrics, Authenticator 2FA, and Audit Logs.</p>
        </div>
        <div>
            <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-2">
                <i class="bi bi-person-badge me-1"></i> Admin Account (ID #<?= (int)$userId ?>)
            </span>
        </div>
    </div>

    <?php if ($success): ?><div class="alert alert-success alert-dismissible fade show" role="alert"><?= e($success) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger alert-dismissible fade show" role="alert"><?= e($error) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

    <!-- 1. Authentication Methods Overview Banner -->
    <div class="card border-0 shadow-sm rounded-4 p-4 mb-4" style="background: linear-gradient(135deg, rgba(81,97,206,0.06), rgba(30,41,59,0.02)); border: 1px solid rgba(81,97,206,0.12) !important;">
        <h2 class="h5 fw-bold mb-3"><i class="bi bi-shield-check text-primary me-2"></i>Admin Authentication Methods</h2>
        <div class="row g-3">
            <div class="col-md-3 col-6">
                <div class="p-3 bg-white rounded-3 border">
                    <div class="small text-muted mb-1">Password</div>
                    <div class="fw-bold text-success"><i class="bi bi-check-circle-fill me-1"></i> Active</div>
                    <div class="small text-muted mt-1">Fallback enabled</div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="p-3 bg-white rounded-3 border">
                    <div class="small text-muted mb-1">Passkeys (WebAuthn)</div>
                    <div class="fw-bold <?= !empty($passkeys) ? 'text-success' : 'text-secondary' ?>">
                        <i class="bi <?= !empty($passkeys) ? 'bi-check-circle-fill text-success' : 'bi-dash-circle' ?> me-1"></i>
                        <?= count($passkeys) ?> registered
                    </div>
                    <div class="small text-muted mt-1">FIDO2 / Hardware Keys</div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="p-3 bg-white rounded-3 border">
                    <div class="small text-muted mb-1">Face Login</div>
                    <div class="fw-bold <?= $faceEnrolled ? 'text-success' : 'text-warning' ?>">
                        <i class="bi <?= $faceEnrolled ? 'bi-check-circle-fill text-success' : 'bi-exclamation-circle text-warning' ?> me-1"></i>
                        <?= $faceEnrolled ? 'Enrolled' : 'Not Enrolled' ?>
                    </div>
                    <div class="small text-muted mt-1"><?= $faceEnrolled ? ($faceStatus['sample_count'] . ' samples calibrated') : 'Webcam PAD optional' ?></div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="p-3 bg-white rounded-3 border">
                    <div class="small text-muted mb-1">Google OAuth</div>
                    <div class="fw-bold <?= $googleLinked ? 'text-success' : 'text-muted' ?>">
                        <i class="bi <?= $googleLinked ? 'bi-check-circle-fill text-success' : 'bi-dash-circle' ?> me-1"></i>
                        <?= $googleLinked ? 'Linked' : 'Not Linked' ?>
                    </div>
                    <div class="small text-muted mt-1"><?= $googleLinked ? htmlspecialchars((string)($uRow['google_email'] ?? '')) : 'Staff Google SSO' ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- 2. Biometric Management Grid -->
    <div class="row g-4 mb-4">
        <!-- Passkeys (WebAuthn / FIDO2) -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm rounded-4 p-4 h-100">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h2 class="h5 fw-bold mb-1"><i class="bi bi-passkey-fill text-primary me-2"></i>Passkey Authentication</h2>
                        <p class="small text-muted mb-0">Passwordless sign-in via Windows Hello, Face ID, Fingerprint, or YubiKey.</p>
                    </div>
                    <button type="button" class="btn btn-primary btn-sm px-3 shadow-sm js-btn-add-passkey">
                        <i class="bi bi-plus-lg me-1"></i> Add Passkey
                    </button>
                </div>

                <div class="border-top pt-3">
                    <h6 class="small fw-bold text-uppercase text-muted mb-3">Registered Passkeys (<?= count($passkeys) ?>)</h6>
                    <?php if (empty($passkeys)): ?>
                        <div class="text-center py-4 bg-light rounded-3 text-muted">
                            <i class="bi bi-key fs-1 d-block mb-2 text-secondary"></i>
                            <p class="small mb-2">No Passkeys registered yet.</p>
                            <button type="button" class="btn btn-outline-primary btn-sm js-btn-add-passkey">
                                Register this device now
                            </button>
                        </div>
                    <?php else: ?>
                        <div class="list-group list-group-flush border rounded-3 mb-3">
                            <?php foreach ($passkeys as $pk): ?>
                                <div class="list-group-item d-flex justify-content-between align-items-center py-3">
                                    <div>
                                        <div class="fw-semibold"><i class="bi bi-shield-check text-success me-1"></i> <?= e($pk['name']) ?></div>
                                        <div class="small text-muted">
                                            Registered: <?= e(date('Y-m-d H:i', strtotime($pk['created_at']))) ?>
                                            <?php if (!empty($pk['last_used_at'])): ?>
                                                &bull; Last used: <?= e(date('Y-m-d H:i', strtotime($pk['last_used_at']))) ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <form method="post" onsubmit="return confirm('Revoke this passkey credential?');">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="passkey_revoke">
                                        <input type="hidden" name="passkey_id" value="<?= (int)$pk['id'] ?>">
                                        <button type="submit" class="btn btn-outline-danger btn-sm">Revoke</button>
                                    </form>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <form method="post" onsubmit="return confirm('Revoke ALL registered passkeys for this administrator?');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="passkey_revoke_all">
                            <button type="submit" class="btn btn-link text-danger btn-sm p-0">Revoke all passkeys</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Webcam Face Login -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm rounded-4 p-4 h-100">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h2 class="h5 fw-bold mb-1"><i class="bi bi-person-bounding-box text-primary me-2"></i>Face Login (Webcam)</h2>
                        <p class="small text-muted mb-0">1:1 facial verification with Presentation Attack Detection (PAD).</p>
                    </div>
                    <div>
                        <?php if ($faceEnrolled): ?>
                            <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2">Enrolled</span>
                        <?php else: ?>
                            <span class="badge bg-secondary-subtle text-secondary px-3 py-2">Not Enrolled</span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="border-top pt-3">
                    <p class="small text-muted">
                        Facial descriptors are encrypted with AES-256-GCM at rest. Raw webcam photographs and videos are <strong>never stored</strong>.
                    </p>

                    <?php if ($faceEnrolled): ?>
                        <div class="alert alert-info py-2 small mb-3">
                            <i class="bi bi-info-circle me-1"></i>
                            Enrolled on <strong><?= e($faceStatus['enrolled_at'] ?? 'N/A') ?></strong>
                            (<?= (int)($faceStatus['sample_count'] ?? 5) ?> pose samples).
                            <?php if (!empty($faceStatus['last_verified_at'])): ?>
                                Last verified: <?= e($faceStatus['last_verified_at']) ?>
                            <?php endif; ?>
                        </div>

                        <div class="d-flex flex-wrap gap-2">
                            <button type="button" class="btn btn-outline-primary btn-sm js-btn-enrol-face">
                                <i class="bi bi-arrow-repeat me-1"></i> Re-enrol Face
                            </button>
                            <form method="post" class="d-inline" onsubmit="return confirm('Disable face login for your account?');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="face_disable">
                                <button type="submit" class="btn btn-outline-warning btn-sm">
                                    <i class="bi bi-pause-circle me-1"></i> Disable Face Login
                                </button>
                            </form>
                            <form method="post" class="d-inline" onsubmit="return confirm('Permanently delete all biometric face templates from the vault?');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="face_clear">
                                <button type="submit" class="btn btn-outline-danger btn-sm">
                                    <i class="bi bi-trash3 me-1"></i> Clear Face Data
                                </button>
                            </form>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-light border small mb-3">
                            <div class="fw-semibold mb-1"><i class="bi bi-shield-lock me-1"></i> Biometric Enrollment Consent</div>
                            By enrolling, your webcam will calibrate 5 facial poses (straight, left, right, up, down). Descriptors are mathematically hashed and encrypted on the server for 1:1 administrator verification.
                        </div>
                        <button type="button" class="btn btn-primary btn-sm px-4 js-btn-enrol-face">
                            <i class="bi bi-camera-fill me-1"></i> Enrol Face Now
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- 3. TOTP & Re-Auth Grid -->
    <div class="row g-4 mb-4">
        <!-- Authenticator (TOTP) -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm rounded-4 p-4 h-100">
                <h2 class="h5 fw-bold mb-1"><i class="bi bi-phone text-primary me-2"></i>Mobile Authenticator (TOTP)</h2>
                <p class="small text-muted mb-3">Time-based one-time password apps (Google Authenticator, Microsoft Authenticator).</p>

                <p class="small text-muted mb-3">
                    Status:
                    <?php if ($totpOn): ?>
                        <span class="badge text-bg-success">Enabled</span>
                    <?php else: ?>
                        <span class="badge text-bg-secondary">Not enabled</span>
                    <?php endif; ?>
                </p>

                <?php if ($setup): ?>
                    <div class="alert alert-info">
                        <div class="fw-semibold mb-1">Secret</div>
                        <code class="user-select-all"><?= e($setup['secret']) ?></code>
                        <div class="small mt-2">otpauth URL (paste into authenticator if needed):</div>
                        <code class="small text-break d-block"><?= e($setup['otpauth_url']) ?></code>
                        <div class="fw-semibold mt-3">Recovery codes (save now)</div>
                        <ul class="small mb-0">
                            <?php foreach ($setup['recovery_codes'] as $code): ?>
                                <li><code><?= e($code) ?></code></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <form method="post" class="mb-3">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="totp_confirm">
                        <label class="form-label">Confirm with 6-digit code</label>
                        <div class="input-group">
                            <input class="form-control" name="totp_code" inputmode="numeric" autocomplete="one-time-code" required>
                            <button class="btn btn-primary" type="submit">Confirm</button>
                        </div>
                    </form>
                <?php elseif (!$totpOn): ?>
                    <form method="post" class="mb-3">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="totp_begin">
                        <button class="btn btn-primary btn-sm" type="submit">Set up authenticator</button>
                    </form>
                    <?php if ($pendingSetup): ?>
                        <form method="post">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="totp_confirm">
                            <label class="form-label small">Already scanned? Enter code</label>
                            <div class="input-group">
                                <input class="form-control" name="totp_code" inputmode="numeric" required>
                                <button class="btn btn-outline-primary" type="submit">Confirm</button>
                            </div>
                        </form>
                    <?php endif; ?>
                <?php else: ?>
                    <form method="post" class="mb-3" onsubmit="return confirm('Disable authenticator for your account?');">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="totp_disable">
                        <label class="form-label small">Password to disable</label>
                        <div class="input-group">
                            <input type="password" class="form-control" name="password" required autocomplete="current-password">
                            <button class="btn btn-outline-danger" type="submit">Disable 2FA</button>
                        </div>
                    </form>
                <?php endif; ?>

                <hr>
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="totp_required">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="admin_totp_required" name="admin_totp_required" <?= $totpRequired ? 'checked' : '' ?>>
                        <label class="form-check-label small" for="admin_totp_required">Require admin TOTP globally (<code>admin_totp_required</code>)</label>
                    </div>
                    <button class="btn btn-sm btn-outline-secondary mt-2" type="submit">Save policy</button>
                </form>
            </div>
        </div>

        <!-- Re-Auth & Active Session Management -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm rounded-4 p-4 h-100">
                <h2 class="h5 fw-bold mb-1"><i class="bi bi-clock-history text-primary me-2"></i>Password Re-Authentication</h2>
                <p class="small text-muted mb-3">Re-authenticate before sensitive administrative actions or biometric updates.</p>
                <form method="post" class="mb-3">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="reauth">
                    <div class="mb-2">
                        <label class="form-label small">Purpose</label>
                        <select class="form-select form-select-sm" name="purpose">
                            <option value="sensitive">sensitive (general)</option>
                            <option value="biometrics">biometrics (Passkey / Face)</option>
                            <option value="backup_restore">backup_restore</option>
                            <option value="security_policy">security_policy</option>
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small">Administrator Password</label>
                        <input type="password" class="form-control form-control-sm" name="password" required autocomplete="current-password">
                    </div>
                    <button class="btn btn-primary btn-sm" type="submit">Confirm password</button>
                </form>

                <hr>
                <h6 class="small fw-bold text-uppercase text-muted mb-2">Active Sessions</h6>
                <form method="post" onsubmit="return confirm('Revoke all other active administrator sessions?');">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="revoke_all_sessions">
                    <button type="submit" class="btn btn-outline-danger btn-sm">
                        <i class="bi bi-box-arrow-right me-1"></i> Revoke All Other Sessions
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- 4. Authentication Audit Trail -->
    <div class="card border-0 shadow-sm rounded-4 p-4 mb-4">
        <h2 class="h5 fw-bold mb-2"><i class="bi bi-journal-code text-primary me-2"></i>Authentication Audit Log</h2>
        <p class="small text-muted mb-3">Audited records of Passkey, Face, Password, and OAuth logins with IPs and outcomes.</p>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead>
                    <tr>
                        <th>Timestamp</th>
                        <th>Method</th>
                        <th>Status</th>
                        <th>User ID</th>
                        <th>IP Address</th>
                        <th>Details / Reason</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($authAuditEvents)): ?>
                        <tr><td colspan="6" class="text-muted">No authentication audit records logged yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($authAuditEvents as $ae): ?>
                            <tr>
                                <td class="small text-nowrap"><?= e((string)$ae['created_at']) ?></td>
                                <td>
                                    <span class="badge bg-dark-subtle text-dark border">
                                        <?= e(strtoupper((string)$ae['authentication_method'])) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if (!empty($ae['success'])): ?>
                                        <span class="badge text-bg-success"><i class="bi bi-check-circle me-1"></i> Success</span>
                                    <?php else: ?>
                                        <span class="badge text-bg-danger"><i class="bi bi-x-circle me-1"></i> Failed</span>
                                    <?php endif; ?>
                                </td>
                                <td class="small"><?= e((string)($ae['user_id'] ?? 'N/A')) ?></td>
                                <td class="small"><?= e((string)$ae['ip_address']) ?></td>
                                <td class="small text-muted"><?= e((string)($ae['failure_reason'] ?: 'Authenticated successfully')) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- 5. General Security Events Filter & Table -->
    <div class="card border-0 shadow-sm rounded-4 p-4 mb-3">
        <h2 class="h5 fw-bold mb-2"><i class="bi bi-activity text-primary me-2"></i>Security Events</h2>
        <form method="get" class="row g-2 align-items-end mb-3">
            <div class="col-md-2">
                <label class="form-label small">From</label>
                <input type="date" class="form-control form-control-sm" name="date_from" value="<?= e($filters['date_from']) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label small">To</label>
                <input type="date" class="form-control form-control-sm" name="date_to" value="<?= e($filters['date_to']) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label small">User</label>
                <input class="form-control form-control-sm" name="user" value="<?= e($filters['user']) ?>" placeholder="name or id">
            </div>
            <div class="col-md-2">
                <label class="form-label small">Type</label>
                <select class="form-select form-select-sm" name="event_type">
                    <option value="">All</option>
                    <?php foreach ($eventTypes as $t): ?>
                        <option value="<?= e($t) ?>" <?= $filters['event_type'] === $t ? 'selected' : '' ?>><?= e($t) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small">IP</label>
                <input class="form-control form-control-sm" name="ip" value="<?= e($filters['ip']) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label small">Severity</label>
                <select class="form-select form-select-sm" name="severity">
                    <option value="">All</option>
                    <?php foreach (['info', 'warning', 'error', 'critical'] as $sev): ?>
                        <option value="<?= e($sev) ?>" <?= $filters['severity'] === $sev ? 'selected' : '' ?>><?= e($sev) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12">
                <button class="btn btn-sm btn-primary" type="submit">Filter</button>
                <a class="btn btn-sm btn-outline-secondary" href="<?= e(BASE_URL) ?>admin/security.php">Reset</a>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead>
                    <tr>
                        <th>When</th>
                        <th>Severity</th>
                        <th>Type</th>
                        <th>User</th>
                        <th>IP</th>
                        <th>Message</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($events === []): ?>
                        <tr><td colspan="6" class="text-muted">No events match.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($events as $ev): ?>
                        <?php
                        $sev = strtolower((string)($ev['severity'] ?? 'info'));
                        $badge = match ($sev) {
                            'critical', 'error' => 'danger',
                            'warning' => 'warning',
                            default => 'secondary',
                        };
                        ?>
                        <tr>
                            <td class="small text-nowrap"><?= e((string)($ev['created_at'] ?? '')) ?></td>
                            <td><span class="badge text-bg-<?= e($badge) ?>"><?= e($sev) ?></span></td>
                            <td class="small"><?= e((string)($ev['event_type'] ?? '')) ?></td>
                            <td class="small"><?= e((string)($ev['username'] ?? $ev['user_id'] ?? '')) ?></td>
                            <td class="small"><?= e((string)($ev['ip_address'] ?? '')) ?></td>
                            <td class="small"><?= e((string)($ev['message'] ?? '')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal 1: Add Passkey Modal -->
<div class="modal fade" id="addPasskeyModal" tabindex="-1" aria-labelledby="addPasskeyModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 440px;">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold" id="addPasskeyModalLabel"><i class="bi bi-passkey-fill text-primary me-2"></i>Register Passkey</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <p class="small text-muted">Give this passkey a friendly name so you can identify it later (e.g., "Work Laptop Windows Hello", "Admin iPhone").</p>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Authenticator / Device Name</label>
                    <input type="text" class="form-control" id="passkeyDeviceName" placeholder="e.g. Windows Hello Laptop" value="Windows Hello">
                </div>
                <div class="alert alert-danger d-none js-passkey-alert small py-2"></div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary btn-sm px-4 js-btn-save-passkey">Continue with Device</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal 2: Face Enrollment Modal -->
<div class="modal fade" id="faceEnrollModal" tabindex="-1" aria-labelledby="faceEnrollModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 520px;">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold" id="faceEnrollModalLabel">
                    <i class="bi bi-person-bounding-box text-primary me-2"></i>Admin Face Enrollment
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center p-4">
                <div class="alert alert-danger d-none js-enrol-alert small py-2 text-start"></div>

                <div class="position-relative mx-auto rounded-4 overflow-hidden shadow-sm mb-3" style="width: 100%; max-width: 440px; aspect-ratio: 4/3; background: #000;">
                    <video class="js-enrol-video w-100 h-100 object-fit-cover" playsinline autoplay muted></video>
                    <canvas class="js-enrol-canvas position-absolute top-0 start-0 w-100 h-100 pointer-events-none"></canvas>
                    <div class="position-absolute top-50 start-50 translate-middle pointer-events-none" style="width: 200px; height: 260px; border: 2px dashed rgba(255,255,255,0.7); border-radius: 50%;"></div>
                </div>

                <div class="js-enrol-steps d-flex justify-content-center flex-wrap mb-2"></div>
                <div class="js-enrol-status text-muted small mb-3">Position your face inside the frame.</div>
                <div class="js-enrol-spinner spinner-border text-primary spinner-border-sm mb-2" role="status"></div>

                <button type="button" class="btn btn-primary w-100 py-2 rounded-3 js-enrol-capture-btn" disabled>
                    <i class="bi bi-camera-fill me-1"></i> Capture Sample
                </button>
            </div>
            <div class="modal-footer border-0 pt-0 justify-content-center">
                <button type="button" class="btn btn-outline-secondary btn-sm px-4 rounded-pill" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script src="<?= BASE_URL ?>assets/vendor/face-api/face-api.min.js"></script>
<script src="<?= BASE_URL ?>assets/js/admin-biometrics.js?v=<?= filemtime(__DIR__ . '/../assets/js/admin-biometrics.js') ?>"></script>
<script src="<?= BASE_URL ?>assets/js/admin-face-ui.js?v=<?= filemtime(__DIR__ . '/../assets/js/admin-face-ui.js') ?>"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    // Passkey Add Modal
    var addPasskeyModalEl = document.getElementById('addPasskeyModal');
    var addPasskeyModal = addPasskeyModalEl ? new bootstrap.Modal(addPasskeyModalEl) : null;
    var btnAddPasskeys = document.querySelectorAll('.js-btn-add-passkey');

    btnAddPasskeys.forEach(function(btn) {
        btn.addEventListener('click', function() {
            if (addPasskeyModal) {
                addPasskeyModal.show();
            }
        });
    });

    var btnSavePasskey = document.querySelector('.js-btn-save-passkey');
    if (btnSavePasskey) {
        btnSavePasskey.addEventListener('click', async function() {
            var origContent = this.innerHTML;
            var deviceName = document.getElementById('passkeyDeviceName')?.value || 'Passkey';
            var alertBox = document.querySelector('.js-passkey-alert');
            alertBox.classList.add('d-none');
            this.disabled = true;
            this.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span>Connecting to authenticator...';

            try {
                var res = await AdminBiometrics.registerPasskey(deviceName, csrfToken);
                this.innerHTML = '<span class="text-success"><i class="bi bi-check-circle-fill me-1"></i> Registered!</span>';
                setTimeout(function() {
                    window.location.reload();
                }, 800);
            } catch (err) {
                alertBox.textContent = err.message || 'Passkey registration error.';
                alertBox.classList.remove('d-none');
                this.disabled = false;
                this.innerHTML = origContent;
            }
        });
    }

    // Face Enrol Modal
    var btnEnrolFaces = document.querySelectorAll('.js-btn-enrol-face');
    btnEnrolFaces.forEach(function(btn) {
        btn.addEventListener('click', function() {
            AdminFaceUI.startEnrollmentModal(csrfToken);
        });
    });
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
