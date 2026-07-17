#!/usr/bin/env python3
"""Apply Build 151 portfolio and Localizable.xcstrings translation fixes."""

from __future__ import annotations

import json
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
PORTFOLIO_PATH = ROOT / "paxdesign-booking/includes/customer/data/portfolio-showcase-data.json"
XCSTRINGS_PATH = ROOT / "paxdesign-booking/ios-live-chat/PAXDesignLiveChat/Resources/Localizable.xcstrings"

# slug -> {field: {en, ar}} where field is title, description, category
PORTFOLIO_TRANSLATIONS: dict[str, dict[str, dict[str, str]]] = {
    "techflow-industries": {
        "category": {"en": "Digital Strategy", "ar": "استراتيجية رقمية"},
        "title": {"en": "TechFlow – B2B Digital Strategy", "ar": "TechFlow – استراتيجية B2B رقمية"},
        "description": {
            "en": "From digital invisibility to B2B market leader: how we propelled an industrial giant into the digital age.",
            "ar": "من الإخفاء الرقمي إلى الريادة في سوق B2B: كيف قادنا عملاقاً صناعياً إلى العصر الرقمي.",
        },
        "stats": [
            {"en": "Qualified Leads", "ar": "عملاء محتملون مؤهلون"},
            {"en": "Organic Traffic", "ar": "زيارات عضوية"},
        ],
    },
    "logitrade-austria": {
        "category": {"en": "Web Design", "ar": "تصميم الويب"},
        "title": {"en": "LogiTrade – Digital Market Presence", "ar": "LogiTrade – حضور رقمي في السوق"},
        "description": {
            "en": "From local secret to regional leader: how strategic web design and local SEO elevated a traditional trading company.",
            "ar": "من سر محلي إلى قائد إقليمي: كيف رفع التصميم الاستراتيجي للموقع وSEO المحلي شركة تجارية تقليدية.",
        },
        "stats": [
            {"en": "Website Inquiries", "ar": "استفسارات الموقع"},
            {"en": "Local Google Rankings", "ar": "ترتيب Google المحلي"},
        ],
    },
    "novabrand-studio": {
        "category": {"en": "Branding", "ar": "الهوية التجارية"},
        "title": {"en": "NovaBrand – Brand Relaunch", "ar": "NovaBrand – إعادة إطلاق العلامة"},
        "description": {
            "en": "When a brand shows its age: how bold rebranding transformed NovaBrand from outdated to a sought-after lifestyle brand.",
            "ar": "عندما تتقادم العلامة: كيف حوّلنا NovaBrand بإعادة هوية جريئة من علامة قديمة إلى علامة lifestyle مرغوبة.",
        },
        "stats": [
            {"en": "Brand Awareness", "ar": "الوعي بالعلامة"},
            {"en": "Social Media Engagement", "ar": "تفاعل وسائل التواصل"},
        ],
    },
    "stylehub-commerce": {
        "category": {"en": "Software Development", "ar": "تطوير البرمجيات"},
        "title": {"en": "StyleHub – E-Commerce Success Story", "ar": "StyleHub – قصة نجاح تجارة إلكترونية"},
        "description": {
            "en": "From marketplace dependency to e-commerce ownership: how we built a web shop for StyleHub that looks great and sells.",
            "ar": "من الاعتماد على الأسواق إلى ملكية التجارة الإلكترونية: كيف أنشأنا متجر StyleHub الذي يبيع ويُبهر.",
        },
        "stats": [
            {"en": "Online Revenue", "ar": "إيرادات عبر الإنترنت"},
            {"en": "Conversion Rate", "ar": "معدل التحويل"},
        ],
    },
    "velocity-digital": {
        "category": {"en": "Branding", "ar": "الهوية التجارية"},
        "title": {"en": "Velocity – Corporate Identity", "ar": "Velocity – الهوية المؤسسية"},
        "description": {
            "en": "Giving a digital agency an identity that is both creative and professional — with corporate design that wins in the pitch room.",
            "ar": "منح وكالة رقمية هوية تجمع بين الإبداع والاحتراف — بهوية مؤسسية تفوز في العروض.",
        },
        "stats": [
            {"en": "Unique Brand Identity", "ar": "هوية علامة فريدة"},
            {"en": "Pitch Success Rate", "ar": "معدل نجاح العروض"},
        ],
    },
    "talentbridge": {
        "category": {"en": "Content & Visuals", "ar": "المحتوى والمرئيات"},
        "title": {"en": "TalentBridge – Recruiting Campaign", "ar": "TalentBridge – حملة توظيف"},
        "description": {
            "en": "Skills shortage meets digital recruiting: emotional storytelling that connects international talent with Austrian companies.",
            "ar": "نقص المهارات يلتقي التوظيف الرقمي: سرد عاطفي يربط المواهب الدولية بالشركات النمساوية.",
        },
        "stats": [
            {"en": "Qualified Applications", "ar": "طلبات مؤهلة"},
            {"en": "Successful Placements", "ar": "توظيفات ناجحة"},
        ],
    },
    "financefirst-consulting": {
        "category": {"en": "Web Design", "ar": "تصميم الويب"},
        "title": {"en": "FinanceFirst – Trust in Pixels", "ar": "FinanceFirst – الثقة بالبكسل"},
        "description": {
            "en": "Building digital trust for a sceptical industry — with a website that conveys competence without arrogance.",
            "ar": "بناء ثقة رقمية لقطاع يُنظر إليه بريبة — بموقع يُظهر الكفاءة دون تكلف.",
        },
        "stats": [
            {"en": "Contact Inquiries", "ar": "استفسارات التواصل"},
            {"en": "Avg. Session Duration", "ar": "متوسط مدة الجلسة"},
        ],
    },
    "signconnect": {
        "category": {"en": "Software Development", "ar": "تطوير البرمجيات"},
        "title": {"en": "SignConnect – Technology for Inclusion", "ar": "SignConnect – تقنية للشمول"},
        "description": {
            "en": "When innovation meets inclusion: paving the way for groundbreaking sign-language technology into millions of apps.",
            "ar": "حين يلتقي الابتكار بالشمول: تمهيد طريق تقنية لغة الإشارة الثورية إلى ملايين التطبيقات.",
        },
        "stats": [
            {"en": "App Integrations", "ar": "تكاملات التطبيقات"},
            {"en": "End Users Reached", "ar": "مستخدمون نهائيون"},
        ],
    },
    "greenscape-design": {
        "category": {"en": "Branding", "ar": "الهوية التجارية"},
        "title": {"en": "GreenScape – Nature Meets Digital", "ar": "GreenScape – الطبيعة تلتقي الرقمي"},
        "description": {
            "en": "From gardener to landscape architect: authentic branding that won premium clients for a traditional crafts business.",
            "ar": "من البستاني إلى مهندس المناظر: هوية أصيلة جذبت عملاء premium لحرفة تقليدية.",
        },
        "stats": [
            {"en": "Inquiries", "ar": "استفسارات"},
            {"en": "Avg. Project Size", "ar": "متوسط حجم المشروع"},
        ],
    },
    "synergy-holdings": {
        "category": {"en": "Branding", "ar": "الهوية التجارية"},
        "title": {"en": "Synergy – Brand Guidelines", "ar": "Synergy – إرشادات العلامة"},
        "description": {
            "en": "When everyone did their own thing: an 80-page brand manual that ended creative chaos and unified a corporate group.",
            "ar": "عندما عمل الجميع بأنفسهم: دليل علامة من 80 صفحة أنهى الفوضى الإبداعية ووحّد مجموعة شركات.",
        },
        "stats": [
            {"en": "Brand Consistency", "ar": "اتساق العلامة"},
            {"en": "Design Alignment Time", "ar": "وقت تنسيق التصميم"},
        ],
    },
    "moveeasy-logistics": {
        "category": {"en": "Branding", "ar": "الهوية التجارية"},
        "title": {"en": "MoveEasy – Brand on Wheels", "ar": "MoveEasy – علامة على عجلات"},
        "description": {
            "en": "From a fleet of white vans to rolling billboards: thoughtful branding made a logistics company impossible to miss.",
            "ar": "من أسطول شاحنات بيضاء إلى لوحات متحركة: هوية مدروسة جعلت شركة لوجistics لا تُغفل.",
        },
        "stats": [
            {"en": "Brand Recognition", "ar": "التعرف على العلامة"},
            {"en": "Referrals", "ar": "إحالات"},
        ],
    },
    "ecoverde-shop": {
        "category": {"en": "Branding", "ar": "الهوية التجارية"},
        "title": {"en": "EcoVerde – Green Brand Identity", "ar": "EcoVerde – هوية خضراء"},
        "description": {
            "en": "When sustainability must be more than a slogan: a lifestyle shop brand that truly lives environmental awareness.",
            "ar": "حين يجب أن تكون الاستدامة أكثر من شعار: علامة متجر lifestyle تعيش الوعي البيئي فعلاً.",
        },
        "stats": [
            {"en": "Authentic Brand Identity", "ar": "هوية علامة أصيلة"},
            {"en": "Social Media Growth", "ar": "نمو وسائل التواصل"},
        ],
    },
    "legalpro-partners": {
        "category": {"en": "Digital Strategy", "ar": "استراتيجية رقمية"},
        "title": {"en": "LegalPro – Legal Visibility", "ar": "LegalPro – ظهور قانوني"},
        "description": {
            "en": "From Google desert to page one: content marketing and SEO that gave a specialist law firm digital visibility.",
            "ar": "من صحراء Google إلى الصفحة الأولى: تسويق محتوى وSEO منحا مكتب محاماة متخصص ظهوراً رقمياً.",
        },
        "stats": [
            {"en": "Organic Traffic", "ar": "زيارات عضوية"},
            {"en": "Core Keyword Rankings", "ar": "ترتيب الكلمات الأساسية"},
        ],
    },
    "smartai-solutions": {
        "category": {"en": "Web Design", "ar": "تصميم الويب"},
        "title": {"en": "SmartAI – Website Redesign", "ar": "SmartAI – إعادة تصميم الموقع"},
        "description": {
            "en": "AI website redesign focused on clearer navigation, sharper messaging and reduced complexity for diverse audiences.",
            "ar": "إعادة تصميم موقع AI تركز على تنقل أوضح ورسائل أدق وتعقيد أقل لجماهير متنوعة.",
        },
        "stats": [
            {"en": "User Engagement", "ar": "تفاعل المستخدمين"},
            {"en": "Bounce Rate", "ar": "معدل الارتداد"},
        ],
    },
    "globalretail-hub": {
        "category": {"en": "Digital Strategy", "ar": "استراتيجية رقمية"},
        "title": {"en": "GlobalRetail – Digital Promotions", "ar": "GlobalRetail – عروض رقمية"},
        "description": {
            "en": "Digital promotion solutions for retail with scalability, automation and clean tracking at the core.",
            "ar": "حلول عروض رقمية للتجزئة مع قابلية التوسع والأتمتة وتتبع دقيق في جوهرها.",
        },
        "stats": [
            {"en": "Campaign Reach", "ar": "وصول الحملة"},
            {"en": "Tracking Accuracy", "ar": "دقة التتبع"},
        ],
    },
    "streampro-media": {
        "category": {"en": "Software Development", "ar": "تطوير البرمجيات"},
        "title": {"en": "StreamPro – Streaming Platform", "ar": "StreamPro – منصة بث"},
        "description": {
            "en": "Scalable streaming architecture including a smart-TV app and performance optimisations.",
            "ar": "بنية بث قابلة للتوسع تشمل تطبيق smart-TV وتحسينات الأداء.",
        },
        "stats": [
            {"en": "Active Users", "ar": "مستخدمون نشطون"},
            {"en": "Uptime", "ar": "وقت التشغيل"},
        ],
    },
    "meditrack-systems": {
        "category": {"en": "Software Development", "ar": "تطوير البرمجيات"},
        "title": {"en": "MediTrack – Data Visualisation", "ar": "MediTrack – تصور البيانات"},
        "description": {
            "en": "Medical application for processing and visualising measurements — stability and data protection first.",
            "ar": "تطبيق طبي لمعالجة وتصور القياسات — الاستقرار وحماية البيانات أولاً.",
        },
        "stats": [
            {"en": "Compliant", "ar": "متوافق"},
            {"en": "Daily Users", "ar": "مستخدمون يوميون"},
        ],
    },
    "securenet-provider": {
        "category": {"en": "Software Development", "ar": "تطوير البرمجيات"},
        "title": {"en": "SecureNet – VPN & Billing Integration", "ar": "SecureNet – VPN وتكامل الفوترة"},
        "description": {
            "en": "Secure global setup connecting VPN and billing systems with clear roles and logging concepts.",
            "ar": "إعداد عالمي آمن يربط VPN وأنظمة الفوترة بأدوار وتسجيل واضحين.",
        },
        "stats": [
            {"en": "Global Servers", "ar": "خوادم عالمية"},
            {"en": "Secure", "ar": "آمن"},
        ],
    },
    "fintech-analytics": {
        "category": {"en": "Software Development", "ar": "تطوير البرمجيات"},
        "title": {"en": "FinTech – Financial Integration", "ar": "FinTech – تكامل مالي"},
        "description": {
            "en": "Integrating complex analytics systems in finance with high compliance and stability requirements.",
            "ar": "دمج أنظمة تحليل معقدة في قطاع المالية بمتطلبات امتثال واستقرار عالية.",
        },
        "stats": [
            {"en": "Compliance", "ar": "امتثال"},
            {"en": "Monitoring", "ar": "مراقبة"},
        ],
    },
    "cloudops-solutions": {
        "category": {"en": "Software Development", "ar": "تطوير البرمجيات"},
        "title": {"en": "CloudOps – Infrastructure", "ar": "CloudOps – البنية التحتية"},
        "description": {
            "en": "Multiple projects focused on automation, monitoring, cost control and recovery strategies.",
            "ar": "مشاريع متعددة تركز على الأتمتة والمراقبة وضبط التكاليف واستراتيجيات الاستعادة.",
        },
        "stats": [
            {"en": "Infrastructure Costs", "ar": "تكاليف البنية التحتية"},
            {"en": "Availability", "ar": "التوفر"},
        ],
    },
    "autoflow-ai": {
        "category": {"en": "Software Development", "ar": "تطوير البرمجيات"},
        "title": {"en": "AutoFlow – AI Process Automation", "ar": "AutoFlow – أتمتة عمليات بالذكاء الاصطناعي"},
        "description": {
            "en": "AI process automation — a web app with clear UX, integrated data flows and understandable presentation of complex features.",
            "ar": "أتمتة عمليات بالذكاء الاصطناعي — تطبيق ويب بتجربة واضحة وتدفقات بيانات متكاملة وعرض مفهوم للميزات المعقدة.",
        },
        "stats": [
            {"en": "Process Time", "ar": "وقت العملية"},
            {"en": "Powered", "ar": "مدعوم"},
        ],
    },
}

XCSTRINGS_PATCHES: dict[str, dict[str, str]] = {
    "Book appointment": {"de": "Termin buchen", "en": "Book appointment", "ar": "احجز موعداً"},
    "What clients say": {"de": "Das sagen unsere Kunden", "en": "What clients say", "ar": "ماذا يقول عملاؤنا"},
    "Legal": {"de": "RECHTLICHES", "en": "LEGAL", "ar": "قانوني"},
    "Your projects, requests, and conversations — all in one place.": {
        "de": "Ihre Projekte, Anfragen und Gespräche — alles an einem Ort.",
        "en": "Your projects, requests, and conversations — all in one place.",
        "ar": "مشاريعك وطلباتك ومحادثاتك — كلها في مكان واحد.",
    },
    "Active projects": {"de": "Aktive Projekte", "en": "Active projects", "ar": "مشاريع نشطة"},
    "Latest messages": {"de": "Neueste Nachrichten", "en": "Latest messages", "ar": "أحدث الرسائل"},
    "Open chat": {"de": "Chat öffnen", "en": "Open chat", "ar": "فتح المحادثة"},
    "Recent requests": {"de": "Aktuelle Anfragen", "en": "Recent requests", "ar": "طلبات حديثة"},
    "Files & invoices": {"de": "Dateien & Rechnungen", "en": "Files & invoices", "ar": "الملفات والفواتير"},
    "Open files": {"de": "Dateien öffnen", "en": "Open files", "ar": "فتح الملفات"},
    "Notifications": {"de": "Benachrichtigungen", "en": "Notifications", "ar": "الإشعارات"},
    "View notifications": {"de": "Benachrichtigungen anzeigen", "en": "View notifications", "ar": "عرض الإشعارات"},
    "Recommended services": {"de": "Empfohlene Leistungen", "en": "Recommended services", "ar": "خدمات موصى بها"},
    "Featured work": {"de": "Ausgewählte Arbeiten", "en": "Featured work", "ar": "أعمال مميزة"},
    "Latest news": {"de": "Aktuelle News", "en": "Latest news", "ar": "آخر الأخبار"},
    "Services": {"de": "Leistungen", "en": "Services", "ar": "الخدمات"},
    "Portfolio": {"de": "Portfolio", "en": "Portfolio", "ar": "معرض الأعمال"},
    "Company": {"de": "Unternehmen", "en": "Company", "ar": "الشركة"},
    "Contact": {"de": "Kontakt", "en": "Contact", "ar": "اتصل بنا"},
}


def apply_portfolio() -> None:
    data = json.loads(PORTFOLIO_PATH.read_text(encoding="utf-8"))
    for item in data.get("items", []):
        slug = item.get("slug", "")
        patch = PORTFOLIO_TRANSLATIONS.get(slug)
        if not patch:
            continue
        for field in ("category", "title", "description"):
            if field not in patch:
                continue
            node = item.setdefault(field, {})
            if "en" in patch[field]:
                node["en"] = patch[field]["en"]
            if "ar" in patch[field]:
                node["ar"] = patch[field]["ar"]
        stat_labels = patch.get("stats", [])
        for idx, stat in enumerate(item.get("stats", [])):
            if idx >= len(stat_labels):
                break
            label = stat.setdefault("label", {})
            label["en"] = stat_labels[idx]["en"]
            label["ar"] = stat_labels[idx]["ar"]
    # Fix shared category labels
    for item in data.get("items", []):
        cat = item.get("category", {})
        if cat.get("en") == "Software Development" and cat.get("ar") == "Software Development":
            cat["ar"] = "تطوير البرمجيات"
        if cat.get("en") == "Content & Visuals" and cat.get("ar") == "Content & Visuals":
            cat["ar"] = "المحتوى والمرئيات"
    PORTFOLIO_PATH.write_text(json.dumps(data, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    print(f"Updated {PORTFOLIO_PATH}")


def apply_xcstrings() -> None:
    catalog = json.loads(XCSTRINGS_PATH.read_text(encoding="utf-8"))
    strings = catalog.setdefault("strings", {})
    for key, langs in XCSTRINGS_PATCHES.items():
        entry = strings.setdefault(key, {})
        locs = entry.setdefault("localizations", {})
        for lang, value in langs.items():
            locs[lang] = {"stringUnit": {"state": "translated", "value": value}}
    XCSTRINGS_PATH.write_text(json.dumps(catalog, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    print(f"Updated {XCSTRINGS_PATH}")


def main() -> None:
    apply_portfolio()
    apply_xcstrings()


if __name__ == "__main__":
    main()
