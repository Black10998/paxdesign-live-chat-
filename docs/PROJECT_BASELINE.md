# PAXdesign Project Baseline (Official)

**Effective:** 2026-08-16  
**Version:** `3.175.23`  
**Git branch:** `cursor/stable-v3-175-no-toolbar`  
**Source of truth:** `origin/main` @ `paxdesign-live-chat-` (not `paxdesign.booking`)

---

## Architecture

| Layer | Location | Notes |
|-------|----------|-------|
| WordPress plugin | `paxdesign-booking/` v3.175.23 | Booking + Live Chat + Customer Platform + CCS/Cybercrime |
| Customer auth UI | `assets/customer-auth/` | Standalone — **no PDXDock / no toolbar** |
| Chat + CCS | `assets/js/chat-script.js` | Latest CCS fixes through `7faf24e` |
| Theme shell | `navein/` | Cybercrime + homepage in git; mobile nav / footer / referenzen wrapper synced from production |
| ~~paxdesign-toolbar~~ | **REMOVED from repo + deploy** | See `REMOVED-PAXDESIGN-TOOLBAR.md` |

---

## Post-login UI (restored from production = GitHub baseline)

| Feature | Implementation |
|---------|----------------|
| Username | `display_name` in header auth bar + account menu |
| Status badge | **Blue verified badge** (`pdx-verified-badge.js`) when `verified === true` |
| Account portal | Apple-style white dashboard (`pdx-portal-apple.css`, `/account/`) |
| Mobile site menu | `#dtr-menu-button` ☰ hamburger — `navein/assets/js/apple-mobile-nav.js` |
| Mobile account nav | Scrollable top nav grid inside account app (≤900px) |

**Gold + Level:** Not present in Git history or production `pax-auth.js`. Latest stable uses **Verified badge**, not membership Gold/Level. Do not reintroduce without explicit new spec.

**GitHub OAuth login:** Not in production or GitHub history. Footer “GitHub” is repo info modal in theme footer, not login.

---

## Pages

| Page | URL | Source |
|------|-----|--------|
| Cybercrime Support | `/cybercrime-support/` | `navein/template-apple-cybercrime-support.php` + plugin CCS |
| Referenzen | `/referenzen/` | Template wrapper + Elementor page content on server |
| Customer account | `/account/` | Plugin auth + portal |

---

## CCS / Chat (must preserve)

- One assistant reply per customer turn (commits `765eafe`, `135f1d5`, `7faf24e`)
- Page context on cybercrime pages
- Case sync between AI ↔ tickets ↔ customer portal

---

## Release checklist

1. `bash scripts/verify-no-toolbar.sh`
2. Tag `vX.Y.Z` matching plugin version
3. GitHub Release → WordPress auto-update
4. Deploy workflow deactivates `paxdesign-toolbar` on server
5. Test login, account, ☰ menu, referenzen, cybercrime, chat (mobile + desktop)

---

## Forbidden

- Restore or deploy `paxdesign-toolbar`
- Use remote `Black10998/paxdesign.booking`
- Redesign UI from scratch when production/GitHub already has the implementation
