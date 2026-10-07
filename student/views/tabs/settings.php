<?php
declare(strict_types=1);
/**
 * student/views/tabs/settings.php
 * Student profile, social links, phone change, and password.
 */
$studentDisplayName = trim((string)($studentDisplayName ?? $studentProfile['full_name'] ?? ''));
$studentDisplayPhone = (string)($studentDisplayPhone ?? ($student['username'] ?? ''));
$pendingPhoneChange = (string)($pendingPhoneChange ?? '');
$phoneChangeWait = (int)($phoneChangeWait ?? 0);
$otpChannelName = (string)($otpChannelName ?? 'WhatsApp');
$pendingPhoneInput = $pendingPhoneChange !== ''
    ? $pendingPhoneChange
    : (string)($_POST['new_whatsapp'] ?? '');
$socialNetworks = function_exists('student_social_networks') ? student_social_networks() : [];
?>
<div class="row g-4 justify-content-center">
    <div class="col-lg-8">
        <?php if (function_exists('app_theme_render_settings_section')) { app_theme_render_settings_section(); } ?>
        <div class="card border shadow-sm rounded-4 mb-4">
            <div class="card-header bg-transparent py-3 fw-bold d-flex flex-wrap align-items-center justify-content-between gap-2">
                <span><i class="bi bi-person-badge me-2 text-primary"></i>My profile</span>
                <span id="profileSaveStatus" class="small fw-normal text-muted">Saves automatically</span>
            </div>
            <div class="card-body p-4">
                <form method="POST" action="settings.php" id="studentProfileForm" data-autosave="1">
                    <?= csrf_field() ?>
                    <input type="hidden" name="form" value="profile">
                    <input type="hidden" name="ajax" value="0" id="profileAjaxFlag">

                    <h3 class="h6 fw-bold text-uppercase text-muted mb-3">Personal details</h3>
                    <p class="text-muted small">Your name, email, parent contact, and social links save automatically. There is no save button.</p>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" for="full_name">Full name</label>
                            <input class="form-control rounded-3" id="full_name" name="full_name" autocomplete="name"
                                   value="<?= student_e($studentDisplayName) ?>" required minlength="2"
                                   placeholder="Your name as used at college">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" for="email">Email</label>
                            <input class="form-control rounded-3" id="email" name="email" type="email" autocomplete="email"
                                   value="<?= student_e($studentProfile['email'] ?? '') ?>"
                                   placeholder="you@email.com">
                        </div>
                    </div>

                    <h3 class="h6 fw-bold text-uppercase text-muted mb-3">Parent contact</h3>
                    <p class="text-muted small">Evening notes (tomorrow’s classes, fees due, and attendance) are sent to you and this parent number. They can also open the parent view link below — no extra login.</p>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" for="parent_name">Parent name</label>
                            <input class="form-control rounded-3" id="parent_name" name="parent_name"
                                   value="<?= student_e($studentProfile['parent_name'] ?? '') ?>"
                                   placeholder="Parent or guardian name">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" for="parent_whatsapp">Parent WhatsApp</label>
                            <input class="form-control rounded-3" id="parent_whatsapp" name="parent_whatsapp" inputmode="tel"
                                   placeholder="0771234567" required
                                   value="<?= student_e($studentProfile['parent_whatsapp'] ?? '') ?>">
                        </div>
                        <?php if (!empty($parentViewUrl)): ?>
                        <div class="col-12">
                            <label class="form-label fw-semibold" for="parent_view_url">Parent view link</label>
                            <div class="input-group">
                                <input class="form-control rounded-start-3" id="parent_view_url" type="url" readonly
                                       value="<?= student_e($parentViewUrl) ?>">
                                <button class="btn btn-outline-secondary rounded-end-3" type="button" id="copyParentViewUrl">Copy</button>
                            </div>
                            <div class="form-text">Shows today’s classes, this month’s attendance, and fees due. Parents can also sign in at <a href="/parent/login.php">/parent/login.php</a> with this WhatsApp number.</div>
                            <form method="POST" action="settings.php" class="mt-2">
                                <?= csrf_field() ?>
                                <input type="hidden" name="form" value="rotate_parent_link">
                                <button class="btn btn-sm btn-outline-danger" type="submit">Create a new parent link (invalidates the old one)</button>
                            </form>
                        </div>
                        <?php endif; ?>
                    </div>

                    <h3 class="h6 fw-bold text-uppercase text-muted mb-3">Social media</h3>
                    <p class="text-muted small mb-3">Paste a full link or just your username. Leave blank to hide a network.</p>
                    <div class="row g-3 mb-0">
                        <?php foreach ($socialNetworks as $key => $meta): ?>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold" for="social_<?= student_e($key) ?>">
                                    <i class="bi <?= student_e($meta['icon']) ?> me-1"></i><?= student_e($meta['label']) ?>
                                </label>
                                <input class="form-control rounded-3" id="social_<?= student_e($key) ?>" name="<?= student_e($key) ?>"
                                       inputmode="url" autocomplete="url"
                                       placeholder="<?= student_e($meta['placeholder']) ?>"
                                       value="<?= student_e($studentProfile[$key] ?? '') ?>">
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <noscript>
                        <button type="submit" class="btn btn-primary px-4 py-2 fw-bold rounded-3 mt-4">
                            <i class="bi bi-check2 me-1"></i> Save profile
                        </button>
                    </noscript>
                </form>

                <?php if (!empty($pendingParentWa)): ?>
                <div class="alert alert-info mt-3">
                    <p class="mb-2">Enter the 6-digit code sent to parent WhatsApp <strong><?= student_e($pendingParentWa) ?></strong> to confirm the new number.</p>
                    <form method="POST" action="settings.php" class="d-flex flex-wrap gap-2 align-items-end">
                        <?= csrf_field() ?>
                        <input type="hidden" name="form" value="parent_wa_verify">
                        <div>
                            <label class="form-label mb-1" for="parent_wa_otp">Code</label>
                            <input class="form-control" id="parent_wa_otp" name="otp" inputmode="numeric" maxlength="6" required placeholder="------">
                        </div>
                        <button class="btn btn-primary" type="submit">Confirm parent WhatsApp</button>
                    </form>
                    <form method="POST" action="settings.php" class="mt-2">
                        <?= csrf_field() ?>
                        <input type="hidden" name="form" value="parent_wa_cancel">
                        <button class="btn btn-sm btn-outline-secondary" type="submit">Cancel</button>
                    </form>
                </div>
                <?php endif; ?>

                <hr class="my-4">

                <div class="row mb-3">
                    <div class="col-sm-4 text-muted">Mobile / WhatsApp</div>
                    <div class="col-sm-8 fw-semibold"><?= student_e($studentDisplayPhone !== '' ? $studentDisplayPhone : ($student['username'] ?? '')) ?></div>
                </div>
                <div class="row mb-3">
                    <div class="col-sm-4 text-muted">Login username</div>
                    <div class="col-sm-8"><?= student_e($student['username'] ?? '') ?></div>
                </div>
                <div class="row mb-3">
                    <div class="col-sm-4 text-muted">Account Status</div>
                    <div class="col-sm-8">
                        <span class="badge <?= ((int)($student['is_active'] ?? 1) === 1) ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' ?>">
                            <?= ((int)($student['is_active'] ?? 1) === 1) ? 'Active Account' : 'Inactive Account' ?>
                        </span>
                    </div>
                </div>
                <div class="row">
                    <div class="col-sm-4 text-muted"><?= !empty($phoneNumberVerified) && empty($whatsappVerified) ? 'Phone verification' : 'WhatsApp Verification' ?></div>
                    <div class="col-sm-8">
                        <?php if ($whatsappVerified): ?>
                            <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i> WhatsApp verified</span>
                        <?php elseif (!empty($phoneNumberVerified)): ?>
                            <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i> Phone number verified</span>
                            <p class="small text-muted mt-2 mb-0">Your mobile number was verified by SMS. Send a WhatsApp message to the college bot to link WhatsApp reminders.</p>
                        <?php else: ?>
                            <span class="badge bg-warning text-dark"><i class="bi bi-exclamation-triangle me-1"></i> Verification Pending</span>
                            <p class="small text-muted mt-2 mb-0">Send a WhatsApp message to our college bot with this number to complete instant linking.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <?php
        $studentDevices = is_array($studentDevices ?? null) ? $studentDevices : [];
        $studentDeviceLimit = (int)($studentDeviceLimit ?? 2);
        $activeDeviceCount = 0;
        foreach ($studentDevices as $dev) {
            if (!empty($dev['is_active'])) {
                $activeDeviceCount++;
            }
        }
        ?>
        <div class="card border shadow-sm rounded-4 mb-4">
            <div class="card-header bg-transparent py-3 fw-bold">
                <i class="bi bi-phone-fill me-2 text-primary"></i>My Active Devices / Sessions
            </div>
            <div class="card-body p-4">
                <p class="text-muted small">
                    This account can use <strong><?= (int)$studentDeviceLimit ?></strong> devices. Only one stays signed in.
                    Signing in on another device signs the old one out. A replaced device stays blocked for 14 days.
                </p>
                <p class="fw-semibold mb-3"><?= (int)$activeDeviceCount ?> of <?= (int)$studentDeviceLimit ?> devices in use</p>
                <?php if ($studentDevices === []): ?>
                    <p class="text-muted mb-0">This device will be recorded the next time you sign in.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Device</th>
                                    <th>Last used</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($studentDevices as $dev): ?>
                                <?php
                                $active = !empty($dev['is_active']);
                                $current = !empty($dev['is_current']);
                                $when = (string)($dev['last_login_at'] ?? $dev['last_seen_at'] ?? $dev['first_seen_at'] ?? '');
                                $whenLabel = $when !== '' ? date('d M Y, g:i A', strtotime($when)) : '—';
                                ?>
                                <tr class="<?= $active ? '' : 'text-muted' ?>">
                                    <td>
                                        <div class="fw-semibold"><?= student_e((string)($dev['label'] ?? 'Device')) ?></div>
                                        <div class="small">
                                            <?php if ($current && $active): ?>
                                                <span class="badge bg-success-subtle text-success">Active now</span>
                                            <?php elseif ($active): ?>
                                                <span class="badge bg-secondary-subtle text-secondary">Registered</span>
                                            <?php else: ?>
                                                <span class="badge bg-light text-muted">Removed</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="small"><?= student_e($whenLabel) ?></td>
                                    <td class="text-end">
                                        <?php if ($active): ?>
                                            <form method="POST" action="settings.php" class="d-inline" onsubmit="return confirm('Remove this device? You will need an SMS code to use it again.');">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="form" value="revoke_device">
                                                <input type="hidden" name="device_id" value="<?= (int)$dev['id'] ?>">
                                                <button class="btn btn-sm btn-outline-danger rounded-pill" type="submit">Remove</button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <form method="POST" action="settings.php" class="mt-3" onsubmit="return confirm('Sign out every other device? This device stays signed in.');">
                        <?= csrf_field() ?>
                        <input type="hidden" name="form" value="logout_others">
                        <button class="btn btn-outline-primary rounded-pill" type="submit">Log out other devices</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <?php $studentLoginEvents = is_array($studentLoginEvents ?? null) ? $studentLoginEvents : []; ?>
        <?php if ($studentLoginEvents !== []): ?>
        <div class="card border shadow-sm rounded-4 mb-4">
            <div class="card-header bg-transparent py-3 fw-bold">
                <i class="bi bi-clock-history me-2 text-primary"></i>Recent sign-ins
            </div>
            <div class="card-body p-4">
                <p class="text-muted small">If you did not do one of these, change your password and remove unknown devices.</p>
                <ul class="list-unstyled mb-0">
                    <?php foreach ($studentLoginEvents as $ev): ?>
                        <?php
                        $kind = (string)($ev['event_name'] ?? 'login');
                        $kindLabel = $kind === 'new_device' ? 'New device' : ($kind === 'presence' ? 'Class confirmation' : 'Sign-in');
                        $when = (string)($ev['created_at'] ?? '');
                        $whenLabel = $when !== '' ? date('d M Y, g:i A', strtotime($when)) : '—';
                        ?>
                        <li class="d-flex justify-content-between gap-3 py-2 border-bottom">
                            <span>
                                <span class="fw-semibold"><?= student_e($kindLabel) ?></span>
                                <span class="text-muted"> · <?= student_e((string)($ev['device_label'] ?? 'Device')) ?></span>
                            </span>
                            <span class="small text-muted text-nowrap"><?= student_e($whenLabel) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
        <?php endif; ?>

        <?php $studentSecurityHistory = is_array($studentSecurityHistory ?? null) ? $studentSecurityHistory : []; ?>
        <div class="card border shadow-sm rounded-4 mb-4">
            <div class="card-header bg-transparent py-3 fw-bold">My Security History</div>
            <div class="card-body p-4">
                <p class="text-muted small">Your own device and class-access activity. This list does not include other students.</p>
                <?php if ($studentSecurityHistory === []): ?>
                    <p class="text-muted mb-0">No security events yet.</p>
                <?php else: ?>
                    <ul class="list-unstyled mb-0">
                        <?php foreach ($studentSecurityHistory as $ev): ?>
                            <?php $when = (string)($ev['created_at'] ?? ''); ?>
                            <li class="d-flex justify-content-between gap-3 py-2 border-bottom">
                                <span class="fw-semibold"><?= student_e(\Edexcel\Services\SecurityEventService::studentLabel((string)($ev['event_code'] ?? ''))) ?></span>
                                <span class="small text-muted text-nowrap"><?= student_e($when !== '' ? date('d M Y, g:i A', strtotime($when)) : '—') ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>

        <div class="card border shadow-sm rounded-4 mb-4">
            <div class="card-header bg-transparent py-3 fw-bold">
                <i class="bi bi-phone me-2 text-primary"></i>Change mobile / WhatsApp number
            </div>
            <div class="card-body p-4">
                <p class="text-muted small">
                    This number is your student login. We will send a <?= student_e($otpChannelName) ?> OTP to the <strong>new</strong> number before the change is saved.
                </p>

                <?php if ($pendingPhoneChange !== ''): ?>
                    <div class="alert alert-info">
                        Enter the 6-digit code sent by <?= student_e($otpChannelName) ?> to
                        <strong><?= student_e(function_exists('campus_display_phone') ? campus_display_phone($pendingPhoneChange) : $pendingPhoneChange) ?></strong>.
                    </div>
                    <form method="POST" action="settings.php" class="mb-3">
                        <?= csrf_field() ?>
                        <input type="hidden" name="form" value="phone_verify">
                        <input type="hidden" name="new_whatsapp" value="<?= student_e($pendingPhoneChange) ?>">
                        <div class="mb-3">
                            <label class="form-label fw-semibold" for="phone_otp">Verification code</label>
                            <input class="form-control rounded-3" id="phone_otp" name="otp" inputmode="numeric"
                                   maxlength="6" autocomplete="one-time-code" placeholder="123456" required autofocus>
                        </div>
                        <button type="submit" class="btn btn-primary rounded-3">
                            <i class="bi bi-check2 me-1"></i> Confirm new number
                        </button>
                    </form>
                    <div class="d-flex flex-wrap gap-2">
                        <form method="POST" action="settings.php">
                            <?= csrf_field() ?>
                            <input type="hidden" name="form" value="phone_resend">
                            <input type="hidden" name="new_whatsapp" value="<?= student_e($pendingPhoneChange) ?>">
                            <button class="btn btn-outline-secondary rounded-3" type="submit" id="phoneOtpResendBtn"
                                    data-wait="<?= $phoneChangeWait ?>" <?= $phoneChangeWait > 0 ? 'disabled' : '' ?>>
                                <?= $phoneChangeWait > 0 ? 'Resend code in ' . $phoneChangeWait . 's' : 'Resend code' ?>
                            </button>
                        </form>
                        <form method="POST" action="settings.php">
                            <?= csrf_field() ?>
                            <input type="hidden" name="form" value="phone_cancel">
                            <button class="btn btn-link text-muted" type="submit">Cancel</button>
                        </form>
                    </div>
                <?php else: ?>
                    <form method="POST" action="settings.php">
                        <?= csrf_field() ?>
                        <input type="hidden" name="form" value="phone_request">
                        <div class="mb-3">
                            <label class="form-label fw-semibold" for="new_whatsapp">New mobile number</label>
                            <input class="form-control rounded-3" id="new_whatsapp" name="new_whatsapp" inputmode="tel"
                                   autocomplete="tel" placeholder="0771234567" value="<?= student_e($pendingPhoneInput) ?>" required>
                            <div class="form-text">Sri Lankan mobile, e.g. 0771234567. You can save directly or verify with an OTP.</div>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="submit" name="phone_action" value="save_direct" class="btn btn-primary rounded-3">
                                Save number directly
                            </button>
                            <button type="submit" name="phone_action" value="send_otp" class="btn btn-outline-secondary rounded-3">
                                Send <?= student_e($otpChannelName) ?> OTP
                            </button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <div class="card border shadow-sm rounded-4 mb-4">
            <div class="card-header bg-transparent py-3 fw-bold">
                <i class="bi bi-stars me-2 text-primary"></i>Talk with AI
            </div>
            <div class="card-body p-4">
                <p class="text-muted mb-3">Study reminders, AI style, difficulty, and layout live with Talk with AI so the assistant can remember them.</p>
                <a class="btn btn-outline-primary rounded-pill" href="dashboard.php?tab=courso#prefs">Open AI preferences</a>
            </div>
        <!-- Face ID Authentication Card -->
        <div class="card border shadow-sm rounded-4 mb-4" id="studentFaceCard">
            <div class="card-header bg-transparent py-3 d-flex align-items-center justify-content-between">
                <div class="fw-bold">
                    <i class="bi bi-person-bounding-box me-2 text-primary"></i>Face ID Sign-In
                </div>
                <span class="badge rounded-pill <?= !empty($studentFaceEnrolled) ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' ?> px-3 py-2">
                    <i class="bi <?= !empty($studentFaceEnrolled) ? 'bi-check-circle-fill' : 'bi-dash-circle' ?> me-1"></i>
                    <?= !empty($studentFaceEnrolled) ? 'Active & Enrolled' : 'Not Enrolled' ?>
                </span>
            </div>
            <div class="card-body p-4">
                <p class="text-muted small mb-3">
                    Sign in with your face in seconds using your device camera. Your facial template is converted into mathematical descriptors and encrypted with AES-256-GCM. Raw photos and video streams are never stored on our servers.
                </p>

                <?php if (!empty($studentFaceEnrolled)): ?>
                    <div class="alert alert-success-subtle border-success-subtle text-success-emphasis small py-2 px-3 rounded-3 mb-3 d-flex align-items-center gap-2">
                        <i class="bi bi-shield-check fs-5"></i>
                        <div>
                            <strong>Face ID is active on your account.</strong> You can use the "Sign in with Face ID" button on any portal login page.
                            <?php if (!empty($studentFaceInfo['enrolled_at'])): ?>
                                <div class="text-muted" style="font-size:0.75rem;">Enrolled: <?= htmlspecialchars((string)$studentFaceInfo['enrolled_at']) ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="d-flex flex-wrap align-items-center gap-2">
                    <button type="button" class="btn btn-primary rounded-3 px-3 py-2 fw-semibold js-btn-enrol-face" id="btnStudentSettingsEnrollFace" data-consent="1">
                        <i class="bi bi-camera-fill me-1"></i> <?= !empty($studentFaceEnrolled) ? 'Re-enroll Face ID' : 'Enroll Face ID' ?>
                    </button>
                    <?php if (!empty($studentFaceEnrolled)): ?>
                        <button type="button" class="btn btn-outline-danger rounded-3 px-3 py-2 fw-semibold" id="btnStudentSettingsDisableFace">
                            <i class="bi bi-trash me-1"></i> Remove Face ID
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="card border shadow-sm rounded-4">
            <div class="card-header bg-transparent py-3 fw-bold">
                <i class="bi bi-shield-lock me-2 text-primary"></i>Change Password
            </div>
            <div class="card-body p-4">
                <form method="POST" action="settings.php">
                    <?= csrf_field() ?>
                    <input type="hidden" name="form" value="password">

                    <div class="mb-3">
                        <label for="current_password" class="form-label fw-semibold">Current Password</label>
                        <input type="password" class="form-control rounded-3" id="current_password" name="current_password" required>
                    </div>

                    <div class="mb-3">
                        <label for="new_password" class="form-label fw-semibold">New Password</label>
                        <input type="password" class="form-control rounded-3" id="new_password" name="new_password" minlength="8" required>
                        <div class="form-text">Must be at least 8 characters.</div>
                    </div>

                    <div class="mb-4">
                        <label for="confirm_password" class="form-label fw-semibold">Confirm New Password</label>
                        <input type="password" class="form-control rounded-3" id="confirm_password" name="confirm_password" minlength="8" required>
                    </div>

                    <button type="submit" class="btn btn-primary px-4 py-2 fw-bold rounded-3">
                        <i class="bi bi-check2 me-1"></i> Update Password
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
<script>
(function () {
    var btn = document.getElementById('phoneOtpResendBtn');
    if (btn) {
        var wait = parseInt(btn.getAttribute('data-wait') || '0', 10);
        var tick = function () {
            if (wait <= 0) {
                btn.disabled = false;
                btn.textContent = 'Resend code';
                return;
            }
            btn.disabled = true;
            btn.textContent = 'Resend code in ' + wait + 's';
            wait -= 1;
            setTimeout(tick, 1000);
        };
        if (wait > 0) tick();
    }

    var form = document.getElementById('studentProfileForm');
    if (!form || document.getElementById('parentPhoneGate')) return;
    var statusEl = document.getElementById('profileSaveStatus');
    var ajaxFlag = document.getElementById('profileAjaxFlag');
    var timer = null;
    var inflight = null;
    var lastSaved = '';
    var queued = false;

    var setStatus = function (text, kind) {
        if (!statusEl) return;
        statusEl.textContent = text;
        statusEl.classList.remove('text-muted', 'text-success', 'text-danger', 'text-primary');
        statusEl.classList.add(kind === 'ok' ? 'text-success' : kind === 'err' ? 'text-danger' : kind === 'busy' ? 'text-primary' : 'text-muted');
    };

    var snapshot = function () {
        return new URLSearchParams(new FormData(form)).toString();
    };

    var isReady = function (strict) {
        var name = (form.full_name && form.full_name.value || '').trim();
        if (name.length < 2) {
            setStatus(strict ? 'Enter your full name' : 'Keep typing…', strict ? 'err' : '');
            return false;
        }
        var email = (form.email && form.email.value || '').trim();
        if (email !== '' && email.indexOf('@') < 1) {
            setStatus(strict ? 'Finish the email address' : 'Keep typing…', strict ? 'err' : '');
            return false;
        }
        var parent = (form.parent_whatsapp && form.parent_whatsapp.value || '').replace(/\D/g, '');
        if (parent.indexOf('0') === 0 && parent.length === 10) parent = '94' + parent.slice(1);
        if (/^7\d{8}$/.test(parent)) parent = '94' + parent;
        if (parent !== '' && !/^(?:947\d{8}|[1-9]\d{7,14})$/.test(parent)) {
            setStatus(strict ? 'Enter a valid parent WhatsApp number' : 'Keep typing…', strict ? 'err' : '');
            return false;
        }
        return true;
    };

    var updateHero = function (data) {
        var title = document.getElementById('studentWelcomeTitle');
        if (title && data.display_name) {
            title.textContent = 'Welcome back, ' + data.display_name;
        }
        var initials = document.getElementById('studentAvatarInitials');
        if (initials && data.initials) {
            initials.textContent = data.initials;
        }
        var wrap = document.getElementById('studentSocialLinks');
        if (!wrap) return;
        wrap.innerHTML = '';
        var links = data.social || [];
        if (!links.length) {
            wrap.classList.add('d-none');
            return;
        }
        wrap.classList.remove('d-none');
        links.forEach(function (link) {
            var a = document.createElement('a');
            a.className = 'btn btn-sm btn-outline-secondary rounded-pill';
            a.href = link.url;
            a.target = '_blank';
            a.rel = 'noopener noreferrer';
            a.title = link.label || '';
            a.setAttribute('aria-label', link.label || '');
            a.innerHTML = '<i class="bi ' + (link.icon || 'bi-link-45deg') + '"></i>';
            wrap.appendChild(a);
        });
    };

    var applyNormalized = function (sent, profile) {
        if (!profile) return;
        Object.keys(profile).forEach(function (key) {
            var input = form.querySelector('[name="' + key + '"]');
            if (!input || input === document.activeElement) return;
            var current = (input.value || '').trim();
            var was = (sent[key] || '').trim();
            var next = (profile[key] || '').trim();
            if (current === was && next && next !== current) {
                input.value = next;
            }
        });
    };

    var save = function (strict) {
        if (!isReady(!!strict)) return;
        var sentParams = new URLSearchParams(new FormData(form));
        var key = sentParams.toString();
        if (key === lastSaved) {
            setStatus('Saved', 'ok');
            return;
        }
        if (inflight) {
            queued = true;
            return;
        }
        if (ajaxFlag) ajaxFlag.value = '1';
        sentParams.set('ajax', '1');
        setStatus('Saving…', 'busy');
        inflight = fetch(form.getAttribute('action') || 'settings.php', {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            },
            body: sentParams,
            credentials: 'same-origin'
        }).then(function (res) {
            return res.json().then(function (data) {
                return { okHttp: res.ok, data: data || {} };
            }).catch(function () {
                return { okHttp: res.ok, data: { ok: false, message: 'Could not save right now.' } };
            });
        }).then(function (result) {
            inflight = null;
            var data = result.data;
            if (!result.okHttp || !data.ok) {
                setStatus(data.message || 'Could not save', 'err');
                return;
            }
            setStatus('Saved', 'ok');
            applyNormalized(Object.fromEntries(sentParams.entries()), data.profile || {});
            updateHero(data);
            lastSaved = snapshot();
            if (queued) {
                queued = false;
                save(false);
            }
        }).catch(function () {
            inflight = null;
            setStatus('Could not save right now', 'err');
        }).finally(function () {
            if (ajaxFlag) ajaxFlag.value = '0';
        });
    };

    var schedule = function () {
        if (timer) clearTimeout(timer);
        timer = setTimeout(function () { save(false); }, 700);
    };

    var copyBtn = document.getElementById('copyParentViewUrl');
    var copyInput = document.getElementById('parent_view_url');
    if (copyBtn && copyInput) {
        copyBtn.addEventListener('click', function () {
            var value = copyInput.value || '';
            var done = function () {
                copyBtn.textContent = 'Copied';
                setTimeout(function () { copyBtn.textContent = 'Copy'; }, 1600);
            };
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(value).then(done).catch(function () {
                    copyInput.select();
                    try { document.execCommand('copy'); } catch (err) {}
                    done();
                });
                return;
            }
            copyInput.select();
            try { document.execCommand('copy'); } catch (err) {}
            done();
        });
    }

    lastSaved = snapshot();
    form.addEventListener('input', schedule);
    form.addEventListener('change', function () {
        if (timer) clearTimeout(timer);
        save(true);
    });
    form.addEventListener('submit', function (e) {
        e.preventDefault();
        if (timer) clearTimeout(timer);
        save(true);
    });
    window.addEventListener('pagehide', function () {
        if (!isReady(false)) return;
        var now = snapshot();
        if (now === lastSaved) return;
        if (ajaxFlag) ajaxFlag.value = '1';
        try {
            navigator.sendBeacon(form.getAttribute('action') || 'settings.php', new FormData(form));
        } catch (err) {}
    });
})();
</script>

<!-- Face ID Enrollment Modal -->
<div class="modal fade" id="faceEnrollModal" tabindex="-1" aria-labelledby="faceEnrollModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 520px;">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold" id="faceEnrollModalLabel">
                    <i class="bi bi-person-bounding-box text-primary me-2"></i>Face ID Enrollment
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center p-4">
                <div class="alert alert-danger d-none js-enrol-alert small py-2 text-start" data-ui-keep="1"></div>

                <div class="position-relative mx-auto rounded-4 overflow-hidden shadow-sm" style="width: 100%; max-width: 440px; aspect-ratio: 4/3; background: #000;">
                    <video class="js-enrol-video w-100 h-100 object-fit-cover" playsinline autoplay muted></video>
                    <canvas class="js-enrol-canvas position-absolute top-0 start-0 w-100 h-100" style="pointer-events:none;"></canvas>
                    <div class="position-absolute top-50 start-50 translate-middle" style="width: 210px; height: 270px; border: 2px dashed rgba(255,255,255,0.35); border-radius: 50%; pointer-events:none;"></div>
                </div>

                <div class="js-enrol-steps d-flex justify-content-center flex-wrap mb-2"></div>
                <div class="js-enrol-status text-muted small mb-2">Position your face inside the frame.</div>
                <div class="js-enrol-spinner spinner-border text-primary spinner-border-sm mb-2" role="status"></div>

                <!-- Automatic capture indicator & hold progress pill -->
                <div class="card border-0 bg-light p-2 mb-2 rounded-3 shadow-sm js-enrol-auto-pill text-start" data-ui-keep="1">
                    <div class="d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center gap-2 text-start ps-1">
                            <span class="js-enrol-status-dot spinner-grow spinner-grow-sm text-primary" role="status" style="width: 0.85rem; height: 0.85rem;"></span>
                            <div>
                                <div class="small fw-bold text-dark js-enrol-auto-title">Detecting face...</div>
                                <div class="text-muted js-enrol-auto-subtitle" style="font-size: 0.75rem;">Center face inside oval</div>
                            </div>
                        </div>
                        <div class="pe-1 text-end" style="min-width: 95px;">
                            <div class="progress" style="height: 7px; width: 90px; background-color: #e2e8f0; border-radius: 4px;">
                                <div class="progress-bar progress-bar-striped progress-bar-animated bg-success js-enrol-hold-bar" role="progressbar" style="width: 0%; transition: width 0.15s ease;"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <button type="button" class="btn btn-outline-primary btn-sm px-4 rounded-pill js-enrol-capture-btn" disabled>
                    <i class="bi bi-camera me-1"></i> Capture Pose
                </button>
            </div>
            <div class="modal-footer border-0 pt-0 justify-content-center">
                <button type="button" class="btn btn-outline-secondary btn-sm px-4 rounded-pill" data-bs-dismiss="modal">Cancel</button>
            </div>
        </div>
    </div>
</div>

<script src="<?= BASE_URL ?>assets/vendor/face-api/face-api.min.js"></script>
<script src="<?= BASE_URL ?>assets/js/admin-biometrics.js?v=<?= filemtime(__DIR__ . '/../../../assets/js/admin-biometrics.js') ?>"></script>
<script src="<?= BASE_URL ?>assets/js/admin-face-ui.js?v=<?= filemtime(__DIR__ . '/../../../assets/js/admin-face-ui.js') ?>"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var enrollBtn = document.getElementById('btnStudentSettingsEnrollFace');
    if (enrollBtn) {
        enrollBtn.addEventListener('click', function() {
            var csrf = '<?= function_exists('generate_csrf_token') ? generate_csrf_token() : '' ?>';
            if (window.AdminFaceUI) {
                window.AdminFaceUI.startEnrollmentModal(csrf, this);
            }
        });
    }

    var disableBtn = document.getElementById('btnStudentSettingsDisableFace');
    if (disableBtn) {
        disableBtn.addEventListener('click', async function() {
            if (!confirm('Are you sure you want to remove Face ID from your account?')) return;
            var csrf = '<?= function_exists('generate_csrf_token') ? generate_csrf_token() : '' ?>';
            this.disabled = true;
            try {
                var res = await fetch('/ajax/admin_biometrics.php?action=face_disable', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-Token': csrf },
                    body: JSON.stringify({ csrf_token: csrf })
                });
                var data = await res.json();
                if (data.ok) {
                    alert('Face ID removed successfully.');
                    window.location.reload();
                } else {
                    alert(data.error || 'Could not remove Face ID.');
                    this.disabled = false;
                }
            } catch (e) {
                alert('Connection error.');
                this.disabled = false;
            }
        });
    }
});
</script>
