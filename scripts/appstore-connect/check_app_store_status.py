#!/usr/bin/env python3
"""Print current App Store Connect release status for PAXDesign Live Chat."""

from __future__ import annotations

import importlib.util
import json
import sys
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
find_app = _SETUP.find_app
find_build = _SETUP.find_build
make_client = _SETUP.make_client
load_config = _SETUP.load_config

STATE_LABELS = {
    "PREPARE_FOR_SUBMISSION": "Prepare for Submission",
    "WAITING_FOR_REVIEW": "Waiting for Review",
    "IN_REVIEW": "In Review",
    "PENDING_DEVELOPER_RELEASE": "Pending Developer Release",
    "READY_FOR_SALE": "Available on the App Store",
    "READY_FOR_DISTRIBUTION": "Ready for Distribution",
    "PENDING_APPLE_RELEASE": "Pending Apple Release",
    "REJECTED": "Rejected",
    "DEVELOPER_REJECTED": "Developer Rejected",
    "METADATA_REJECTED": "Metadata Rejected",
    "INVALID_BINARY": "Invalid Binary",
    "PROCESSING_FOR_APP_STORE": "Processing for App Store",
}


def ui_status(state: str) -> str:
    return STATE_LABELS.get(state, state)


def public_availability(state: str) -> str:
    if state == "READY_FOR_SALE":
        return "PUBLIC — Available on the App Store"
    if state == "PENDING_DEVELOPER_RELEASE":
        return "NOT PUBLIC — Approved; awaiting manual release by developer"
    if state in {"WAITING_FOR_REVIEW", "IN_REVIEW", "READY_FOR_REVIEW"}:
        return "NOT PUBLIC — Under Apple review"
    if state in {"PREPARE_FOR_SUBMISSION", "DEVELOPER_REJECTED", "REJECTED", "METADATA_REJECTED"}:
        return "NOT PUBLIC — Not submitted or rejected"
    return f"NOT PUBLIC — State: {state}"


def get_submission(client: ASCClient, version_id: str) -> dict[str, Any] | None:
    status, payload = client.request(
        "GET",
        "/appStoreVersionSubmissions",
        params={"filter[appStoreVersion]": version_id, "limit": "1"},
        allow_error=True,
    )
    if status != 200:
        return None
    data = payload.get("data") or []
    return data[0] if data else None


def get_age_rating_declaration(client: ASCClient, app_id: str) -> dict[str, Any] | None:
    info_payload = client.get(f"/apps/{app_id}/appInfos", **{"limit": "5"})
    infos = info_payload.get("data") or []
    if not infos:
        return None
    info_id = infos[0]["id"]
    status, payload = client.request(
        "GET",
        f"/appInfos/{info_id}/ageRatingDeclaration",
        allow_error=True,
    )
    if status != 200:
        return None
    data = payload.get("data") or {}
    return (data.get("attributes") or {}) if data else None


def main() -> None:
    issuer_id, key_id, private_key = load_config()
    client = make_client(issuer_id, key_id, private_key)

    app = find_app(client)
    app_id = app["id"]
    app_name = (app.get("attributes") or {}).get("name", "?")

    versions_payload = client.get(
        f"/apps/{app_id}/appStoreVersions",
        **{"filter[platform]": "IOS", "limit": "10"},
    )
    versions = versions_payload.get("data") or []
    if not versions:
        fail("No iOS App Store versions found")

    # Prefer the newest non-replaced version
    target = versions[0]
    for item in versions:
        attrs = item.get("attributes") or {}
        if attrs.get("appStoreState") not in {"REPLACED_WITH_NEW_VERSION", "NOT_APPLICABLE"}:
            target = item
            break

    version_id = target["id"]
    attrs = target.get("attributes") or {}
    version_string = attrs.get("versionString", "?")
    state = attrs.get("appStoreState", "UNKNOWN")
    release_type = attrs.get("releaseType", "UNKNOWN")

    build_rel = (target.get("relationships") or {}).get("build", {}).get("data")
    build_info = "not attached"
    build_number = "?"
    processing = "?"
    if build_rel:
        build_id = build_rel["id"]
        build_payload = client.get(f"/builds/{build_id}")
        battrs = (build_payload.get("data") or {}).get("attributes") or {}
        build_number = battrs.get("version", "?")
        processing = battrs.get("processingState", "?")
        build_info = f"Build {build_number} ({build_id}) processing={processing}"

    submission = get_submission(client, version_id)
    submission_state = "none"
    if submission:
        submission_state = "active"

    report = {
        "appName": app_name,
        "appId": app_id,
        "bundleId": "at.paxdesign.livechat",
        "versionString": version_string,
        "versionId": version_id,
        "appStoreState": state,
        "appStoreStateLabel": ui_status(state),
        "publicAvailability": public_availability(state),
        "releaseType": release_type,
        "releaseTypeLabel": (
            "Manual release after approval"
            if release_type == "MANUAL"
            else "Automatic release after approval"
            if release_type == "AFTER_APPROVAL"
            else release_type
        ),
        "build": build_info,
        "submission": submission_state,
        "ageRatingDeclaration": get_age_rating_declaration(client, app_id),
        "ascAgeRatingUrl": f"https://appstoreconnect.apple.com/apps/{app_id}/distribution/agerating",
        "ascAppInformationUrl": f"https://appstoreconnect.apple.com/apps/{app_id}/distribution/info",
        "ascBusinessComplianceUrl": "https://appstoreconnect.apple.com/business/compliance",
        "ascVersionUrl": f"https://appstoreconnect.apple.com/apps/{app_id}/distribution/ios/version/inflight",
        "ascAppUrl": f"https://appstoreconnect.apple.com/apps/{app_id}/appstore",
    }

    print(json.dumps(report, indent=2, ensure_ascii=False))
    print()
    print("=== Summary ===")
    print(f"Status: {report['appStoreStateLabel']} ({state})")
    print(f"Public: {report['publicAvailability']}")
    print(f"Release: {report['releaseTypeLabel']}")
    print(f"ASC: {report['ascVersionUrl']}")


if __name__ == "__main__":
    main()
