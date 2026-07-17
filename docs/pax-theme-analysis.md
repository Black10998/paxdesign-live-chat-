# PAXDesign Theme Analysis & Native App Mapping

**Theme:** Navein (P.A.X.) v1.0.1 — Elementor agency theme by Ahmad Al-Khalaf  
**Production site:** https://paxdesign.at  
**Data source for app:** `paxdesign-booking` plugin (`pdx/v1` REST), not theme PHP templates  
**Analysis date:** July 2026

---

## Executive Summary

The uploaded theme ZIP is a **presentation shell**. Most business content on production is built with **Elementor** page builder and stored in WordPress posts/meta — not in static theme template files. Customer account data, chat, projects, orders, notifications, and services are served by **`paxdesign-booking`** (customer platform). Auth routes currently live in **`paxdesign-toolbar`** and will be migrated into booking in Phase 2.

The iOS app should remain a **native client** over REST — never a WebView clone of the marketing site.

---

## Theme Structure

| Area | Path | Role |
|------|------|------|
| Core templates | `page.php`, `single.php`, `archive.php` | Standard WP loops |
| Portfolio | `single-dtr_portfolio.php` | CPT showcase layout |
| Testimonials | `single-dtr_testimonial.php` | CPT testimonial layout |
| Headers | `template-parts/header/header-v1..v4.php` | Layout variants |
| Options | `includes/options/theme-options/*` | Redux theme customizer |
| Bundled deps | Elementor, Redux, Contact Form 7 (via `include-plugins.php`) | Page building |

**Bundled CPTs (via `dtr-navein-core` plugin — referenced, not in ZIP):**
- `dtr_portfolio` — portfolio / case studies
- `dtr_testimonial` — client testimonials

**Blog:** standard `post` type with masonry/standard archive layouts.

---

## Production Site Pages (Elementor-driven)

These are the primary public sections inferred from theme demo import, chat quick actions, and existing REST catalog:

| Site section | Native app target | REST / source |
|--------------|-------------------|---------------|
| Home / marketing | Optional browse tab (Phase 3+) | Elementor pages → future `/content/*` or curated news |
| Services catalog | **Native Services** (exists) | `GET /pdx/v1/customer/services` |
| Portfolio | Native gallery (Phase 3+) | WP `dtr_portfolio` or curated endpoint |
| Blog / news | **Native News** (exists) | `GET /pdx/v1/customer/news` |
| Contact / booking | Native order flow (partial) | Booking widget + `POST /customer/orders` |
| Customer portal | **Native tabs** (exists) | `/customer/dashboard`, `/profile`, `/projects`, `/orders` |
| Live Chat | **Native Chat** (exists) | `/customer/chat/*` |
| Auth (Sign In / Register) | **Native Login** (exists) | `/auth/mobile-login`, `/auth/register`, `/auth/verify` |
| Team page | Staff app only | Staff REST |

---

## Customer Platform — Already Native in iOS

| Feature | iOS screen | REST |
|---------|------------|------|
| Dashboard | `CustomerDashboardView` | `/customer/dashboard` |
| Profile | Customer profile screens | `/customer/profile` |
| Projects | `CustomerProjectDetailView` | `/customer/projects`, `/projects/{id}` |
| Orders | `CustomerOrderDetailView` | `/customer/orders`, `/orders/{id}` |
| Services | Services list | `/customer/services` |
| News | `CustomerNewsDetailView` | `/customer/news` |
| Notifications | `CustomerNotificationsView` | `/customer/notifications` |
| Chat | `CustomerChatView` | `/customer/chat/messages`, stream, media |
| Push | `PushService` | `/customer/push/register` + APNS |

---

## Chat Architecture

```
WordPress (paxdesign-booking)
├── Web widget (chat-script.js + booking widget)
├── Admin hub (live chat)
├── Customer REST (/pdx/v1/customer/chat/*)
└── iOS app (CustomerAPIClient)
         ↓
   PAXdesign_Message_Store + PAXdesign_Chat_Live (single source of truth)
```

**Phase 1 changes:**
- Website chat requires login before any conversation (modal gate)
- Participant identity on all messages (customer / staff / AI)
- iOS chat bubbles show avatar, name, role

**Separation rule:** Live Chat tab ≠ Projects/Orders official updates. Notifications route by `entity_type` (`chat`, `project`, `order`, etc.).

---

## Notifications Matrix

| Event | Category | Deep link |
|-------|----------|-----------|
| Staff chat reply | `chat` | `/chat/{session_id}` |
| Project created/updated | `project` | `/projects/{id}` |
| Order status change | `order` | `/orders/{id}` |
| New project file | `project` | `/projects/{id}` |
| Invoice / quote | `order` or `project` | entity-specific |
| Staff note | `project` | `/projects/{id}` |
| Account update | `account` | `/profile` |

Backend: `PAXdesign_Customer_Notifications` — DB inbox + APNS push.

---

## Toolbar Dependency (Phase 2 migration)

| Dependency | Used for | Migration target |
|------------|----------|------------------|
| `PDX_Auth` | Register, login, verify, mobile-login | `paxdesign-booking/includes/auth/` |
| `PDX_Customers` | Account suspension | Customer auth class |
| `PDX_RateLimit` | Auth rate limits | Booking auth module |
| `pdx-auth.js` | Web login overlay | Keep until booking auth UI exists |

Goal: delete `paxdesign-toolbar` without breaking auth or chat.

---

## Phased Roadmap

### Phase 1 (this release — Build 132)
- [x] Theme analysis document
- [x] Website chat login gate (modal + block all guest AJAX)
- [x] Chat participant profiles (API + web + iOS)
- [ ] Deploy WordPress + TestFlight Build 132

### Phase 2
- Migrate auth from toolbar into booking
- Remove `class_exists('PDX_*')` fallbacks where possible

### Phase 3
- Notifications inbox polish + deep link routing audit
- Native services/news marketing polish
- Portfolio native browse (optional)

### Phase 4
- Full localization audit (`Localizable.xcstrings` + WP i18n)
- iPad layouts, design system pass
- E2E QA checklist per release

---

## Testing Checklist (Phase 1)

**Website**
- [ ] Guest clicks chat → auth modal (Sign In / Create Account), no anonymous messages
- [ ] After login → same session history loads
- [ ] AI and staff messages show name, avatar/initials, role
- [ ] Customer messages show customer name/avatar when logged in

**iOS (TestFlight 132)**
- [ ] Customer chat loads without decode errors
- [ ] Bubbles show participant identity for user / staff / AI
- [ ] Typing indicator when staff is typing (human queue)
- [ ] Push notification opens correct chat session

**Regression**
- [ ] Booking widget still works for logged-in users
- [ ] Staff live chat hub unchanged
- [ ] Customer dashboard, projects, orders unaffected
