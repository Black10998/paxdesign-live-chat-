# Production Release — Build 169

**Date:** 2026-07-20  
**WordPress:** unchanged (3.158.0)  
**iOS MARKETING_VERSION:** 2.1.0  
**iOS CFBundleVersion:** 169  

## Fixes

1. **Tab bar & scrolling (Customer + Staff)** — Restored overlay tab bar + explicit `paxShellScrollClearance` (`shellTabBarScrollInset`). The previous `safeAreaInset` tab bar left ScrollView/List content cut off behind the bar in ZStack shells.
2. **Customer chat composer** — Composer uses `.safeAreaInset(edge: .bottom)` again so it stacks above the tab-bar clearance pad (and keyboard), remaining fully tappable.
3. **Services page** — Removed non-Services quick-menu chrome from the toolbar. Service cards use curated high-quality photos per slug (plus any API `image_url`), not generic vector icons.

## Publish pipeline (completed)

| Step | Result | Run |
|------|--------|-----|
| Merge to `main` | ✅ `2078a80` | [PR #142](https://github.com/Black10998/paxdesign-live-chat-/pull/142) |
| App Store IPA (`CFBundleVersion=169`) | ✅ | [29714757631](https://github.com/Black10998/paxdesign-live-chat-/actions/runs/29714757631) |
| TestFlight upload | ✅ IPA 169 validated | [29714931534](https://github.com/Black10998/paxdesign-live-chat-/actions/runs/29714931534) |
| Internal TestFlight verify | ✅ `IN_BETA_TESTING` | [29715165525](https://github.com/Black10998/paxdesign-live-chat-/actions/runs/29715165525) |

WordPress left at **3.158.0** (no website changes).
