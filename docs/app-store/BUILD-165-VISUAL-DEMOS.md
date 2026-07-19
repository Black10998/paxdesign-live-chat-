# Build 165 — Visual Demo Reference

These mockups illustrate the premium UI delivered in the consolidated Build 165 release. Install TestFlight Build **165** to verify on device.

## Home Screen Widget (all sizes)

Dark mode — small, medium, and large widgets with live counters, tappable tiles, and no duplicate in-widget brand label (iOS shows **PAXDesign** below the widget only):

<img alt="Build 165 widgets dark mode all sizes" src="/opt/cursor/artifacts/assets/build-165-widget-all-sizes-dark.png" />

Light mode — adaptive palette with system-blue accent:

<img alt="Build 165 widgets light mode" src="/opt/cursor/artifacts/assets/build-165-widget-light-mode.png" />

### Widget features in Build 165

| Feature | Detail |
|---------|--------|
| Sizes | Small, Medium, Large |
| Appearance | Adaptive Light / Dark / System |
| Metrics | Chats (unread sessions), Live, Tasks, Events |
| Live refresh | `WidgetCenter.reloadTimelines` on sync, push, unread changes, background flush |
| Deep links | Tap tiles → `paxlivechat://live`, `paxlivechat://chats`, dashboard |
| Large extras | Top live request name, next event title, LIVE badge when queue > 0 |
| Signed out | Placeholder state after logout |

## Login, registration, and verification

Premium auth hero with animated PAXdesign logo (staff login) and branded icon illustrations (register, forgot password, verify):

<img alt="Build 165 auth screens" src="/opt/cursor/artifacts/assets/build-165-auth-screens-light.png" />

### Auth visual upgrades

- `PAXAuthHeroView` — animated logo or accent-ring icon hero
- Staff `LoginView` — website-parity `PAXAnimatedLogoView`
- Customer register / forgot / verify — glass `PAXField` forms (no plain system Form)
- Customer login — matching premium layout
- Post-login onboarding — `PAXOnboardingIllustration` cards with per-step accent tints
- Login → dashboard — scale + opacity shell transition; splash replay after sign-in

## Onboarding and Staff Dashboard entry

<img alt="Build 165 onboarding and dashboard" src="/opt/cursor/artifacts/assets/build-165-onboarding-dashboard-dark.png" />

- First-run carousel uses premium illustration frames (not bare icons)
- Post-login setup steps use matching illustration treatment
- Dashboard hero retains glass metrics grid with PAXIcon vector set

## Physical device QA checklist

- [ ] Add widget in Small, Medium, Large — Light, Dark, System
- [ ] Confirm counters update after opening app, reading chat, completing task
- [ ] Tap Live tile → opens Live tab
- [ ] Staff login shows animated logo; register sheet shows premium form
- [ ] First-run onboarding illustrations render with accent rings
- [ ] Appearance switch does not crash shell or widget host
