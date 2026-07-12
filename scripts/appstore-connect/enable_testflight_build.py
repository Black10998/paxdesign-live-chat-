#!/usr/bin/env python3
"""Enable TestFlight internal/external testing for a specific build."""

from __future__ import annotations

import importlib.util
import json
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
TARGET_BUILD = _SETUP.TARGET_BUILD
TESTER_EMAIL = _SETUP.TESTER_EMAIL
fail = _SETUP.fail
warn = _SETUP.warn
find_app = _SETUP.find_app
find_build = _SETUP.find_build
list_groups = _SETUP.list_groups
add_build_to_group = _SETUP.add_build_to_group
get_build_beta_detail = _SETUP.get_build_beta_detail
make_token = _SETUP.make_token
load_config = _SETUP.load_config
print_diagnostics = _SETUP.print_diagnostics

LOCALES = ("en-US", "de-DE", "ar")


def ui_status(internal: str, external: str) -> str:
    mapping = {
        "READY_FOR_BETA_SUBMISSION": "Ready to Submit (external)",
        "READY_FOR_BETA_TESTING": "Ready to Test",
        "IN_BETA_TESTING": "Testing",
        "MISSING_EXPORT_COMPLIANCE": "Missing Compliance",
        "PROCESSING": "Processing",
        "WAITING_FOR_BETA_REVIEW": "Waiting for Review",
        "IN_BETA_REVIEW": "In Review",
        "BETA_APPROVED": "Approved",
    }
    return (
        f"internal={mapping.get(internal, internal)}; "
        f"external={mapping.get(external, external)}"
    )


def print_build_status(client: ASCClient, build: dict[str, Any]) -> None:
    build_id = build["id"]
    attrs = build.get("attributes") or {}
    version = attrs.get("version", "?")
    processing = attrs.get("processingState", "?")
    encryption = attrs.get("usesNonExemptEncryption")
    print(f"Build {version} ({build_id}) processing={processing} encryption={encryption}")

    detail = get_build_beta_detail(client, build_id)
    if not detail:
        warn("No buildBetaDetail found")
        return
    dattrs = detail.get("attributes") or {}
    internal = dattrs.get("internalBuildState", "UNKNOWN")
    external = dattrs.get("externalBuildState", "UNKNOWN")
    print(f"API states: internal={internal} external={external}")
    print(f"UI mapping: {ui_status(internal, external)}")
    print(
        f"autoNotifyEnabled={dattrs.get('autoNotifyEnabled')} "
        f"didNotify={dattrs.get('didNotify')}"
    )


def ensure_build_export_compliance(client: ASCClient, build: dict[str, Any]) -> None:
    build_id = build["id"]
    attrs = build.get("attributes") or {}
    if attrs.get("usesNonExemptEncryption") is False:
        print("Build already has usesNonExemptEncryption=false")
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
    print("Set build usesNonExemptEncryption=false")


def get_beta_app_review_detail(client: ASCClient, app_id: str) -> dict[str, Any] | None:
    payload = client.get("/betaAppReviewDetails", **{"filter[app]": app_id, "limit": "1"})
    data = payload.get("data") or []
    return data[0] if data else None


def ensure_beta_app_review_detail(client: ASCClient, app_id: str) -> None:
    detail = get_beta_app_review_detail(client, app_id)
    if detail is None:
        warn("No betaAppReviewDetails resource found for app")
        return

    detail_id = detail["id"]
    attrs = detail.get("attributes") or {}
    missing = [
        name
        for name, key in (
            ("contactEmail", "contactEmail"),
            ("contactPhone", "contactPhone"),
            ("contactFirstName", "contactFirstName"),
            ("contactLastName", "contactLastName"),
        )
        if not attrs.get(key)
    ]
    if not missing:
        print("Beta app review contact information already complete")
        return

    local_part = TESTER_EMAIL.split("@", 1)[0]
    first_name = local_part.replace(".", " ").replace("_", " ").title() or "Tester"
    client.patch(
        f"/betaAppReviewDetails/{detail_id}",
        {
            "data": {
                "type": "betaAppReviewDetails",
                "id": detail_id,
                "attributes": {
                    "contactEmail": TESTER_EMAIL,
                    "contactPhone": "+436641234567",
                    "contactFirstName": first_name,
                    "contactLastName": "Tester",
                    "demoAccountRequired": False,
                    "notes": "Internal TestFlight testing for PAXDesign Live Chat.",
                },
            }
        },
    )
    print("Updated beta app review contact information")


def list_beta_app_localizations(client: ASCClient, app_id: str) -> dict[str, dict[str, Any]]:
    payload = client.get(f"/apps/{app_id}/betaAppLocalizations", **{"limit": "20"})
    result: dict[str, dict[str, Any]] = {}
    for item in payload.get("data") or []:
        locale = (item.get("attributes") or {}).get("locale", "?")
        result[locale] = item
    return result


def ensure_beta_app_localizations(client: ASCClient, app_id: str) -> None:
    existing = list_beta_app_localizations(client, app_id)
    if existing:
        print(f"Existing beta app localizations: {', '.join(existing)}")

    for locale in LOCALES:
        attrs = {
            "description": "PAXDesign Live Chat TestFlight beta build.",
            "feedbackEmail": TESTER_EMAIL,
            "marketingUrl": "https://paxdesign.at",
            "privacyPolicyUrl": "https://paxdesign.at/privacy",
        }
        if locale in existing:
            loc_id = existing[locale]["id"]
            client.patch(
                f"/betaAppLocalizations/{loc_id}",
                {
                    "data": {
                        "type": "betaAppLocalizations",
                        "id": loc_id,
                        "attributes": attrs,
                    }
                },
            )
            print(f"Updated beta app localization ({locale})")
            continue

        status, response = client.request(
            "POST",
            "/betaAppLocalizations",
            body={
                "data": {
                    "type": "betaAppLocalizations",
                    "attributes": {"locale": locale, **attrs},
                    "relationships": {
                        "app": {"data": {"type": "apps", "id": app_id}},
                    },
                }
            },
            allow_error=True,
        )
        if status in {200, 201}:
            print(f"Created beta app localization ({locale})")
        elif status == 409:
            print(f"Beta app localization already exists ({locale})")
        else:
            warn(f"Could not create beta app localization {locale}: {json.dumps(response)}")


def ensure_beta_build_localizations(client: ASCClient, build_id: str) -> None:
    payload = client.get(f"/builds/{build_id}/betaBuildLocalizations", **{"limit": "20"})
    existing = {
        (item.get("attributes") or {}).get("locale", "?"): item
        for item in payload.get("data") or []
    }
    for locale in LOCALES:
        whats_new = "PAXDesign Live Chat build for TestFlight testing."
        if locale in existing:
            loc_id = existing[locale]["id"]
            client.patch(
                f"/betaBuildLocalizations/{loc_id}",
                {
                    "data": {
                        "type": "betaBuildLocalizations",
                        "id": loc_id,
                        "attributes": {"whatsNew": whats_new},
                    }
                },
            )
            print(f"Updated beta build localization ({locale})")
            continue

        status, response = client.request(
            "POST",
            "/betaBuildLocalizations",
            body={
                "data": {
                    "type": "betaBuildLocalizations",
                    "attributes": {"locale": locale, "whatsNew": whats_new},
                    "relationships": {
                        "build": {"data": {"type": "builds", "id": build_id}},
                    },
                }
            },
            allow_error=True,
        )
        if status in {200, 201}:
            print(f"Created beta build localization ({locale})")
        elif status == 409:
            print(f"Beta build localization already exists ({locale})")
        else:
            warn(f"Could not create beta build localization {locale}: {json.dumps(response)}")


def enable_build_beta_detail(client: ASCClient, build_id: str) -> None:
    detail = get_build_beta_detail(client, build_id)
    if not detail:
        warn("No buildBetaDetail to patch")
        return
    detail_id = detail["id"]
    client.patch(
        f"/buildBetaDetails/{detail_id}",
        {
            "data": {
                "type": "buildBetaDetails",
                "id": detail_id,
                "attributes": {"autoNotifyEnabled": True},
            }
        },
    )
    print("Enabled autoNotifyEnabled on buildBetaDetail")


def find_internal_group(client: ASCClient, app_id: str) -> dict[str, Any]:
    groups = list_groups(client, app_id)
    for group in groups:
        attrs = group.get("attributes") or {}
        if attrs.get("isInternalGroup") is True:
            return group
    fail("No internal TestFlight group found")


def submit_external_beta_review(client: ASCClient, build_id: str) -> bool:
    detail = get_build_beta_detail(client, build_id)
    if not detail:
        return False
    external = (detail.get("attributes") or {}).get("externalBuildState", "UNKNOWN")
    if external in {"IN_BETA_TESTING", "READY_FOR_BETA_TESTING", "WAITING_FOR_BETA_REVIEW", "IN_BETA_REVIEW"}:
        print(f"External beta review already in progress or approved ({external})")
        return True

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
        return True
    warn(f"External beta review submission failed ({status}): {json.dumps(payload)}")
    return False


def wait_for_internal_ready(client: ASCClient, build_id: str, timeout: int = 120) -> str:
    deadline = time.time() + timeout
    last = ""
    while time.time() < deadline:
        detail = get_build_beta_detail(client, build_id)
        if not detail:
            time.sleep(5)
            continue
        internal = (detail.get("attributes") or {}).get("internalBuildState", "UNKNOWN")
        if internal != last:
            print(f"internalBuildState={internal}")
            last = internal
        if internal in {"IN_BETA_TESTING", "READY_FOR_BETA_TESTING"}:
            return internal
        time.sleep(5)
    detail = get_build_beta_detail(client, build_id)
    if detail:
        return (detail.get("attributes") or {}).get("internalBuildState", "UNKNOWN")
    return "UNKNOWN"


def main() -> None:
    issuer_id, key_id, private_key = load_config()
    client = ASCClient(make_token(issuer_id, key_id, private_key))

    app = find_app(client)
    app_id = app["id"]
    build = find_build(client, app_id, TARGET_BUILD)
    if build is None:
        fail(f"Build {TARGET_BUILD} not found")
    if (build.get("attributes") or {}).get("processingState") != "VALID":
        fail("Build is not VALID")

    build_id = build["id"]
    build_version = str((build.get("attributes") or {}).get("version", TARGET_BUILD))

    print("=== Before enablement ===")
    print_build_status(client, build)
    print_diagnostics(client, app, build, list_groups(client, app_id))

    ensure_build_export_compliance(client, build)
    ensure_beta_app_review_detail(client, app_id)
    ensure_beta_app_localizations(client, app_id)
    ensure_beta_build_localizations(client, build_id)
    enable_build_beta_detail(client, build_id)

    internal_group = find_internal_group(client, app_id)
    internal_group_id = internal_group["id"]
    internal_name = (internal_group.get("attributes") or {}).get("name", internal_group_id)
    add_build_to_group(client, internal_group_id, build_id)
    print(f"Linked build {build_version} to internal group '{internal_name}'")

    import os

    if os.environ.get("ALLOW_EXTERNAL_BETA_REVIEW", "").strip() == "1":
        submit_external_beta_review(client, build_id)
    else:
        print("Skipping external beta review (ALLOW_EXTERNAL_BETA_REVIEW not set)")

    print("=== Waiting for internal TestFlight state ===")
    internal_state = wait_for_internal_ready(client, build_id)

    print("=== After enablement ===")
    build = find_build(client, app_id, TARGET_BUILD) or build
    print_build_status(client, build)
    print_diagnostics(client, app, build, list_groups(client, app_id))

    detail = get_build_beta_detail(client, build_id)
    external_state = (
        (detail.get("attributes") or {}).get("externalBuildState", "UNKNOWN")
        if detail
        else "UNKNOWN"
    )

    if internal_state in {"IN_BETA_TESTING", "READY_FOR_BETA_TESTING"}:
        print("INTERNAL_TESTING_READY=true")
        print(
            "Internal TestFlight is ready. Open the TestFlight app directly with "
            f"{TESTER_EMAIL}; do not use email invite links for internal testing."
        )
    else:
        warn(f"Internal build state is still {internal_state}")

    if external_state in {"IN_BETA_TESTING", "READY_FOR_BETA_TESTING", "WAITING_FOR_BETA_REVIEW", "IN_BETA_REVIEW"}:
        print("EXTERNAL_BETA_REVIEW_SUBMITTED=true")
    elif external_state == "READY_FOR_BETA_SUBMISSION":
        warn(
            "External status remains Ready to Submit. Complete any remaining TestFlight "
            "Test Information fields manually in App Store Connect if API submission failed."
        )

    print(f"TESTFLIGHT_BUILD={build_version}")
    print(f"TESTFLIGHT_INTERNAL_STATE={internal_state}")
    print(f"TESTFLIGHT_EXTERNAL_STATE={external_state}")


if __name__ == "__main__":
    main()
