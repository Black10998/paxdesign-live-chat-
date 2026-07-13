# App Store Production Checklist — v2.0.0 / Build 113

Production review for official App Store release (WordPress 3.124.0).

## Native app (not WebView)

| Check | Status |
|-------|--------|
| No WKWebView / UIWebView / SFSafariViewController for app UI | ✅ SwiftUI native |
| Safari links only for legal pages (external) | ✅ Link() to paxdesign.at |
| REST API networking via URLSession | ✅ |

## Login & authentication

| Check | Status |
|-------|--------|
| No hardcoded credentials in source | ✅ |
| HTTPS-only server URLs enforced | ✅ SecureURLValidator + ATS |
| Credentials in iOS Keychain | ✅ |
| Session preserved offline; retry before logout | ✅ Build 111 |
| Login form validation | ✅ |
| Privacy links on login screen | ✅ |

## Legal & privacy

| Check | Status |
|-------|--------|
| In-app Privacy Policy | ✅ Konto → Datenschutz |
| In-app Terms | ✅ Konto → Nutzungsbedingungen |
| Working web Privacy Policy URL | ✅ https://paxdesign.at/datenschutz/ |
| Impressum link | ✅ https://paxdesign.at/impressum/ |
| Security claims accurate (no false E2E) | ✅ |
| Privacy manifest (PrivacyInfo.xcprivacy) | ✅ Includes location, photos, device ID |

## Permissions & App Privacy labels

| Permission | Usage string | Needed |
|------------|--------------|--------|
| Photo Library | NSPhotoLibraryUsageDescription | ✅ PhotosPicker |
| Camera | NSCameraUsageDescription | ✅ Optional camera capture |
| Microphone | NSMicrophoneUsageDescription | ✅ Voice messages |
| Location | NSLocationWhenInUseUsageDescription | ✅ Team chat location share |
| Notifications | NSUserNotificationsUsageDescription | ✅ Push alerts |
| Face ID | NSFaceIDUsageDescription | ✅ Optional app lock |

App Privacy Connect labels match manifest. No tracking.

## App icon & launch

| Check | Status |
|-------|--------|
| 1024×1024 App Icon | ✅ AppIcon asset set |
| Launch screen | ✅ UILaunchScreen generation |
| Display name | PAXDesign Live Chat |

## Core features (release highlights)

| Feature | Status |
|---------|--------|
| Live Team Chat | ✅ |
| AI Reply Assistant | ✅ |
| Voice Messages (M4A upload) | ✅ Build 112 fix |
| Photo & Location Sharing | ✅ |
| Real-Time Customer Support | ✅ |
| Push Notifications (APNs) | ✅ |
| Analytics Dashboard | ✅ Build 112 |
| App-wide Themes | ✅ Build 112 |

## Stability & production hygiene

| Check | Status |
|-------|--------|
| Diagnostics use os.log only (no UI debug panels) | ✅ |
| No SIDELOAD code path in App Store build | ✅ `#if !SIDELOAD` |
| Polling tasks cancelled on stop/disappear | ✅ |
| Unauthorized session handling | ✅ |
| ITSAppUsesNonExemptEncryption = NO | ✅ |

## Encryption export

| Check | Status |
|-------|--------|
| ITSAppUsesNonExemptEncryption = false | ✅ |

## App Store assets

| Check | Status |
|-------|--------|
| 5 marketing screenshots (6.7") | ✅ docs/app-store/screenshots/ |
| Metadata (DE/EN/AR) | ✅ METADATA.md + metadata.json |
| Review notes for employee-only app | ✅ |

## Rejection risks addressed

1. Employee-only app → demo credentials in ASC review field
2. Voice upload failures → WordPress `store_audio_upload()` (3.123.0+)
3. Overstated security → explicit TLS-only wording
4. HTTP allowed → blocked via ATS + validator
5. Missing privacy strings → all permissions documented

## Physical device testing (recommended before public release)

- [ ] Fresh install → login → logout → re-login
- [ ] Send/receive customer messages in real time
- [ ] Send voice message in team chat
- [ ] Send image and location in team chat
- [ ] AI reply suggestions in customer chat
- [ ] Push notification for live request
- [ ] Privacy Policy link opens Safari
- [ ] Revoke Application Password → app logs out
