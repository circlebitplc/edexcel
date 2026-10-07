#!/usr/bin/env python3
"""Move node_ip under rtc: (it is invalid under turn:). Keep keys and the rest of the file."""
from __future__ import annotations

import pathlib
import re
import shutil
import sys
import time

YAML = pathlib.Path("/opt/livekit/livekit.yaml")
IP = "169.58.123.255"


def is_top_level_key(line: str) -> bool:
    if not line.strip() or line.lstrip().startswith("#"):
        return False
    if line[0] in " \t":
        return False
    return bool(re.match(r"^[A-Za-z0-9_]+\s*:", line))


def main() -> int:
    if not YAML.is_file():
        print(f"Missing {YAML}", file=sys.stderr)
        return 1
    bak = YAML.with_name(f"livekit.yaml.bak.yamlfix.{time.strftime('%Y%m%d%H%M%S')}")
    shutil.copy2(YAML, bak)
    print(f"Backup: {bak}")

    lines = YAML.read_text(encoding="utf-8").splitlines()
    lines = [ln for ln in lines if not re.match(r"^[\t ]*node_ip\s*:", ln)]

    new: list[str] = []
    i = 0
    inserted = False
    while i < len(lines):
        ln = lines[i]
        if re.match(r"^rtc\s*:", ln):
            new.append(ln)
            i += 1
            body: list[str] = []
            while i < len(lines) and not is_top_level_key(lines[i]):
                body.append(lines[i])
                i += 1
            put = False
            has_ext = False
            has_udp = False
            has_tcp = False
            for b in body:
                if re.match(r"^[\t ]*port_range_start\s*:", b) or re.match(
                    r"^[\t ]*port_range_end\s*:", b
                ):
                    continue
                if re.match(r"^[\t ]*udp_port\s*:", b):
                    new.append("  udp_port: 50000")
                    has_udp = True
                    continue
                if re.match(r"^[\t ]*tcp_port\s*:", b):
                    new.append("  tcp_port: 7881")
                    has_tcp = True
                    continue
                if re.match(r"^[\t ]*use_external_ip\s*:", b):
                    new.append("  use_external_ip: false")
                    has_ext = True
                    if not put:
                        new.append(f"  node_ip: {IP}")
                        put = True
                    continue
                if re.match(r"^[\t ]*allow_tcp_fallback\s*:", b):
                    new.append("  allow_tcp_fallback: true")
                    continue
                new.append(b)
            if not has_tcp:
                new.append("  tcp_port: 7881")
            if not has_udp:
                new.append("  udp_port: 50000")
            if not has_ext:
                new.append("  use_external_ip: false")
            if not put:
                new.append(f"  node_ip: {IP}")
            if not any(re.match(r"^[\t ]*allow_tcp_fallback\s*:", x) for x in body):
                new.append("  allow_tcp_fallback: true")
            inserted = True
            continue
        if re.match(r"^turn\s*:", ln):
            new.append(ln)
            i += 1
            body = []
            while i < len(lines) and not is_top_level_key(lines[i]):
                body.append(lines[i])
                i += 1
            has_start = False
            has_end = False
            has_turn_udp = False
            for b in body:
                if re.match(r"^[\t ]*udp_port\s*:", b):
                    new.append("  udp_port: 3478")
                    has_turn_udp = True
                    continue
                if re.match(r"^[\t ]*relay_range_start\s*:", b):
                    new.append("  relay_range_start: 30000")
                    has_start = True
                    continue
                if re.match(r"^[\t ]*relay_range_end\s*:", b):
                    new.append("  relay_range_end: 30100")
                    has_end = True
                    continue
                new.append(b)
            if not has_turn_udp:
                new.append("  udp_port: 3478")
            if not has_start:
                new.append("  relay_range_start: 30000")
            if not has_end:
                new.append("  relay_range_end: 30100")
            continue
        new.append(ln)
        i += 1

    if not inserted:
        new = ["rtc:", f"  node_ip: {IP}", ""] + new

    YAML.write_text("\n".join(new) + "\n", encoding="utf-8")

    print("=== rtc / turn (node_ip must be under rtc only) ===")
    section = None
    for ln in YAML.read_text(encoding="utf-8").splitlines():
        if is_top_level_key(ln):
            section = ln.split(":", 1)[0].strip()
        if section in {"rtc", "turn"}:
            print(ln)
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
