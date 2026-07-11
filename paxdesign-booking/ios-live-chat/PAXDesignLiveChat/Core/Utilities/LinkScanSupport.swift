import Foundation

enum LinkScanStatus: String, Equatable {
    case checking
    case safe
    case suspicious
    case dangerous
    case failed
    case timeout
    case incomplete
    case none

    init(raw: String?) {
        guard let raw, !raw.isEmpty else {
            self = .none
            return
        }
        self = LinkScanStatus(rawValue: raw) ?? .none
    }

    var isFinal: Bool {
        switch self {
        case .safe, .suspicious, .dangerous, .failed, .timeout, .incomplete:
            return true
        case .checking, .none:
            return false
        }
    }

    var label: String {
        switch self {
        case .checking: return L10n.ChatLinkScanChecking
        case .safe: return L10n.ChatLinkScanSafe
        case .suspicious: return L10n.ChatLinkScanSuspicious
        case .dangerous: return L10n.ChatLinkScanDangerous
        case .failed, .timeout, .incomplete: return L10n.ChatLinkScanIncomplete
        case .none: return ""
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
        guard !urls(in: message.content).isEmpty else { return .none }
        return .checking
    }

    static func shouldBlockLinks(in message: LiveMessage) -> Bool {
        resolvedStatus(for: message) == .dangerous
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
