# PAXDesign Live Chat — Native iOS Admin App

SwiftUI administrator app for the PAXdesign Booking Live Chat system.

## Requirements

| Requirement | Details |
|-------------|---------|
| iPhone | iOS 16+ |
| WordPress | Plugin **v3.46.0+** with mobile REST API |
| Account | WP admin with `manage_options` + Application Password |
| Mac (optional) | Xcode 16+ for local builds |

## Install (test build)

1. Deploy the latest WordPress plugin from [GitHub Releases](https://github.com/Black10998/paxdesign-live-chat-/releases/latest)
2. On your iPhone, download **`PAXDesignLiveChat.ipa`** from the same release
3. Install via **LiveContainer**, AltStore, or Xcode
4. Open the app and sign in:
   - **Website:** `https://paxdesign.at` (or your site)
   - **Username:** your WordPress login or email
   - **Application Password:** from WP Admin → Users → Profile → Application Passwords

## App icon

The official icon is bundled in `Resources/Assets.xcassets/AppIcon.appiconset/` (1024×1024 PNG).

## Build from source (Mac)

```bash
brew install xcodegen
cd ios-live-chat
xcodegen generate
open PAXDesignLiveChat.xcodeproj
```

In Xcode: set your **Team**, enable **Push Notifications**, then Run on device.

Unsigned IPA (CI uses the same script):

```bash
./scripts/build-ipa.sh
# Output: build/output/PAXDesignLiveChat.ipa
```

## Push notifications (optional)

Configure APNs in WordPress:

```php
update_option('paxdesign_apns_key_id', 'YOUR_KEY_ID');
update_option('paxdesign_apns_team_id', 'YOUR_TEAM_ID');
update_option('paxdesign_apns_key_p8', file_get_contents('AuthKey_XXXX.p8'));
update_option('paxdesign_apns_bundle_id', 'at.paxdesign.livechat');
```

Enable Push Notifications for bundle ID `at.paxdesign.livechat` in Apple Developer.

## REST API

Base: `https://your-site/wp-json/paxdesign/v1/live-admin/`  
Auth: HTTP Basic (`username:application_password`)

## App Store preparation (later)

- Bundle ID: `at.paxdesign.livechat`
- Signing: Apple Developer Program required
- Push: production APNs certificate/key
- Privacy: document camera/photos usage if profile image picker is kept

## Architecture

```
PAXDesignLiveChat/
├── App/                 # Entry point
├── Core/
│   ├── API/             # REST client
│   ├── Models/          # Codable types
│   ├── Services/        # Auth, polling, push, audio
│   └── Design/          # Theme + components
└── Features/
    ├── Login/
    ├── Sessions/        # Chat list
    ├── Chat/            # Thread + composer + AI assist
    ├── Incoming/        # Live request alert
    └── Settings/
```
