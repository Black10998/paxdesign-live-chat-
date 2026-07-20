# Production Release — Build 168

**Date:** 2026-07-20  
**WordPress:** 3.158.0  
**iOS MARKETING_VERSION:** 2.1.0  
**iOS CFBundleVersion:** 168  

## Changes

1. **Customer chat composer** — removed nested bottom `safeAreaInset` that hid the composer behind the tab bar; composer now sits in the chat layout above the shell tab bar and keyboard.
2. **Services page redesign** — Apple-quality black-and-white catalog with large imagery, premium typography, hairline separators, and restrained CTAs (no neumorphism / rotating discs / decorative ribbons).

## Publish pipeline (completed)

| Step | Result | Run |
|------|--------|-----|
| Merge to `main` | ✅ `2256e88` | [PR #141](https://github.com/Black10998/paxdesign-live-chat-/pull/141) |
| WordPress 3.158.0 | ✅ live `chat-script.js?ver=3.158.0` | [29713765112](https://github.com/Black10998/paxdesign-live-chat-/actions/runs/29713765112) |
| App Store IPA (`CFBundleVersion=168`) | ✅ | [29713765061](https://github.com/Black10998/paxdesign-live-chat-/actions/runs/29713765061) |
| TestFlight upload | ✅ IPA 168 validated | [29713897270](https://github.com/Black10998/paxdesign-live-chat-/actions/runs/29713897270) |
| Internal TestFlight verify | ✅ `IN_BETA_TESTING` | [29714351567](https://github.com/Black10998/paxdesign-live-chat-/actions/runs/29714351567) |
