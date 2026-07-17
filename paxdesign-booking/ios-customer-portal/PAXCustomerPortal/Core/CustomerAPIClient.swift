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

    private func get<T: Decodable>(_ path: String, as type: T.Type) async throws -> T {
        guard let auth, let header = auth.basicAuthHeader else {
            throw CustomerAPIError.unauthorized
        }
        guard let url = URL(string: path, relativeTo: baseURL) else {
            throw CustomerAPIError.invalidURL
        }
        var request = URLRequest(url: url)
        request.httpMethod = "GET"
        request.setValue(header, forHTTPHeaderField: "Authorization")
        request.setValue("application/json", forHTTPHeaderField: "Accept")
        let (data, response) = try await URLSession.shared.data(for: request)
        guard let http = response as? HTTPURLResponse else { throw CustomerAPIError.network }
        guard (200..<300).contains(http.statusCode) else {
            throw CustomerAPIError.http(http.statusCode)
        }
        return try JSONDecoder().decode(T.self, from: data)
    }
}

enum CustomerAPIError: LocalizedError {
    case unauthorized, invalidURL, network
    case http(Int)

    var errorDescription: String? {
        switch self {
        case .unauthorized: return String(localized: "Please sign in.")
        case .invalidURL: return String(localized: "Invalid server URL.")
        case .network: return String(localized: "Network error.")
        case .http(let code): return String(localized: "Server responded with status \(code).")
        }
    }
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
