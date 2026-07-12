#!/usr/bin/env python3
"""Verify and fix TestFlight access for a tester email."""

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
    os.environ.get("TESTFLIGHT_TESTER_EMAIL", "awjime29@icloud.com"),
).strip().lower()
TARGET_BUILD = os.environ.get("TESTFLIGHT_TARGET_BUILD", "86").strip()
EXTERNAL_GROUP_NAME = os.environ.get(
    "TESTFLIGHT_EXTERNAL_GROUP_NAME", "PAXDesign External Testers"
)


def fail(message: str) -> None:
    print(f"ERROR: {message}", file=sys.stderr)
    sys.exit(1)


def warn(message: str) -> None:
    print(f"WARNING: {message}", file=sys.stderr)


def load_private_key() -> str:
    import base64

    b64 = os.environ.get("APP_STORE_CONNECT_API_KEY_P8_BASE64", "").strip()
    if b64:
        try:
            raw = base64.b64decode("".join(b64.split())).decode("utf-8")
        except Exception as exc:  # noqa: BLE001
            fail(f"Could not decode APP_STORE_CONNECT_API_KEY_P8_BASE64: {exc}")
    else:
        raw = os.environ.get("APP_STORE_CONNECT_API_PRIVATE_KEY") or os.environ.get(
            "APPSTORE_API_PRIVATE_KEY", ""
        )

    raw = raw.strip().strip('"').replace("\\n", "\n").replace("\r", "")
    if not raw:
        fail("Missing App Store Connect API private key secret")
    if not raw.endswith("\n"):
        raw += "\n"
    return raw


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
        allow_error: bool = False,
    ) -> tuple[int, dict[str, Any]]:
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
                return resp.status, json.loads(raw) if raw else {}
        except urllib.error.HTTPError as exc:
            detail = exc.read().decode("utf-8", errors="replace")
            if allow_error:
                try:
                    payload = json.loads(detail) if detail else {}
                except json.JSONDecodeError:
                    payload = {"raw": detail}
                return exc.code, payload
            fail(f"{method} {path} failed ({exc.code}): {detail}")

    def get(self, path: str, **params: str) -> dict[str, Any]:
        _, payload = self.request("GET", path, params=params or None)
        return payload

    def post(self, path: str, body: dict[str, Any], *, allow_error: bool = False) -> dict[str, Any]:
        _, payload = self.request("POST", path, body=body, allow_error=allow_error)
        return payload

    def patch(self, path: str, body: dict[str, Any]) -> dict[str, Any]:
        _, payload = self.request("PATCH", path, body=body)
        return payload


def find_app(client: ASCClient) -> dict[str, Any]:
    payload = client.get("/apps", **{"filter[bundleId]": BUNDLE_ID, "limit": "1"})
    data = payload.get("data") or []
    if not data:
        fail(f"App not found for bundle id {BUNDLE_ID}")
    return data[0]


def find_build(client: ASCClient, app_id: str, version: str) -> dict[str, Any] | None:
    payload = client.get(
        "/builds",
        **{
            "filter[app]": app_id,
            "filter[version]": version,
            "sort": "-uploadedDate",
            "limit": "5",
        },
    )
    builds = payload.get("data") or []
    if builds:
        return builds[0]

    payload = client.get(
        "/builds",
        **{
            "filter[app]": app_id,
            "sort": "-uploadedDate",
            "limit": "10",
        },
    )
    for build in payload.get("data") or []:
        if str((build.get("attributes") or {}).get("version", "")) == version:
            return build
    return None


def get_build_beta_detail(client: ASCClient, build_id: str) -> dict[str, Any] | None:
    payload = client.get("/buildBetaDetails", **{"filter[build]": build_id, "limit": "1"})
    data = payload.get("data") or []
    return data[0] if data else None


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


def list_groups(client: ASCClient, app_id: str) -> list[dict[str, Any]]:
    payload = client.get(f"/apps/{app_id}/betaGroups", **{"limit": "200"})
    return payload.get("data") or []


def group_build_versions(client: ASCClient, group_id: str) -> list[str]:
    payload = client.get(f"/betaGroups/{group_id}/builds", **{"limit": "50"})
    versions = []
    for item in payload.get("data") or []:
        versions.append(str((item.get("attributes") or {}).get("version", "?")))
    return versions


def group_tester_emails(client: ASCClient, group_id: str) -> list[str]:
    payload = client.get(f"/betaGroups/{group_id}/betaTesters", **{"limit": "200"})
    emails = []
    for item in payload.get("data") or []:
        email = ((item.get("attributes") or {}).get("email") or "").lower()
        if email:
            emails.append(email)
    return emails


def find_asc_user(client: ASCClient, email: str) -> dict[str, Any] | None:
    for field in ("username", "email"):
        payload = client.get("/users", **{f"filter[{field}]": email, "limit": "5"})
        for user in payload.get("data") or []:
            attrs = user.get("attributes") or {}
            username = (attrs.get("username") or "").lower()
            if username == email:
                return user
    return None


def find_or_create_group(
    client: ASCClient, app_id: str, *, internal: bool, name: str
) -> dict[str, Any]:
    groups = list_groups(client, app_id)
    for group in groups:
        attrs = group.get("attributes") or {}
        if attrs.get("isInternalGroup") is internal and attrs.get("name") == name:
            return group
    for group in groups:
        attrs = group.get("attributes") or {}
        if attrs.get("isInternalGroup") is internal:
            return group

    print(f"Creating {'internal' if internal else 'external'} beta group: {name}")
    payload = client.post(
        "/betaGroups",
        {
            "data": {
                "type": "betaGroups",
                "attributes": {
                    "name": name,
                    "isInternalGroup": internal,
                },
                "relationships": {
                    "app": {"data": {"type": "apps", "id": app_id}},
                },
            }
        },
    )
    return payload["data"]


def add_build_to_group(client: ASCClient, group_id: str, build_id: str) -> bool:
    status, payload = client.request(
        "POST",
        f"/betaGroups/{group_id}/relationships/builds",
        body={"data": [{"type": "builds", "id": build_id}]},
        allow_error=True,
    )
    if status in {200, 201, 204}:
        print(f"Linked build {build_id} to beta group {group_id}")
        return True
    detail = json.dumps(payload)
    if status == 409 and "already" in detail.lower():
        print(f"Build {build_id} already linked to beta group {group_id}")
        return True
    warn(f"Could not link build to group ({status}): {detail}")
    return False


def find_beta_tester(client: ASCClient, email: str) -> dict[str, Any] | None:
    payload = client.get("/betaTesters", **{"filter[email]": email, "limit": "1"})
    data = payload.get("data") or []
    return data[0] if data else None


def invite_external_tester(
    client: ASCClient, group_id: str, email: str
) -> dict[str, Any] | None:
    existing = find_beta_tester(client, email)
    if existing:
        add_tester_to_group(client, group_id, existing["id"])
        return existing

    local_part = email.split("@", 1)[0]
    first_name = local_part.replace(".", " ").replace("_", " ").title() or "Tester"
    status, payload = client.request(
        "POST",
        "/betaTesters",
        body={
            "data": {
                "type": "betaTesters",
                "attributes": {
                    "email": email,
                    "firstName": first_name,
                    "lastName": "Tester",
                },
                "relationships": {
                    "betaGroups": {
                        "data": [{"type": "betaGroups", "id": group_id}],
                    }
                },
            }
        },
        allow_error=True,
    )
    if status in {200, 201}:
        print(f"Sent external TestFlight invite to {email}")
        return payload.get("data")
    if status == 409:
        existing = find_beta_tester(client, email)
        if existing:
            add_tester_to_group(client, group_id, existing["id"])
            return existing
    warn(f"Could not invite external tester ({status}): {json.dumps(payload)}")
    return None


def add_tester_to_group(client: ASCClient, group_id: str, tester_id: str) -> bool:
    status, payload = client.request(
        "POST",
        f"/betaGroups/{group_id}/relationships/betaTesters",
        body={"data": [{"type": "betaTesters", "id": tester_id}]},
        allow_error=True,
    )
    if status in {200, 201, 204}:
        print(f"Added tester {tester_id} to beta group {group_id}")
        return True
    detail = json.dumps(payload)
    if status == 409:
        print(f"Tester {tester_id} already in beta group {group_id}")
        return True
    warn(f"Could not add tester to group ({status}): {detail}")
    return False


def add_internal_tester(
    client: ASCClient,
    group_id: str,
    email: str,
    asc_user: dict[str, Any] | None,
) -> dict[str, Any] | None:
    """Add an App Store Connect team user to an internal TestFlight group."""
    if asc_user is None:
        warn(f"Cannot add internal tester: {email} is not an App Store Connect user")
        return None

    existing = find_beta_tester(client, email)
    if existing:
        add_tester_to_group(client, group_id, existing["id"])
        return existing

    username = ((asc_user.get("attributes") or {}).get("username") or email).lower()
    local_part = username.split("@", 1)[0]
    first_name = local_part.replace(".", " ").replace("_", " ").title() or "Internal"
    status, payload = client.request(
        "POST",
        "/betaTesters",
        body={
            "data": {
                "type": "betaTesters",
                "attributes": {
                    "email": email,
                    "firstName": first_name,
                    "lastName": "Tester",
                },
                "relationships": {
                    "betaGroups": {
                        "data": [{"type": "betaGroups", "id": group_id}],
                    }
                },
            }
        },
        allow_error=True,
    )
    if status in {200, 201}:
        print(f"Added internal tester {email} to group {group_id}")
        return payload.get("data")
    if status == 409:
        existing = find_beta_tester(client, email)
        if existing:
            add_tester_to_group(client, group_id, existing["id"])
            return existing
    warn(f"Could not add internal tester ({status}): {json.dumps(payload)}")
    return None


def ensure_beta_build_localization(client: ASCClient, build_id: str) -> None:
    payload = client.get(
        "/betaBuildLocalizations",
        **{"filter[build]": build_id, "limit": "10"},
    )
    if payload.get("data"):
        print("Beta build localization already exists")
        return

    status, response = client.request(
        "POST",
        "/betaBuildLocalizations",
        body={
            "data": {
                "type": "betaBuildLocalizations",
                "attributes": {
                    "locale": "en-US",
                    "whatsNew": "PAXDesign Live Chat build for TestFlight testing.",
                },
                "relationships": {
                    "build": {"data": {"type": "builds", "id": build_id}},
                },
            }
        },
        allow_error=True,
    )
    if status in {200, 201}:
        print("Created beta build localization (en-US)")
        return
    if status == 409:
        print("Beta build localization already exists (409)")
        return
    warn(f"Could not create beta build localization ({status}): {json.dumps(response)}")


def ensure_beta_localization(client: ASCClient, app_id: str) -> None:
    payload = client.get(f"/apps/{app_id}/betaAppLocalizations", **{"limit": "10"})
    if payload.get("data"):
        print("Beta app localization already exists")
        return

    status, response = client.request(
        "POST",
        "/betaAppLocalizations",
        body={
            "data": {
                "type": "betaAppLocalizations",
                "attributes": {
                    "locale": "en-US",
                    "description": "PAXDesign Live Chat TestFlight build.",
                    "feedbackEmail": TESTER_EMAIL,
                },
                "relationships": {
                    "app": {"data": {"type": "apps", "id": app_id}},
                },
            }
        },
        allow_error=True,
    )
    if status in {200, 201}:
        print("Created beta app localization (en-US)")
        return
    if status == 409:
        print("Beta app localization already exists (409)")
        return
    fail(f"Could not create beta app localization ({status}): {json.dumps(response)}")


def submit_beta_review(client: ASCClient, build_id: str) -> None:
    detail = get_build_beta_detail(client, build_id)
    if not detail:
        warn("No buildBetaDetail found; skipping beta review submission")
        return
    attrs = detail.get("attributes") or {}
    external_state = attrs.get("externalBuildState", "UNKNOWN")
    print(f"External build state: {external_state}")
    if external_state in {"IN_BETA_TESTING", "READY_FOR_BETA_TESTING"}:
        print("External beta review already approved or in testing")
        return
    if external_state not in {"READY_FOR_BETA_SUBMISSION", "MISSING_EXPORT_COMPLIANCE"}:
        warn(f"Build not ready for beta submission (state={external_state})")
        return

    for attempt in range(1, 4):
        status, payload = client.request(
            "POST",
            "/betaAppReviewSubmissions",
            body={
                "data": {
                    "type": "betaAppReviewSubmissions",
                    "relationships": {
                        "build": {"data": {"type": "builds", "id": build_id}},
                    },
                }
            },
            allow_error=True,
        )
        if status in {200, 201}:
            print("Submitted build for external beta app review")
            return
        detail_text = json.dumps(payload)
        if "betaAppLocalization" in detail_text and attempt < 3:
            print(f"Waiting for beta localization propagation (attempt {attempt})")
            time.sleep(5)
            continue
        warn(f"Beta review submission returned {status}: {detail_text}")
        return


def print_diagnostics(
    client: ASCClient,
    app: dict[str, Any],
    build: dict[str, Any],
    groups: list[dict[str, Any]],
) -> None:
    app_name = (app.get("attributes") or {}).get("name", BUNDLE_ID)
    build_attrs = build.get("attributes") or {}
    build_id = build["id"]
    build_version = str(build_attrs.get("version", "?"))
    processing = build_attrs.get("processingState", "UNKNOWN")

    print("=== TestFlight diagnostics ===")
    print(f"App: {app_name} ({app['id']})")
    print(f"Build: {build_version} ({build_id}) state={processing}")

    detail = get_build_beta_detail(client, build_id)
    if detail:
        attrs = detail.get("attributes") or {}
        print(
            "Build beta detail: "
            f"internal={attrs.get('internalBuildState')} "
            f"external={attrs.get('externalBuildState')}"
        )

    asc_user = find_asc_user(client, TESTER_EMAIL)
    if asc_user:
        roles = (asc_user.get("attributes") or {}).get("roles", [])
        print(f"ASC team user: yes ({TESTER_EMAIL}, roles={roles})")
    else:
        print(
            f"ASC team user: no ({TESTER_EMAIL} is NOT an App Store Connect user; "
            "internal TestFlight will not work without Users and Access membership)"
        )

    for group in groups:
        attrs = group.get("attributes") or {}
        gid = group["id"]
        name = attrs.get("name", gid)
        internal = attrs.get("isInternalGroup")
        builds = group_build_versions(client, gid)
        testers = group_tester_emails(client, gid)
        has_build = build_version in builds
        has_tester = TESTER_EMAIL in testers
        print(
            f"Group '{name}' internal={internal} id={gid} "
            f"build_{build_version}={has_build} tester={has_tester}"
        )
        if testers:
            print(f"  testers: {', '.join(testers)}")
        if builds:
            print(f"  builds: {', '.join(builds)}")


def main() -> None:
    issuer_id, key_id, private_key = load_config()
    token = make_token(issuer_id, key_id, private_key)
    client = ASCClient(token)

    app = find_app(client)
    app_id = app["id"]
    build = find_build(client, app_id, TARGET_BUILD)
    if build is None:
        fail(f"Build {TARGET_BUILD} not found in App Store Connect TestFlight")

    build_id = build["id"]
    build_version = str((build.get("attributes") or {}).get("version", TARGET_BUILD))
    processing = (build.get("attributes") or {}).get("processingState", "UNKNOWN")
    if processing != "VALID":
        fail(f"Build {build_version} is not VALID (state={processing})")

    ensure_export_compliance(client, build)
    ensure_beta_localization(client, app_id)
    ensure_beta_build_localization(client, build_id)
    groups = list_groups(client, app_id)
    print_diagnostics(client, app, build, groups)

    asc_user = find_asc_user(client, TESTER_EMAIL)
    internal_groups = [
        group
        for group in groups
        if (group.get("attributes") or {}).get("isInternalGroup") is True
    ]
    if not internal_groups:
        internal_groups = [
            find_or_create_group(client, app_id, internal=True, name="Internal Testing")
        ]

    for group in internal_groups:
        group_name = (group.get("attributes") or {}).get("name", group["id"])
        add_build_to_group(client, group["id"], build_id)
        print(f"Internal path: build {build_version} linked to {group_name}")

    if asc_user:
        roles = (asc_user.get("attributes") or {}).get("roles", [])
        print(
            f"Internal TestFlight eligible via App Store Connect membership "
            f"({TESTER_EMAIL}, roles={roles})"
        )
    else:
        print(
            "Skipping internal-only access; tester is not an App Store Connect team user."
        )

    ensure_beta_build_localization(client, build_id)
    import os

    if os.environ.get("ALLOW_EXTERNAL_BETA_REVIEW", "").strip() == "1":
        external_group = find_or_create_group(
            client, app_id, internal=False, name=EXTERNAL_GROUP_NAME
        )
        external_group_id = external_group["id"]
        add_build_to_group(client, external_group_id, build_id)
        invite_external_tester(client, external_group_id, TESTER_EMAIL)
        submit_beta_review(client, build_id)

        groups = list_groups(client, app_id)
        print_diagnostics(client, app, build, groups)

        external_builds = group_build_versions(client, external_group_id)
        external_testers = group_tester_emails(client, external_group_id)
        if build_version not in external_builds:
            fail(f"Build {build_version} is still not linked to external group")
        if TESTER_EMAIL not in external_testers:
            fail(f"Tester {TESTER_EMAIL} is still not linked to external group")
    else:
        print("Skipping external TestFlight setup (ALLOW_EXTERNAL_BETA_REVIEW not set)")
        groups = list_groups(client, app_id)
        print_diagnostics(client, app, build, groups)

    print("TESTFLIGHT_READY=true")
    print(f"TESTFLIGHT_APP={(app.get('attributes') or {}).get('name', BUNDLE_ID)}")
    print(f"TESTFLIGHT_BUILD={build_version}")
    print(f"TESTFLIGHT_TESTER={TESTER_EMAIL}")
    print(
        "TESTFLIGHT_NEXT_STEP=Check email inbox for TestFlight invite and tap Accept, "
        "then reopen the TestFlight app."
    )


if __name__ == "__main__":
    main()
