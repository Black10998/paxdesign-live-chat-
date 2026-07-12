#!/usr/bin/env python3
"""Enable an App Store Connect build for internal TestFlight testing."""

from __future__ import annotations

import json
import os
import sys
import time
import urllib.error
import urllib.parse
import urllib.request
from typing import Any

try:
    import jwt
except ImportError:
    jwt = None


API_BASE = "https://api.appstoreconnect.apple.com/v1"
BUNDLE_ID = os.environ.get("APP_BUNDLE_ID", "at.paxdesign.livechat")
TESTER_EMAIL = os.environ.get(
    "TESTFLIGHT_INTERNAL_TESTER_EMAIL",
    os.environ.get("APPLE_ID_EMAIL", "awjime29@icloud.com"),
).strip().lower()
POLL_SECONDS = int(os.environ.get("TESTFLIGHT_POLL_SECONDS", "30"))
POLL_TIMEOUT = int(os.environ.get("TESTFLIGHT_POLL_TIMEOUT", "3600"))


def fail(message: str) -> None:
    print(f"ERROR: {message}", file=sys.stderr)
    sys.exit(1)


def load_private_key() -> str:
    raw = os.environ.get("APP_STORE_CONNECT_API_PRIVATE_KEY") or os.environ.get(
        "APPSTORE_API_PRIVATE_KEY", ""
    )
    if not raw:
        b64 = os.environ.get("APP_STORE_CONNECT_API_KEY_P8_BASE64", "")
        if b64:
            import base64

            raw = base64.b64decode("".join(b64.split())).decode("utf-8")
    raw = raw.strip()
    if not raw:
        fail("Missing App Store Connect API private key secret")
    raw = raw.strip('"')
    raw = raw.replace("\\n", "\n").replace("\r", "")
    if not raw.endswith("\n"):
        raw += "\n"
    if not openssl_validate_key(raw):
        fail("APP_STORE_CONNECT_API_PRIVATE_KEY is not a valid PKCS8 private key")
    return raw


def openssl_validate_key(raw: str) -> bool:
    import subprocess

    proc = subprocess.run(
        ["openssl", "pkey", "-noout"],
        input=raw.encode("utf-8"),
        capture_output=True,
        check=False,
    )
    return proc.returncode == 0


def load_config() -> tuple[str, str, str]:
    issuer_id = (
        os.environ.get("APP_STORE_CONNECT_ISSUER_ID")
        or os.environ.get("APPSTORE_ISSUER_ID")
        or ""
    ).strip()
    key_id = (
        os.environ.get("APP_STORE_CONNECT_API_KEY_ID")
        or os.environ.get("APPSTORE_API_KEY_ID")
        or ""
    ).strip()
    private_key = load_private_key()
    if not issuer_id or not key_id:
        fail("Missing App Store Connect Issuer ID or API Key ID secrets")
    return issuer_id, key_id, private_key


def make_token(issuer_id: str, key_id: str, private_key: str) -> str:
    if jwt is None:
        fail("PyJWT is required (pip install PyJWT cryptography)")
    now = int(time.time())
    headers = {"alg": "ES256", "kid": key_id, "typ": "JWT"}
    payload = {"iss": issuer_id, "exp": now + 1200, "aud": "appstoreconnect-v1"}
    return jwt.encode(payload, private_key, algorithm="ES256", headers=headers)


class ASCClient:
    def __init__(self, token: str) -> None:
        self.token = token

    def request(
        self,
        method: str,
        path: str,
        *,
        params: dict[str, str] | None = None,
        body: dict[str, Any] | None = None,
    ) -> dict[str, Any]:
        url = API_BASE + path
        if params:
            url += "?" + urllib.parse.urlencode(params)
        data = None
        headers = {
            "Authorization": f"Bearer {self.token}",
            "Content-Type": "application/json",
        }
        if body is not None:
            data = json.dumps(body).encode("utf-8")
        req = urllib.request.Request(url, data=data, headers=headers, method=method)
        try:
            with urllib.request.urlopen(req, timeout=120) as resp:
                raw = resp.read().decode("utf-8")
                return json.loads(raw) if raw else {}
        except urllib.error.HTTPError as exc:
            detail = exc.read().decode("utf-8", errors="replace")
            fail(f"{method} {path} failed ({exc.code}): {detail}")

    def get(self, path: str, **params: str) -> dict[str, Any]:
        return self.request("GET", path, params=params or None)

    def post(self, path: str, body: dict[str, Any]) -> dict[str, Any]:
        return self.request("POST", path, body=body)

    def patch(self, path: str, body: dict[str, Any]) -> dict[str, Any]:
        return self.request("PATCH", path, body=body)


def find_app(client: ASCClient) -> dict[str, Any]:
    payload = client.get("/apps", **{"filter[bundleId]": BUNDLE_ID, "limit": "1"})
    data = payload.get("data") or []
    if not data:
        fail(f"App not found in App Store Connect for bundle id {BUNDLE_ID}")
    return data[0]


def wait_for_valid_build(client: ASCClient, app_id: str) -> dict[str, Any]:
    deadline = time.time() + POLL_TIMEOUT
    seen_states: set[str] = set()
    while time.time() < deadline:
        payload = client.get(
            "/builds",
            **{
                "filter[app]": app_id,
                "sort": "-uploadedDate",
                "limit": "1",
                "include": "preReleaseVersion,betaBuildLocalizations",
            },
        )
        builds = payload.get("data") or []
        if not builds:
            print("Waiting for uploaded build to appear in App Store Connect...")
            time.sleep(POLL_SECONDS)
            continue

        build = builds[0]
        state = (build.get("attributes") or {}).get("processingState", "UNKNOWN")
        version = (build.get("attributes") or {}).get("version", "?")
        if state not in seen_states:
            print(f"Build {version} processing state: {state}")
            seen_states.add(state)

        if state == "VALID":
            return build
        if state in {"FAILED", "INVALID"}:
            fail(f"Build processing failed with state {state}")

        time.sleep(POLL_SECONDS)

    fail("Timed out waiting for TestFlight build processing")


def ensure_export_compliance(client: ASCClient, build: dict[str, Any]) -> None:
    build_id = build["id"]
    attrs = build.get("attributes") or {}
    if attrs.get("usesNonExemptEncryption") is False:
        return
    client.patch(
        f"/builds/{build_id}",
        {
            "data": {
                "type": "builds",
                "id": build_id,
                "attributes": {"usesNonExemptEncryption": False},
            }
        },
    )
    print("Set usesNonExemptEncryption=false on build")


def find_internal_group(client: ASCClient, app_id: str) -> dict[str, Any]:
    payload = client.get(
        "/betaGroups",
        **{
            "filter[app]": app_id,
            "filter[isInternalGroup]": "true",
            "limit": "200",
        },
    )
    groups = payload.get("data") or []
    if not groups:
        fail("No internal TestFlight group found for this app")
    for group in groups:
        name = (group.get("attributes") or {}).get("name", "")
        if "internal" in name.lower() or "team" in name.lower():
            return group
    return groups[0]


def add_build_to_group(client: ASCClient, group_id: str, build_id: str) -> None:
    client.post(
        f"/betaGroups/{group_id}/relationships/builds",
        {"data": [{"type": "builds", "id": build_id}]},
    )
    print(f"Added build {build_id} to internal beta group {group_id}")


def find_beta_tester(client: ASCClient, app_id: str, email: str) -> dict[str, Any] | None:
    payload = client.get(
        "/betaTesters",
        **{"filter[apps]": app_id, "filter[email]": email, "limit": "1"},
    )
    data = payload.get("data") or []
    return data[0] if data else None


def invite_beta_tester(client: ASCClient, app_id: str, email: str) -> dict[str, Any]:
    existing = find_beta_tester(client, app_id, email)
    if existing:
        return existing

    local_part = email.split("@", 1)[0]
    first_name = local_part.replace(".", " ").replace("_", " ").title() or "Tester"
    payload = client.post(
        "/betaTesters",
        {
            "data": {
                "type": "betaTesters",
                "attributes": {
                    "email": email,
                    "firstName": first_name,
                    "lastName": "Tester",
                },
                "relationships": {
                    "apps": {"data": [{"type": "apps", "id": app_id}]},
                },
            }
        },
    )
    return payload["data"]


def add_tester_to_group(client: ASCClient, group_id: str, tester_id: str) -> None:
    client.post(
        f"/betaGroups/{group_id}/relationships/betaTesters",
        {"data": [{"type": "betaTesters", "id": tester_id}]},
    )
    print(f"Added tester {tester_id} to internal beta group {group_id}")


def verify_group_has_build_and_tester(
    client: ASCClient, group_id: str, build_id: str, email: str
) -> None:
    builds_payload = client.get(f"/betaGroups/{group_id}/builds", **{"limit": "20"})
    build_ids = {item["id"] for item in builds_payload.get("data") or []}
    if build_id not in build_ids:
        fail("Build is not visible on the internal TestFlight group yet")

    testers_payload = client.get(
        f"/betaGroups/{group_id}/betaTesters", **{"limit": "200"}
    )
    emails = {
        ((item.get("attributes") or {}).get("email") or "").lower()
        for item in testers_payload.get("data") or []
    }
    if email not in emails:
        fail(f"Tester {email} is not assigned to the internal TestFlight group yet")

    print("Verified TestFlight internal group contains the build and tester")


def main() -> None:
    issuer_id, key_id, private_key = load_config()
    token = make_token(issuer_id, key_id, private_key)
    client = ASCClient(token)

    app = find_app(client)
    app_id = app["id"]
    app_name = (app.get("attributes") or {}).get("name", BUNDLE_ID)
    print(f"App Store Connect app: {app_name} ({app_id})")

    build = wait_for_valid_build(client, app_id)
    build_id = build["id"]
    build_version = (build.get("attributes") or {}).get("version", "?")
    print(f"TestFlight build is VALID: {build_version} ({build_id})")

    ensure_export_compliance(client, build)

    group = find_internal_group(client, app_id)
    group_id = group["id"]
    group_name = (group.get("attributes") or {}).get("name", group_id)
    print(f"Internal beta group: {group_name} ({group_id})")

    add_build_to_group(client, group_id, build_id)

    tester = find_beta_tester(client, app_id, TESTER_EMAIL)
    if tester is None:
        print(f"Creating beta tester invitation for {TESTER_EMAIL}")
        tester = invite_beta_tester(client, app_id, TESTER_EMAIL)
    tester_id = tester["id"]
    add_tester_to_group(client, group_id, tester_id)

    verify_group_has_build_and_tester(client, group_id, build_id, TESTER_EMAIL)

    print("TESTFLIGHT_READY=true")
    print(f"TESTFLIGHT_APP={app_name}")
    print(f"TESTFLIGHT_BUILD={build_version}")
    print(f"TESTFLIGHT_TESTER={TESTER_EMAIL}")


if __name__ == "__main__":
    main()
