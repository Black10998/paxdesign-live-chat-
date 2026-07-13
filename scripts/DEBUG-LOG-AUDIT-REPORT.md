# تقرير تدقيق debug.log — `email_mapped_to_login`

**الإصدار:** 3.112.0 (فرع `cursor/debug-log-audit-b37f`)  
**الملف الرئيسي:** `includes/class-paxdesign-live-chat-mobile-api.php`

---

## 1. أين تُكتب الرسالة؟

| العنصر | التفاصيل |
|--------|----------|
| **الدالة** | `PAXdesign_Live_Chat_Mobile_API::resolve_basic_auth_login()` |
| **الخطاف** | `add_filter('determine_current_user', …, 15)` |
| **التسجيل (سابقًا)** | `log_auth_event('email_mapped_to_login', …)` → `error_log()` |
| **الملف** | `class-paxdesign-live-chat-mobile-api.php` (حوالي السطر 77–122) |

---

## 2. لماذا تتكرر عشرات/مئات المرات في ثوانٍ؟

ثلاثة عوامل مجتمعة:

### أ) كل طلب REST من تطبيق iOS يمرّ بهذا الفلتر

التطبيق يرسل `Authorization: Basic …` مع **البريد الإلكتروني** (`sarah.gta1995@gmail.com`) بدل `user_login`. الفلتر يحوّل البريد إلى login قبل مصادقة Application Password.

### ب) ووردبريس يستدعي `determine_current_user` عدة مرات في **نفس الطلب**

هذا سلوك معروف في WordPress — أثناء طلب REST واحد قد يُستدعى الفلتر **10–50+ مرة** قبل اكتمال المصادقة. في كل مرة كان `$user_id` لا يزال `false`، فكان الكود يعيد:

- `get_user_by('email', …)` — استعلام قاعدة بيانات
- `error_log(email_mapped_to_login)` — سطر في debug.log

### ج) تطبيق iOS يرسل عشرات الطلبات المتوازية

SSE + polling + ack + قوائم الجلسات = **عشرات طلبات REST في الثانية**.  
مثال من السجلات الحية: **80+ سطر في 4 ثوانٍ** (02:53:22 UTC).

### د) حالة خاصة: `user_login` يساوي البريد

عندما يكون `login` و`email` نفس القيمة (`sarah.gta1995@gmail.com`):

- `is_email($login)` يبقى `true` في كل استدعاء للفلتر
- لا يوجد فرق بعد «التحويل» — القيمة نفسها
- لذلك كان التسجيل يتكرر **في كل استدعاء** وليس مرة واحدة فقط

---

## 3. هل البحث عن المستخدم يُنفَّذ في كل Request بدون تخزين؟

**نعم — سابقًا:**

- لا كان هناك cache لنتيجة التحويل داخل الطلب
- `get_user_by('email')` كان يُنفَّذ في كل استدعاء للفلتر حتى داخل نفس HTTP request
- لا يوجد cache عبر الطلبات (وهذا مقبول — المصادقة يجب أن تبقى حية لكل طلب)

**بعد الإصلاح (3.112.0):**

- `$basic_auth_email_resolved` — علم ثابت **لكل طلب HTTP** يمنع إعادة المعالجة
- فحص `get_user_by('login')` أولاً — إذا كان login صالحاً (بما في ذلك عندما يساوي البريد) لا حاجة لبحث email
- `get_user_by('email')` يُنفَّذ **مرة واحدة كحد أقصى** لكل طلب

---

## 4. هل الرسالة للتطوير (Debug) فقط؟

**نعم — كانت مخصصة للتشخيص فقط**، لكن الشرط كان خاطئاً:

```php
// قبل الإصلاح
if (!defined('WP_DEBUG') || !WP_DEBUG) { return; }
error_log($line);
```

على `paxdesign.at`: **`WP_DEBUG = true`** في الإنتاج (يظهر من وجود `wp-content/debug.log` وكتابة الرسائل).  
لذلك كانت تُكتب في الإنتاج رغم أنها ليست أخطاء حقيقية.

**المعيار الصحيح في WordPress:** التسجيل التشخيصي يجب أن يتطلب **`WP_DEBUG` و `WP_DEBUG_LOG` معاً**.

---

## 5. ماذا تم تغييره في 3.112.0؟

| التغيير | الحالة |
|---------|--------|
| **حذف** `email_mapped_to_login` بالكامل | ✅ تم |
| الإبقاء على `email_lookup_failed` **فقط** عند فشل البحث | ✅ مع `WP_DEBUG && WP_DEBUG_LOG` |
| إضافة `is_debug_logging_enabled()` | ✅ |
| Cache per-request `$basic_auth_email_resolved` | ✅ |
| فحص `get_user_by('login')` قبل email | ✅ |
| `PAXdesign_Chat::log_event()` — كان بدون شرط | ✅ أصبح يتطلب `WP_DEBUG_LOG` |
| Device Sessions `error_log` | ✅ أصبح يتطلب `WP_DEBUG_LOG` |

---

## 6. مراجعة كل استدعاءات التسجيل في الإضافة

| الملف | الاستدعاء | متى يُكتب | الحكم |
|-------|-----------|-----------|-------|
| `class-paxdesign-live-chat-mobile-api.php` | `log_auth_event` | كان كل طلب REST + iOS | **أُزيل النجاح؛ الفشل فقط مع WP_DEBUG_LOG** |
| `class-paxdesign-live-chat-mobile-api.php` | Device Sessions `error_log` | عند استثناء | **مقيّد بـ WP_DEBUG_LOG** |
| `class-paxdesign-chat.php` | `log_event` | OpenAI/worker (كل نجاح/فشل) | **مقيّد بـ WP_DEBUG_LOG** |
| `class-paxdesign-apns.php` | `error_log` | أخطاء نقل APNs فقط | **يُبقى** — أخطاء حقيقية |
| `class-paxdesign-apns.php` | `log_delivery` | `update_option` — ليس debug.log | **لا مشكلة** |
| `paxdesign-booking.php` | `error_log` | فشل بريد / DB / migration | **يُبقى** — أخطاء تشغيلية |

**لم يُعثر على:** `debug_log()` أو `write_log()` (WooCommerce) داخل الإضافة.

---

## 7. توصية للإنتاج

لتصغير `debug.log` بشكل عام على الموقع:

```php
// wp-config.php — للإنتاج
define('WP_DEBUG', true);       // يمكن إبقاؤه للتطوير
define('WP_DEBUG_LOG', false);  // يوقف الكتابة إلى debug.log
define('WP_DEBUG_DISPLAY', false);
```

مع 3.112.0: حتى لو `WP_DEBUG_LOG = true`، لن تظهر رسائل `email_mapped_to_login` بعد الآن.

---

## 8. الملفات المعدّلة

- `includes/class-paxdesign-live-chat-mobile-api.php`
- `includes/class-paxdesign-chat.php`
- `paxdesign-booking.php` (3.112.0)
