#!/bin/bash
set -euo pipefail
echo "=== network_mode (want: host) ==="
docker inspect livekit-livekit-1 --format '{{.HostConfig.NetworkMode}}' 2>/dev/null || echo "container missing"
echo
echo "=== UDP (want livekit on 50000 and 3478) ==="
ss -lunp | grep -E ':50000|:3478|:7882' | head -n 20 || true
echo
echo "=== TCP ==="
ss -lntp | grep -E ':7880|:7881|:8443' || true
echo
echo "=== 7880 ==="
curl -sS -m 3 http://127.0.0.1:7880/ || echo "FAIL"
echo
echo "If 7880 is FAIL or UDP 50000 is missing:"
echo "  curl -fsS -o /opt/livekit/fix-udp-mux.sh https://edexcel.college/tools/livekit-setup/fix-udp-mux.sh"
echo "  bash /opt/livekit/fix-udp-mux.sh"
