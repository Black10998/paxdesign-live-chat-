#!/usr/bin/env python3
"""Update App Store Connect age rating declaration for PAXDesign Live Chat."""

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
fail = _SETUP.fail
warn = _SETUP.warn
find_app = _SETUP.find_app
make_client = _SETUP.make_client
load_config = _SETUP.load_config

# Accurate answers based on codebase audit (see docs/app-store/COMPLIANCE_REVIEW.md)
TARGET_ATTRIBUTES: dict[str, Any] = {
    "messagingAndChat": True,
    "userGeneratedContent": True,
    "unrestrictedWebAccess": False,
    "advertising": False,
    "parentalControls": False,
    "ageAssurance": False,
    "gambling": False,
    "lootBox": False,
    "healthOrWellnessTopics": False,
    "alcoholTobaccoOrDrugUseOrReferences": "NONE",
    "contests": "NONE",
    "gamblingSimulated": "NONE",
    "medicalOrTreatmentInformation": "NONE",
    "profanityOrCrudeHumor": "NONE",
    "sexualContentGraphicAndNudity": "NONE",
    "sexualContentOrNudity": "NONE",
    "horrorOrFearThemes": "NONE",
    "matureOrSuggestiveThemes": "NONE",
    "violenceCartoonOrFantasy": "NONE",
    "violenceRealistic": "NONE",
    "violenceRealisticProlongedGraphicOrSadistic": "NONE",
    "gunsOrOtherWeapons": "NONE",
}


def get_declaration(client: ASCClient, app_id: str) -> tuple[str, dict[str, Any]]:
    info_payload = client.get(f"/apps/{app_id}/appInfos", **{"limit": "5"})
    infos = info_payload.get("data") or []
    if not infos:
        fail("No appInfos found")
    info_id = infos[0]["id"]
    payload = client.get(f"/appInfos/{info_id}/ageRatingDeclaration")
    data = payload.get("data") or {}
    if not data:
        fail("No ageRatingDeclaration found")
    return data["id"], data.get("attributes") or {}


def main() -> None:
    issuer_id, key_id, private_key = load_config()
    client = make_client(issuer_id, key_id, private_key)
    app = find_app(client)
    app_id = app["id"]

    decl_id, before = get_declaration(client, app_id)
    print("Current age rating declaration (selected fields):")
    for key in sorted(TARGET_ATTRIBUTES):
        if key in before:
            print(f"  {key}: {before[key]}")

    attrs = dict(TARGET_ATTRIBUTES)
    # Include socialMedia fields if API supports them (new July 2026 questionnaire)
    if "socialMedia" in before:
        attrs["socialMedia"] = False
    if "socialMediaDisabledForUsersUnder13" in before:
        attrs["socialMediaDisabledForUsersUnder13"] = False

    status, response = client.request(
        "PATCH",
        f"/ageRatingDeclarations/{decl_id}",
        body={
            "data": {
                "type": "ageRatingDeclarations",
                "id": decl_id,
                "attributes": attrs,
            }
        },
        allow_error=True,
    )
    if status not in {200, 201}:
        fail(f"Age rating update failed ({status}): {json.dumps(response)}")

    _, after = get_declaration(client, app_id)
    print("Updated age rating declaration:")
    for key in sorted(attrs):
        print(f"  {key}: {after.get(key, attrs[key])}")
    print("Age rating update complete")


if __name__ == "__main__":
    main()
