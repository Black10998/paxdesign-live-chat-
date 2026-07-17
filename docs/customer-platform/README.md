# PAXDesign Customer Platform

Implementation lives primarily in `paxdesign-booking/includes/customer/` and extends the existing `pdx/v1` REST namespace used by `paxdesign-toolbar`.

## Authentication

- **Website:** WordPress cookie session + `X-WP-Nonce` via existing `paxdesign-toolbar` `/auth/*` routes.
- **Mobile:** WordPress Application Password (HTTP Basic Auth) on `/pdx/v1/customer/*` — same `wp_users` records, no parallel auth store.
- **Toolbar delegation:** When toolbar helpers exist (`pdx_auth_*`), customer auth delegates to them.

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
| GET | `/customer/chat/messages` |
| POST | `/customer/push/register` |

## No purchases

Customer portal endpoints and the iOS customer app exclude PayPal, billing checkout, and In-App Purchases.

## Blockers

- Full `paxdesign-toolbar` PHP source is required in this repository for deeper auth hook integration and admin publishing UI for news/services.
- WordPress admin/SSH access is needed for production backup before migration deploy.
