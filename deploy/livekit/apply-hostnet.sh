#!/bin/bash
# Put LiveKit on the host network so WebRTC UDP is not docker-proxy.
# Run after Join works. Keep API keys. Recreates livekit + caddy8443 + redis.
set -euo pipefail
cd /opt/livekit

curl -fsS -o /opt/livekit/docker-compose.hostnet.yml https://edexcel.college/tools/livekit-setup/docker-compose.hostnet.yml
curl -fsS -o /opt/livekit/repair-yaml.py https://edexcel.college/tools/livekit-setup/repair-yaml.py
python3 /opt/livekit/repair-yaml.py || {
  echo "python3 repair failed, using sed"
  sed -i '/^[[:space:]]*node_ip:/d' livekit.yaml
  sed -i -E 's/^[[:space:]]*use_external_ip:.*/  use_external_ip: false/' livekit.yaml
  sed -i '/^rtc:/a\  node_ip: 169.58.123.255' livekit.yaml
}

cp -a livekit.yaml "livekit.yaml.bak.hostnet.$(date +%Y%m%d%H%M%S)"
# Host-network LiveKit talks to Redis on localhost. 6379 is often taken (Evolution).
sed -i -E 's/address:[[:space:]]*redis:6379/address: 127.0.0.1:16379/' livekit.yaml
sed -i -E 's/address:[[:space:]]*127\.0\.0\.1:6379/address: 127.0.0.1:16379/' livekit.yaml
sed -i -E 's/relay_range_start:[[:space:]]*50000/relay_range_start: 30000/' livekit.yaml
sed -i -E 's/relay_range_end:[[:space:]]*50100/relay_range_end: 30100/' livekit.yaml
echo "livekit.yaml redis:"
grep -n 'address:' livekit.yaml | head

if [ -f egress.yaml ]; then
  cp -a egress.yaml "egress.yaml.bak.hostnet.$(date +%Y%m%d%H%M%S)"
  sed -i -E 's#ws_url:[[:space:]]*ws://livekit:7880#ws_url: ws://host.docker.internal:7880#' egress.yaml
  sed -i -E 's#ws_url:[[:space:]]*ws://127.0.0.1:7880#ws_url: ws://host.docker.internal:7880#' egress.yaml
fi

if [ -f Caddyfile.8443 ]; then
  cp -a Caddyfile.8443 "Caddyfile.8443.bak.hostnet.$(date +%Y%m%d%H%M%S)"
  sed -i 's/reverse_proxy livekit:7880/reverse_proxy 127.0.0.1:7880/' Caddyfile.8443
  sed -i 's/reverse_proxy host.docker.internal:7880/reverse_proxy 127.0.0.1:7880/' Caddyfile.8443
fi

if command -v firewall-cmd >/dev/null 2>&1; then
  firewall-cmd --permanent --add-port=7881/tcp || true
  firewall-cmd --permanent --add-port=8443/tcp || true
  firewall-cmd --permanent --add-port=3478/udp || true
  firewall-cmd --permanent --add-port=30000-30100/udp || true
  firewall-cmd --permanent --add-port=50000-50100/udp || true
  firewall-cmd --reload || true
fi
if command -v ufw >/dev/null 2>&1; then
  ufw allow 7881/tcp || true
  ufw allow 3478/udp || true
  ufw allow 30000:30100/udp || true
  ufw allow 50000:50100/udp || true
  ufw reload || true
fi

COMPOSE=( -f docker-compose.yml -f docker-compose.cyberpanel.yml )
[ -f docker-compose.ws8443.yml ] && COMPOSE+=( -f docker-compose.ws8443.yml )
COMPOSE+=( -f docker-compose.hostnet.yml )

docker compose "${COMPOSE[@]}" up -d --force-recreate redis
sleep 2
docker compose "${COMPOSE[@]}" up -d --force-recreate livekit
docker compose "${COMPOSE[@]}" up -d --force-recreate caddy8443
docker compose "${COMPOSE[@]}" up -d egress || true

sleep 3
echo
echo "=== 50000 must be livekit (or livekit-server), NOT docker-proxy ==="
ss -lunp | grep -E ':50000|:3478' | head -n 8
echo
echo "=== 7880 / 7881 / 8443 ==="
ss -lntp | grep -E ':7880|:7881|:8443' || true
echo
echo "=== Redis on 16379 (localhost only) ==="
ss -lntp | grep 16379 || echo "16379 not listening"
curl -sS -m 3 http://127.0.0.1:7880/ || echo "FAIL 7880"
echo
echo "=== public 8443 (want 200) ==="
curl -sI -m 8 https://live.kandy.edexcel.college:8443/ || true
echo
echo "Leave the class completely, hard-refresh, Join, then Share."
