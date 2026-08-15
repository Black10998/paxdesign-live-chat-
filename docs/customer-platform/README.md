# PAXDesign Customer Platform

Implementation lives in `paxdesign-booking/includes/customer/` and `paxdesign-booking/includes/auth/`, using the `pdx/v1` REST namespace.

## Authentication

- **Website:** WordPress cookie session + `X-WP-Nonce` via `paxdesign-booking` `/pdx/v1/auth/*` routes (`PAXdesign_Auth_Native`).
- **Mobile:** WordPress Application Password (HTTP Basic Auth) on `/pdx/v1/customer/*` — same `wp_users` records.
- **Apple Sign-In:** `/pdx/v1/auth/apple/*` web and mobile flows.
- **Role:** `pdx_customer` (booking-native customer role).
- **Account status:** Suspended/pending accounts blocked via `PAXdesign_Customers::is_login_allowed()`.
- **Rate limiting:** WordPress transients (toolbar `PDX_RateLimit` optional if present on server).

## Toolbar (removed)

`paxdesign-toolbar` is **not** part of this repository or deployment. Auth UI runs standalone in `assets/customer-auth/js/pax-auth.js` with no `PDXDock` dependency.

## Guest chat migration

`POST /pdx/v1/customer/chat/claim` requires a verified device token match before linking a legacy guest `session_id` to the authenticated user.

## Data model

Custom tables (prefix `wp_paxdesign_customer_*`):

- projects, project_assignees, project_milestones, project_notes, project_files, project_activity
- orders, order_notes, order_files, order_activity
- notifications, news, service_categories, services
- chat_sessions, guest_claims

Chat logs gain `wp_user_id` for account-linked conversations.

## REST routes

| Method | Path |
|--------|------|
| GET | `/customer/dashboard` |
| GET/POST | `/customer/profile` |
| GET/POST | `/customer/settings` |
| POST | `/customer/account/delete` |
| GET | `/customer/projects` |
| GET | `/customer/projects/{id}` |
| GET/POST | `/customer/orders` |
| GET | `/customer/orders/{id}` |
| GET | `/customer/services` |
| GET | `/customer/services/{slug}` |
| GET | `/customer/news` |
| GET/PATCH | `/customer/notifications` |
| GET | `/customer/chat/session` |
| GET | `/customer/chat/conversations` |
| POST | `/customer/chat/claim` |

## Deploy

- Deploy **only** `paxdesign-booking` plugin ZIP from GitHub Releases.
- Theme shell assets (mobile nav, referenzen wrapper, cybercrime page) live under `navein/` and deploy via theme rsync workflows.
- Run `scripts/verify-no-toolbar.sh` before every release.
