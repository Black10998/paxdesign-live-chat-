# Removed: paxdesign-toolbar (final decision)

**Date:** 2026-08-16  
**Status:** Permanently removed from the PAXdesign project baseline.

## Why it kept reappearing

Investigation found **no automatic installer inside `paxdesign.booking`**. The toolbar was reintroduced by:

1. **Separate repository** at `C:\Users\43681\.ssh\paxdesign-toolbar` — agents and manual deploys used this repo independently of `paxdesign-booking`.
2. **Optional JS fallbacks** in `pax-auth.js` that called `window.PDXDock` when the account page URL was missing (legacy Utility Dock integration).
3. **Shared CSS tokens** (`#pdx-dock`, `#pdx-root`, `#pdx-panel`) copied from the dock design system into Customer Platform styles.
4. **Manual WordPress uploads** of `paxdesign-toolbar-*.zip` releases (GitHub repo `Black10998/paxdesign-toolbar`) — not triggered by booking plugin CI.

This booking repository’s GitHub Actions **only** build and release `paxdesign-booking-v*.zip`. They never install toolbar.

## What was removed from this repo

- All `PDXDock` JavaScript calls → replaced with `/account/` navigation and standalone toast notifications
- Toolbar-only CSS (`#pdx-dock`, `#pdx-root`, `#pdx-backdrop` dock chrome)
- Guard script: `scripts/verify-no-toolbar.sh` (also `.ps1`) — fails CI if toolbar references return

## What was **not** removed (Customer Platform stays)

These remain in **`paxdesign-booking`**:

- `assets/customer-auth/` — login, account dashboard, portal
- REST namespace `pdx/v1` — customer dashboard, chat, cybercrime/CCS
- Live Chat, case sync, attachments, and page context in `chat-script.js`

## WordPress cleanup (run once on production)

### 1. Deactivate and delete plugin files

**WP Admin:** Plugins → deactivate **PaxDesign Utility Dock** → Delete

Or **WP-CLI** (SSH):

```bash
wp plugin deactivate paxdesign-toolbar/paxdesign-toolbar.php --quiet || true
wp plugin delete paxdesign-toolbar --quiet || true
# Remove versioned duplicate folders if Hostinger created them:
rm -rf wp-content/plugins/paxdesign-toolbar-* 
```

### 2. Remove from active plugins list

```bash
wp option patch delete active_plugins paxdesign-toolbar/paxdesign-toolbar.php 2>/dev/null || true
```

### 3. Toolbar-specific options (safe to delete)

These options belong to the **Utility Dock plugin only**. Do **not** delete `paxdesign_booking_*` or customer chat tables.

```sql
DELETE FROM wp_options WHERE option_name IN (
  'pdx_settings',
  'pdx_updater_last_checked',
  'pdx_updater_state',
  'pdx_github_release',
  'pdx_config_version',
  'pdx_event_log',
  'pdx_briefs',
  'pdx_stripe_secret_key'
);
DELETE FROM wp_options WHERE option_name LIKE 'pdx_setup%';
DELETE FROM wp_options WHERE option_name LIKE 'pdx_webhook%';
DELETE FROM wp_options WHERE option_name LIKE 'pdx_recovery%';
DELETE FROM wp_options WHERE option_name LIKE 'pdx_cf_%';
```

Or via WP-CLI:

```bash
wp option delete pdx_settings --quiet || true
wp option delete pdx_updater_last_checked --quiet || true
wp option delete pdx_updater_state --quiet || true
wp option delete pdx_github_release --quiet || true
```

### 4. Scrub plugin update transients

```bash
wp transient delete paxdesign_booking_update_info 2>/dev/null || true
wp cache flush
```

After cleanup, verify the live site loads **no** scripts from:

`/wp-content/plugins/paxdesign-toolbar/`

### 5. Optional PHP runner

Upload and run once via WP-CLI:

```bash
wp eval-file scripts/wp-uninstall-toolbar.php
```

## Prevent recurrence

- CI runs `scripts/verify-no-toolbar.sh` on every plugin release workflow
- Cursor rule: `.cursor/rules/no-paxdesign-toolbar.mdc`
- Do not clone or deploy from `.ssh/paxdesign-toolbar` for this site

## Verification checklist

- [ ] `paxdesign-toolbar` folder absent from `wp-content/plugins/`
- [ ] No `dock.js` or `paxdesign-toolbar.php` in page source
- [ ] `/account/` and Customer Platform work via `paxdesign-booking` only
- [ ] Chat, CCS, cybercrime flows work without `PDXDock`
- [ ] `bash scripts/verify-no-toolbar.sh` passes in this repo
