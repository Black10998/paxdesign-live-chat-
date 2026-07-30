#!/usr/bin/env python3
"""Ensure App Store profile for PAXDesign Live Chat includes Sign in with Apple."""

from __future__ import annotations

import base64
import importlib.util
import os
import plistlib
import subprocess
import sys
import time
from pathlib import Path
from typing import Any

_SPEC = importlib.util.spec_from_file_location(
    "setup_testflight_access",
    Path(__file__).resolve().parent / "setup_testflight_access.py",
)
_SETUP = importlib.util.module_from_spec(_SPEC)
assert _SPEC.loader is not None
_SPEC.loader.exec_module(_SETUP)

ASCClient = _SETUP.ASCClient
fail = _SETUP.fail
make_client = _SETUP.make_client
load_config = _SETUP.load_config

BUNDLE_ID = os.environ.get("APP_BUNDLE_ID", "at.paxdesign.livechat")
PROFILE_NAME = os.environ.get("MAIN_PROFILE_NAME", "PAXDesign Live Chat App Store")
OUTPUT_PATH = os.environ.get(
    "MAIN_PROFILE_OUTPUT",
    str(Path("/tmp") / "paxdesign-livechat-appstore.mobileprovision"),
)
GITHUB_ENV = os.environ.get("GITHUB_ENV", "")


def resource_data(payload: dict[str, Any], *, label: str) -> dict[str, Any]:
    data = payload.get("data")
    if isinstance(data, dict):
        return data
    if isinstance(data, list) and data:
        return data[0]
    fail(f"{label} returned no resource data: {payload}")


def find_bundle_id(client: ASCClient) -> dict[str, Any]:
    payload = client.get("/bundleIds", **{"filter[identifier]": BUNDLE_ID, "limit": "1"})
    data = payload.get("data") or []
    if not data:
        fail(f"Bundle ID not found: {BUNDLE_ID}")
    return data[0] if isinstance(data, list) else data


def list_capabilities(client: ASCClient, bundle_resource_id: str) -> list[dict[str, Any]]:
    payload = client.get(f"/bundleIds/{bundle_resource_id}/bundleIdCapabilities")
    return payload.get("data") or []


def has_capability(caps: list[dict[str, Any]], capability_type: str) -> bool:
    for cap in caps:
        cap_type = (cap.get("attributes") or {}).get("capabilityType", "")
        if cap_type == capability_type:
            return True
    return False


def enable_capability(client: ASCClient, bundle_resource_id: str, capability_type: str) -> None:
    caps = list_capabilities(client, bundle_resource_id)
    if has_capability(caps, capability_type):
        print(f"{capability_type} already enabled on {BUNDLE_ID}")
        return

    print(f"Enabling {capability_type} on {BUNDLE_ID} …")
    status, payload = client.request(
        "POST",
        "/bundleIdCapabilities",
        body={
            "data": {
                "type": "bundleIdCapabilities",
                "attributes": {"capabilityType": capability_type},
                "relationships": {
                    "bundleId": {"data": {"type": "bundleIds", "id": bundle_resource_id}}
                },
            }
        },
        allow_error=True,
    )
    if status in {201, 409}:
        print(f"{capability_type} enabled (status {status})")
        return
    fail(f"Could not enable {capability_type} ({status}): {payload}")


def find_distribution_certificate(client: ASCClient) -> dict[str, Any]:
    for cert_type in ("IOS_DISTRIBUTION", "DISTRIBUTION"):
        payload = client.get(
            "/certificates",
            **{"filter[certificateType]": cert_type, "limit": "20"},
        )
        certs = payload.get("data") or []
        active = [
            c
            for c in certs
            if (c.get("attributes") or {}).get("certificateType")
            in {cert_type, "IOS_DISTRIBUTION", "DISTRIBUTION"}
        ]
        if active:
            name = (active[0].get("attributes") or {}).get("displayName", active[0]["id"])
            print(f"Using distribution certificate: {name}")
            return active[0]
    fail("No Apple Distribution certificate found in App Store Connect")


def find_existing_profiles(client: ASCClient, bundle_resource_id: str) -> list[dict[str, Any]]:
    payload = client.get(
        "/profiles",
        **{"filter[profileType]": "IOS_APP_STORE", "limit": "200"},
    )
    matches: list[dict[str, Any]] = []
    for profile in payload.get("data") or []:
        rel = (profile.get("relationships") or {}).get("bundleId", {}).get("data") or {}
        if rel.get("id") == bundle_resource_id:
            matches.append(profile)
    return matches


def find_profiles_by_name(client: ASCClient, profile_name: str) -> list[dict[str, Any]]:
    payload = client.get(
        "/profiles",
        **{"filter[profileType]": "IOS_APP_STORE", "limit": "200"},
    )
    matches: list[dict[str, Any]] = []
    for profile in payload.get("data") or []:
        attrs = profile.get("attributes") or {}
        if attrs.get("name") == profile_name:
            matches.append(profile)
    return matches


def delete_profile(client: ASCClient, profile_id: str, name: str) -> None:
    print(f"Deleting stale profile {name!r} ({profile_id}) …")
    status, payload = client.request("DELETE", f"/profiles/{profile_id}", allow_error=True)
    if status not in {204, 404}:
        fail(f"Could not delete profile {profile_id} ({status}): {payload}")


def create_profile(client: ASCClient, bundle_resource_id: str, certificate_id: str) -> dict[str, Any]:
    print(f"Creating App Store profile {PROFILE_NAME!r} …")
    status, payload = client.request(
        "POST",
        "/profiles",
        body={
            "data": {
                "type": "profiles",
                "attributes": {
                    "name": PROFILE_NAME,
                    "profileType": "IOS_APP_STORE",
                },
                "relationships": {
                    "bundleId": {
                        "data": {"type": "bundleIds", "id": bundle_resource_id}
                    },
                    "certificates": {
                        "data": [{"type": "certificates", "id": certificate_id}]
                    },
                },
            }
        },
        allow_error=True,
    )
    if status == 409:
        duplicates = find_profiles_by_name(client, PROFILE_NAME)
        for profile in duplicates:
            attrs = profile.get("attributes") or {}
            delete_profile(client, profile["id"], str(attrs.get("name", profile["id"])))
            time.sleep(2)
        status, payload = client.request(
            "POST",
            "/profiles",
            body={
                "data": {
                    "type": "profiles",
                    "attributes": {
                        "name": PROFILE_NAME,
                        "profileType": "IOS_APP_STORE",
                    },
                    "relationships": {
                        "bundleId": {
                            "data": {"type": "bundleIds", "id": bundle_resource_id}
                        },
                        "certificates": {
                            "data": [{"type": "certificates", "id": certificate_id}]
                        },
                    },
                }
            },
            allow_error=True,
        )
        if status == 201:
            return resource_data(payload, label=f"Profile {PROFILE_NAME}")
        existing = find_existing_profiles(client, bundle_resource_id)
        for profile in existing:
            attrs = profile.get("attributes") or {}
            if attrs.get("name") == PROFILE_NAME:
                return profile
        if existing:
            return existing[0]
    if status != 201:
        fail(f"Profile creation failed ({status}): {payload}")
    return resource_data(payload, label=f"Profile {PROFILE_NAME}")


def download_profile_content(client: ASCClient, profile_id: str) -> bytes:
    payload = client.get(f"/profiles/{profile_id}")
    data = payload.get("data") or {}
    attrs = data.get("attributes") or {}
    encoded = attrs.get("profileContent") or ""
    if not encoded:
        fail(f"Profile {profile_id} has empty profileContent")
    return base64.b64decode(encoded)


def verify_profile_contents(raw: bytes) -> None:
    import tempfile

    with tempfile.NamedTemporaryFile(suffix=".mobileprovision", delete=False) as tmp:
        tmp.write(raw)
        tmp_path = tmp.name
    try:
        proc = subprocess.run(
            ["security", "cms", "-D", "-i", tmp_path],
            capture_output=True,
            check=False,
        )
        if proc.returncode != 0:
            fail("Downloaded profile is not a valid CMS/mobileprovision payload")
        plist = plistlib.loads(proc.stdout)
        entitlements = plist.get("Entitlements") or {}
        app_id = entitlements.get("application-identifier", "")
        aps = entitlements.get("aps-environment", "")
        apple_signin = entitlements.get("com.apple.developer.applesignin") or []

        if BUNDLE_ID not in str(app_id):
            fail(f"Profile application-identifier mismatch: {app_id}")
        if aps != "production":
            fail(f"Profile aps-environment must be production, got {aps!r}")
        if "Default" not in apple_signin:
            fail(
                "Profile missing com.apple.developer.applesignin = Default. "
                "Regenerate the App Store profile after enabling Sign in with Apple."
            )

        print(f"Verified profile: app_id={app_id}")
        print(f"  aps-environment={aps}")
        print("  com.apple.developer.applesignin=Default")
    finally:
        Path(tmp_path).unlink(missing_ok=True)


def export_for_build(raw: bytes) -> None:
    Path(OUTPUT_PATH).write_bytes(raw)
    encoded = base64.b64encode(raw).decode("ascii")
    print(f"Wrote profile to {OUTPUT_PATH} ({len(raw)} bytes)")
    if GITHUB_ENV:
        with open(GITHUB_ENV, "a", encoding="utf-8") as handle:
            handle.write(f"MAIN_PROFILE_PATH={OUTPUT_PATH}\n")
            handle.write(f"APPLE_PROVISIONING_PROFILE_MAIN_BASE64={encoded}\n")
        print("Exported APPLE_PROVISIONING_PROFILE_MAIN_BASE64 to GITHUB_ENV")


def ensure_fresh_profile(
    client: ASCClient, bundle_resource_id: str, certificate_id: str
) -> dict[str, Any]:
    for profile in find_profiles_by_name(client, PROFILE_NAME):
        attrs = profile.get("attributes") or {}
        delete_profile(client, profile["id"], str(attrs.get("name", profile["id"])))
        time.sleep(2)

    existing = find_existing_profiles(client, bundle_resource_id)
    for profile in existing:
        attrs = profile.get("attributes") or {}
        delete_profile(client, profile["id"], str(attrs.get("name", profile["id"])))
        time.sleep(2)
    return create_profile(client, bundle_resource_id, certificate_id)


def main() -> None:
    issuer_id, key_id, private_key = load_config()
    client = make_client(issuer_id, key_id, private_key)

    bundle = find_bundle_id(client)
    bundle_id = bundle["id"]
    print(f"Bundle ID resource: {bundle_id} ({BUNDLE_ID})")

    enable_capability(client, bundle_id, "PUSH_NOTIFICATIONS")
    enable_capability(client, bundle_id, "APPLE_ID_AUTH")
    enable_capability(client, bundle_id, "APP_GROUPS")

    certificate = find_distribution_certificate(client)
    profile = ensure_fresh_profile(client, bundle_id, certificate["id"])

    raw = download_profile_content(client, profile["id"])
    verify_profile_contents(raw)
    export_for_build(raw)
    print("PASS: Live Chat App Store provisioning profile ready (Sign in with Apple included)")


if __name__ == "__main__":
    main()
