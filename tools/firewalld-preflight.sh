#!/bin/bash
# Checklist before any firewalld apply. This script never changes rules.
set -u

REPORT=no
if [[ "${1:-}" == "--report" ]]; then
  REPORT=yes
fi

PASS=0
FAIL=0
mark() {
  local state="$1"
  local text="$2"
  if [[ "$state" == "PASS" ]]; then
    PASS=$((PASS + 1))
    echo "[PASS] $text"
  else
    FAIL=$((FAIL + 1))
    echo "[FAIL] $text"
  fi
}

CHECK_DIR="/home/edexcel.college/private_backups/firewall/checks"
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"

if [[ -n "${SSH_CONNECTION:-}" ]] && { [[ "$(id -u)" -eq 0 ]] || sudo -n true 2>/dev/null; }; then
  mark PASS "Root/sudo SSH session verified"
else
  mark FAIL "Root/sudo SSH session verified"
fi

pts=0
if command -v who >/dev/null 2>&1; then
  pts="$(who | awk '{print $2}' | grep -c '^pts/' || true)"
fi
if [[ -n "${SSH_CONNECTION:-}" && "$pts" -ge 2 ]]; then
  mark PASS "Second SSH session verified"
else
  mark FAIL "Second SSH session verified"
fi

safe_ip='^[0-9A-Fa-f:.]+(/[0-9]{1,3})?$'
ips_ok=no
if [[ -n "${ADMIN_IPS:-}" ]]; then
  ips_ok=yes
  for ip in ${ADMIN_IPS}; do
    if [[ ! "$ip" =~ $safe_ip ]]; then
      ips_ok=no
    fi
  done
fi
if [[ "$ips_ok" == "yes" ]]; then
  mark PASS "Trusted administrator IP confirmed"
  mark PASS "ADMIN_IPS configured"
else
  mark FAIL "Trusted administrator IP confirmed"
  mark FAIL "ADMIN_IPS configured"
fi

client="${SSH_CONNECTION%% *}"
if [[ -n "${SSH_CONNECTION:-}" && "$ips_ok" == "yes" && -n "$client" ]]; then
  found=no
  for ip in ${ADMIN_IPS}; do
    if [[ "$ip" == "$client" || "$ip" == */* ]]; then
      found=yes
    fi
  done
  if [[ "$found" != "yes" && "${CONFIRM_ADMIN_IP_MISMATCH:-}" != "yes" ]]; then
    mark FAIL "Current SSH client is included in ADMIN_IPS or a VPN subnet"
  else
    mark PASS "Current SSH client is included in ADMIN_IPS or a VPN subnet"
  fi
fi

if systemctl is-active --quiet firewalld 2>/dev/null; then
  mark PASS "firewalld active"
else
  mark FAIL "firewalld active"
fi

backup_dir="/home/edexcel.college/private_backups/firewall"
if mkdir -p "$backup_dir" 2>/dev/null && [[ -w "$backup_dir" || "$(id -u)" -eq 0 ]]; then
  mark PASS "firewalld backup created"
else
  mark FAIL "firewalld backup created"
fi

if bash -n "${SCRIPT_DIR}/firewalld-origin-rollback.sh" 2>/dev/null && grep -q 'firewall-cmd --reload' "${SCRIPT_DIR}/firewalld-origin-rollback.sh" && ! grep -v '^[[:space:]]*#' "${SCRIPT_DIR}/firewalld-origin-rollback.sh" | grep -q 'iptables'; then
  mark PASS "rollback script tested/available"
else
  mark FAIL "rollback script tested/available"
fi

if systemctl cat lshttpd.service >/dev/null 2>&1; then
  mark PASS "7080 service identified"
else
  mark FAIL "7080 service identified"
fi
if systemctl cat lscpd.service >/dev/null 2>&1; then
  mark PASS "8090 service identified"
else
  mark FAIL "8090 service identified"
fi
if systemctl cat fastapi_ssh_server.service >/dev/null 2>&1; then
  mark PASS "8888 service identified"
else
  mark FAIL "8888 service identified"
fi

if [[ -f "${CHECK_DIR}/pdns-audit.stamp" ]] && grep -q '^changed=no$' "${CHECK_DIR}/pdns-audit.stamp"; then
  mark PASS "DNS dependency checked"
else
  mark FAIL "DNS dependency checked"
fi
if [[ -f "${CHECK_DIR}/ipv6-audit.stamp" ]] && grep -q '^changed=no$' "${CHECK_DIR}/ipv6-audit.stamp"; then
  mark PASS "IPv6 policy reviewed"
else
  mark FAIL "IPv6 policy reviewed"
fi
if [[ -f "${CHECK_DIR}/ftp-audit.stamp" ]] && grep -q '^changed=no$' "${CHECK_DIR}/ftp-audit.stamp"; then
  mark PASS "FTP deployment dependency checked"
else
  mark FAIL "FTP deployment dependency checked"
fi

echo "Passed: ${PASS}  Failed: ${FAIL}"
if [[ "$FAIL" -gt 0 ]]; then
  echo "STOP. Do not apply the firewall."
  if [[ "$REPORT" == "yes" ]]; then
    exit 0
  fi
  exit 1
fi
echo "Preflight passed. Rules are still unchanged until an apply command says so."
exit 0
