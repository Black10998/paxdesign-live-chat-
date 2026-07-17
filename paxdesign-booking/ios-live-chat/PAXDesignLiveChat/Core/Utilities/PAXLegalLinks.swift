import Foundation

enum PAXLegalLinks {
    static let privacyPolicy = URL(string: "https://paxdesign.at/datenschutz/")!
    static let impressum = URL(string: "https://paxdesign.at/impressum/")!
    static let terms = URL(string: "https://paxdesign.at/agb/")!
    static let serviceDocumentation = URL(string: "https://paxdesign.at/service-dokumentation/")!
    static let contact = URL(string: "https://paxdesign.at/kontakt/")!
    static let support = URL(string: "https://paxdesign.at")!
    static let supportEmail = URL(string: "mailto:info@paxdesign.at")!

    static func url(for slug: String) -> URL? {
        switch slug {
        case "impressum": return impressum
        case "datenschutz": return privacyPolicy
        case "agb": return terms
        case "service-dokumentation": return serviceDocumentation
        case "kontakt", "contact": return contact
        default: return nil
        }
    }
}
