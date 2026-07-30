import Foundation

@MainActor
final class CustomerAPIClient: ObservableObject {
    private var baseURL = URL(string: "https://paxdesign.at/wp-json/pdx/v1")!
    private weak var auth: CustomerAuthStore?

    func configure(baseURL: String, auth: CustomerAuthStore) {
        if let url = URL(string: baseURL.trimmingCharacters(in: .whitespacesAndNewlines).trimmingSuffix("/") + "/wp-json/pdx/v1") {
            self.baseURL = url
        }
        self.auth = auth
    }

    private var authBaseURL: URL {
        baseURL
    }

    /// Join REST path segments without treating `/customer/...` as site-root absolute paths.
    private func endpointURL(_ path: String) -> URL? {
        let raw = path.trimmingCharacters(in: .whitespacesAndNewlines)
        let pathPart: String
        let queryPart: String?
        if let qIndex = raw.firstIndex(of: "?") {
            pathPart = String(raw[..<qIndex]).trimmingCharacters(in: CharacterSet(charactersIn: "/"))
            queryPart = String(raw[raw.index(after: qIndex)...])
        } else {
            pathPart = raw.trimmingCharacters(in: CharacterSet(charactersIn: "/"))
            queryPart = nil
        }
        var url = baseURL
        if !pathPart.isEmpty {
            for component in pathPart.split(separator: "/") {
                url = url.appendingPathComponent(String(component), isDirectory: false)
            }
        }
        guard let queryPart, !queryPart.isEmpty else { return url }
        guard var components = URLComponents(url: url, resolvingAgainstBaseURL: false) else { return url }
        components.percentEncodedQuery = queryPart
        return components.url
    }

    func authRegister(name: String, email: String, password: String) async throws -> CustomerAuthMessageResponse {
        try await publicPost("/auth/register", json: ["name": name, "email": email, "password": password], as: CustomerAuthMessageResponse.self)
    }

    func authForgotPassword(email: String) async throws -> CustomerAuthMessageResponse {
        try await publicPost("/auth/forgot-password", json: ["email": email], as: CustomerAuthMessageResponse.self)
    }

    func authVerify(token: String) async throws -> CustomerAuthMessageResponse {
        try await publicPost("/auth/verify", json: ["token": token], as: CustomerAuthMessageResponse.self)
    }

    func authResendVerification(email: String) async throws -> CustomerAuthMessageResponse {
        var body: [String: String] = [:]
        if !email.isEmpty { body["email"] = email }
        return try await publicPost("/auth/resend-verification", json: body, as: CustomerAuthMessageResponse.self)
    }

    func registerPush(token: String, deviceID: String) async throws {
        _ = try await post("/customer/push/register", body: [
            "token": token,
            "device_id": deviceID,
            "platform": "ios",
        ], as: EmptyResponse.self)
    }

    func fetchConversations() async throws -> CustomerConversationsResponse {
        try await get("/customer/chat/conversations", as: CustomerConversationsResponse.self)
    }

    func downloadProjectFile(projectId: Int, fileId: Int) async throws -> URL {
        guard let auth, let header = auth.basicAuthHeader else { throw CustomerAPIError.unauthorized }
        guard let url = endpointURL("/customer/projects/\(projectId)/files/\(fileId)/download") else {
            throw CustomerAPIError.invalidURL
        }
        var request = URLRequest(url: url)
        request.setValue(header, forHTTPHeaderField: "Authorization")
        let (data, response) = try await URLSession.shared.data(for: request)
        guard let http = response as? HTTPURLResponse, (200..<300).contains(http.statusCode) else {
            throw CustomerAPIError.http((response as? HTTPURLResponse)?.statusCode ?? 0)
        }
        let temp = FileManager.default.temporaryDirectory.appendingPathComponent("pax-file-\(fileId)")
        try data.write(to: temp)
        return temp
    }

    func uploadChatImage(sessionID: String, imageData: Data, filename: String, caption: String = "", clientMsgID: String = UUID().uuidString) async throws -> CustomerSendResponse {
        try await uploadMultipart(path: "/customer/chat/messages/image", field: "image", filename: filename, mime: "image/jpeg", data: imageData, fields: [
            "session_id": sessionID,
            "caption": caption,
            "client_msg_id": clientMsgID,
        ], as: CustomerSendResponse.self)
    }

    func uploadChatVoice(sessionID: String, audioData: Data, duration: Double, clientMsgID: String = UUID().uuidString) async throws -> CustomerSendResponse {
        try await uploadMultipart(path: "/customer/chat/messages/voice", field: "audio", filename: "voice.m4a", mime: "audio/mp4", data: audioData, fields: [
            "session_id": sessionID,
            "duration": String(duration),
            "client_msg_id": clientMsgID,
        ], as: CustomerSendResponse.self)
    }

    func sendChatLocation(sessionID: String, lat: Double, lng: Double, label: String) async throws -> CustomerSendResponse {
        try await requestJSON(path: "/customer/chat/messages/location", method: "POST", json: [
            "session_id": sessionID,
            "lat": lat,
            "lng": lng,
            "label": label,
            "client_msg_id": UUID().uuidString,
        ], as: CustomerSendResponse.self)
    }

    func fetchDashboard() async throws -> CustomerDashboard {
        try await get("/customer/dashboard", as: CustomerDashboard.self)
    }

    func fetchServices(search: String? = nil) async throws -> CustomerServicesResponse {
        var path = "/customer/services"
        if let search, !search.isEmpty {
            path += "?search=\(search.addingPercentEncoding(withAllowedCharacters: .urlQueryAllowed) ?? search)"
        }
        return try await get(path, as: CustomerServicesResponse.self)
    }

    func fetchService(slug: String) async throws -> CustomerServiceDetail {
        try await get("/customer/services/\(slug)", as: CustomerServiceDetail.self)
    }

    func fetchProjects(status: String? = nil) async throws -> CustomerProjectsResponse {
        var path = "/customer/projects"
        if let status, !status.isEmpty {
            path += "?status=\(status.addingPercentEncoding(withAllowedCharacters: .urlQueryAllowed) ?? status)"
        }
        return try await get(path, as: CustomerProjectsResponse.self)
    }

    func fetchProject(id: Int) async throws -> CustomerProjectDetail {
        try await get("/customer/projects/\(id)", as: CustomerProjectDetail.self)
    }

    func fetchOrders(status: String? = nil) async throws -> CustomerOrdersResponse {
        var path = "/customer/orders"
        if let status, !status.isEmpty {
            path += "?status=\(status.addingPercentEncoding(withAllowedCharacters: .urlQueryAllowed) ?? status)"
        }
        return try await get(path, as: CustomerOrdersResponse.self)
    }

    func fetchOrder(id: Int) async throws -> CustomerOrderDetail {
        try await get("/customer/orders/\(id)", as: CustomerOrderDetail.self)
    }

    func createOrder(serviceSlug: String, description: String, projectId: Int? = nil) async throws -> CustomerOrderDetail {
        var body: [String: String] = [
            "service_slug": serviceSlug,
            "description": description,
        ]
        if let projectId { body["project_id"] = String(projectId) }
        return try await post("/customer/orders", body: body, as: CustomerOrderDetail.self)
    }

    func fetchNews(lang: String? = nil) async throws -> CustomerNewsResponse {
        let language = lang ?? Locale.current.language.languageCode?.identifier ?? "de"
        let normalized = language.addingPercentEncoding(withAllowedCharacters: .urlQueryAllowed) ?? language
        return try await get("/customer/news?lang=\(normalized)", as: CustomerNewsResponse.self)
    }

    func fetchNewsItem(slug: String, lang: String? = nil) async throws -> CustomerNewsItem {
        let language = lang ?? Locale.current.language.languageCode?.identifier ?? "de"
        let normalized = language.addingPercentEncoding(withAllowedCharacters: .urlQueryAllowed) ?? language
        return try await get("/customer/news/\(slug)?lang=\(normalized)", as: CustomerNewsItem.self)
    }

    func fetchNotifications(unreadOnly: Bool = false) async throws -> CustomerNotificationsResponse {
        var path = "/customer/notifications"
        if unreadOnly { path += "?unread=1" }
        return try await get(path, as: CustomerNotificationsResponse.self)
    }

    func markNotificationsRead(ids: [Int]) async throws {
        _ = try await requestJSON(path: "/customer/notifications", method: "PATCH", json: ["ids": ids], as: EmptyResponse.self)
    }

    func fetchSettings() async throws -> CustomerSettingsResponse {
        try await get("/customer/settings", as: CustomerSettingsResponse.self)
    }

    func updateSettings(_ prefs: CustomerSettingsResponse.NotificationPrefs) async throws -> CustomerSettingsResponse {
        let payload: [String: Any] = [
            "notifications": [
                "chat": prefs.chat,
                "project": prefs.project,
                "order": prefs.order,
                "news": prefs.news,
                "security": prefs.security,
                "push_enabled": prefs.push_enabled,
            ],
        ]
        return try await requestJSON(path: "/customer/settings", method: "PATCH", json: payload, as: CustomerSettingsResponse.self)
    }

    func updateProfile(displayName: String) async throws -> CustomerProfileResponse {
        try await requestJSON(path: "/customer/profile", method: "PATCH", json: ["display_name": displayName], as: CustomerProfileResponse.self)
    }

    func deleteAccount(password: String) async throws {
        _ = try await post("/customer/account/delete", body: ["password": password], as: EmptyResponse.self)
    }

    func fetchProfile() async throws -> CustomerProfileResponse {
        try await get("/customer/profile", as: CustomerProfileResponse.self)
    }

    func fetchChatMessages(sessionID: String? = nil, since: Int = 0, full: Bool = true) async throws -> CustomerChatPoll {
        var path = "/customer/chat/messages?since=\(since)"
        if full { path += "&full=1" }
        if let sessionID, !sessionID.isEmpty {
            path += "&session_id=\(sessionID.addingPercentEncoding(withAllowedCharacters: .urlQueryAllowed) ?? sessionID)"
        }
        return try await get(path, as: CustomerChatPoll.self)
    }

    func sendChatMessage(_ message: String, sessionID: String? = nil) async throws -> CustomerSendResponse {
        var body: [String: String] = ["message": message]
        if let sessionID, !sessionID.isEmpty {
            body["session_id"] = sessionID
        }
        return try await post("/customer/chat/messages", body: body, as: CustomerSendResponse.self)
    }

    func streamChatMessage(
        _ message: String,
        sessionID: String?,
        onEvent: @escaping (CustomerStreamEvent) -> Void
    ) async throws {
        guard let auth, let header = auth.basicAuthHeader else {
            throw CustomerAPIError.unauthorized
        }
        guard let url = endpointURL("/customer/chat/stream") else {
            throw CustomerAPIError.invalidURL
        }
        var body: [String: String] = ["message": message]
        if let sessionID, !sessionID.isEmpty {
            body["session_id"] = sessionID
        }
        var request = URLRequest(url: url)
        request.httpMethod = "POST"
        request.setValue(header, forHTTPHeaderField: "Authorization")
        request.setValue("application/json", forHTTPHeaderField: "Content-Type")
        request.setValue("text/event-stream", forHTTPHeaderField: "Accept")
        request.httpBody = try JSONSerialization.data(withJSONObject: body)

        let (bytes, response) = try await URLSession.shared.bytes(for: request)
        guard let http = response as? HTTPURLResponse, (200..<300).contains(http.statusCode) else {
            throw CustomerAPIError.http((response as? HTTPURLResponse)?.statusCode ?? 0)
        }

        var buffer = ""
        for try await line in bytes.lines {
            if line.hasPrefix("data: ") {
                let payload = String(line.dropFirst(6))
                if payload == "[DONE]" { break }
                if let data = payload.data(using: .utf8),
                   let event = try? JSONDecoder().decode(CustomerStreamEvent.self, from: data) {
                    onEvent(event)
                }
            }
        }
    }

    func get<T: Decodable>(_ path: String, as type: T.Type) async throws -> T {
        try await request(path, method: "GET", body: nil, as: type)
    }

    func post<T: Decodable>(_ path: String, body: [String: String], as type: T.Type) async throws -> T {
        try await request(path, method: "POST", body: body, as: type)
    }

    private func requestJSON<T: Decodable>(path: String, method: String, json: [String: Any], as type: T.Type) async throws -> T {
        guard let auth, let header = auth.basicAuthHeader else { throw CustomerAPIError.unauthorized }
        guard let url = endpointURL(path) else { throw CustomerAPIError.invalidURL }
        var request = URLRequest(url: url)
        request.httpMethod = method
        request.setValue(header, forHTTPHeaderField: "Authorization")
        request.setValue("application/json", forHTTPHeaderField: "Accept")
        request.setValue("application/json", forHTTPHeaderField: "Content-Type")
        request.httpBody = try JSONSerialization.data(withJSONObject: json)
        let (data, response) = try await URLSession.shared.data(for: request)
        guard let http = response as? HTTPURLResponse, (200..<300).contains(http.statusCode) else {
            throw CustomerAPIError.http((response as? HTTPURLResponse)?.statusCode ?? 0)
        }
        return try JSONDecoder().decode(T.self, from: data)
    }

    private func publicPost<T: Decodable>(_ path: String, json: [String: String], as type: T.Type) async throws -> T {
        guard let url = endpointURL(path) else { throw CustomerAPIError.invalidURL }
        var request = URLRequest(url: url)
        request.httpMethod = "POST"
        request.setValue("application/json", forHTTPHeaderField: "Content-Type")
        request.setValue("application/json", forHTTPHeaderField: "Accept")
        request.httpBody = try JSONSerialization.data(withJSONObject: json)
        let (data, response) = try await URLSession.shared.data(for: request)
        guard let http = response as? HTTPURLResponse else { throw CustomerAPIError.network }
        guard (200..<300).contains(http.statusCode) else {
            if let apiError = try? JSONDecoder().decode(CustomerAPIErrorPayload.self, from: data) {
                throw CustomerAPIError.server(apiError.message ?? apiError.code ?? "HTTP \(http.statusCode)")
            }
            throw CustomerAPIError.http(http.statusCode)
        }
        return try JSONDecoder().decode(T.self, from: data)
    }

    private func uploadMultipart<T: Decodable>(
        path: String,
        field: String,
        filename: String,
        mime: String,
        data: Data,
        fields: [String: String],
        as type: T.Type
    ) async throws -> T {
        guard let auth, let header = auth.basicAuthHeader else { throw CustomerAPIError.unauthorized }
        guard let url = endpointURL(path) else { throw CustomerAPIError.invalidURL }
        let boundary = "Boundary-\(UUID().uuidString)"
        var body = Data()
        for (key, value) in fields {
            body.append("--\(boundary)\r\n".data(using: .utf8)!)
            body.append("Content-Disposition: form-data; name=\"\(key)\"\r\n\r\n".data(using: .utf8)!)
            body.append("\(value)\r\n".data(using: .utf8)!)
        }
        body.append("--\(boundary)\r\n".data(using: .utf8)!)
        body.append("Content-Disposition: form-data; name=\"\(field)\"; filename=\"\(filename)\"\r\n".data(using: .utf8)!)
        body.append("Content-Type: \(mime)\r\n\r\n".data(using: .utf8)!)
        body.append(data)
        body.append("\r\n--\(boundary)--\r\n".data(using: .utf8)!)

        var request = URLRequest(url: url)
        request.httpMethod = "POST"
        request.setValue(header, forHTTPHeaderField: "Authorization")
        request.setValue("multipart/form-data; boundary=\(boundary)", forHTTPHeaderField: "Content-Type")
        request.httpBody = body
        let (responseData, response) = try await URLSession.shared.data(for: request)
        guard let http = response as? HTTPURLResponse, (200..<300).contains(http.statusCode) else {
            throw CustomerAPIError.http((response as? HTTPURLResponse)?.statusCode ?? 0)
        }
        return try JSONDecoder().decode(T.self, from: responseData)
    }

    private func request<T: Decodable>(_ path: String, method: String, body: [String: String]?, as type: T.Type) async throws -> T {
        guard let auth, let header = auth.basicAuthHeader else {
            throw CustomerAPIError.unauthorized
        }
        guard let url = endpointURL(path) else {
            throw CustomerAPIError.invalidURL
        }
        var request = URLRequest(url: url)
        request.httpMethod = method
        request.setValue(header, forHTTPHeaderField: "Authorization")
        request.setValue("application/json", forHTTPHeaderField: "Accept")
        if let body {
            request.setValue("application/json", forHTTPHeaderField: "Content-Type")
            request.httpBody = try JSONSerialization.data(withJSONObject: body)
        }
        let (data, response) = try await URLSession.shared.data(for: request)
        guard let http = response as? HTTPURLResponse else { throw CustomerAPIError.network }
        guard (200..<300).contains(http.statusCode) else {
            if let apiError = try? JSONDecoder().decode(CustomerAPIErrorPayload.self, from: data) {
                throw CustomerAPIError.server(apiError.message ?? apiError.code ?? "HTTP \(http.statusCode)")
            }
            throw CustomerAPIError.http(http.statusCode)
        }
        return try JSONDecoder().decode(T.self, from: data)
    }
}

enum CustomerAPIError: LocalizedError {
    case unauthorized, invalidURL, network
    case http(Int)
    case server(String)

    var errorDescription: String? {
        switch self {
        case .unauthorized: return String(localized: "Please sign in.")
        case .invalidURL: return String(localized: "Invalid server URL.")
        case .network: return String(localized: "Network error.")
        case .http(let code): return String(localized: "Server responded with status \(code).")
        case .server(let message): return message
        }
    }
}

private struct CustomerAPIErrorPayload: Decodable {
    let code: String?
    let message: String?
}

private extension String {
    func trimmingSuffix(_ suffix: String) -> String {
        hasSuffix(suffix) ? String(dropLast(suffix.count)) : self
    }
}

struct CustomerDashboard: Decodable {
    struct ChatSummary: Decodable {
        let session_id: String
        let last_preview: String?
        let handler: String?
        let message_count: Int?
    }
    struct ProjectSummary: Decodable {
        let id: Int
        let title: String
        let progress: Int
        let status: String
    }
    struct OrderSummary: Decodable {
        let id: Int
        let ref: String
        let service_label: String
        let status: String
    }
    struct NewsItem: Decodable {
        let slug: String
        let title: String
        let excerpt: String?
    }
    let projects_active: [ProjectSummary]?
    let orders_recent: [OrderSummary]?
    let news: [NewsItem]?
    let unread_count: Int?
    let chat: ChatSummary?
}

struct CustomerServicesResponse: Decodable {
    struct Service: Decodable, Identifiable {
        var id: String { slug }
        let slug: String
        let name: String
        let category: String
        let description: String
        let featured: Bool
    }
    struct Category: Decodable, Identifiable {
        var id: String { slug }
        let slug: String
        let name: String
    }
    let categories: [Category]
    let services: [Service]
}

struct CustomerChatPoll: Decodable {
    struct ChatMessage: Decodable, Identifiable {
        var id: Int { seq }
        let seq: Int
        let role: String
        let content: String
        let sender_name: String?
        let image_url: String?
        let audio_url: String?
        let attachment_type: String?
        let location_lat: Double?
        let location_lng: Double?
        let location_label: String?
        let file_url: String?
        let file_name: String?
    }
    let session_id: String?
    let handler: String?
    let messages: [ChatMessage]?
    let message_count: Int?
    let last_preview: String?
}

struct CustomerSendResponse: Decodable {
    let session_id: String
    let handler: String?
}

struct CustomerStreamEvent: Decodable {
    let type: String
    let text: String?
    let message: CustomerChatPoll.ChatMessage?
}

struct EmptyResponse: Decodable {}

struct CustomerAuthMessageResponse: Decodable {
    let success: Bool?
    let message: String?
}

struct CustomerConversationsResponse: Decodable {
    let conversations: [CustomerConversation]
}

struct CustomerConversation: Decodable, Identifiable {
    var id: String { session_id }
    let session_id: String
    let last_preview: String?
    let handler: String?
    let message_count: Int?
    let updated_at: String?
}
