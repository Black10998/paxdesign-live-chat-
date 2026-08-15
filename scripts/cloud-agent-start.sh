#!/usr/bin/env bash
# Per-boot startup for the PAXdesign Live Chat Cloud Agent environment.
#
# Starts MariaDB and provisions the database/user the messaging reliability
# tests expect. Idempotent: tolerates an already-running server and existing
# database objects, and returns once the server is accepting connections.
set -euo pipefail

# Start MariaDB only if it is not already accepting connections.
if ! sudo mysqladmin ping --silent >/dev/null 2>&1; then
  echo "==> Starting MariaDB"
  sudo service mariadb start
fi

# Wait for readiness (up to ~30s) before provisioning.
ready=0
for _ in $(seq 1 30); do
  if sudo mysqladmin ping --silent >/dev/null 2>&1; then
    ready=1
    break
  fi
  sleep 1
done

if [ "$ready" -ne 1 ]; then
  echo "ERROR: MariaDB did not become ready" >&2
  exit 1
fi

# Provision the test database + user used by tests/messaging/run.sh.
sudo mysql -e "
  CREATE DATABASE IF NOT EXISTS pax_chat_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
  CREATE USER IF NOT EXISTS 'pax_test'@'localhost' IDENTIFIED BY '';
  GRANT ALL PRIVILEGES ON pax_chat_test.* TO 'pax_test'@'localhost';
  FLUSH PRIVILEGES;
"

echo "==> MariaDB ready; pax_chat_test database provisioned."
