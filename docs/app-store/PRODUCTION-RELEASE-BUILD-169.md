# Production Release — Build 169

**Date:** 2026-07-20  
**WordPress:** unchanged (3.158.0)  
**iOS MARKETING_VERSION:** 2.1.0  
**iOS CFBundleVersion:** 169  

## Fixes

1. **Tab bar & scrolling (Customer + Staff)** — Restored overlay tab bar + explicit `paxShellScrollClearance` (`shellTabBarScrollInset`). The previous `safeAreaInset` tab bar left ScrollView/List content cut off behind the bar in ZStack shells.
2. **Customer chat composer** — Composer uses `.safeAreaInset(edge: .bottom)` again so it stacks above the tab-bar clearance pad (and keyboard), remaining fully tappable.
3. **Services page** — Removed non-Services quick-menu chrome from the toolbar. Service cards use curated high-quality photos per slug (plus any API `image_url`), not generic vector icons.

## Pipeline

| Step | Trigger |
|------|---------|
| App Store IPA | `.github/triggers/appstore-build` |
| TestFlight | `.github/triggers/testflight-upload` |
