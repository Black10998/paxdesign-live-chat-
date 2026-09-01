#!/usr/bin/env bash
# Per-boot startup: bring up MariaDB and ensure the messaging test database
# exists. The messaging suite (tests/messaging/run.php) connects with the
# default DSN in tests/messaging/bootstrap.php:
#   mysql:unix_socket=/run/mysqld/mysqld.sock;dbname=pax_chat_test  (user root, empty password)
# so root@localhost is switched to empty-password native auth to allow any
# local OS user to connect over the socket. Idempotent and safe to re-run.
set -euo pipefail

sudo mkdir -p /run/mysqld
sudo chown mysql:mysql /run/mysqld

if ! sudo mysqladmin ping >/dev/null 2>&1; then
  sudo mariadbd-safe --skip-syslog >/tmp/mariadb.log 2>&1 &
  for _ in $(seq 1 30); do
    if sudo mysqladmin ping >/dev/null 2>&1; then
      break
    fi
    sleep 1
  done
fi

if ! sudo mysqladmin ping >/dev/null 2>&1; then
  echo "MariaDB failed to start" >&2
  tail -n 40 /tmp/mariadb.log >&2 || true
  exit 1
fi

sudo mysql -e "CREATE DATABASE IF NOT EXISTS pax_chat_test CHARACTER SET utf8mb4;
ALTER USER 'root'@'localhost' IDENTIFIED VIA mysql_native_password USING '';
FLUSH PRIVILEGES;"

echo "MariaDB ready; database pax_chat_test present."
