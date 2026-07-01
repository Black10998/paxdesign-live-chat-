import Foundation

enum LiveChatAPIError: LocalizedError {
    case invalidURL
    case unauthorized
    case server(String)
    case decoding(Error)

    var errorDescription: String? {
        switch self {
        case .invalidURL: return "Ungültige Server-URL."
        case .unauthorized: return "Anmeldung fehlgeschlagen. Bitte Zugangsdaten prüfen."
        case .server(let msg): return msg
        case .decoding(let err): return "Antwort konnte nicht gelesen werden: \(err.localizedDescription)"
        }
    }
}

final class LiveChatAPI {
    private let siteURL: URL
    private let username: String
    private let appPassword: String
    private let session: URLSession

    init(siteURL: URL, username: String, appPassword: String, session: URLSession = .shared) {
        self.siteURL = Self.normalizeSiteURL(siteURL)
        self.username = Self.normalizeUsername(username)
        self.appPassword = Self.normalizeAppPassword(appPassword)
        self.session = session
    }

    static func normalizeSiteURL(_ url: URL) -> URL {
        var trimmed = url.absoluteString.trimmingCharacters(in: .whitespacesAndNewlines)
        while trimmed.hasSuffix("/") {
            trimmed.removeLast()
        }
        return URL(string: trimmed) ?? url
    }

    static func normalizeUsername(_ value: String) -> String {
        value.trimmingCharacters(in: .whitespacesAndNewlines)
    }

    /// WordPress displays Application Passwords with spaces — strip them before Basic Auth.
    static func normalizeAppPassword(_ value: String) -> String {
        value
            .trimmingCharacters(in: .whitespacesAndNewlines)
            .replacingOccurrences(of: " ", with: "")
    }

    private var restBase: URL {
        siteURL.appendingPathComponent("wp-json/paxdesign/v1/live-admin/")
    }

    private func authRequest(url: URL, method: String = "GET", body: Data? = nil) -> URLRequest {
        var request = URLRequest(url: url)
        request.httpMethod = method
        request.setValue("application/json", forHTTPHeaderField: "Accept")
        if body != nil {
            request.setValue("application/json", forHTTPHeaderField: "Content-Type")
        }

        let credentials = "\(username):\(appPassword)"
        let token = Data(credentials.utf8).base64EncodedString()
        request.setValue("Basic \(token)", forHTTPHeaderField: "Authorization")
        request.httpBody = body
        return request
    }

    private func decode<T: Decodable>(_ type: T.Type, from data: Data) throws -> T {
        let decoder = JSONDecoder()
        return try decoder.decode(type, from: data)
    }

    private func wpErrorMessage(from data: Data) -> String? {
        guard let err = try? JSONDecoder().decode(WPErrorResponse.self, from: data) else {
            return nil
        }
        let message = err.message.trimmingCharacters(in: .whitespacesAndNewlines)
        return message.isEmpty ? nil : message
    }

    private func perform<T: Decodable>(_ request: URLRequest, as type: T.Type) async throws -> T {
        let (data, response) = try await session.data(for: request)
        guard let http = response as? HTTPURLResponse else {
            throw LiveChatAPIError.server("Keine Server-Antwort.")
        }

        if http.statusCode == 401 || http.statusCode == 403 {
            if let message = wpErrorMessage(from: data) {
                throw LiveChatAPIError.server(message)
            }
            throw LiveChatAPIError.unauthorized
        }

        if http.statusCode >= 400 {
            if let message = wpErrorMessage(from: data) {
                throw LiveChatAPIError.server(message)
            }
            throw LiveChatAPIError.server("HTTP \(http.statusCode)")
        }

        do {
            return try decode(type, from: data)
        } catch {
            throw LiveChatAPIError.decoding(error)
        }
    }

    func validateLogin() async throws -> AdminProfile {
        let url = restBase.appendingPathComponent("me")
        let request = authRequest(url: url)
        return try await perform(request, as: AdminProfile.self)
    }

    func fetchSessions() async throws -> SessionListResponse {
        let url = restBase.appendingPathComponent("sessions")
        return try await perform(authRequest(url: url), as: SessionListResponse.self)
    }

    func fetchSession(_ sessionId: String) async throws -> PollResponse {
        let url = restBase
            .appendingPathComponent("sessions")
            .appendingPathComponent(sessionId)
            .appendingPathComponent("poll")
        var components = URLComponents(url: url, resolvingAgainstBaseURL: false)!
        components.queryItems = [URLQueryItem(name: "full", value: "1")]
        return try await perform(authRequest(url: components.url!), as: PollResponse.self)
    }

    func pollSession(_ sessionId: String, since: Int) async throws -> PollResponse {
        var components = URLComponents(url: restBase
            .appendingPathComponent("sessions")
            .appendingPathComponent(sessionId)
            .appendingPathComponent("poll"), resolvingAgainstBaseURL: false)!
        components.queryItems = [URLQueryItem(name: "since", value: String(since))]
        return try await perform(authRequest(url: components.url!), as: PollResponse.self)
    }

    func takeover(_ sessionId: String) async throws {
        let url = restBase
            .appendingPathComponent("sessions")
            .appendingPathComponent(sessionId)
            .appendingPathComponent("takeover")
        _ = try await perform(authRequest(url: url, method: "POST", body: Data()), as: EmptyResponse.self)
    }

    func decline(_ sessionId: String) async throws {
        let url = restBase
            .appendingPathComponent("sessions")
            .appendingPathComponent(sessionId)
            .appendingPathComponent("decline")
        _ = try await perform(authRequest(url: url, method: "POST", body: Data()), as: EmptyResponse.self)
    }

    func close(_ sessionId: String) async throws {
        let url = restBase
            .appendingPathComponent("sessions")
            .appendingPathComponent(sessionId)
            .appendingPathComponent("close")
        _ = try await perform(authRequest(url: url, method: "POST", body: Data()), as: EmptyResponse.self)
    }

    func archive(_ sessionId: String) async throws {
        let url = restBase
            .appendingPathComponent("sessions")
            .appendingPathComponent(sessionId)
            .appendingPathComponent("archive")
        _ = try await perform(authRequest(url: url, method: "POST", body: Data()), as: EmptyResponse.self)
    }

    func deleteSession(_ sessionId: String) async throws {
        let url = restBase
            .appendingPathComponent("sessions")
            .appendingPathComponent(sessionId)
        var request = authRequest(url: url, method: "DELETE")
        _ = try await perform(request, as: EmptyResponse.self)
    }

    func reopen(_ sessionId: String) async throws {
        let url = restBase
            .appendingPathComponent("sessions")
            .appendingPathComponent(sessionId)
            .appendingPathComponent("reopen")
        _ = try await perform(authRequest(url: url, method: "POST", body: Data()), as: EmptyResponse.self)
    }

    func release(_ sessionId: String) async throws {
        let url = restBase
            .appendingPathComponent("sessions")
            .appendingPathComponent(sessionId)
            .appendingPathComponent("release")
        _ = try await perform(authRequest(url: url, method: "POST", body: Data()), as: EmptyResponse.self)
    }

    func sendMessage(_ sessionId: String, text: String) async throws -> LiveMessage {
        let url = restBase
            .appendingPathComponent("sessions")
            .appendingPathComponent(sessionId)
            .appendingPathComponent("messages")
        let body = try JSONEncoder().encode(["message": text])
        struct SendResponse: Codable { let message: LiveMessage }
        let response: SendResponse = try await perform(authRequest(url: url, method: "POST", body: body), as: SendResponse.self)
        return response.message
    }

    func setTyping(_ sessionId: String, stop: Bool = false) async throws {
        let url = restBase
            .appendingPathComponent("sessions")
            .appendingPathComponent(sessionId)
            .appendingPathComponent("typing")
        let body = try JSONEncoder().encode(["stop": stop])
        _ = try await perform(authRequest(url: url, method: "POST", body: body), as: EmptyResponse.self)
    }

    func registerAPNs(token: String, sandbox: Bool) async throws {
        let url = restBase.appendingPathComponent("push/apns")
        let payload: [String: Any] = [
            "device_token": token,
            "sandbox": sandbox,
            "bundle_id": Bundle.main.bundleIdentifier ?? "at.paxdesign.livechat"
        ]
        let json = try JSONSerialization.data(withJSONObject: payload)
        _ = try await perform(authRequest(url: url, method: "POST", body: json), as: EmptyResponse.self)
    }

    func unregisterAPNs(token: String) async throws {
        let url = restBase.appendingPathComponent("push/apns")
        let payload: [String: Any] = ["device_token": token]
        let json = try JSONSerialization.data(withJSONObject: payload)
        var request = authRequest(url: url, method: "DELETE", body: json)
        _ = try await perform(request, as: EmptyResponse.self)
    }
}

private struct WPErrorResponse: Codable {
    let code: String
    let message: String
}

private struct EmptyResponse: Codable {}
