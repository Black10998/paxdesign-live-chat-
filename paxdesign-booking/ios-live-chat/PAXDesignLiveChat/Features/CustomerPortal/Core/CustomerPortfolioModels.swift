import Foundation

struct CustomerPortfolioStructuredDetail: Decodable {
    struct Hero: Decodable {
        let headline: String?
        let subtitle: String?
    }

    struct Stat: Decodable, Identifiable {
        var id: String { value + label }
        let value: String
        let label: String
    }

    struct Metadata: Decodable, Identifiable {
        var id: String { label + value }
        let label: String
        let value: String
        let link: String?
    }

    struct Section: Decodable, Identifiable {
        var id: String { title }
        let title: String
        let body: String
    }

    struct GalleryImage: Decodable, Identifiable {
        var id: String { url }
        let url: String
        let caption: String?
    }

    struct CTA: Decodable {
        let label: String
        let url: String
    }

    struct Highlight: Decodable, Identifiable {
        var id: String { label }
        let label: String
        let value: String
        let link: String?
    }

    let hero: Hero?
    let stats: [Stat]?
    let metadata: [Metadata]?
    let sections: [Section]?
    let services: [String]?
    let tags: [String]?
    let gallery: [GalleryImage]?
    let cta: CTA?
    let summary: String?
    let paragraphs: [String]?
    let highlights: [Highlight]?
    let website_url: String?
    let published_label: String?
}

extension CustomerPortfolioDetail {
    var structuredDetail: CustomerPortfolioStructuredDetail? { structured }

    var displayTitle: String {
        CustomerPortfolioTextSanitizer.clean(structured?.hero?.headline ?? title)
    }

    var displaySubtitle: String {
        let subtitle = structured?.hero?.subtitle ?? structured?.summary ?? excerpt ?? ""
        return CustomerPortfolioTextSanitizer.clean(subtitle)
    }

    var showcaseGallery: [CustomerPortfolioStructuredDetail.GalleryImage] {
        if let structuredGallery = structured?.gallery, !structuredGallery.isEmpty {
            return structuredGallery
        }
        return (gallery ?? []).map {
            CustomerPortfolioStructuredDetail.GalleryImage(url: $0, caption: nil)
        }
    }
}

extension CustomerPortfolioItem {
    var displayTitle: String {
        CustomerPortfolioTextSanitizer.clean(title)
    }

    var displayExcerpt: String {
        CustomerPortfolioTextSanitizer.clean(excerpt ?? "")
    }
}

enum CustomerPortfolioTextSanitizer {
    static func clean(_ text: String) -> String {
        var value = text
            .replacingOccurrences(of: "&#038;", with: "&")
            .replacingOccurrences(of: "&amp;", with: "&")
            .replacingOccurrences(of: "&hellip;", with: "…")
        value = value.replacingOccurrences(of: "<[^>]+>", with: "", options: .regularExpression)
        value = value.replacingOccurrences(of: #"\s+"#, with: " ", options: .regularExpression)
        value = value.trimmingCharacters(in: .whitespacesAndNewlines)

        let lower = value.lowercased()
        let placeholders = [
            "single line or paragraph description",
            "product or service",
            "lorem ipsum",
            "your text here",
        ]
        if placeholders.contains(where: { lower.contains($0) }) {
            return ""
        }
        if lower.contains("0 % industry") || lower.contains("scope websites") {
            return ""
        }
        return value
    }
}
