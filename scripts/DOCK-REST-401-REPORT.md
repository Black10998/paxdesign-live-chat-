# تقرير: REST 401 لـ `/workers` و `/queue/stats`

## المصدر

| العنصر | التفاصيل |
|--------|----------|
| **الإضافة** | `paxdesign-toolbar` (v9.1.0) |
| **الملف** | `wp-content/plugins/paxdesign-toolbar/assets/js/dock.js` |
| **الدالة** | `loadDeferredInitData()` — تُستدعى تلقائيًا عند `init()` لكل زائر |
| **REST base** | `PDX_CONFIG.restUrl` → `https://paxdesign.at/wp-json/pdx/v1` |

## لماذا تظهر 401 في Console؟

1. **الواجهة العامة** تحمّل `dock.js` للجميع (`PDX_CONFIG.userId = "0"`, `isLoggedIn = ""`).
2. بعد ~350ms / `requestIdleCallback`، `loadDeferredInitData()` يستدعي بدون شرط:
   - `GET /workers`
   - `GET /queue/stats`
   - (+ `/billing/status`, `/teams`, SSE `activity`/`queue`)
3. هذه المسارات **إدارية** — `permission_callback` في PHP يتطلب صلاحيات (admin).
4. الزائر يرسل `X-WP-Nonce` من `PDX_CONFIG.nonce` لكن **بدون جلسة WordPress** → الاستجابة الصحيحة: **401 `rest_forbidden`**.

هذا **ليس** خطأ iOS ولا 403 من LiteSpeed — سلوك REST متوقع لطلب غير مخوّل.

## `ERR_BLOCKED_BY_CLIENT` (Cloudflare)

`static.cloudflareinsights.com/beacon.min.js` — حظر من **Brave Shields / uBlock** في المتصفح، وليس من الخادم.

## الإصلاح (منشور)

في `dock.js`:

- `canAccessAuthenticatedDock()` — مسارات الفوترة للمستخدم المسجّل فقط
- `canAccessInfrastructure()` — `/workers`, `/queue/stats`, `/teams`, SSE للمسؤول (`is_admin`) فقط
- `/pay/status` يبقى للجميع (تحقق وصول الوحدات العامة)
- عند تسجيل الدخول: حدث `pdx-session-updated` من `pdx-auth.js` يعيد تحميل البيانات المؤهّلة

## الملفات المعدّلة

- `paxdesign-toolbar/assets/js/dock.js`
- `paxdesign-toolbar/assets/js/pdx-auth.js`
- `.github/workflows/deploy-toolbar-dock-fix.yml`

## التحقق بعد النشر

1. افتح الموقع كزائر في نافذة خاصة (بدون تسجيل WP).
2. Console → Network: **لا** يجب أن ترى طلبات إلى `/wp-json/pdx/v1/workers` أو `/queue/stats`.
3. سجّل دخول كمسؤول: الطلبات تعود مع `X-WP-Nonce` صالح و cookies الجلسة.
