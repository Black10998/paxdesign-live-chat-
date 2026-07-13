import Foundation

enum PAXAppInfo {
    static var marketingVersion: String {
        Bundle.main.infoDictionary?["CFBundleShortVersionString"] as? String ?? "—"
    }

    static var buildNumber: String {
        Bundle.main.infoDictionary?["CFBundleVersion"] as? String ?? "—"
    }

    static var fullVersion: String {
        "\(marketingVersion) (\(buildNumber))"
    }

    /// Sent on every live-admin REST/SSE request for WAF log correlation.
    static var httpUserAgent: String {
        "PAXDesignLiveChat/\(marketingVersion) (iOS; Build \(buildNumber); CFNetwork)"
    }
}
