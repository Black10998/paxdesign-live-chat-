#!/usr/bin/env python3
"""Verify and fix internal TestFlight access only (no external review)."""

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
TARGET_BUILD = _SETUP.TARGET_BUILD
TESTER_EMAIL = _SETUP.TESTER_EMAIL
fail = _SETUP.fail
warn = _SETUP.warn
find_app = _SETUP.find_app
find_build = _SETUP.find_build
list_groups = _SETUP.list_groups
group_build_versions = _SETUP.group_build_versions
group_tester_emails = _SETUP.group_tester_emails
find_beta_tester = _SETUP.find_beta_tester
add_build_to_group = _SETUP.add_build_to_group
get_build_beta_detail = _SETUP.get_build_beta_detail
find_asc_user = _SETUP.find_asc_user
make_token = _SETUP.make_token
load_config = _SETUP.load_config


def check(label: str, ok: bool, detail: str) -> bool:
    status = "PASS" if ok else "FAIL"
    print(f"[{status}] {label}: {detail}")
    return ok


def remove_tester_from_group(client: ASCClient, group_id: str, tester_id: str) -> bool:
    status, payload = client.request(
        "DELETE",
        f"/betaGroups/{group_id}/relationships/betaTesters",
        body={"data": [{"type": "betaTesters", "id": tester_id}]},
        allow_error=True,
    )
    if status in {200, 204, 404}:
        return True
    warn(f"Could not remove tester from group {group_id}: {json.dumps(payload)}")
    return False


def delete_beta_tester(client: ASCClient, tester_id: str) -> bool:
    status, payload = client.request(
        "DELETE",
        f"/betaTesters/{tester_id}",
        allow_error=True,
    )
    if status in {200, 204}:
        return True
    warn(f"Could not delete beta tester {tester_id}: {json.dumps(payload)}")
    return False


def remove_build_from_group(client: ASCClient, group_id: str, build_id: str) -> bool:
    status, payload = client.request(
        "DELETE",
        f"/betaGroups/{group_id}/relationships/builds",
        body={"data": [{"type": "builds", "id": build_id}]},
        allow_error=True,
    )
    if status in {200, 204, 404}:
        return True
    warn(f"Could not remove build from group {group_id}: {json.dumps(payload)}")
    return False


def print_group_report(
    client: ASCClient,
    group: dict[str, Any],
    build_version: str,
    email: str,
) -> None:
    attrs = group.get("attributes") or {}
    gid = group["id"]
    name = attrs.get("name", gid)
    internal = attrs.get("isInternalGroup")
    access_all = attrs.get("hasAccessToAllBuilds")
    builds = group_build_versions(client, gid)
    testers = group_tester_emails(client, gid)
    print(
        f"  group='{name}' id={gid} internal={internal} "
        f"hasAccessToAllBuilds={access_all}"
    )
    print(f"    builds: {builds or '(none)'}")
    print(f"    betaTesters: {testers or '(none)'}")
    print(f"    build_{build_version}_present={build_version in builds}")
    print(f"    email_in_betaTesters={email in testers}")


def verify_asc_user(client: ASCClient, email: str) -> dict[str, Any] | None:
    user = find_asc_user(client, email)
    if user is None:
        return None
    attrs = user.get("attributes") or {}
    print(f"  user_id={user['id']}")
    print(f"  username={attrs.get('username')}")
    print(f"  roles={attrs.get('roles', [])}")
    print(f"  allAppsVisible={attrs.get('allAppsVisible')}")
    print(f"  provisioningAllowed={attrs.get('provisioningAllowed')}")
    return user


def verify_beta_tester_record(client: ASCClient, app_id: str, email: str) -> None:
    tester = find_beta_tester(client, email)
    if tester is None:
        print("  no betaTester record (expected for pure internal ASC access)")
        return
    attrs = tester.get("attributes") or {}
    print(f"  betaTester_id={tester['id']}")
    print(f"  state={attrs.get('state', 'UNKNOWN')}")
    print(f"  inviteType={attrs.get('inviteType', 'UNKNOWN')}")
    payload = client.get(
        "/betaTesters",
        **{"filter[email]": email, "filter[apps]": app_id, "limit": "1"},
    )
    apps_linked = payload.get("data") or []
    print(f"  linked_to_app={bool(apps_linked)}")
    print(
        "  note=betaTester records are for EXTERNAL invites; "
        "internal ASC users should not rely on them"
    )


def main() -> None:
    issuer_id, key_id, private_key = load_config()
    client = ASCClient(make_token(issuer_id, key_id, private_key))

    app = find_app(client)
    app_id = app["id"]
    app_name = (app.get("attributes") or {}).get("name", _SETUP.BUNDLE_ID)

    build = find_build(client, app_id, TARGET_BUILD)
    if build is None:
        fail(f"Build {TARGET_BUILD} not found")
    build_id = build["id"]
    build_version = str((build.get("attributes") or {}).get("version", TARGET_BUILD))
    build_attrs = build.get("attributes") or {}

    detail = get_build_beta_detail(client, build_id)
    dattrs = (detail or {}).get("attributes") or {}
    internal_state = dattrs.get("internalBuildState", "UNKNOWN")
    external_state = dattrs.get("externalBuildState", "UNKNOWN")

    print("=== Internal TestFlight API verification ===")
    print(f"app={app_name} ({app_id})")
    print(f"build={build_version} ({build_id})")
    print(f"processingState={build_attrs.get('processingState')}")
    print(f"usesNonExemptEncryption={build_attrs.get('usesNonExemptEncryption')}")
    print(f"internalBuildState={internal_state}")
    print(f"externalBuildState={external_state} (informational only; no changes)")

    groups = list_groups(client, app_id)
    internal_groups = [g for g in groups if (g.get("attributes") or {}).get("isInternalGroup")]
    external_groups = [
        g for g in groups if (g.get("attributes") or {}).get("isInternalGroup") is False
    ]

    print("\n--- 1) App Store Connect user (internal tester eligibility) ---")
    user = verify_asc_user(client, TESTER_EMAIL)
    asc_ok = check(
        "ASC team user",
        user is not None,
        f"{TESTER_EMAIL} {'is' if user else 'is NOT'} an App Store Connect user",
    )
    if user:
        roles = (user.get("attributes") or {}).get("roles", [])
        role_ok = any(
            role in roles
            for role in (
                "ACCOUNT_HOLDER",
                "ADMIN",
                "APP_MANAGER",
                "DEVELOPER",
                "MARKETING",
            )
        )
        check(
            "ASC role allows internal TestFlight",
            role_ok,
            f"roles={roles}",
        )

    print("\n--- 2) betaTester record (external vs internal) ---")
    verify_beta_tester_record(client, app_id, TESTER_EMAIL)
    tester = find_beta_tester(client, TESTER_EMAIL)

    print("\n--- 3) Beta groups before fix ---")
    for group in groups:
        print_group_report(client, group, build_version, TESTER_EMAIL)

    print("\n--- 4) Internal-only fix (no external review actions) ---")
    if tester is not None:
        tester_id = tester["id"]
        for group in groups:
            gid = group["id"]
            name = (group.get("attributes") or {}).get("name", gid)
            if TESTER_EMAIL in group_tester_emails(client, gid):
                remove_tester_from_group(client, gid, tester_id)
                print(f"  removed {TESTER_EMAIL} from group '{name}'")
        delete_beta_tester(client, tester_id)
        if find_beta_tester(client, TESTER_EMAIL) is None:
            print("  deleted stale betaTester record")
        else:
            warn("betaTester record still exists after delete attempt")

    for group in external_groups:
        gid = group["id"]
        name = (group.get("attributes") or {}).get("name", gid)
        if build_version in group_build_versions(client, gid):
            remove_build_from_group(client, gid, build_id)
            print(f"  removed build {build_version} from external group '{name}'")
        if TESTER_EMAIL in group_tester_emails(client, gid):
            warn(f"{TESTER_EMAIL} still listed in external group '{name}'")

    if not internal_groups:
        fail("No internal TestFlight group exists for this app")

    for group in internal_groups:
        gid = group["id"]
        name = (group.get("attributes") or {}).get("name", gid)
        add_build_to_group(client, gid, build_id)
        print(f"  ensured build {build_version} linked to internal group '{name}'")

    print("\n--- 5) Beta groups after fix ---")
    groups = list_groups(client, app_id)
    for group in groups:
        print_group_report(client, group, build_version, TESTER_EMAIL)

    print("\n--- 6) Final checks ---")
    internal_build_linked = any(
        build_version in group_build_versions(client, g["id"]) for g in internal_groups
    )
    check(
        f"Build {build_version} linked to internal group",
        internal_build_linked,
        "see group reports above",
    )

    tester_after = find_beta_tester(client, TESTER_EMAIL)
    check(
        "No stale external betaTester record",
        tester_after is None,
        "betaTester should be absent for ASC internal access",
    )

    in_external_group = any(
        TESTER_EMAIL in group_tester_emails(client, g["id"]) for g in external_groups
    )
    check(
        "Not assigned as external betaTester",
        not in_external_group,
        f"{TESTER_EMAIL} must not be in external groups",
    )

    detail = get_build_beta_detail(client, build_id)
    dattrs = (detail or {}).get("attributes") or {}
    internal_state = dattrs.get("internalBuildState", "UNKNOWN")
    internal_ready = internal_state in {"READY_FOR_BETA_TESTING", "IN_BETA_TESTING"}
    check(
        "Internal build state allows TestFlight install",
        internal_ready,
        f"internalBuildState={internal_state}",
    )

    print("\n=== Summary ===")
    print("INTERNAL_API_VERIFIED=true")
    print(f"TESTER={TESTER_EMAIL}")
    print(f"BUILD={build_version}")
    print(f"INTERNAL_BUILD_STATE={internal_state}")
    print(
        "INTERNAL_ACCESS_MODEL=App Store Connect team member; "
        "open TestFlight app directly (no email invite link)."
    )
    print(
        "DEVICE_STEP=Sign in to TestFlight with the same Apple ID as ASC username "
        f"({TESTER_EMAIL}), then pull to refresh."
    )

    if not asc_ok or not internal_ready or not internal_build_linked:
        fail("One or more internal TestFlight checks failed; see report above")


if __name__ == "__main__":
    main()
