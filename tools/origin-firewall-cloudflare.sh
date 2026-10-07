#!/bin/bash
# Prepare an origin HTTP/HTTPS lock to Cloudflare addresses.
# Default is a dry run. It does not change firewall rules.
#
# Apply only after ALL of these are true:
#   1. edexcel.college and www answer with a CF-Ray header.
#   2. You can still open an SSH session and you know the rollback command.
#   3. Mail, FTP, CyberPanel, and LiveKit ports are intentionally left open.
#
#   CONFIRM_ORIGIN_LOCK=yes sudo bash tools/origin-firewall-cloudflare.sh --apply
#   sudo bash tools/origin-firewall-rollback.sh
set -euo pipefail

MODE="${1:---dry-run}"
if [[ "$MODE" != "--dry-run" && "$MODE" != "--apply" ]]; then
  echo "Usage: $0 [--dry-run|--apply]"
  exit 2
fi

BACKUP_DIR="/home/edexcel.college/private_backups/firewall"
MARKER="/home/edexcel.college/public_html/storage/cache/abuse/origin-firewall.json"
CHAIN="EDEXCEL-CF-HTTP"

# Keep in step with config/client_ip.php
CF4=(
  173.245.48.0/20 103.21.244.0/22 103.22.200.0/22 103.31.4.0/22
  141.101.64.0/18 108.162.192.0/18 190.93.240.0/20 188.114.96.0/20
  197.234.240.0/22 198.41.128.0/17 162.158.0.0/15 104.16.0.0/13
  104.24.0.0/14 172.64.0.0/13 131.0.72.0/22
)
CF6=(
  2400:cb00::/32 2606:4700::/32 2803:f800::/32 2405:b500::/32
  2405:8100::/32 2a06:98c0::/29 2c0f:f248::/32
)

echo "SSH_CONNECTION=${SSH_CONNECTION:-not-an-ssh-session}"
echo "SSH port 22 is not modified."
echo "Left open on purpose: 21 FTP, 22 SSH, 25/465/587 mail, 7080/8090/8888 panel, 7881/8443 LiveKit."
echo "Only NEW connections to TCP 80 and 443 would be limited to Cloudflare."

if [[ -n "${SSH_CONNECTION:-}" ]]; then
  echo "Current SSH client: ${SSH_CONNECTION}"
else
  echo "WARNING: this shell has no SSH_CONNECTION. Do not apply from a console you cannot recover."
fi

has_v6=no
if command -v ip >/dev/null 2>&1 && ip -6 addr show scope global 2>/dev/null | grep -q 'inet6 '; then
  has_v6=yes
fi
echo "Global IPv6 on this host: ${has_v6}"

if [[ "$MODE" == "--dry-run" ]]; then
  echo "Dry run only. No firewall command was executed."
  echo "Refusing to enable the application origin lock as well."
  exit 0
fi

if [[ "${CONFIRM_ORIGIN_LOCK:-}" != "yes" ]]; then
  echo "Refusing --apply. Export CONFIRM_ORIGIN_LOCK=yes after the checks above."
  exit 3
fi
if [[ "$(id -u)" -ne 0 ]]; then
  echo "Refusing --apply. Run as root on the origin."
  exit 4
fi
if ! command -v iptables >/dev/null 2>&1; then
  echo "Refusing --apply. iptables was not found. Do not guess a panel firewall from here."
  exit 5
fi
if systemctl is-active --quiet firewalld; then
  echo "Refusing --apply. firewalld is active."
  echo "Do not mix unmanaged iptables rules with firewalld."
  echo "Use tools/firewalld-origin-protection.sh after Cloudflare is confirmed. Nothing was changed."
  exit 7
fi

headers="$(curl -fsSI --max-time 15 https://edexcel.college/ || true)"
if ! grep -qi '^cf-ray:' <<<"$headers"; then
  echo "Refusing --apply. https://edexcel.college/ did not return CF-Ray."
  echo "Cloudflare is not confirmed in front of the site."
  exit 6
fi

mkdir -p "$BACKUP_DIR"
stamp="$(date -u +%Y%m%dT%H%M%SZ)"
iptables-save > "${BACKUP_DIR}/iptables-${stamp}.rules"
if command -v ip6tables >/dev/null 2>&1; then
  ip6tables-save > "${BACKUP_DIR}/ip6tables-${stamp}.rules" || true
fi
ln -sfn "${BACKUP_DIR}/iptables-${stamp}.rules" "${BACKUP_DIR}/iptables-latest.rules"
echo "Rollback copy: ${BACKUP_DIR}/iptables-${stamp}.rules"

iptables -N "$CHAIN" 2>/dev/null || iptables -F "$CHAIN"
iptables -A "$CHAIN" -m conntrack --ctstate ESTABLISHED,RELATED -j ACCEPT
for net in "${CF4[@]}"; do
  iptables -A "$CHAIN" -p tcp -s "$net" --dport 80 -j ACCEPT
  iptables -A "$CHAIN" -p tcp -s "$net" --dport 443 -j ACCEPT
done
iptables -A "$CHAIN" -p tcp --dport 80 -j DROP
iptables -A "$CHAIN" -p tcp --dport 443 -j DROP
iptables -C INPUT -j "$CHAIN" 2>/dev/null || iptables -I INPUT 1 -j "$CHAIN"

v6_applied=false
if [[ "$has_v6" == "yes" ]] && command -v ip6tables >/dev/null 2>&1; then
  ip6tables -N "$CHAIN" 2>/dev/null || ip6tables -F "$CHAIN"
  ip6tables -A "$CHAIN" -m conntrack --ctstate ESTABLISHED,RELATED -j ACCEPT
  for net in "${CF6[@]}"; do
    ip6tables -A "$CHAIN" -p tcp -s "$net" --dport 80 -j ACCEPT
    ip6tables -A "$CHAIN" -p tcp -s "$net" --dport 443 -j ACCEPT
  done
  ip6tables -A "$CHAIN" -p tcp --dport 80 -j DROP
  ip6tables -A "$CHAIN" -p tcp --dport 443 -j DROP
  ip6tables -C INPUT -j "$CHAIN" 2>/dev/null || ip6tables -I INPUT 1 -j "$CHAIN"
  v6_applied=true
fi

mkdir -p "$(dirname "$MARKER")"
now="$(date -u +%Y-%m-%dT%H:%M:%SZ)"
cat > "$MARKER" <<EOF
{"ipv4":{"applied":true,"at":"${now}"},"ipv6":{"applied":${v6_applied},"at":"${now}"}}
EOF
echo "Applied HTTP/HTTPS Cloudflare lock. SSH was not changed."
echo "Test the site through Cloudflare, then test SSH in the existing session before closing it."
echo "Rollback: bash tools/origin-firewall-rollback.sh"
