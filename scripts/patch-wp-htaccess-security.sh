#!/usr/bin/env bash
# Insert or replace the PAXDesign security FilesMatch block in WordPress .htaccess.
# Intended to run on the production host with WP_PATH set. Idempotent.
set -euo pipefail

WP_PATH="${WP_PATH:?WP_PATH is required}"
HTACCESS="${WP_PATH%/}/.htaccess"

python3 - "$HTACCESS" << 'PY'
import os
import re
import sys

path = sys.argv[1]
block = """# BEGIN PAXDesign security
<FilesMatch "^(readme\\.html|readme\\.txt|license\\.txt|llms\\.txt)$">
  <IfModule mod_authz_core.c>
    Require all denied
  </IfModule>
  <IfModule !mod_authz_core.c>
    Order allow,deny
    Deny from all
  </IfModule>
</FilesMatch>
<IfModule mod_headers.c>
  Header always unset X-Powered-By
  SetEnvIf Origin "^https://(www\\.)?paxdesign\\.at$" PAX_CORS_ORIGIN=$0
  Header always unset Access-Control-Allow-Origin
  Header always unset Access-Control-Allow-Credentials
  Header always set Access-Control-Allow-Origin "%{PAX_CORS_ORIGIN}e" env=PAX_CORS_ORIGIN
  Header always set Access-Control-Allow-Credentials "true" env=PAX_CORS_ORIGIN
  Header always append Vary Origin
</IfModule>
# END PAXDesign security
"""

if not os.path.isfile(path):
    with open(path, "w", encoding="utf-8") as handle:
        handle.write(block)
    print("created .htaccess with PAXDesign security block")
    sys.exit(0)

with open(path, "r", encoding="utf-8", errors="replace") as handle:
    text = handle.read()

start = "# BEGIN PAXDesign security"
end = "# END PAXDesign security"
pattern = re.compile(re.escape(start) + r".*?" + re.escape(end), re.S)
if pattern.search(text):
    text = pattern.sub(block.strip(), text, count=1)
else:
    text = text.rstrip() + "\n\n" + block

tmp = path + ".pax-security.tmp"
with open(tmp, "w", encoding="utf-8") as handle:
    handle.write(text if text.endswith("\n") else text + "\n")
os.replace(tmp, path)
print("updated PAXDesign security block in .htaccess")
PY
