import Foundation

struct ActivityLogEntry: Codable, Identifiable, Hashable {
    let id: String
    let timestamp: Date
    let category: String
    let title: String
    let detail: String
    let module: String
    let severity: Severity

    enum Severity: String, Codable {
        case info, success, warning, action
    }

    init(
        category: String,
        title: String,
        detail: String = "",
        module: String = "system",
        severity: Severity = .info
    ) {
        self.id = UUID().uuidString
        self.timestamp = Date()
        self.category = category
        self.title = title
        self.detail = detail
        self.module = module
        self.severity = severity
    }
}

@MainActor
final class ActivityLogService: ObservableObject {
    static let shared = ActivityLogService()

    @Published private(set) var entries: [ActivityLogEntry] = []

    private let maxEntries = 250
    private let storageKey = "pax.activity.log"

    private init() {
        load()
    }

    func applyServerEntries(_ items: [ActivityLogEntry]) {
        entries = Array(items.prefix(maxEntries))
        persist()
    }

    func resetForLogout() {
        entries = []
        UserDefaults.standard.removeObject(forKey: storageKey)
    }

    func log(
        category: String,
        title: String,
        detail: String = "",
        module: String = "system",
        severity: ActivityLogEntry.Severity = .info,
        auth: AuthStore? = nil
    ) async {
        if let api = auth?.api {
            if let record = try? await api.appendPlatformActivity(
                module: module,
                title: title,
                detail: detail,
                severity: severity.rawValue,
                category: category
            ) {
                let entry = ActivityLogEntry(api: record)
                entries.insert(entry, at: 0)
                if entries.count > maxEntries {
                    entries = Array(entries.prefix(maxEntries))
                }
                persist()
                WidgetDataStore.shared.syncFromApp()
                return
            }
        }

        let entry = ActivityLogEntry(
            category: category,
            title: title,
            detail: detail,
            module: module,
            severity: severity
        )
        entries.insert(entry, at: 0)
        if entries.count > maxEntries {
            entries = Array(entries.prefix(maxEntries))
        }
        persist()
        WidgetDataStore.shared.syncFromApp()
    }

    func clear(auth: AuthStore) async {
        if let api = auth.api {
            _ = try? await api.clearPlatformActivity()
        }
        entries = []
        persist()
    }

    func entries(for module: String) -> [ActivityLogEntry] {
        entries.filter { $0.module == module }
    }

    private func load() {
        guard let data = UserDefaults.standard.data(forKey: storageKey),
              let decoded = try? JSONDecoder().decode([ActivityLogEntry].self, from: data) else {
            entries = []
            return
        }
        entries = decoded
    }

    private func persist() {
        guard let data = try? JSONEncoder().encode(entries) else { return }
        DeferredUserDefaults.setData(data, forKey: storageKey)
    }
}
