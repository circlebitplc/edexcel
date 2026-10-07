#!/bin/bash
# Restore the firewalld backup taken by firewalld-origin-protection.sh --apply.
# Does not call iptables. Does not change SSH by itself.
set -euo pipefail

BACKUP_DIR="/home/edexcel.college/private_backups/firewall"
ARCHIVE="${BACKUP_DIR}/firewalld-latest.tar.gz"

if [[ "$(id -u)" -ne 0 ]]; then
  echo "Run as root on the origin."
  exit 4
fi
if ! systemctl is-active --quiet firewalld; then
  echo "firewalld is not active. Nothing was restored."
  exit 5
fi
if [[ ! -f "$ARCHIVE" ]]; then
  echo "No firewalld snapshot at ${ARCHIVE}. Nothing was restored."
  exit 6
fi

echo "SSH_CONNECTION=${SSH_CONNECTION:-not-an-ssh-session}"
echo "Restoring ${ARCHIVE}"
tar -C /etc -xzf "$ARCHIVE"
firewall-cmd --reload
echo "firewalld configuration restored and reloaded."
echo "Confirm SSH still works in this session before closing it."
