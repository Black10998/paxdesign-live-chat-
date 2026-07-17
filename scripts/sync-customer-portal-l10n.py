#!/usr/bin/env python3
"""Add Customer Portal String(localized:) entries to Localizable.xcstrings with DE/AR."""

import json
import re
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
PORTAL = ROOT / "paxdesign-booking/ios-live-chat/PAXDesignLiveChat/Features/CustomerPortal"
XCSTRINGS = ROOT / "paxdesign-booking/ios-live-chat/PAXDesignLiveChat/Resources/Localizable.xcstrings"

# German / Arabic translations for customer portal copy.
TR = {
    "AI Assistant": ("KI-Assistent", "المساعد الذكي"),
    "About": ("Über", "حول"),
    "About this app": ("Über diese App", "حول هذا التطبيق"),
    "Account": ("Konto", "الحساب"),
    "Active Projects": ("Aktive Projekte", "المشاريع النشطة"),
    "Activity": ("Aktivität", "النشاط"),
    "All": ("Alle", "الكل"),
    "Announcement": ("Mitteilung", "إعلان"),
    "Ask a question about your project, order, or service. Our assistant is here to help.": (
        "Stellen Sie eine Frage zu Ihrem Projekt, Auftrag oder Service. Unser Assistent hilft Ihnen gerne.",
        "اطرح سؤالاً عن مشروعك أو طلبك أو خدمتك. مساعدنا هنا لمساعدتك.",
    ),
    "Assigned": ("Zugewiesen", "مُعيَّن"),
    "Attachment": ("Anhang", "مرفق"),
    "Back": ("Zurück", "رجوع"),
    "Browse Services": ("Services entdecken", "تصفح الخدمات"),
    "Cancel": ("Abbrechen", "إلغاء"),
    "Chat": ("Chat", "الدردشة"),
    "Check your email to verify your account.": (
        "Prüfen Sie Ihre E-Mail, um Ihr Konto zu bestätigen.",
        "تحقق من بريدك الإلكتروني لتأكيد حسابك.",
    ),
    "Choose a project to view milestones, files, and activity.": (
        "Wählen Sie ein Projekt, um Meilensteine, Dateien und Aktivitäten anzuzeigen.",
        "اختر مشروعاً لعرض المراحل والملفات والنشاط.",
    ),
    "Confirm password": ("Passwort bestätigen", "تأكيد كلمة المرور"),
    "Contact Support": ("Support kontaktieren", "تواصل مع الدعم"),
    "Conversation": ("Unterhaltung", "المحادثة"),
    "Conversations": ("Unterhaltungen", "المحادثات"),
    "Conversations unavailable": ("Unterhaltungen nicht verfügbar", "المحادثات غير متاحة"),
    "Create account": ("Konto erstellen", "إنشاء حساب"),
    "Creating…": ("Wird erstellt…", "جارٍ الإنشاء…"),
    "Customer": ("Kunde", "العميل"),
    "Delete account": ("Konto löschen", "حذف الحساب"),
    "Delete my account": ("Mein Konto löschen", "حذف حسابي"),
    "Describe your request": ("Beschreiben Sie Ihre Anfrage", "صف طلبك"),
    "Details": ("Details", "التفاصيل"),
    "Display name": ("Anzeigename", "اسم العرض"),
    "Done": ("Fertig", "تم"),
    "Download shared documents, quotes, and invoices.": (
        "Laden Sie geteilte Dokumente, Angebote und Rechnungen herunter.",
        "حمّل المستندات والعروض والفواتير المشتركة.",
    ),
    "Email or username": ("E-Mail oder Benutzername", "البريد أو اسم المستخدم"),
    "End chat": ("Chat beenden", "إنهاء المحادثة"),
    "Everything about your projects, requests, and conversations in one place.": (
        "Alles zu Ihren Projekten, Anfragen und Unterhaltungen an einem Ort.",
        "كل ما يخص مشاريعك وطلباتك ومحادثاتك في مكان واحد.",
    ),
    "Explore our latest work.": ("Entdecken Sie unsere neuesten Arbeiten.", "استكشف أحدث أعمالنا."),
    "Files": ("Dateien", "الملفات"),
    "Files & Invoices": ("Dateien & Rechnungen", "الملفات والفواتير"),
    "Forgot password?": ("Passwort vergessen?", "نسيت كلمة المرور؟"),
    "Gallery": ("Galerie", "المعرض"),
    "History": ("Verlauf", "السجل"),
    "Home": ("Start", "الرئيسية"),
    "Loading chat…": ("Chat wird geladen…", "جارٍ تحميل الدردشة…"),
    "Loading dashboard…": ("Dashboard wird geladen…", "جارٍ تحميل لوحة التحكم…"),
    "Loading news…": ("News werden geladen…", "جارٍ تحميل الأخبار…"),
    "Loading portfolio…": ("Portfolio wird geladen…", "جارٍ تحميل الأعمال…"),
    "Loading projects…": ("Projekte werden geladen…", "جارٍ تحميل المشاريع…"),
    "Loading services…": ("Services werden geladen…", "جارٍ تحميل الخدمات…"),
    "Location permission denied.": ("Standortberechtigung verweigert.", "تم رفض إذن الموقع."),
    "Message": ("Nachricht", "رسالة"),
    "Milestones": ("Meilensteine", "المراحل"),
    "My location": ("Mein Standort", "موقعي"),
    "News": ("News", "الأخبار"),
    "News unavailable": ("News nicht verfügbar", "الأخبار غير متاحة"),
    "New request": ("Neue Anfrage", "طلب جديد"),
    "No active projects yet.": ("Noch keine aktiven Projekte.", "لا توجد مشاريع نشطة بعد."),
    "No announcements yet": ("Noch keine Mitteilungen", "لا توجد إعلانات بعد"),
    "No messages yet. Open Chat to start talking with our team.": (
        "Noch keine Nachrichten. Öffnen Sie den Chat, um mit unserem Team zu sprechen.",
        "لا توجد رسائل بعد. افتح الدردشة للتحدث مع فريقنا.",
    ),
    "No portfolio items yet": ("Noch keine Portfolio-Einträge", "لا توجد عناصر في المعرض بعد"),
    "No projects yet": ("Noch keine Projekte", "لا توجد مشاريع بعد"),
    "No requests yet": ("Noch keine Anfragen", "لا توجد طلبات بعد"),
    "No description.": ("Keine Beschreibung.", "لا يوجد وصف."),
    "Notes": ("Notizen", "ملاحظات"),
    "Notifications": ("Benachrichtigungen", "الإشعارات"),
    "Offline — messages will send when you reconnect.": (
        "Offline — Nachrichten werden gesendet, sobald Sie wieder online sind.",
        "غير متصل — ستُرسل الرسائل عند إعادة الاتصال.",
    ),
    "Open Chat": ("Chat öffnen", "فتح الدردشة"),
    "Open Files": ("Dateien öffnen", "فتح الملفات"),
    "Orders": ("Aufträge", "الطلبات"),
    "Our latest work will appear here.": (
        "Unsere neuesten Arbeiten erscheinen hier.",
        "ستظهر أحدث أعمالنا هنا.",
    ),
    "Password": ("Passwort", "كلمة المرور"),
    "Photo": ("Foto", "صورة"),
    "Play voice message": ("Sprachnachricht abspielen", "تشغيل الرسالة الصوتية"),
    "Portfolio": ("Portfolio", "معرض الأعمال"),
    "Portfolio unavailable": ("Portfolio nicht verfügbar", "المعرض غير متاح"),
    "Project": ("Projekt", "المشروع"),
    "Projects": ("Projekte", "المشاريع"),
    "Projects unavailable": ("Projekte nicht verfügbar", "المشاريع غير متاحة"),
    "Read": ("Gelesen", "مقروء"),
    "Recent Requests": ("Letzte Anfragen", "الطلبات الأخيرة"),
    "Reference": ("Referenz", "المرجع"),
    "Request": ("Anfrage", "طلب"),
    "Request a service on our website to get started.": (
        "Fordern Sie einen Service auf unserer Website an, um zu starten.",
        "اطلب خدمة من موقعنا للبدء.",
    ),
    "Requesting location…": ("Standort wird abgerufen…", "جارٍ طلب الموقع…"),
    "Requests": ("Anfragen", "الطلبات"),
    "Requests unavailable": ("Anfragen nicht verfügbar", "الطلبات غير متاحة"),
    "Search services": ("Services suchen", "البحث في الخدمات"),
    "Security": ("Sicherheit", "الأمان"),
    "Select a project": ("Projekt auswählen", "اختر مشروعاً"),
    "Select a service": ("Service auswählen", "اختر خدمة"),
    "Sent": ("Gesendet", "مُرسَل"),
    "Services": ("Services", "الخدمات"),
    "Services unavailable": ("Services nicht verfügbar", "الخدمات غير متاحة"),
    "Settings": ("Einstellungen", "الإعدادات"),
    "Share": ("Teilen", "مشاركة"),
    "Share location": ("Standort teilen", "مشاركة الموقع"),
    "Shared location": ("Geteilter Standort", "موقع مشترك"),
    "Sign In": ("Anmelden", "تسجيل الدخول"),
    "Signing in…": ("Anmeldung…", "جارٍ تسجيل الدخول…"),
    "Something went wrong. Please try again.": (
        "Etwas ist schiefgelaufen. Bitte versuchen Sie es erneut.",
        "حدث خطأ. يرجى المحاولة مرة أخرى.",
    ),
    "Stop recording": ("Aufnahme stoppen", "إيقاف التسجيل"),
    "Submit request": ("Anfrage senden", "إرسال الطلب"),
    "Submitting…": ("Wird gesendet…", "جارٍ الإرسال…"),
    "Support": ("Support", "الدعم"),
    "Support is typing…": ("Support tippt…", "الدعم يكتب…"),
    "Team": ("Team", "الفريق"),
    "This conversation has ended. You can start a new one anytime.": (
        "Diese Unterhaltung wurde beendet. Sie können jederzeit eine neue starten.",
        "انتهت هذه المحادثة. يمكنك بدء محادثة جديدة في أي وقت.",
    ),
    "Unable to load": ("Laden nicht möglich", "تعذّر التحميل"),
    "Unable to load project": ("Projekt konnte nicht geladen werden", "تعذّر تحميل المشروع"),
    "Unable to load request": ("Anfrage konnte nicht geladen werden", "تعذّر تحميل الطلب"),
    "Updates from PAXDesign will appear here.": (
        "Updates von PAXDesign erscheinen hier.",
        "ستظهر تحديثات PAXDesign هنا.",
    ),
    "View Portfolio": ("Portfolio ansehen", "عرض المعرض"),
    "View live project": ("Live-Projekt ansehen", "عرض المشروع المباشر"),
    "Voice message": ("Sprachnachricht", "رسالة صوتية"),
    "Welcome back": ("Willkommen zurück", "مرحباً بعودتك"),
    "You": ("Sie", "أنت"),
    "You are offline. Connect to sign in.": (
        "Sie sind offline. Verbinden Sie sich, um sich anzumelden.",
        "أنت غير متصل. اتصل بالإنترنت لتسجيل الدخول.",
    ),
    "We couldn't load your messages. Pull down to refresh.": (
        "Nachrichten konnten nicht geladen werden. Ziehen Sie nach unten zum Aktualisieren.",
        "تعذّر تحميل رسائلك. اسحب للأسفل للتحديث.",
    ),
    "Your active work will appear here.": (
        "Ihre aktiven Arbeiten erscheinen hier.",
        "سيظهر عملك النشط هنا.",
    ),
    "Submit a service request from the Services tab.": (
        "Senden Sie eine Service-Anfrage über den Tab Services.",
        "قدّم طلب خدمة من تبويب الخدمات.",
    ),
    "Update": ("Update", "تحديث"),
    "Open Files & Invoices": ("Dateien & Rechnungen öffnen", "فتح الملفات والفواتير"),
    "Invoices": ("Rechnungen", "الفواتير"),
    "Mark all read": ("Alle als gelesen markieren", "تعليم الكل كمقروء"),
    "No notifications yet": ("Noch keine Benachrichtigungen", "لا توجد إشعارات بعد"),
    "Push notifications": ("Push-Benachrichtigungen", "إشعارات الدفع"),
    "Sign out": ("Abmelden", "تسجيل الخروج"),
    "Profile": ("Profil", "الملف الشخصي"),
    "Verified": ("Verifiziert", "موثّق"),
    "Not verified": ("Nicht verifiziert", "غير موثّق"),
    "Order service": ("Service bestellen", "طلب الخدمة"),
    "Learn more": ("Mehr erfahren", "اعرف المزيد"),
    "Featured": ("Empfohlen", "مميز"),
}


def extract_strings() -> set[str]:
    found: set[str] = set()
    pattern = re.compile(r'String\(localized:\s*"((?:\\.|[^"\\])*)"')
    for path in PORTAL.rglob("*.swift"):
        for match in pattern.finditer(path.read_text(encoding="utf-8")):
            raw = match.group(1)
            if "\\(" in raw or "%" in raw:
                continue
            found.add(raw)
    return found


def make_entry(en: str) -> dict:
    de, ar = TR.get(en, (en, en))
    return {
        "localizations": {
            "en": {"stringUnit": {"state": "translated", "value": en}},
            "de": {"stringUnit": {"state": "translated", "value": de}},
            "ar": {"stringUnit": {"state": "translated", "value": ar}},
        }
    }


def main() -> None:
    catalog = json.loads(XCSTRINGS.read_text(encoding="utf-8"))
    strings = catalog.setdefault("strings", {})
    added = 0
    for key in sorted(extract_strings()):
        if key in strings:
            continue
        strings[key] = make_entry(key)
        added += 1
    XCSTRINGS.write_text(json.dumps(catalog, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    print(f"Added {added} customer portal localization entries.")


if __name__ == "__main__":
    main()
