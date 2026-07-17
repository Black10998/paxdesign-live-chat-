import Foundation

enum CustomerPortalLocale {
    static var languageCode: String {
        let preferred = Locale.preferredLanguages.first ?? "de"
        let code = Locale(identifier: preferred).language.languageCode?.identifier ?? "de"
        switch code {
        case "de", "en", "ar": return code
        default: return "de"
        }
    }

    static var catalogLanguage: CustomerServicesCatalogLanguage {
        CustomerServicesCatalogLanguage(rawValue: languageCode) ?? .de
    }
}
