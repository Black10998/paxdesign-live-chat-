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
TEAM_ID = os.environ.get(
    "APNS_TEAM_ID",
    os.environ.get("PAX_APNS_TEAM_ID", "4ZSP8S5A7B"),
).strip()
BUNDLE_ID = os.environ.get("PAX_APNS_BUNDLE_ID", "at.paxdesign.livechat").strip()
KEY_ID = os.environ.get(
    "APNS_KEY_ID",
    os.environ.get("PAX_APNS_KEY_ID", ""),
).strip()
EXPECTED_PLUGIN = os.environ.get("PAX_EXPECTED_PLUGIN", "3.108.14").strip()
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
        "prepare_apns_key",
        Path(__file__).resolve().parent / "appstore-connect" / "prepare-apns-key.py",
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
    for attempt in range(3):
        req = urllib.request.Request(
            f"{SITE}/",
            headers={"User-Agent": "PAXdesign-APNs-Configure/1.0"},
        )
        try:
            html = urllib.request.urlopen(req, timeout=30).read().decode("utf-8", errors="replace")
            break
        except urllib.error.HTTPError as exc:
            if exc.code in {403, 429, 503} and attempt < 2:
                print(f"WARN: Homepage returned {exc.code}; retrying version probe")
                time.sleep(5)
                continue
            raise
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


def bootstrap_auth_works(key_p8: str) -> bool:
    jwt_token = make_bootstrap_jwt(key_p8)
    status, payload = request(
        "POST",
        "/system/bootstrap/update",
        body=bootstrap_body(key_p8, include_apns=False),
        auth_header=f"Bearer {jwt_token}",
        allow_404=True,
    )
    if status == 404:
        return False
    if status == 403 and payload.get("code") == "asc_auth_invalid":
        return False
    if status == 403:
        # JWT accepted; endpoint rejected the action (e.g. update_plugins on older builds).
        return payload.get("code") != "asc_auth_invalid"
    return status == 200


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

    if current == "3.108.13" and EXPECTED_PLUGIN == "3.108.14" and bootstrap_auth_works(key_p8):
        print(
            f"WARN: Production is on v{current}; continuing with bootstrap APNs verification. "
            f"Update to v{EXPECTED_PLUGIN} for the latest provider JWT fix."
        )
        ok(f"Production plugin v{current}")
        return

    if current == "3.108.11" and EXPECTED_PLUGIN in {"3.108.12", "3.108.13", "3.108.14"} and bootstrap_auth_works(key_p8):
        print(
            f"WARN: Production is on v{current}; continuing with bootstrap APNs verification. "
            f"Update to v{EXPECTED_PLUGIN} for the latest APNs delivery fixes."
        )
        ok(f"Production plugin v{current}")
        return

    if current == "3.108.10" and EXPECTED_PLUGIN == "3.108.11" and bootstrap_auth_works(key_p8):
        print(
            "WARN: Production is on v3.108.10; continuing with bootstrap APNs verification. "
            "Update to v3.108.11 for bootstrap test push and remote self-update."
        )
        ok(f"Production plugin v{current}")
        return

    if current == "3.108.9" and EXPECTED_PLUGIN == "3.108.10" and bootstrap_endpoint_available():
        if not bootstrap_auth_works(key_p8):
            fail(
                "Production is on v3.108.9 with a broken ASC bootstrap verifier (OpenSSL 3.x). "
                "Update the plugin to v3.108.10 once from WordPress Admin → Dashboard → Updates, "
                "then rerun this workflow."
            )

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
        f"(team_id={payload.get('team_id')}, bundle_id={payload.get('bundle_id')}, "
        f"key_id={payload.get('key_id')}, devices={payload.get('device_total', 0)})"
    )
    return payload


def verify_devices_bootstrap(key_p8: str) -> None:
    jwt_token = make_bootstrap_jwt(key_p8)
    status, payload = request(
        "POST",
        "/system/bootstrap/devices",
        body=bootstrap_body(key_p8, include_apns=False),
        auth_header=f"Bearer {jwt_token}",
        allow_404=True,
    )
    if status == 404:
        status, payload = request(
            "POST",
            "/system/bootstrap/apns",
            body=bootstrap_body(key_p8),
            auth_header=f"Bearer {jwt_token}",
        )
        if status == 200:
            total = int(payload.get("device_total") or 0)
            ok(f"APNs status reachable via bootstrap; active_devices={total}")
            if total <= 0:
                print(
                    "WARN: No active device tokens on production yet. "
                    "Open TestFlight Build 86, enable notifications, and log in."
                )
            return
        fail(f"Bootstrap device status failed ({status}): {payload}")

    if status != 200:
        fail(f"Bootstrap device list failed ({status}): {payload}")

    devices = payload.get("devices") or []
    active_total = int(payload.get("active_total") or len(devices))
    ok(f"Device API reachable via bootstrap; active_devices={active_total}")
    if active_total <= 0:
        print(
            "WARN: No active device tokens on production yet. "
            "Open TestFlight Build 86, enable notifications, and log in."
        )
        return

    for device in devices:
        prefix = device.get("token_prefix") or "unknown"
        name = device.get("device_name") or device.get("device_model") or "device"
        updated = device.get("updated_at") or 0
        bundle = device.get("bundle_id") or "(default)"
        marker = " [iPhone]" if name == "iPhone" or "iphone" in name.lower() else ""
        print(
            f"  device={name}{marker} token_prefix={prefix}... sandbox={device.get('sandbox')} "
            f"bundle_id={bundle} updated_at={updated}"
        )


def verify_apns_provider_token_direct(key_p8: str) -> None:
    """Probe Apple with a dummy device token to validate the provider JWT."""
    import jwt

    try:
        import httpx
    except ImportError:
        print("SKIP: httpx not installed for direct APNs provider token probe")
        return

    provider = jwt.encode(
        {"iss": TEAM_ID, "iat": int(time.time())},
        key_p8,
        algorithm="ES256",
        headers={"alg": "ES256", "kid": KEY_ID, "typ": "JWT"},
    )
    dummy_token = "0" * 64
    url = f"https://api.push.apple.com/3/device/{dummy_token}"
    headers = {
        "authorization": f"bearer {provider}",
        "apns-topic": BUNDLE_ID,
        "apns-push-type": "alert",
        "apns-priority": "10",
        "content-type": "application/json",
    }
    payload = json.dumps(
        {
            "aps": {
                "alert": {"title": "Provider probe", "body": "APNs provider token validation"},
                "sound": "default",
            }
        }
    )
    with httpx.Client(http2=True) as client:
        resp = client.post(url, headers=headers, content=payload, timeout=15)

    reason = ""
    try:
        reason = resp.json().get("reason", "")
    except Exception:  # noqa: BLE001
        reason = resp.text

    if resp.status_code == 200:
        ok("Apple accepted APNs provider token (unexpected 200 on dummy token)")
        return

    if reason == "BadDeviceToken":
        ok(
            f"Apple accepted APNs provider token (HTTP {resp.status_code} BadDeviceToken; "
            f"kid=...{KEY_ID[-4:]}, team_id={TEAM_ID})"
        )
        print(f"Apple APNs provider probe response: {json.dumps({'status': resp.status_code, 'reason': reason})}")
        return

    if reason == "InvalidProviderToken":
        fail(
            "Apple rejected the APNs provider token (InvalidProviderToken). "
            "Verify APNS_KEY_ID, APNS_TEAM_ID, and APNS_KEY_P8_BASE64 in GitHub secrets "
            "match the APNs Auth Key in Apple Developer → Keys."
        )

    fail(f"Unexpected Apple APNs provider probe response HTTP {resp.status_code}: {reason or resp.text}")


def send_test_push_bootstrap(key_p8: str, scenario: str = "new_customer_message") -> bool:
    jwt_token = make_bootstrap_jwt(key_p8)
    body = bootstrap_body(key_p8, include_apns=False)
    body["scenario"] = scenario
    status, payload = request(
        "POST",
        "/system/bootstrap/apns/test",
        body=body,
        auth_header=f"Bearer {jwt_token}",
        allow_404=True,
    )
    if status == 404:
        print(f"SKIP: Bootstrap APNs test endpoint not available yet ({scenario})")
        return False
    if status != 200:
        fail(f"Bootstrap APNs test request failed for {scenario} ({status}): {payload}")
    if payload.get("sent"):
        ok(
            f"Test push sent ({scenario}) delivered={payload.get('sent_count', 0)} "
            f"attempts={len(payload.get('attempts') or [])}"
        )
    else:
        print(f"WARN: APNs test not delivered for {scenario}")

    for attempt in payload.get("attempts") or []:
        status = attempt.get("apns_http_status", 0)
        prefix = attempt.get("token_prefix") or "unknown"
        name = attempt.get("device_name") or "device"
        sandbox = attempt.get("sandbox")
        apple_body = attempt.get("apple_response") or ""
        apple_reason = attempt.get("error") or ("accepted" if attempt.get("sent") else "unknown")
        primary_env = attempt.get("primary_env") or ""
        primary_reason = attempt.get("primary_reason") or ""
        alternate_env = attempt.get("alternate_env") or ""
        alternate_reason = attempt.get("alternate_reason") or ""
        alternate_body = attempt.get("alternate_body") or ""
        marker = " [iPhone]" if name == "iPhone" or prefix == "7c2aaeb89616" else ""
        env_detail = ""
        if primary_env:
            env_detail = f" primary={primary_env}:{primary_reason}"
        if alternate_env:
            env_detail += f" alternate={alternate_env}:{alternate_reason}"
        if attempt.get("sent"):
            ok(
                f"  device={name}{marker} token_prefix={prefix}... sandbox={sandbox} "
                f"apns_http_status={status} apple_response={apple_reason}"
            )
        else:
            print(
                f"WARN:  device={name}{marker} token_prefix={prefix}... sandbox={sandbox} "
                f"apns_http_status={status} apple_response={apple_body or apple_reason}{env_detail}"
            )
        if alternate_body:
            print(f"       alternate_apple_response={alternate_body}")

    print(f"Apple APNs test response ({scenario}): {json.dumps(payload, indent=2)}")
    return bool(payload.get("sent"))


def verify_notification_paths_bootstrap(key_p8: str) -> None:
    results = {
        "new_customer_message": send_test_push_bootstrap(key_p8, "new_customer_message"),
        "live_request": send_test_push_bootstrap(key_p8, "live_request"),
    }
    if not any(results.values()):
        fail(
            "No APNs test scenario delivered to any device. "
            f"Results: {results}. If iPhone token_prefix is unchanged, delete and reinstall "
            "the TestFlight app to force a fresh APNs token, then log in again."
        )


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


def verify_bootstrap_jwt_locally(key_p8: str) -> None:
    """Ensure the ASC key can produce a JWT our WordPress bootstrap verifier accepts."""
    import jwt

    token = make_bootstrap_jwt(key_p8)
    parts = token.split(".")
    if len(parts) != 3:
        fail("Bootstrap JWT self-test failed: malformed token")

    def b64url_decode(data: str) -> bytes:
        remainder = len(data) % 4
        if remainder > 0:
            data += "=" * (4 - remainder)
        return base64.urlsafe_b64decode(data)

    def jose_to_der(jose: bytes) -> bytes:
        if len(jose) < 64:
            return b""
        r = jose[:32].lstrip(b"\x00")
        s = jose[32:64].lstrip(b"\x00")
        if r and (r[0] & 0x80):
            r = b"\x00" + r
        if s and (s[0] & 0x80):
            s = b"\x00" + s
        seq = bytes([0x02, len(r)]) + r + bytes([0x02, len(s)]) + s
        return bytes([0x30, len(seq)]) + seq

    header = json.loads(b64url_decode(parts[0]))
    claims = json.loads(b64url_decode(parts[1]))
    if header.get("alg") != "ES256" or header.get("kid") != KEY_ID:
        fail("Bootstrap JWT self-test failed: unexpected header")
    if claims.get("iss") != TEAM_ID or claims.get("aud") != BOOTSTRAP_AUDIENCE:
        fail("Bootstrap JWT self-test failed: unexpected claims")

    from cryptography.hazmat.primitives import hashes, serialization
    from cryptography.hazmat.primitives.asymmetric import ec

    private_key = serialization.load_pem_private_key(key_p8.encode("utf-8"), password=None)
    public_key = private_key.public_key()
    der = jose_to_der(b64url_decode(parts[2]))
    if der == b"":
        fail("Bootstrap JWT self-test failed: invalid ES256 signature encoding")

    try:
        public_key.verify(der, (parts[0] + "." + parts[1]).encode("utf-8"), ec.ECDSA(hashes.SHA256()))
    except Exception as exc:  # noqa: BLE001
        fail(f"Bootstrap JWT self-test failed: {exc}")

    ok("Bootstrap JWT self-test passed locally")


def main() -> None:
    print(f"=== Remote APNs configuration ({SITE}) ===")

    if not KEY_ID:
        fail("APNS_KEY_ID is required")

    key_p8 = load_p8_key()
    verify_bootstrap_jwt_locally(key_p8)
    verify_apns_provider_token_direct(key_p8)
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
        verify_devices_bootstrap(key_p8)
        verify_notification_paths_bootstrap(key_p8)


if __name__ == "__main__":
    main()
