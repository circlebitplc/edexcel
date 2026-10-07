#!/bin/bash
# Put LiveKit WebSocket on :8443 (HTTP/1.1+2 only) so Chrome does not use OpenLiteSpeed on 443.
set -euo pipefail
cd /opt/livekit
mkdir -p /opt/livekit/certs

CERT_SRC=""
for d in \
  /etc/letsencrypt/live/live.kandy.edexcel.college \
  /etc/letsencrypt/live/kandy.edexcel.college \
  /etc/letsencrypt/live/kandy.edexcel.college_ecc; do
  if [ -f "$d/fullchain.pem" ] && [ -f "$d/privkey.pem" ]; then
    CERT_SRC="$d"
    break
  fi
done

if [ -z "$CERT_SRC" ]; then
  echo "Could not find Let's Encrypt certs. Listed:"
  ls -la /etc/letsencrypt/live 2>/dev/null || true
  echo "Copy fullchain.pem and privkey.pem for live.kandy.edexcel.college into /opt/livekit/certs/"
  exit 1
fi

cp -L "$CERT_SRC/fullchain.pem" /opt/livekit/certs/fullchain.pem
cp -L "$CERT_SRC/privkey.pem" /opt/livekit/certs/privkey.pem
chmod 644 /opt/livekit/certs/fullchain.pem
chmod 640 /opt/livekit/certs/privkey.pem
echo "Copied certs from $CERT_SRC"

if command -v ufw >/dev/null 2>&1; then
  ufw allow 8443/tcp || true
fi

curl -fsS -o /opt/livekit/Caddyfile.8443 https://edexcel.college/tools/livekit-setup/Caddyfile.8443
curl -fsS -o /opt/livekit/docker-compose.ws8443.yml https://edexcel.college/tools/livekit-setup/docker-compose.ws8443.yml

docker compose -f docker-compose.yml -f docker-compose.cyberpanel.yml -f docker-compose.ws8443.yml up -d

echo
echo "Caddy on 8443:"
ss -lntp | grep 8443 || true
echo
echo "In Admin → Live classroom set LiveKit URL to:"
echo "  wss://live.kandy.edexcel.college:8443"
echo "Save, Test Connection, then Join from a NEW Incognito window."
