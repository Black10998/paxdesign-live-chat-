#!/usr/bin/env bash
# Live anonymous-visitor checks for public identity hardening.
# Safe / non-destructive only. Never prints account identifiers.
set -euo pipefail

BASE="${BASE:-https://paxdesign.at}"
STAMP="$(date +%s)"
FAIL=0
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT

ok() { echo "OK  $*"; }
fail() { echo "FAIL $*"; FAIL=$((FAIL + 1)); }

has_email() {
  grep -Eqi '[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}' "$1"
}

has_author_slug_path() {
  grep -Eqi '/author/[^/"[:space:]]+' "$1"
}

users_payload_leaks() {
  local file="$1"
  if grep -Eq '"slug"[[:space:]]*:' "$file"; then
    return 0
  fi
  if grep -Eq '"id"[[:space:]]*:[[:space:]]*[0-9]+' "$file" && grep -Eq '"name"[[:space:]]*:' "$file"; then
    return 0
  fi
  return 1
}

request() {
  local name="$1"
  local method="$2"
  shift 2
  local body="$TMP/${name}.body"
  local hdr="$TMP/${name}.headers"
  local code
  code="$(curl -sS -D "$hdr" -o "$body" -w '%{http_code}' -X "$method" \
    -H 'Cache-Control: no-cache' -H 'Pragma: no-cache' "$@")"
  echo "$code"
}

echo "Public identity hardening live verify against ${BASE}"

# --- Users REST ---
for name_path in \
  "users_list /wp-json/wp/v2/users?n=${STAMP}" \
  "users_1 /wp-json/wp/v2/users/1?n=${STAMP}" \
  "rest_route /?rest_route=/wp/v2/users&n=${STAMP}"
do
  name="${name_path%% *}"
  path="${name_path#* }"
  code="$(request "$name" GET "${BASE}${path}")"
  body="$TMP/${name}.body"
  if users_payload_leaks "$body" || has_email "$body" || has_author_slug_path "$body"; then
    fail "${name} still exposes account fields (HTTP ${code})"
    continue
  fi
  if [ "$code" = "200" ]; then
    fail "${name} returned HTTP 200 without a forbidden/empty identity payload"
    continue
  fi
  ok "${name} is closed to anonymous visitors (HTTP ${code})"
done

# --- Author archive first hop must not leak /author/{slug}/ ---
author_code="$(curl -sS -D "$TMP/author.headers" -o "$TMP/author.body" -w '%{http_code}' \
  --max-redirs 0 -H 'Cache-Control: no-cache' "${BASE}/?author=1&n=${STAMP}" || true)"
tr -d '\r' < "$TMP/author.headers" | grep -i '^location:' > "$TMP/author.location" || true
if has_author_slug_path "$TMP/author.headers" || has_author_slug_path "$TMP/author.location"; then
  fail "author=1 Location still points at an author archive"
elif has_email "$TMP/author.body" && [ "$author_code" = "200" ]; then
  fail "author=1 served an identity page"
else
  loc="$(tr -d '\r' < "$TMP/author.location" | awk '{print $2}' | head -1 || true)"
  if [ -n "$loc" ] && [[ "$loc" != "${BASE}/" && "$loc" != "${BASE}" && "$loc" != "${BASE}/?"* ]]; then
    # Homepage with or without trailing slash / query is acceptable.
    if [[ "$loc" == "${BASE}/"* && "$loc" != *"/author/"* ]]; then
      ok "author=1 redirects away from author archives (HTTP ${author_code})"
    else
      fail "author=1 redirected to an unexpected non-home URL"
    fi
  else
    ok "author=1 no longer exposes an author archive (HTTP ${author_code})"
  fi
fi

# --- XML-RPC: safe listMethods only ---
xml_code="$(request xmlrpc_methods POST \
  -H 'Content-Type: text/xml' \
  --data '<?xml version="1.0"?><methodCall><methodName>system.listMethods</methodName></methodCall>' \
  "${BASE}/xmlrpc.php")"
xml_body="$TMP/xmlrpc_methods.body"
if grep -Eqi 'wp\.getUsers|wp\.getAuthors|pingback\.ping|blogger\.getUserInfo' "$xml_body"; then
  fail "xmlrpc.php still advertises account/pingback methods (HTTP ${xml_code})"
else
  ok "xmlrpc.php no longer advertises account/pingback methods (HTTP ${xml_code})"
fi

rsd_code="$(request xmlrpc_rsd GET "${BASE}/xmlrpc.php?rsd")"
if grep -Eqi 'apiLink|WordPress' "$TMP/xmlrpc_rsd.body" && [ "$rsd_code" = "200" ]; then
  fail "xmlrpc.php?rsd still publishes the WordPress API map"
else
  ok "xmlrpc.php?rsd is closed (HTTP ${rsd_code})"
fi

# --- Site functions still work ---
home_code="$(request home GET "${BASE}/")"
[ "$home_code" = "200" ] && ok "homepage HTTP 200" || fail "homepage HTTP ${home_code}"

login_code="$(request login GET "${BASE}/wp-login.php")"
[ "$login_code" = "200" ] && ok "wp-login.php HTTP 200" || fail "wp-login.php HTTP ${login_code}"
grep -Eqi 'log|pwd|Anmelden|login' "$TMP/login.body" \
  && ok "login form is present" \
  || fail "login form missing"

# --- Generic wp-login errors (no account enumeration) ---
probe_login() {
  local name="$1"
  local user="$2"
  curl -sS -D "$TMP/${name}.headers" -o "$TMP/${name}.body" -w '%{http_code}' \
    -X POST "${BASE}/wp-login.php" \
    -H 'Content-Type: application/x-www-form-urlencoded' \
    --data-urlencode "log=${user}" \
    --data-urlencode "pwd=wrong-password-not-used" \
    --data-urlencode "wp-submit=Log In" \
    --data-urlencode "testcookie=1" >/dev/null
  if grep -Eiq 'not registered|nicht registriert|kein konto|unknown username|unknown email' "$TMP/${name}.body"; then
    fail "${name} still enumerates missing accounts"
    return
  fi
  if grep -Eiq 'entered for the username|für den benutzername|incorrect password for' "$TMP/${name}.body"; then
    fail "${name} still names the account in the password error"
    return
  fi
  ok "${name} uses a generic login failure"
}
probe_login login_unknown "pax-no-such-user-${STAMP}"
probe_login login_common admin

# --- REST CORS must not reflect a foreign Origin ---
cors_code="$(curl -sS -D "$TMP/cors.headers" -o "$TMP/cors.body" -w '%{http_code}' \
  -H 'Origin: https://evil.example' \
  -H 'Cache-Control: no-cache' \
  "${BASE}/wp-json/pdx/v1/auth/me?n=${STAMP}")"
tr -d '\r' < "$TMP/cors.headers" | grep -i '^access-control-allow-origin:' > "$TMP/cors.acao" || true
if grep -Fqi 'https://evil.example' "$TMP/cors.headers"; then
  fail "REST CORS reflects a foreign Origin (HTTP ${cors_code})"
else
  ok "REST CORS does not reflect a foreign Origin (HTTP ${cors_code})"
fi
if grep -Fqi 'access-control-allow-credentials: true' "$TMP/cors.headers" \
  && grep -Fqi 'access-control-allow-origin: https://evil.example' "$TMP/cors.headers"; then
  fail "REST CORS still pairs credentials with a foreign Origin"
else
  ok "REST CORS does not pair credentials with a foreign Origin"
fi

# --- Public media library listing ---
media_code="$(request media GET "${BASE}/wp-json/wp/v2/media?per_page=5&n=${STAMP}")"
if [ "$media_code" = "200" ] && grep -Eq '"source_url"|"media_details"' "$TMP/media.body"; then
  fail "media collection still lists files anonymously (HTTP ${media_code})"
else
  ok "media collection is closed to anonymous visitors (HTTP ${media_code})"
fi

ccs_code="$(request ccs GET "${BASE}/cybercrime-support/")"
[ "$ccs_code" = "200" ] && ok "cybercrime-support HTTP 200" || fail "cybercrime-support HTTP ${ccs_code}"
grep -Eqi 'cybercrime|Cybercrime' "$TMP/ccs.body" \
  && ok "cybercrime-support page content is present" \
  || fail "cybercrime-support content missing"

chat_code="$(request chat_js GET "${BASE}/wp-content/plugins/paxdesign-booking/assets/js/chat-script.js?n=${STAMP}")"
[ "$chat_code" = "200" ] && ok "chat-script.js HTTP 200" || fail "chat-script.js HTTP ${chat_code}"
grep -q 'Version: 3.174.128' "$TMP/chat_js.body" \
  && ok "chat JS still reports 3.174.128" \
  || fail "chat JS version is not 3.174.128"
if grep -q 'skipping stacked sync' "$TMP/chat_js.body"; then
  fail "chat JS contains 3.176 stacked-sync rewrite"
else
  ok "chat JS is not the 3.176 rewrite"
fi
if grep -q 'Gespräch beenden' "$TMP/chat_js.body"; then
  fail "chat JS contains Gespräch beenden"
else
  ok "chat JS has no Gespräch beenden control"
fi

admin_code="$(request wpadmin GET "${BASE}/wp-admin/")"
if [ "$admin_code" = "302" ] || [ "$admin_code" = "301" ] || [ "$admin_code" = "200" ]; then
  ok "wp-admin still responds for the login/dashboard flow (HTTP ${admin_code})"
else
  fail "wp-admin unexpected HTTP ${admin_code}"
fi

ajax_code="$(curl -sS -o "$TMP/chat_ajax.body" -w '%{http_code}' \
  -X POST "${BASE}/wp-admin/admin-ajax.php" \
  -d "action=paxdesign_chat&n=${STAMP}")"
if grep -q 'login_required' "$TMP/chat_ajax.body"; then
  ok "guest chat still requires login (HTTP ${ajax_code})"
else
  fail "guest chat gate changed (HTTP ${ajax_code})"
fi

# Public REST index and a non-users route must keep working.
index_code="$(request rest_index GET "${BASE}/wp-json/?n=${STAMP}")"
[ "$index_code" = "200" ] && ok "REST index HTTP 200" || fail "REST index HTTP ${index_code}"

pages_code="$(request pages GET "${BASE}/wp-json/wp/v2/pages?per_page=1&n=${STAMP}")"
[ "$pages_code" = "200" ] && ok "pages REST still works without users listing" || fail "pages REST HTTP ${pages_code}"
if grep -Eq '"author"[[:space:]]*:' "$TMP/pages.body"; then
  fail "pages REST still includes author user IDs"
else
  ok "pages REST no longer includes author user IDs"
fi

if [ "$FAIL" -gt 0 ]; then
  echo "${FAIL} live identity-hardening check(s) failed"
  exit 1
fi

echo "Public identity hardening live verification passed."
