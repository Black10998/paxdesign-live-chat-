#!/usr/bin/env python3
"""Verify App Store Connect API credentials before TestFlight upload."""

from __future__ import annotations

import json
import os
import sys
import time
import urllib.error
import urllib.parse
import urllib.request

try:
    import jwt
except ImportError:
    jwt = None

import importlib.util
from pathlib import Path

_SPEC = importlib.util.spec_from_file_location(
    "prepare_asc_key",
    Path(__file__).resolve().parent / "prepare-asc-key.py",
)
_PREPARE = importlib.util.module_from_spec(_SPEC)
assert _SPEC.loader is not None
_SPEC.loader.exec_module(_PREPARE)
fail = _PREPARE.fail
load_key = _PREPARE.load_key
validate_key = _PREPARE.validate_key


API_BASE = "https://api.appstoreconnect.apple.com/v1"
BUNDLE_ID = os.environ.get("APP_BUNDLE_ID", "at.paxdesign.livechat")


def make_token(issuer_id: str, key_id: str, private_key: str) -> str:
    if jwt is None:
        fail("PyJWT is required (pip install PyJWT cryptography)")
    now = int(time.time())
    headers = {"alg": "ES256", "kid": key_id, "typ": "JWT"}
    payload = {"iss": issuer_id, "exp": now + 1200, "aud": "appstoreconnect-v1"}
    return jwt.encode(payload, private_key, algorithm="ES256", headers=headers)


def api_get(token: str, path: str, params: dict[str, str] | None = None) -> dict:
    url = API_BASE + path
    if params:
        url += "?" + urllib.parse.urlencode(params)
    request = urllib.request.Request(
        url,
        headers={"Authorization": f"Bearer {token}"},
        method="GET",
    )
    try:
        with urllib.request.urlopen(request, timeout=60) as response:
            return json.loads(response.read().decode("utf-8"))
    except urllib.error.HTTPError as exc:
        body = exc.read().decode("utf-8", errors="replace")
        if exc.code == 401:
            fail(
                "App Store Connect API authentication failed (401). "
                "Verify APP_STORE_CONNECT_API_KEY_ID matches AuthKey_XXXX.p8, "
                "APP_STORE_CONNECT_ISSUER_ID is correct, and the API key is active with App Manager access. "
                f"Response: {body}"
            )
        fail(f"App Store Connect API check failed ({exc.code}): {body}")


def main() -> None:
    issuer_id = os.environ.get("APP_STORE_CONNECT_ISSUER_ID", "").strip()
    key_id = os.environ.get("APP_STORE_CONNECT_API_KEY_ID", "").strip()
    if not issuer_id or not key_id:
        fail("Missing APP_STORE_CONNECT_ISSUER_ID or APP_STORE_CONNECT_API_KEY_ID")

    private_key = load_key()
    validate_key(private_key)

    key_dir = os.path.join(os.path.expanduser("~"), ".appstoreconnect", "private_keys")
    os.makedirs(key_dir, exist_ok=True)
    key_path = os.path.join(key_dir, f"AuthKey_{key_id}.p8")
    with open(key_path, "w", encoding="utf-8") as handle:
        handle.write(private_key)
    os.chmod(key_path, 0o600)

    token = make_token(issuer_id, key_id, private_key)
    payload = api_get(token, "/apps", {"filter[bundleId]": BUNDLE_ID, "limit": "1"})
    apps = payload.get("data") or []
    if not apps:
        print("ERROR: Authenticated successfully, but filter[bundleId] returned no app.", file=sys.stderr)
        print(f"Target bundle id: {BUNDLE_ID}", file=sys.stderr)
        print("Apps visible to this API key:", file=sys.stderr)
        listing = api_get(token, "/apps", {"limit": "200"})
        for app in listing.get("data") or []:
            app_id = app.get("id", "")
            name = (app.get("attributes") or {}).get("name", app_id)
            bundle_payload = api_get(token, f"/apps/{app_id}/bundleId", None)
            bundle_data = bundle_payload.get("data") or {}
            identifier = (bundle_data.get("attributes") or {}).get("identifier", "?")
            print(f"  - apple_id={app_id} bundleId={identifier!r} name={name!r}", file=sys.stderr)
        fail(f"No App Store Connect app found for bundle id {BUNDLE_ID}")

    app_name = (apps[0].get("attributes") or {}).get("name", BUNDLE_ID)
    print(f"App Store Connect API credentials verified for {app_name}")


if __name__ == "__main__":
    main()
