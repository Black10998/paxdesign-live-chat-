# TestFlight — PAXDesign Live Chat iOS

This guide explains how to produce an installable iPhone build via **TestFlight**.

## Why this cannot be done from the cloud build alone

Apple requires:

- A paid **Apple Developer Program** membership
- Code signing with your **Distribution certificate**
- Upload via **Xcode** or `xcrun altool` from a Mac
- App Store Connect app record with matching bundle ID

The native source code is complete in this repository; you (or PAXDesign) run the final signing and upload on a Mac tied to your Apple account.

---

## Step 1 — Apple Developer setup

1. Enroll at [developer.apple.com/programs](https://developer.apple.com/programs/)
2. In **Certificates, Identifiers & Profiles**:
   - Create App ID: `at.paxdesign.livechat`
   - Enable **Push Notifications**
3. Create an **APNs Auth Key** (.p8) and note **Key ID** and **Team ID**
4. Add the key to WordPress (see `ios-live-chat/README.md`)

---

## Step 2 — Generate Xcode project

```bash
brew install xcodegen
cd ios-live-chat
xcodegen generate
open PAXDesignLiveChat.xcodeproj
```

---

## Step 3 — Signing & assets

1. Target **PAXDesignLiveChat → Signing & Capabilities**
2. Team: your Apple Developer team
3. Bundle Identifier: `at.paxdesign.livechat`
4. Add capability: **Push Notifications**
5. Add **1024×1024** app icon (PAXDesign clock + engraved “Chat” recommended)
6. Optional: add launch screen image `LaunchLogo` in Assets

---

## Step 4 — Archive

1. Select **Any iOS Device (arm64)** as run destination
2. **Product → Archive**
3. When Organizer opens: **Distribute App → App Store Connect → Upload**

---

## Step 5 — TestFlight

1. Open [App Store Connect](https://appstoreconnect.apple.com/)
2. Create app **PAX Live Chat** (bundle `at.paxdesign.livechat`)
3. After processing (~5–15 min), open **TestFlight**
4. Add internal testers (your Apple ID) or external testers
5. Install **TestFlight** app on iPhone → accept invitation → install

---

## Step 6 — Configure the app

1. Open **PAX Live Chat** on iPhone
2. Allow notifications when prompted
3. Sign in:
   - Website: `https://paxdesign.at`
   - WordPress username
   - Application Password from WP profile

---

## Troubleshooting

| Issue | Fix |
|-------|-----|
| Login fails | Ensure Application Passwords enabled; user has Administrator role |
| No push | APNs key configured in WP; push capability enabled; test on real device |
| 401 on API | Regenerate Application Password; check site URL (https, no trailing path) |
| Plugin API missing | Upgrade plugin to v3.39.0+ |

---

## Alternative: Ad-hoc install (small team)

For up to 100 registered devices without TestFlight review:

1. Register device UDIDs in Developer portal
2. Create **Ad Hoc** provisioning profile
3. Archive → **Distribute → Ad Hoc** → export `.ipa`
4. Install via Apple Configurator or MDM

TestFlight is recommended for ongoing updates.
