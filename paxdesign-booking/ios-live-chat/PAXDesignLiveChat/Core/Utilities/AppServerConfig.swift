import Foundation

/// Production server — not user-editable in the app UI.
enum AppServerConfig {
    static let siteURL = "https://paxdesign.at"

    static var customerAPIBaseURL: URL {
        URL(string: siteURL.trimmingCharacters(in: .whitespacesAndNewlines).trimmingSuffix("/") + "/wp-json/pdx/v1")!
    }

    static var staffAPIBaseURL: URL {
        URL(string: siteURL.trimmingCharacters(in: .whitespacesAndNewlines).trimmingSuffix("/") + "/wp-json/paxdesign/v1")!
    }
}

private extension String {
    func trimmingSuffix(_ suffix: String) -> String {
        hasSuffix(suffix) ? String(dropLast(suffix.count)) : self
    }
}
