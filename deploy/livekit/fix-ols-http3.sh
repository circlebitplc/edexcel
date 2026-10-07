#!/bin/bash
# Chrome Join fails with "unknown websocket error" while PHP Test Connection works.
# Cause: OpenLiteSpeed advertises HTTP/3 (alt-svc: h3, cached in Chrome for 30 days).
set -euo pipefail

CONF=/usr/local/lsws/conf/httpd_config.conf
if [ ! -f "$CONF" ]; then
  echo "OpenLiteSpeed config not found: $CONF"
  exit 1
fi

STAMP=$(date +%Y%m%d%H%M%S)
cp -a "$CONF" "$CONF.bak.livekit.$STAMP"
echo "Backup: $CONF.bak.livekit.$STAMP"

echo
echo "=== enableSpdy / QUIC before ==="
grep -n -iE 'enableSpdy|quicEnable|enableQuic|enableHttp3' "$CONF" || echo "(none in httpd_config.conf)"

# HTTP/2 only (4). 13/12/15 include HTTP/3.
sed -i -E 's/enableSpdy[[:space:]]+[0-9]+/enableSpdy 4/g' "$CONF"
sed -i -E 's/quicEnable[[:space:]]+[0-9]+/quicEnable 0/g' "$CONF"
sed -i -E 's/enableQuic[[:space:]]+[0-9]+/enableQuic 0/g' "$CONF"

echo
echo "=== after ==="
grep -n -iE 'enableSpdy|quicEnable|enableQuic' "$CONF" || true

# Force Chrome to drop cached h3 (ma=2592000).
for f in /usr/local/lsws/conf/vhosts/*/vhconf.conf \
         /usr/local/lsws/conf/vhosts/*/vhost.conf \
         /usr/local/lsws/conf/vhosts/*/*.conf; do
  [ -f "$f" ] || continue
  if grep -q 'live.kandy.edexcel.college' "$f" 2>/dev/null || grep -qi 'livekit' "$f" 2>/dev/null; then
    if grep -q 'Alt-Svc: clear' "$f"; then
      echo "Alt-Svc: clear already in $f"
    else
      cp -a "$f" "$f.bak.livekit.$STAMP"
      printf '\nextraHeaders              Alt-Svc: clear\n' >> "$f"
      echo "Added Alt-Svc: clear to $f"
    fi
  fi
done

# If live. is only mapped in httpd_config, still add a server-level header is not portable.
# Add clear on the main kandy vhost too if it serves the live. alias.
for f in /usr/local/lsws/conf/vhosts/kandy.edexcel.college/vhconf.conf \
         /usr/local/lsws/conf/vhosts/kandy.edexcel.college/vhost.conf; do
  if [ -f "$f" ] && ! grep -q 'Alt-Svc: clear' "$f"; then
    cp -a "$f" "$f.bak.livekit.$STAMP"
    printf '\nextraHeaders              Alt-Svc: clear\n' >> "$f"
    echo "Added Alt-Svc: clear to $f"
  fi
done

if command -v ufw >/dev/null 2>&1; then
  ufw allow 7881/tcp || true
  ufw allow 3478/udp || true
  ufw allow 50000:50100/udp || true
  echo "ufw: 7881/tcp, 3478/udp, 50000-50100/udp"
fi

if [ -x /usr/local/lsws/bin/lswsctrl ]; then
  /usr/local/lsws/bin/lswsctrl restart
else
  systemctl restart lsws
fi

sleep 1
echo
echo "=== live. headers (alt-svc: h3 should be gone or include clear) ==="
curl -sI --http1.1 https://live.kandy.edexcel.college/ | grep -iE 'HTTP/|alt-svc|server' || true
echo
echo "Chrome cached h3 for 30 days. After this, use a NEW Incognito window (Ctrl+Shift+N),"
echo "or Chrome → chrome://net-internals/#alt-svc → Clear, then Join again."
