"""Shared App Store version helpers for App Store Connect API."""

from __future__ import annotations

import json
from typing import Any

EDITABLE_STATES = {
    "PREPARE_FOR_SUBMISSION",
    "DEVELOPER_REJECTED",
    "REJECTED",
    "WAITING_FOR_REVIEW",
    "READY_FOR_REVIEW",
    "PENDING_DEVELOPER_RELEASE",
}

IN_REVIEW_STATES = {
    "IN_REVIEW",
    "PENDING_APPLE_RELEASE",
    "READY_FOR_SALE",
    "WAITING_FOR_REVIEW",
}


def list_ios_versions(client: Any, app_id: str) -> list[dict[str, Any]]:
    payload = client.get(
        f"/apps/{app_id}/appStoreVersions",
        **{"filter[platform]": "IOS", "limit": "30"},
    )
    return payload.get("data") or []


def find_open_editable_version(client: Any, app_id: str) -> dict[str, Any] | None:
    for item in list_ios_versions(client, app_id):
        attrs = item.get("attributes") or {}
        if attrs.get("appStoreState") in EDITABLE_STATES:
            return item
    return None


def find_or_create_version(
    client: Any,
    app_id: str,
    version_string: str,
    *,
    fail: Any,
    warn: Any,
) -> dict[str, Any]:
    versions = list_ios_versions(client, app_id)
    for item in versions:
        attrs = item.get("attributes") or {}
        if attrs.get("versionString") == version_string:
            state = attrs.get("appStoreState", "")
            if state in EDITABLE_STATES or state in IN_REVIEW_STATES:
                return item

    status, response = client.request(
        "POST",
        "/appStoreVersions",
        body={
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
        allow_error=True,
    )
    if status in {200, 201}:
        print(f"Created App Store version {version_string}")
        return response["data"]

    if status == 409:
        open_version = find_open_editable_version(client, app_id)
        if open_version:
            attrs = open_version.get("attributes") or {}
            current = attrs.get("versionString", "?")
            if current != version_string:
                patch_status, patch_response = client.request(
                    "PATCH",
                    f"/appStoreVersions/{open_version['id']}",
                    body={
                        "data": {
                            "type": "appStoreVersions",
                            "id": open_version["id"],
                            "attributes": {"versionString": version_string},
                        }
                    },
                    allow_error=True,
                )
                if patch_status in {200, 201}:
                    print(f"Updated open App Store version {current} -> {version_string}")
                    payload = client.get(f"/appStoreVersions/{open_version['id']}")
                    return payload.get("data") or open_version
                warn(
                    f"Using open App Store version {current} (could not rename to {version_string}): "
                    f"{json.dumps(patch_response)}"
                )
            else:
                print(f"Using existing open App Store version {version_string}")
            return open_version

    fail(f"Could not find or create App Store version {version_string} ({status}): {json.dumps(response)}")
