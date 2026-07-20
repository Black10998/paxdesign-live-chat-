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

## Publish pipeline (completed)

| Step | Result | Run |
|------|--------|-----|
| Merge to `main` | ✅ `ed80347` | [PR #140](https://github.com/Black10998/paxdesign-live-chat-/pull/140) |
| WordPress production deploy (3.157.0) | ✅ | [29712927163](https://github.com/Black10998/paxdesign-live-chat-/actions/runs/29712927163) |
| App Store signed IPA (`CFBundleVersion=167`) | ✅ | [29712927173](https://github.com/Black10998/paxdesign-live-chat-/actions/runs/29712927173) |
| TestFlight upload + internal enable | ✅ IPA build 167 validated | [29713007066](https://github.com/Black10998/paxdesign-live-chat-/actions/runs/29713007066) |
| Internal TestFlight verify | ✅ `IN_BETA_TESTING`, group has build 167 | [29713334481](https://github.com/Black10998/paxdesign-live-chat-/actions/runs/29713334481) |

### Match confirmation

- Live site serves `chat-script.js?ver=3.157.0`
- App Store / WordPress deploy SHA: `ed80347` (merge of completed UX work + compile fix)
- TestFlight IPA: `CFBundleVersion=167` (matches `project.yml`)
- ASC: build 167 `processingState=VALID`, `internalBuildState=IN_BETA_TESTING`
