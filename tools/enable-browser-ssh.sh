#!/bin/bash
# Explicitly turn the browser SSH terminal back on. Does nothing unless asked.
set -euo pipefail

echo "SSH_CONNECTION=${SSH_CONNECTION:-not-an-ssh-session}"
if [[ "${CONFIRM_ENABLE_BROWSER_SSH:-}" != "yes" ]]; then
  echo "Refusing. The browser terminal stays as it is."
  exit 3
fi
if [[ "$(id -u)" -ne 0 || -z "${SSH_CONNECTION:-}" ]]; then
  echo "Refusing. Use a root SSH session."
  exit 4
fi

BACKUP="/home/edexcel.college/private_backups/firewall/fastapi_ssh_server.service.bak"
UNIT="/etc/systemd/system/fastapi_ssh_server.service"
if [[ ! -s "$BACKUP" ]]; then
  echo "No unit backup. Nothing was enabled."
  exit 5
fi
chattr -i "$UNIT" 2>/dev/null || true
cp "$BACKUP" "$UNIT"
systemctl daemon-reload
systemctl enable --now fastapi_ssh_server.service
echo "Browser SSH terminal started again. Restrict TCP 8888 with firewalld if it must stay on."
