import Foundation

enum SecureURLValidator {
    /// Rejects non-HTTPS URLs — required for accurate security claims and App Store compliance.
    static func validateHTTPS(_ urlString: String) throws -> URL {
        let trimmed = urlString.trimmingCharacters(in: .whitespacesAndNewlines)
        guard let url = URL(string: trimmed), let scheme = url.scheme?.lowercased() else {
            throw LiveChatAPIError.server("Ungültige Server-URL.")
        }
        guard scheme == "https" else {
            throw LiveChatAPIError.server("Nur HTTPS-Verbindungen sind erlaubt.")
        }
        guard let host = url.host, !host.isEmpty else {
            throw LiveChatAPIError.server("Ungültige Server-URL.")
        }
        return url
    }
}
