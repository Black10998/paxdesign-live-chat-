import Foundation

enum LiveChatAPIError: LocalizedError {
    case invalidURL
    case unauthorized
    case server(String)
    case decoding(Error)

    var errorDescription: String? {
        switch self {
        case .invalidURL: return L10n.ApiErrorInvalidUrl
        case .unauthorized: return L10n.ApiErrorLoginFailed
        case .server(let msg): return msg
        case .decoding(let err): return "Antwort konnte nicht gelesen werden: \(err.localizedDescription)"
        }
    }
}

final class LiveChatAPI {
    private static let apiSession: URLSession = {
        let config = URLSessionConfiguration.ephemeral
        config.requestCachePolicy = .reloadIgnoringLocalCacheData
        config.urlCache = nil
        return URLSession(configuration: config)
    }()

    private let siteURL: URL
    private let username: String
    private let appPassword: String
    private let session: URLSession

    init(siteURL: URL, username: String, appPassword: String, session: URLSession? = nil) {
        let normalized = Self.normalizeSiteURL(siteURL)
        // Keep runtime resilient in production/sideload builds; login validation enforces HTTPS.
        assert(normalized.scheme?.lowercased() == "https", "LiveChatAPI requires HTTPS")
        self.siteURL = normalized
        self.username = Self.normalizeUsername(username)
        self.appPassword = Self.normalizeAppPassword(appPassword)
        self.session = session ?? Self.apiSession
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
        siteURL
            .appendingPathComponent("wp-json", isDirectory: true)
            .appendingPathComponent("paxdesign", isDirectory: true)
            .appendingPathComponent("v1", isDirectory: true)
            .appendingPathComponent("live-admin", isDirectory: true)
    }

    private func liveAdminURL(path: String, query: [URLQueryItem] = []) -> URL? {
        let trimmed = path.trimmingCharacters(in: CharacterSet(charactersIn: "/"))
        var url = restBase
        if !trimmed.isEmpty {
            for component in trimmed.split(separator: "/") {
                url = url.appendingPathComponent(String(component), isDirectory: false)
            }
        }
        guard var components = URLComponents(url: url, resolvingAgainstBaseURL: false) else {
            return nil
        }
        var items = query
        items.append(URLQueryItem(name: "_", value: String(Int(Date().timeIntervalSince1970 * 1000))))
        components.queryItems = items
        return components.url
    }

    private func authRequest(url: URL, method: String = "GET", body: Data? = nil) -> URLRequest {
        var request = URLRequest(url: url)
        request.httpMethod = method
        request.cachePolicy = .reloadIgnoringLocalCacheData
        request.setValue("application/json", forHTTPHeaderField: "Accept")
        request.setValue("no-cache", forHTTPHeaderField: "Cache-Control")
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

    var publicApiBaseURL: String {
        restBase.absoluteString
    }

    private func perform<T: Decodable>(_ request: URLRequest, endpoint: String, as type: T.Type) async throws -> T {
        let data: Data
        let http: HTTPURLResponse
        do {
            let pair = try await session.data(for: request)
            data = pair.0
            guard let response = pair.1 as? HTTPURLResponse else {
                throw LiveChatAPIError.server("Keine Server-Antwort.")
            }
            http = response
        } catch {
            throw error
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
            if let text = String(data: data, encoding: .utf8),
               text.contains("<!DOCTYPE") || text.contains("<html") || text.contains("kritischen Fehler") {
                throw LiveChatAPIError.server("Serverfehler auf der Website. Bitte Plugin aktualisieren oder den Administrator kontaktieren.")
            }
            throw LiveChatAPIError.decoding(error)
        }
    }

    func validateLogin() async throws -> AdminProfile {
        guard let url = liveAdminURL(path: "me") else {
            throw LiveChatAPIError.invalidURL
        }
        return try await perform(authRequest(url: url), endpoint: "me", as: AdminProfile.self)
    }

    func fetchSessions() async throws -> SessionListResponse {
        guard let url = liveAdminURL(path: "sessions") else {
            throw LiveChatAPIError.invalidURL
        }
        return try await perform(authRequest(url: url), endpoint: "sessions", as: SessionListResponse.self)
    }

    func fetchSession(_ sessionId: String) async throws -> PollResponse {
        guard let url = liveAdminURL(
            path: "sessions/\(sessionId)/poll",
            query: [URLQueryItem(name: "full", value: "1")]
        ) else {
            throw LiveChatAPIError.invalidURL
        }
        return try await perform(authRequest(url: url), endpoint: "poll:\(sessionId):full", as: PollResponse.self)
    }

    func pollSession(_ sessionId: String, since: Int) async throws -> PollResponse {
        guard let url = liveAdminURL(
            path: "sessions/\(sessionId)/poll",
            query: [URLQueryItem(name: "since", value: String(since))]
        ) else {
            throw LiveChatAPIError.invalidURL
        }
        return try await perform(authRequest(url: url), endpoint: "poll:\(sessionId)", as: PollResponse.self)
    }

    func takeover(_ sessionId: String) async throws {
        guard let url = liveAdminURL(path: "sessions/\(sessionId)/takeover") else {
            throw LiveChatAPIError.invalidURL
        }
        _ = try await perform(authRequest(url: url, method: "POST", body: Data()), endpoint: "takeover", as: EmptyResponse.self)
    }

    func decline(_ sessionId: String) async throws {
        guard let url = liveAdminURL(path: "sessions/\(sessionId)/decline") else {
            throw LiveChatAPIError.invalidURL
        }
        _ = try await perform(authRequest(url: url, method: "POST", body: Data()), endpoint: "decline", as: EmptyResponse.self)
    }

    func close(_ sessionId: String) async throws {
        guard let url = liveAdminURL(path: "sessions/\(sessionId)/close") else {
            throw LiveChatAPIError.invalidURL
        }
        _ = try await perform(authRequest(url: url, method: "POST", body: Data()), endpoint: "close", as: EmptyResponse.self)
    }

    func archive(_ sessionId: String) async throws {
        guard let url = liveAdminURL(path: "sessions/\(sessionId)/archive") else {
            throw LiveChatAPIError.invalidURL
        }
        _ = try await perform(authRequest(url: url, method: "POST", body: Data()), endpoint: "archive", as: EmptyResponse.self)
    }

    func deleteSession(_ sessionId: String) async throws {
        guard let url = liveAdminURL(path: "sessions/\(sessionId)") else {
            throw LiveChatAPIError.invalidURL
        }
        var request = authRequest(url: url, method: "DELETE")
        _ = try await perform(request, endpoint: "delete", as: EmptyResponse.self)
    }

    func reopen(_ sessionId: String) async throws {
        guard let url = liveAdminURL(path: "sessions/\(sessionId)/reopen") else {
            throw LiveChatAPIError.invalidURL
        }
        _ = try await perform(authRequest(url: url, method: "POST", body: Data()), endpoint: "reopen", as: EmptyResponse.self)
    }

    func release(_ sessionId: String) async throws {
        guard let url = liveAdminURL(path: "sessions/\(sessionId)/release") else {
            throw LiveChatAPIError.invalidURL
        }
        _ = try await perform(authRequest(url: url, method: "POST", body: Data()), endpoint: "release", as: EmptyResponse.self)
    }

    func sendMessage(_ sessionId: String, text: String, replyTo: Int? = nil) async throws -> LiveMessage {
        guard let url = liveAdminURL(path: "sessions/\(sessionId)/messages") else {
            throw LiveChatAPIError.invalidURL
        }
        var payload: [String: Any] = ["message": text]
        if let replyTo, replyTo > 0 {
            payload["reply_to"] = replyTo
        }
        let body = try JSONSerialization.data(withJSONObject: payload)
        struct SendResponse: Codable { let message: LiveMessage }
        let response: SendResponse = try await perform(authRequest(url: url, method: "POST", body: body), endpoint: "send", as: SendResponse.self)
        return response.message
    }

    func sendImage(
        _ sessionId: String,
        imageData: Data,
        filename: String,
        caption: String = "",
        replyTo: Int? = nil
    ) async throws -> LiveMessage {
        guard let url = liveAdminURL(path: "sessions/\(sessionId)/images") else {
            throw LiveChatAPIError.invalidURL
        }

        let boundary = "PAXBoundary\(UUID().uuidString)"
        var body = Data()
        let mime = mimeType(for: filename)

        body.append("--\(boundary)\r\n".data(using: .utf8)!)
        body.append("Content-Disposition: form-data; name=\"image\"; filename=\"\(filename)\"\r\n".data(using: .utf8)!)
        body.append("Content-Type: \(mime)\r\n\r\n".data(using: .utf8)!)
        body.append(imageData)
        body.append("\r\n".data(using: .utf8)!)

        if !caption.isEmpty {
            body.append("--\(boundary)\r\n".data(using: .utf8)!)
            body.append("Content-Disposition: form-data; name=\"caption\"\r\n\r\n".data(using: .utf8)!)
            body.append("\(caption)\r\n".data(using: .utf8)!)
        }

        if let replyTo, replyTo > 0 {
            body.append("--\(boundary)\r\n".data(using: .utf8)!)
            body.append("Content-Disposition: form-data; name=\"reply_to\"\r\n\r\n".data(using: .utf8)!)
            body.append("\(replyTo)\r\n".data(using: .utf8)!)
        }

        body.append("--\(boundary)--\r\n".data(using: .utf8)!)

        var request = authRequest(url: url, method: "POST", body: body)
        request.setValue("multipart/form-data; boundary=\(boundary)", forHTTPHeaderField: "Content-Type")

        struct SendResponse: Codable { let message: LiveMessage }
        let response: SendResponse = try await perform(request, endpoint: "send-image", as: SendResponse.self)
        return response.message
    }

    private func mimeType(for filename: String) -> String {
        let lower = filename.lowercased()
        if lower.hasSuffix(".png") { return "image/png" }
        if lower.hasSuffix(".gif") { return "image/gif" }
        if lower.hasSuffix(".webp") { return "image/webp" }
        return "image/jpeg"
    }

    func setTyping(_ sessionId: String, stop: Bool = false) async throws {
        guard let url = liveAdminURL(path: "sessions/\(sessionId)/typing") else {
            throw LiveChatAPIError.invalidURL
        }
        let body = try JSONEncoder().encode(["stop": stop])
        _ = try await perform(authRequest(url: url, method: "POST", body: body), endpoint: "typing", as: EmptyResponse.self)
    }

    func fetchQuickReplies() async throws -> QuickRepliesResponse {
        guard let url = liveAdminURL(path: "quick-replies") else {
            throw LiveChatAPIError.invalidURL
        }
        return try await perform(authRequest(url: url), endpoint: "quick-replies", as: QuickRepliesResponse.self)
    }

    func fetchSuggestions(sessionId: String, messageId: Int) async throws -> SuggestionsResponse {
        guard let url = liveAdminURL(
            path: "sessions/\(sessionId)/suggestions",
            query: [URLQueryItem(name: "message_id", value: String(messageId))]
        ) else {
            throw LiveChatAPIError.invalidURL
        }
        return try await perform(authRequest(url: url), endpoint: "suggestions", as: SuggestionsResponse.self)
    }

    func fetchStaff() async throws -> StaffListResponse {
        guard let url = liveAdminURL(path: "staff") else {
            throw LiveChatAPIError.invalidURL
        }
        return try await perform(authRequest(url: url), endpoint: "staff", as: StaffListResponse.self)
    }

    func saveStaff(
        userId: Int = 0,
        email: String = "",
        enabled: Bool,
        permissions: AdminPermissions
    ) async throws {
        guard let url = liveAdminURL(path: "staff") else {
            throw LiveChatAPIError.invalidURL
        }
        var payload: [String: Any] = [
            "enabled": enabled,
            "permissions": permissions.apiDictionary,
        ]
        if userId > 0 {
            payload["user_id"] = userId
        }
        let trimmedEmail = email.trimmingCharacters(in: .whitespacesAndNewlines)
        if !trimmedEmail.isEmpty {
            payload["email"] = trimmedEmail
        }
        let body = try JSONSerialization.data(withJSONObject: payload)
        _ = try await perform(authRequest(url: url, method: "POST", body: body), endpoint: "staff-save", as: EmptyResponse.self)
    }

    func removeStaff(userId: Int) async throws {
        guard let url = liveAdminURL(path: "staff/\(userId)") else {
            throw LiveChatAPIError.invalidURL
        }
        _ = try await perform(authRequest(url: url, method: "DELETE"), endpoint: "staff-remove", as: EmptyResponse.self)
    }

    func fetchTeamSessions() async throws -> SessionListResponse {
        guard let url = liveAdminURL(path: "team/sessions") else {
            throw LiveChatAPIError.invalidURL
        }
        return try await perform(authRequest(url: url), endpoint: "team-sessions", as: SessionListResponse.self)
    }

    func openTeamConversation(userId: Int) async throws -> TeamOpenResponse {
        guard let url = liveAdminURL(path: "team/sessions/open") else {
            throw LiveChatAPIError.invalidURL
        }
        let body = try JSONSerialization.data(withJSONObject: ["user_id": userId])
        return try await perform(authRequest(url: url, method: "POST", body: body), endpoint: "team-open", as: TeamOpenResponse.self)
    }

    func pollTeamSession(_ sessionId: String, since: Int, full: Bool = false) async throws -> PollResponse {
        var query = [URLQueryItem(name: "since", value: String(since))]
        if full {
            query.append(URLQueryItem(name: "full", value: "1"))
        }
        guard let url = liveAdminURL(
            path: "team/sessions/\(sessionId)/poll",
            query: query
        ) else {
            throw LiveChatAPIError.invalidURL
        }
        return try await perform(authRequest(url: url), endpoint: "team-poll:\(sessionId)", as: PollResponse.self)
    }

    func sendTeamMessage(_ sessionId: String, content: String) async throws {
        guard let url = liveAdminURL(path: "team/sessions/\(sessionId)/messages") else {
            throw LiveChatAPIError.invalidURL
        }
        let body = try JSONEncoder().encode(["content": content])
        _ = try await perform(authRequest(url: url, method: "POST", body: body), endpoint: "team-send", as: TeamSendResponse.self)
    }

    func registerAPNs(token: String, sandbox: Bool, metadata: [String: Any] = [:]) async throws {
        guard let url = liveAdminURL(path: "push/apns") else {
            throw LiveChatAPIError.invalidURL
        }
        var payload: [String: Any] = [
            "device_token": token,
            "sandbox": sandbox,
            "bundle_id": Bundle.main.bundleIdentifier ?? "at.paxdesign.livechat"
        ]
        for (key, value) in metadata {
            payload[key] = value
        }
        let json = try JSONSerialization.data(withJSONObject: payload)
        _ = try await perform(authRequest(url: url, method: "POST", body: json), endpoint: "apns-register", as: EmptyResponse.self)
    }

    func unregisterAPNs(token: String) async throws {
        guard let url = liveAdminURL(path: "push/apns") else {
            throw LiveChatAPIError.invalidURL
        }
        let payload: [String: Any] = ["device_token": token]
        let json = try JSONSerialization.data(withJSONObject: payload)
        var request = authRequest(url: url, method: "DELETE", body: json)
        _ = try await perform(request, endpoint: "apns-unregister", as: EmptyResponse.self)
    }

    func sendDeviceHeartbeat(metadata: [String: Any]) async throws {
        guard let url = liveAdminURL(path: "devices/heartbeat") else {
            throw LiveChatAPIError.invalidURL
        }
        let json = try JSONSerialization.data(withJSONObject: metadata)
        _ = try await perform(authRequest(url: url, method: "POST", body: json), endpoint: "device-heartbeat", as: EmptyResponse.self)
    }

    func fetchEmployeeDevices(userId: Int? = nil) async throws -> DeviceListResponse {
        var path = "devices"
        if let userId, userId > 0 {
            path += "?user_id=\(userId)"
        }
        guard let url = liveAdminURL(path: path) else {
            throw LiveChatAPIError.invalidURL
        }
        return try await perform(authRequest(url: url), endpoint: "devices-list", as: DeviceListResponse.self)
    }

    func revokeDevice(deviceId: String, userId: Int) async throws {
        guard let url = liveAdminURL(path: "devices/\(deviceId)") else {
            throw LiveChatAPIError.invalidURL
        }
        let body = try JSONSerialization.data(withJSONObject: ["user_id": userId])
        var request = authRequest(url: url, method: "DELETE", body: body)
        _ = try await perform(request, endpoint: "device-revoke", as: EmptyResponse.self)
    }

    func completeOnboarding() async throws {
        guard let url = liveAdminURL(path: "onboarding/complete") else {
            throw LiveChatAPIError.invalidURL
        }
        _ = try await perform(authRequest(url: url, method: "POST"), endpoint: "onboarding-complete", as: EmptyResponse.self)
    }

    func resetOnboarding(for userId: Int) async throws {
        guard let url = liveAdminURL(path: "onboarding/reset") else {
            throw LiveChatAPIError.invalidURL
        }
        let body = try JSONSerialization.data(withJSONObject: ["user_id": userId])
        _ = try await perform(authRequest(url: url, method: "POST", body: body), endpoint: "onboarding-reset", as: EmptyResponse.self)
    }

    // MARK: - Platform modules

    func fetchPlatformSync() async throws -> PlatformSyncResponse {
        guard let url = liveAdminURL(path: "platform/sync") else {
            throw LiveChatAPIError.invalidURL
        }
        return try await perform(authRequest(url: url), endpoint: "platform-sync", as: PlatformSyncResponse.self)
    }

    func fetchPlatformDashboard() async throws -> PlatformDashboardPayload {
        guard let url = liveAdminURL(path: "platform/dashboard") else {
            throw LiveChatAPIError.invalidURL
        }
        return try await perform(authRequest(url: url), endpoint: "platform-dashboard", as: PlatformDashboardPayload.self)
    }

    func fetchPlatformReports() async throws -> PlatformReportsPayload {
        guard let url = liveAdminURL(path: "platform/reports") else {
            throw LiveChatAPIError.invalidURL
        }
        return try await perform(authRequest(url: url), endpoint: "platform-reports", as: PlatformReportsPayload.self)
    }

    func fetchPlatformEmployee() async throws -> PlatformEmployeePayload {
        guard let url = liveAdminURL(path: "platform/employee") else {
            throw LiveChatAPIError.invalidURL
        }
        return try await perform(authRequest(url: url), endpoint: "platform-employee", as: PlatformEmployeePayload.self)
    }

    func fetchPlatformNotifications() async throws -> PlatformNotificationsSummary {
        guard let url = liveAdminURL(path: "platform/notifications") else {
            throw LiveChatAPIError.invalidURL
        }
        return try await perform(authRequest(url: url), endpoint: "platform-notifications", as: PlatformNotificationsSummary.self)
    }

    func platformSearch(query: String) async throws -> PlatformSearchResponse {
        guard let url = liveAdminURL(path: "platform/search", query: [URLQueryItem(name: "q", value: query)]) else {
            throw LiveChatAPIError.invalidURL
        }
        return try await perform(authRequest(url: url), endpoint: "platform-search", as: PlatformSearchResponse.self)
    }

    func fetchPlatformTasks() async throws -> [APITaskRecord] {
        guard let url = liveAdminURL(path: "platform/tasks") else {
            throw LiveChatAPIError.invalidURL
        }
        struct Response: Codable { let tasks: [APITaskRecord] }
        let response = try await perform(authRequest(url: url), endpoint: "platform-tasks", as: Response.self)
        return response.tasks
    }

    func savePlatformTask(_ payload: [String: Any]) async throws -> APITaskRecord {
        guard let url = liveAdminURL(path: "platform/tasks") else {
            throw LiveChatAPIError.invalidURL
        }
        let body = try JSONSerialization.data(withJSONObject: payload)
        return try await perform(authRequest(url: url, method: "POST", body: body), endpoint: "platform-task-save", as: APITaskRecord.self)
    }

    func deletePlatformTask(id: String) async throws {
        guard let url = liveAdminURL(path: "platform/tasks/\(id)") else {
            throw LiveChatAPIError.invalidURL
        }
        _ = try await perform(authRequest(url: url, method: "DELETE"), endpoint: "platform-task-delete", as: EmptyResponse.self)
    }

    func fetchPlatformCalendar() async throws -> (events: [APICalendarRecord], upcoming: [APICalendarRecord]) {
        guard let url = liveAdminURL(path: "platform/calendar") else {
            throw LiveChatAPIError.invalidURL
        }
        struct Response: Codable {
            let events: [APICalendarRecord]
            let upcoming: [APICalendarRecord]
        }
        let response = try await perform(authRequest(url: url), endpoint: "platform-calendar", as: Response.self)
        return (response.events, response.upcoming)
    }

    func savePlatformEvent(_ payload: [String: Any]) async throws -> APICalendarRecord {
        guard let url = liveAdminURL(path: "platform/calendar") else {
            throw LiveChatAPIError.invalidURL
        }
        let body = try JSONSerialization.data(withJSONObject: payload)
        return try await perform(authRequest(url: url, method: "POST", body: body), endpoint: "platform-calendar-save", as: APICalendarRecord.self)
    }

    func deletePlatformEvent(id: String) async throws {
        guard let url = liveAdminURL(path: "platform/calendar/\(id)") else {
            throw LiveChatAPIError.invalidURL
        }
        _ = try await perform(authRequest(url: url, method: "DELETE"), endpoint: "platform-calendar-delete", as: EmptyResponse.self)
    }

    func fetchPlatformFiles() async throws -> [APIFileRecord] {
        guard let url = liveAdminURL(path: "platform/files") else {
            throw LiveChatAPIError.invalidURL
        }
        struct Response: Codable { let files: [APIFileRecord] }
        let response = try await perform(authRequest(url: url), endpoint: "platform-files", as: Response.self)
        return response.files
    }

    func savePlatformFile(_ payload: [String: Any]) async throws -> APIFileRecord {
        guard let url = liveAdminURL(path: "platform/files") else {
            throw LiveChatAPIError.invalidURL
        }
        let body = try JSONSerialization.data(withJSONObject: payload)
        return try await perform(authRequest(url: url, method: "POST", body: body), endpoint: "platform-file-save", as: APIFileRecord.self)
    }

    func deletePlatformFile(id: String) async throws {
        guard let url = liveAdminURL(path: "platform/files/\(id)") else {
            throw LiveChatAPIError.invalidURL
        }
        _ = try await perform(authRequest(url: url, method: "DELETE"), endpoint: "platform-file-delete", as: EmptyResponse.self)
    }

    func fetchPlatformActivity(module: String? = nil) async throws -> [APIActivityRecord] {
        var query: [URLQueryItem] = []
        if let module, !module.isEmpty {
            query.append(URLQueryItem(name: "module", value: module))
        }
        guard let url = liveAdminURL(path: "platform/activity", query: query) else {
            throw LiveChatAPIError.invalidURL
        }
        struct Response: Codable { let entries: [APIActivityRecord] }
        let response = try await perform(authRequest(url: url), endpoint: "platform-activity", as: Response.self)
        return response.entries
    }

    func appendPlatformActivity(
        module: String,
        title: String,
        detail: String = "",
        severity: String = "info",
        category: String = ""
    ) async throws -> APIActivityRecord {
        guard let url = liveAdminURL(path: "platform/activity") else {
            throw LiveChatAPIError.invalidURL
        }
        let payload: [String: Any] = [
            "module": module,
            "title": title,
            "detail": detail,
            "severity": severity,
            "category": category.isEmpty ? module : category,
        ]
        let body = try JSONSerialization.data(withJSONObject: payload)
        return try await perform(authRequest(url: url, method: "POST", body: body), endpoint: "platform-activity-append", as: APIActivityRecord.self)
    }

    func clearPlatformActivity() async throws {
        guard let url = liveAdminURL(path: "platform/activity") else {
            throw LiveChatAPIError.invalidURL
        }
        _ = try await perform(authRequest(url: url, method: "DELETE"), endpoint: "platform-activity-clear", as: EmptyResponse.self)
    }

    func fetchPlatformSettings() async throws -> [String: Bool] {
        guard let url = liveAdminURL(path: "platform/settings") else {
            throw LiveChatAPIError.invalidURL
        }
        struct Response: Codable { let settings: [String: Bool] }
        let response = try await perform(authRequest(url: url), endpoint: "platform-settings-get", as: Response.self)
        return response.settings
    }

    func savePlatformSettings(_ settings: [String: Bool]) async throws -> [String: Bool] {
        guard let url = liveAdminURL(path: "platform/settings") else {
            throw LiveChatAPIError.invalidURL
        }
        let body = try JSONSerialization.data(withJSONObject: ["settings": settings])
        struct Response: Codable { let settings: [String: Bool] }
        let response = try await perform(authRequest(url: url, method: "POST", body: body), endpoint: "platform-settings-save", as: Response.self)
        return response.settings
    }
}

private struct WPErrorResponse: Codable {
    let code: String
    let message: String
}

private struct EmptyResponse: Codable {}

struct DeviceRecord: Codable, Identifiable {
    var id: String { deviceId }
    let userId: Int
    let employeeName: String
    let employeeEmail: String
    let deviceId: String
    let deviceToken: String
    let deviceName: String
    let deviceModel: String
    let osVersion: String
    let appVersion: String
    let firstLoginAt: Int
    let lastActiveAt: Int
    let ipAddress: String
    let location: String
    let revoked: Bool
    let sandbox: Bool

    enum CodingKeys: String, CodingKey {
        case userId = "user_id"
        case employeeName = "employee_name"
        case employeeEmail = "employee_email"
        case deviceId = "device_id"
        case deviceToken = "device_token"
        case deviceName = "device_name"
        case deviceModel = "device_model"
        case osVersion = "os_version"
        case appVersion = "app_version"
        case firstLoginAt = "first_login_at"
        case lastActiveAt = "last_active_at"
        case ipAddress = "ip_address"
        case location, revoked, sandbox
    }
}

struct DeviceListResponse: Codable {
    let devices: [DeviceRecord]
    let canManage: Bool

    enum CodingKeys: String, CodingKey {
        case devices
        case canManage = "can_manage"
    }
}
