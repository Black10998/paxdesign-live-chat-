import Foundation

enum LinkScanStatus: String, Equatable {
    case checking
    case safe
    case suspicious
    case dangerous
    case none

    init(raw: String?) {
        guard let raw, !raw.isEmpty else {
            self = .none
            return
        }
        self = LinkScanStatus(rawValue: raw) ?? .none
    }

    var label: String {
        switch self {
        case .checking: return L10n.ChatLinkScanChecking
        case .safe: return L10n.ChatLinkScanSafe
        case .suspicious: return L10n.ChatLinkScanSuspicious
        case .dangerous: return L10n.ChatLinkScanDangerous
        case .none: return ""
        }
    }

    var symbolName: String {
        switch self {
        case .checking: return "arrow.triangle.2.circlepath"
        case .safe: return "checkmark.shield.fill"
        case .suspicious: return "exclamationmark.triangle.fill"
        case .dangerous: return "xmark.shield.fill"
        case .none: return "link"
        }
    }
}

enum LinkScanSupport {
    static func urls(in text: String) -> [String] {
        let pattern = #"\bhttps?://[^\s<>\"')\]]+"#
        guard let regex = try? NSRegularExpression(pattern: pattern, options: [.caseInsensitive]) else {
            return []
        }
        let range = NSRange(text.startIndex..<text.endIndex, in: text)
        let matches = regex.matches(in: text, options: [], range: range)
        var urls: [String] = []
        for match in matches {
            guard let swiftRange = Range(match.range, in: text) else { continue }
            var url = String(text[swiftRange])
            url = url.trimmingCharacters(in: CharacterSet(charactersIn: ".,;:!?)"))
            if !url.isEmpty, !urls.contains(url) {
                urls.append(url)
            }
        }
        return urls
    }

    static func resolvedStatus(for message: LiveMessage) -> LinkScanStatus {
        let status = LinkScanStatus(raw: message.linkScanStatus)
        if status != .none { return status }
        let urls = urls(in: message.content)
        guard !urls.isEmpty else { return .none }
        return worstClientStatus(urls: urls)
    }

    static func worstClientStatus(urls: [String]) -> LinkScanStatus {
        var worst = LinkScanStatus.safe
        for url in urls {
            let status = clientScan(url)
            if status == .dangerous { return .dangerous }
            if status == .suspicious { worst = .suspicious }
        }
        return worst
    }

    private static func clientScan(_ url: String) -> LinkScanStatus {
        let lower = url.lowercased()
        if lower.hasPrefix("javascript:") || lower.hasPrefix("data:") || lower.hasPrefix("file:")
            || lower.hasPrefix("vbscript:") || lower.hasPrefix("blob:") {
            return .dangerous
        }
        guard let host = URL(string: url)?.host?.lowercased(), !host.isEmpty else {
            return .dangerous
        }
        if host.range(of: #"^\d{1,3}(\.\d{1,3}){3}$"#, options: .regularExpression) != nil {
            return .suspicious
        }
        if host.contains("xn--") { return .suspicious }
        if host.components(separatedBy: ".").count >= 5 { return .suspicious }
        if lower.count > 300 { return .suspicious }
        let suspiciousTlds = ["tk", "ml", "ga", "cf", "gq", "zip", "mov", "top", "xyz", "click", "loan"]
        if let tld = host.split(separator: ".").last.map(String.init),
           suspiciousTlds.contains(tld) {
            return .suspicious
        }
        return .safe
    }

    static func resolveURL(_ raw: String, siteBase: String?) -> URL? {
        let trimmed = raw.trimmingCharacters(in: .whitespacesAndNewlines)
        guard !trimmed.isEmpty else { return nil }
        if let absolute = URL(string: trimmed), absolute.scheme != nil {
            return absolute
        }
        guard let base = siteBase?.trimmingCharacters(in: .whitespacesAndNewlines), !base.isEmpty else {
            return nil
        }
        let normalizedBase = base.hasSuffix("/") ? String(base.dropLast()) : base
        let path = trimmed.hasPrefix("/") ? trimmed : "/\(trimmed)"
        return URL(string: normalizedBase + path)
    }

    static func linkCardLabel(for message: LiveMessage) -> String {
        let label = (message.linkLabel ?? message.content).trimmingCharacters(in: .whitespacesAndNewlines)
        guard !label.isEmpty else { return L10n.ChatQuickLinkDefaultLabel }
        if label.lowercased().hasPrefix("view ") { return label }
        return "View \(label)"
    }
}
