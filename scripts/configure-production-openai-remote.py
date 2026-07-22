#!/usr/bin/env python3
"""Configure production WordPress OpenAI key via ASC bootstrap or admin REST."""

from __future__ import annotations

import base64
import importlib.util
import json
import os
import sys
import time
import urllib.error
import urllib.request
from pathlib import Path

SITE = os.environ.get("PAX_SITE", "https://paxdesign.at").rstrip("/")
ADMIN_USER = os.environ.get("PAX_ADMIN_USER", "").strip()
ADMIN_PASS = os.environ.get("PAX_ADMIN_APP_PASSWORD", "").strip()
OPENAI_KEY = os.environ.get("PAX_OPENAI_API_KEY", "").strip()
TEAM_ID = os.environ.get(
    "APNS_TEAM_ID",
    os.environ.get("PAX_APNS_TEAM_ID", "4ZSP8S5A7B"),
).strip()
KEY_ID = os.environ.get(
    "APNS_KEY_ID",
    os.environ.get("PAX_APNS_KEY_ID", ""),
).strip()
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


def bootstrap_body(key_p8: str) -> dict:
    return {
        "key_id": KEY_ID,
        "team_id": TEAM_ID,
        "key_p8": key_p8,
        "openai_api_key": OPENAI_KEY,
    }


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
        "User-Agent": "PAXdesign-OpenAI-Configure/1.0",
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


def configure_openai_bootstrap(key_p8: str) -> dict | None:
    jwt_token = make_bootstrap_jwt(key_p8)
    status, payload = request(
        "POST",
        "/system/bootstrap/openai",
        body=bootstrap_body(key_p8),
        auth_header=f"Bearer {jwt_token}",
        allow_404=True,
    )
    if status == 404:
        return None
    if status != 200:
        fail(f"ASC bootstrap OpenAI configure failed ({status}): {payload}")
    if not payload.get("configured"):
        fail(f"ASC bootstrap OpenAI response did not mark backend as configured: {payload}")
    ok(
        "OpenAI key saved via ASC bootstrap "
        f"(model={payload.get('model')}, prefix={payload.get('key_prefix')})"
    )
    return payload


def configure_openai_admin() -> dict:
    status, payload = request("GET", "/live-admin/system/openai", allow_404=True)
    if status == 404:
        fail(
            "No OpenAI configure endpoint on production. Deploy plugin v3.172.7 or newer, then rerun."
        )
    if status != 200:
        fail(f"OpenAI status request failed ({status}): {payload}")

    status, payload = request(
        "POST",
        "/live-admin/system/openai",
        body={"openai_api_key": OPENAI_KEY},
    )
    if status != 200:
        fail(f"OpenAI configure request failed ({status}): {payload}")
    if not payload.get("configured"):
        fail(f"OpenAI configure response did not mark backend as configured: {payload}")
    ok(
        "OpenAI key saved via admin REST "
        f"(model={payload.get('model')}, prefix={payload.get('key_prefix')})"
    )
    return payload


def verify_openai_test() -> None:
    status, payload = request("POST", "/live-admin/system/openai/test", body={}, allow_404=True)
    if status == 404:
        print("SKIP: Admin OpenAI test endpoint not available yet")
        return
    if status != 200:
        fail(f"OpenAI test request failed ({status}): {payload}")
    ok(f"OpenAI connection verified (model={payload.get('model')})")


def main() -> None:
    print(f"=== Remote OpenAI configuration ({SITE}) ===")

    if not OPENAI_KEY:
        fail("PAX_OPENAI_API_KEY is required")
    if not OPENAI_KEY.startswith("sk-"):
        fail("PAX_OPENAI_API_KEY must start with sk-")

    bootstrap = None
    if KEY_ID:
        try:
            key_p8 = load_p8_key()
            bootstrap = configure_openai_bootstrap(key_p8)
        except SystemExit:
            raise
        except Exception as exc:  # noqa: BLE001
            print(f"WARN: Bootstrap configure unavailable ({exc})")

    if bootstrap is None:
        if not ADMIN_USER or not ADMIN_PASS:
            fail(
                "OpenAI bootstrap endpoint unavailable and PAX_ADMIN credentials are missing. "
                "Deploy plugin v3.172.7 first or add PAX_ADMIN_USER/PAX_ADMIN_APP_PASSWORD."
            )
        configure_openai_admin()

    verify_openai_test()


if __name__ == "__main__":
    main()
