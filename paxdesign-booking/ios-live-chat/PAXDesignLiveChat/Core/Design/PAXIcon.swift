import SwiftUI

/// Centralized icon renderer with a clean, thin monochrome style.
struct PAXIcon: View {
    let systemName: String

    init(_ systemName: String) {
        self.systemName = systemName
    }

    var body: some View {
        PAXIcon( PAXIconCatalog.outlineSymbol(for: systemName))
            .symbolRenderingMode(.monochrome)
            .foregroundStyle(.primary)
    }
}

enum PAXIconCatalog {
    private static let explicitMappings: [String: String] = [
        "bell.and.waves.left.and.right.fill": "bell.badge",
        "bubble.left.and.bubble.right.fill": "bubble.left.and.bubble.right",
        "checkmark.circle.fill": "checkmark.circle",
        "checkmark.seal.fill": "checkmark.seal",
        "exclamationmark.triangle.fill": "exclamationmark.triangle",
        "heart.fill": "heart",
        "lock.fill": "lock",
        "lock.shield.fill": "lock.shield",
        "message.fill": "message",
        "paperplane.fill": "paperplane",
        "person.3.fill": "person.3",
        "person.3.sequence.fill": "person.3.sequence",
        "person.crop.circle.badge.clock.fill": "person.crop.circle.badge.clock",
        "person.crop.circle.fill": "person.crop.circle",
        "plus.circle.fill": "plus.circle",
        "shield.lefthalf.filled.badge.checkmark": "shield.checkered",
        "xmark.circle.fill": "xmark.circle"
    ]

    static func outlineSymbol(for name: String) -> String {
        if let mapped = explicitMappings[name] {
            return mapped
        }

        // Keep the same metaphor while preferring lighter outlined variants.
        let stripped = name
            .replacingOccurrences(of: ".fill", with: "")
            .replacingOccurrences(of: ".filled", with: "")
        return stripped.isEmpty ? "circle" : stripped
    }

    static func quickLinkSymbol(for icon: String, label: String) -> String {
        let raw = icon.hasPrefix("svg:") ? String(icon.dropFirst(4)) : icon
        let sanitized = raw.lowercased()
        if sanitized.hasPrefix("sf:") {
            return outlineSymbol(for: String(sanitized.dropFirst(3)))
        }

        switch sanitized {
        case "services": return "slider.horizontal.3"
        case "projects": return "square.grid.2x2"
        case "pricing": return "dollarsign.circle"
        case "contact": return "envelope"
        case "about": return "info.circle"
        case "faq": return "questionmark.circle"
        case "portfolio": return "briefcase"
        case "link": return "link"
        default:
            let lower = label.lowercased()
            if lower.contains("service") { return "slider.horizontal.3" }
            if lower.contains("project") { return "square.grid.2x2" }
            if lower.contains("pric") { return "dollarsign.circle" }
            if lower.contains("contact") { return "envelope" }
            if lower.contains("about") { return "info.circle" }
            if lower.contains("faq") { return "questionmark.circle" }
            if lower.contains("portfolio") { return "briefcase" }
            return "link"
        }
    }
}
