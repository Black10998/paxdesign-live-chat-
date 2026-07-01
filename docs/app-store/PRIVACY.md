# App Store Privacy Documentation

## App Privacy Questionnaire (App Store Connect)

| Data Type | Collected | Linked to User | Used for Tracking | Purpose |
|-----------|-----------|----------------|-------------------|---------|
| Email address | Yes | Yes | No | App functionality (login) |
| Name | Yes | Yes | No | App functionality (profile display) |
| User ID | Yes | Yes | No | App functionality |
| Photos | Yes (optional) | Yes | No | Send images in chat |
| Other User Content | Yes | Yes | No | Chat messages |
| Device ID | Yes | Yes | No | Push notifications (APNs token) |

**Tracking:** No third-party tracking or advertising.

## Privacy Policy URL

https://paxdesign.at/datenschutz/

## Encryption

- Transport: HTTPS/TLS only (enforced in app + ATS)
- Credentials: iOS Keychain (Application Password)
- End-to-end encryption: **Not implemented** (server stores messages for support workflow)

## GDPR

In-app: Konto → Datenschutzerklärung, Datenverarbeitung, Sicherheit  
Web: https://paxdesign.at/datenschutz/

## Required Info.plist usage strings

| Key | Purpose |
|-----|---------|
| NSPhotoLibraryUsageDescription | Select photos to send in chat |
| NSUserNotificationsUsageDescription | Live request and message alerts |

Camera is **not** used — no NSCameraUsageDescription required.

## Privacy manifest

File: `ios-live-chat/PAXDesignLiveChat/PrivacyInfo.xcprivacy`

Matches the table above. Includes UserDefaults API reason CA92.1.

## Submission

See [SUBMISSION.md](SUBMISSION.md), [METADATA.md](METADATA.md), [APP_REVIEW_CHECKLIST.md](APP_REVIEW_CHECKLIST.md).
