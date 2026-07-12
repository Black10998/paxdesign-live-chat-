#!/usr/bin/env python3
"""Configure production WordPress APNs via ASC bootstrap or authenticated REST API."""

from __future__ import annotations

import base64
import importlib.util
import json
import os
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
EXPECTED_PLUGIN = os.environ.get("PAX_EXPECTED_PLUGIN", "3.108.9").strip()
HOSTINGER_TOKEN = os.environ.get("HOSTINGER_MANAGE_BEARER_TOKEN", "").strip()
HOSTINGER_API_TOKEN = os.environ.get("HOSTINGER_API_TOKEN", "").strip()
HOSTINGER_ACCOUNT = os.environ.get("HOSTINGER_ACCOUNT_USERNAME", "").strip()
HOSTINGER_WP_ID = os.environ.get("HOSTINGER_WP_SOFTWARE_ID", "").strip()
WAIT_SECONDS = int(os.environ.get("PAX_PLUGIN_WAIT_SECONDS", "900"))
BOOTSTRAP_AUDIENCE = "paxdesign-wordpress-bootstrap"


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


def make_bootstrap_jwt(key_p8: str) -> str:
    import jwt

    now = int(time.time())
    headers = {"alg": "ES256", "kid": KEY_ID, "typ": "JWT"}
    claims = {
        "iss": TEAM_ID,
        "iat": now,
        "exp": now + 300,
        "aud": BOOTSTRAP_AUDIENCE,
    }
    return jwt.encode(claims, key_p8, algorithm="ES256", headers=headers)


def bootstrap_body(key_p8: str, *, include_apns: bool = True) -> dict:
    body = {
        "key_id": KEY_ID,
        "team_id": TEAM_ID,
        "key_p8": key_p8,
    }
    if include_apns:
        body["bundle_id"] = BUNDLE_ID
    return body


def request(
    method: str,
    path: str,
    *,
    body: dict | None = None,
    allow_404: bool = False,
    auth_header: str | None = None,
):
    url = path if path.startswith("http") else f"{SITE}/wp-json/paxdesign/v1{path}"
    data = None
    headers = {
        "Accept": "application/json",
        "User-Agent": "PAXdesign-APNs-Configure/1.0",
    }
    if auth_header:
        headers["Authorization"] = auth_header
    elif ADMIN_USER and ADMIN_PASS:
        token = base64.b64encode(f"{ADMIN_USER}:{ADMIN_PASS}".encode()).decode()
        headers["Authorization"] = f"Basic {token}"
    if body is not None:
        data = json.dumps(body).encode("utf-8")
        headers["Content-Type"] = "application/json"
    req = urllib.request.Request(url, data=data, headers=headers, method=method)
    try:
        with urllib.request.urlopen(req, timeout=120) as resp:
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


def hostinger_manage_request(method: str, path: str, body: dict | None = None):
    if not HOSTINGER_TOKEN:
        return 0, {}
    url = f"{SITE}/wp-json{path}"
    data = json.dumps(body).encode("utf-8") if body is not None else None
    req = urllib.request.Request(
        url,
        data=data,
        headers={
            "Accept": "application/json",
            "Authorization": f"Bearer {HOSTINGER_TOKEN}",
            "Content-Type": "application/json",
            "User-Agent": "PAXdesign-APNs-Configure/1.0",
        },
        method=method,
    )
    try:
        with urllib.request.urlopen(req, timeout=120) as resp:
            raw = resp.read().decode("utf-8")
            return resp.status, json.loads(raw) if raw else {}
    except urllib.error.HTTPError as exc:
        detail = exc.read().decode("utf-8", errors="replace")
        try:
            payload = json.loads(detail) if detail else {}
        except json.JSONDecodeError:
            payload = {"raw": detail}
        return exc.code, payload


def hostinger_api_request(method: str, path: str, body: dict | None = None):
    if not HOSTINGER_API_TOKEN:
        return 0, {}
    url = f"https://developers.hostinger.com{path}"
    data = json.dumps(body).encode("utf-8") if body is not None else None
    req = urllib.request.Request(
        url,
        data=data,
        headers={
            "Accept": "application/json",
            "Authorization": f"Bearer {HOSTINGER_API_TOKEN}",
            "Content-Type": "application/json",
            "User-Agent": "PAXdesign-APNs-Configure/1.0",
        },
        method=method,
    )
    try:
        with urllib.request.urlopen(req, timeout=120) as resp:
            raw = resp.read().decode("utf-8")
            return resp.status, json.loads(raw) if raw else {}
    except urllib.error.HTTPError as exc:
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


def verify_admin_auth() -> bool:
    if not ADMIN_USER or not ADMIN_PASS:
        return False
    status, payload = request("GET", "/live-admin/me")
    if status != 200:
        print(f"WARN: Admin authentication failed ({status}): {payload}")
        return False
    ok(f"Authenticated as {payload.get('email') or payload.get('username') or ADMIN_USER}")
    return True


def trigger_bootstrap_plugin_update(key_p8: str) -> bool:
    jwt_token = make_bootstrap_jwt(key_p8)
    status, payload = request(
        "POST",
        "/system/bootstrap/update",
        body=bootstrap_body(key_p8, include_apns=False),
        auth_header=f"Bearer {jwt_token}",
        allow_404=True,
    )
    if status == 404:
        print("SKIP: ASC bootstrap update endpoint not available on current production version")
        return False
    if status != 200:
        print(f"WARN: ASC bootstrap update failed ({status}): {payload}")
        return False
    if payload.get("updated"):
        ok(f"Plugin updated via ASC bootstrap to v{payload.get('version')}")
    else:
        print(f"ASC bootstrap update not required: {payload.get('message') or payload}")
    return True


def has_plugin_update_mechanism() -> bool:
    return bool(
        HOSTINGER_API_TOKEN
        or HOSTINGER_TOKEN
        or (ADMIN_USER and ADMIN_PASS)
    )


def bootstrap_endpoint_available() -> bool:
    url = f"{SITE}/wp-json/paxdesign/v1/system/bootstrap/update"
    req = urllib.request.Request(
        url,
        headers={"User-Agent": "PAXdesign-APNs-Configure/1.0"},
        method="OPTIONS",
    )
    try:
        with urllib.request.urlopen(req, timeout=30) as resp:
            return resp.status in {200, 204}
    except urllib.error.HTTPError as exc:
        return exc.code != 404


def resolve_hostinger_installation() -> tuple[str, str]:
    global HOSTINGER_ACCOUNT, HOSTINGER_WP_ID
    if HOSTINGER_ACCOUNT and HOSTINGER_WP_ID:
        return HOSTINGER_ACCOUNT, HOSTINGER_WP_ID
    if not HOSTINGER_API_TOKEN:
        return "", ""

    status, payload = hostinger_api_request("GET", "/api/hosting/v1/wordpress/installations")
    if status != 200:
        print(f"WARN: Could not list Hostinger WordPress installations ({status}): {payload}")
        return "", ""

    installations = payload if isinstance(payload, list) else payload.get("data") or payload.get("installations") or []
    for item in installations:
        if not isinstance(item, dict):
            continue
        domain = str(item.get("domain") or item.get("url") or item.get("site_url") or "").lower()
        if "paxdesign.at" not in domain:
            continue
        account = str(item.get("username") or item.get("account") or item.get("account_username") or HOSTINGER_ACCOUNT)
        software = str(item.get("software") or item.get("id") or item.get("software_id") or "")
        if account and software:
            HOSTINGER_ACCOUNT = account
            HOSTINGER_WP_ID = software
            ok(f"Resolved Hostinger installation account={account} software={software}")
            return account, software

    print(f"WARN: No Hostinger installation matched {SITE}: {payload}")
    return "", ""


def trigger_plugin_update() -> None:
    if HOSTINGER_API_TOKEN:
        resolve_hostinger_installation()
    if HOSTINGER_API_TOKEN and HOSTINGER_ACCOUNT and HOSTINGER_WP_ID:
        status, payload = hostinger_api_request(
            "POST",
            f"/api/hosting/v1/accounts/{urllib.parse.quote(HOSTINGER_ACCOUNT)}/wordpress/{urllib.parse.quote(HOSTINGER_WP_ID)}/plugins/update",
            {"plugins": ["paxdesign-booking/paxdesign-booking.php"]},
        )
        if status == 200:
            ok(f"Hostinger API plugin update triggered: {payload}")
            return
        print(f"WARN: Hostinger API plugin update failed ({status}): {payload}")

    if HOSTINGER_TOKEN:
        status, payload = hostinger_manage_request(
            "POST",
            "/manage/v1/plugins/update",
            {"slug": "paxdesign-booking"},
        )
        if status == 200:
            ok(f"Hostinger plugin update triggered: {payload}")
            return
        print(f"WARN: Hostinger plugin update failed ({status}): {payload}")

    status, payload = request("POST", "/live-admin/system/plugin/update", body={}, allow_404=True)
    if status == 404:
        print("SKIP: Remote plugin update endpoint not available on current production version")
        return
    if status != 200:
        print(f"WARN: Plugin update request failed ({status}): {payload}")
        return
    if payload.get("updated"):
        ok(f"Plugin updated to v{payload.get('version')}")
    else:
        print(f"Plugin update not required: {payload.get('message') or payload}")


def wait_for_plugin_version(key_p8: str) -> None:
    current = homepage_plugin_version()
    if current == EXPECTED_PLUGIN:
        ok(f"Production plugin v{current}")
        return

    if not bootstrap_endpoint_available() and not has_plugin_update_mechanism():
        fail(
            f"Production is on v{current or 'unknown'} and no remote update path is available. "
            f"Add HOSTINGER_API_TOKEN, HOSTINGER_MANAGE_BEARER_TOKEN, or PAX_ADMIN credentials, "
            f"or update the plugin to v{EXPECTED_PLUGIN} once from WordPress admin."
        )

    deadline = time.time() + WAIT_SECONDS
    while time.time() < deadline:
        version = homepage_plugin_version()
        if version == EXPECTED_PLUGIN:
            ok(f"Production plugin v{version}")
            return
        print(f"Waiting for plugin v{EXPECTED_PLUGIN}; production reports v{version or 'unknown'}")
        trigger_bootstrap_plugin_update(key_p8)
        trigger_plugin_update()
        time.sleep(20)
    fail(f"Production plugin did not reach v{EXPECTED_PLUGIN} within {WAIT_SECONDS}s")


def configure_apns_bootstrap(key_p8: str) -> dict | None:
    jwt_token = make_bootstrap_jwt(key_p8)
    status, payload = request(
        "POST",
        "/system/bootstrap/apns",
        body=bootstrap_body(key_p8),
        auth_header=f"Bearer {jwt_token}",
        allow_404=True,
    )
    if status == 404:
        return None
    if status != 200:
        fail(f"ASC bootstrap APNs configure failed ({status}): {payload}")
    if not payload.get("configured"):
        fail(f"ASC bootstrap APNs response did not mark backend as configured: {payload}")
    ok(
        "APNs credentials saved via ASC bootstrap "
        f"(team_id={payload.get('team_id')}, bundle_id={payload.get('bundle_id')}, key_id={payload.get('key_id')})"
    )
    return payload


def configure_apns_admin(key_p8: str) -> dict:
    status, payload = request("GET", "/live-admin/system/apns", allow_404=True)
    if status == 200:
        if payload.get("configured") and payload.get("key_id") == KEY_ID:
            ok(
                "APNs already configured "
                f"(key_id={payload.get('key_id')}, devices={payload.get('device_total', 0)})"
            )
            return payload

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
        return payload

    status, payload = request(
        "POST",
        "/live-admin/push/apns",
        body={
            "configure_apns": True,
            "key_id": KEY_ID,
            "team_id": TEAM_ID,
            "bundle_id": BUNDLE_ID,
            "key_p8": key_p8,
        },
        allow_404=True,
    )
    if status == 404:
        fail(
            "No APNs configure endpoint on production. Update the plugin to "
            f"v{EXPECTED_PLUGIN} or newer, then rerun this workflow."
        )
    if status != 200:
        fail(f"APNs configure fallback failed ({status}): {payload}")

    status, payload = request("GET", "/live-admin/system/apns", allow_404=True)
    if status == 200 and payload.get("configured"):
        ok(
            "APNs credentials saved via register fallback "
            f"(key_id={payload.get('key_id')}, devices={payload.get('device_total', 0)})"
        )
        return payload

    ok("APNs configure request accepted via register fallback")
    return payload


def configure_apns(key_p8: str) -> dict:
    bootstrap = configure_apns_bootstrap(key_p8)
    if bootstrap is not None:
        return bootstrap
    if not ADMIN_USER or not ADMIN_PASS:
        fail(
            "APNs bootstrap endpoint unavailable and PAX_ADMIN credentials are missing. "
            "Deploy plugin v{0} first or add PAX_ADMIN_USER/PAX_ADMIN_APP_PASSWORD.".format(EXPECTED_PLUGIN)
        )
    return configure_apns_admin(key_p8)


def main() -> None:
    print(f"=== Remote APNs configuration ({SITE}) ===")

    if not KEY_ID:
        fail("APP_STORE_CONNECT_API_KEY_ID is required")

    key_p8 = load_p8_key()
    verify_admin_auth()
    trigger_bootstrap_plugin_update(key_p8)
    wait_for_plugin_version(key_p8)
    configure_apns(key_p8)

    if ADMIN_USER and ADMIN_PASS:
        status, devices_payload = request("GET", "/live-admin/devices")
        if status == 200:
            devices = devices_payload.get("devices") or []
            active = [d for d in devices if not d.get("revoked")]
            ok(f"Device API reachable; active_devices={len(active)} total={len(devices)}")
        else:
            print(f"WARN: Device list unavailable ({status}): {devices_payload}")

        status, test_payload = request("POST", "/live-admin/system/apns/test", body={}, allow_404=True)
        if status == 404:
            print("SKIP: APNs test endpoint not available yet")
            return
        if status != 200:
            fail(f"APNs test request failed ({status}): {test_payload}")
        if test_payload.get("sent"):
            ok(f"Test push sent to user_id={test_payload.get('user_id')}")
        else:
            print(
                "WARN: APNs configured but no active device token yet. "
                "Open TestFlight Build 86, enable notifications, and log in."
            )
    else:
        print(
            "SKIP: Device list and test push require PAX_ADMIN_USER/PAX_ADMIN_APP_PASSWORD "
            "(APNs credentials were configured via ASC bootstrap)."
        )


if __name__ == "__main__":
    main()
