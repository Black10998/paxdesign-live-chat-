# App Store Privacy Documentation — v2.0.0

## App Privacy Questionnaire (App Store Connect)

| Data Type | Collected | Linked to User | Used for Tracking | Purpose |
|-----------|-----------|----------------|-------------------|---------|
| Email address | Yes | Yes | No | App functionality (login) |
| Name | Yes | Yes | No | App functionality (profile display) |
| User ID | Yes | Yes | No | App functionality |
| Photos or Videos | Yes (optional) | Yes | No | Send images in chat |
| Audio Data | Yes (optional) | Yes | No | Voice messages in team chat |
| Precise Location | Yes (optional) | Yes | No | Share location in team chat; device sign-in verification |
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
| NSCameraUsageDescription | Take photos to send in chat |
| NSMicrophoneUsageDescription | Record voice messages in team chat |
| NSLocationWhenInUseUsageDescription | Share location in team chat; device sign-in verification |
| NSUserNotificationsUsageDescription | Live request and message alerts |
| NSFaceIDUsageDescription | Optional app lock after inactivity |

## Privacy manifest

File: `paxdesign-booking/ios-live-chat/PAXDesignLiveChat/PrivacyInfo.xcprivacy`

Declares: Email, Name, Photos/Videos, Other User Content, User ID, Precise Location, Device ID.  
API: UserDefaults (CA92.1). No tracking.

## Submission

See [SUBMISSION.md](SUBMISSION.md), [METADATA.md](METADATA.md), [APP_REVIEW_CHECKLIST.md](APP_REVIEW_CHECKLIST.md).
