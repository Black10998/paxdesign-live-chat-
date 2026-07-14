#!/usr/bin/env python3
"""Upload App Store screenshots to App Store Connect."""

from __future__ import annotations

import importlib.util
import json
import mimetypes
import os
import sys
import time
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


def load_metadata() -> dict[str, Any]:
    if not METADATA_PATH.exists():
        fail(f"Missing metadata file: {METADATA_PATH}")
    return json.loads(METADATA_PATH.read_text(encoding="utf-8"))


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
    return payload["data"]


def get_screenshot_set(client: ASCClient, localization_id: str, display_type: str) -> dict[str, Any] | None:
    payload = client.get(
        f"/appStoreVersionLocalizations/{localization_id}/appScreenshotSets",
        **{"limit": "20"},
    )
    for item in payload.get("data") or []:
        attrs = item.get("attributes") or {}
        if attrs.get("screenshotDisplayType") == display_type:
            return item
    return None


def ensure_screenshot_set(client: ASCClient, localization_id: str, display_type: str) -> dict[str, Any]:
    existing = get_screenshot_set(client, localization_id, display_type)
    if existing:
        return existing
    payload = client.post(
        "/appScreenshotSets",
        {
            "data": {
                "type": "appScreenshotSets",
                "attributes": {"screenshotDisplayType": display_type},
                "relationships": {
                    "appStoreVersionLocalization": {
                        "data": {"type": "appStoreVersionLocalizations", "id": localization_id},
                    },
                },
            }
        },
    )
    return payload["data"]


def list_screenshots(client: ASCClient, set_id: str) -> list[dict[str, Any]]:
    payload = client.get(f"/appScreenshotSets/{set_id}/appScreenshots", **{"limit": "20"})
    return payload.get("data") or []


def reserve_screenshot(client: ASCClient, set_id: str, filename: str, file_size: int) -> dict[str, Any]:
    payload = client.post(
        "/appScreenshots",
        {
            "data": {
                "type": "appScreenshots",
                "attributes": {
                    "fileName": filename,
                    "fileSize": file_size,
                },
                "relationships": {
                    "appScreenshotSet": {"data": {"type": "appScreenshotSets", "id": set_id}},
                },
            }
        },
    )
    return payload["data"]


def upload_binary(client: ASCClient, screenshot_id: str, path: Path) -> None:
    import urllib.error
    import urllib.request

    payload = client.get(f"/appScreenshots/{screenshot_id}")
    attrs = (payload.get("data") or {}).get("attributes") or {}
    upload_ops = attrs.get("uploadOperations") or []
    if not upload_ops:
        state = attrs.get("assetDeliveryState", {}).get("state")
        if state == "COMPLETE":
            print(f"Screenshot already uploaded: {path.name}")
            return
        fail(f"No upload operations for {path.name} (state={state})")

    data = path.read_bytes()
    for op in upload_ops:
        method = op.get("method", "PUT")
        url = op["url"]
        headers = {h["name"]: h["value"] for h in op.get("requestHeaders") or []}
        req = urllib.request.Request(url, data=data, headers=headers, method=method)
        try:
            with urllib.request.urlopen(req, timeout=300) as resp:
                resp.read()
        except urllib.error.HTTPError as exc:
            fail(f"Screenshot upload failed for {path.name} ({exc.code}): {exc.read().decode()}")

    for attempt in range(1, 13):
        payload = client.get(f"/appScreenshots/{screenshot_id}")
        state = ((payload.get("data") or {}).get("attributes") or {}).get("assetDeliveryState", {}).get("state")
        if state == "COMPLETE":
            print(f"Uploaded {path.name}")
            return
        if state in {"FAILED", "REJECTED"}:
            fail(f"Screenshot processing failed for {path.name}: {state}")
        time.sleep(5)
    warn(f"Screenshot {path.name} still processing after upload")


def delete_screenshot(client: ASCClient, screenshot_id: str) -> None:
    status, payload = client.request(
        "DELETE",
        f"/appScreenshots/{screenshot_id}",
        allow_error=True,
    )
    if status not in {200, 204, 404}:
        warn(f"Could not delete screenshot {screenshot_id}: {json.dumps(payload)}")


def upload_locale_screenshots(
    client: ASCClient,
    version_id: str,
    locale: str,
    display_type: str,
    screenshot_dir: Path,
    *,
    replace_existing: bool,
) -> None:
    loc = ensure_localization(client, version_id, locale)
    loc_id = loc["id"]
    shot_set = ensure_screenshot_set(client, loc_id, display_type)
    set_id = shot_set["id"]

    existing = list_screenshots(client, set_id)
    if existing:
        if replace_existing:
            print(f"Removing {len(existing)} existing screenshot(s) for {locale}")
            for item in existing:
                delete_screenshot(client, item["id"])
        else:
            print(f"Screenshots already present for {locale} ({len(existing)}); skipping upload")
            return

    files = sorted(p for p in screenshot_dir.glob("*.png") if p.is_file())
    if not files:
        fail(f"No PNG screenshots in {screenshot_dir}")

    for index, path in enumerate(files):
        reserved = reserve_screenshot(client, set_id, path.name, path.stat().st_size)
        upload_binary(client, reserved["id"], path)
        print(f"{locale}: {index + 1}/{len(files)} {path.name}")


def main() -> None:
    metadata = load_metadata()
    issuer_id, key_id, private_key = load_config()
    client = make_client(issuer_id, key_id, private_key)

    app = find_app(client)
    app_id = app["id"]
    version_string = metadata.get("versionString", os.environ.get("APP_STORE_VERSION", "2.0.0"))
    version = find_or_create_version(client, app_id, version_string, fail=fail, warn=warn)
    version_id = version["id"]

    display_type = metadata.get("screenshotDisplayType", "APP_IPHONE_67")
    screenshot_dir = ROOT / metadata.get("screenshotDir", "docs/app-store/screenshots/6.7-inch")
    primary_locale = metadata.get("primaryLocale", "de-DE")
    replace_existing = os.environ.get("REPLACE_SCREENSHOTS", "1").strip().lower() in {"1", "true", "yes"}

    upload_locale_screenshots(
        client,
        version_id,
        primary_locale,
        display_type,
        screenshot_dir,
        replace_existing=replace_existing,
    )
    print("Screenshot upload complete")


if __name__ == "__main__":
    main()
