# Customer Platform — Completion Audit (v3.135.0)

Audit date: 2026-07-17. Branch: `cursor/customer-platform-828a`.

Status legend:
- **C+T** — Completed and fully tested
- **I−T** — Implemented but not fully tested
- **Partial** — Partially implemented
- **None** — Not implemented
- **Deploy** — Deploy-dependent (requires staging/production + credentials)

---

## iOS Customer Application

| Feature | Status | Evidence / gap |
|---------|--------|----------------|
| iOS dashboard with real customer data | Partial | `CustomerDashboardView` calls `/customer/dashboard`; summary rows not navigable; `unread_count` ignored |
| Projects list screen | None → Partial* | *In progress this audit cycle |
| Project detail (milestones, progress, files, notes, assignees, activity) | None | Backend returns sub-resources; no iOS UI |
| Orders / service requests list | None → Partial* | *In progress |
| Order creation flow | None → Partial* | Backend `POST /orders`; no iOS form |
| Order detail screen | None → Partial* | Backend `GET /orders/{id}` |
| Services list | I−T | Searchable list; CI static test only |
| Service detail + request flow | Partial | Detail + “Request service” in progress |
| Customer chat UI | I−T | Text + AI SSE + human POST; no media |
| Conversation history | Partial | Full poll reload; no incremental `since` loop |
| AI replies (authenticated REST) | I−T | `/customer/chat/stream`; not device-tested |
| Human support messaging | I−T | `/customer/chat/messages` when handler is admin/live |
| Chat images / voice / files / location | None | No customer REST upload; staff app has media |
| Notifications center | None → Partial* | Backend ready; iOS inbox in progress |
| Push notification registration & delivery | None | No APNs in customer app; `/push/register` unused |
| News list & article detail | Partial | Dashboard embed only; dedicated screens in progress |
| Profile | I−T | Read-only; login validation only |
| Settings (notification prefs) | None → Partial* | Backend `/settings`; iOS in progress |
| Email verification UX | Partial | Shows verified flag; no resend/verify flow in app |
| Logout | I−T | Clears memory; no Keychain until this cycle |
| Account deletion | None → Partial* | Backend `POST /account/delete` |
| Offline states | None | No reachability monitor |
| Retry behavior | Partial | Pull-to-refresh on dashboard/chat; no global retry |
| Loading states | Partial | Per-screen spinners; chat initial load weak |
| Error handling | Partial | Localized errors; no structured error codes |
| Keychain session persistence | None → Partial* | In progress |
| Xcode project / build | None → Partial* | `project.yml` scaffold in progress (no `.xcodeproj` in repo before) |
| Physical device testing | Deploy | Requires Mac + device |
| TestFlight readiness | None | No CI workflow for customer app IPA |
| App Store readiness | None | No metadata, screenshots, or submit pipeline |

---

## WordPress Backend (paxdesign-booking customer module)

| Feature | Status | Notes |
|---------|--------|-------|
| REST `/customer/dashboard` | I−T | Static + CI; not production-tested |
| REST `/customer/profile` GET/PATCH | I−T | |
| REST `/customer/settings` | I−T | Notification prefs only |
| REST `/customer/account/delete` | I−T | Password required |
| REST `/customer/projects` list + detail | I−T | Sub-resources read-only from DB |
| Project milestones write path | None | Schema only |
| Project files write + download URLs | None | `file_path` stored, no signed URL |
| Project notes write path | None | Schema only |
| Project assignees write path | None | Schema only |
| Project activity timeline (read) | I−T | On detail endpoint |
| REST `/customer/orders` list/create/detail | I−T | Booking auto-link |
| Order status updates (admin/staff) | None | |
| REST `/customer/services` public catalog | I−T | |
| REST `/customer/news` | I−T | List only; slug detail route added this cycle |
| REST `/customer/notifications` | I−T | Mark read supported |
| Push register + APNs send | Partial | Register stores meta; chat push not wired to customers |
| Chat poll + human send | I−T | |
| Chat AI SSE stream | I−T | CI static; not live OpenAI tested in agent env |
| Chat images/voice/files/location (customer REST) | None | |
| Guest chat claim | I−T | Device token verification implemented |
| Login-required persistent chat (option) | I−T | Opt-in via `paxdesign_customer_require_login_for_chat` |
| PDX_Auth integration | I−T | No parallel auth store |
| Suspended account blocking | I−T | `PDX_Customers::is_login_allowed` |
| Rate limiting | I−T | `PDX_RateLimit` per route |
| Ownership / IDOR checks | I−T | SQL scoping + chat bridge |
| No PayPal/billing in customer REST | C+T | Static test verifies no commerce routes |

---

## WordPress Admin UI

| Feature | Status | Notes |
|---------|--------|-------|
| Customer Portal menu | I−T | Booking → Customer Portal |
| Projects list + create | Partial | No edit, milestones, files, notes, assignees |
| Orders management | None → Partial* | Tab in progress |
| News draft + publish | Partial | No edit/delete/image/audience UI |
| Services sync | I−T | Sync from booking catalog |
| User management | None | Uses core WP Users + toolbar Customers admin |
| Assignments | None | |
| Files management | None | |
| Notifications management | None | |

---

## Website Customer Portal (pdx-auth.js)

| Feature | Status | Notes |
|---------|--------|-------|
| Customer Portal overlay | I−T | Dashboard + chat panel |
| AI streaming via REST | I−T | fetch SSE to `/customer/chat/stream` |
| Projects/orders/news navigation | Partial | Summary only, no drill-down |
| Guest claim after login | Partial | Backend ready; no auto-claim UI hook |

---

## Regression / Staff Systems

| Feature | Status | Notes |
|---------|--------|-------|
| Staff live chat (`/paxdesign/v1/live-admin/*`) | Deploy | Verify script included; not post-deploy tested |
| Team messaging | Deploy | No code changes intended; needs regression on deploy |
| Toolbar authentication | I−T | Full plugin in repo; integrated |
| iOS staff/admin app | Deploy | Separate `ios-live-chat`; CI sideload on PR |

---

## Deployment & Operations

| Feature | Status | Notes |
|---------|--------|-------|
| DB + wp-content backup script | I−T | `scripts/backup-wp-production.sh` |
| Deploy workflow (both plugins) | I−T | `deploy-customer-platform-3135.yml`; not run (not on main) |
| Staging environment | None | No staging URL in repo; deploy targets production SSH |
| Production migration test | Deploy | |
| Post-deploy verification script | I−T | `verify-customer-platform-3135.sh` |
| Production deploy | **Blocked** | Per instruction: do not deploy until audit complete |

---

## Automated Test Coverage

| Suite | Status | Last result |
|-------|--------|-------------|
| `tests/customer-platform/run.php` | C+T (static) | PASS locally + CI |
| `tests/messaging/run.php` | C+T (CI) | PASS on PR #105 |
| Auth E2E (register/verify/suspend) | Deploy | |
| Chat claim E2E | Deploy | |
| iOS unit/UI tests | None | No test target |
| Xcode build | None | No Mac runner for customer app |

---

*Partial* items marked “in progress this audit cycle” are being extended in the same PR branch after this document is written.
