# PAXDesign Customer Platform

Implementation lives in `paxdesign-booking/includes/customer/` and extends the existing `pdx/v1` REST namespace shared with `paxdesign-toolbar`.

## Authentication

- **Website:** WordPress cookie session + `X-WP-Nonce` via `paxdesign-toolbar` `/auth/*` routes (`PDX_Auth`).
- **Mobile:** WordPress Application Password (HTTP Basic Auth) on `/pdx/v1/customer/*` — same `wp_users` records, no parallel auth store.
- **Role:** `pdx_customer` (from toolbar `PDX_Auth::CUSTOMER_ROLE`), not a separate `pax_customer` role.
- **Account status:** Suspended/pending accounts blocked via `PDX_Customers::is_login_allowed()`.
- **Rate limiting:** `PDX_RateLimit` when toolbar is active.

## Toolbar integration

The full `paxdesign-toolbar` plugin (v9.1.0+) is included in this repository. Customer platform auth delegates to `PDX_Auth` and fires `pdx_user_logged_in` on toolbar login for chat session linking.

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
| GET/POST | `/customer/chat/messages` |
| POST | `/customer/chat/stream` (SSE AI responses) |
| POST | `/customer/push/register` |

## WordPress admin

**Booking System → Customer Portal** — manage projects, news, and sync the services catalog.

## No purchases

Customer portal endpoints and the iOS customer app exclude PayPal, billing checkout, and In-App Purchases.

## Deploy notes

- Deploy both `paxdesign-toolbar` and `paxdesign-booking` (v3.134.0+).
- Run DB migration on activation or first load (`PAXdesign_Customer_DB::install()`).
- Back up production database before first deploy.
