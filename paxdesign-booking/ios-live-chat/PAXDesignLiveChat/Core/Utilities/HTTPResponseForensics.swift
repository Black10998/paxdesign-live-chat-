import Foundation

/// Captures recent HTTP exchanges for 403/WAF diagnosis (Settings → Netzwerk-Diagnose).
@MainActor
final class HTTPResponseForensics {
    static let shared = HTTPResponseForensics()

    struct Entry: Identifiable {
        let id = UUID()
        let at: Date
        let method: String
        let url: String
        let endpoint: String
        let status: Int
        let requestHeaders: [String: String]
        let responseHeaders: [String: String]
        let bodySnippet: String
        let isEdgeBlock: Bool

        var cfRay: String { responseHeaders["cf-ray"] ?? responseHeaders["CF-RAY"] ?? "—" }
        var server: String { responseHeaders["server"] ?? responseHeaders["Server"] ?? "—" }
        var retryAfter: String { responseHeaders["retry-after"] ?? responseHeaders["Retry-After"] ?? "—" }
        var poweredBy: String { responseHeaders["x-powered-by"] ?? responseHeaders["X-Powered-By"] ?? "—" }
    }

    private(set) var recent: [Entry] = []
    private(set) var lastEdge403: Entry?
    private let maxEntries = 20

    private init() {}

    func record(
        request: URLRequest,
        endpoint: String,
        response: HTTPURLResponse,
        bodySnippet: String
    ) {
        let status = response.statusCode
        guard status >= 400 else { return }

        let reqHeaders = normalizedHeaders(request.allHTTPHeaderFields ?? [:])
        let resHeaders = normalizedHeaders(response.allHeaderFields as? [String: String] ?? [:])
        let edge = bodySnippet.localizedCaseInsensitiveContains("Access to this resource on the server is denied")
            || (status == 403 && poweredBy(from: resHeaders).isEmpty)

        let entry = Entry(
            at: Date(),
            method: request.httpMethod ?? "GET",
            url: request.url?.absoluteString ?? endpoint,
            endpoint: endpoint,
            status: status,
            requestHeaders: reqHeaders,
            responseHeaders: resHeaders,
            bodySnippet: String(bodySnippet.prefix(280)),
            isEdgeBlock: edge
        )

        recent.insert(entry, at: 0)
        if recent.count > maxEntries {
            recent.removeLast(recent.count - maxEntries)
        }
        if status == 403 || status == 429 {
            lastEdge403 = entry
        }
    }

    func reset() {
        recent = []
        lastEdge403 = nil
    }

    private func normalizedHeaders(_ raw: [String: String]) -> [String: String] {
        var out: [String: String] = [:]
        for (k, v) in raw {
            out[k.lowercased()] = v
        }
        return out
    }

    private func poweredBy(from headers: [String: String]) -> String {
        headers["x-powered-by"] ?? ""
    }
}
