#!/usr/bin/env bash
# Idempotent Cloud Agent setup for PAXdesign Live Chat.
#
# Installs the PHP + MariaDB toolchain used by the automated test suite
# (tests/messaging, tests/customer-platform, tests/ccs-ai) and the plugin
# release build (scripts/build-release.sh). Safe to run repeatedly.
set -euo pipefail

export DEBIAN_FRONTEND=noninteractive

PACKAGES=(
  php-cli        # PHP CLI runner for tests and `php -l` linting
  php-mysql      # pdo_mysql for the messaging durability tests
  php-mbstring   # mb_* helpers used by the cybercrime AI workflow
  php-curl       # OpenAI / REST integrations
  php-gd         # image handling (avatars, uploads)
  mariadb-server # database backing the messaging reliability tests
  mariadb-client # mysql/mysqladmin CLIs
  zip            # scripts/build-release.sh packages the plugin ZIP
  unzip          # inspect built release archives
)

missing=()
for pkg in "${PACKAGES[@]}"; do
  if ! dpkg -s "$pkg" >/dev/null 2>&1; then
    missing+=("$pkg")
  fi
done

if [ "${#missing[@]}" -gt 0 ]; then
  echo "==> Installing: ${missing[*]}"
  sudo apt-get update -qq
  sudo apt-get install -y -qq "${missing[@]}"
else
  echo "==> All system packages already present"
fi

echo "==> Toolchain versions"
php --version | head -1
node --version 2>/dev/null || echo "node: not found (provided by the base image)"

echo "==> Verifying required PHP extensions"
for ext in pdo_mysql mbstring curl gd json; do
  if php -m | grep -qix "$ext"; then
    echo "    ok: $ext"
  else
    echo "    ERROR: missing PHP extension: $ext" >&2
    exit 1
  fi
done

echo "==> PAXdesign Live Chat toolchain ready."
