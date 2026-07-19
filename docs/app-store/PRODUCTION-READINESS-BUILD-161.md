# Production Readiness — Build 161 (In Progress)

**Status:** Build 161 implements the first production-hardening batch. **Do not submit to App Review** until physical-device QA passes for all items below.

## Build 161 — Changes in This Batch

### 1. Staff notifications
| Fix | Root cause | Files |
|-----|------------|-------|
| Dedupe human-queue alerts | `session_sync` + `new_customer_message` both fired for admin-queue customer messages | `class-paxdesign-apns.php` |
| Foreground duplicate banners | System banner + local notification both shown when app active | `InAppNotificationCoordinator.swift` |
| APNs 429 retry | No retry on Apple rate limit | `class-paxdesign-apns.php` |
| Token register 429 trip | `push-apns-register` tripped global circuit breaker | `NetworkCircuitBreaker.swift` |
| Bell under-count | Team unread excluded from shell bell | `StaffShellNotificationBell.swift` |

### 2. Persistent customer chat (website)
| Fix | Root cause | Files |
|-----|------------|-------|
| Server-authoritative session ID | Widget created local `pax_*` IDs before server sync | `chat-script.js` `getSessionId()` |
| New conversation via REST | Persistent accounts used client-only session rotation | `chat-script.js` `startNewConversation()` |

### 3. Orders module
| Fix | Root cause | Files |
|-----|------------|-------|
| Detail decode failure | Note `id` returned as string from MySQL | `class-paxdesign-customer-orders.php` |
| Save decode failure | PATCH returned customer shape missing `customer_name` | `class-paxdesign-customer-orders.php` |

### 4. Live Agent handoff
| Fix | Root cause | Files |
|-----|------------|-------|
| SSE stream handoff | Website SSE path skipped intent detection | `class-paxdesign-chat.php` |
| Broader phrase detection | Natural language not matching regex | `class-paxdesign-language-routing.php` |
| iOS handoff field | `handoff` flag not decoded | `CustomerAPIClient.swift` |

### 5. Take Over / Übernehmen
| Fix | Root cause | Files |
|-----|------------|-------|
| Dedicated permission | Any `reply_chats` user could take over | `class-paxdesign-live-chat-permissions.php` |
| Server enforcement | Web AJAX only checked `manage_options` | `class-paxdesign-chat-live.php`, mobile API |
| iOS UI gating | Take Over shown for all reply users | `ChatView.swift`, `LiveTabView.swift`, `ChatCoordinator.swift`, `PermissionGate.swift` |

### 6. News push
| Fix | Root cause | Files |
|-----|------------|-------|
| Deep link slug | Push used numeric ID; app expects slug | `class-paxdesign-customer-notifications.php` |
| Audience filtering | Broadcast ignored news audience rules | same |

## Remaining Before App Store Resubmission

- [ ] Staff UI full polish (SVG icons audit, tab bar glass parity with customer app)
- [ ] Full end-to-end QA on physical iPhone (both apps, fresh install)
- [ ] Staff chat architecture cleanup (temp sessions, loading loops)
- [ ] WordPress deploy 3.152.8 to production
- [ ] Messaging reliability CI green
- [ ] Customer chat: block guest AJAX for logged-in users (server-side rewrite in poll/send handlers)
- [ ] Notification badge per-item read sync audit
- [ ] Incoming live request fullscreen + ringtone wiring

## Versions

| Component | Version |
|-----------|---------|
| WordPress plugin | 3.152.8 |
| iOS build | 161 |
| iOS marketing | 2.1.0 |
