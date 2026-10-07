#!/bin/bash
# Read-only FTP check. Does not restart Pure-FTPd or change TLS.
set -u

echo "FTP audit. Pure-FTPd is not stopped. TLS is not changed."
echo "--- TLS ---"
if [[ -f /etc/pure-ftpd/conf/TLS ]]; then
  echo -n "conf/TLS="
  cat /etc/pure-ftpd/conf/TLS
fi
if [[ -f /etc/pure-ftpd/pure-ftpd.conf ]]; then
  grep -E '^(TLS|NoAnonymous|ChrootEveryone)' /etc/pure-ftpd/pure-ftpd.conf || true
fi
echo "TLS 1 means clients may still use cleartext FTP."
echo "The current deploy client does not use TLS. Setting TLS to 2 would reject that client."
echo "Make the deploy client use AUTH TLS first. Only then consider requiring TLS."
echo "Anonymous login was refused on 25 September 2026. This script does not try another login."
echo "A later firewalld limit may allow only known deploy and administrator addresses. No address is included here."

if [[ "$(id -u)" -eq 0 ]]; then
  CHECK_DIR="/home/edexcel.college/private_backups/firewall/checks"
  mkdir -p "$CHECK_DIR"
  printf 'checked=yes\nchanged=no\n' > "${CHECK_DIR}/ftp-audit.stamp"
  echo "Recorded that the review ran. FTP was not changed."
else
  echo "Root is required to record the review. Nothing was changed."
fi
