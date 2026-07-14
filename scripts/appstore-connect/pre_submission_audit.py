#!/usr/bin/env python3
"""Full pre-submission audit for App Store Connect — read-only, no submit."""

from __future__ import annotations

import importlib.util
import json
import os
import sys
import urllib.error
import urllib.request
from pathlib import Path
from typing import Any

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
warn = _SETUP.warn
find_app = _SETUP.find_app
find_build = _SETUP.find_build
make_client = _SETUP.make_client
load_config = _SETUP.load_config


EXPECTED_SCREENSHOTS = [
    "01-ihre-kommandozentrale.png",
    "02-live-anfragen-sofort-beantworten.png",
    "03-ki-gestuetzte-kundenantworten.png",
    "04-integrierter-team-chat.png",
    "05-eine-plattform-fuer-alles.png",
]


def load_metadata() -> dict[str, Any]:
    return json.loads(METADATA_PATH.read_text(encoding="utf-8"))


def check(label: str, ok: bool, detail: str) -> bool:
    status = "PASS" if ok else "FAIL"
    print(f"[{status}] {label}: {detail}")
    return ok


def warn_check(label: str, ok: bool, detail: str) -> bool:
    status = "PASS" if ok else "WARN"
    print(f"[{status}] {label}: {detail}")
    return ok


def url_ok(url: str) -> tuple[bool, str]:
    try:
        req = urllib.request.Request(
            url,
            method="GET",
            headers={"User-Agent": "Mozilla/5.0 (compatible; PAXDesignASCVerify/1.0)"},
        )
        with urllib.request.urlopen(req, timeout=20) as resp:
            code = resp.status
            return 200 <= code < 400, f"HTTP {code}"
    except urllib.error.HTTPError as exc:
        return 200 <= exc.code < 400, f"HTTP {exc.code}"
    except Exception as exc:  # noqa: BLE001
        return False, str(exc)


def find_version(client: ASCClient, app_id: str, version_string: str) -> dict[str, Any] | None:
    payload = client.get(
        f"/apps/{app_id}/appStoreVersions",
        **{"filter[platform]": "IOS", "limit": "30"},
    )
    for item in payload.get("data") or []:
        attrs = item.get("attributes") or {}
        if attrs.get("versionString") == version_string:
            return item
    return None


def get_version_localization(client: ASCClient, version_id: str, locale: str) -> dict[str, Any] | None:
    payload = client.get(
        f"/appStoreVersions/{version_id}/appStoreVersionLocalizations",
        **{"limit": "20"},
    )
    for item in payload.get("data") or []:
        if (item.get("attributes") or {}).get("locale") == locale:
            return item
    return None


def get_app_info_localization(client: ASCClient, app_id: str, locale: str) -> dict[str, Any] | None:
    info_payload = client.get(f"/apps/{app_id}/appInfos", **{"limit": "5"})
    infos = info_payload.get("data") or []
    if not infos:
        return None
    info_id = infos[0]["id"]
    payload = client.get(f"/appInfos/{info_id}/appInfoLocalizations", **{"limit": "20"})
    for item in payload.get("data") or []:
        if (item.get("attributes") or {}).get("locale") == locale:
            return item
    return None


def get_screenshots(client: ASCClient, loc_id: str, display_type: str) -> list[dict[str, Any]]:
    sets_payload = client.get(
        f"/appStoreVersionLocalizations/{loc_id}/appScreenshotSets",
        **{"limit": "20"},
    )
    shot_set = None
    for item in sets_payload.get("data") or []:
        if (item.get("attributes") or {}).get("screenshotDisplayType") == display_type:
            shot_set = item
            break
    if not shot_set:
        return []
    payload = client.get(
        f"/appScreenshotSets/{shot_set['id']}/appScreenshots",
        **{"limit": "20"},
    )
    return payload.get("data") or []


def get_review_detail(client: ASCClient, version_id: str) -> dict[str, Any] | None:
    status, payload = client.request(
        "GET",
        f"/appStoreVersions/{version_id}/appStoreReviewDetail",
        allow_error=True,
    )
    if status != 200:
        return None
    data = payload.get("data")
    return data if isinstance(data, dict) else None


def get_app_categories(client: ASCClient, app_id: str) -> dict[str, Any]:
    info_payload = client.get(f"/apps/{app_id}/appInfos", **{"limit": "5"})
    infos = info_payload.get("data") or []
    if not infos:
        return {}
    info_id = infos[0]["id"]
    payload = client.get(f"/appInfos/{info_id}")
    return (payload.get("data") or {}).get("attributes") or {}


def get_app_price(client: ASCClient, app_id: str) -> dict[str, Any]:
    status, payload = client.request(
        "GET",
        f"/apps/{app_id}/appPriceSchedule",
        allow_error=True,
    )
    if status != 200:
        return {}
    data = payload.get("data")
    return data if isinstance(data, dict) else {}


def get_app_availability(client: ASCClient, app_id: str) -> dict[str, Any]:
    status, payload = client.request(
        "GET",
        f"/apps/{app_id}/appAvailability",
        allow_error=True,
    )
    if status != 200:
        return {}
    data = payload.get("data")
    return data if isinstance(data, dict) else {}


def get_privacy_policy(client: ASCClient, app_id: str) -> dict[str, Any] | None:
    status, payload = client.request(
        "GET",
        f"/apps/{app_id}/appPrivacyPolicy",
        allow_error=True,
    )
    if status != 200:
        return None
    data = payload.get("data")
    return data if isinstance(data, dict) else None


def main() -> None:
    metadata = load_metadata()
    issuer_id, key_id, private_key = load_config()
    client = make_client(issuer_id, key_id, private_key)

    app = find_app(client)
    app_id = app["id"]
    version_string = metadata.get("versionString", "2.0.5")
    build_number = str(metadata.get("buildNumber", "127"))
    locale = metadata.get("primaryLocale", "de-DE")
    display_type = metadata.get("screenshotDisplayType", "APP_IPHONE_67")
    app_info = metadata.get("appInfo", {})

    print("=== PAXDesign Live Chat — Pre-Submission Audit ===")
    print(f"Target: version {version_string}, build {build_number}, locale {locale}")
    print()

    results: list[bool] = []

    version = find_version(client, app_id, version_string)
    results.append(check("Version 2.0.5 exists", version is not None, version_string if version else "not found"))
    if not version:
        sys.exit(1)

    version_id = version["id"]
    vattrs = version.get("attributes") or {}

    # Build attachment — list responses may omit relationships; refetch with include=build
    version_detail = client.get(f"/appStoreVersions/{version_id}", **{"include": "build"})
    build_rel = (version_detail.get("data") or {}).get("relationships", {}).get("build", {}).get("data")
    included = version_detail.get("included") or []
    attached_build = None
    attached_number = None
    if build_rel:
        attached_build = next(
            (item for item in included if item.get("type") == "builds" and item.get("id") == build_rel.get("id")),
            None,
        )
        if attached_build is None:
            build_payload = client.get(f"/builds/{build_rel['id']}")
            attached_build = build_payload.get("data") or {}
        attached_number = str((attached_build.get("attributes") or {}).get("version", ""))

    results.append(
        check(
            "Build 127 attached to version 2.0.5",
            attached_number == build_number,
            f"attached={attached_number or 'none'} expected={build_number}",
        )
    )

    if attached_build:
        battrs = attached_build.get("attributes") or {}
        processing = battrs.get("processingState", "?")
        results.append(
            check(
                "Build processing state VALID",
                processing == "VALID",
                processing,
            )
        )
        encryption = battrs.get("usesNonExemptEncryption")
        results.append(
            check(
                "Export compliance (usesNonExemptEncryption=false)",
                encryption is False,
                f"usesNonExemptEncryption={encryption}",
            )
        )

    # Release type
    release_type = vattrs.get("releaseType", "?")
    results.append(
        check(
            "Manual release after approval",
            release_type == "MANUAL",
            f"releaseType={release_type}",
        )
    )

    state = vattrs.get("appStoreState", "?")
    results.append(
        check(
            "Version ready for submission (not already in review)",
            state in {"PREPARE_FOR_SUBMISSION", "DEVELOPER_REJECTED", "METADATA_REJECTED"},
            f"appStoreState={state}",
        )
    )

    # Screenshots
    loc = get_version_localization(client, version_id, locale)
    results.append(check(f"Localization {locale} exists", loc is not None, locale))
    screenshots: list[dict[str, Any]] = []
    if loc:
        screenshots = get_screenshots(client, loc["id"], display_type)
        names = [(s.get("attributes") or {}).get("fileName", "?") for s in screenshots]
        states = [
            ((s.get("attributes") or {}).get("assetDeliveryState") or {}).get("state", "?")
            for s in screenshots
        ]
        results.append(
            check(
                "Five German screenshots present",
                len(screenshots) == 5,
                f"count={len(screenshots)}",
            )
        )
        results.append(
            check(
                "All screenshots COMPLETE",
                len(screenshots) == 5 and all(s == "COMPLETE" for s in states),
                ", ".join(f"{n}:{st}" for n, st in zip(names, states)),
            )
        )
        results.append(
            check(
                "Screenshot order correct",
                names == EXPECTED_SCREENSHOTS,
                " → ".join(names),
            )
        )

    # Version localization metadata
    if loc:
        lattrs = loc.get("attributes") or {}
        expected_desc = (app_info.get("description") or {}).get(locale, "")
        expected_keywords = (app_info.get("keywords") or {}).get(locale, "")
        results.append(
            check(
                "Description present (de-DE)",
                bool(lattrs.get("description")),
                f"length={len(lattrs.get('description') or '')} expected~{len(expected_desc)}",
            )
        )
        results.append(
            check(
                "Keywords present (de-DE)",
                bool(lattrs.get("keywords")),
                lattrs.get("keywords") or "(empty)",
            )
        )
        if expected_keywords and lattrs.get("keywords"):
            results.append(
                warn_check(
                    "Keywords match metadata.json",
                    lattrs.get("keywords") == expected_keywords,
                    lattrs.get("keywords"),
                )
            )
        support_url = lattrs.get("supportUrl") or ""
        results.append(
            check(
                "Support URL set on version localization",
                support_url == app_info.get("supportUrl"),
                support_url or "(empty)",
            )
        )

    # App info localization
    info_loc = get_app_info_localization(client, app_id, locale)
    if info_loc:
        iattrs = info_loc.get("attributes") or {}
        results.append(
            check(
                "App name",
                iattrs.get("name") == app_info.get("name"),
                iattrs.get("name") or "(empty)",
            )
        )
        expected_sub = (app_info.get("subtitle") or {}).get(locale, "")
        results.append(
            check(
                "Subtitle (de-DE)",
                iattrs.get("subtitle") == expected_sub,
                iattrs.get("subtitle") or "(empty)",
            )
        )
        results.append(
            check(
                "Privacy Policy URL on app info",
                iattrs.get("privacyPolicyUrl") == app_info.get("privacyPolicyUrl"),
                iattrs.get("privacyPolicyUrl") or "(empty)",
            )
        )

    # Categories
    categories = get_app_categories(client, app_id)
    primary = categories.get("primaryCategory") or categories.get("primaryCategoryId")
    secondary = categories.get("secondaryCategory") or categories.get("secondaryCategoryId")
    results.append(
        warn_check(
            "Primary category Business",
            str(primary).upper() in {"BUSINESS", "MZGenre.Business", "6000"},
            str(primary),
        )
    )
    results.append(
        warn_check(
            "Secondary category Productivity",
            str(secondary).upper() in {"PRODUCTIVITY", "MZGenre.Productivity", "6007"},
            str(secondary),
        )
    )

    # Copyright on version
    copyright_text = vattrs.get("copyright", "")
    expected_copyright = os.environ.get("APP_STORE_COPYRIGHT", metadata.get("copyright", ""))
    results.append(
        check(
            "Copyright",
            copyright_text == expected_copyright,
            copyright_text or "(empty)",
        )
    )

    # Public URL checks
    for label, url in [
        ("Support URL reachable", app_info.get("supportUrl", "")),
        ("Privacy Policy URL reachable", app_info.get("privacyPolicyUrl", "")),
        ("Marketing URL reachable", app_info.get("marketingUrl", "")),
    ]:
        ok, detail = url_ok(url)
        results.append(check(label, ok, f"{url} ({detail})"))

    # App Privacy
    privacy = get_privacy_policy(client, app_id)
    results.append(
        warn_check(
            "App Privacy policy configured in ASC",
            privacy is not None,
            "present" if privacy else "not found via API — verify manually in App Privacy",
        )
    )

    # Pricing / availability
    price = get_app_price(client, app_id)
    availability = get_app_availability(client, app_id)
    results.append(
        warn_check(
            "Pricing schedule configured",
            bool(price),
            "present" if price else "verify manually — free apps usually OK",
        )
    )
    results.append(
        warn_check(
            "App availability configured",
            bool(availability),
            "present" if availability else "verify manually in Pricing and Availability",
        )
    )

    # App Review Information
    review = get_review_detail(client, version_id)
    review_attrs = (review or {}).get("attributes") or {}
    contact_email = review_attrs.get("contactEmail") or ""
    contact_first = review_attrs.get("contactFirstName") or ""
    contact_last = review_attrs.get("contactLastName") or ""
    contact_phone = review_attrs.get("contactPhone") or ""
    notes = review_attrs.get("notes") or ""
    demo_required = review_attrs.get("demoAccountRequired")

    results.append(
        check(
            "Review contact email",
            bool(contact_email),
            contact_email or "(MISSING — required)",
        )
    )
    results.append(
        warn_check(
            "Review contact name",
            bool(contact_first and contact_last),
            f"{contact_first} {contact_last}".strip() or "(missing)",
        )
    )
    results.append(
        warn_check(
            "Review contact phone",
            bool(contact_phone),
            contact_phone or "(missing — recommended)",
        )
    )
    results.append(
        check(
            "Review notes present",
            bool(notes.strip()),
            f"length={len(notes.strip())}" if notes else "(MISSING)",
        )
    )
    results.append(
        warn_check(
            "Demo account flag set",
            demo_required is not None,
            f"demoAccountRequired={demo_required}",
        )
    )

    # Demo credentials are usually in notes, not a separate API field for password
    has_login_hint = any(
        token in notes.lower()
        for token in (
            "password",
            "application password",
            "username",
            "test@apple.app.com",
            "sign in",
            "sign-in",
        )
    )
    results.append(
        check(
            "Review notes mention login/demo credentials",
            has_login_hint,
            "found login hints" if has_login_hint else "notes do not mention username/password",
        )
    )

    print()
    print("=== App Review Information — where to enter credentials ===")
    print("App Store Connect → Apps → PAXDesign Live Chat → App Store → iOS App → 2.0.5")
    print("→ left sidebar: App Review Information")
    print("Direct section fields:")
    print("  • Sign-in required: Yes")
    print("  • Contact: First name, Last name, Phone, Email")
    print("  • Notes: step-by-step login + feature access instructions")
    print("  • Demo account username + password go in the Notes field (ASC has no separate password field)")
    print()
    print("Required demo/review account info to provide:")
    print("  1. WordPress username or email for https://paxdesign.at")
    print("  2. Application Password (not the main WordPress password)")
    print("  3. Optional: note if 2FA is disabled for review account")
    print("  4. Step-by-step: open app → enter URL https://paxdesign.at → login → tabs to test")
    print()

    passed = sum(1 for r in results if r)
    failed = sum(1 for r in results if not r)
    print("=== Summary ===")
    print(f"checks_passed={passed}/{len(results)}")
    print(f"checks_failed_or_warn={failed}")
    print(f"asc_version_url=https://appstoreconnect.apple.com/apps/{app_id}/distribution/ios/version/inflight")
    print(f"asc_review_url=https://appstoreconnect.apple.com/apps/{app_id}/distribution/ios/version/inflight/reviewdetails")
    print(f"asc_media_url=https://appstoreconnect.apple.com/apps/{app_id}/distribution/ios/version/inflight/media-manager/iphone")
    print(f"asc_app_privacy_url=https://appstoreconnect.apple.com/apps/{app_id}/distribution/privacy")

    # Hard failures only — WARN items are manual UI confirmations
    hard_fail_labels = {
        "Build 127 attached to version 2.0.5",
        "Manual release after approval",
        "Copyright",
        "Review contact email",
        "Review notes present",
        "Review notes mention login/demo credentials",
        "Five German screenshots present",
        "All screenshots COMPLETE",
        "Screenshot order correct",
    }
    hard_results = [
        (label, ok)
        for label, ok in zip(
            [
                "Version 2.0.5 exists",
                "Build 127 attached to version 2.0.5",
                "Build processing state VALID",
                "Export compliance (usesNonExemptEncryption=false)",
                "Manual release after approval",
                "Version ready for submission (not already in review)",
                "Localization de-DE exists",
                "Five German screenshots present",
                "All screenshots COMPLETE",
                "Screenshot order correct",
                "Description present (de-DE)",
                "Keywords present (de-DE)",
                "Keywords match metadata.json",
                "Support URL set on version localization",
                "App name",
                "Subtitle (de-DE)",
                "Privacy Policy URL on app info",
                "Primary category Business",
                "Secondary category Productivity",
                "Copyright",
                "Support URL reachable",
                "Privacy Policy URL reachable",
                "Marketing URL reachable",
                "App Privacy policy configured in ASC",
                "Pricing schedule configured",
                "App availability configured",
                "Review contact email",
                "Review contact name",
                "Review contact phone",
                "Review notes present",
                "Demo account flag set",
                "Review notes mention login/demo credentials",
            ][: len(results)],
            results,
        )
        if label in hard_fail_labels
    ]
    blocking = [label for label, ok in hard_results if not ok]

    if blocking:
        print(f"blocking_issues={blocking}")
        print("RESULT=NOT_READY")
        sys.exit(1)
    if failed:
        print(f"manual_verification_recommended={failed} warn-level item(s)")
    print("RESULT=READY")


if __name__ == "__main__":
    main()
