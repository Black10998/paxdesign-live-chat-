#!/usr/bin/env python3
"""Verify App Store screenshot upload state in App Store Connect."""

from __future__ import annotations

import importlib.util
import json
import os
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
METADATA_PATH = ROOT / "docs" / "app-store" / "metadata.json"

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
make_client = _SETUP.make_client
load_config = _SETUP.load_config


def load_metadata() -> dict:
    return json.loads(METADATA_PATH.read_text(encoding="utf-8"))


def png_dimensions(path: Path) -> tuple[int, int] | None:
    try:
        data = path.read_bytes()
        if len(data) < 24 or data[:8] != b"\x89PNG\r\n\x1a\n":
            return None
        width = int.from_bytes(data[16:20], "big")
        height = int.from_bytes(data[20:24], "big")
        return width, height
    except OSError:
        return None


def main() -> None:
    metadata = load_metadata()
    issuer_id, key_id, private_key = load_config()
    client = make_client(issuer_id, key_id, private_key)

    app = find_app(client)
    app_id = app["id"]
    version_string = metadata.get("versionString", "2.0.5")
    locale = metadata.get("primaryLocale", "de-DE")
    display_type = metadata.get("screenshotDisplayType", "APP_IPHONE_67")
    screenshot_dir = ROOT / metadata.get("screenshotDir", "docs/app-store/screenshots/6.7-inch")

    versions_payload = client.get(
        f"/apps/{app_id}/appStoreVersions",
        **{"filter[platform]": "IOS", "limit": "30"},
    )
    versions = versions_payload.get("data") or []
    print(f"=== App Store versions ({len(versions)}) ===")
    target_version = None
    for item in versions:
        attrs = item.get("attributes") or {}
        vs = attrs.get("versionString", "?")
        state = attrs.get("appStoreState", "?")
        marker = ""
        if vs == version_string:
            target_version = item
            marker = "  <-- target"
        print(f"  {vs}  state={state}  id={item['id']}{marker}")

    if not target_version:
        fail(f"Version {version_string} not found in App Store Connect")

    version_id = target_version["id"]
    loc_payload = client.get(
        f"/appStoreVersions/{version_id}/appStoreVersionLocalizations",
        **{"limit": "20"},
    )
    localization = None
    for item in loc_payload.get("data") or []:
        if (item.get("attributes") or {}).get("locale") == locale:
            localization = item
            break
    if not localization:
        fail(f"Localization {locale} not found for version {version_string}")

    loc_id = localization["id"]
    sets_payload = client.get(
        f"/appStoreVersionLocalizations/{loc_id}/appScreenshotSets",
        **{"limit": "20"},
    )
    shot_set = None
    for item in sets_payload.get("data") or []:
        if (item.get("attributes") or {}).get("screenshotDisplayType") == display_type:
            shot_set = item
            break

    print(f"\n=== Target ===")
    print(f"version={version_string} ({version_id})")
    print(f"locale={locale} ({loc_id})")
    print(f"displayType={display_type}")

    if not shot_set:
        print("\nNo screenshot set found for this display type.")
        sys.exit(1)

    set_id = shot_set["id"]
    shots_payload = client.get(f"/appScreenshotSets/{set_id}/appScreenshots", **{"limit": "20"})
    screenshots = shots_payload.get("data") or []
    print(f"\n=== Screenshots in App Store Connect ({len(screenshots)}) ===")
    if not screenshots:
        print("  (none)")
    complete = 0
    for item in screenshots:
        attrs = item.get("attributes") or {}
        name = attrs.get("fileName", "?")
        delivery = attrs.get("assetDeliveryState") or {}
        state = delivery.get("state", "?")
        errors = delivery.get("errors") or []
        if state == "COMPLETE":
            complete += 1
        print(f"  {name}")
        print(f"    state={state}")
        if errors:
            print(f"    errors={json.dumps(errors)}")
        source_file = attrs.get("sourceFileChecksum")
        if source_file:
            print(f"    checksum={source_file}")

    print(f"\n=== Local files ({screenshot_dir}) ===")
    required = (1290, 2796)
    for path in sorted(screenshot_dir.glob("*.png")):
        dims = png_dimensions(path)
        size_kb = path.stat().st_size // 1024
        dim_text = f"{dims[0]}x{dims[1]}" if dims else "unknown"
        ok = dims == required
        flag = "OK" if ok else "WRONG SIZE"
        print(f"  {path.name}: {dim_text} ({size_kb} KB) [{flag}]")
    print(f"  required for {display_type}: {required[0]}x{required[1]}")

    print(f"\n=== Summary ===")
    print(f"remote_screenshots={len(screenshots)}")
    print(f"remote_complete={complete}")
    if complete < len(list(screenshot_dir.glob('*.png'))):
        print("RESULT=INCOMPLETE")
        sys.exit(1)
    print("RESULT=OK")


if __name__ == "__main__":
    main()
