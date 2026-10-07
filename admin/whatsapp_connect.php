<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/evolution.php';
require_once __DIR__ . '/../config/whatsapp_gateway.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Edexcel\Services\MetaEmbeddedSignupService;

require_admin();

$error = '';
$success = '';
$service = new MetaEmbeddedSignupService($pdo);
$service->ensureWebhookVerifyToken();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token.';
    } elseif (($_POST['action'] ?? '') === 'save_app') {
        try {
            $service->saveAppCredentials(
                (string)($_POST['meta_app_id'] ?? ''),
                (string)($_POST['meta_app_secret'] ?? ''),
                (string)($_POST['meta_embedded_signup_config_id'] ?? ''),
                (string)($_POST['meta_graph_version'] ?? '')
            );
            log_audit($pdo, 'whatsapp_connect_app', 'settings', null, null, [
                'meta_app_id' => preg_replace('/\D+/', '', (string)($_POST['meta_app_id'] ?? '')),
            ]);
            $success = 'App secret matches this App ID. You can connect WhatsApp now.';
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}

$status = MetaEmbeddedSignupService::publicStatus($pdo);
$fbVersion = preg_match('/^v\d+\.\d+$/', $status['graph_version']) ? $status['graph_version'] : 'v21.0';
$sdkVersion = $fbVersion;
if (preg_match('/^v(\d+)\./', $fbVersion, $m) && (int)$m[1] < 25) {
    $sdkVersion = 'v25.0';
}
$oauthRedirect = rtrim(edexcel_public_app_url(), '/') . '/admin/whatsapp_connect.php';

if (isset($_GET['error'])) {
    $error = 'Facebook login was cancelled or denied.';
}

include __DIR__ . '/../includes/header.php';
?>
<div class="container-fluid py-4">
    <div class="mb-4">
        <h1><i class="bi bi-whatsapp text-success"></i> Connect WhatsApp</h1>
        <p class="text-muted mb-0">
            Create a new Meta app, save its App ID / secret / Embedded Signup config ID below, then connect the college WhatsApp number.
            Inbound messages still hit
            <a href="<?= htmlspecialchars(BASE_URL . 'admin/whatsapp_bot.php') ?>">Messages</a>
            and the chatbot.
        </p>
    </div>

    <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <?php if ($status['connected'] && empty($status['needs_register'])): ?>
        <div class="alert alert-success d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <strong>Connected.</strong>
                <?= $status['display_phone'] !== '' ? 'Number ' . htmlspecialchars($status['display_phone']) . '. ' : '' ?>
                Provider is Meta Cloud API<?= $status['onboarding_mode'] === 'coexistence' ? ' (coexistence)' : '' ?>.
                <?php if ($status['is_on_biz_app'] === '1' && strtoupper((string)$status['platform_type']) === 'CLOUD_API'): ?>
                    WhatsApp Business app and Cloud API are both active.
                <?php endif; ?>
            </div>
            <button type="button" class="btn btn-outline-danger btn-sm rounded-pill" id="wa-disconnect-btn">Disconnect</button>
        </div>
    <?php elseif ($status['connected']): ?>
        <div class="alert alert-warning d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <strong>Linked, but Cloud API cannot send yet.</strong>
                Number <?= $status['display_phone'] !== '' ? htmlspecialchars($status['display_phone']) : '' ?>
                is not registered. Paste a token from WhatsApp → API Setup for the number you added to this new app, or click Connect WhatsApp.
            </div>
            <button type="button" class="btn btn-outline-danger btn-sm rounded-pill" id="wa-disconnect-btn">Disconnect</button>
        </div>
    <?php endif; ?>

    <?php if (empty($status['connected'])): ?>
        <div class="card shadow-sm border-0 rounded-4 mb-4">
            <div class="card-body p-4">
                <h4 class="mb-2"><i class="bi bi-key"></i> Optional: paste API Setup token</h4>
                <p class="mb-2">
                    After the new app is Live and the college number is in WhatsApp → API Setup, generate an access token and paste it here.
                </p>
                <p class="small mb-2">Open
                    <a href="https://developers.facebook.com/apps/<?= htmlspecialchars($status['app_id']) ?>/whatsapp-business/wa-dev-console/" target="_blank" rel="noopener">WhatsApp → API Setup</a>,
                    select your WABA, generate an access token, then paste it below.
                </p>
                <div class="row g-2 align-items-end">
                    <div class="col-md-8">
                        <label class="form-label small" for="wa-manual-token">WhatsApp access token from API Setup</label>
                        <input class="form-control" type="password" id="wa-manual-token" autocomplete="off" placeholder="Starts with EAA…">
                    </div>
                    <div class="col-md-4">
                        <button type="button" class="btn btn-outline-primary rounded-pill" id="wa-save-token-btn">Save API token</button>
                    </div>
                </div>
                <p class="small text-muted mb-0 mt-2" id="wa-token-status"></p>
            </div>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card shadow-sm border-0 rounded-4 mb-4">
                <div class="card-body p-4">
                    <h4 class="mb-2">1. Meta app (once)</h4>
                    <p class="text-muted small">
                        Meta still requires a Live app, Facebook Login for Business, and Tech Provider or Solution Partner status.
                        This page cannot skip those console steps. After they exist, paste the IDs here.
                    </p>
                    <ol class="small text-muted">
                        <li>Create or open the app in <a href="https://developers.facebook.com/apps/" target="_blank" rel="noopener">Meta for Developers</a> and add WhatsApp.</li>
                        <li>
                            Under Facebook Login for Business → Configurations, the ID you paste must be a <strong>WhatsApp Embedded Signup</strong> configuration, not a generic Facebook Login configuration. Generic login is why the popup closes after Facebook login.
                        </li>
                        <li>
                            In <a href="https://developers.facebook.com/apps/<?= htmlspecialchars($status['app_id'] !== '' ? $status['app_id'] : '') ?>/settings/basic/" target="_blank" rel="noopener">App settings → Basic</a>, App Domains will not save until a Website platform exists. Do this order:
                            <ol class="mt-1">
                                <li>Privacy Policy URL: <code><?= htmlspecialchars(rtrim(edexcel_public_app_url(), '/')) ?>/privacy-policy</code></li>
                                <li>Terms of Service URL: <code><?= htmlspecialchars(rtrim(edexcel_public_app_url(), '/')) ?>/terms</code></li>
                                <li>User data deletion URL: <code><?= htmlspecialchars(rtrim(edexcel_public_app_url(), '/')) ?>/data-deletion.php</code></li>
                                <li>Scroll to <strong>Add platform</strong> → Website → Site URL <code><?= htmlspecialchars(rtrim(edexcel_public_app_url(), '/')) ?>/</code></li>
                                <li>Click <strong>Save changes</strong></li>
                                <li>App Domains: type only <code><?= htmlspecialchars(parse_url(rtrim(edexcel_public_app_url(), '/') . '/', PHP_URL_HOST) ?: 'edexcel.college') ?></code> (no https://, no /), press <strong>Enter</strong> until it becomes a chip, then Save again</li>
                            </ol>
                        </li>
                        <li>In Facebook Login → Settings, keep <strong>Login with the JavaScript SDK</strong> on Yes. Leave Client OAuth and Web OAuth as No (they are locked). Keep Valid OAuth Redirect URI <code><?= htmlspecialchars($oauthRedirect) ?></code> and Allowed Domains <code><?= htmlspecialchars(rtrim(edexcel_public_app_url(), '/')) ?>/</code>.</li>
                        <li>Subscribe the app webhook to <code>messages</code>, <code>history</code>, <code>smb_app_state_sync</code>, and <code>smb_message_echoes</code>.</li>
                        <li>Callback URL: <code><?= htmlspecialchars($status['webhook_url']) ?></code><?php if ($status['verify_token'] !== ''): ?> — verify token is saved below.<?php endif; ?></li>
                        <li>On the phone, WhatsApp Business app 2.24.17 or newer.</li>
                    </ol>
                    <form method="post" class="row g-3">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="save_app">
                        <div class="col-md-6">
                            <label class="form-label" for="meta_app_id">App ID</label>
                            <input class="form-control" id="meta_app_id" name="meta_app_id" value="<?= htmlspecialchars($status['app_id']) ?>" required inputmode="numeric" autocomplete="off">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="meta_embedded_signup_config_id">Embedded Signup config ID</label>
                            <input class="form-control" id="meta_embedded_signup_config_id" name="meta_embedded_signup_config_id" value="<?= htmlspecialchars($status['config_id']) ?>" required inputmode="numeric" autocomplete="off">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="meta_app_secret">App secret</label>
                            <input class="form-control" type="password" id="meta_app_secret" name="meta_app_secret" value="" placeholder="<?= $status['has_app_secret'] ? 'Paste a fresh secret to replace the saved one' : 'From App Settings → Basic' ?>" autocomplete="new-password">
                            <small class="text-muted">
                                From <strong>App settings → Basic → App secret → Show</strong> for App ID <?= htmlspecialchars($status['app_id'] !== '' ? $status['app_id'] : '…') ?>.
                                Not the webhook verify token.
                                <?php if (!empty($status['app_secret_length'])): ?>
                                    Saved value is <?= (int)$status['app_secret_length'] ?> characters; Meta’s App Secret is usually 32.
                                <?php endif; ?>
                            </small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="meta_graph_version">Graph API version</label>
                            <input class="form-control" id="meta_graph_version" name="meta_graph_version" value="<?= htmlspecialchars($status['graph_version'] ?: 'v21.0') ?>">
                        </div>
                        <div class="col-12">
                            <button class="btn btn-outline-primary rounded-pill">Save app details</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card shadow-sm border-0 rounded-4">
                <div class="card-body p-4">
                    <h4 class="mb-2">2. Connect existing WhatsApp Business app</h4>
                    <p class="text-muted small mb-3">
                        This opens Meta Embedded Signup. Choose the existing WhatsApp Business app, complete QR or pairing on the phone,
                        then we store that WABA and phone number, subscribe Cloud API, and point traffic at your PHP webhook.
                    </p>
                    <p class="small mb-3" id="wa-connect-status">
                        <?php if (!$status['ready']): ?>
                            Save App ID, App Secret, and configuration ID first.
                        <?php elseif (!empty($status['needs_register'])): ?>
                            Tokens are saved, but this number is not registered for sending. Paste an API Setup token.
                        <?php elseif ($status['connected']): ?>
                            Already connected. Connect again only if you need to re-pair.
                        <?php else: ?>
                            Ready. Connect the college WhatsApp Business number.
                        <?php endif; ?>
                    </p>
                    <div class="row g-2 mb-3">
                        <div class="col-md-4">
                            <label class="form-label small" for="wa-waba-id">WABA ID</label>
                            <input class="form-control" id="wa-waba-id" inputmode="numeric" autocomplete="off" value="<?= htmlspecialchars($status['waba_id']) ?>" placeholder="From API Setup">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small" for="wa-phone-id">Phone number ID</label>
                            <input class="form-control" id="wa-phone-id" inputmode="numeric" autocomplete="off" value="<?= htmlspecialchars($status['phone_number_id']) ?>" placeholder="From API Setup">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small" for="wa-business-id">Business portfolio ID</label>
                            <input class="form-control" id="wa-business-id" inputmode="numeric" autocomplete="off" value="" placeholder="Meta Business ID">
                        </div>
                    </div>
                    <p class="small text-muted">
                        Leave WABA and phone number ID blank if you will finish Embedded Signup / QR. If the Facebook window closes at login, paste an API Setup token instead.
                    </p>
                    <button type="button" class="btn btn-success rounded-pill px-4" id="wa-connect-btn" <?= $status['ready'] ? '' : 'disabled' ?>>
                        <i class="bi bi-whatsapp"></i> Connect WhatsApp
                    </button>
                    <button type="button" class="btn btn-outline-success rounded-pill px-4" id="wa-connect-qr-btn" <?= $status['ready'] ? '' : 'disabled' ?>>
                        Connect with QR (Business app)
                    </button>
                    <button type="button" class="btn btn-outline-success rounded-pill px-4 d-none" id="wa-finish-pasted-btn">
                        Finish with saved IDs
                    </button>
                    <a class="btn btn-outline-secondary rounded-pill ms-2" href="<?= htmlspecialchars(BASE_URL . 'admin/whatsapp_bot.php') ?>">Back to Messages</a>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card shadow-sm border-0 rounded-4">
                <div class="card-body p-4">
                    <h4>What happens</h4>
                    <ol class="small mb-0">
                        <li>This website</li>
                        <li>Connect WhatsApp</li>
                        <li>Meta Embedded Signup</li>
                        <li>Existing WhatsApp Business app</li>
                        <li>College WhatsApp Business number</li>
                        <li>WABA from the new Meta app</li>
                        <li>Cloud API</li>
                        <li>PHP webhook <code>/api/whatsapp/webhook.php</code></li>
                        <li>Chatbot</li>
                    </ol>
                    <hr>
                    <p class="small text-muted mb-1">Webhook verify token — paste this into Meta’s Verify token box, then click Verify and save</p>
                    <code><?= htmlspecialchars($status['verify_token'] !== '' ? $status['verify_token'] : 'Open this page again after Save app details') ?></code>
                    <?php if ($status['waba_id'] !== ''): ?>
                        <hr>
                        <p class="small mb-1"><strong>WABA ID</strong><br><code><?= htmlspecialchars($status['waba_id']) ?></code></p>
                        <p class="small mb-0"><strong>Phone number ID</strong><br><code><?= htmlspecialchars($status['phone_number_id']) ?></code></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
window.fbAsyncInit = function () {
    var cfg = window.__waEsCfg;
    if (!cfg || !cfg.appId || typeof FB === 'undefined') return;
    FB.init({
        appId: cfg.appId,
        autoLogAppEvents: true,
        xfbml: false,
        version: cfg.sdkVersion
    });
    cfg.sdkReady = true;
};
</script>
<script async defer crossorigin="anonymous" src="https://connect.facebook.net/en_US/sdk.js"></script>
<script>
(function () {
    var cfg = {
        appId: <?= json_encode($status['app_id']) ?>,
        configId: <?= json_encode($status['config_id']) ?>,
        sdkVersion: <?= json_encode($sdkVersion) ?>,
        ready: <?= $status['ready'] ? 'true' : 'false' ?>,
        endpoint: <?= json_encode(BASE_URL . 'ajax/meta_embedded_signup.php') ?>,
        csrf: <?= json_encode(csrf_token()) ?>,
        redirectUri: <?= json_encode($oauthRedirect) ?>,
        sdkReady: false
    };
    window.__waEsCfg = cfg;
    var sessionInfo = { waba_id: '', phone_number_id: '', business_id: '' };
    var sessionReady = false;
    var capturedRedirectUri = '';
    var pendingCode = '';
    var finishPastedBtn = document.getElementById('wa-finish-pasted-btn');

    function rememberRedirectUri(value) {
        if (!value || typeof value !== 'string') return;
        capturedRedirectUri = value;
    }

    function captureFromForm(form) {
        if (!form) return;
        var action = String(form.action || '');
        if (!/facebook\.com|fbcdn\.net|oauth\.facebook/i.test(action)) return;
        var nodes = form.querySelectorAll('input[name="redirect_uri"], input[name="fallback_redirect_uri"]');
        for (var i = 0; i < nodes.length; i += 1) {
            if (nodes[i].name === 'redirect_uri' && nodes[i].value) {
                rememberRedirectUri(nodes[i].value);
                return;
            }
        }
        for (var j = 0; j < nodes.length; j += 1) {
            if (nodes[j].value) rememberRedirectUri(nodes[j].value);
        }
    }

    (function hookOpenAndForms() {
        if (window.__waOpenHooked) return;
        window.__waOpenHooked = true;
        var origOpen = window.open;
        window.open = function (url) {
            try {
                if (typeof url === 'string' && /facebook\.com|fbcdn\.net/i.test(url)) {
                    var u = new URL(url, window.location.href);
                    rememberRedirectUri(u.searchParams.get('redirect_uri'));
                    rememberRedirectUri(u.searchParams.get('fallback_redirect_uri'));
                    var raw = url.match(/[?&]redirect_uri=([^&]*)/i);
                    if (raw && raw[1]) rememberRedirectUri(decodeURIComponent(raw[1].replace(/\+/g, ' ')));
                }
            } catch (e) {}
            return origOpen.apply(this, arguments);
        };
        var origSubmit = HTMLFormElement.prototype.submit;
        HTMLFormElement.prototype.submit = function () {
            try { captureFromForm(this); } catch (e) {}
            return origSubmit.apply(this, arguments);
        };
        document.addEventListener('submit', function (event) {
            try { captureFromForm(event.target); } catch (e) {}
        }, true);
    })();
    var btn = document.getElementById('wa-connect-btn');
    var statusEl = document.getElementById('wa-connect-status');
    var registerStatusEl = document.getElementById('wa-register-status');
    var tokenStatusEl = document.getElementById('wa-token-status');
    var disconnectBtn = document.getElementById('wa-disconnect-btn');

    function setStatus(text, isError) {
        [statusEl, registerStatusEl, tokenStatusEl].forEach(function (el) {
            if (!el) return;
            el.textContent = text;
            el.classList.toggle('text-danger', !!isError);
            el.classList.toggle('text-success', !isError && /connected|saved|registered/i.test(text));
        });
    }

    function postJson(payload) {
        return fetch(cfg.endpoint, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-Token': cfg.csrf
            },
            body: JSON.stringify(Object.assign({ csrf_token: cfg.csrf }, payload))
        }).then(function (res) {
            return res.json().then(function (body) {
                if (!res.ok || !body.ok) {
                    throw new Error(body.error || 'Request failed');
                }
                return body;
            });
        });
    }

    function isMetaOrigin(origin) {
        try {
            var host = new URL(origin).hostname.toLowerCase();
            return /(^|\.)(facebook\.com|fbcdn\.net|instagram\.com|whatsapp\.com)$/.test(host);
        } catch (e) {
            return false;
        }
    }

    function collectSignupIds(value, into) {
        if (!value) return;
        if (typeof value === 'string') {
            try { value = JSON.parse(value); } catch (e) { return; }
        }
        if (Array.isArray(value)) {
            value.forEach(function (item) { collectSignupIds(item, into); });
            return;
        }
        if (typeof value !== 'object') return;
        var digits = function (raw) {
            return String(raw == null ? '' : raw).replace(/\D+/g, '');
        };
        if (value.waba_id) into.waba_id = digits(value.waba_id);
        if ((!into.waba_id) && Array.isArray(value.waba_ids) && value.waba_ids.length) {
            into.waba_id = digits(value.waba_ids[0]);
        }
        if (value.phone_number_id) into.phone_number_id = digits(value.phone_number_id);
        if (value.business_id) into.business_id = digits(value.business_id);
        if (value.waba && (value.waba.id || typeof value.waba === 'string')) {
            into.waba_id = digits(value.waba.id || value.waba);
        }
        Object.keys(value).forEach(function (key) {
            if (value[key] && typeof value[key] === 'object') collectSignupIds(value[key], into);
        });
    }

    window.addEventListener('message', function (event) {
        if (!isMetaOrigin(event.origin)) return;
        var raw = '';
        try {
            raw = typeof event.data === 'string' ? event.data : JSON.stringify(event.data);
        } catch (e) {
            raw = '';
        }
        var wabaMatch = raw.match(/waba_ids?["'\s:\[,]+["']?(\d{10,20})/i);
        var phoneMatch = raw.match(/phone_number_id["'\s:]+["']?(\d{10,20})/i);
        var bizMatch = raw.match(/business_id["'\s:]+["']?(\d{10,20})/i);
        if (wabaMatch) sessionInfo.waba_id = wabaMatch[1];
        if (phoneMatch) sessionInfo.phone_number_id = phoneMatch[1];
        if (bizMatch) sessionInfo.business_id = bizMatch[1];
        var data = event.data;
        try {
            if (typeof data === 'string') data = JSON.parse(data);
        } catch (e) {
            data = null;
        }
        collectSignupIds(data, sessionInfo);
        var type = data && data.type;
        var name = String((data && data.event) || '').toUpperCase();
        if (type && type !== 'WA_EMBEDDED_SIGNUP' && !sessionInfo.waba_id) return;
        if (name === 'CANCEL') {
            setStatus('Signup was cancelled.', true);
        } else if (name === 'ERROR') {
            setStatus('Meta returned an error during signup. Try again.', true);
        }
        if (/FINISH/i.test(name) || sessionInfo.waba_id) {
            sessionReady = true;
        }
    });

    function fieldDigits(id) {
        return String((document.getElementById(id) || {}).value || '').replace(/\D+/g, '');
    }

    function knownWaba() {
        return sessionInfo.waba_id || fieldDigits('wa-waba-id');
    }

    function knownPhone() {
        return sessionInfo.phone_number_id || fieldDigits('wa-phone-id');
    }

    function knownBusiness() {
        return sessionInfo.business_id || fieldDigits('wa-business-id');
    }

    function canFinish() {
        return !!(knownWaba() || knownPhone());
    }

    function showFinishPasted(show) {
        if (!finishPastedBtn) return;
        finishPastedBtn.classList.toggle('d-none', !show);
    }

    function finishSignup(code) {
        pendingCode = '';
        showFinishPasted(false);
        setStatus('Finishing Cloud API connection. Keep WhatsApp Business open…');
        postJson({
            action: 'complete',
            code: code,
            waba_id: knownWaba(),
            phone_number_id: knownPhone(),
            business_id: knownBusiness(),
            pin: fieldDigits('wa-register-pin'),
            redirect_uri: capturedRedirectUri || ''
        }).then(function () {
            setStatus('Connected. Reloading…');
            window.location.href = window.location.pathname;
        }).catch(function (err) {
            setStatus(err.message || 'Could not complete WhatsApp connection.', true);
        });
    }

    function waitForWabaThenFinish(code) {
        pendingCode = code;
        if (sessionReady || sessionInfo.waba_id) {
            finishSignup(code);
            return;
        }
        showFinishPasted(true);
        setStatus('Waiting for WhatsApp screens in the Facebook window. If that window already closed after login, Embedded Signup did not start.');
        var started = Date.now();
        var t = setInterval(function () {
            if (sessionReady || sessionInfo.waba_id) {
                clearInterval(t);
                finishSignup(pendingCode || code);
                return;
            }
            if (Date.now() - started > 8000) {
                clearInterval(t);
                setStatus('The Facebook window closed at login, so pairing did not run. Click Connect WhatsApp again and stay in the popup through the WhatsApp steps. If it still closes immediately, the configuration ID is Facebook Login, not WhatsApp Embedded Signup. You can click Finish with saved IDs only to refresh the token (the number will stay PENDING).', true);
            }
        }, 200);
    }

    function loginExtras(withQr) {
        var extras = {
            setup: {},
            sessionInfoVersion: '3'
        };
        if (withQr) {
            extras.featureType = 'whatsapp_business_app_onboarding';
        }
        return extras;
    }

    function startFacebookLogin(withQr) {
        if (!cfg.ready) {
            setStatus('Save App ID, App Secret, and configuration ID first.', true);
            return;
        }
        if (!cfg.appId || !cfg.configId) {
            setStatus('Refresh this page, then click Connect WhatsApp again.', true);
            return;
        }
        setStatus(withQr
            ? 'Keep the Facebook window open. You should see Connect existing WhatsApp Business app, then QR on the phone.'
            : 'Keep the Facebook window open. After Facebook login you should see WhatsApp business and phone screens, not an immediate close.');
        sessionInfo = { waba_id: '', phone_number_id: '', business_id: '' };
        sessionReady = false;
        pendingCode = '';
        waitForSdk(function () {
            FB.login(function (response) {
                var code = response && response.authResponse && response.authResponse.code;
                if (code) {
                    waitForWabaThenFinish(String(code));
                    return;
                }
                setStatus('Facebook login was cancelled or did not return a code.', true);
            }, {
                config_id: cfg.configId,
                response_type: 'code',
                override_default_response_type: true,
                fallback_redirect_uri: cfg.redirectUri,
                extras: JSON.stringify(loginExtras(withQr))
            });
        });
    }

    if (btn) {
        btn.disabled = !cfg.ready;
        btn.addEventListener('click', function () {
            startFacebookLogin(false);
        });
    }

    var qrBtn = document.getElementById('wa-connect-qr-btn');
    if (qrBtn) {
        qrBtn.disabled = !cfg.ready;
        qrBtn.addEventListener('click', function () {
            startFacebookLogin(true);
        });
    }

    function waitForSdk(cb) {
        if (typeof FB !== 'undefined' && cfg.sdkReady) {
            cb();
            return;
        }
        var n = 0;
        var t = setInterval(function () {
            n += 1;
            if (typeof FB !== 'undefined') {
                if (!cfg.sdkReady && cfg.appId) {
                    FB.init({
                        appId: cfg.appId,
                        autoLogAppEvents: true,
                        xfbml: false,
                        version: cfg.sdkVersion
                    });
                    cfg.sdkReady = true;
                }
                if (cfg.sdkReady) {
                    clearInterval(t);
                    cb();
                    return;
                }
            }
            if (n > 50) {
                clearInterval(t);
                setStatus('Facebook Login did not load. Allow connect.facebook.net and refresh this page.', true);
            }
        }, 100);
    }

    if (finishPastedBtn) {
        finishPastedBtn.addEventListener('click', function () {
            if (!pendingCode) {
                setStatus('Paste the WABA ID first, then click Connect WhatsApp.', true);
                return;
            }
            if (!canFinish() && !knownBusiness()) {
                setStatus('Paste a WABA ID or Phone number ID from Meta Business Suite first.', true);
                return;
            }
            finishSignup(pendingCode);
        });
    }

    var registerBtn = document.getElementById('wa-register-btn');
    if (registerBtn) {
        registerBtn.addEventListener('click', function () {
            var pin = fieldDigits('wa-register-pin');
            if (!/^\d{6}$/.test(pin)) {
                setStatus('Enter the 6-digit two-step PIN from WhatsApp Business, then click Activate Cloud API.', true);
                return;
            }
            setStatus('Registering this number on Cloud API…');
            postJson({ action: 'register', pin: pin }).then(function () {
                setStatus('Number registered. Reloading…');
                window.location.href = window.location.pathname;
            }).catch(function (err) {
                setStatus(err.message || 'Could not register the WhatsApp number.', true);
            });
        });
    }

    var saveTokenBtn = document.getElementById('wa-save-token-btn');
    if (saveTokenBtn) {
        saveTokenBtn.addEventListener('click', function () {
            var token = String((document.getElementById('wa-manual-token') || {}).value || '').trim();
            if (token.length < 40) {
                setStatus('Paste the access token from WhatsApp → API Setup first.', true);
                return;
            }
            setStatus('Saving the Meta token…');
            postJson({
                action: 'save_token',
                access_token: token,
                waba_id: fieldDigits('wa-waba-id'),
                phone_number_id: fieldDigits('wa-phone-id')
            }).then(function (body) {
                if (!body.registered) {
                    setStatus(
                        'Token saved, but Meta still reports this number as '
                        + (body.phone_status || 'PENDING')
                        + '. Open WhatsApp → API Setup on the new app, generate a new token, and save it again.',
                        true
                    );
                    return;
                }
                setStatus('Connected. Reloading…');
                window.location.href = window.location.pathname;
            }).catch(function (err) {
                setStatus(err.message || 'Could not save that token.', true);
            });
        });
    }

    if (disconnectBtn) {
        disconnectBtn.addEventListener('click', function () {
            if (!window.confirm('Disconnect Cloud API from this website? The WhatsApp Business app on the phone is not deleted.')) {
                return;
            }
            postJson({ action: 'disconnect' }).then(function () {
                window.location.reload();
            }).catch(function (err) {
                setStatus(err.message || 'Could not disconnect.', true);
            });
        });
    }
})();
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
