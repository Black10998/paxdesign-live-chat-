import Foundation

struct CustomerAboutResponse: Decodable {
    let lang: String
    let dir: String
    let hero: Hero
    let intro: Intro
    let values: Values
    let stats: [Stat]
    let awards: Awards
    let gallery: [String]?
    let supported_languages: [String]?

    struct Hero: Decodable {
        let title: String
        let subtitle: String
    }

    struct Intro: Decodable {
        let heading: String
        let brand: String
        let since_label: String
        let since: String
        let about_label: String
        let about_text: String
    }

    struct Values: Decodable {
        let title: String
        let items: [Item]

        struct Item: Decodable, Identifiable {
            var id: String { title }
            let title: String
            let text: String
        }
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

    var isRTL: Bool { dir.lowercased() == "rtl" }
}

struct CustomerContactResponse: Decodable {
    let lang: String
    let dir: String
    let hero: Hero
    let contact: ContactInfo
    let faq: [FAQItem]
    let cta: CTA
    let supported_languages: [String]?

    struct Hero: Decodable {
        let title: String
        let subtitle: String
    }

    struct ContactInfo: Decodable {
        let phone: String
        let email: String
        let address: String
    }

    struct FAQItem: Decodable, Identifiable {
        var id: String { question }
        let question: String
        let answer: String
    }

    struct CTA: Decodable {
        let primary: String
        let secondary: String
    }

    var isRTL: Bool { dir.lowercased() == "rtl" }
}
