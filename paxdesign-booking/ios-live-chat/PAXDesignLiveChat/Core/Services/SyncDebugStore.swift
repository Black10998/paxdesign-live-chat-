import Foundation

@MainActor
final class SyncDebugStore: ObservableObject {
    static let shared = SyncDebugStore()

    @Published var apiBaseURL = "—"
    @Published var loggedInUser = "—"
    @Published var syncEngineStatus = "stopped"
    @Published var listPollLoopActive = false
    @Published var messagePollLoopActive = false
    @Published var lastListPollAt: Date?
    @Published var lastMessagePollAt: Date?
    @Published var lastHTTPStatus: Int?
    @Published var lastRequestURL = "—"
    @Published var lastEndpoint = "—"
    @Published var apiRawSessionCount: Int?
    @Published var apiLiveCount: Int?
    @Published var displayedSessionCount = 0
    @Published var latestSessionIdFromAPI = "—"
    @Published var selectedSessionId = "—"
    @Published var apiRawMessageCount: Int?
    @Published var displayedMessageCount = 0
    @Published var lastDecodeError = "—"
    @Published var lastSyncError = "—"
    @Published var lastRawBodyPreview = "—"
    @Published var runtimeEnvironment = RuntimeEnvironment.detect()
    @Published var pluginVersion = "—"
    @Published var lastSuccessfulListSyncAt: Date?

    private init() {}

    func setSyncEngineStatus(_ status: String) {
        syncEngineStatus = status
    }

    func recordListDisplayed(count: Int, sessions: [LiveSession]) {
        displayedSessionCount = count
        if let first = sessions.first {
            if latestSessionIdFromAPI == "—" || latestSessionIdFromAPI.isEmpty {
                latestSessionIdFromAPI = first.sessionId
            }
        }
    }

    func recordMessagesDisplayed(count: Int, sessionId: String) {
        displayedMessageCount = count
        selectedSessionId = sessionId
    }

    func recordRequestStarted(endpoint: String, url: String) {
        lastEndpoint = endpoint
        lastRequestURL = url
        lastDecodeError = "—"
    }

    func recordHTTPResponse(endpoint: String, status: Int, data: Data, url: String) {
        lastHTTPStatus = status
        lastRequestURL = url
        lastEndpoint = endpoint
        lastRawBodyPreview = Self.preview(data)

        if endpoint == "sessions" {
            lastListPollAt = Date()
            parseSessionsPayload(data)
        } else if endpoint.hasPrefix("poll/") || endpoint.hasPrefix("poll:") {
            lastMessagePollAt = Date()
            parsePollPayload(data)
        } else if endpoint == "me" {
            parseMePayload(data)
        }

        if (200...299).contains(status), endpoint == "sessions" {
            lastSuccessfulListSyncAt = Date()
            lastSyncError = "—"
        }
    }

    func recordDecodeFailure(endpoint: String, error: Error, data: Data) {
        lastDecodeError = "[\(endpoint)] \(error.localizedDescription)"
        lastRawBodyPreview = Self.preview(data)
    }

    func recordSyncFailure(endpoint: String, error: Error) {
        lastSyncError = "[\(endpoint)] \(error.localizedDescription)"
        if let apiError = error as? LiveChatAPIError, case .decoding(let inner) = apiError {
            lastDecodeError = "[\(endpoint)] \(inner.localizedDescription)"
        }
    }

    private func parseSessionsPayload(_ data: Data) {
        guard let json = try? JSONSerialization.jsonObject(with: data) as? [String: Any] else {
            apiRawSessionCount = nil
            apiLiveCount = nil
            return
        }
        if let sessions = json["sessions"] as? [[String: Any]] {
            apiRawSessionCount = sessions.count
            if let first = sessions.first, let sid = first["session_id"] as? String, !sid.isEmpty {
                latestSessionIdFromAPI = sid
            }
        } else {
            apiRawSessionCount = 0
        }
        if let live = json["live_count"] as? Int {
            apiLiveCount = live
        } else if let live = json["live_count"] as? String, let parsed = Int(live) {
            apiLiveCount = parsed
        }
    }

    private func parsePollPayload(_ data: Data) {
        guard let json = try? JSONSerialization.jsonObject(with: data) as? [String: Any] else {
            apiRawMessageCount = nil
            return
        }
        if let messages = json["messages"] as? [Any] {
            apiRawMessageCount = messages.count
        } else {
            apiRawMessageCount = 0
        }
    }

    private func parseMePayload(_ data: Data) {
        guard let json = try? JSONSerialization.jsonObject(with: data) as? [String: Any] else { return }
        if let ver = json["plugin_ver"] as? String {
            pluginVersion = ver
        }
    }

    private static func preview(_ data: Data) -> String {
        let text = String(data: data.prefix(900), encoding: .utf8) ?? "<binary>"
        return text.replacingOccurrences(of: "\n", with: " ")
    }
}

enum RuntimeEnvironment {
    case xcode
    case liveContainer
    case testFlight
    case appStore
    case unknown

    var label: String {
        switch self {
        case .xcode: return "Xcode / direct install"
        case .liveContainer: return "LiveContainer"
        case .testFlight: return "TestFlight"
        case .appStore: return "App Store"
        case .unknown: return "Unknown"
        }
    }

    static func detect() -> String {
        detectKind().label
    }

    static func detectKind() -> RuntimeEnvironment {
        let path = Bundle.main.bundlePath.lowercased()
        if path.contains("livecontainer") {
            return .liveContainer
        }
        #if DEBUG
        if path.contains("/var/containers/bundle/application") || path.contains("debug.dylib") {
            return .xcode
        }
        return .xcode
        #else
        if let receipt = Bundle.main.appStoreReceiptURL?.lastPathComponent, receipt == "sandboxReceipt" {
            return .testFlight
        }
        if path.contains("/private/var/containers/") {
            return .appStore
        }
        return .unknown
        #endif
    }
}
