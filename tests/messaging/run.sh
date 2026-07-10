#!/usr/bin/env bash
set -euo pipefail

if ! mysqladmin ping --silent >/dev/null 2>&1; then
  sudo service mariadb start
fi

mysql -uroot -e 'CREATE DATABASE IF NOT EXISTS pax_chat_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
php "$(dirname "$0")/run.php"
