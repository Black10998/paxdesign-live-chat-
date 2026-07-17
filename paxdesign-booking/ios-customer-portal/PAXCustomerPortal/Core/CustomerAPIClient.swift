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
        guard let url = URL(string: "/customer/chat/stream", relativeTo: baseURL) else {
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

    private func request<T: Decodable>(_ path: String, method: String, body: [String: String]?, as type: T.Type) async throws -> T {
        guard let auth, let header = auth.basicAuthHeader else {
            throw CustomerAPIError.unauthorized
        }
        guard let url = URL(string: path, relativeTo: baseURL) else {
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
