#!/usr/bin/env python3
"""Resolve an App Store Connect app by bundle ID and expose its numeric Apple ID."""

from __future__ import annotations

import importlib.util
import json
import os
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
make_client = _SETUP.make_client
load_config = _SETUP.load_config

BUNDLE_ID = os.environ.get("APP_BUNDLE_ID", "at.paxdesign.customerportal").strip()
GITHUB_OUTPUT = os.environ.get("GITHUB_OUTPUT", "")


def app_bundle_identifier(client: ASCClient, app_id: str) -> str:
    payload = client.get(f"/apps/{app_id}/bundleId")
    data = payload.get("data") or {}
    return (data.get("attributes") or {}).get("identifier", "?")


def list_apps_with_bundle_ids(client: ASCClient) -> list[tuple[str, str, str]]:
    """Return (apple_id, app_name, bundle_identifier) for every visible app."""
    rows: list[tuple[str, str, str]] = []
    cursor = ""
    while True:
        params: dict[str, str] = {"limit": "200"}
        if cursor:
            params["cursor"] = cursor
        payload = client.get("/apps", **params)
        for app in payload.get("data") or []:
            app_id = app.get("id", "")
            name = (app.get("attributes") or {}).get("name", app_id)
            identifier = app_bundle_identifier(client, app_id)
            rows.append((app_id, name, identifier))
        cursor = (payload.get("meta") or {}).get("paging", {}).get("nextCursor") or ""
        if not cursor:
            break
    return rows


def find_app_by_bundle(client: ASCClient, bundle_id: str) -> dict[str, Any] | None:
    payload = client.get("/apps", **{"filter[bundleId]": bundle_id, "limit": "1"})
    data = payload.get("data") or []
    if data:
        return data[0]

    for apple_id, _name, identifier in list_apps_with_bundle_ids(client):
        if identifier == bundle_id:
            return {"id": apple_id, "attributes": {"name": _name}}
    return None


def export_github_output(app_id: str, app_name: str, bundle_id: str) -> None:
    if not GITHUB_OUTPUT:
        return
    with open(GITHUB_OUTPUT, "a", encoding="utf-8") as handle:
        handle.write(f"asc_app_apple_id={app_id}\n")
        handle.write(f"asc_app_name={app_name}\n")
        handle.write(f"asc_bundle_id={bundle_id}\n")


def main() -> None:
    issuer_id, key_id, private_key = load_config()
    client = make_client(issuer_id, key_id, private_key)

    print(f"Resolving App Store Connect app for bundle id {BUNDLE_ID!r} …")
    app = find_app_by_bundle(client, BUNDLE_ID)
    if not app:
        print("ERROR: No App Store Connect app visible to this API key for that bundle id.", file=sys.stderr)
        print("Apps visible to APP_STORE_CONNECT_API_KEY_ID / ISSUER_ID:", file=sys.stderr)
        for apple_id, name, identifier in list_apps_with_bundle_ids(client):
            print(f"  - apple_id={apple_id} bundleId={identifier!r} name={name!r}", file=sys.stderr)
        fail(
            f"App Store Connect API key cannot see an app for bundle id {BUNDLE_ID}. "
            "If the app exists in the ASC web UI, confirm the API key belongs to the same provider team "
            "and that the bundle identifier matches exactly."
        )

    app_id = app["id"]
    app_name = (app.get("attributes") or {}).get("name", BUNDLE_ID)
    print(f"Resolved ASC app: apple_id={app_id} name={app_name!r} bundleId={BUNDLE_ID}")
    export_github_output(app_id, app_name, BUNDLE_ID)
    print(json.dumps({"apple_id": app_id, "name": app_name, "bundleId": BUNDLE_ID}))


if __name__ == "__main__":
    main()
