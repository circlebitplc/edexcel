#!/bin/bash
# Prepared TCP/UDP 53 limit. This script does not apply it.
set -euo pipefail

echo "Proposed later change, after the PowerDNS audit says public DNS is unnecessary:"
echo "  remove 53/tcp and 53/udp from the public firewalld zone"
echo "  keep DNS on localhost for CyberPanel if the audit shows that need"
echo "The edexcel.college zone must not be deleted."
echo "This script does not call firewall-cmd."
if [[ "${1:-}" == "--apply" ]]; then
  echo "Refusing. Public DNS has not been marked unnecessary."
  exit 3
fi
exit 0
