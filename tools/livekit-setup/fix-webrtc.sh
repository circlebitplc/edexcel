#!/bin/bash
# Screen/camera "publication of local track timed out" = WebRTC media never reaches LiveKit.
# Sets rtc.node_ip to the VPS public IP, opens UDP/TCP media ports, restarts LiveKit.
set -euo pipefail
YAML=/opt/livekit/livekit.yaml
cd /opt/livekit

if [ ! -f "$YAML" ]; then
  echo "Missing $YAML"
  exit 1
fi

IP=$(curl -4 -fsS --max-time 8 https://ifconfig.me 2>/dev/null || curl -4 -fsS --max-time 8 https://icanhazip.com 2>/dev/null || true)
IP=$(echo "${IP:-}" | tr -d '[:space:]')
if [ -z "$IP" ]; then
  IP=169.58.123.255
fi
echo "Using node_ip: $IP"

cp -a "$YAML" "$YAML.bak.webrtc.$(date +%Y%m%d%H%M%S)"

# Always under rtc:. A node_ip line under turn: crash-loops livekit-server
# ("field node_ip not found in type config.TURNConfig").
sed -i '/^[[:space:]]*node_ip:/d' "$YAML"
if grep -qE '^[[:space:]]*use_external_ip:' "$YAML"; then
  sed -i -E 's/^[[:space:]]*use_external_ip:.*/  use_external_ip: false/' "$YAML"
fi
if grep -qE '^rtc:' "$YAML"; then
  sed -i "/^rtc:/a\\  node_ip: ${IP}" "$YAML"
else
  printf 'rtc:\n  use_external_ip: false\n  node_ip: %s\n' "$IP" | cat - "$YAML" > "$YAML.tmp" && mv "$YAML.tmp" "$YAML"
fi
if ! grep -qE '^[[:space:]]*allow_tcp_fallback:' "$YAML"; then
  sed -i '/^rtc:/a\  allow_tcp_fallback: true' "$YAML"
fi

grep -n "node_ip\|tcp_port\|port_range\|turn:" "$YAML" || true

if command -v ufw >/dev/null 2>&1; then
  ufw allow 7881/tcp || true
  ufw allow 3478/udp || true
  ufw allow 50000:50100/udp || true
  ufw reload || true
  echo "ufw: 7881/tcp, 3478/udp, 50000-50100/udp"
fi

COMPOSE=( -f docker-compose.yml -f docker-compose.cyberpanel.yml )
if [ -f docker-compose.ws8443.yml ]; then
  COMPOSE+=( -f docker-compose.ws8443.yml )
fi
docker compose "${COMPOSE[@]}" up -d --force-recreate livekit

echo
echo "Listeners (want 7881/tcp, 3478/udp, 50000/udp):"
ss -lntp | grep 7881 || echo "7881/tcp not listed"
ss -lunp | grep -E '3478|50000' || echo "UDP 3478/50000 not listed"
echo
echo "Also open UDP 50000-50100 and 3478 on the cloud/security-group firewall, not only ufw."
echo "Then hard-refresh the class and try camera or Share again."
