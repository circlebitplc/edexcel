#!/bin/bash
# Read-only PowerDNS dependency check. Does not delete zones or change the bind address.
set -u

echo "PowerDNS audit. No zone is deleted. The server is not rebound to localhost."
if [[ "$(id -u)" -ne 0 ]]; then
  echo "Root is required to read the PowerDNS configuration. Nothing was changed."
  exit 4
fi

echo "--- zones ---"
pdnsutil list-zones || echo "pdnsutil list-zones failed."
echo "--- resolver ---"
cat /etc/resolv.conf
echo "--- public delegation ---"
dig +short NS edexcel.college @1.1.1.1 || true
echo "--- query to the local listener ---"
dig +short NS edexcel.college @127.0.0.1 || true
echo "--- recursion probe ---"
dig +time=2 +tries=1 example.com @127.0.0.1 || true
echo "Review the zone names before any TCP/UDP 53 firewall change."
echo "A later restriction, still not applied by this script, would remove public 53/tcp and 53/udp only after this review."

CHECK_DIR="/home/edexcel.college/private_backups/firewall/checks"
mkdir -p "$CHECK_DIR"
cat > "${CHECK_DIR}/pdns-audit.stamp" <<EOF
checked=yes
changed=no
EOF
echo "Recorded that the audit ran. DNS was not changed."
