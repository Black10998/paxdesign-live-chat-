#!/usr/bin/env python3
"""Create or download an App Store provisioning profile for the Customer Portal app."""

from __future__ import annotations

import base64
import importlib.util
import os
import plistlib
import subprocess
import sys
import tempfile
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

BUNDLE_ID = os.environ.get("APP_BUNDLE_ID", "at.paxdesign.customerportal")
PROFILE_NAME = os.environ.get(
    "CUSTOMER_PROFILE_NAME", "PAX Customer Portal App Store CI"
)
OUTPUT_PATH = os.environ.get(
    "CUSTOMER_PROFILE_OUTPUT",
    str(Path(tempfile.gettempdir()) / "customer-portal-appstore.mobileprovision"),
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
    if data:
        return data[0] if isinstance(data, list) else data

    print(f"Creating bundle ID {BUNDLE_ID} …")
    status, created = client.request(
        "POST",
        "/bundleIds",
        body={
            "data": {
                "type": "bundleIds",
                "attributes": {
                    "identifier": BUNDLE_ID,
                    "name": "PAXDesign Customer Portal",
                    "platform": "IOS",
                },
            }
        },
        allow_error=True,
    )
    if status == 409:
        payload = client.get("/bundleIds", **{"filter[identifier]": BUNDLE_ID, "limit": "1"})
        data = payload.get("data") or []
        if data:
            return data[0] if isinstance(data, list) else data
    if status != 201:
        fail(f"Could not create bundle ID {BUNDLE_ID} ({status}): {created}")
    return resource_data(created, label=f"Bundle ID {BUNDLE_ID}")


def ensure_push_notifications(client: ASCClient, bundle_id: dict[str, Any]) -> None:
    bundle_resource_id = bundle_id["id"]
    payload = client.get(f"/bundleIds/{bundle_resource_id}/bundleIdCapabilities")
    caps = payload.get("data") or []
    for cap in caps:
        cap_type = (cap.get("attributes") or {}).get("capabilityType", "")
        if cap_type == "PUSH_NOTIFICATIONS":
            print("Push Notifications capability already enabled")
            return

    print("Enabling Push Notifications capability …")
    client.post(
        "/bundleIdCapabilities",
        {
            "data": {
                "type": "bundleIdCapabilities",
                "attributes": {"capabilityType": "PUSH_NOTIFICATIONS"},
                "relationships": {
                    "bundleId": {"data": {"type": "bundleIds", "id": bundle_resource_id}}
                },
            }
        },
    )


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
            if (c.get("attributes") or {}).get("certificateType") in {cert_type, "IOS_DISTRIBUTION", "DISTRIBUTION"}
        ]
        if active:
            cert = active[0]
            name = (cert.get("attributes") or {}).get("displayName", cert["id"])
            print(f"Using distribution certificate: {name}")
            return cert
    fail("No Apple Distribution certificate found in App Store Connect")


def find_existing_profile(client: ASCClient, bundle_resource_id: str) -> dict[str, Any] | None:
    payload = client.get(
        "/profiles",
        **{"filter[profileType]": "IOS_APP_STORE", "limit": "200"},
    )
    for profile in payload.get("data") or []:
        rel = (profile.get("relationships") or {}).get("bundleId", {}).get("data") or {}
        if rel.get("id") == bundle_resource_id:
            attrs = profile.get("attributes") or {}
            print(f"Found existing profile: {attrs.get('name', profile['id'])}")
            return profile
    return None


def create_profile(
    client: ASCClient, bundle_resource_id: str, certificate_id: str
) -> dict[str, Any]:
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
        if BUNDLE_ID not in app_id:
            fail(f"Profile application-identifier mismatch: {app_id}")
        if aps != "production":
            fail(f"Profile aps-environment must be production, got {aps!r}")
        print(f"Verified profile: app_id={app_id} aps-environment={aps}")
    finally:
        Path(tmp_path).unlink(missing_ok=True)


def ensure_app_record(client: ASCClient, bundle_resource_id: str) -> dict[str, Any] | None:
    payload = client.get("/apps", **{"filter[bundleId]": BUNDLE_ID, "limit": "1"})
    data = payload.get("data") or []
    if data:
        name = (data[0].get("attributes") or {}).get("name", BUNDLE_ID)
        print(f"App Store Connect app record exists: {name}")
        return data[0]

    print("Creating App Store Connect app record for Customer Portal …")
    status, created = client.request(
        "POST",
        "/apps",
        body={
            "data": {
                "type": "apps",
                "attributes": {
                    "name": "PAXDesign Customer Portal",
                    "sku": "paxdesign-customer-portal-ios",
                    "primaryLocale": "de-DE",
                },
                "relationships": {
                    "bundleId": {
                        "data": {"type": "bundleIds", "id": bundle_resource_id}
                    }
                },
            }
        },
        allow_error=True,
    )
    if status == 201:
        return resource_data(created, label=f"App record for {BUNDLE_ID}")
    if status == 403:
        print(
            "WARNING: App Store Connect API key cannot CREATE apps; "
            "continuing with provisioning profile only (create the app manually if needed)"
        )
        return None
    fail(f"App Store Connect app creation failed ({status}): {created}")


def export_for_github_actions(raw: bytes) -> None:
    Path(OUTPUT_PATH).write_bytes(raw)
    encoded = base64.b64encode(raw).decode("ascii")
    if GITHUB_ENV:
        with open(GITHUB_ENV, "a", encoding="utf-8") as handle:
            handle.write(f"CUSTOMER_PROFILE_PATH={OUTPUT_PATH}\n")
            handle.write(f"APPLE_PROVISIONING_PROFILE_CUSTOMER_BASE64={encoded}\n")
    print(f"Wrote profile to {OUTPUT_PATH} ({len(raw)} bytes)")


def main() -> None:
    issuer_id, key_id, private_key = load_config()
    client = make_client(issuer_id, key_id, private_key)

    bundle = find_bundle_id(client)
    ensure_push_notifications(client, bundle)
    ensure_app_record(client, bundle["id"])
    certificate = find_distribution_certificate(client)

    profile = find_existing_profile(client, bundle["id"])
    if not profile:
        profile = create_profile(client, bundle["id"], certificate["id"])

    raw = download_profile_content(client, profile["id"])
    verify_profile_contents(raw)
    export_for_github_actions(raw)
    print("PASS: Customer Portal App Store provisioning profile ready")


if __name__ == "__main__":
    main()
