#!/usr/bin/env bash
set -euo pipefail

# Creates a timestamped database dump and wp-content archive on the remote WordPress host.
# Intended to run over SSH from GitHub Actions before customer platform deploy.

WP_PATH="${WP_PATH:?WP_PATH is required}"
BACKUP_ROOT="${BACKUP_ROOT:-${WP_PATH%/}/../paxdesign-backups}"
STAMP="$(date -u +%Y%m%dT%H%M%SZ)"
TARGET_DIR="${BACKUP_ROOT}/customer-platform-${STAMP}"

mkdir -p "$TARGET_DIR"

echo "==> Backup directory: $TARGET_DIR"

read_wp_config_value() {
  local key="$1"
  php -r "
    \$config = file_get_contents('${WP_PATH}/wp-config.php');
    if (preg_match(\"/define\\s*\\(\\s*['\\\"]${key}['\\\"]\\s*,\\s*['\\\"]([^'\\\"]*)['\\\"]\\s*\\)/\", \$config, \$m)) {
      echo \$m[1];
    }
  " 2>/dev/null || true
}

export_database() {
  local output="$1"
  local db_name db_user db_pass db_host db_port

  if command -v wp >/dev/null 2>&1; then
    echo "==> Exporting database via wp db export"
    if wp --path="$WP_PATH" db export "$output" --add-drop-table --single-transaction --default-character-set=utf8mb4 2>"$TARGET_DIR/wp-db-export.err"; then
      return 0
    fi
    echo "WARN: wp db export failed (see $TARGET_DIR/wp-db-export.err)"
    cat "$TARGET_DIR/wp-db-export.err" || true
  else
    echo "WARN: wp-cli not found"
  fi

  db_name="$(read_wp_config_value DB_NAME)"
  db_user="$(read_wp_config_value DB_USER)"
  db_pass="$(read_wp_config_value DB_PASSWORD)"
  db_host="$(read_wp_config_value DB_HOST)"
  if [ -z "$db_name" ] || [ -z "$db_user" ] || [ -z "$db_host" ]; then
    echo "ERROR: Could not read database credentials from wp-config.php" >&2
    return 1
  fi

  db_port="3306"
  if [[ "$db_host" == *:* ]]; then
    db_port="${db_host#*:}"
    db_host="${db_host%%:*}"
  fi

  if ! command -v mysqldump >/dev/null 2>&1; then
    echo "ERROR: mysqldump unavailable and wp db export failed" >&2
    return 1
  fi

  echo "==> Exporting database via mysqldump fallback"
  MYSQL_PWD="$db_pass" mysqldump \
    --host="$db_host" \
    --port="$db_port" \
    --user="$db_user" \
    --single-transaction \
    --quick \
    --default-character-set=utf8mb4 \
    --add-drop-table \
    "$db_name" > "$output"
}

if ! export_database "$TARGET_DIR/database.sql"; then
  echo "ERROR: database backup failed" >&2
  exit 1
fi

echo "==> Archiving wp-content"
tar -czf "$TARGET_DIR/wp-content.tgz" -C "$WP_PATH" wp-content

if [ -f "$TARGET_DIR/database.sql" ]; then
  gzip -f "$TARGET_DIR/database.sql"
fi

cat > "$TARGET_DIR/manifest.txt" <<EOF
backup_created_utc=${STAMP}
wp_path=${WP_PATH}
database=$( [ -f "$TARGET_DIR/database.sql.gz" ] && echo database.sql.gz || echo missing )
wp_content=wp-content.tgz
EOF

echo "==> Backup manifest"
cat "$TARGET_DIR/manifest.txt"

echo "PASS: backup completed at $TARGET_DIR"
