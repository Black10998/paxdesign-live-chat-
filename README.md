# PAXdesign Live Chat

Production real-time customer support: WordPress plugin with browser admin panel and native iOS admin app.

## Repository layout

| Path | Description |
|------|-------------|
| `paxdesign-booking/` | WordPress plugin (booking, live chat, REST API) |
| `paxdesign-booking/ios-live-chat/` | Native iOS admin app (SwiftUI) |
| `scripts/build-release.sh` | Build plugin ZIP locally |
| `.github/workflows/release.yml` | Automated release (ZIP + IPA on tag push) |

## Current versions

| Component | Version |
|-----------|---------|
| WordPress plugin | **3.49.0** |
| iOS app | **1.6.0** (build 14) |

## Releases

Published at: **https://github.com/Black10998/paxdesign-live-chat-/releases**

Each release includes:

- `paxdesign-booking-vX.Y.Z.zip` — upload to WordPress
- `PAXDesignLiveChat.ipa` — install on iPhone (LiveContainer, AltStore, or Xcode)

### Create a new release

1. Bump versions in `paxdesign-booking/paxdesign-booking.php` and `ios-live-chat/project.yml`
2. Commit and push to `main`
3. Tag and push:

```bash
git tag -a v3.47.0 -m "v3.47.0"
git push origin v3.47.0
```

GitHub Actions builds both assets and attaches them to the release automatically.

### Local build (optional)

```bash
chmod +x scripts/build-release.sh
./scripts/build-release.sh
# ZIP → dist/paxdesign-booking-vX.Y.Z.zip

# IPA (macOS + Xcode only):
cd paxdesign-booking/ios-live-chat && ./scripts/build-ipa.sh
```

## WordPress deployment

1. Download the latest plugin ZIP from [Releases](https://github.com/Black10998/paxdesign-live-chat-/releases)
2. WordPress Admin → Plugins → Add New → Upload Plugin
3. Activate **PAXdesign Booking System**

The plugin checks GitHub Releases for one-click updates from the WordPress dashboard.

## iOS app

See [paxdesign-booking/ios-live-chat/README.md](paxdesign-booking/ios-live-chat/README.md) for login setup, Application Passwords, and APNs configuration.

**Latest test IPA:** download `PAXDesignLiveChat.ipa` from the [latest GitHub Release](https://github.com/Black10998/paxdesign-live-chat-/releases/latest).

## Features (iOS admin)

- Real-time messaging (REST polling, same backend as browser)
- AI reply suggestions + quick replies (Schnellantworten)
- Customer typing indicator
- Like/dislike feedback (per-message + session rating)
- Push notifications for live agent requests
- Incoming call-style alert for new live requests
