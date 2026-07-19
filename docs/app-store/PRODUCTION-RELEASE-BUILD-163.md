# Production Release — Build 163

**Date:** 2026-07-19  
**WordPress:** 3.153.0 (production confirmed)  
**iOS CFBundleVersion:** 163  
**Branch:** `cursor/production-ready-163-828a` → `main`

## Summary

Build 163 completes the production-readiness pass: full Customer App SVG icon migration, CI/TestFlight workflow hardening, and automated verification across Customer App, Staff App, website, and WordPress backend.

## Changes in Build 163

### Customer App — SVG icon cleanup (complete)

All direct `Image(systemName:)`, `Label(..., systemImage:)`, and `Button(..., systemImage:)` usages in the Customer Portal were replaced with `PAXIcon` / `PAXLabel` across:

- `CustomerPortalChrome.swift` — avatar, quick menu, toolbar
- `CustomerScreens.swift` — login, dashboard, chat composer, profile, service detail
- `CustomerPortalDesign.swift` — category badges, file rows
- `CustomerNotificationsBadgeStore.swift` — notification bell
- `CustomerNotificationPermissionSheet.swift`
- `CustomerAccountFooterViews.swift`
- `CustomerFeatureScreens.swift` — projects, orders, settings, file share
- `CustomerPortfolioViews.swift` — gallery, empty states
- `CustomerChatMediaViews.swift` — bubbles, voice, location
- `CustomerContentViews.swift` — discover, services catalog
- `CustomerNativeContentBlocksView.swift`
- `CustomerHomeWorkspaceSections.swift`
- `CustomerServicesCatalogScreen.swift`
- `CustomerChatSessionRecovery.swift`
- `CustomerAboutContactViews.swift`
- `PAXIcon.swift` — added mappings for `paperclip`, `location`, `bell`, `chevron.forward`, etc.

Staff App SVG migration was already complete in Build 162.

### CI / TestFlight

- `upload-testflight.yml`: IPA build validation uses Python `plistlib` (no apt-get dependency)
- Build 163 triggers paired App Store + TestFlight on same commit (no stale RUN_ID pin)
- Removed pinned `RUN_ID=29705658251` from trigger file

### Build number

- `project.yml`: `CURRENT_PROJECT_VERSION: "163"`

## Automated verification

| Check | Result |
|-------|--------|
| `php tests/customer-platform/run.php` | ✅ 25 modules passed |
| `scripts/verify-customer-platform-3135.sh` | ✅ Public routes, auth gates, staff namespace |
| WordPress production 3.153.0 | ✅ Confirmed (prior deploy run 29704900345) |
| App Store build 162 | ✅ Run 29705658251 |

## CI run reference

| Workflow | Run ID | Result | Notes |
|----------|--------|--------|-------|
| Deploy 3.153.0 | 29704900345 | ✅ | Production backend |
| App Store Build 162 | 29705658251 | ✅ | Prior successful IPA |
| App Store Build 163 | 29707109684 | ✅ | Customer SVG + compile fixes |
| TestFlight Build 163 | 29707109679 | ✅ | IPA validated CFBundleVersion=163, uploaded |
| Verify Internal TestFlight | 29707695723 | ✅ | BUILD=163, IN_BETA_TESTING, all checks PASS |

## Root causes — TestFlight failures (Build 162 era)

1. **Concurrency deadlock** — shared macOS concurrency blocked App Store behind TestFlight (fixed: split workflow, separate concurrency groups)
2. **Stale IPA pin** — TestFlight used Build 161 when Build 162 App Store failed (fixed: build validation + paired triggers)
3. **Runner assignment failures** — jobs with `runner_id: 0`, empty steps (~3s) indicate GitHub Actions runner quota or stuck in_progress jobs blocking the queue
4. **Repository visibility** — repo is now **public**; secrets remain org/repo-scoped; `contents: read` + `actions: read` permissions verified in workflow YAML

## Physical device QA checklist (user)

The agent cannot run on physical devices. Please verify on TestFlight Build 163:

- [ ] Customer glass tab bar scroll behavior and haptics
- [ ] Customer chat: send/receive, attachments, permanent session after relaunch
- [ ] Customer notifications: tap-to-navigate, badge sync
- [ ] Customer orders: list, detail, file download
- [ ] Staff dashboard: toolbar bell (no floating overlay)
- [ ] Staff chat: session recovery, push tap navigation
- [ ] Staff appearance menu: SVG icons (sun/moon/auto)
- [ ] Website chat widget: login gate, inline auth

## Acceptance status

| Requirement | Status |
|-------------|--------|
| Staff SVG icons | ✅ |
| Customer SVG icons | ✅ |
| Staff UI polish | ✅ |
| Orders E2E (backend + decode) | ✅ |
| Notifications (code) | ✅ |
| Permanent chat (web + iOS) | ✅ |
| Website login/chat UX | ✅ |
| Glass tab bar (code) | ✅ |
| WordPress 3.153.0 deploy | ✅ |
| Automated tests | ✅ |
| TestFlight Build 163 upload | ✅ Run 29707109679 |
| Internal TestFlight verified | ✅ Run 29707695723 |
| Physical device QA | user |

## App Store Connect

- **App:** PAXDesign (Apple ID `6790031845`)
- **TestFlight URL:** https://appstoreconnect.apple.com/apps/6790031845/testflight/ios
- **Build 163 ASC ID:** `03bcd61d-5834-4750-bbea-83a163799f90`
- **Delivery UUID:** `03bcd61d-5834-4750-bbea-83a163799f90`
- **Internal state:** `IN_BETA_TESTING` (Ready to install via TestFlight app)
- **Internal tester:** `awjime29@icloud.com` in group "Internal Testing"
- **Upload confirmation:** `UPLOAD SUCCEEDED with no errors` (altool)

## Non-blocking build warnings (Build 163 archive)

The production archive compiled and signed successfully. Remaining warnings are non-critical:
- AppIcon asset catalog: unassigned child in icon set
- Swift unused variable / unused result warnings in CustomerScreens, LiveChatAPI, etc.
- Deprecated API usage (NavigationLink iOS 16, allowBluetooth)

These do not block TestFlight or App Store submission.
