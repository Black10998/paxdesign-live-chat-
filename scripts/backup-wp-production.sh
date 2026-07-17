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

if command -v wp >/dev/null 2>&1; then
  echo "==> Exporting database via wp db export"
  wp --path="$WP_PATH" db export "$TARGET_DIR/database.sql" --add-drop-table
else
  echo "WARN: wp-cli not found; skipping database export"
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
