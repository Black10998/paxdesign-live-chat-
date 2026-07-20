#!/usr/bin/env bash
# Inspect WordPress disk usage + DB bloat; backup; safe cleanup; optimize; disable debug logging.
# Runs ON the production host (via SSH from GitHub Actions).
set -euo pipefail

WP_ROOT="${WP_PATH:?WP_PATH is required}"
MODE="${MODE:-full}"  # inspect | cleanup | full
REPORT="${REPORT_PATH:-${WP_ROOT}/wp-content/pax-disk-audit-$(date -u +%Y%m%dT%H%M%SZ).txt}"
BACKUP_ROOT="${BACKUP_ROOT:-${WP_ROOT%/}/../paxdesign-backups}"
STAMP="$(date -u +%Y%m%dT%H%M%SZ)"
AUDIT_BACKUP="${BACKUP_ROOT}/disk-audit-${STAMP}"

exec > >(tee -a "$REPORT") 2>&1

section() { echo; echo "=== $* ==="; echo "Time: $(date -u '+%Y-%m-%d %H:%M:%S UTC')"; }

human_size() {
  local bytes="$1"
  if command -v numfmt >/dev/null 2>&1; then
    numfmt --to=iec-i --suffix=B "$bytes" 2>/dev/null || echo "${bytes}B"
  else
    echo "${bytes}B"
  fi
}

file_size_bytes() {
  stat -c%s "$1" 2>/dev/null || stat -f%z "$1" 2>/dev/null || wc -c <"$1" 2>/dev/null || echo 0
}

wp_cmd() {
  if command -v wp >/dev/null 2>&1; then
    wp --path="$WP_ROOT" "$@"
  else
    return 127
  fi
}

read_wp_config_value() {
  local key="$1"
  php -r "
    \$config = file_get_contents('${WP_ROOT}/wp-config.php');
    if (preg_match(\"/define\\s*\\(\\s*['\\\"]${key}['\\\"]\\s*,\\s*['\\\"]([^'\\\"]*)['\\\"]\\s*\\)/\", \$config, \$m)) {
      echo \$m[1];
    }
  " 2>/dev/null || true
}

mysql_query() {
  local sql="$1"
  if wp_cmd db query "$sql" --skip-column-names 2>/dev/null; then
    return 0
  fi
  local db_name db_user db_pass db_host db_port
  db_name="$(read_wp_config_value DB_NAME)"
  db_user="$(read_wp_config_value DB_USER)"
  db_pass="$(read_wp_config_value DB_PASSWORD)"
  db_host="$(read_wp_config_value DB_HOST)"
  db_port="3306"
  if [[ "$db_host" == *:* ]]; then
    db_port="${db_host#*:}"
    db_host="${db_host%%:*}"
  fi
  MYSQL_PWD="$db_pass" mysql -h"$db_host" -P"$db_port" -u"$db_user" "$db_name" -N -e "$sql"
}

section "Disk audit start"
echo "WP_ROOT=$WP_ROOT"
echo "MODE=$MODE"
echo "REPORT=$REPORT"
echo "Host=$(hostname 2>/dev/null || echo unknown)"
echo "User=$(whoami 2>/dev/null || echo unknown)"

section "Filesystem quota and top-level usage"
if command -v quota >/dev/null 2>&1; then quota -s 2>/dev/null || true; fi
df -h "$WP_ROOT" "$HOME" 2>/dev/null || df -h
du -sh "$WP_ROOT" 2>/dev/null || true
du -sh "${WP_ROOT%/}/../"* 2>/dev/null | sort -hr | head -20 || true

section "Largest files under WordPress root (top 40)"
find "$WP_ROOT" -xdev -type f -printf '%s\t%p\n' 2>/dev/null \
  | sort -nr | head -40 \
  | while IFS=$'\t' read -r size path; do
      printf '%s\t%s\n' "$(human_size "$size")" "$path"
    done || \
find "$WP_ROOT" -type f -exec stat -c '%s %n' {} + 2>/dev/null | sort -nr | head -40 || true

section "Debug and PHP log candidates"
for candidate in \
  "$WP_ROOT/wp-content/debug.log" \
  "$WP_ROOT/debug.log" \
  "$WP_ROOT/error_log" \
  "$WP_ROOT/wp-content/error_log" \
  "$WP_ROOT/wp-content/uploads/debug.log" \
  "$HOME/logs/error.log" \
  "$HOME/error_log" \
  "$HOME/public_html/error_log" \
  "/var/log/php-fpm/error.log" \
  "/var/log/php/error.log"; do
  if [[ -f "$candidate" ]]; then
    bytes=$(file_size_bytes "$candidate")
    lines=$(wc -l <"$candidate" 2>/dev/null || echo 0)
    echo "$(human_size "$bytes") ($lines lines) $candidate"
    echo "  tail patterns:"
    tail -n 200 "$candidate" 2>/dev/null | grep -oE 'PAXdesign[^[:space:]]*|PHP (Warning|Notice|Fatal|Deprecated)[^[:cn]]*|email_mapped_to_login|WP_DEBUG' | sort | uniq -c | sort -nr | head -15 || true
  fi
done

section "Backup archives"
if [[ -d "$BACKUP_ROOT" ]]; then
  du -sh "$BACKUP_ROOT" 2>/dev/null || true
  find "$BACKUP_ROOT" -maxdepth 2 -type f -printf '%s\t%p\n' 2>/dev/null | sort -nr | head -25 | while IFS=$'\t' read -r s p; do printf '%s\t%s\n' "$(human_size "$s")" "$p"; done || true
  find "$BACKUP_ROOT" -maxdepth 1 -type d -name 'customer-platform-*' 2>/dev/null | wc -l | xargs echo "customer-platform backup dirs:"
fi

section "Cache and temp directories"
for dir in \
  "$WP_ROOT/wp-content/cache" \
  "$WP_ROOT/wp-content/litespeed" \
  "$WP_ROOT/wp-content/lscache" \
  "$WP_ROOT/wp-content/upgrade" \
  "$WP_ROOT/wp-content/backup*" \
  "$WP_ROOT/wp-content/wflogs" \
  "$WP_ROOT/wp-content/updraft" \
  "$WP_ROOT/wp-content/ai1wm-backups"; do
  for match in $dir; do
    [[ -e "$match" ]] || continue
    du -sh "$match" 2>/dev/null || true
  done
done

section "Uploads breakdown (top-level)"
du -sh "$WP_ROOT/wp-content/uploads" 2>/dev/null || true
du -sh "$WP_ROOT/wp-content/uploads"/* 2>/dev/null | sort -hr | head -15 || true

section "wp-config debug flags"
grep -E "WP_DEBUG|WP_DEBUG_LOG|WP_DEBUG_DISPLAY" "$WP_ROOT/wp-config.php" 2>/dev/null || true

if ! wp_cmd core version >/dev/null 2>&1; then
  section "WARN: wp-cli unavailable — skipping DB analysis"
  echo "PASS: filesystem inspection complete (DB skipped)"
  exit 0
fi

TABLE_PREFIX="$(wp_cmd db prefix 2>/dev/null | tr -d '\r\n')"
POSTMETA="${TABLE_PREFIX}postmeta"
POSTS="${TABLE_PREFIX}posts"
OPTIONS="${TABLE_PREFIX}options"

section "Database table sizes"
mysql_query "
SELECT table_name,
       ROUND((data_length + index_length)/1024/1024, 2) AS total_mb,
       table_rows
FROM information_schema.tables
WHERE table_schema = DATABASE()
  AND table_name IN ('${POSTMETA}', '${POSTS}', '${OPTIONS}')
ORDER BY (data_length + index_length) DESC;
" || true

section "Largest wp_postmeta rows (top 25 by meta_value length)"
mysql_query "
SELECT pm.meta_id,
       pm.post_id,
       pm.meta_key,
       LENGTH(pm.meta_value) AS bytes,
       p.post_type,
       p.post_status,
       LEFT(p.post_title, 80) AS post_title
FROM ${POSTMETA} pm
LEFT JOIN ${POSTS} p ON p.ID = pm.post_id
ORDER BY LENGTH(pm.meta_value) DESC
LIMIT 25;
" || true

section "wp_postmeta total bytes by meta_key (top 30)"
mysql_query "
SELECT meta_key,
       COUNT(*) AS rows,
       SUM(LENGTH(meta_value)) AS total_bytes,
       ROUND(SUM(LENGTH(meta_value))/1024/1024, 2) AS total_mb,
       MAX(LENGTH(meta_value)) AS max_row_bytes
FROM ${POSTMETA}
GROUP BY meta_key
ORDER BY total_bytes DESC
LIMIT 30;
" || true

section "wp_postmeta by meta_key prefix (plugin hints)"
mysql_query "
SELECT CASE
         WHEN meta_key LIKE '_elementor%' THEN '_elementor*'
         WHEN meta_key LIKE '_wp_%' THEN '_wp_*'
         WHEN meta_key LIKE '_wc_%' OR meta_key LIKE '_woocommerce%' THEN 'woocommerce*'
         WHEN meta_key LIKE '_yoast_%' THEN 'yoast*'
         WHEN meta_key LIKE '_oembed_%' THEN '_oembed_*'
         WHEN meta_key LIKE 'pax%' OR meta_key LIKE '_pax%' THEN 'paxdesign*'
         ELSE LEFT(meta_key, 40)
       END AS key_group,
       COUNT(*) AS rows,
       ROUND(SUM(LENGTH(meta_value))/1024/1024, 2) AS total_mb
FROM ${POSTMETA}
GROUP BY key_group
ORDER BY total_mb DESC
LIMIT 25;
" || true

section "Largest wp_posts rows (top 20 by post_content length)"
mysql_query "
SELECT ID,
       post_type,
       post_status,
       LENGTH(post_content) AS content_bytes,
       LENGTH(post_excerpt) AS excerpt_bytes,
       LEFT(post_title, 80) AS post_title,
       post_modified
FROM ${POSTS}
ORDER BY LENGTH(post_content) DESC
LIMIT 20;
" || true

section "wp_posts size by post_type"
mysql_query "
SELECT post_type,
       post_status,
       COUNT(*) AS rows,
       ROUND(SUM(LENGTH(post_content))/1024/1024, 2) AS content_mb
FROM ${POSTS}
GROUP BY post_type, post_status
ORDER BY content_mb DESC
LIMIT 25;
" || true

section "Autoloaded options size (top 20)"
mysql_query "
SELECT option_name,
       LENGTH(option_value) AS bytes,
       autoload
FROM ${OPTIONS}
WHERE autoload IN ('yes', 'on', 'auto-on', 'auto')
ORDER BY LENGTH(option_value) DESC
LIMIT 20;
" || true

section "Transient/options bloat (pax + _transient counts)"
mysql_query "
SELECT
  SUM(CASE WHEN option_name LIKE '_transient_%' THEN 1 ELSE 0 END) AS transient_rows,
  SUM(CASE WHEN option_name LIKE '_transient_%' THEN LENGTH(option_value) ELSE 0 END) AS transient_bytes,
  SUM(CASE WHEN option_name LIKE '_site_transient_%' THEN 1 ELSE 0 END) AS site_transient_rows,
  SUM(CASE WHEN option_name LIKE 'pax%' OR option_name LIKE '_pax%' THEN 1 ELSE 0 END) AS pax_option_rows,
  SUM(CASE WHEN option_name LIKE 'pax%' OR option_name LIKE '_pax%' THEN LENGTH(option_value) ELSE 0 END) AS pax_option_bytes
FROM ${OPTIONS};
" || true

if [[ "$MODE" == "inspect" ]]; then
  section "Inspect-only mode — no changes made"
  echo "PASS: inspection complete"
  exit 0
fi

section "Pre-cleanup backup"
mkdir -p "$AUDIT_BACKUP"
for logfile in "$WP_ROOT/wp-content/debug.log" "$WP_ROOT/debug.log" "$WP_ROOT/error_log"; do
  if [[ -f "$logfile" ]]; then
    cp -a "$logfile" "$AUDIT_BACKUP/$(basename "$logfile").${STAMP}" 2>/dev/null || true
  fi
done
if wp_cmd db export "$AUDIT_BACKUP/database-pre-cleanup.sql" --add-drop-table --single-transaction --default-character-set=utf8mb4 2>/dev/null; then
  gzip -f "$AUDIT_BACKUP/database-pre-cleanup.sql" || true
  echo "Database backup: $AUDIT_BACKUP/database-pre-cleanup.sql.gz"
else
  echo "WARN: database backup failed — aborting destructive cleanup"
  exit 1
fi
echo "Backup dir: $AUDIT_BACKUP"

section "Safe filesystem cleanup"
# Truncate (not delete) active debug logs after backup
for logfile in "$WP_ROOT/wp-content/debug.log" "$WP_ROOT/debug.log"; do
  if [[ -f "$logfile" ]]; then
    before=$(file_size_bytes "$logfile")
    : >"$logfile"
    echo "Truncated $logfile (was $(human_size "$before"))"
  fi
done

# Remove stale backup dirs older than 14 days (keep 3 newest regardless)
if [[ -d "$BACKUP_ROOT" ]]; then
  mapfile -t all_backups < <(find "$BACKUP_ROOT" -maxdepth 1 -mindepth 1 -type d -name 'customer-platform-*' | sort -r)
  kept=0
  for dir in "${all_backups[@]}"; do
    kept=$((kept + 1))
    if [[ $kept -le 3 ]]; then
      echo "Keeping recent backup: $dir"
      continue
    fi
    age_days=$(( ( $(date +%s) - $(stat -c %Y "$dir" 2>/dev/null || stat -f %m "$dir") ) / 86400 ))
    if [[ $age_days -gt 14 ]]; then
      freed=$(du -sb "$dir" 2>/dev/null | awk '{print $1}' || echo 0)
      rm -rf "$dir"
      echo "Removed old backup ($age_days d): $dir freed $(human_size "$freed")"
    fi
  done
fi

# LiteSpeed / generic cache purge (files only)
for cache_dir in "$WP_ROOT/wp-content/cache" "$WP_ROOT/wp-content/litespeed/css" "$WP_ROOT/wp-content/litespeed/js" "$WP_ROOT/wp-content/litespeed/avatar"; do
  if [[ -d "$cache_dir" ]]; then
    before=$(du -sb "$cache_dir" 2>/dev/null | awk '{print $1}' || echo 0)
    find "$cache_dir" -mindepth 1 -delete 2>/dev/null || true
    echo "Cleared cache dir $cache_dir (was $(human_size "$before"))"
  fi
done
wp_cmd cache flush 2>/dev/null || true
wp_cmd transient delete --expired 2>/dev/null || true

section "Safe database cleanup (no table truncates)"
# Expired transients
expired_count=$(mysql_query "SELECT COUNT(*) FROM ${OPTIONS} WHERE option_name LIKE '_transient_timeout_%' AND option_value < UNIX_TIMESTAMP();" | tail -1 || echo 0)
echo "Expired transient timeouts found: $expired_count"
wp_cmd transient delete --expired 2>/dev/null || true

# Old paxdesign rate-limit transients (>24h stale by name pattern — options table only)
mysql_query "
DELETE o1, o2 FROM ${OPTIONS} o1
INNER JOIN ${OPTIONS} o2 ON o2.option_name = CONCAT('_transient_timeout_', SUBSTRING(o1.option_name, 12))
WHERE o1.option_name LIKE '_transient_pax%'
  AND o2.option_value < UNIX_TIMESTAMP();
" 2>/dev/null && echo "Removed expired paxdesign transients" || true

# Remove orphan postmeta (posts deleted)
orphan_meta=$(mysql_query "
SELECT COUNT(*)
FROM ${POSTMETA} pm
LEFT JOIN ${POSTS} p ON p.ID = pm.post_id
WHERE p.ID IS NULL;
" | tail -1 || echo 0)
echo "Orphan postmeta rows: $orphan_meta"
if [[ "${orphan_meta:-0}" =~ ^[0-9]+$ ]] && [[ "$orphan_meta" -gt 0 ]]; then
  mysql_query "
DELETE pm FROM ${POSTMETA} pm
LEFT JOIN ${POSTS} p ON p.ID = pm.post_id
WHERE p.ID IS NULL;
" && echo "Deleted orphan postmeta rows: $orphan_meta"
fi

# Remove redundant oembed cache entries older than 90 days on revisions/auto-drafts only
mysql_query "
DELETE pm FROM ${POSTMETA} pm
INNER JOIN ${POSTS} p ON p.ID = pm.post_id
WHERE pm.meta_key LIKE '_oembed_%'
  AND p.post_status IN ('inherit', 'auto-draft', 'trash')
  AND p.post_modified < DATE_SUB(NOW(), INTERVAL 90 DAY);
" 2>/dev/null && echo "Pruned stale oembed postmeta on old revisions/trash" || true

section "Optimize tables (non-destructive)"
for tbl in "${POSTMETA}" "${POSTS}" "${OPTIONS}"; do
  echo "OPTIMIZE TABLE ${tbl}..."
  mysql_query "OPTIMIZE TABLE ${tbl};" || true
done

section "Disable production debug logging"
if [[ -f "$WP_ROOT/wp-config.php" ]]; then
  WP_ROOT="$WP_ROOT" php <<'PHP'
<?php
$path = getenv('WP_ROOT') . '/wp-config.php';
$text = file_get_contents($path);
$replacements = [
    "define('WP_DEBUG', true)"  => "define('WP_DEBUG', false)",
    "define('WP_DEBUG', true);" => "define('WP_DEBUG', false);",
    "define( 'WP_DEBUG', true )" => "define( 'WP_DEBUG', false )",
    "define('WP_DEBUG_LOG', true)" => "define('WP_DEBUG_LOG', false)",
    "define('WP_DEBUG_LOG', true);" => "define('WP_DEBUG_LOG', false);",
    "define( 'WP_DEBUG_LOG', true )" => "define( 'WP_DEBUG_LOG', false )",
    "define('WP_DEBUG_DISPLAY', true)" => "define('WP_DEBUG_DISPLAY', false)",
    "define('WP_DEBUG_DISPLAY', true);" => "define('WP_DEBUG_DISPLAY', false);",
];
foreach ($replacements as $from => $to) {
    $text = str_replace($from, $to, $text);
}
if (!preg_match("/define\s*\(\s*['\"]WP_DEBUG['\"]/", $text)) {
    $text = preg_replace("/(\/\* That's all, stop editing!)/", "define('WP_DEBUG', false);\ndefine('WP_DEBUG_LOG', false);\ndefine('WP_DEBUG_DISPLAY', false);\n\n$1", $text, 1);
}
file_put_contents($path, $text);
echo "Updated wp-config.php debug flags\n";
PHP
  grep -E "WP_DEBUG" "$WP_ROOT/wp-config.php" || true
fi

section "Post-cleanup disk usage"
df -h "$WP_ROOT" 2>/dev/null || df -h
du -sh "$WP_ROOT/wp-content/debug.log" 2>/dev/null || echo "debug.log absent or empty"

section "Post-cleanup table sizes"
mysql_query "
SELECT table_name,
       ROUND((data_length + index_length)/1024/1024, 2) AS total_mb,
       table_rows
FROM information_schema.tables
WHERE table_schema = DATABASE()
  AND table_name IN ('${POSTMETA}', '${POSTS}', '${OPTIONS}')
ORDER BY (data_length + index_length) DESC;
" || true

section "Done"
echo "PASS: disk audit and cleanup complete"
echo "Report: $REPORT"
echo "Backup: $AUDIT_BACKUP"
