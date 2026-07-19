# Production Release — Build 165

**Date:** 2026-07-19  
**WordPress:** 3.156.0  
**iOS MARKETING_VERSION:** 2.1.0  
**iOS CFBundleVersion:** 165  
**Branch:** `cursor/final-release-build-165-828a` → `main`

## Summary

Build 165 is the consolidated production release merging PR #136 (Team Chat rate limits), PR #137 (iOS stability / appearance-switch fixes), stashed pre-release improvements, WordPress chat-login dialog changes, PHP fatal/DB/JSON fixes, notification recovery, widget improvements, and all chat reliability work into a single branch.

## Changes in Build 165

### WordPress — chat reliability & server errors

- **PHP fatal fix:** `PAXdesign_Chat_Live::clear_typing_indicator()` is now public; customer chat bridge no longer calls private `clear_typing()` (fixes repeated fatal at `class-paxdesign-customer-chat-bridge.php:453`).
- **JSON safety:** New `PAXdesign_Ajax_JSON_Guard` returns valid JSON on fatal PHP errors for all `paxdesign_*` admin-ajax actions.
- **DB sync:** `PAXdesign_DB` drains mysqli results at `PHP_INT_MAX` shutdown, after Action Scheduler runs, and on REST dispatch; team messaging locks use `PAXdesign_DB::acquire_named_lock()`.
- **Rate limits:** Team Chat send guard 120/min; live chat poll limits tuned for normal messaging.
- **html_entity_decode(null):** Normalized null inputs in portfolio and toolbar URL analyzer / browser automation.
- **Chat widget:** Login dialog CSS (top-right X, centered no-scroll layout); `safeJson()` guards; skip name prompt when logged in; `resolvedCustomerName()` helper.

### iOS — stability, notifications, widgets

- **Appearance crash:** Removed `themeRevision` from shell `.id()` (Staff + Customer).
- **History recovery:** Bounded `verifyHistoryIntegrity` ↔ `reloadFullHistory` loop (max 3 attempts, 3s cooldown).
- **Team Chat rate limit:** `NetworkCircuitBreaker` exempts team polls/sends; transient 429 handling; optimistic messages retained on rate limit.
- **Notification recovery:** `PermissionCoordinator.syncPushRegistrationIfAuthorized()`; foreground re-register on customer home/chat/settings.
- **Customer API:** HTML/fatal-error decode guard via `decodeJSON()`.
- **Home Screen widget (complete redesign):**
  - Professional adaptive layout for Small, Medium, Large (Light/Dark/System)
  - No duplicate in-widget **PAXDesign** label; brand mark uses official accent (#C2FF00 / system blue)
  - All four metrics on every size (2×2 grid on small); LIVE badge when queue active
  - Large widget: top live request + next event insight rows
  - Tappable metric tiles deep-link to Live/Chats/Dashboard via `paxlivechat://`
  - Live refresh: `WidgetCenter.reloadTimelines` on sync, push, unread changes, background flush
  - Signed-out placeholder state; platform sync ordering fixed for Tasks/Events
- **Auth & onboarding visuals:**
  - `PAXAuthHeroView` + `PAXOnboardingIllustration` premium components
  - Staff login: animated PAXdesign logo hero
  - Customer register/forgot/verify/login: glass `PAXField` forms with branded icon heroes
  - Onboarding carousel + post-login setup: accent-ring illustration frames
  - Login → dashboard: scale/opacity shell transition
- **Removed fatalError:** `ConversationHistoryStore` / `LiveChatModels` use empty snapshot fallback

## Automated verification

| Check | Result |
|-------|--------|
| `php tests/customer-platform/run.php` | ✅ 25 modules passed |
| PHP syntax (`class-paxdesign-ajax-json-guard.php`) | ✅ |
| Consolidated branch includes #136 + #137 | ✅ |

## CI run reference

| Workflow | Trigger | Notes |
|----------|---------|-------|
| Deploy customer platform 3.156.0 | push `main` + plugin changes | WordPress production |
| App Store Build 165 | `.github/triggers/appstore-build` | Signed IPA |
| TestFlight Build 165 | `.github/triggers/testflight-upload` | Upload + validate CFBundleVersion=165 |
| Verify Internal TestFlight | `.github/triggers/testflight-verify-internal` | Post-upload checks |

## Visual demos

See [BUILD-165-VISUAL-DEMOS.md](./BUILD-165-VISUAL-DEMOS.md) for widget, auth, and onboarding mockups.

- [ ] Team Chat: open, send, typing, attachments, read receipts, close/reopen
- [ ] Customer Chat: send/receive, attachments, login gate, permanent session
- [ ] Live Chat widget: login dialog, send, typing, retries
- [ ] AI Chat: stream, error recovery
- [ ] Appearance switch Light/Dark/System (no crash/freeze)
- [ ] Push notifications: tap-to-navigate, badge sync after foreground
- [ ] WordPress debug log clean during above tests

## Supersedes

- PR #136 (`cursor/team-chat-rate-limit-828a`)
- PR #137 (`cursor/app-stability-audit-828a`)
- Stashed pre-release login-dialog work on `cursor/pre-release-improvements-828a`
