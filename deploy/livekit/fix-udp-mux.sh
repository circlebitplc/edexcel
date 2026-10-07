#!/bin/bash
# RTC media = one UDP mux on 50000. TURN stays on 3478. Do not sed every udp_port line.
set -euo pipefail
cd /opt/livekit

curl -fsS -o /opt/livekit/repair-yaml.py https://edexcel.college/tools/livekit-setup/repair-yaml.py
python3 /opt/livekit/repair-yaml.py

if command -v firewall-cmd >/dev/null 2>&1; then
  firewall-cmd --permanent --add-port=7881/tcp || true
  firewall-cmd --permanent --add-port=3478/udp || true
  firewall-cmd --permanent --add-port=50000/udp || true
  firewall-cmd --permanent --add-port=50000-50100/udp || true
  firewall-cmd --permanent --add-port=30000-30100/udp || true
  firewall-cmd --reload || true
fi

COMPOSE=( -f docker-compose.yml -f docker-compose.cyberpanel.yml )
[ -f docker-compose.ws8443.yml ] && COMPOSE+=( -f docker-compose.ws8443.yml )
[ -f docker-compose.hostnet.yml ] && COMPOSE+=( -f docker-compose.hostnet.yml )

docker compose "${COMPOSE[@]}" up -d --force-recreate livekit
sleep 4

echo
echo "=== rtc / turn udp_port (rtc must be 50000, turn must be 3478) ==="
grep -n 'udp_port\|tcp_port\|node_ip' livekit.yaml || true
echo
echo "=== sockets (want livekit on :50000/udp and :3478/udp and :7880/:7881) ==="
ss -lunp | grep -E ':50000|:3478|:7882' || echo "NO UDP 50000/3478"
ss -lntp | grep -E ':7880|:7881' || true
echo
echo "=== logs ==="
docker logs livekit-livekit-1 --tail 20 2>/dev/null || true
echo
echo "=== 7880 (want OK) ==="
curl -sS -m 3 http://127.0.0.1:7880/ || echo FAIL
echo
echo "Then leave class, Ctrl+Shift+R, Join, Share."
