# Administrator access before firewall hardening

No firewall rule in this folder is applied by reading this page. SSH, port 8888, WebAdmin, CyberPanel, FTP, DNS, and IPv6 stay as they are until the steps below are done from a normal SSH client.

Do not use the CyberPanel browser terminal on port 8888 as the session that proves access. That service is one of the things these steps are meant to close.

## Before any firewall change

1. Connect with a normal SSH client to port 22.
2. Run `bash tools/admin-ssh-status.sh` as root. It must print `ADMIN SESSION VERIFIED = YES`. A website request cannot produce that result.
3. Confirm the same command shows root, or sudo that works without a password prompt.
4. Open a second SSH session and leave the first one open.
5. Run `who` and confirm both sessions are listed.
6. Copy the client address from `SSH_CONNECTION` in the verified session. That is the trusted administrator address. Do not invent one, and do not paste an address taken from a web server log.
7. If administration uses a VPN, add that subnet as well. A changing home address is not a safe allow-list. Use a VPN with a stable subnet instead.
8. Export the addresses, for example `ADMIN_IPS='203.0.113.10 203.0.113.0/24'`. The example addresses are not this server's administrator.
9. Run the read-only audits, still as root:
   - `bash tools/pdns-dependency-audit.sh`
   - `bash tools/ipv6-firewall-audit.sh`
   - `bash tools/ftp-deployment-audit.sh`
10. Run `bash tools/firewalld-preflight.sh`. If any line says `[FAIL]`, stop. Do not apply a firewall change.
11. Run `bash tools/firewalld-origin-protection.sh` and read the proposed rules. This is a dry run.
12. Only after the dry run matches the trusted addresses, and both SSH sessions still work, apply with the two confirmation variables from an SSH shell:
    `CONFIRM_FIREWALLD=yes CONFIRM_ADMIN_PORTS=yes bash tools/firewalld-origin-protection.sh --apply`
13. Keep the original SSH session open. Test SSH, `https://edexcel.college/`, the panel on 8090, WebAdmin on 7080, mail, FTP, and LiveKit before closing it.
14. Rollback, if the panel or WebAdmin is blocked, from the open SSH session: `bash tools/firewalld-origin-rollback.sh`

## Port 8888

Option A is preferred when the browser terminal is not required. It is a separate command and it is not part of the firewall dry run:

`CONFIRM_DISABLE_BROWSER_SSH=yes bash tools/disable-browser-ssh.sh`

CyberPanel's `website.py` copies the unit back and runs `systemctl enable --now`. `virtualHostUtilities.py` and `upgrade.py` restart it. Option A replaces the unit with an immutable stub so that copy fails. The college site does not use this service. Turn it back on only on purpose:

`CONFIRM_ENABLE_BROWSER_SSH=yes bash tools/enable-browser-ssh.sh`

Option B leaves the service running and limits TCP 8888 to `ADMIN_IPS`. Add `RESTRICT_8888=yes` to the firewall dry run and, later, to the apply command. Use Option B when an administrator still needs the browser terminal. Do not put 8888 on the public website proxy.

## Do not restrict SSH yet

Port 22 stays open to the internet until a second administrative login works.

After that login exists:

- Create a dedicated administrator account that is not root.
- Give it sudo.
- Confirm sudo from that account.
- Open a second SSH session as that account and confirm it still works.
- Only then consider `PermitRootLogin no`.
- Only then consider limiting port 22 to the trusted addresses.

Do not turn off password authentication until `sshd -T` has been read. A config file says password login is off, and a live handshake has still offered password.

## Ports that stay public

Mail on 25, 465, and 587 stays open. LiveKit on 7880, 7881, 8443, and UDP 50000 stays open. Ports 80 and 443 stay open until Cloudflare is actually in front and a firewalld design replaces the old iptables script. FTP stays available for deploy. PowerDNS is not moved and its zone is not deleted. IPv6 is not disabled. The same administrator limits must cover IPv6; a missing AAAA record is not a firewall.

## FTP TLS later

Deploy currently uses FTP without TLS. Pure-FTPd allows that. Do not require TLS until the deploy client has been switched to explicit TLS and a test upload has succeeded. Anonymous login did not open a session and is not being changed here.

## Preflight

`tools/firewalld-preflight.sh` must pass before `--apply`. A failed line means stop. The apply command runs that checklist again and creates a firewalld backup before it changes a rule.
