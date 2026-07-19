# Production Release — Build 162 / WordPress 3.153.0

**Release date:** 2026-07-19  
**Policy:** Single final TestFlight build after full-stack completion  
**iOS:** 2.1.0 (162)  
**WordPress plugin:** 3.153.0  

---

## Root-cause analysis (issues fixed)

| # | Area | Root cause | Fix |
|---|------|------------|-----|
| 1 | Website chat | Client generated temporary `pax_*` session IDs for guests | Auth gate blocks ID creation; server resolves to `pax_u{id}_*` primary session |
| 2 | Website login UX | Full-page `#pdx-auth-overlay` caused page jump and scroll lock | Inline `PDXAuth.mountInlineAuth()` inside chat widget |
| 3 | Staff push navigation | `shouldNavigate: false` on all notification taps | Navigate to session on tap; order pushes parsed without `session_id` |
| 4 | Customer push (background) | Background handler auto-set deep link without user tap | Background only refreshes badge; navigation on explicit tap only |
| 5 | Customer badge drift | Local `incrementUnread()` on tap desynced from server | Server-authoritative refresh via `scheduleRefresh()` |
| 6 | Cross-device badges | No silent push after mark-read | `push_badge_sync()` silent APNs after notification read |
| 7 | Orders decode | Strict `Int` decode on order summaries/details | Lenient `CustomerPortalDecode` on all order models |
| 8 | Staff orders | `files` array dropped; staff file IDs as strings | Added `FileItem` decode; PHP normalizes file row IDs |
| 9 | Staff chat loops | Synthetic push sessions + unbounded history recovery | Removed placeholder sessions; capped recovery; dedup list |
| 10 | Tab bar lag | `Task { @MainActor }` deferred scroll KVO one frame | Direct main-thread scroll ingestion; glass tab bar on customer app |
| 11 | Staff dashboard UI | Floating notification bell in `safeAreaInset` | Bell integrated into dashboard toolbar |
| 12 | Customer chat iOS | Navigation passed stale `initialSessionID` without server verify | Always `fetchChatSession()` first; reset `streamSince` on renew |
| 13 | Deep link `/account/devices` | No routing case | Added `.devices` destination → `CustomerDeviceManagementView` |
| 14 | SF Symbol placeholders | Direct `Image(systemName:)` in staff banners/badges | Replaced with `PAXIcon` SVG; `PAXContentUnavailableView` uses SVG |
| 15 | App Store build 162 | `CustomerPushService.handleForegroundNotification` passed optional `api` to non-optional `scheduleRefresh(api:)` | Added `guard let api else { return }` before badge refresh |
| 16 | CI TestFlight deadlock | Shared macOS concurrency group let TestFlight hold lock while waiting for App Store | Removed shared concurrency; TestFlight falls back to latest main IPA after 3m with build validation |
| 17 | CI App Store log noise | Verbose xcodebuild output caused early runner termination on some attempts | `-quiet`, log tee, 3-attempt retry with DerivedData cleanup |

---

## Files changed (this release branch)

### WordPress (`paxdesign-booking/`)
- `paxdesign-booking.php` — v3.153.0
- `assets/js/chat-script.js` — auth-only sessions, inline login
- `assets/css/booking-styles.css` — premium auth pane
- `assets/customer-auth/js/pax-auth.js` — inline auth mount API
- `templates/booking-widget.php` — single-container auth UI
- `includes/class-paxdesign-chat.php` — block guest AI chat
- `includes/class-paxdesign-chat-live.php` — session resolve on all handlers
- `includes/customer/class-paxdesign-customer-notifications.php` — badge sync push
- `includes/customer/class-paxdesign-customer-orders.php` — staff file ID normalization

### iOS (`ios-live-chat/`)
- `project.yml` — build 162
- `Features/CustomerPortal/Features/RootView.swift` — glass tab bar shell
- `Features/CustomerPortal/Features/CustomerScreens.swift` — server session priming
- `Features/CustomerPortal/Features/CustomerNavigationCoordinator.swift` — devices deep link
- `Features/CustomerPortal/Core/CustomerModels.swift` — lenient order decode
- `Features/CustomerPortal/Core/CustomerPushService.swift` — badge refresh on tap
- `Features/Orders/StaffOrdersCoordinator.swift` — files + lenient decode
- `Core/Services/PushService.swift` — order push parse, customer cold start
- `Core/Services/PushDeepLinkRouter.swift` — navigate on tap
- `Core/Services/ChatCoordinator.swift` — dedup, loading watchdog, push navigate
- `Core/Services/AppServicesController.swift` — staff notification categories
- `Core/Design/PAXContentUnavailableView.swift` — SVG empty states
- `Features/Shell/UiverseMenuBarView.swift` — scroll tracking
- `Features/Shell/UiverseMenuIcons.swift` — customer tab glyphs
- `Features/Live/StaffCustomerMessageBanner.swift` — SVG icon
- `Features/Team/TeamVerifiedBadge.swift` — SVG icon
- `Features/Dashboard/DashboardView.swift` — toolbar bell placement
- `App/PAXDesignLiveChatApp.swift` — customer/staff push routing

### CI / docs
- `.github/workflows/deploy-customer-platform-3135.yml` — plugin path trigger, v3.153.0
- `docs/app-store/PRODUCTION-RELEASE-BUILD-162.md` (this file)

---

## QA checklist

### Customer chat (website + app)
- [x] Guest cannot create chat sessions when login gate enabled (static + code review)
- [x] Logged-in users resolve to permanent `pax_u*` session (server bridge)
- [x] Inline login inside widget (no full-page overlay)
- [x] iOS always fetches server session on chat open
- [ ] Physical device: login → existing messages load → send message (requires device)

### Notifications
- [x] Staff tap opens correct session
- [x] Customer order push routes to orders
- [x] Background push does not auto-navigate customer app
- [x] Badge sync silent push after mark-read
- [ ] Physical device: cross-device badge sync (requires two devices)

### Orders
- [x] Lenient decode on customer + staff order models
- [x] Staff files normalized in PHP
- [ ] Authenticated REST smoke on production post-deploy

### Staff chat
- [x] Session list deduplication
- [x] History recovery capped at 3 attempts
- [x] 12s loading watchdog
- [x] No synthetic push placeholder sessions

### UI
- [x] Customer glass tab bar with direct scroll tracking
- [x] Staff dashboard toolbar (bell + search + appearance)
- [x] SVG icons in banners, badges, empty states

---

## Deployment

WordPress production deploy: **confirmed** via workflow run **29704900345** (`Deploy customer platform 3.153.0`).

| Check | Result |
|-------|--------|
| Deploy workflow | success (test + deploy jobs) |
| Production plugin version | **3.153.0** (asset URLs on https://paxdesign.at) |
| Customer platform verification | ALL CHECKS PASSED |
| Unauthenticated chat stream | HTTP 401 (auth gate active) |
| Public services route | HTTP 200 |

---

## TestFlight

**Expected build number:** 162 (CFBundleVersion in `project.yml`)

| Workflow | Run ID | Status | Notes |
|----------|--------|--------|-------|
| Deploy customer platform 3.153.0 | **29704900345** | success | Production at 3.153.0 |
| Validate Release Contract | 29704900369 | success | Pre-deploy gate |
| Messaging Reliability | 29704900354 | success | Chat reliability tests |
| App Store iOS Build (compile fix) | **29705658251** | **success** | Build **162** IPA archived and exported |
| App Store iOS Build (earlier attempts) | 29704900342, 29705001536, 29705546001 | failure | Runner flake / compile error (fixed in d3d9d2e) |
| Upload TestFlight Build | **29705750836** | in_progress | Waiting for same-commit IPA resolution (trigger-only commit) |
| Upload TestFlight Build (retry) | 8510bd8 push | pending | Uses fallback to run 29705658251 after 3m |

**Final iOS build number:** 162 (CFBundleVersion verified in App Store run 29705658251)

---

## Post-release verification

After CI completes, confirm:
1. Deploy workflow green + verify log shows 3.153.0 — **done**
2. App Store Connect shows build 162 processing — pending CI
3. Production site chat requires login — **verified** (401 on unauthenticated stream)
4. Authenticated chat returns same session ID on web + API — **verified** in deploy job
