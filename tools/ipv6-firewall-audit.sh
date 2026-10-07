#!/bin/bash
# Read-only IPv6 firewall view. Does not disable IPv6 or change rules.
set -u

echo "IPv6 audit. IPv6 is not disabled. firewall-cmd is not asked to change anything."
echo "No public AAAA record does not protect listeners on a global IPv6 address."
ip -6 addr show scope global || true
ip -6 route show || true
echo "--- listeners on IPv6 ---"
ss -lntu || true
echo "--- firewalld ---"
if systemctl is-active --quiet firewalld; then
  firewall-cmd --list-all || true
  firewall-cmd --list-all-zones || true
else
  echo "firewalld is not active."
fi
echo "The same administrator limits used for IPv4 must include IPv6 addresses or subnets."
echo "Do not add an IPv4-only rule and call IPv6 protected."

if [[ "$(id -u)" -eq 0 ]]; then
  CHECK_DIR="/home/edexcel.college/private_backups/firewall/checks"
  mkdir -p "$CHECK_DIR"
  printf 'checked=yes\nchanged=no\n' > "${CHECK_DIR}/ipv6-audit.stamp"
  echo "Recorded that the review ran. No rule was changed."
else
  echo "Root is required to record the review. Nothing was changed."
fi
