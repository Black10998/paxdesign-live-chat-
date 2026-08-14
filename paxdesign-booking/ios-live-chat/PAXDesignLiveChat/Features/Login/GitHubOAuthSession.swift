import AuthenticationServices
import UIKit

enum GitHubOAuthError: LocalizedError, Equatable {
    case invalidStartURL
    case cancelled
    case missingTicket
    case server(String)

    var errorDescription: String? {
        switch self {
        case .invalidStartURL:
            return String(localized: "GitHub sign-in is not available right now.")
        case .cancelled:
            return String(localized: "GitHub sign-in was cancelled.")
        case .missingTicket:
            return String(localized: "GitHub did not return a valid PAXDesign session.")
        case .server(let message):
            return message
        }
    }
}

/// iOS → GitHub authorize → PAXDesign callback → custom-scheme return.
@MainActor
final class GitHubOAuthSession: NSObject, ASWebAuthenticationPresentationContextProviding {
    static let shared = GitHubOAuthSession()

    private var session: ASWebAuthenticationSession?

    private override init() {
        super.init()
    }

    func signIn() async throws -> String {
        guard let url = Self.startURL() else {
            throw GitHubOAuthError.invalidStartURL
        }

        return try await withCheckedThrowingContinuation { continuation in
            let authSession = ASWebAuthenticationSession(
                url: url,
                callbackURLScheme: "paxlivechat"
            ) { callbackURL, error in
                Task { @MainActor in
                    self.session = nil
                    if let error {
                        let nsError = error as NSError
                        if nsError.domain == ASWebAuthenticationSessionError.errorDomain,
                           nsError.code == ASWebAuthenticationSessionError.canceledLogin.rawValue {
                            continuation.resume(throwing: GitHubOAuthError.cancelled)
                            return
                        }
                        continuation.resume(throwing: error)
                        return
                    }
                    guard let callbackURL else {
                        continuation.resume(throwing: GitHubOAuthError.missingTicket)
                        return
                    }
                    do {
                        continuation.resume(returning: try Self.ticket(from: callbackURL))
                    } catch {
                        continuation.resume(throwing: error)
                    }
                }
            }
            authSession.presentationContextProvider = self
            authSession.prefersEphemeralWebBrowserSession = false
            self.session = authSession
            if !authSession.start() {
                self.session = nil
                continuation.resume(throwing: GitHubOAuthError.invalidStartURL)
            }
        }
    }

    func presentationAnchor(for session: ASWebAuthenticationSession) -> ASPresentationAnchor {
        let scenes = UIApplication.shared.connectedScenes.compactMap { $0 as? UIWindowScene }
        if let key = scenes.flatMap(\.windows).first(where: \.isKeyWindow) {
            return key
        }
        return scenes.first?.windows.first ?? ASPresentationAnchor()
    }

    static func startURL() -> URL? {
        var components = URLComponents(string: "\(AppServerConfig.siteURL)/wp-json/pdx/v1/auth/github/start")
        components?.queryItems = [
            URLQueryItem(name: "platform", value: "ios"),
            URLQueryItem(name: "device_label", value: "PAXDesign iOS"),
        ]
        return components?.url
    }

    static func ticket(from url: URL) throws -> String {
        let items = URLComponents(url: url, resolvingAgainstBaseURL: false)?.queryItems ?? []
        if let error = items.first(where: { $0.name == "error" })?.value, !error.isEmpty {
            throw GitHubOAuthError.server(error.removingPercentEncoding ?? error)
        }
        let ticket = items.first(where: { $0.name == "ticket" })?.value ?? ""
        guard !ticket.isEmpty else {
            throw GitHubOAuthError.missingTicket
        }
        return ticket
    }
}
