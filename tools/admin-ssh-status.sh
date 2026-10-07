#!/bin/bash
# Report authenticated SSH sessions. Prints addresses on this terminal only.
# Writes a status file with no addresses. A website request cannot set VERIFIED.
set -euo pipefail

STATUS_FILE="/home/edexcel.college/private_backups/firewall/admin-ssh-status.json"
echo "This check uses the shell that started it. It does not trust a web request."

echo "--- current SSH connection ---"
if [[ -n "${SSH_CONNECTION:-}" ]]; then
  echo "SSH_CONNECTION is set."
  # client address, client port, server address, server port
  echo "SSH_CONNECTION=${SSH_CONNECTION}"
else
  echo "SSH_CONNECTION is not set. This shell is not an SSH session."
fi

echo "--- authenticated sessions ---"
if command -v who >/dev/null 2>&1; then
  who
else
  echo "who is not available."
fi

echo "--- established connections to port 22 ---"
if command -v ss >/dev/null 2>&1; then
  ss -tn state established '( sport = :22 )' || true
else
  echo "ss is not available."
fi

echo "--- this user ---"
id
if [[ "$(id -u)" -eq 0 ]]; then
  echo "Root privileges: yes"
elif sudo -n true 2>/dev/null; then
  echo "Root privileges: sudo without a password prompt"
else
  echo "Root privileges: no"
fi

echo "--- public addresses ---"
ip -4 addr show scope global || true
ip -6 addr show scope global || true

echo "--- SSH listeners ---"
if command -v ss >/dev/null 2>&1; then
  ss -lnt | grep -E ':22[[:space:]]' || echo "No listener matched :22"
fi

echo "--- SSH authentication configuration ---"
if sshd -T >/dev/null 2>&1; then
  sshd -T | grep -E '^(port|listenaddress|permitrootlogin|passwordauthentication|pubkeyauthentication|kbdinteractiveauthentication|challengeresponseauthentication|allowusers|allowgroups) '
else
  echo "sshd -T could not be read. Effective settings are not confirmed."
fi

verified=no
pts=0
if command -v who >/dev/null 2>&1; then
  pts="$(who | awk '{print $2}' | grep -c '^pts/' || true)"
fi
if [[ -n "${SSH_CONNECTION:-}" && "$pts" -ge 1 ]]; then
  if [[ "$(id -u)" -eq 0 ]] || sudo -n true 2>/dev/null; then
    verified=yes
  fi
fi

if [[ "$verified" == "yes" ]]; then
  echo "ADMIN SESSION VERIFIED = YES"
else
  echo "ADMIN SESSION VERIFIED = NO"
fi

if [[ "$(id -u)" -eq 0 ]]; then
  mkdir -p "$(dirname "$STATUS_FILE")"
  stamp="$(date -u +%Y-%m-%dT%H:%M:%SZ)"
  if [[ "$verified" == "yes" ]]; then
    printf '{"verified":true,"source":"ssh","checked_at":"%s"}\n' "$stamp" > "$STATUS_FILE"
  else
    printf '{"verified":false,"source":"ssh","checked_at":"%s"}\n' "$stamp" > "$STATUS_FILE"
  fi
  chmod 644 "$STATUS_FILE"
  echo "Recorded the result without client addresses."
else
  echo "Status file was not written. Root is required to record it outside the website."
fi
