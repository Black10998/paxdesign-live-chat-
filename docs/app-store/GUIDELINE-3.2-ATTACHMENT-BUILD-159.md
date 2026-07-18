# PAXDesign — Evolution Since Build 127
## Supporting Document for App Review (Guideline 3.2)
**Version submitted:** 2.1.0 (Build 159)  
**Prior reviewed version:** 2.0.5 (Build 127)  
**Bundle ID:** at.paxdesign.livechat  
**App name:** PAXDesign  

---

## Purpose of This Document

This attachment describes how **PAXDesign** has evolved since Build 127 and why the current submission (Build 159) is designed and positioned for **public App Store distribution**. It supplements the concise reply submitted in the Resolution Center and provides functional and technical detail for the App Review team.

---

## 1. Product Transformation at a Glance

| Aspect | Build 127 (2.0.5) | Build 159 (2.1.0) |
|--------|-------------------|-------------------|
| Primary experience | Staff live-chat agent console | **Public customer portal** |
| Sign-in model | WordPress administrator credentials | **Unified login**; server routes by account type |
| Customer registration | Not available in-app | **Self-service registration + email verification** |
| Public content | Not present | **Home, Services, Portfolio, About, Contact, Legal** |
| Core tabs (customer) | N/A | **Home · Services · Portfolio · Chat · Account** |
| Staff tools | Entire app | **Separate, permission-gated shell** |
| App positioning | Live Chat (staff support) | **PAXDesign — Customer Portal & Team Support** |

The application has been expanded from a staff-oriented live-chat tool into a **unified platform**: a native customer portal for the general public and business clients, with authorized staff capabilities available only to appropriately permissioned accounts.

---

## 2. Public Customer Platform — Primary Experience

### 2.1 Discovery and marketing (no staff credentials required)

- **Home tab** — Native homepage with hero, service highlights, capabilities, portfolio showcase, testimonials, awards, process overview, and news digest, aligned with the public PAXdesign website.
- **Services catalog** — Fully native browsable catalog with categories, expandable service cards, detail pages, and integrated “Request service” flow.
- **Portfolio** — Curated showcase and searchable project gallery with category filters and detail views.
- **About & Contact** — Structured content pages loaded from the CMS.
- **Legal & compliance** — Impressum, Privacy Policy, Terms, AGB, and related links accessible from the account area (Privacy Policy: https://paxdesign.at/datenschutz/).

### 2.2 Account system and onboarding

- **Self-service registration** — Name, email, password (minimum 8 characters).
- **Email verification** — OTP code with resend; accounts remain pending until verified.
- **Forgot password** — Standard recovery flow.
- **Unified login** — Single email/password form; server returns `session_mode: customer | staff` and routes accordingly.
- **Profile management** — Display name, email, verification status, avatar upload.
- **Account deletion** — Available with password confirmation.
- **Device management** — View connected devices; revoke individual devices or all other sessions.
- **App Lock** — Optional Face ID / PIN per account.

Any individual or business may create an account and use the customer portal **without being a PAXdesign employee**.

### 2.3 Customer workspace

- **Dashboard** — Personalized greeting, active projects (with progress), recent requests, file summary, news, chat preview, notification badges, and quick actions.
- **Projects** — List and detail views with milestones, assignees, notes, activity timeline, and downloadable files; iPad split layouts supported.
- **Orders / requests** — Create and track service requests (eight request types: service, general, question, support, consultation, new project, custom work, other); order detail with notes, files, and activity.
- **Files & Invoices** — Unified library from projects and orders; download and iOS share sheet.
- **News** — Native list and article detail views.

### 2.4 Communication — AI and human support

- **AI assistant** — Default chat entry with streaming SSE responses for instant help.
- **Human handoff** — Seamless transition to PAXdesign support staff when required.
- **Rich messaging** (human queue) — Photos, camera, documents, voice messages, location.
- **Conversation history**, typing indicators, read receipts, session recovery.
- **Real-time events** — SSE stream with intelligent polling fallback.

Communication is **1:1 customer support** (customer ↔ PAXdesign). There is no public social feed, follower system, or user-generated content amplification.

### 2.5 Notifications

- In-app notification center with category filters (All, Chat, Projects, Requests, News).
- Push notifications for chat, projects, orders, news, and security events.
- Badge sync, deep links, mark-as-read.
- Pre-permission education before the iOS notification prompt.

---

## 3. Staff Tools — Secondary, Permission-Protected Experience

Staff capabilities from the original live-chat app are **preserved but isolated**:

- Accessible only when the server validates **live-chat staff permission** on login.
- Routed to a **separate shell** (`AdaptiveShellView`) — not the customer tab interface.
- Includes live request handling, team messaging, staff AI reply assistant, analytics, and session management.
- Uses a **separate REST namespace** (`paxdesign/v1/live-admin/*`) from the customer API (`pdx/v1/customer/*`).

Registered customer accounts **cannot** access staff dashboards, team chat, live agent consoles, or admin analytics. The staff experience is **not** the default or primary path for App Store users.

---

## 4. Technical Architecture Supporting Public Distribution

### 4.1 API separation

| Layer | Customer | Staff |
|-------|----------|-------|
| iOS shell | CustomerPortalShellView | AdaptiveShellView |
| REST namespace | `/pdx/v1/customer/*` | `/paxdesign/v1/live-admin/*` |
| Role / permission | `pdx_customer` + ownership checks | Live-chat permission required |
| Push registry | `pax_customer_apns_devices` | `pax_live_apns_devices` |

Public auth endpoints (no login required): register, verify, forgot-password, resend-verification.  
Public content endpoints: homepage, services catalog, portfolio showcase, about, contact, legal.

### 4.2 Security

- HTTPS-only; credentials in iOS Keychain.
- WordPress Application Passwords minted on login (revocable per device).
- Rate limiting, failed-login tracking, suspended/pending account blocks.
- Chat and file access scoped to authenticated user ownership.
- Customers blocked from WordPress admin.
- No in-app purchases, ads, or tracking.

### 4.3 Backend and reliability (WordPress 3.152.7)

- Dedicated customer data model (projects, orders, notifications, news, services, chat sessions).
- MySQL message store (v2.1) with idempotent writes and outbox pattern.
- MySQL connection hygiene for stable concurrent REST/SSE/cron operation.
- Production-safe logging (no routine successful-request spam; PII redaction; log rotation).

### 4.4 iOS client optimizations (Builds 128–159)

- Auth-gated API client; no protected calls before session restoration.
- Deduplicated push registration; reduced heartbeat/polling overhead.
- Chat SSE with adaptive fallback; session persistence and recovery.
- Offline-aware loading; skeleton states; multilingual DE/EN/AR with RTL.

---

## 5. UI/UX Evolution

- App renamed **PAXDesign**; subtitle **Customer Portal & Team Support**.
- Purpose-built native SwiftUI customer portal (not a WebView wrapper).
- Five-tab customer navigation: Home, Services, Portfolio, Chat, Account.
- Six visual themes; Light/Dark/System appearance; accent color presets.
- Light-mode readability and appearance-aware accent colors.
- Accessibility labels; Dynamic Type on key controls; haptic feedback.

---

## 6. Release Milestones (Build 127 → Build 159)

| Phase | Builds | Highlights |
|-------|--------|------------|
| Unified app | 128+ | Customer Portal merged; PAXDesign branding; dual-shell routing |
| Auth & portal core | 129–134 | Mobile login, OTP verification, files hub, deep links |
| Content platform | 135–141 | Native homepage, about/contact, portfolio tab |
| Portfolio & chat | 144–147 | Showcase, session recovery, hero parity |
| Push & i18n | 148–151 | Customer APNs, legal footer, Arabic |
| Home & orders | 152–154 | Premium workspace UX, persistent chat |
| Real-time & polish | 155–157 | SSE chat, contrast, news content |
| Production ready | 158–159 | Logging/DB fixes, TestFlight validated |

**Build 159** is the version submitted for App Store review and supersedes all prior builds.

---

## 7. Summary — Suitability for Public Distribution

PAXDesign (Build 159) is a **professional customer relationship platform** for PAXdesign clients and the public:

1. **Open registration** — Any individual or business can sign up, verify email, and use the portal.
2. **Full native customer experience** — Services, portfolio, projects, orders, chat, files, news, notifications.
3. **Public marketing content** — Discoverable before sign-in.
4. **Clear role separation** — Customer and staff experiences are distinct and server-enforced.
5. **Support-focused messaging** — Not a social network or public UGC platform.
6. **Transparent compliance** — Privacy Policy, Terms, and legal pages in-app and on the website.
7. **No monetization in-app** — No IAP, subscriptions, or ads.

The application has evolved substantially since Build 127 and is intended for **general public distribution** on the App Store as PAXdesign’s official iOS customer platform.

---

*© 2026 PAXdesign — Confidential submission to Apple App Review*
