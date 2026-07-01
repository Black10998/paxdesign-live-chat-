# App Store Submission Guide

## Prerequisites

- Apple Developer Program membership (Company or Individual)
- App Store Connect access
- Signing certificate + provisioning profile for `at.paxdesign.livechat`

## App Store Connect setup

| Field | Value |
|-------|-------|
| Bundle ID | `at.paxdesign.livechat` |
| Name | PAXDesign Live Chat |
| Primary language | German |
| Category | Business |
| Age rating | 4+ (no restricted content) |

## Privacy

- Complete **App Privacy** labels in App Store Connect using [PRIVACY.md](PRIVACY.md)
- Privacy manifest: `ios-live-chat/PAXDesignLiveChat/PrivacyInfo.xcprivacy`
- Privacy Policy URL: in-app legal text + https://paxdesign.at

## Required screenshots

Capture on iPhone (portrait only):

- 6.7" (iPhone 15 Pro Max)
- 6.5" (iPhone 11 Pro Max)
- 5.5" (iPhone 8 Plus) — optional if using scaled uploads

Suggested screens: Session list, active chat with privacy banner, Konto tab, Settings.

## Build upload

1. Download `PAXDesignLiveChat.ipa` from the latest [GitHub Release](https://github.com/Black10998/paxdesign-live-chat-/releases/latest)
2. Or build locally: `cd paxdesign-booking/ios-live-chat && ./scripts/build-ipa.sh`
3. Upload via **Transporter** or Xcode Organizer (Archive → Distribute)

For App Store distribution, configure `DEVELOPMENT_TEAM` in `project.yml` and use a distribution certificate.

## Review notes (suggested)

> This app is for authorized PAXdesign staff only. Login requires a WordPress administrator account and Application Password. The app provides real-time customer support chat — equivalent to the browser-based admin panel.

Provide a demo account in App Store Connect if Apple requests test credentials.

## Export compliance

- Uses standard HTTPS/TLS only
- No custom encryption beyond Apple's OS APIs
- Typical answer: **No** for proprietary encryption (uses exempt HTTPS)

## Checklist

- [ ] Version and build number bumped in `project.yml`
- [ ] Privacy manifest matches App Privacy questionnaire
- [ ] All usage descriptions present in Info.plist / project.yml
- [ ] IPA built and tested on physical device
- [ ] Plugin v3.49.0+ deployed on WordPress (image upload REST route)
- [ ] Push notifications configured (APNs key in WordPress)
- [ ] Submit for review
