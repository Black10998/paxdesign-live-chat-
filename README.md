# PAXdesign Live Chat

Production-ready real-time customer support platform with AI Assistant, Live Agent, WordPress plugin, and native iOS admin application.

## Repository structure

- `paxdesign-booking/` — WordPress plugin (booking + live chat + admin dashboard)
- `paxdesign-booking/ios-live-chat/` — Native iOS admin app (SwiftUI)
- `scripts/build-release.sh` — Build production WordPress ZIP (and IPA on macOS)

## Current versions

| Component | Version |
|-----------|---------|
| WordPress plugin | 3.43.0 |
| iOS app | 1.3.1 (build 6) |

## Production release

```bash
chmod +x scripts/build-release.sh
./scripts/build-release.sh
```

WordPress ZIP output: `dist/paxdesign-booking-v3.43.0.zip`

iOS IPA (macOS + Xcode only):

```bash
BUILD_IOS=1 ./scripts/build-release.sh
```

## WordPress updates

The plugin checks GitHub Releases on `Black10998/paxdesign-live-chat-` for one-click updates from the WordPress dashboard.
