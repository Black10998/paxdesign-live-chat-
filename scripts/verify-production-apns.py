#!/usr/bin/env python3
"""Verify production WordPress push backend via public REST endpoints."""

from __future__ import annotations

import json
import os
import sys
import urllib.error
import urllib.parse
import urllib.request

SITE = os.environ.get("PAX_SITE", "https://paxdesign.at").rstrip("/")
ADMIN_USER = os.environ.get("PAX_ADMIN_USER", "").strip()
ADMIN_PASS = os.environ.get("PAX_ADMIN_APP_PASSWORD", "").strip()
EXPECTED_PLUGIN = os.environ.get("PAX_EXPECTED_PLUGIN", "3.108.7").strip()


def fail(message: str) -> None:
    print(f"FAIL: {message}", file=sys.stderr)
    sys.exit(1)


def ok(message: str) -> None:
    print(f"PASS: {message}")


DEFAULT_HEADERS = {
    "Accept": "application/json",
    "User-Agent": "PAXdesign-Production-Verify/1.0",
}


def request(method: str, url: str, *, auth: tuple[str, str] | None = None, body: dict | None = None):
    data = None
    headers = dict(DEFAULT_HEADERS)
    if body is not None:
        data = json.dumps(body).encode("utf-8")
        headers["Content-Type"] = "application/json"
    req = urllib.request.Request(url, data=data, headers=headers, method=method)
    if auth:
        import base64

        token = base64.b64encode(f"{auth[0]}:{auth[1]}".encode()).decode()
        req.add_header("Authorization", f"Basic {token}")
    try:
        with urllib.request.urlopen(req, timeout=30) as resp:
            raw = resp.read().decode("utf-8")
            return resp.status, json.loads(raw) if raw else {}
    except urllib.error.HTTPError as exc:
        detail = exc.read().decode("utf-8", errors="replace")
        try:
            payload = json.loads(detail) if detail else {}
        except json.JSONDecodeError:
            payload = {"raw": detail}
        return exc.code, payload


def main() -> None:
    print(f"=== Production APNs verification ({SITE}) ===")

    home_req = urllib.request.Request(f"{SITE}/", headers={"User-Agent": DEFAULT_HEADERS["User-Agent"]})
    html = urllib.request.urlopen(home_req, timeout=30).read().decode("utf-8", errors="replace")
    asset_ver = ""
    for part in html.split("chat-script.js?ver="):
        if part[:1].isdigit():
            asset_ver = part.split('"', 1)[0].split("'", 1)[0]
            break
    if asset_ver != EXPECTED_PLUGIN:
        fail(f"Production plugin assets report v{asset_ver or 'unknown'}; expected v{EXPECTED_PLUGIN}")
    ok(f"Production plugin v{asset_ver}")

    status, payload = request("POST", f"{SITE}/wp-json/paxdesign/v1/live-admin/push/apns", body={})
    if status not in {401, 403}:
        fail(f"Push register endpoint unexpected status {status}: {payload}")
    ok("Push register endpoint exists and requires authentication")

    if ADMIN_USER and ADMIN_PASS:
        status, apns_status = request("GET", f"{SITE}/wp-json/paxdesign/v1/live-admin/system/apns", auth=(ADMIN_USER, ADMIN_PASS))
        if status == 200:
            if apns_status.get("configured"):
                ok(
                    "APNs configured on production "
                    f"(key_id={apns_status.get('key_id')}, devices={apns_status.get('device_total', 0)})"
                )
            else:
                fail("APNs is not configured on production yet")
        elif status != 404:
            fail(f"APNs status check failed ({status}): {apns_status}")

    if not ADMIN_USER or not ADMIN_PASS:
        print("SKIP: Device list verification requires PAX_ADMIN_USER and PAX_ADMIN_APP_PASSWORD")
        return

    status, payload = request(
        "GET",
        f"{SITE}/wp-json/paxdesign/v1/live-admin/devices",
        auth=(ADMIN_USER, ADMIN_PASS),
    )
    if status != 200:
        fail(f"Device list failed ({status}): {payload}")

    devices = payload.get("devices") or []
    active = [d for d in devices if not d.get("revoked")]
    ok(f"Device API reachable; active_devices={len(active)} total={len(devices)}")
    if not active:
        print(
            "WARN: No active device tokens on production yet. "
            "Open TestFlight build 86, enable notifications, and log in."
        )
        return

    for device in active[:3]:
        token = (device.get("token") or device.get("device_token") or "")[:12]
        name = device.get("device_name") or device.get("device_id") or "device"
        print(f"  device={name} token_prefix={token}... sandbox={device.get('sandbox')}")


if __name__ == "__main__":
    main()
