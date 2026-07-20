# Production Release — Build 168

**Date:** 2026-07-20  
**WordPress:** 3.158.0  
**iOS MARKETING_VERSION:** 2.1.0  
**iOS CFBundleVersion:** 168  

## Changes

1. **Customer chat composer** — removed nested bottom `safeAreaInset` that hid the composer behind the tab bar; composer now sits in the chat layout above the shell tab bar and keyboard.
2. **Services page redesign** — Apple-quality black-and-white catalog with large imagery, premium typography, hairline separators, and restrained CTAs (no neumorphism / rotating discs / decorative ribbons).

## Pipeline

| Step | Trigger |
|------|---------|
| WordPress deploy | push `main` + plugin |
| App Store IPA | `.github/triggers/appstore-build` |
| TestFlight | `.github/triggers/testflight-upload` |
