# App Store Privacy Documentation

## App Privacy Questionnaire (summary)

| Data Type | Collected | Linked to User | Used for Tracking | Purpose |
|-----------|-----------|----------------|-------------------|---------|
| Email address | Yes | Yes | No | App functionality (login) |
| User ID | Yes | Yes | No | App functionality |
| Photos | Yes (optional) | Yes | No | Send images in chat |
| Chat content | Yes | Yes | No | App functionality |

**Tracking:** No third-party tracking or advertising.

## Encryption

- Transport: HTTPS/TLS for all API communication
- Credentials: iOS Keychain (Application Password)
- End-to-end encryption: Not implemented (server stores messages for support workflow)

## GDPR

See in-app: Konto → Datenschutzerklärung, Datenverarbeitung

## Required Info.plist usage strings

- `NSPhotoLibraryUsageDescription` — attach images to chat
- `NSUserNotificationsUsageDescription` — live request alerts

## Submission checklist

- [ ] Apple Developer Program enrollment
- [ ] App Store Connect app record (`at.paxdesign.livechat`)
- [ ] Screenshots (6.7", 6.5", 5.5")
- [ ] Privacy Policy URL: https://paxdesign.at (or in-app legal text)
- [ ] Upload signed IPA via Xcode / Transporter
- [ ] Complete App Privacy labels in App Store Connect (match PrivacyInfo.xcprivacy)
