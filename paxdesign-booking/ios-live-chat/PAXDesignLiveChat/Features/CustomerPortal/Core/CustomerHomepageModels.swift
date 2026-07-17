import Foundation

struct CustomerHomepageResponse: Decodable {
    let lang: String
    let dir: String
    let hero: Hero
    let service_carousel: [ServiceCard]
    let capabilities: Capabilities
    let portfolio_section: PortfolioSection
    let portfolio_items: [PortfolioItem]
    let about_teaser: AboutTeaser
    let stats: [Stat]
    let awards: Awards
    let testimonials: [Testimonial]
    let features: [FeatureCard]
    let process: Process
    let news_section: NewsSection
    let supported_languages: [String]?

    struct Hero: Decodable {
        let image_url: String?
        let tags: [String]
        let lead: String
        let mid: String
        let sub: String
        let cta_primary: String
        let cta_secondary: String
    }

    struct ServiceCard: Decodable, Identifiable {
        let id: String
        let order_slug: String
        let title: String
        let description: String
        let features: [String]?
        let is_new: Bool
    }

    struct Capabilities: Decodable {
        let title: String
        let subtitle: String
        let items: [Item]

        struct Item: Decodable, Identifiable {
            var id: Int { number }
            let number: Int
            let title: String
            let text: String
        }
    }

    struct PortfolioSection: Decodable {
        let title: String
        let subtitle: String
        let cta: String
        let categories: [String]
    }

    struct PortfolioItem: Decodable, Identifiable {
        var id: String { slug }
        let slug: String
        let title: String
        let excerpt: String?
        let image_url: String?
        let category_slugs: [String]?
        let category_names: [String]?
    }

    struct AboutTeaser: Decodable {
        let title: String
        let subtitle: String
        let heading: String
        let brand: String
        let since_label: String
        let since: String
        let about_label: String
        let about_text: String
        let cta: String
    }

    struct Stat: Decodable, Identifiable {
        var id: String { label }
        let label: String
        let value: Int
        let suffix: String
    }

    struct Awards: Decodable {
        let title: String
        let text: String
        let rating_label: String
    }

    struct Testimonial: Decodable, Identifiable {
        var id: String { name }
        let name: String
        let role: String
        let quote: String
        let stars: Int
    }

    struct FeatureCard: Decodable, Identifiable {
        var id: String { command }
        let command: String
        let title: String
        let text: String
    }

    struct Process: Decodable {
        let title: String
        let subtitle: String
        let steps: [Step]

        struct Step: Decodable, Identifiable {
            var id: String { number }
            let number: String
            let title: String
            let text: String
            let tags: [String]?
        }
    }

    struct NewsSection: Decodable {
        let title: String
        let subtitle: String
        let cta: String
    }

    var isRTL: Bool { dir.lowercased() == "rtl" }
}

struct CustomerSiteMenuResponse: Decodable {
    let lang: String
    let dir: String
    let tabs: [TabItem]
    let pages: [PageItem]
    let portal: [PortalItem]

    struct TabItem: Decodable, Identifiable {
        var id: String { itemId }
        let itemId: String
        let title: String
        let icon: String

        enum CodingKeys: String, CodingKey {
            case itemId = "id", title, icon
        }
    }

    struct PageItem: Decodable, Identifiable {
        var id: String { itemId }
        let itemId: String
        let slug: String
        let title: String
        let type: String

        enum CodingKeys: String, CodingKey {
            case itemId = "id", slug, title, type
        }
    }

    struct PortalItem: Decodable, Identifiable {
        var id: String { itemId }
        let itemId: String
        let title: String
        let route: String

        enum CodingKeys: String, CodingKey {
            case itemId = "id", title, route
        }
    }
}
