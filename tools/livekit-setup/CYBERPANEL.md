# CyberPanel (same VPS as the college site)

`live.kandy.edexcel.college` and the college site (`edexcel.college` / legacy `kandy.edexcel.college`) share **169.58.123.255**. OpenLiteSpeed owns ports 80 and 443. Do **not** run Caddy from `docker-compose.yml` on this machine.

Admin → Test Connection can reach the LiveKit **API** (`/twirp`) while **Join class** still fails. Join uses a WebSocket to `/rtc`. LiteSpeed currently sends `alt-svc: h3`, which often stops Chrome from opening that WebSocket. Camera/mic also need **UDP**.

## 1. Docker without Caddy

On the VPS, in the LiveKit folder:

```bash
docker compose stop caddy
docker compose -f docker-compose.yml -f docker-compose.cyberpanel.yml up -d
```

Confirm LiveKit is only on localhost:

```bash
ss -lntp | grep 7880
# should show 127.0.0.1:7880
```

## 2. Reverse proxy + kill HTTP/3

In CyberPanel: **Websites → live.kandy.edexcel.college → Manage → vHost Conf**.

If that website does not exist, create the subdomain `live` for `kandy.edexcel.college`, issue SSL, then edit vHost Conf.

Merge the contents of `openlitespeed.conf` into that vhost (external app `livekit` → `127.0.0.1:7880`, context `/`, **Alt-Svc: clear**, WebSocket rewrite).

Save, then restart OpenLiteSpeed (CyberPanel → Server Status → Restart LiteSpeed, or `systemctl restart lsws`).

Check from a PC:

```bash
curl -sI --http1.1 https://live.kandy.edexcel.college/
```

You want `OK` from LiveKit and **no** `alt-svc: h3` line. If `alt-svc: h3` is still there, Chrome will keep failing Join even when Admin Test Connection succeeds (that check uses HTTP/1.1).

On the VPS as root, this turns off HTTP/3 for OpenLiteSpeed and opens media ports:

```bash
curl -fsS -o /opt/livekit/fix-ols-http3.sh https://edexcel.college/tools/livekit-setup/fix-ols-http3.sh
bash /opt/livekit/fix-ols-http3.sh
```

Confirm:

```bash
curl -sI --http1.1 https://live.kandy.edexcel.college/ | grep -i alt
```

Empty output (no `alt-svc: h3`) is what you want. Then hard-refresh the class and Join again.

Optional: in the same vhost, turn off QUIC/HTTP3 if CyberPanel shows that toggle.

## 3. Firewall and cloud security group

On the VPS:

```bash
ufw allow 443/tcp
ufw allow 7881/tcp
ufw allow 3478/udp
ufw allow 50000:50100/udp
```

If the VPS is IBM Cloud / another panel, also open **UDP 50000–50100** and **UDP 3478** on the **cloud security group**, not only `ufw`. TCP 443 is not enough for WebRTC.

In `livekit.yaml`, if students connect but have no audio/video, set:

```yaml
rtc:
  use_external_ip: true
  node_ip: 169.58.123.255
```

then restart the `livekit` container.

## 4. Recheck

1. Admin → Live classroom → **Test Connection** (should mention WebSocket working, or still tell you `/rtc` did not upgrade).
2. Hard-refresh the class room and click **Join class**.
3. Chrome → F12 → Network → WS: you should see `/rtc` status **101**.

## 5. Chrome still fails after HTTP/3 is off

The server may no longer send `alt-svc: h3`, but Chrome caches HTTP/3 for 30 days. Join from a **new Incognito window**.

If Incognito still fails, skip OpenLiteSpeed for WebSocket: terminate TLS on **8443** (Caddy HTTP/1.1 + HTTP/2 only).

```bash
curl -fsS -o /opt/livekit/setup-ws8443.sh https://edexcel.college/tools/livekit-setup/setup-ws8443.sh
bash /opt/livekit/setup-ws8443.sh
```

Then Admin → Live classroom → LiveKit URL = `wss://live.kandy.edexcel.college:8443` → Save → Test Connection → Join in Incognito.

If `https://live.kandy.edexcel.college:8443/` returns **502** and `https://live.kandy.edexcel.college/` returns **503**, LiveKit is not listening on 7880 (often after host-network broke Redis). Recover **without** host-network:

```bash
curl -fsS -o /opt/livekit/fix-signal-8443.sh https://edexcel.college/tools/livekit-setup/fix-signal-8443.sh
bash /opt/livekit/fix-signal-8443.sh
```

You want LiveKit `OK` on `127.0.0.1:7880` and HTTP **200** on `:8443`.

## 6. Screen share / camera timeout (Join works)

firewalld can be open and Share still fails: UDP 50000–50100 is bound by **docker-proxy** (101 sockets), which often never delivers RTP. Put LiveKit on the host network:

```bash
curl -fsS -o /opt/livekit/apply-hostnet.sh https://edexcel.college/tools/livekit-setup/apply-hostnet.sh
bash /opt/livekit/apply-hostnet.sh
```

`ss` for `:50000` must show `livekit` / `livekit-server`, not `docker-proxy`. Then leave the class, Join again, and Share.



1. Admin → Live classroom → **Test Connection** (should mention WebSocket working, or still tell you `/rtc` did not upgrade).
2. Hard-refresh the class room and click **Join class**.
3. Chrome → F12 → Network → WS: you should see `/rtc` status **101**.
