# Self-hosted LiveKit for Edexcel College

The **college website stays on** https://edexcel.college (PHP / MySQL). The teacher room UI and the scrollable PDF viewer are documented in `public_html/SYSTEM.md` §38.6. This folder is only the LiveKit server.

**LiveKit runs on your VPS.** Browsers use `wss://live.kandy.edexcel.college` for video. PHP calls the same host over HTTPS for rooms and recording. Recordings still land in Bunny with the existing paywall.

Copy **this folder to the VPS**. Do not treat it as a public website. If it is left under `public_html/deploy`, HTTP access is blocked.

## What you need

- A VPS with Docker and Docker Compose
- DNS **A records** pointing at the VPS:
  - `live.kandy.edexcel.college`
  - `s3.live.kandy.edexcel.college`
- Open ports: **80/tcp**, **443/tcp**, **7881/tcp**, **3478/udp**, **50000–50100/udp**
- Ports 80 and 443 on the VPS must be free (this stack uses Caddy for HTTPS)

Change the hostnames in `.env`, `livekit.yaml` (`turn.domain`), and college Admin settings if you use different names.

## 1. Keys (same three places)

On the VPS:

```bash
docker run --rm livekit/livekit-server generate-keys
```

Put that API key and secret in:

1. `livekit.yaml` → `keys:` and `webhook.api_key`
2. `egress.yaml` → `api_key` / `api_secret`
3. College Admin → Settings → Live classroom (or college `.env` `LIVEKIT_API_KEY` / `LIVEKIT_API_SECRET`)

They must match. Do not generate a second pair for PHP.

## 2. Start the stack

```bash
cp .env.example .env
# edit .env (hostnames, MinIO password, keys if you keep them there for notes)
# edit livekit.yaml and egress.yaml with the generated keys
# edit turn.domain in livekit.yaml if the LiveKit hostname is not live.kandy.edexcel.college
docker compose up -d
```

Webhook for self-hosted LiveKit is **already** in `livekit.yaml`:

`https://edexcel.college/api/livekit/webhook.php`

Do not paste that URL into LiveKit Cloud. Cloud uses the Cloud dashboard instead.

## 3. College Admin settings

| Field | Example |
| --- | --- |
| LiveKit URL | `wss://live.kandy.edexcel.college` |
| API key / secret | The pair from step 1 |
| Public S3 URL | `https://s3.live.kandy.edexcel.college` |
| Internal S3 URL | `http://minio:9000` |
| Bucket | `livekit` |
| Region | `us-east-1` |
| S3 access key / secret | `MINIO_ROOT_USER` / `MINIO_ROOT_PASSWORD` from `.env` |
| Path-style URLs | On |

Use **Test Connection**. That checks the SFU, not MinIO.

Keep **Record live classes** on. Without MinIO/S3, class still works; Bunny ingest is skipped.

Each teacher still needs a Bunny Stream library. Joining live does not unlock paid recordings.

## 4. Firewall (example)

```bash
ufw allow 80/tcp
ufw allow 443/tcp
ufw allow 7881/tcp
ufw allow 3478/udp
ufw allow 50000:50100/udp
```

## If LiveKit is on the same VPS as the college website (CyberPanel)

Follow **`CYBERPANEL.md`** in this folder. Short version: stop Caddy, use `docker-compose.cyberpanel.yml`, proxy `live.` to `127.0.0.1:7880` with WebSocket, add **Alt-Svc: clear**, open **UDP 50000–50100** and **3478**.

## How the pieces connect

```
Students / teachers  →  wss://live.kandy.edexcel.college   (VPS, Caddy → LiveKit)
College PHP          →  https://live.…/twirp               (tokens, start/stop, egress)
LiveKit              →  POST /api/livekit/webhook.php      (college site)
Egress               →  MinIO on the VPS
College PHP          →  presigned HTTPS GET on s3.live.…   (Bunny fetchFromUrl)
Bunny                →  class_recordings paywall (unchanged)
```
