# Unified PAXDesign iOS App — Implementation Plan

## Product decision

There is **no second iOS application**. The existing **PAXDesign Live Chat** app (`at.paxdesign.livechat`, App Store Connect Apple ID **6790031845**) becomes the unified **PAXDesign** customer and team platform. Customer Portal functionality is merged into this app and released as a normal update on the same App Store record.

The separate bundle `at.paxdesign.customerportal` and its CI upload workflows are **retired** and must not be used for public releases unless explicitly approved in writing.

---

## 1. Existing configuration (inspected)

| Item | Value |
|------|-------|
| Bundle ID | `at.paxdesign.livechat` |
| Widget bundle | `at.paxdesign.livechat.widgets` |
| App Store Connect Apple ID | `6790031845` |
| App name (ASC) | PAXDesign Live Chat |
| Version / build (pre-merge) | 2.0.5 / 127 |
| Version / build (this update) | **2.1.0 / 128** |
| iOS deployment target | 16.0 |
| Push entitlement | `aps-environment=production` |
| URL scheme | `paxlivechat` |
| Staff REST namespace | `paxdesign/v1/live-admin/*` |
| Customer REST namespace | `pdx/v1/customer/*` |
| CI upload workflow | `.github/workflows/upload-testflight.yml` |

---

## 2. How Customer Portal code is merged

Customer Portal Swift sources from `ios-customer-portal/` are copied into:

```
ios-live-chat/PAXDesignLiveChat/Features/CustomerPortal/
├── Core/          API client, models, push, keychain, session controller
└── Features/      Dashboard, chat, projects, orders, auth flows, etc.
```

Integration points:

- **`AuthStore`** — extended with `SessionMode` (`staff` | `customer`). Login tries staff API first (`live-admin/me`); on 401/403 falls back to customer profile API (`/customer/profile`).
- **`CustomerSessionController`** — bridges credentials into `CustomerAuthStore` + `CustomerAPIClient` after customer login.
- **`RootView` (app entry)** — routes logged-in users to `AdaptiveShellView` (staff) or `CustomerPortalShellView` (customer).
- **`LoginView`** — unified sign-in plus customer registration / forgot-password sheets.
- **`AppDelegate`** — APNs token routed to staff push registration or customer push registration based on session mode.

No second Xcode target. Same signing, entitlements, and bundle ID.

---

## 3. Live Chat functionality preserved

All existing staff features remain in `AdaptiveShellView` and related modules:

- Dashboard, sessions, live requests, incoming fullscreen alerts
- Team chat, voice messages, media, location
- AI reply assistant, platform hub, admin, widgets, Live Activities
- App lock, onboarding, permissions, device sessions
- Push deep links for live chat and team messages

Staff services (`ChatCoordinator`, `TeamMessagingCoordinator`, `AppServicesController`) start **only** when `sessionMode == .staff`.

---

## 4. Separate customer-app code and workflows retired

| Retired | Action |
|---------|--------|
| `ios-customer-portal/` as shipping target | Kept for reference; not built or uploaded by CI |
| `at.paxdesign.customerportal` bundle | Not used for App Store releases |
| `release-customer-portal-appstore.yml` | Disabled — see workflow header |
| `upload-customer-portal-testflight.yml` | Disabled — see workflow header |
| `audit-customer-portal-asc.yml` | Retained for diagnostics only |

Production releases use the existing Live Chat pipeline:

- `.github/workflows/release-appstore.yml`
- `.github/workflows/upload-testflight.yml`

---

## 5. Proposed app name and metadata

| Field | New value |
|-------|-----------|
| App Store name | **PAXDesign** |
| Home screen display name | **PAXDesign** |
| Subtitle (DE) | Kundenportal & Team-Support |
| Subtitle (EN) | Customer Portal & Team Support |
| Positioning | Unified customer + staff platform (not chat-only) |

Updated in:

- `ios-live-chat/project.yml` (`CFBundleDisplayName`)
- `docs/app-store/metadata.json`
- Login / onboarding strings (`Localizable.xcstrings`)

Screenshots and App Review notes must be refreshed in App Store Connect to show customer tabs (Home, Projects, Requests, Chat) **and** staff capabilities where relevant.

---

## 6. Test plan before TestFlight

1. **Build** — run `release-appstore.yml` on branch; confirm IPA signs with `at.paxdesign.livechat`.
2. **Staff regression** — sign in with WordPress admin Application Password → verify dashboard, chats, team, push, app lock.
3. **Customer flow** — sign in with customer account → verify dashboard, projects, orders, chat (poll + AI stream), notifications, profile.
4. **Registration** — create account, verify email, sign in as new customer.
5. **Session persistence** — kill app, relaunch; correct shell restored for each mode.
6. **Logout / re-login** — switch account types without stale UI.
7. **Push** — customer notification opens correct tab; staff live request still works.
8. **Upload** — `upload-testflight.yml` to existing ASC record `6790031845`; verify on physical iPhone via TestFlight.

---

## Release checklist

- [ ] Bump build in `project.yml` if re-uploading after fixes
- [ ] Update App Store Connect name, subtitle, description, keywords, review notes
- [ ] Replace screenshots with unified-app captures
- [ ] Submit for review as update to existing app (not new app)
- [ ] Do **not** create or upload `at.paxdesign.customerportal` unless explicitly approved
