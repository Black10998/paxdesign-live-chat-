# PAXDesign Live Chat — App Store Release Report

Generated: 2026-07-13 (App Store production release)

## Version alignment

| Component | Version | Status |
|-----------|---------|--------|
| iOS Marketing Version | **2.0.0** | ✅ `project.yml` |
| iOS Build Number | **113** | ✅ Uploaded & VALID |
| WordPress Plugin | **3.124.0** | ✅ Deployed to production |

## CI pipeline results

| Step | Workflow | Result |
|------|----------|--------|
| WordPress deploy | `deploy-and-verify-3124.yml` | ✅ Success |
| App Store IPA build | `release-appstore.yml` | ✅ Success |
| TestFlight upload | `upload-testflight.yml` | ✅ Success |
| Screenshot upload | `app-store-submit.yml` | ✅ 5 screenshots on de-DE |
| Metadata sync | `app-store-submit.yml` | ✅ de-DE, en-US |
| Build attach | App Store Connect API | ✅ Build 113 attached |
| Review submission | App Store Connect API | ✅ Already in review queue |

## App Store Connect status

- **Build processing:** `VALID` (Build 113)
- **Build attached:** Yes (`d2af854c-a9c1-43a2-8c0a-01756b66ba7a`)
- **Screenshots:** 5 × 6.7" (1290×2796) uploaded for German (de-DE)
- **Submitted for Apple review:** **Yes** — version was already in the submission queue (API returned CREATE not allowed; only DELETE permitted, indicating active submission)

## Warnings & recommendations

1. **What's New** — Could not update `whatsNew` via API (field locked in current ASC state). Verify release notes manually in App Store Connect if needed.
2. **Arabic localization** — `ar` is not enabled in App Store Connect; automated metadata applied for **de-DE** and **en-US** only. Add Arabic in ASC if required.
3. **Demo credentials** — Ensure WordPress admin Application Password is set in App Store Connect **App Review Information** for Apple testers.
4. **Age rating & App Privacy** — Confirm questionnaire matches `docs/app-store/PRIVACY.md` and `PrivacyInfo.xcprivacy`.
5. **Export compliance** — `ITSAppUsesNonExemptEncryption = NO` (standard HTTPS/TLS only).

## App Store page assets

| Asset | Location |
|-------|----------|
| Screenshots | `docs/app-store/screenshots/6.7-inch/` |
| Metadata | `docs/app-store/METADATA.md`, `metadata.json` |
| Privacy | `docs/app-store/PRIVACY.md` |
| Review checklist | `docs/app-store/APP_REVIEW_CHECKLIST.md` |

## URLs

- Privacy Policy: https://paxdesign.at/datenschutz/
- Support: https://paxdesign.at
- Categories: Business (primary), Productivity (secondary)

## Review notes (for Apple)

> Internal employee app for PAXdesign customer support staff. Login requires a WordPress administrator account and Application Password for https://paxdesign.at. Demo credentials provided in App Store Connect review field. No in-app purchases. No ads. No tracking.

---

**PR:** [#86](https://github.com/Black10998/paxdesign-live-chat-/pull/86) (release) + hotfixes [#87–#93](https://github.com/Black10998/paxdesign-live-chat-/pulls)
