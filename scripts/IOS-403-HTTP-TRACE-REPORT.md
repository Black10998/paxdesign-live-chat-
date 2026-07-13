# iOS App 403 — HTTP Request Trace Analysis

## Deployment status (confirmed via CI)

| Component | Version | Status |
|-----------|---------|--------|
| WordPress plugin (`paxdesign-booking`) | **3.112.0** | Deployed [run #29221111018](https://github.com/Black10998/paxdesign-live-chat-/actions/runs/29221111018) |
| iOS TestFlight | **1.60.0 Build 99** | Uploaded [run #29222090017](https://github.com/Black10998/paxdesign-live-chat-/actions/runs/29222090017), `IN_BETA_TESTING` for `awjime29@icloud.com` |
| iOS TestFlight (HTTP forensics) | **1.61.0 Build 100** | Triggered after this report — check latest App Store / TestFlight workflow |
| `paxdesign-toolbar` dock.js 401 fix | deployed | [run #29222733272](https://github.com/Black10998/paxdesign-live-chat-/actions/runs/29222733272) |

---

## Why 403 affects the app but not website browsing

| | Website (browser) | iOS Live Chat app |
|---|-------------------|-------------------|
| **Auth** | Cookies (`wordpress_logged_in_*`) on admin pages; public pages often no auth | **`Authorization: Basic`** (Application Password) on **every** `/live-admin/*` call |
| **Nonce** | `X-WP-Nonce` for PDX dock REST | **Not used** — WordPress Application Password auth only |
| **Cookies** | Persistent browser jar | **`URLSessionConfiguration.ephemeral`** — no cookie storage |
| **User-Agent** | Chrome/Safari | CFNetwork default → **`PAXDesignLiveChat/x.y (iOS; Build n)`** from Build 100 |
| **Endpoints** | HTML, static assets, optional PDX public tools | **`/wp-json/paxdesign/v1/live-admin/*`** — SSE + parallel REST |
| **Request rate** | Low (human navigation) | High at login (10+ endpoints) + polling/SSE (mitigated in Build 99+, traced in Build 100) |
| **403 layer** | Rare during normal browse | **LiteSpeed/Hostinger edge** — static body, no `x-powered-by: PHP`, site-wide on client IP ~5 min |

The app does **not** share the browser’s WordPress session. Cloudflare/WAF sees a **different traffic fingerprint**: high-frequency authenticated REST from one mobile IP, not HTML browsing.

---

## iOS request flow (code path)

```
LiveChatAPI.authRequest()
  → User-Agent: PAXDesignLiveChat/… (Build 100+)
  → Authorization: Basic base64(email:appPassword)
  → Accept: application/json
  → Cache-Control: no-cache
  → NO Cookie header
  → NO X-WP-Nonce

perform() / consumeEventStream()
  → NetworkCircuitBreaker (3 rps cap, 5 min pause on edge 403)
  → HTTPResponseForensics (captures cf-ray, server, retry-after on 4xx)
```

WordPress side (`class-paxdesign-live-chat-mobile-api.php`):

- Basic Auth only on `/wp-json/paxdesign/v1/live-admin/*`
- Email → login mapping once per HTTP request (3.112.0)

---

## How to capture evidence on next 403 (Build 100)

1. Install **Build 100** from TestFlight (pull to refresh).
2. Open app → **Settings → Netzwerk-Diagnose**.
3. Reproduce 403 (open app on Wi‑Fi).
4. Read **Letzter 403/429 (WAF)** section:
   - `cf-ray` → Cloudflare Security Events
   - `server` / `x-powered-by` → edge vs PHP
   - `retry-after` → ban TTL if present
   - `Edge-Block: Ja` → LiteSpeed static deny

---

## Server-side trace script

```bash
# From CI or Mac with admin app password secrets:
bash scripts/trace-ios-http-profile.sh
```

Simulates iOS User-Agent + Basic Auth burst vs browser profile.

---

## Root cause (current best model)

**IP-scoped LiteSpeed/Hostinger rate limit** triggered by parallel **Application Password REST** from the iOS app on the home Wi‑Fi network — **not** WordPress permissions and **not** website browsing. Build 99 reduces load; Build 100 captures headers for definitive WAF correlation.
