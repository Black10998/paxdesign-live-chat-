# App Store Production Checklist — v3.50.0

Completed review from an Apple App Review perspective.

## Native app (not WebView)

| Check | Status |
|-------|--------|
| No WKWebView / UIWebView / SFSafariViewController for app UI | ✅ SwiftUI native |
| Safari links only for legal pages (external) | ✅ Link() to paxdesign.at |
| REST API networking via URLSession | ✅ |

## Login & authentication

| Check | Status |
|-------|--------|
| No hardcoded credentials in source | ✅ Fixed (removed default email) |
| HTTPS-only server URLs enforced | ✅ SecureURLValidator + ATS |
| Credentials in iOS Keychain | ✅ |
| Auto-logout on 401/403 | ✅ handleUnauthorized() |
| Login form validation | ✅ |
| Privacy links on login screen | ✅ |

## Legal & privacy

| Check | Status |
|-------|--------|
| In-app Privacy Policy | ✅ Konto → Datenschutz |
| In-app Terms | ✅ Konto → Nutzungsbedingungen |
| Working web Privacy Policy URL | ✅ https://paxdesign.at/datenschutz/ |
| Impressum link | ✅ https://paxdesign.at/impressum/ |
| Security claims accurate (no false E2E) | ✅ Explicitly documented |
| Privacy manifest (PrivacyInfo.xcprivacy) | ✅ Updated |

## Permissions & App Privacy labels

| Permission | Usage string | Needed |
|------------|--------------|--------|
| Photo Library | NSPhotoLibraryUsageDescription | ✅ PhotosPicker only |
| Notifications | NSUserNotificationsUsageDescription | ✅ Push alerts |
| Camera | — | Not used (no string) |

App Privacy Connect labels match manifest: Email, Name, Photos, User Content, User ID, Device ID. No tracking.

## App icon & launch

| Check | Status |
|-------|--------|
| 1024×1024 App Icon | ✅ AppIcon.png |
| Launch screen (no missing assets) | ✅ Fixed Info.plist |
| Display name | PAXDesign Live Chat |

## Stability

| Check | Status |
|-------|--------|
| Polling tasks cancelled on stop/disappear | ✅ weak self, cancel |
| No force-unwrap crashes in UI paths | ✅ Reviewed |
| Session delete without optimistic crash | ✅ Fixed in prior release |
| Unauthorized session handling | ✅ Added v3.50.0 |

## Security statements

| Claim | Accurate? |
|-------|-----------|
| TLS/HTTPS transport | ✅ Enforced |
| Keychain credential storage | ✅ |
| End-to-end encryption | ❌ Not claimed (explicitly denied) |
| "Protected conversations" | ✅ Wording: TLS only, no E2E |

## Encryption export

| Check | Status |
|-------|--------|
| ITSAppUsesNonExemptEncryption = false | ✅ |

## Physical device testing (manual)

Test on iPhone before App Store submit:

- [ ] Fresh install → login → logout → re-login
- [ ] Send/receive messages in real time
- [ ] Send image from photo library
- [ ] Long-press: copy, share, reply
- [ ] Push notification for live request
- [ ] Tap Privacy Policy link on login → opens Safari
- [ ] Konto → Datenschutz (Web) → opens Safari
- [ ] Revoke Application Password on server → app logs out

## Rejection risks addressed

1. ~~Hardcoded user email in AuthStore~~ → Removed
2. ~~Missing privacy policy link at login~~ → Added
3. ~~Broken launch screen asset references~~ → Removed
4. ~~Overstated security (E2E implied)~~ → Clarified wording
5. ~~HTTP allowed~~ → Blocked
6. Employee-only app → Provide demo credentials in Review Notes
