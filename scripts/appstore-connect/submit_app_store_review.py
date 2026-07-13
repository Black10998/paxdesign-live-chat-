#!/usr/bin/env python3
"""Prepare App Store metadata and submit a version for Apple review."""

from __future__ import annotations

import importlib.util
import json
import os
import sys
import time
from pathlib import Path
from typing import Any

ROOT = Path(__file__).resolve().parents[2]
METADATA_PATH = ROOT / "docs" / "app-store" / "metadata.json"
REPORT_PATH = ROOT / "docs" / "app-store" / "RELEASE_REPORT.md"

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
find_build = _SETUP.find_build
make_client = _SETUP.make_client
load_config = _SETUP.load_config
get_build_beta_detail = _SETUP.get_build_beta_detail


def load_metadata() -> dict[str, Any]:
    if not METADATA_PATH.exists():
        fail(f"Missing metadata file: {METADATA_PATH}")
    return json.loads(METADATA_PATH.read_text(encoding="utf-8"))


def find_or_create_version(client: ASCClient, app_id: str, version_string: str) -> dict[str, Any]:
    payload = client.get(
        f"/apps/{app_id}/appStoreVersions",
        **{"filter[platform]": "IOS", "limit": "30"},
    )
    editable_states = {
        "PREPARE_FOR_SUBMISSION",
        "DEVELOPER_REJECTED",
        "WAITING_FOR_REVIEW",
        "READY_FOR_REVIEW",
        "PENDING_DEVELOPER_RELEASE",
    }
    for item in payload.get("data") or []:
        attrs = item.get("attributes") or {}
        if attrs.get("versionString") == version_string:
            if attrs.get("appStoreState") in editable_states or attrs.get("appStoreState") == "REJECTED":
                return item
            if attrs.get("appStoreState") in {"IN_REVIEW", "PENDING_APPLE_RELEASE", "READY_FOR_SALE"}:
                print(f"Version {version_string} already in state {attrs.get('appStoreState')}")
                return item

    payload = client.post(
        "/appStoreVersions",
        {
            "data": {
                "type": "appStoreVersions",
                "attributes": {
                    "platform": "IOS",
                    "versionString": version_string,
                },
                "relationships": {
                    "app": {"data": {"type": "apps", "id": app_id}},
                },
            }
        },
    )
    print(f"Created App Store version {version_string}")
    return payload["data"]


def get_localization(client: ASCClient, version_id: str, locale: str) -> dict[str, Any] | None:
    payload = client.get(f"/appStoreVersions/{version_id}/appStoreVersionLocalizations", **{"limit": "20"})
    for item in payload.get("data") or []:
        if (item.get("attributes") or {}).get("locale") == locale:
            return item
    return None


def ensure_localization(client: ASCClient, version_id: str, locale: str) -> dict[str, Any]:
    existing = get_localization(client, version_id, locale)
    if existing:
        return existing
    payload = client.post(
        "/appStoreVersionLocalizations",
        {
            "data": {
                "type": "appStoreVersionLocalizations",
                "attributes": {"locale": locale},
                "relationships": {
                    "appStoreVersion": {"data": {"type": "appStoreVersions", "id": version_id}},
                },
            }
        },
    )
    print(f"Created localization {locale}")
    return payload["data"]


def update_localization(
    client: ASCClient,
    loc_id: str,
    *,
    description: str | None = None,
    keywords: str | None = None,
    promotional_text: str | None = None,
    whats_new: str | None = None,
    support_url: str | None = None,
    marketing_url: str | None = None,
) -> None:
    attrs: dict[str, Any] = {}
    if description is not None:
        attrs["description"] = description
    if keywords is not None:
        attrs["keywords"] = keywords
    if promotional_text is not None:
        attrs["promotionalText"] = promotional_text
    if whats_new is not None:
        attrs["whatsNew"] = whats_new
    if support_url is not None:
        attrs["supportUrl"] = support_url
    if marketing_url is not None:
        attrs["marketingUrl"] = marketing_url
    if not attrs:
        return
    client.patch(
        f"/appStoreVersionLocalizations/{loc_id}",
        {"data": {"type": "appStoreVersionLocalizations", "id": loc_id, "attributes": attrs}},
    )


def update_app_info_localization(
    client: ASCClient,
    app_id: str,
    locale: str,
    *,
    name: str | None = None,
    subtitle: str | None = None,
    privacy_url: str | None = None,
) -> None:
    info_payload = client.get(f"/apps/{app_id}/appInfos", **{"limit": "5"})
    infos = info_payload.get("data") or []
    if not infos:
        warn("No appInfos found; skipping app info localization update")
        return
    info_id = infos[0]["id"]
    payload = client.get(f"/appInfos/{info_id}/appInfoLocalizations", **{"limit": "20"})
    loc = None
    for item in payload.get("data") or []:
        if (item.get("attributes") or {}).get("locale") == locale:
            loc = item
            break
    if not loc:
        status, response = client.request(
            "POST",
            "/appInfoLocalizations",
            body={
                "data": {
                    "type": "appInfoLocalizations",
                    "attributes": {"locale": locale},
                    "relationships": {
                        "appInfo": {"data": {"type": "appInfos", "id": info_id}},
                    },
                }
            },
            allow_error=True,
        )
        if status not in {200, 201}:
            warn(f"Could not create app info localization {locale}: {json.dumps(response)}")
            return
        loc = response["data"]

    attrs: dict[str, Any] = {}
    if name is not None:
        attrs["name"] = name
    if subtitle is not None:
        attrs["subtitle"] = subtitle
    if privacy_url is not None:
        attrs["privacyPolicyUrl"] = privacy_url
    if attrs:
        client.patch(
            f"/appInfoLocalizations/{loc['id']}",
            {"data": {"type": "appInfoLocalizations", "id": loc["id"], "attributes": attrs}},
        )


def attach_build(client: ASCClient, version_id: str, build_id: str) -> None:
    client.patch(
        f"/appStoreVersions/{version_id}",
        {
            "data": {
                "type": "appStoreVersions",
                "id": version_id,
                "relationships": {
                    "build": {"data": {"type": "builds", "id": build_id}},
                },
            }
        },
    )
    print(f"Attached build {build_id} to version {version_id}")


def wait_for_build(client: ASCClient, app_id: str, build_number: str) -> dict[str, Any]:
    poll_seconds = int(os.environ.get("APP_STORE_POLL_SECONDS", "30"))
    poll_timeout = int(os.environ.get("APP_STORE_POLL_TIMEOUT", "3600"))
    deadline = time.time() + poll_timeout
    while time.time() < deadline:
        build = find_build(client, app_id, build_number)
        if build is None:
            print(f"Waiting for build {build_number}...")
            time.sleep(poll_seconds)
            continue
        state = (build.get("attributes") or {}).get("processingState", "UNKNOWN")
        print(f"Build {build_number} processingState={state}")
        if state == "VALID":
            return build
        if state in {"FAILED", "INVALID"}:
            fail(f"Build {build_number} failed processing: {state}")
        time.sleep(poll_seconds)
    fail(f"Timed out waiting for build {build_number}")


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
    print("Set usesNonExemptEncryption=false")


def submit_for_review(client: ASCClient, version_id: str) -> dict[str, Any]:
    status, payload = client.request(
        "POST",
        "/appStoreVersionSubmissions",
        body={
            "data": {
                "type": "appStoreVersionSubmissions",
                "relationships": {
                    "appStoreVersion": {"data": {"type": "appStoreVersions", "id": version_id}},
                },
            }
        },
        allow_error=True,
    )
    if status in {200, 201}:
        print("Submitted App Store version for review")
        return payload.get("data") or {}
    if status == 409:
        warn("Version may already be submitted for review")
        return payload
    fail(f"Review submission failed ({status}): {json.dumps(payload)}")


def version_status(client: ASCClient, version_id: str) -> dict[str, Any]:
    payload = client.get(f"/appStoreVersions/{version_id}")
    return (payload.get("data") or {}).get("attributes") or {}


def write_report(
    *,
    metadata: dict[str, Any],
    version_attrs: dict[str, Any],
    build: dict[str, Any],
    submission: dict[str, Any] | None,
    warnings: list[str],
) -> None:
    build_attrs = build.get("attributes") or {}
    lines = [
        "# PAXDesign Live Chat — App Store Release Report",
        "",
        f"Generated: {time.strftime('%Y-%m-%d %H:%M:%S UTC', time.gmtime())}",
        "",
        "## Version alignment",
        "",
        f"| Component | Version |",
        f"|-----------|---------|",
        f"| iOS Marketing Version | {metadata.get('versionString')} |",
        f"| iOS Build Number | {metadata.get('buildNumber')} |",
        f"| WordPress Plugin | {metadata.get('wordpressVersion')} |",
        "",
        "## App Store Connect status",
        "",
        f"- **App Store state:** {version_attrs.get('appStoreState', 'unknown')}",
        f"- **Build processing:** {build_attrs.get('processingState', 'unknown')}",
        f"- **Build version:** {build_attrs.get('version', metadata.get('buildNumber'))}",
        f"- **Encryption:** usesNonExemptEncryption={build_attrs.get('usesNonExemptEncryption')}",
        "",
        "## Submission",
        "",
    ]
    if submission:
        lines.append("- **Submitted for Apple review:** Yes")
        lines.append(f"- **Submission ID:** {submission.get('id', 'n/a')}")
    else:
        lines.append("- **Submitted for Apple review:** Pending or already in review")
    lines.extend(["", "## Warnings & recommendations", ""])
    if warnings:
        for item in warnings:
            lines.append(f"- {item}")
    else:
        lines.append("- None reported by automation")
    lines.extend(
        [
            "",
            "## Metadata",
            "",
            f"- Privacy Policy: {metadata['appInfo']['privacyPolicyUrl']}",
            f"- Support URL: {metadata['appInfo']['supportUrl']}",
            f"- Primary category: {metadata['categories']['primary']}",
            f"- Secondary category: {metadata['categories']['secondary']}",
            "",
            "## Review notes",
            "",
            metadata.get("reviewNotes", ""),
            "",
        ]
    )
    REPORT_PATH.write_text("\n".join(lines), encoding="utf-8")
    print(f"Wrote release report to {REPORT_PATH}")


def main() -> None:
    metadata = load_metadata()
    issuer_id, key_id, private_key = load_config()
    client = make_client(issuer_id, key_id, private_key)

    app = find_app(client)
    app_id = app["id"]
    version_string = metadata.get("versionString", "2.0.0")
    build_number = str(metadata.get("buildNumber", os.environ.get("APP_STORE_TARGET_BUILD", "113")))
    app_info = metadata.get("appInfo", {})
    warnings: list[str] = []

    version = find_or_create_version(client, app_id, version_string)
    version_id = version["id"]

    for locale in metadata.get("locales", ["de-DE"]):
        loc = ensure_localization(client, version_id, locale)
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
        print(f"Updated metadata for {locale}")

    build = wait_for_build(client, app_id, build_number)
    ensure_export_compliance(client, build)
    attach_build(client, version_id, build["id"])

    version_attrs = version_status(client, version_id)
    state = version_attrs.get("appStoreState", "")
    submission = None
    if state in {"PREPARE_FOR_SUBMISSION", "DEVELOPER_REJECTED"}:
        submission = submit_for_review(client, version_id)
        version_attrs = version_status(client, version_id)
    elif state in {"WAITING_FOR_REVIEW", "READY_FOR_REVIEW", "IN_REVIEW", "PENDING_DEVELOPER_RELEASE", "READY_FOR_SALE"}:
        print(f"Version already in review/release state: {state}")
    else:
        warnings.append(f"Version state {state} — manual review in App Store Connect may be required")

    detail = get_build_beta_detail(client, build["id"])
    if detail:
        beta_attrs = detail.get("attributes") or {}
        if beta_attrs.get("internalBuildState") not in {"IN_BETA_TESTING", "READY_FOR_BETA_TESTING"}:
            warnings.append(
                f"TestFlight internal state: {beta_attrs.get('internalBuildState')} — verify TestFlight access"
            )

    write_report(
        metadata=metadata,
        version_attrs=version_attrs,
        build=build,
        submission=submission,
        warnings=warnings,
    )
    print("App Store submission workflow complete")


if __name__ == "__main__":
    main()
