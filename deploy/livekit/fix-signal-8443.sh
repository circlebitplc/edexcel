#!/bin/bash
# Get LiveKit answering on 7880 again, then Caddy :8443 (fixes Chrome Failed to fetch / 502).
# Does NOT use host-network. Signalling first; UDP/hostnet is a later step.
set -euo pipefail
cd /opt/livekit

echo "=== before ==="
docker ps -a --format 'table {{.Names}}\t{{.Status}}\t{{.Ports}}' || true

curl -fsS -o /opt/livekit/docker-compose.cyberpanel.yml https://edexcel.college/tools/livekit-setup/docker-compose.cyberpanel.yml
curl -fsS -o /opt/livekit/docker-compose.ws8443.yml https://edexcel.college/tools/livekit-setup/docker-compose.ws8443.yml
curl -fsS -o /opt/livekit/Caddyfile.8443 https://edexcel.college/tools/livekit-setup/Caddyfile.8443

# Docker DNS name redis — required when LiveKit is not on the host network
if [ -f livekit.yaml ]; then
  cp -a livekit.yaml "livekit.yaml.bak.signal.$(date +%Y%m%d%H%M%S)"
  sed -i -E 's/address:[[:space:]]*127\.0\.0\.1:6379/address: redis:6379/' livekit.yaml
fi

# node_ip belongs under rtc:, not turn: (latest livekit-server crash-loops otherwise)
curl -fsS -o /opt/livekit/repair-yaml.py https://edexcel.college/tools/livekit-setup/repair-yaml.py
if ! python3 /opt/livekit/repair-yaml.py; then
  echo "python3 repair failed, using sed"
  sed -i '/^[[:space:]]*node_ip:/d' livekit.yaml
  sed -i '/^rtc:/a\  node_ip: 169.58.123.255' livekit.yaml
fi
if [ -f egress.yaml ]; then
  sed -i -E 's#ws_url:[[:space:]]*ws://host.docker.internal:7880#ws_url: ws://livekit:7880#' egress.yaml
fi

cat > /opt/livekit/Caddyfile.8443 <<'EOF'
{
    auto_https off
    servers {
        protocols h1 h2
    }
}

:8443 {
    tls /certs/fullchain.pem /certs/privkey.pem
    reverse_proxy livekit:7880
}
EOF

if [ ! -f /opt/livekit/certs/fullchain.pem ] || [ ! -f /opt/livekit/certs/privkey.pem ]; then
  echo "Missing /opt/livekit/certs — run setup-ws8443.sh first"
  exit 1
fi

if command -v ufw >/dev/null 2>&1; then
  ufw allow 8443/tcp || true
  ufw allow 7881/tcp || true
fi

# Bridge network only (no hostnet overlay)
COMPOSE=( -f docker-compose.yml -f docker-compose.cyberpanel.yml -f docker-compose.ws8443.yml )

docker compose "${COMPOSE[@]}" up -d redis
sleep 1
docker compose "${COMPOSE[@]}" up -d --force-recreate livekit
docker compose "${COMPOSE[@]}" up -d --force-recreate caddy8443

echo
echo "Waiting for 127.0.0.1:7880 ..."
ok=0
for i in $(seq 1 20); do
  if curl -sf -m 2 http://127.0.0.1:7880/ >/dev/null 2>&1; then
    ok=1
    break
  fi
  sleep 1
done

echo
echo "=== after ==="
docker ps -a --format 'table {{.Names}}\t{{.Status}}\t{{.Ports}}' || true
echo
echo "=== LiveKit logs ==="
docker compose "${COMPOSE[@]}" logs --tail 60 livekit || true
echo
if [ "$ok" -eq 1 ]; then
  echo "=== 127.0.0.1:7880 ==="
  curl -sS -m 3 http://127.0.0.1:7880/ || true
  echo
else
  echo "FAIL: LiveKit still not on 7880. Paste the logs above."
fi
echo "=== public :8443 (want HTTP 200, not 502) ==="
curl -sI -m 8 https://live.kandy.edexcel.college:8443/ || true
echo
echo "Then Join from a new Incognito window."
