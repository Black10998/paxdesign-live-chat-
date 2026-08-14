import Foundation
import SwiftUI

enum CustomerCybercrimeCatalog {
    static let platforms = [
        "Google", "Gmail", "Facebook", "Instagram", "WhatsApp", "Apple", "iCloud",
        "Microsoft", "Outlook", "X", "TikTok", "YouTube", "LinkedIn", "Snapchat",
        "Telegram", "Discord", "PayPal", "Amazon", "eBay", "Yahoo", "Binance", "Coinbase",
    ]

    static let maxFileBytes = 25 * 1024 * 1024
    static let maxFiles = 20

    struct Category: Identifiable, Hashable {
        let id: String
        let title: String
        let icon: String
    }

    static let categories: [Category] = [
        .init(id: "account_takeover", title: String(localized: "Account takeover"), icon: "person.crop.circle.badge.exclamationmark"),
        .init(id: "phishing_fraud", title: String(localized: "Phishing / fraud"), icon: "envelope.badge.shield.half.filled"),
        .init(id: "identity_theft", title: String(localized: "Identity theft"), icon: "person.text.rectangle"),
        .init(id: "malware_ransomware", title: String(localized: "Malware / ransomware"), icon: "lock.trianglebadge.exclamationmark"),
        .init(id: "social_media_recovery", title: String(localized: "Social media recovery"), icon: "bubble.left.and.bubble.right"),
        .init(id: "financial_fraud", title: String(localized: "Financial fraud"), icon: "creditcard.trianglebadge.exclamationmark"),
        .init(id: "data_breach", title: String(localized: "Data breach"), icon: "internaldrive"),
        .init(id: "other", title: String(localized: "Other"), icon: "ellipsis.circle"),
    ]

    struct Urgency: Identifiable, Hashable {
        let id: String
        let title: String
        let tint: Color
    }

    static let urgencyLevels: [Urgency] = [
        .init(id: "low", title: String(localized: "Low"), tint: PAXTheme.textSecondary),
        .init(id: "medium", title: String(localized: "Medium"), tint: Color(uiColor: PAXDynamic.warn)),
        .init(id: "high", title: String(localized: "High"), tint: Color(uiColor: PAXDynamic.spend)),
        .init(id: "critical", title: String(localized: "Critical, active now"), tint: Color(uiColor: PAXDynamic.spend)),
    ]

    struct Country: Identifiable, Hashable {
        let id: String
        let name: String
        let dial: String
        var flag: String {
            id.uppercased().unicodeScalars.compactMap { scalar -> String? in
                guard let value = UnicodeScalar(127397 + scalar.value) else { return nil }
                return String(value)
            }.joined()
        }
    }

    static let countries: [Country] = {
        let dial: [String: String] = [
            "AT": "+43", "DE": "+49", "CH": "+41", "US": "+1", "GB": "+44", "AE": "+971",
            "SA": "+966", "EG": "+20", "TR": "+90", "FR": "+33", "IT": "+39", "ES": "+34",
            "NL": "+31", "BE": "+32", "PL": "+48", "SE": "+46", "NO": "+47", "DK": "+45",
            "FI": "+358", "IE": "+353", "PT": "+351", "GR": "+30", "CZ": "+420", "HU": "+36",
            "RO": "+40", "BG": "+359", "HR": "+385", "RS": "+381", "UA": "+380", "RU": "+7",
            "IN": "+91", "PK": "+92", "BD": "+880", "CN": "+86", "JP": "+81", "KR": "+82",
            "AU": "+61", "CA": "+1", "BR": "+55", "MX": "+52", "AR": "+54", "ZA": "+27",
            "NG": "+234", "KE": "+254", "IQ": "+964", "IR": "+98", "JO": "+962", "LB": "+961",
            "KW": "+965", "QA": "+974", "BH": "+973", "OM": "+968", "IL": "+972", "PS": "+970",
            "SY": "+963", "MA": "+212", "TN": "+216", "DZ": "+213", "LY": "+218", "SD": "+249",
            "YE": "+967", "AF": "+93", "ID": "+62", "MY": "+60", "PH": "+63", "TH": "+66",
            "VN": "+84", "SG": "+65", "HK": "+852", "TW": "+886", "NZ": "+64", "LU": "+352",
        ]
        let preferred = ["AT", "DE", "CH", "US", "GB", "AE", "SA", "EG"]
        let codes = Set(Locale.isoRegionCodes.filter { $0.count == 2 })
        return codes.compactMap { code -> Country? in
            let name = Locale.current.localizedString(forRegionCode: code) ?? code
            return Country(id: code, name: name, dial: dial[code] ?? "")
        }
        .sorted { lhs, rhs in
            let lRank = preferred.firstIndex(of: lhs.id) ?? 99
            let rRank = preferred.firstIndex(of: rhs.id) ?? 99
            if lRank != rRank { return lRank < rRank }
            return lhs.name < rhs.name
        }
    }()

    static func categoryTitle(_ id: String?) -> String {
        categories.first { $0.id == id }?.title ?? (id ?? "")
    }

    static func urgencyTitle(_ id: String?) -> String {
        urgencyLevels.first { $0.id == id }?.title ?? (id ?? "")
    }

    static func statusTitle(_ status: String?) -> String {
        switch status ?? "" {
        case "submitted": return String(localized: "Submitted")
        case "in_review": return String(localized: "In review")
        case "needs_info", "waiting_for_customer": return String(localized: "Waiting for you")
        case "resolved": return String(localized: "Resolved")
        case "closed": return String(localized: "Closed")
        default: return status?.replacingOccurrences(of: "_", with: " ").capitalized ?? String(localized: "Unknown")
        }
    }

    static func isActiveStatus(_ status: String?) -> Bool {
        switch status ?? "" {
        case "closed", "resolved": return false
        default: return true
        }
    }

    static func statusTint(_ status: String?) -> Color {
        switch status ?? "" {
        case "submitted": return PAXTheme.accent
        case "in_review": return Color(red: 0.35, green: 0.62, blue: 1)
        case "needs_info", "waiting_for_customer": return Color(uiColor: PAXDynamic.warn)
        case "resolved": return Color(uiColor: PAXDynamic.income)
        case "closed": return PAXTheme.textTertiary
        default: return PAXTheme.accent
        }
    }

    static func localeCode() -> String {
        let code = Locale.current.language.languageCode?.identifier ?? "en"
        return ["en", "de", "ar"].contains(code) ? code : "en"
    }
}
