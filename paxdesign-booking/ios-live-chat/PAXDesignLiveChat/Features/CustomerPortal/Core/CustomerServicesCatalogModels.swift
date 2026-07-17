import Foundation

struct CustomerServicesCatalogResponse: Decodable {
    let lang: String
    let dir: String
    let title: String
    let subtitle: String
    let statement: String
    let book_label: String
    let more_label: String
    let less_label: String
    let badges: Badges
    let process_title: String
    let process_steps: [ProcessStep]
    let security_section: SecuritySection
    let cards: [Card]
    let supported_languages: [String]?

    struct Badges: Decodable {
        let popular: String
        let premium: String
        let new: String
    }

    struct ProcessStep: Decodable, Identifiable {
        var id: Int { abs(title.hashValue ^ text.hashValue) }
        let title: String
        let text: String
    }

    struct SecuritySection: Decodable {
        let after_card_id: String
        let title: String
        let subtitle: String
    }

    struct Card: Decodable, Identifiable {
        let id: String
        let order_slug: String
        let title: String
        let description: String
        let features: [String]
        let details: [DetailBlock]
        let badge: String
        let highlighted: Bool
        let is_new: Bool

        enum BadgeKind {
            case popular, premium, new, none
        }

        var badgeKind: BadgeKind {
            switch badge.lowercased() {
            case "popular": return .popular
            case "premium": return .premium
            case "new": return .new
            default: return .none
            }
        }
    }

    struct DetailBlock: Decodable, Identifiable {
        var id: Int { abs(heading.hashValue) }
        let heading: String
        let paragraph: String?
        let items: [String]?

        var bulletItems: [String] {
            if let items, !items.isEmpty { return items }
            return []
        }
    }

    var isRTL: Bool { dir.lowercased() == "rtl" }
}

enum CustomerServicesCatalogLanguage: String, CaseIterable, Identifiable {
    case de, en, ar

    var id: String { rawValue }

    var label: String {
        switch self {
        case .de: return "DE"
        case .en: return "EN"
        case .ar: return "AR"
        }
    }
}
