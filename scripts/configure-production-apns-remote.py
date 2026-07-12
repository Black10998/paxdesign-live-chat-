#!/usr/bin/env python3
"""Configure production WordPress APNs via authenticated REST API."""

from __future__ import annotations

import base64
import importlib.util
import json
import os
import re
import sys
import time
import urllib.error
import urllib.parse
import urllib.request
from pathlib import Path

SITE = os.environ.get("PAX_SITE", "https://paxdesign.at").rstrip("/")
ADMIN_USER = os.environ.get("PAX_ADMIN_USER", "").strip()
ADMIN_PASS = os.environ.get("PAX_ADMIN_APP_PASSWORD", "").strip()
TEAM_ID = os.environ.get("PAX_APNS_TEAM_ID", "4ZSP8S5A7B").strip()
BUNDLE_ID = os.environ.get("PAX_APNS_BUNDLE_ID", "at.paxdesign.livechat").strip()
KEY_ID = os.environ.get("APP_STORE_CONNECT_API_KEY_ID", os.environ.get("PAX_APNS_KEY_ID", "")).strip()
EXPECTED_PLUGIN = os.environ.get("PAX_EXPECTED_PLUGIN", "3.108.7").strip()
WAIT_SECONDS = int(os.environ.get("PAX_PLUGIN_WAIT_SECONDS", "900"))


def fail(message: str) -> None:
    print(f"FAIL: {message}", file=sys.stderr)
    sys.exit(1)


def ok(message: str) -> None:
    print(f"PASS: {message}")


def load_p8_key() -> str:
    spec = importlib.util.spec_from_file_location(
        "prepare_asc_key",
        Path(__file__).resolve().parent / "appstore-connect" / "prepare-asc-key.py",
    )
    module = importlib.util.module_from_spec(spec)
    assert spec.loader is not None
    spec.loader.exec_module(module)
    return module.load_key()


def auth_header() -> str:
    if not ADMIN_USER or not ADMIN_PASS:
        fail("PAX_ADMIN_USER and PAX_ADMIN_APP_PASSWORD are required for remote APNs configuration")
    token = base64.b64encode(f"{ADMIN_USER}:{ADMIN_PASS}".encode()).decode()
    return f"Basic {token}"


def request(method: str, path: str, *, body: dict | None = None, allow_404: bool = False):
    url = f"{SITE}/wp-json/paxdesign/v1{path}"
    data = None
    headers = {
        "Accept": "application/json",
        "User-Agent": "PAXdesign-APNs-Configure/1.0",
        "Authorization": auth_header(),
    }
    if body is not None:
        data = json.dumps(body).encode("utf-8")
        headers["Content-Type"] = "application/json"
    req = urllib.request.Request(url, data=data, headers=headers, method=method)
    try:
        with urllib.request.urlopen(req, timeout=60) as resp:
            raw = resp.read().decode("utf-8")
            return resp.status, json.loads(raw) if raw else {}
    except urllib.error.HTTPError as exc:
        if allow_404 and exc.code == 404:
            return 404, {}
        detail = exc.read().decode("utf-8", errors="replace")
        try:
            payload = json.loads(detail) if detail else {}
        except json.JSONDecodeError:
            payload = {"raw": detail}
        return exc.code, payload


def homepage_plugin_version() -> str:
    req = urllib.request.Request(
        f"{SITE}/",
        headers={"User-Agent": "PAXdesign-APNs-Configure/1.0"},
    )
    html = urllib.request.urlopen(req, timeout=30).read().decode("utf-8", errors="replace")
    for part in html.split("chat-script.js?ver="):
        if part[:1].isdigit():
            return part.split('"', 1)[0].split("'", 1)[0]
    return ""


def wait_for_plugin_version() -> None:
    deadline = time.time() + WAIT_SECONDS
    while time.time() < deadline:
        version = homepage_plugin_version()
        if version == EXPECTED_PLUGIN:
            ok(f"Production plugin v{version}")
            return
        print(f"Waiting for plugin v{EXPECTED_PLUGIN}; production reports v{version or 'unknown'}")
        time.sleep(20)
    fail(f"Production plugin did not reach v{EXPECTED_PLUGIN} within {WAIT_SECONDS}s")


def main() -> None:
    print(f"=== Remote APNs configuration ({SITE}) ===")

    if not KEY_ID:
        fail("APP_STORE_CONNECT_API_KEY_ID is required")

    wait_for_plugin_version()

    status, payload = request("GET", "/live-admin/system/apns", allow_404=True)
    if status == 404:
        fail(
            "APNs system endpoint not found. Production must run plugin "
            f"v{EXPECTED_PLUGIN} or newer before remote configuration."
        )
    if status != 200:
        fail(f"APNs status request failed ({status}): {payload}")

    if payload.get("configured") and payload.get("key_id") == KEY_ID:
        ok(f"APNs already configured (key_id={payload.get('key_id')}, devices={payload.get('device_total', 0)})")
    else:
        key_p8 = load_p8_key()
        status, payload = request(
            "POST",
            "/live-admin/system/apns",
            body={
                "key_id": KEY_ID,
                "team_id": TEAM_ID,
                "bundle_id": BUNDLE_ID,
                "key_p8": key_p8,
            },
        )
        if status != 200:
            fail(f"APNs configure request failed ({status}): {payload}")
        if not payload.get("configured"):
            fail(f"APNs configure response did not mark backend as configured: {payload}")
        ok(
            "APNs credentials saved on production "
            f"(team_id={payload.get('team_id')}, bundle_id={payload.get('bundle_id')}, key_id={payload.get('key_id')})"
        )

    status, devices_payload = request("GET", "/live-admin/devices")
    if status == 200:
        devices = devices_payload.get("devices") or []
        active = [d for d in devices if not d.get("revoked")]
        ok(f"Device API reachable; active_devices={len(active)} total={len(devices)}")
    else:
        print(f"WARN: Device list unavailable ({status}): {devices_payload}")

    status, test_payload = request("POST", "/live-admin/system/apns/test", body={})
    if status != 200:
        fail(f"APNs test request failed ({status}): {test_payload}")
    if test_payload.get("sent"):
        ok(f"Test push sent to user_id={test_payload.get('user_id')}")
    else:
        print(
            "WARN: APNs configured but no active device token yet. "
            "Open TestFlight Build 86, enable notifications, and log in."
        )


if __name__ == "__main__":
    main()
