# PAXDesign Live Chat — Native iOS App

Production SwiftUI administrator app for the PAXDesign Booking Live Chat system.

## Features

- Native SwiftUI interface (not a web wrapper)
- Secure login via WordPress **Application Passwords**
- Real-time chat via REST polling (same backend as web admin)
- Push notifications via **Apple Push Notification service (APNs)**
- Full-screen incoming **call-style alert** for Live Agent requests
- Accept or decline requests in-app
- Background notifications when app is closed

## Requirements

| Requirement | Details |
|-------------|---------|
| Mac | macOS with **Xcode 15+** |
| Apple Developer | Enrolled in [Apple Developer Program](https://developer.apple.com/programs/) |
| WordPress plugin | **PAXdesign Booking v3.39.0+** with mobile REST API |
| Admin account | WordPress user with `manage_options` + Application Password |

## Backend setup (WordPress)

1. Update the plugin to **v3.39.0+**
2. Create an Application Password: **WordPress Admin → Users → Profile → Application Passwords**
3. Configure APNs (for push when app is closed):

```php
// wp-config.php or via options API
update_option('paxdesign_apns_key_id', 'YOUR_KEY_ID');
update_option('paxdesign_apns_team_id', 'YOUR_TEAM_ID');
update_option('paxdesign_apns_key_p8', file_get_contents('AuthKey_XXXX.p8'));
update_option('paxdesign_apns_bundle_id', 'at.paxdesign.livechat');
```

4. Enable **Push Notifications** capability for the app bundle ID in Apple Developer portal

### REST API

Base: `https://your-site/wp-json/paxdesign/v1/live-admin/`

Authentication: HTTP Basic (`username:application_password`)

## Build the app (Mac)

```bash
# Install XcodeGen (once)
brew install xcodegen

cd ios-live-chat
xcodegen generate
open PAXDesignLiveChat.xcodeproj
```

In Xcode:

1. Select the **PAXDesignLiveChat** target
2. Set your **Team** (Signing & Capabilities)
3. Enable **Push Notifications** capability
4. Replace `DEVELOPMENT_TEAM` in project settings if needed
5. Add a 1024×1024 app icon to `Resources/Assets.xcassets/AppIcon.appiconset/`
6. Build & Run on your iPhone (⌘R)

## LiveContainer install (.ipa)

An unsigned **`.ipa`** is built automatically by GitHub Actions and published to:

**https://github.com/Black10998/paxdesign.booking/releases/download/ios-live-chat-v1.2.0/PAXDesignLiveChat.ipa**

1. Download `PAXDesignLiveChat.ipa` on your iPhone (Safari).
2. Open **LiveContainer** → import the `.ipa`.
3. Launch **PAX Live Chat** and sign in with your WordPress Application Password.

Push notifications may require additional signing configuration inside LiveContainer (JIT-less mode with your certificate). In-app polling and live-request alerts work without push.

## TestFlight distribution

See [TESTFLIGHT.md](./TESTFLIGHT.md) for step-by-step instructions.

> **Important:** TestFlight builds require your Apple Developer account. This repository provides the complete native source code; the signed IPA/TestFlight upload must be done on your Mac with your certificates.

## Default login

| Field | Example |
|-------|---------|
| Website | `https://paxdesign.at` |
| Username | Your WP admin username |
| Application Password | Generated in WordPress profile |

## Architecture

```
PAXDesignLiveChat/
├── App/                 # App entry, root navigation
├── Core/
│   ├── API/             # REST client (Application Password auth)
│   ├── Models/          # Codable types
│   └── Services/        # Auth, polling, push
└── Features/
    ├── Login/
    ├── Sessions/        # Chat list
    ├── Chat/            # Message thread
    └── Incoming/        # Call-style live request UI
```

## Bundle ID

`at.paxdesign.livechat` — change in `project.yml` if needed, and match APNs configuration.
