#!/bin/bash
# Option A. Stop the CyberPanel browser SSH terminal and keep it stopped.
# CyberPanel copies the unit file back and runs systemctl enable --now.
# An immutable stub makes that copy fail. CyberPanel source is not edited.
#
#   CONFIRM_DISABLE_BROWSER_SSH=yes sudo bash tools/disable-browser-ssh.sh
#
# Explicit enable later, from SSH:
#   CONFIRM_ENABLE_BROWSER_SSH=yes sudo bash tools/enable-browser-ssh.sh
set -euo pipefail

echo "SSH_CONNECTION=${SSH_CONNECTION:-not-an-ssh-session}"
if [[ "${CONFIRM_DISABLE_BROWSER_SSH:-}" != "yes" ]]; then
  echo "Refusing. Export CONFIRM_DISABLE_BROWSER_SSH=yes after ADMIN SESSION VERIFIED = YES."
  exit 3
fi
if [[ "$(id -u)" -ne 0 ]]; then
  echo "Refusing. Run as root."
  exit 4
fi
if [[ -z "${SSH_CONNECTION:-}" ]]; then
  echo "Refusing. This shell is not an SSH session."
  exit 6
fi
if ! systemctl cat fastapi_ssh_server.service >/dev/null 2>&1; then
  echo "fastapi_ssh_server.service was not found. Nothing was stopped."
  exit 0
fi

BACKUP_DIR="/home/edexcel.college/private_backups/firewall"
UNIT="/etc/systemd/system/fastapi_ssh_server.service"
mkdir -p "$BACKUP_DIR"
systemctl cat fastapi_ssh_server.service > "${BACKUP_DIR}/fastapi_ssh_server.service.bak"
systemctl disable --now fastapi_ssh_server.service || true
if lsattr -d "$UNIT" 2>/dev/null | grep -q '\-i\-'; then
  chattr -i "$UNIT" || true
fi
cat > "$UNIT" <<'EOF'
[Unit]
Description=Browser SSH terminal disabled by the administrator
[Service]
Type=oneshot
ExecStart=/bin/true
RemainAfterExit=yes
[Install]
WantedBy=multi-user.target
EOF
chattr +i "$UNIT"
systemctl daemon-reload
systemctl disable fastapi_ssh_server.service || true
if ss -lnt | grep -q ':8888 '; then
  echo "WARNING: something is still listening on 8888."
  exit 7
fi
echo "Option A applied. Port 8888 should be closed."
echo "CyberPanel cannot replace the unit file until chattr -i is used."
echo "Website, panel process, mail, FTP, and LiveKit were not stopped by this script."
