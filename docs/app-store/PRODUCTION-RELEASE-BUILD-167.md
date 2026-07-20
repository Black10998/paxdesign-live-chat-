# Production Release — Build 167

**Date:** 2026-07-20  
**WordPress:** 3.157.0  
**iOS MARKETING_VERSION:** 2.1.0  
**iOS CFBundleVersion:** 167  
**Branch:** `cursor/finalize-publish-build-167-554f` → `main`

## Summary

Finalizes the completed pre-TestFlight UX work (PR #139) into `main`, publishes WordPress 3.157.0, and ships iOS Build 167 to TestFlight.

## Changes

### WordPress (3.157.0)

- Website chat widget: remove name prompt / live-confirm gate so Live Chat and KI-Assistent start immediately
- Guest chat auth actions: Sign In + Create Account remain available when authentication is required

### iOS (Build 167)

- Customer Chat guest panel with Sign In + Create Account
- Expanded SVG icon catalog (unique glyphs; no aliased unrelated icons)
- Shorter tab bar, flush to home indicator via `safeAreaInset`
- Shell scroll clearance across customer panes
- Home Screen widget layout redesign (Small / Medium / Large)
- Compile fix: `CustomerChatView` brace structure after guest-auth refactor

## Publish pipeline

| Step | Trigger |
|------|---------|
| WordPress production deploy | push `main` + `paxdesign-booking/**` / deploy trigger |
| App Store signed IPA | `.github/triggers/appstore-build` |
| TestFlight upload | `.github/triggers/testflight-upload` |
| Internal TestFlight verify | `.github/triggers/testflight-verify-internal` |
