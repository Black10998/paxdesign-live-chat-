import Foundation

struct CachedSessionList: Codable {
    let sessions: [LiveSession]
    let teamSessions: [LiveSession]
    let liveCount: Int
    let cachedAt: TimeInterval

    init(
        sessions: [LiveSession],
        teamSessions: [LiveSession],
        liveCount: Int,
        cachedAt: TimeInterval = Date().timeIntervalSince1970
    ) {
        self.sessions = sessions
        self.teamSessions = teamSessions
        self.liveCount = liveCount
        self.cachedAt = cachedAt
    }

    var cachedDate: Date { Date(timeIntervalSince1970: cachedAt) }
}

@MainActor
final class SessionListCache {
    static let shared = SessionListCache()

    private let fileName = "session-list.json"
    private let encoder = JSONEncoder()
    private let decoder = JSONDecoder()
    private var siteScope = ""
    private var directoryURL: URL {
        let base = FileManager.default.urls(for: .applicationSupportDirectory, in: .userDomainMask).first
            ?? FileManager.default.temporaryDirectory
        return base.appendingPathComponent("ConversationHistory", isDirectory: true)
    }

    private init() {}

    func setSiteScope(_ siteURL: String) {
        let normalized = siteURL.trimmingCharacters(in: .whitespacesAndNewlines).lowercased()
        siteScope = normalized
    }

    func load() -> CachedSessionList? {
        let url = fileURL()
        guard let data = try? Data(contentsOf: url),
              let cached = try? decoder.decode(CachedSessionList.self, from: data) else {
            return nil
        }
        return cached
    }

    func save(sessions: [LiveSession], teamSessions: [LiveSession], liveCount: Int) {
        let snapshot = CachedSessionList(
            sessions: sessions,
            teamSessions: teamSessions,
            liveCount: liveCount
        )
        try? FileManager.default.createDirectory(at: directoryURL, withIntermediateDirectories: true)
        guard let data = try? encoder.encode(snapshot) else { return }
        try? data.write(to: fileURL(), options: .atomic)
    }

    func clear() {
        try? FileManager.default.removeItem(at: fileURL())
    }

    private func fileURL() -> URL {
        let scope = siteScope.isEmpty ? "default" : String(siteScope.hashValue)
        return directoryURL.appendingPathComponent("\(scope)-\(fileName)")
    }
}

enum SessionRefreshMode {
    case lightweight
    case full
}
