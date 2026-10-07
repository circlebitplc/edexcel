# WhatsApp Cloud API — Edexcel College

Reference for connecting this website to Meta WhatsApp Cloud API (coexistence with the WhatsApp Business app), then routing inbound messages to the PHP chatbot.

Last updated: 27 August 2026.

Site: https://edexcel.college  
Connect page: https://edexcel.college/admin/whatsapp_connect.php  
Messages: https://edexcel.college/admin/whatsapp_bot.php  
Webhook: https://edexcel.college/api/whatsapp/webhook.php

---

## Goal

```
Website
  → Connect WhatsApp (admin)
  → Meta Embedded Signup (or API Setup token)
  → Existing WhatsApp Business app
  → QR / pairing (only if the number is not already Cloud-API registered)
  → WABA + phone number ID
  → Cloud API
  → PHP webhook /api/whatsapp/webhook.php
  → Chatbot
```

Staff can keep using WhatsApp Business on the phone while this site sends and receives through Cloud API (**coexistence**).

---

## Active number (use this)

This is the number shown as **Registered** in Meta Production setup, with **Subscribe webhooks ON**.

| Field | Value |
| --- | --- |
| Display name | mobitel |
| Phone | **+94 71 339 6083** |
| Phone number ID | **1401006549754257** |
| WhatsApp Business account ID (WABA) | **1427234989472063** |
| Meta status | Registered |
| Subscribe webhooks | ON |

Sending and webhooks must use this phone number ID. Test outbound from **Messages** to **+94 71 339 6083**.

---

## Do not use (unverified college number)

This number was linked earlier but never became Cloud API registered. PIN `/register` was attempted and hit Meta’s 72-hour block. **Do not call `/register` on it again.**

| Field | Value |
| --- | --- |
| Display name | edexcel college |
| Phone | +94 78 585 8585 (sometimes seen as +94 75 585 8585) |
| Phone number ID | 1278707178658883 |
| WABA | 1582133780129878 |
| Meta status | unverified / PENDING |
| Verification | NOT_VERIFIED |
| Platform | NOT_APPLICABLE |
| Subscribe webhooks | OFF |

Graph send error for this number: **133010 Account not registered**.

---

## Meta app (`edexcel_bot`)

| Field | Value |
| --- | --- |
| App name | edexcel_bot |
| App ID | **1374835141532566** |
| App dashboard | https://developers.facebook.com/apps/1374835141532566/ |
| Production setup | https://developers.facebook.com/apps/1374835141532566/whatsapp-business/onboarding/?business_id=757119500319688 |
| API Setup | https://developers.facebook.com/apps/1374835141532566/whatsapp-business/wa-dev-console/ |
| Facebook Login configurations | https://developers.facebook.com/apps/1374835141532566/fb-login/configs/ |
| App settings → Basic | https://developers.facebook.com/apps/1374835141532566/settings/basic/ |
| Default business portfolio ID | **757119500319688** |
| Saved config ID (on site) | **28284409661178672** (also seen as 2828440988117872) |
| Config type problem | This ID is almost certainly a **Facebook Login** configuration, **not** WhatsApp Embedded Signup. That is why the Facebook window closes after login. |
| App published | Unpublished (as of last check) |
| Facebook Login for Business Strict Mode | Yes (locked) |
| Client OAuth | No (locked) |
| Web OAuth | No (locked) |
| Login with JavaScript SDK | **Yes** (required) |
| Valid OAuth Redirect URI | `https://edexcel.college/admin/whatsapp_connect.php` |
| Allowed domains | `https://edexcel.college/` |
| Facebook user ID seen during login | 4443859862541593 |

### App Settings → Basic (order matters)

App Domains will **not** save until a Website platform exists.

1. Privacy Policy URL: `https://edexcel.college/privacy-policy`
2. Terms of Service URL: `https://edexcel.college/terms`
3. User data deletion URL: `https://edexcel.college/data-deletion.php`
4. Add platform → Website → Site URL: `https://edexcel.college/`
5. Save changes
6. App Domains: type only `edexcel.college` (no `https://`, no `/`), press Enter until it becomes a chip, Save again

Public pages on this site:

- `privacy.php` → `/privacy-policy`
- `terms.php` → `/terms` (use `terms.php` if a rewrite 404s on LiteSpeed)
- `data-deletion.php`

### Facebook Login for Business

Keep **Login with the JavaScript SDK** on **Yes**. Leave Client OAuth and Web OAuth as **No**.

Embedded Signup **must** use `FB.login` (popup). A custom `dialog/oauth` redirect hits **URL Blocked**.

### Embedded Signup configuration (still required for the popup path)

The saved config ID is the wrong type. Create a new one:

1. Open [Configurations](https://developers.facebook.com/apps/1374835141532566/fb-login/configs/)
2. **Create configuration** → choose **WhatsApp Embedded Signup** (not User access token / Facebook Login)
3. Permissions: `whatsapp_business_management`, `whatsapp_business_messaging`, `business_management`
4. Copy the new configuration ID
5. Paste it on Connect WhatsApp → **Embedded Signup config ID** → **Save app details**
6. Click **Connect WhatsApp** and stay in the popup through the WhatsApp screens

Until that config exists, **do not keep clicking Connect**. Use the API Setup token path instead.

---

## Fastest working path (no Embedded Signup)

The Registered number is already on Cloud API. Skip QR and skip PIN.

1. Open [WhatsApp → API Setup](https://developers.facebook.com/apps/1374835141532566/whatsapp-business/wa-dev-console/)
2. Connect / select WABA `1427234989472063`
3. Confirm the From number is **+94 71 339 6083** (phone number ID `1401006549754257`)
4. Generate access token (starts with `EAA`)
5. On https://edexcel.college/admin/whatsapp_connect.php:
   - WABA ID: `1427234989472063`
   - Phone number ID: `1401006549754257`
   - Paste token → **Save API token**
6. Send a test from **Messages** to `+94 71 339 6083`

If a token was already saved for the old college number, open Connect WhatsApp once. The page switches the saved connection to the Registered IDs when a token exists (`usePhone`).

---

## How login works on this site

**Must use `FB.login` JS SDK popup.** Do not build a custom OAuth URL.

Green **Connect WhatsApp** extras:

```json
{ "setup": {}, "sessionInfoVersion": "3" }
```

Outline **Connect with QR (Business app)** adds:

```json
{ "featureType": "whatsapp_business_app_onboarding" }
```

`FB.login` options:

- `config_id`
- `response_type: code`
- `override_default_response_type: true`
- `fallback_redirect_uri`: `https://edexcel.college/admin/whatsapp_connect.php`
- `extras`: JSON string of the object above

OAuth code exchange uses Graph **v22.0**. Codes last about **30 seconds**. The SDK POSTs a form from `about:blank`; the page captures `redirect_uri` from that form. Exchange tries: captured URI, xd_arbiter `?version=46`, `login_success.html`, empty, omit.

Complete only if postMessage delivered a `waba_id` / session FINISH. Do not auto-complete just because WABA/phone fields are prefilled (that left the college number PENDING).

postMessage origins accepted: facebook / fbcdn / instagram / whatsapp. Events: `FINISH`, `FINISH_WHATSAPP_BUSINESS_APP_ONBOARDING`.

After 8 seconds without FINISH, the UI tells you Embedded Signup did not start. **Finish with saved IDs** only refreshes the token.

Login scopes seen: `whatsapp_business_management`, `whatsapp_business_messaging`, `public_profile`. `/me/businesses` needs `business_management` (often missing unless the ES config includes it). User node has no `whatsapp_business_accounts` without the right token.

---

## Webhook

| Item | Value |
| --- | --- |
| Callback URL | `https://edexcel.college/api/whatsapp/webhook.php` |
| Verify token | stored as `meta_webhook_verify_token` (generated on Connect; also seen historically as `edexcel-kandy-wa-2026`) |
| GET | Meta hub challenge (`hub_mode=subscribe`) |
| POST | `object: whatsapp_business_account` |
| Signature | `X-Hub-Signature-256` HMAC SHA256 with App Secret |

Subscribe fields:

- `messages`
- `history`
- `smb_app_state_sync`
- `smb_message_echoes`

The PHP handler also still accepts Evolution API webhooks when the provider is Evolution.

---

## Project files

| File | Role |
| --- | --- |
| `admin/whatsapp_connect.php` | Connect UI, FB JS SDK, WABA/phone fields, API token paste, QR button |
| `ajax/meta_embedded_signup.php` | JSON actions: `complete`, `disconnect`, `register`, `save_token`, `use_phone` |
| `src/Services/MetaEmbeddedSignupService.php` | Code exchange, WABA discovery, subscribe, webhook override, SMB sync, `applyManualToken`, `usePhone`, PIN register (skipped for NOT_APPLICABLE) |
| `src/Services/MetaCloudApiService.php` | Outbound Cloud API send; maps 133010 to a readable error |
| `config/whatsapp_gateway.php` | Provider switch (`meta` vs `evolution`), credentials, default IDs |
| `api/whatsapp/webhook.php` | Inbound Meta + Evolution webhook |
| `admin/whatsapp_bot.php` | Messages / chatbot admin |
| `admin/settings.php` | WhatsApp enable + Meta Cloud API status (connect/clear, not ID editing) |
| `privacy.php` / `terms.php` / `data-deletion.php` | Meta App Settings URLs |
| `src/Services/WhatsAppBotService.php` | Chatbot logic |
| `src/Services/WhatsAppSender.php` | Sender interface |

A number looks **registered** when Graph `platform_type` is `CLOUD_API` or `status` is `CONNECTED` or `REGISTERED`. PIN `/register` runs only when platform is not `NOT_APPLICABLE` and verification is not `NOT_VERIFIED`.

---

## Settings keys (database / env)

| Env | DB setting | Meaning |
| --- | --- | --- |
| `WHATSAPP_PROVIDER` | `whatsapp_provider` | `meta` or `evolution` |
| `META_WHATSAPP_TOKEN` | `meta_access_token` | Cloud API token (`EAA…`) |
| `META_WHATSAPP_PHONE_NUMBER_ID` | `meta_phone_number_id` | Must be `1401006549754257` |
| `META_WABA_ID` | `meta_waba_id` | Must be `1427234989472063` |
| `META_WHATSAPP_VERIFY_TOKEN` | `meta_webhook_verify_token` | Webhook verify token |
| `META_GRAPH_VERSION` | `meta_graph_version` | e.g. `v21.0` (OAuth exchange forced to v22.0) |
| `META_APP_ID` | `meta_app_id` | `1374835141532566` |
| `META_APP_SECRET` | `meta_app_secret` | App Secret from Basic settings (not the webhook token) |
| `META_EMBEDDED_SIGNUP_CONFIG_ID` | `meta_embedded_signup_config_id` | Must be a **WhatsApp Embedded Signup** config |
| | `meta_display_phone_number` | e.g. `+94 71 339 6083` |
| | `meta_onboarding_mode` | `coexistence` or `cloud` |
| | `meta_connected_at` | ISO timestamp |
| | `meta_is_on_biz_app` | `1` if WhatsApp Business app still linked |
| | `meta_platform_type` | Graph platform |
| | `meta_phone_status` | Graph status |
| | `meta_code_verification_status` | Graph verification |
| | `meta_register_blocked_until` | Unix time if PIN 133016 hit |
| | `whatsapp_enabled` | `1` to send |

---

## Ajax actions (`ajax/meta_embedded_signup.php`)

Admin-only POST JSON with CSRF.

| `action` | What it does |
| --- | --- |
| `complete` (default) | Exchange Embedded Signup code, save WABA/phone, subscribe, optional PIN |
| `save_token` | Save API Setup token + WABA + phone, subscribe, override webhook |
| `use_phone` | Re-point the saved token at a WABA + phone ID |
| `register` | PIN `/register` — **do not use** on the unverified college number |
| `disconnect` | Clear saved Cloud API connection (phone app is not deleted) |

---

## Errors already hit (do not regress)

| Error | Cause | Fix / rule |
| --- | --- | --- |
| **URL Blocked** | Custom OAuth redirect | Use `FB.login` popup only |
| **Can't load URL / App Domains** | App Domains saved before Website platform | Website platform first, then hostname chip `edexcel.college` |
| **redirect_uri mismatch** | Exchange URI ≠ popup URI | Capture form `redirect_uri`; try captured, xd_arbiter, `login_success.html`, empty, omit |
| **Error validating verification code** | Wrong `redirect_uri` or expired code (~30s) | Match URI; click Connect again quickly |
| **No WABA ID from token** | User node has no `whatsapp_business_accounts`; `/me/businesses` needs `business_management` | Paste API Setup token, or create a real WhatsApp ES config with that permission |
| **133010 Account not registered** | Sending from unverified college number | Use phone ID `1401006549754257` |
| **Invalid parameter / 133016** | PIN `/register` on a Business-app number (`NOT_APPLICABLE`) | Never PIN-register that number; 72-hour block after too many tries |
| **Facebook window closes at login** | Config ID is Facebook Login, not WhatsApp Embedded Signup | Create WhatsApp ES configuration, or paste API Setup token |
| **App Secret rejected** | Webhook verify token pasted instead of App Secret | App settings → Basic → App secret → Show |
| **Finish with saved IDs** after login-only | Completes with old PENDING college IDs | Do not auto-complete without session FINISH |

PIN register error **133016** means Meta blocked PIN for 72 hours. That does **not** block QR / Embedded Signup / API token on a *different* registered number.

---

## What not to do

- Do not PIN-register phone ID `1278707178658883`.
- Do not keep clicking Connect until the configuration ID is WhatsApp Embedded Signup.
- Do not use a custom Facebook OAuth dialog (Client/Web OAuth are locked).
- Do not paste the App Secret as the WhatsApp access token (token starts with `EAA`).
- Do not paste the webhook verify token as the App Secret.
- Do not send from the unverified college number; 133010 will continue.

---

## Test checklist

1. Connect WhatsApp shows the number **+94 71 339 6083** and phone ID `1401006549754257`.
2. Provider is Meta Cloud API.
3. Meta Production setup: that number is **Registered**, webhooks **ON**.
4. Send a test from **Messages** to `+94 71 339 6083`.
5. Reply on WhatsApp; the chatbot should answer via `/api/whatsapp/webhook.php`.

---

## Still unresolved

Embedded Signup popup closing at Facebook login is **not** fixed until a real **WhatsApp Embedded Signup** configuration ID is saved. The Registered Mobitel number does not need that popup if the API Setup token is pasted.
