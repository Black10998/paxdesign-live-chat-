import Foundation

struct CustomerLegalPageResponse: Decodable {
    let slug: String
    let lang: String
    let dir: String
    let title: String
    let subtitle: String
    let sections: [Section]
    let website_url: String
    let cta: String

    struct Section: Decodable, Identifiable {
        var id: String { title }
        let title: String
        let body: String
    }

    var isRTL: Bool { dir.lowercased() == "rtl" }
}
