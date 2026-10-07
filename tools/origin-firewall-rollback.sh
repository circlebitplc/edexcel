#!/bin/bash
# Restore the iptables snapshot taken by origin-firewall-cloudflare.sh --apply.
# Does not touch SSH by itself: it restores the ruleset from before the lock.
set -euo pipefail

BACKUP_DIR="/home/edexcel.college/private_backups/firewall"
V4="${BACKUP_DIR}/iptables-latest.rules"
MARKER="/home/edexcel.college/public_html/storage/cache/abuse/origin-firewall.json"

if [[ "$(id -u)" -ne 0 ]]; then
  echo "Run as root on the origin."
  exit 4
fi
if [[ ! -f "$V4" ]]; then
  echo "No snapshot at ${V4}. Nothing was restored."
  exit 5
fi

echo "SSH_CONNECTION=${SSH_CONNECTION:-not-an-ssh-session}"
echo "Restoring ${V4}"
iptables-restore < "$V4"

latest6="$(ls -1t ${BACKUP_DIR}/ip6tables-*.rules 2>/dev/null | head -n 1 || true)"
if [[ -n "$latest6" ]] && command -v ip6tables-restore >/dev/null 2>&1; then
  echo "Restoring ${latest6}"
  ip6tables-restore < "$latest6" || echo "IPv6 restore failed. Check ip6tables-save output."
fi

if [[ -f "$MARKER" ]]; then
  rm -f "$MARKER"
fi
echo "Firewall snapshot restored and the origin-lock marker removed."
echo "Confirm SSH still works in this session, then open https://edexcel.college/."
