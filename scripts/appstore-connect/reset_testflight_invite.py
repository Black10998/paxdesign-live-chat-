#!/usr/bin/env python3
"""Reset a TestFlight tester invite: remove, re-add, link build, resend."""

from __future__ import annotations

import importlib.util
import json
import os
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

API_BASE = _SETUP.API_BASE
ASCClient = _SETUP.ASCClient
TESTER_EMAIL = _SETUP.TESTER_EMAIL
TARGET_BUILD = _SETUP.TARGET_BUILD
EXTERNAL_GROUP_NAME = _SETUP.EXTERNAL_GROUP_NAME
fail = _SETUP.fail
warn = _SETUP.warn
find_app = _SETUP.find_app
find_build = _SETUP.find_build
list_groups = _SETUP.list_groups
group_build_versions = _SETUP.group_build_versions
group_tester_emails = _SETUP.group_tester_emails
find_beta_tester = _SETUP.find_beta_tester
add_build_to_group = _SETUP.add_build_to_group
ensure_beta_build_localization = _SETUP.ensure_beta_build_localization
ensure_export_compliance = _SETUP.ensure_export_compliance
get_build_beta_detail = _SETUP.get_build_beta_detail
make_token = _SETUP.make_token
load_config = _SETUP.load_config
print_diagnostics = _SETUP.print_diagnostics


def remove_tester_from_group(client: ASCClient, group_id: str, tester_id: str) -> bool:
    status, payload = client.request(
        "DELETE",
        f"/betaGroups/{group_id}/relationships/betaTesters",
        body={"data": [{"type": "betaTesters", "id": tester_id}]},
        allow_error=True,
    )
    if status in {200, 204}:
        print(f"Removed tester {tester_id} from group {group_id}")
        return True
    if status == 404:
        print(f"Tester {tester_id} was not in group {group_id}")
        return True
    warn(f"Could not remove tester from group ({status}): {json.dumps(payload)}")
    return False


def delete_beta_tester(client: ASCClient, tester_id: str) -> bool:
    status, payload = client.request(
        "DELETE",
        f"/betaTesters/{tester_id}",
        allow_error=True,
    )
    if status in {200, 204}:
        print(f"Deleted beta tester {tester_id}")
        return True
    warn(f"Could not delete beta tester ({status}): {json.dumps(payload)}")
    return False


def remove_tester_from_all_groups(client: ASCClient, app_id: str, email: str) -> None:
    tester = find_beta_tester(client, email)
    if tester is None:
        print(f"No existing beta tester record for {email}")
        return

    tester_id = tester["id"]
    groups = list_groups(client, app_id)
    for group in groups:
        gid = group["id"]
        name = (group.get("attributes") or {}).get("name", gid)
        if email in group_tester_emails(client, gid):
            remove_tester_from_group(client, gid, tester_id)
            print(f"Removed {email} from group '{name}'")

    delete_beta_tester(client, tester_id)
    if find_beta_tester(client, email) is not None:
        fail(f"Beta tester record for {email} still exists after delete")
    print(f"Confirmed {email} removed from all TestFlight groups")


def app_primary_locale(client: ASCClient, app_id: str) -> str:
    payload = client.get(f"/apps/{app_id}")
    return (payload.get("data", {}).get("attributes") or {}).get("primaryLocale", "en-US")


def ensure_beta_app_localization(client: ASCClient, app_id: str) -> None:
    payload = client.get(f"/apps/{app_id}/betaAppLocalizations", **{"limit": "20"})
    existing = payload.get("data") or []
    locales = {
        (item.get("attributes") or {}).get("locale", "?"): item for item in existing
    }
    if locales:
        print(f"Beta app localizations exist: {', '.join(locales)}")

    locale = app_primary_locale(client, app_id)
    targets = []
    for candidate in (locale, "en-US", "de-DE", "en"):
        if candidate not in locales and candidate not in targets:
            targets.append(candidate)

    for candidate in targets:
        status, response = client.request(
            "POST",
            "/betaAppLocalizations",
            body={
                "data": {
                    "type": "betaAppLocalizations",
                    "attributes": {
                        "locale": candidate,
                        "description": "PAXDesign Live Chat TestFlight beta.",
                        "feedbackEmail": TESTER_EMAIL,
                        "marketingUrl": "https://paxdesign.at",
                        "privacyPolicyUrl": "https://paxdesign.at/privacy",
                    },
                    "relationships": {
                        "app": {"data": {"type": "apps", "id": app_id}},
                    },
                }
            },
            allow_error=True,
        )
        if status in {200, 201}:
            print(f"Created beta app localization ({candidate})")
        elif status == 409:
            print(f"Beta app localization already exists ({candidate})")
        else:
            warn(
                f"Could not create beta app localization ({candidate}, {status}): "
                f"{json.dumps(response)}"
            )

    payload = client.get(f"/apps/{app_id}/betaAppLocalizations", **{"limit": "20"})
    for item in payload.get("data") or []:
        loc_id = item["id"]
        attrs = item.get("attributes") or {}
        if attrs.get("privacyPolicyUrl") and attrs.get("marketingUrl"):
            continue
        client.patch(
            f"/betaAppLocalizations/{loc_id}",
            {
                "data": {
                    "type": "betaAppLocalizations",
                    "id": loc_id,
                    "attributes": {
                        "privacyPolicyUrl": "https://paxdesign.at/privacy",
                        "marketingUrl": "https://paxdesign.at",
                        "feedbackEmail": TESTER_EMAIL,
                    },
                }
            },
        )
        print(f"Updated beta app localization {attrs.get('locale', loc_id)} metadata")

    payload = client.get(f"/apps/{app_id}/betaAppLocalizations", **{"limit": "20"})
    if not payload.get("data"):
        fail("No beta app localizations exist after setup")


def submit_external_beta_review(client: ASCClient, build_id: str) -> bool:
    detail = get_build_beta_detail(client, build_id)
    if not detail:
        warn("No buildBetaDetail found")
        return False
    external_state = (detail.get("attributes") or {}).get("externalBuildState", "UNKNOWN")
    print(f"External build state before review: {external_state}")
    if external_state in {"IN_BETA_TESTING", "READY_FOR_BETA_TESTING"}:
        print("External beta testing already enabled for this build")
        return True

    for attempt in range(1, 6):
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
        if "betaAppLocalization" in json.dumps(payload) and attempt < 5:
            print(f"Waiting for beta app localization (attempt {attempt})")
            time.sleep(6)
            continue
        warn(f"Beta review submission failed ({status}): {json.dumps(payload)}")
        return False
    return False


def create_external_tester(
    client: ASCClient, group_id: str, email: str
) -> dict[str, Any]:
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
    if status not in {200, 201}:
        fail(f"Could not create beta tester ({status}): {json.dumps(payload)}")
    tester = payload["data"]
    print(f"Created fresh beta tester for {email} ({tester['id']})")
    return tester


def resend_invitation(client: ASCClient, app_id: str, tester_id: str) -> bool:
    status, payload = client.request(
        "POST",
        "/betaTesterInvitations",
        body={
            "data": {
                "type": "betaTesterInvitations",
                "relationships": {
                    "betaTester": {"data": {"type": "betaTesters", "id": tester_id}},
                    "app": {"data": {"type": "apps", "id": app_id}},
                },
            }
        },
        allow_error=True,
    )
    if status in {200, 201}:
        print(f"Resent TestFlight invitation to {TESTER_EMAIL}")
        return True
    detail = json.dumps(payload)
    if "NO_INSTALLABLE_BUILDS" in detail:
        warn(
            "Cannot send external email invite yet: build is not installable for "
            "external testers until Apple approves external beta review."
        )
        return False
    fail(f"Could not resend TestFlight invitation ({status}): {detail}")


def get_tester_state(client: ASCClient, app_id: str, email: str) -> str:
    payload = client.get(
        "/betaTesters",
        **{"filter[email]": email, "filter[apps]": app_id, "limit": "1"},
    )
    data = payload.get("data") or []
    if not data:
        return "NOT_FOUND"
    return (data[0].get("attributes") or {}).get("state", "UNKNOWN")


def find_internal_group(client: ASCClient, app_id: str) -> dict[str, Any]:
    groups = list_groups(client, app_id)
    for group in groups:
        attrs = group.get("attributes") or {}
        if attrs.get("isInternalGroup") is True:
            return group
    fail(f"No internal TestFlight group found for app {app_id}")


def find_external_group(client: ASCClient, app_id: str) -> dict[str, Any]:
    groups = list_groups(client, app_id)
    for group in groups:
        attrs = group.get("attributes") or {}
        if attrs.get("isInternalGroup") is False and attrs.get("name") == EXTERNAL_GROUP_NAME:
            return group
    for group in groups:
        attrs = group.get("attributes") or {}
        if attrs.get("isInternalGroup") is False:
            return group
    fail(f"No external TestFlight group found for app {app_id}")


def verify_invite_ready(
    client: ASCClient,
    app_id: str,
    external_group_id: str,
    internal_group_id: str,
    build_id: str,
    build_version: str,
    *,
    invite_sent: bool,
) -> None:
    external_builds = group_build_versions(client, external_group_id)
    external_testers = group_tester_emails(client, external_group_id)
    internal_builds = group_build_versions(client, internal_group_id)
    internal_testers = group_tester_emails(client, internal_group_id)

    if build_version not in external_builds:
        fail(f"Build {build_version} is not linked to external group after reset")
    if build_version not in internal_builds:
        fail(f"Build {build_version} is not linked to internal group after reset")
    if TESTER_EMAIL not in external_testers:
        fail(f"{TESTER_EMAIL} is not in external group after reset")

    state = get_tester_state(client, app_id, TESTER_EMAIL)
    print(f"Tester invite state: {state}")
    if invite_sent and state not in {"INVITED", "ACCEPTED", "INSTALLED"}:
        fail(f"Unexpected tester state after invite reset: {state}")
    if not invite_sent and state not in {"NOT_INVITED", "INVITED", "ACCEPTED", "INSTALLED"}:
        fail(f"Unexpected tester state after invite reset: {state}")

    detail = get_build_beta_detail(client, build_id)
    external_state = (
        (detail.get("attributes") or {}).get("externalBuildState", "UNKNOWN")
        if detail
        else "UNKNOWN"
    )
    internal_state = (
        (detail.get("attributes") or {}).get("internalBuildState", "UNKNOWN")
        if detail
        else "UNKNOWN"
    )
    print(f"Build states: internal={internal_state} external={external_state}")
    print(
        f"Internal group build_{build_version}={build_version in internal_builds} "
        f"tester_in_group={TESTER_EMAIL in internal_testers}"
    )

    if invite_sent and external_state in {"IN_BETA_TESTING", "READY_FOR_BETA_TESTING"}:
        print("INVITE_VALID=true")
        return

    if internal_state in {"IN_BETA_TESTING", "READY_FOR_BETA_TESTING"}:
        print("INTERNAL_ACCESS_VALID=true")
        print(
            "For this App Store Connect account, open the TestFlight app directly "
            f"(signed in as {TESTER_EMAIL}). Do not use the email invite link until "
            "external beta review is approved."
        )
        return

    fail(
        "Build is not installable yet for internal or external TestFlight access. "
        f"internal={internal_state} external={external_state}"
    )


def main() -> None:
    issuer_id, key_id, private_key = load_config()
    client = ASCClient(make_token(issuer_id, key_id, private_key))

    app = find_app(client)
    app_id = app["id"]
    build = find_build(client, app_id, TARGET_BUILD)
    if build is None:
        fail(f"Build {TARGET_BUILD} not found in TestFlight")

    build_id = build["id"]
    build_version = str((build.get("attributes") or {}).get("version", TARGET_BUILD))
    if (build.get("attributes") or {}).get("processingState") != "VALID":
        fail(f"Build {build_version} is not VALID")

    print(f"=== Reset TestFlight invite for {TESTER_EMAIL} ===")
    print_diagnostics(client, app, build, list_groups(client, app_id))

    remove_tester_from_all_groups(client, app_id, TESTER_EMAIL)

    ensure_export_compliance(client, build)
    ensure_beta_app_localization(client, app_id)
    ensure_beta_build_localization(client, build_id)
    import os

    if os.environ.get("ALLOW_EXTERNAL_BETA_REVIEW", "").strip() == "1":
        submit_external_beta_review(client, build_id)
    else:
        print("Skipping external beta review (ALLOW_EXTERNAL_BETA_REVIEW not set)")

    internal_group = find_internal_group(client, app_id)
    internal_group_id = internal_group["id"]
    internal_group_name = (internal_group.get("attributes") or {}).get("name", internal_group_id)
    if not add_build_to_group(client, internal_group_id, build_id):
        fail(f"Could not link build {build_version} to internal group")
    print(f"Linked build {build_version} to internal group '{internal_group_name}'")

    external_group = find_external_group(client, app_id)
    group_id = external_group["id"]
    group_name = (external_group.get("attributes") or {}).get("name", group_id)
    print(f"Using external group: {group_name} ({group_id})")

    if not add_build_to_group(client, group_id, build_id):
        fail(f"Could not link build {build_version} to external group")

    tester = create_external_tester(client, group_id, TESTER_EMAIL)
    invite_sent = resend_invitation(client, app_id, tester["id"])

    print("=== Post-reset verification ===")
    print_diagnostics(client, app, build, list_groups(client, app_id))
    verify_invite_ready(
        client,
        app_id,
        group_id,
        internal_group_id,
        build_id,
        build_version,
        invite_sent=invite_sent,
    )

    print("TESTFLIGHT_INVITE_RESET=true")
    print(f"TESTFLIGHT_BUILD={build_version}")
    print(f"TESTFLIGHT_TESTER={TESTER_EMAIL}")
    if invite_sent:
        print(
            "TESTFLIGHT_NEXT_STEP=Use the NEW invitation email only. "
            "Do not reuse the old invite link."
        )
    else:
        print(
            "TESTFLIGHT_NEXT_STEP=Open the TestFlight app directly while signed in as "
            f"{TESTER_EMAIL}. Email invite links stay invalid until external beta review "
            "is approved by Apple."
        )


if __name__ == "__main__":
    main()
