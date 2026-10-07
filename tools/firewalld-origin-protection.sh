#!/bin/bash
# firewalld-only admin-port plan. Default is a dry run.
# This script does not call iptables and does not apply a Cloudflare HTTP lock.
#
# It will not change SSH, mail, DNS, FTP, the website, or LiveKit.
# It refuses to guess an administrator address.
#
#   sudo bash tools/firewalld-origin-protection.sh
#   ADMIN_IPS='203.0.113.10' CONFIRM_FIREWALLD=yes CONFIRM_ADMIN_PORTS=yes \
#     sudo bash tools/firewalld-origin-protection.sh --apply
#
# Keep the current SSH session open, and confirm a second SSH session first.
# Rollback: sudo bash tools/firewalld-origin-rollback.sh
set -euo pipefail

MODE="${1:---dry-run}"
if [[ "$MODE" != "--dry-run" && "$MODE" != "--apply" ]]; then
  echo "Usage: $0 [--dry-run|--apply]"
  exit 2
fi

BACKUP_DIR="/home/edexcel.college/private_backups/firewall"
PORTS=(7080/tcp 7080/udp 8090/tcp)
if [[ "${RESTRICT_8888:-}" == "yes" ]]; then
  PORTS+=(8888/tcp)
fi

echo "SSH_CONNECTION=${SSH_CONNECTION:-not-an-ssh-session}"
echo "This script never changes ports 22, 25, 465, 587, 53, 80, 443, 21, 7880, 7881, 8443, or UDP 50000."
echo "Cloudflare HTTP origin lock is not part of this script."

if ! systemctl is-active --quiet firewalld; then
  echo "Refusing. firewalld is not active. Do not add iptables rules beside it."
  exit 5
fi
echo "firewalld is active."

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
if [[ -z "${ADMIN_IPS:-}" ]]; then
  echo "No ADMIN_IPS given. No administrator address will be guessed."
  echo "Proposed rules: none."
  echo "Add a verified administrator address or VPN subnet before any apply."
  echo "7080, 8090, and 8888 stay unchanged."
  bash "${SCRIPT_DIR}/firewalld-preflight.sh" --report || true
  if [[ "$MODE" == "--apply" ]]; then
    echo "STOP. Do not apply the firewall."
    exit 1
  fi
  exit 0
fi

echo "Proposed firewalld change, for each address in ADMIN_IPS:"
for ip in ${ADMIN_IPS}; do
  for port in "${PORTS[@]}"; do
    echo "  accept ${ip} to ${port}"
  done
done
echo "  remove world-open 7080/tcp, 7080/udp, and 8090/tcp from the default zone if they are listed"
echo "The zone target must stay reject/default. A blanket drop rule is not added, because it can match before the allow rule."
echo "An address is applied to IPv4 or IPv6 according to its family. Do not disable IPv6."
echo "Rules would be stored with --permanent and reloaded, so they survive reboot."
if [[ "${RESTRICT_8888:-}" == "yes" ]]; then
  echo "Option B: TCP 8888 would be limited to ADMIN_IPS as well. The service would keep running."
else
  echo "Option B is off. Port 8888 is not in this firewall change."
  echo "Option A, not run here: tools/disable-browser-ssh.sh"
fi
echo "SSH, mail, DNS, FTP, ports 80 and 443, and LiveKit are not in this change."

bash "${SCRIPT_DIR}/firewalld-preflight.sh" --report || true
if [[ "$MODE" == "--dry-run" ]]; then
  echo "Dry run only. firewall-cmd was not called."
  exit 0
fi
bash "${SCRIPT_DIR}/firewalld-preflight.sh"

if [[ "${CONFIRM_FIREWALLD:-}" != "yes" || "${CONFIRM_ADMIN_PORTS:-}" != "yes" ]]; then
  echo "Refusing --apply. Export CONFIRM_FIREWALLD=yes and CONFIRM_ADMIN_PORTS=yes."
  exit 3
fi
if [[ "$(id -u)" -ne 0 ]]; then
  echo "Refusing --apply. Run as root."
  exit 4
fi
if [[ -z "${SSH_CONNECTION:-}" ]]; then
  echo "Refusing --apply. This shell has no SSH_CONNECTION. Open SSH on port 22 first and keep it open."
  exit 6
fi

mkdir -p "$BACKUP_DIR"
stamp="$(date -u +%Y%m%dT%H%M%SZ)"
tar -C /etc -czf "${BACKUP_DIR}/firewalld-${stamp}.tar.gz" firewalld
firewall-cmd --list-all > "${BACKUP_DIR}/firewalld-${stamp}.list-all"
firewall-cmd --list-all --permanent > "${BACKUP_DIR}/firewalld-${stamp}.permanent"
ln -sfn "${BACKUP_DIR}/firewalld-${stamp}.tar.gz" "${BACKUP_DIR}/firewalld-latest.tar.gz"
echo "Backup: ${BACKUP_DIR}/firewalld-${stamp}.tar.gz"

target="$(firewall-cmd --permanent --get-target 2>/dev/null || true)"
if [[ "$target" == "ACCEPT" ]]; then
  echo "Refusing. The permanent zone target is ACCEPT, so removing a port would not block anyone."
  echo "No rule was changed."
  exit 8
fi
for ip in ${ADMIN_IPS}; do
  if [[ ! "$ip" =~ ^[0-9A-Fa-f:.]+(/[0-9]{1,3})?$ ]]; then
    echo "Refusing unsafe address. No rule was changed."
    exit 9
  fi
  family=ipv4
  if [[ "$ip" == *:* ]]; then
    family=ipv6
  fi
  for port in "${PORTS[@]}"; do
    firewall-cmd --permanent --add-rich-rule="rule family=\"${family}\" source address=\"${ip}\" port port=\"${port%/*}\" protocol=\"${port#*/}\" accept"
  done
done
echo "Permanent ports before the change:"
firewall-cmd --permanent --list-ports
echo "Permanent services before the change:"
firewall-cmd --permanent --list-services
for port in "${PORTS[@]}"; do
  firewall-cmd --permanent --remove-port="${port}" >/dev/null 2>&1 || true
done
firewall-cmd --reload
left="$(firewall-cmd --list-ports || true)"
for port in "${PORTS[@]}"; do
  if [[ "$left" == *"${port}"* ]]; then
    echo "WARNING: ${port} is still listed. A firewalld service may still open it. Do not assume it is restricted."
  fi
done
echo "Applied admin-port limits for 7080 and 8090. SSH was not changed."
echo "Test the second SSH session, then test 7080 and 8090 from an allowed address."
echo "Rollback: bash tools/firewalld-origin-rollback.sh"
