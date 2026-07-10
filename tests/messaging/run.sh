#!/usr/bin/env bash
set -euo pipefail

if ! mysqladmin ping --silent >/dev/null 2>&1; then
  sudo service mariadb start
fi

sudo mysql -e "
  CREATE DATABASE IF NOT EXISTS pax_chat_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
  CREATE USER IF NOT EXISTS 'pax_test'@'localhost' IDENTIFIED BY '';
  GRANT ALL PRIVILEGES ON pax_chat_test.* TO 'pax_test'@'localhost';
  FLUSH PRIVILEGES;
"
export PAX_TEST_DB_USER=pax_test
export PAX_TEST_DB_PASS=
php "$(dirname "$0")/run.php"
