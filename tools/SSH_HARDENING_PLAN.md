# SSH hardening plan

Not applied. SSH settings, firewall rules, and the existing sessions were not changed.

Checked 26 September 2026 from local session data only. `36.110.81.98` is UNKNOWN. Do not restrict SSH, and do not add that address to an allow-list, until it is identified or the administrator explicitly approves it.

A second established connection, `195.178.110.217`, was also present and is not a recorded login. It is not trusted either.

The open administrator session is `root` on `pts/0` from `203.189.184.59`, logged in 25 September 2026 at 23:55. Keep that session open.

## Live SSH settings

`sshd -T` currently has:

- `PermitRootLogin yes`
- `PubkeyAuthentication yes`
- `PasswordAuthentication yes`
- `KbdInteractiveAuthentication no`

Leave these as they are.

## Target, later

1. Create a dedicated administrator account that is not root.
2. Give it sudo.
3. From a second SSH session, log in as that account and confirm sudo works.
4. Confirm public-key login for that account.
5. Only then set `PasswordAuthentication no`.
6. Only then set `PermitRootLogin no`.
7. Only then consider limiting port 22 to the administrator address or a VPN subnet.

Do none of those steps while an unidentified SSH connection is open, and do not do them in the same session that would be locked out.

`203.189.184.59` is the administrator address for the current root session. It is not written into a firewall rule by this plan. `36.110.81.98` and `195.178.110.217` must not be copied into `ADMIN_IPS`.

Port 22 is not part of `tools/firewalld-origin-protection.sh`. That script still must not be applied until its own preflight passes, and it must not be used as a way to drop these SSH connections.
