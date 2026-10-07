# Cloudflare migration checklist

Nothing in this checklist has been switched on. Nameservers are still `dns1.registrar-servers.com` and `dns2.registrar-servers.com`. Do not change DNS from this file. Do not delete records. Do not run the origin firewall apply command.

Observed 25 September 2026 from public resolvers. A zone transfer was not available, so the Namecheap panel must be copied in full before the nameserver change. Records below are the ones public DNS returned.

## 1. Current DNS records

| Name | Type | Value | Proxy later |
| --- | --- | --- | --- |
| `edexcel.college` | NS | `dns1.registrar-servers.com`, `dns2.registrar-servers.com` | Becomes Cloudflare nameservers only after the copy below is entered |
| `edexcel.college` | A | `169.58.123.255` | Proxy |
| `edexcel.college` | AAAA | none | Do not invent one |
| `edexcel.college` | MX | `10 mail.edexcel.college` | Keep. MX is never proxied |
| `edexcel.college` | TXT | none returned | If the panel shows any TXT, copy it. Do not add a guessed SPF |
| `edexcel.college` | SOA | serial `1789048796` | Informational |
| `www.edexcel.college` | CNAME | `edexcel.college` | Proxy |
| `mail.edexcel.college` | A | `169.58.123.255` | DNS only |
| `kandy.edexcel.college` | A | `169.58.123.255` | Preserve. Proxy only after the site still redirects. Do not delete |
| `live.kandy.edexcel.college` | A | `169.58.123.255` | DNS only |
| `s3.live.kandy.edexcel.college` | A | `169.58.123.255` | DNS only |
| `_dmarc.edexcel.college` | TXT | not present | Do not invent one during the move |
| Common DKIM names (`default`, `google`, `selector1`, `selector2`, `s1`, `s2`, `k1`, `mail`, `dkim`, `edexcel`) | TXT/CNAME | not present | Copy any selector the mail panel shows, even if it was not in this list |

Also absent from public DNS: `ftp`, `webmail`, `autodiscover`, `autoconfig`, `smtp`, `imap`, `pop`, `cpanel`, `direct`, `api`, `cdn`.

## 2. Records that should be proxied

Orange cloud, and only these website names:

- `edexcel.college` A
- `www.edexcel.college` CNAME

`kandy.edexcel.college` is a retired website name on the same address. Keep it. After the apex works through Cloudflare, proxy it too so it stops publishing the origin. It is not mail and it is not LiveKit.

## 3. Records that must remain DNS-only

Grey cloud:

- `mail.edexcel.college` — MX target. Proxying it breaks delivery.
- `live.kandy.edexcel.college` — LiveKit. Signaling is HTTPS/WebSocket on port **8443**. Normal Cloudflare HTTP proxying covers 80/443, not 8443.
- `s3.live.kandy.edexcel.college` — recording object storage.

Do not orange-cloud the MX record.

## 4. Required SSL/TLS configuration

Set these only after the origin still presents a valid certificate:

- SSL/TLS mode: **Full (strict)**
- Always Use HTTPS: on
- Do not enable HSTS `includeSubDomains`. The origin already sends `max-age=15552000` for the apex only. Mail and LiveKit names must not be forced by that header.
- Minimum TLS 1.2
- Keep the existing origin certificate, or install a Cloudflare origin certificate on OpenLiteSpeed before switching to Full (strict). Do not use Flexible.

## 5. WAF rules

Start in log mode for one school day where that choice exists, then block. Use the Cloudflare Managed Ruleset and OWASP core ruleset at the default or lower paranoia. Do not enable a rule that challenges every visitor.

Skip custom WAF for these paths, in this order, before any block rule:

1. `http.request.uri.path` equals `/auth/google/start.php` or `/auth/google/callback.php`
2. `http.request.uri.path` starts with `/api/onepay/`
3. `http.request.uri.path` equals `/api/livekit/webhook.php`
4. `http.request.uri.path` equals `/api/whatsapp/webhook.php`
5. `http.request.uri.path` equals `/api/sms/webhook.php`
6. `http.request.uri.path` equals `/api/bunny/webhook.php`

Then block HTTP methods other than GET, HEAD, POST, and OPTIONS on `/api/` and `/login.php`.
Leave managed SQL injection and XSS rules on for every other URL.

## 6. Bot protection

Bot Fight Mode: on for definitely automated clients.
Do not use Interactive Challenge or Under Attack Mode on the six paths above.
Do not put the whole zone in Under Attack Mode except during an active flood.

## 7. Rate-limit rules

Count by IP. Do not add a site-wide low limit. A school can share one address.

Stricter:

| Match | Limit | Action |
| --- | --- | --- |
| POST `/login.php`, `/portal/login.php`, `/parent/login.php`, `/student/login.php` | 30 / 10 minutes | Block 10 minutes |
| POST `/student/register.php` or path starts `/admissions/` | 10 / hour | Managed Challenge |
| POST path contains `pay_lesson.php` | 10 / 10 minutes | Block 10 minutes |
| POST path contains `otp` or starts `/api/sms/` | 20 / 10 minutes | Block 10 minutes |

Moderate:

| Match | Limit | Action |
| --- | --- | --- |
| `/api/v1/public.php` | 90 / minute | Block 1 minute |

More generous:

| Match | Limit | Action |
| --- | --- | --- |
| path starts `/api/classroom/` | 600 / minute | Block 1 minute |

Account limits for signed-in students stay in AbuseGuard. There is no separate password-reset URL; reset is the SMS OTP flow on the login pages, covered by the OTP limit.

## 8. Cache rules

Cache at the edge when the path ends in `css`, `js`, `mjs`, `png`, `jpg`, `jpeg`, `gif`, `webp`, `svg`, `ico`, `woff`, or `woff2`. The origin already sends `public, max-age=604800`.
Cache GET `/api/v1/public.php` for 60 seconds. It has no student records.
Bypass cache for `/admin`, `/student`, `/parent`, `/classroom`, `/login.php`, `/portal`, `/auth`, payment paths, and any response with `Cache-Control: no-store`.

## 9. Webhook and login exclusions

Never challenge or cache:

- Google OAuth start and callback
- OnePay `/api/onepay/`
- LiveKit webhook and the DNS-only host `live.kandy.edexcel.college:8443`
- WhatsApp `/api/whatsapp/webhook.php`
- SMS `/api/sms/webhook.php`
- Bunny `/api/bunny/webhook.php`
- Mail DNS and SMTP ports 25, 465, and 587

## 10. Verification after the nameserver change

Wait until `nslookup -type=NS edexcel.college` shows Cloudflare nameservers. Then:

- `curl -sI https://edexcel.college/` includes `cf-ray`
- `http://edexcel.college/` redirects to HTTPS
- `https://www.edexcel.college/` reaches the apex
- `/login.php` returns 200
- `/api/v1/public.php` returns JSON and `cache-control: public, max-age=60`
- `/auth/google/start.php?intent=staff` redirects to `accounts.google.com` and not to a challenge page
- A static CSS file still returns 200
- `mail.edexcel.college` still resolves to the origin and is not a Cloudflare proxy address
- MX is still `10 mail.edexcel.college`
- `live.kandy.edexcel.college` and `s3.live.kandy.edexcel.college` still resolve to the origin
- Send one real mail test from the mail panel
- Open Admin → Protection. Expected while the firewall is still off: Cloudflare YES, WAF INACTIVE until the API confirms the rules, Origin lock DISABLED, Trusted proxy OK, IPv4/IPv6 firewall WARNING, AbuseGuard ACTIVE, API Guard ACTIVE

Do not run `origin-firewall-cloudflare.sh --apply` in this step. The dry run is allowed only after `cf-ray` is present. Apply still requires root, `CONFIRM_ORIGIN_LOCK=yes`, and that same header.

## Not done yet

- Nameservers were not changed
- WAF, bot rules, and edge rate limits were not enabled
- The origin firewall was not applied and the dry run was not executed, because Cloudflare is not confirmed
