#!/usr/bin/env python3
"""Remove older TestFlight builds from active beta groups; keep the latest build."""

from __future__ import annotations

import importlib.util
import os
import sys
from pathlib import Path

KEEP_BUILD = int(os.environ.get("TESTFLIGHT_KEEP_BUILD", "152"))

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
list_groups = _SETUP.list_groups
make_client = _SETUP.make_client
load_config = _SETUP.load_config


def list_app_builds(client: ASCClient, app_id: str) -> list[dict]:
    payload = client.get(f"/apps/{app_id}/builds", **{"limit": "200", "sort": "-uploadedDate"})
    return payload.get("data") or []


def list_group_build_ids(client: ASCClient, group_id: str) -> list[str]:
    payload = client.get(f"/betaGroups/{group_id}/builds", **{"limit": "200"})
    return [item["id"] for item in payload.get("data") or []]


def remove_build_from_group(client: ASCClient, group_id: str, build_id: str) -> None:
    status, response = client.request(
        "DELETE",
        f"/betaGroups/{group_id}/relationships/builds",
        body={"data": [{"type": "builds", "id": build_id}]},
        allow_error=True,
    )
    if status in {200, 204}:
        print(f"Removed build {build_id} from group {group_id}")
        return
    warn(f"Could not remove build {build_id} from group {group_id} ({status}): {response}")


def expire_build(client: ASCClient, build_id: str) -> None:
    status, response = client.request(
        "PATCH",
        f"/builds/{build_id}",
        body={
            "data": {
                "type": "builds",
                "id": build_id,
                "attributes": {"expired": True},
            }
        },
        allow_error=True,
    )
    if status in {200, 201}:
        print(f"Expired build {build_id}")
        return
    warn(f"Could not expire build {build_id} ({status}): {response}")


def main() -> None:
    issuer_id, key_id, private_key = load_config()
    client = make_client(issuer_id, key_id, private_key)
    app = find_app(client)
    app_id = app["id"]
    groups = list_groups(client, app_id)

    builds = list_app_builds(client, app_id)
    if not builds:
        print("No builds found in App Store Connect.")
        return

    keep_ids: set[str] = set()
    for build in builds:
        version = int((build.get("attributes") or {}).get("version") or "0")
        if version >= KEEP_BUILD:
            keep_ids.add(build["id"])

    if not keep_ids:
        warn(f"No builds found at or above keep build {KEEP_BUILD}; skipping cleanup.")

    removed = 0
    expired = 0
    for build in builds:
        build_id = build["id"]
        version = int((build.get("attributes") or {}).get("version") or "0")
        if version >= KEEP_BUILD:
            print(f"Keeping build {version} ({build_id})")
            continue

        for group in groups:
            group_id = group["id"]
            group_name = (group.get("attributes") or {}).get("name", group_id)
            if build_id in list_group_build_ids(client, group_id):
                remove_build_from_group(client, group_id, build_id)
                removed += 1
                print(f"Removed build {version} from group '{group_name}'")

        expire_build(client, build_id)
        expired += 1

    print(f"CLEANUP_REMOVED_FROM_GROUPS={removed}")
    print(f"CLEANUP_EXPIRE_ATTEMPTS={expired}")
    print(f"CLEANUP_KEEP_BUILD={KEEP_BUILD}")


if __name__ == "__main__":
    main()
