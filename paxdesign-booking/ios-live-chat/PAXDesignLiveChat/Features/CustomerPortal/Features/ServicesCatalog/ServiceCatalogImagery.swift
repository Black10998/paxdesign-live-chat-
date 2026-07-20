import Foundation

/// Curated high-quality imagery for Services catalog cards.
/// Used when the API does not return `image_url` (common for seeded booking services).
enum ServiceCatalogImagery {
    /// Prefer API media, then curated slug art. Never fall back to generic vector icons in the hero.
    static func imageURL(
        for card: CustomerServicesCatalogResponse.Card,
        apiMedia: CustomerServicesResponse.Service?
    ) -> URL? {
        if let raw = apiMedia?.image_url?.trimmingCharacters(in: .whitespacesAndNewlines),
           !raw.isEmpty,
           let url = URL(string: raw) {
            return url
        }
        let keys = [card.order_slug, card.id, apiMedia?.slug, apiMedia?.icon_key]
            .compactMap { $0?.lowercased() }
        for key in keys {
            if let url = curated[key] { return url }
            if let alias = aliases[key], let url = curated[alias] { return url }
        }
        return nil
    }

    private static let aliases: [String: String] = [
        "cross": "crossplatform",
        "crossplatform": "cross",
    ]

    /// High-resolution editorial photos matched to each catalog service.
    private static let curated: [String: URL] = {
        let pairs: [(String, String)] = [
            ("website", "https://images.unsplash.com/photo-1460925895917-afdab827c52f?auto=format&fit=crop&w=1600&q=80"),
            ("webapp", "https://images.unsplash.com/photo-1551650975-87deedd944c3?auto=format&fit=crop&w=1600&q=80"),
            ("android", "https://images.unsplash.com/photo-1607252650355-f7fd0460ccdb?auto=format&fit=crop&w=1600&q=80"),
            ("ios", "https://images.unsplash.com/photo-1512941937669-90a1b58e7e9c?auto=format&fit=crop&w=1600&q=80"),
            ("crossplatform", "https://images.unsplash.com/photo-1512941937669-90a1b58e7e9c?auto=format&fit=crop&w=1600&q=80"),
            ("androidtv", "https://images.unsplash.com/photo-1593359677879-a4bb92f829d1?auto=format&fit=crop&w=1600&q=80"),
            ("security", "https://images.unsplash.com/photo-1550751827-4bd374c3f58b?auto=format&fit=crop&w=1600&q=80"),
            ("backend", "https://images.unsplash.com/photo-1558494949-ef010cbdcc31?auto=format&fit=crop&w=1600&q=80"),
            ("devops", "https://images.unsplash.com/photo-1667372393119-3d4c48d07fc9?auto=format&fit=crop&w=1600&q=80"),
            ("enterprise", "https://images.unsplash.com/photo-1486406146926-c627a92ad1ab?auto=format&fit=crop&w=1600&q=80"),
            ("aiautomation", "https://images.unsplash.com/photo-1677442136019-21780ecad995?auto=format&fit=crop&w=1600&q=80"),
            ("aichatbot", "https://images.unsplash.com/photo-1531746790731-6c087fecd65a?auto=format&fit=crop&w=1600&q=80"),
            ("ecommerce", "https://images.unsplash.com/photo-1556742049-0cfed4f6a45d?auto=format&fit=crop&w=1600&q=80"),
            ("maintenance", "https://images.unsplash.com/photo-1581091226825-a6a2a5aee158?auto=format&fit=crop&w=1600&q=80"),
            ("pagespeed", "https://images.unsplash.com/photo-1460925895917-afdab827c52f?auto=format&fit=crop&w=1600&q=80"),
            ("uiux", "https://images.unsplash.com/photo-1561070791-2526d30994b5?auto=format&fit=crop&w=1600&q=80"),
            ("branding", "https://images.unsplash.com/photo-1626785774573-4b7993147565?auto=format&fit=crop&w=1600&q=80"),
            ("crm", "https://images.unsplash.com/photo-1552664730-d307ca884978?auto=format&fit=crop&w=1600&q=80"),
            ("bookingsystem", "https://images.unsplash.com/photo-1506784983877-45594efa4cbe?auto=format&fit=crop&w=1600&q=80"),
            ("pwa", "https://images.unsplash.com/photo-1516321318423-f06f85e504b3?auto=format&fit=crop&w=1600&q=80"),
            ("analytics", "https://images.unsplash.com/photo-1551288049-bebda4e38f71?auto=format&fit=crop&w=1600&q=80"),
            ("gdpr", "https://images.unsplash.com/photo-1450101499163-c8848c66ca85?auto=format&fit=crop&w=1600&q=80"),
            ("secflash", "https://images.unsplash.com/photo-1563986768609-322da13575f3?auto=format&fit=crop&w=1600&q=80"),
            ("seclayers", "https://images.unsplash.com/photo-1526374965328-7f61d4dc18c5?auto=format&fit=crop&w=1600&q=80"),
            ("sectamper", "https://images.unsplash.com/photo-1633265486064-086b219458ec?auto=format&fit=crop&w=1600&q=80"),
            ("secruntime", "https://images.unsplash.com/photo-1518770660439-4636190af475?auto=format&fit=crop&w=1600&q=80"),
            ("secobfusc", "https://images.unsplash.com/photo-1555949963-aa79dcee981c?auto=format&fit=crop&w=1600&q=80"),
            ("sectoken", "https://images.unsplash.com/photo-1639322537504-6427a16b0a28?auto=format&fit=crop&w=1600&q=80"),
            ("seclicense", "https://images.unsplash.com/photo-1450101499163-c8848c66ca85?auto=format&fit=crop&w=1600&q=80"),
            ("secintegrity", "https://images.unsplash.com/photo-1563986768494-4dee2763ff3f?auto=format&fit=crop&w=1600&q=80"),
        ]
        var map: [String: URL] = [:]
        for (key, raw) in pairs {
            if let url = URL(string: raw) {
                map[key] = url
            }
        }
        return map
    }()
}
