#!/usr/bin/env python3
"""Normalize Apple web OAuth Auth Key (.p8) from PAX_APPLE_WEB_* env vars."""

from __future__ import annotations

import base64
import os
import re
import subprocess
import sys


def fail(message: str) -> None:
    print(f"ERROR: {message}", file=sys.stderr)
    sys.exit(1)


def normalize_pem(raw: str) -> str:
    key = raw.strip().strip('"').replace("\r", "")
    key = key.replace("\\n", "\n")
    if "BEGIN PRIVATE KEY" not in key:
        return key

    if key.count("\n") >= 2:
        return key if key.endswith("\n") else key + "\n"

    match = re.search(
        r"-----BEGIN PRIVATE KEY-----\s*(.+?)\s*-----END PRIVATE KEY-----",
        key,
        re.DOTALL,
    )
    if not match:
        return key

    body = re.sub(r"\s+", "", match.group(1))
    wrapped = "\n".join(body[i : i + 64] for i in range(0, len(body), 64))
    return f"-----BEGIN PRIVATE KEY-----\n{wrapped}\n-----END PRIVATE KEY-----\n"


def load_key() -> str:
    b64 = os.environ.get("PAX_APPLE_WEB_KEY_P8_BASE64", "").strip()
    if b64:
        if "BEGIN PRIVATE KEY" in b64:
            return normalize_pem(b64)
        try:
            raw = base64.b64decode("".join(b64.split())).decode("utf-8")
            return normalize_pem(raw)
        except Exception as exc:  # noqa: BLE001
            fail(f"Could not decode PAX_APPLE_WEB_KEY_P8_BASE64: {exc}")

    raw = os.environ.get("PAX_APPLE_WEB_KEY_P8", "").strip()
    if not raw:
        fail("Missing PAX_APPLE_WEB_KEY_P8_BASE64 or PAX_APPLE_WEB_KEY_P8")

    return normalize_pem(raw)


def validate_key(key: str) -> None:
    proc = subprocess.run(
        ["openssl", "pkey", "-noout"],
        input=key.encode("utf-8"),
        capture_output=True,
        check=False,
    )
    if proc.returncode != 0:
        fail(
            "Apple web OAuth private key is invalid. Store the full AuthKey_XXXX.p8 "
            "file base64-encoded in PAX_APPLE_WEB_KEY_P8_BASE64."
        )


def main() -> None:
    key = load_key()
    validate_key(key)

    key_id = os.environ.get("PAX_APPLE_WEB_KEY_ID", "").strip()
    if key_id:
        print(f"Prepared Apple web OAuth AuthKey for Key ID ...{key_id[-4:]}")

    sys.stdout.write(key)


if __name__ == "__main__":
    main()
