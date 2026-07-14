#!/usr/bin/env python3
"""Prepare App Store Connect listing for submission — no review submit."""

from __future__ import annotations

import importlib.util
import json
import os
import sys
from pathlib import Path
from typing import Any

ROOT = Path(__file__).resolve().parents[2]
METADATA_PATH = ROOT / "docs" / "app-store" / "metadata.json"

_SPEC = importlib.util.spec_from_file_location(
    "asc_version",
    Path(__file__).resolve().parent / "asc_version.py",
)
_VERSION_MOD = importlib.util.module_from_spec(_SPEC)
assert _SPEC.loader is not None
_SPEC.loader.exec_module(_VERSION_MOD)
find_or_create_version = _VERSION_MOD.find_or_create_version

_SPEC = importlib.util.spec_from_file_location(
    "submit_app_store_review",
    Path(__file__).resolve().parent / "submit_app_store_review.py",
)
_SUBMIT = importlib.util.module_from_spec(_SPEC)
assert _SPEC.loader is not None
_SPEC.loader.exec_module(_SUBMIT)

attach_build = _SUBMIT.attach_build
ensure_export_compliance = _SUBMIT.ensure_export_compliance
ensure_localization = _SUBMIT.ensure_localization
load_metadata = _SUBMIT.load_metadata
update_app_info_localization = _SUBMIT.update_app_info_localization
update_localization = _SUBMIT.update_localization
wait_for_build = _SUBMIT.wait_for_build

_SPEC = importlib.util.spec_from_file_location(
    "setup_testflight_access",
    Path(__file__).resolve().parent / "setup_testflight_access.py",
)
_SETUP = importlib.util.module_from_spec(_SPEC)
assert _SPEC.loader is not None
_SPEC.loader.exec_module(_SETUP)

ASCClient = _SETUP.ASCClient
fail = _SETUP.fail
warn = _SETUP.warn
find_app = _SETUP.find_app
make_client = _SETUP.make_client
load_config = _SETUP.load_config


CATEGORY_IDS = {
    "BUSINESS": "BUSINESS",
    "PRODUCTIVITY": "PRODUCTIVITY",
}


def build_review_notes() -> str:
    username = os.environ.get("APP_REVIEW_USERNAME", "test@apple.app.com").strip()
    app_password = os.environ.get("APP_REVIEW_APP_PASSWORD", "").strip()
    server_url = os.environ.get("APP_REVIEW_SERVER_URL", "https://paxdesign.at").strip()
    privacy_url = os.environ.get(
        "APP_REVIEW_PRIVACY_URL", "https://paxdesign.at/datenschutz/"
    ).strip()

    if not app_password:
        fail("APP_REVIEW_APP_PASSWORD is required")

    return f"""PAXDesign Live Chat is an internal business communication and customer support application for authorized PAXdesign staff.

SIGN-IN INSTRUCTIONS
1. Open the PAXDesign Live Chat app.
2. Enter the server URL: {server_url}
3. Sign in using:
   Username: {username}
   Application Password: {app_password}
   (Use the Application Password only — not the primary WordPress password.)

FEATURES TO TEST AFTER SIGN-IN
• Dashboard — business overview and analytics
• Chats — customer live chat inbox
• Team — internal team messaging
• Live — live customer request queue
• Platform — workspace modules and account settings

ADDITIONAL NOTES
• Push notifications are optional and are not required to review the app.
• No in-app purchases.
• No advertisements.
• Privacy Policy: {privacy_url}
"""


def patch_version(
    client: ASCClient,
    version_id: str,
    *,
    release_type: str | None = None,
    copyright_text: str | None = None,
) -> None:
    attrs: dict[str, Any] = {}
    if release_type is not None:
        attrs["releaseType"] = release_type
    if copyright_text is not None:
        attrs["copyright"] = copyright_text
    if not attrs:
        return
    status, response = client.request(
        "PATCH",
        f"/appStoreVersions/{version_id}",
        body={
            "data": {
                "type": "appStoreVersions",
                "id": version_id,
                "attributes": attrs,
            }
        },
        allow_error=True,
    )
    if status not in {200, 201}:
        fail(f"Could not update version ({status}): {json.dumps(response)}")
    if release_type:
        print(f"Set releaseType={release_type}")
    if copyright_text:
        print(f"Set copyright={copyright_text}")


def ensure_review_detail(
    client: ASCClient,
    version_id: str,
    *,
    contact_email: str,
    contact_phone: str,
    contact_first: str,
    contact_last: str,
    notes: str,
) -> None:
    status, payload = client.request(
        "GET",
        f"/appStoreVersions/{version_id}/appStoreReviewDetail",
        allow_error=True,
    )
    review_id = None
    if status == 200:
        data = payload.get("data")
        if isinstance(data, dict):
            review_id = data.get("id")

    attrs = {
        "contactEmail": contact_email,
        "contactPhone": contact_phone,
        "contactFirstName": contact_first,
        "contactLastName": contact_last,
        "notes": notes,
        "demoAccountRequired": True,
    }

    if review_id:
        status, response = client.request(
            "PATCH",
            f"/appStoreReviewDetails/{review_id}",
            body={
                "data": {
                    "type": "appStoreReviewDetails",
                    "id": review_id,
                    "attributes": attrs,
                }
            },
            allow_error=True,
        )
        if status not in {200, 201}:
            fail(f"Could not update review detail ({status}): {json.dumps(response)}")
        print(f"Updated App Review Information ({review_id})")
        return

    status, response = client.request(
        "POST",
        "/appStoreReviewDetails",
        body={
            "data": {
                "type": "appStoreReviewDetails",
                "attributes": attrs,
                "relationships": {
                    "appStoreVersion": {
                        "data": {"type": "appStoreVersions", "id": version_id},
                    }
                },
            }
        },
        allow_error=True,
    )
    if status not in {200, 201}:
        fail(f"Could not create review detail ({status}): {json.dumps(response)}")
    print("Created App Review Information")


def update_categories(client: ASCClient, app_id: str, primary: str, secondary: str) -> None:
    info_payload = client.get(f"/apps/{app_id}/appInfos", **{"limit": "5"})
    infos = info_payload.get("data") or []
    if not infos:
        warn("No appInfos found; skipping category update")
        return
    info_id = infos[0]["id"]
    status, response = client.request(
        "PATCH",
        f"/appInfos/{info_id}",
        body={
            "data": {
                "type": "appInfos",
                "id": info_id,
                "attributes": {
                    "primaryCategory": primary,
                    "secondaryCategory": secondary,
                },
            }
        },
        allow_error=True,
    )
    if status in {200, 201}:
        print(f"Set categories: {primary} / {secondary}")
    else:
        warn(f"Could not update categories ({status}): {json.dumps(response)}")


def main() -> None:
    if os.environ.get("SUBMIT_FOR_REVIEW", "").strip().lower() in {"1", "true", "yes"}:
        fail("SUBMIT_FOR_REVIEW must not be set for prepare workflow")

    metadata = load_metadata()
    issuer_id, key_id, private_key = load_config()
    client = make_client(issuer_id, key_id, private_key)

    app = find_app(client)
    app_id = app["id"]
    version_string = metadata.get("versionString", "2.0.5")
    build_number = str(metadata.get("buildNumber", "127"))
    app_info = metadata.get("appInfo", {})
    categories = metadata.get("categories", {})

    version = find_or_create_version(client, app_id, version_string, fail=fail, warn=warn)
    version_id = version["id"]

    copyright_text = os.environ.get(
        "APP_STORE_COPYRIGHT", "© 2026 PAXdesign"
    ).strip()

    patch_version(
        client,
        version_id,
        release_type="MANUAL",
        copyright_text=copyright_text,
    )

    for locale in metadata.get("locales", ["de-DE"]):
        loc = ensure_localization(client, version_id, locale)
        if not loc:
            warn(f"Skipping metadata for unavailable locale {locale}")
            continue
        loc_id = loc["id"]
        update_localization(
            client,
            loc_id,
            description=(app_info.get("description") or {}).get(locale),
            keywords=(app_info.get("keywords") or {}).get(locale),
            promotional_text=(app_info.get("promotionalText") or {}).get(locale),
            whats_new=(app_info.get("whatsNew") or {}).get(locale),
            support_url=app_info.get("supportUrl"),
            marketing_url=app_info.get("marketingUrl"),
        )
        update_app_info_localization(
            client,
            app_id,
            locale,
            name=app_info.get("name"),
            subtitle=(app_info.get("subtitle") or {}).get(locale),
            privacy_url=app_info.get("privacyPolicyUrl"),
        )
        print(f"Synced metadata for {locale}")

    update_categories(
        client,
        app_id,
        categories.get("primary", "BUSINESS"),
        categories.get("secondary", "PRODUCTIVITY"),
    )

    build = wait_for_build(client, app_id, build_number)
    ensure_export_compliance(client, build)
    attach_build(client, version_id, build["id"])

    contact_email = os.environ.get("APP_REVIEW_CONTACT_EMAIL", "info@paxdesign.at").strip()
    contact_phone = os.environ.get("APP_REVIEW_CONTACT_PHONE", "+43 681 20543638").strip()
    contact_first = os.environ.get("APP_REVIEW_CONTACT_FIRST", "PAXdesign").strip()
    contact_last = os.environ.get("APP_REVIEW_CONTACT_LAST", "Support").strip()
    notes = build_review_notes()

    ensure_review_detail(
        client,
        version_id,
        contact_email=contact_email,
        contact_phone=contact_phone,
        contact_first=contact_first,
        contact_last=contact_last,
        notes=notes,
    )

    print("App Store Connect preparation complete (not submitted for review)")


if __name__ == "__main__":
    main()
