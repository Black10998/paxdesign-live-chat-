# Production Release Plan

## Release policy (confirmed)

- **No TestFlight uploads** until all remaining work is complete.
- **No App Store submission** until full end-to-end QA passes on physical devices.
- **One final build** will be created after WordPress deploy + complete audit.

Build 161 on TestFlight is an intermediate batch — do **not** use it for App Review.

---

## Remaining work checklist

### Backend / WordPress
- [x] Batch 1: orders decode, push dedupe, news slug, takeover permission, handoff patterns
- [x] Server-side AJAX session rewrite (`resolve_ajax_session`) — in progress this branch
- [ ] Guest AJAX logged-in hardening (close, rating, reaction, history handlers)
- [ ] `class-paxdesign-chat-log.php` transcript sync parity
- [ ] Database stability audit (`PAXdesign_DB` drain, shutdown hooks)
- [ ] Deploy plugin **3.152.8+** to production (after all backend fixes)
- [ ] Production deploy verification

### Customer chat
- [x] Website client session ID for logged-in users (partial)
- [x] Poll returns authoritative `session_id`
- [ ] Poll handler adopts server `session_id` in JS
- [ ] Eliminate all orphan guest sessions (server merge/backfill script)
- [ ] iPhone + website + REST single primary conversation verified

### Staff chat (iOS)
- [x] Reduce SSE poll storm (no typing → postSessionSync)
- [x] Live tab: takeover before navigate
- [ ] History recovery cap (prevent infinite reload)
- [ ] Remove synthetic push stub sessions
- [ ] Lazy thread registry (ConversationLocalSync)
- [ ] Team session dedup by sessionId
- [ ] Loading state machine (thread + list)

### Notifications
- [x] Batch 1: dedupe, foreground fix, bell team count
- [ ] Per-notification read → badge decrement verified
- [ ] Deep link navigation audit (staff + customer)
- [ ] Push + poll dedup gate in ChatCoordinator

### Staff UI
- [ ] Customer app glass tab bar parity (optional: migrate CustomerTabView to UiverseMenuBarView)
- [ ] Unify Staff iPhone/iPad tab glyphs
- [ ] Replace remaining SF Symbol shell chrome with PAXIcon
- [ ] Empty states use PAXIcon not ContentUnavailableView SF

### QA (physical iPhone required)
- [ ] Fresh install both apps
- [ ] Every screen / button / menu / setting
- [ ] All permission combinations (takeover vs reply-only)
- [ ] Chat: send, handoff, takeover, release, attachments
- [ ] Orders: list + detail + save with notes
- [ ] News push → correct article
- [ ] Notification allow / deny / not now
- [ ] Website logged-in chat ↔ app sync

---

## Final deliverables (when complete)

1. Root cause document for every issue
2. Files changed summary
3. Full QA report
4. CI run numbers (single final build)
5. Production deployment confirmation
6. Final TestFlight build number

---

## Current branch

`cursor/production-release-complete-828a` — active development, **no CI triggers**.
