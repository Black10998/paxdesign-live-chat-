# AGENTS.md

## Cursor Cloud specific instructions

This repo is a **WordPress plugin/theme suite (PHP)** plus native iOS apps. The startup
update script (installed via the Cursor environment) does **not** install system packages
or start services — PHP 8.3, MariaDB 10.11, `zip`, `wp-cli`, a local WordPress install
(`~/wp`), and `puppeteer-core` (`~/pptr`) are baked into the VM snapshot. This section
covers the non-obvious runtime caveats.

### Components / scope

| Component | Path | Notes |
|-----------|------|-------|
| `paxdesign-booking` (core product) | `paxdesign-booking/` | WordPress plugin: booking, live chat, REST API (`paxdesign/v1`), customer platform |
| `paxdesign-toolbar` | `paxdesign-toolbar/` | Companion "PaxDesign Utility Dock" plugin |
| `navein` theme | `navein/` | **Incomplete theme fragment** — has no `index.php`/`templates/index.html`, so WordPress will NOT list or activate it standalone. Not independently runnable. |
| iOS apps | `paxdesign-booking/ios-live-chat/`, `.../ios-customer-portal/` | SwiftUI — require **macOS + Xcode**. Cannot be built on this Linux VM (out of scope here). |

### Start MariaDB (required for tests and the WordPress runtime)
`service mariadb start` / `invoke-rc.d` do **not** work in this VM. Start the daemon directly:
```bash
sudo mariadbd-safe >/tmp/mariadb.log 2>&1 &
sleep 5 && sudo mysqladmin ping   # -> "mysqld is alive"
```
The unix socket is `/run/mysqld/mysqld.sock`. The messaging test's `tests/messaging/run.sh`
assumes `sudo service mariadb start`; on this VM start MariaDB with the command above first,
then run the test via `php` directly (see below).

### Lint (mirrors CI `.github/workflows/messaging-reliability.yml`)
```bash
rg --files -g '*.php' paxdesign-booking tests/messaging | xargs -n1 php -l   # PHP syntax
node --check paxdesign-booking/assets/js/chat-script.js
node --check paxdesign-booking/assets/js/chat-live-admin.js
```

### Tests
```bash
php tests/customer-platform/run.php   # static checks, no DB needed
# messaging reliability (needs MariaDB running + a test DB/user):
sudo mysql -e "CREATE DATABASE IF NOT EXISTS pax_chat_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
  CREATE USER IF NOT EXISTS 'pax_test'@'localhost' IDENTIFIED BY '';
  GRANT ALL PRIVILEGES ON pax_chat_test.* TO 'pax_test'@'localhost'; FLUSH PRIVILEGES;"
PAX_TEST_DB_USER=pax_test PAX_TEST_DB_PASS= php tests/messaging/run.php   # expects {"status":"ok",...}
```
The messaging test spawns concurrent PHP worker processes against real MariaDB (exercises the
message store / outbox / reconnect replay) — it is genuine backend end-to-end coverage.

### Build
```bash
./scripts/build-release.sh              # -> dist/paxdesign-booking-v<version>.zip (needs `zip`)
bash scripts/validate-release-contract.sh   # validates the WP auto-update release contract
```
iOS IPA build is skipped automatically unless `BUILD_IOS=1` and `xcodebuild` are present (macOS only).

### Run the app (local WordPress dev site)
A ready WordPress install lives in `~/wp` (admin `admin` / `admin123`), DB `wordpress`
(user `wpuser`/`wppass` over the MariaDB socket). The repo plugins are **symlinked** into
`~/wp/wp-content/plugins/` so code edits are picked up live. Start the dev server:
```bash
cd ~/wp && wp server --host=0.0.0.0 --port=8088
```
- Front-end: `http://localhost:8088/` — the floating booking/live-chat widget (`.paxdesign-booking-button`) renders on every page regardless of the active theme.
- Admin bookings dashboard: `http://localhost:8088/wp-admin/admin.php?page=paxdesign-booking`.
- Pretty permalinks are off by default; hit the REST API via `http://localhost:8088/?rest_route=/paxdesign/v1/...`.

### Gotchas
- The front-end localizes **two** nonces: `paxdesignBooking.nonce` (booking) and `paxdesignChat.nonce` (chat). Use the correct one per endpoint — a mismatched nonce makes `admin-ajax.php` return `-1`.
- Booking submit endpoint: `POST /wp-admin/admin-ajax.php` with `action=paxdesign_submit_booking`, the `paxdesignBooking` nonce, and required fields `member` (e.g. `ahmad`), `date`, `time`, `name`, `email`.
- Plugin activation prints a couple of harmless `Table ... doesn't exist` warnings on the very first activation (a legacy-session purge runs before table creation in that path); tables are created successfully.
- No `composer.json` / `package.json` at repo root — there are no PHP/JS package dependencies to install; Node is only used for `node --check` syntax validation.
- GUI/computer-use automation may be unavailable; drive Chrome headless via `puppeteer-core` in `~/pptr` (`executablePath: /usr/local/bin/google-chrome`) for screenshots.
