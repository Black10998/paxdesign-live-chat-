#!/usr/bin/env bash
# Idempotent dependency setup for the PAXdesign Live Chat repository.
# The test suites and CI use the PHP CLI (static guards) plus MariaDB and the
# pdo_mysql extension for the messaging durability/concurrency suite. Node and
# zip are already provided by the base image and are used for JS client checks
# and the plugin release build.
set -euo pipefail

export DEBIAN_FRONTEND=noninteractive

sudo apt-get update -y
sudo apt-get install -y --no-install-recommends \
  php-cli \
  php-mysql \
  mariadb-server \
  zip

php --version
mariadbd --version

# Fail fast if the PHP extension the messaging durability/concurrency suite
# depends on is missing, so a broken image is caught during install instead of
# only when tests/messaging connects over PDO.
if ! php -m | grep -qi '^pdo_mysql$'; then
  echo "Required PHP extension pdo_mysql is not available" >&2
  exit 1
fi
echo "pdo_mysql extension present."
