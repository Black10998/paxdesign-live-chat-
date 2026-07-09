import Foundation

struct PAXTaskItem: Codable, Identifiable, Hashable {
    let id: String
    var title: String
    var notes: String
    var dueDate: Date?
    var isCompleted: Bool
    var priority: Priority
    let createdAt: Date

    enum Priority: String, Codable, CaseIterable, Identifiable {
        case low, medium, high

        var id: String { rawValue }

        var title: String {
            switch self {
            case .low: return L10n.TaskPriorityLow
            case .medium: return L10n.TaskPriorityMedium
            case .high: return L10n.TaskPriorityHigh
            }
        }
    }

    init(title: String, notes: String = "", dueDate: Date? = nil, priority: Priority = .medium) {
        self.id = UUID().uuidString
        self.title = title
        self.notes = notes
        self.dueDate = dueDate
        self.isCompleted = false
        self.priority = priority
        self.createdAt = Date()
    }
}

@MainActor
final class TaskStore: ObservableObject {
    static let shared = TaskStore()

    @Published private(set) var tasks: [PAXTaskItem] = []

    private let storageKey = "pax.tasks"
    private var isServerSynced = false

    var openCount: Int { tasks.filter { !$0.isCompleted }.count }
    var overdueCount: Int {
        tasks.filter { !$0.isCompleted && ($0.dueDate ?? .distantFuture) < Date() }.count
    }

    private init() {
        load()
    }

    func applyServerTasks(_ items: [PAXTaskItem]) {
        tasks = items
        isServerSynced = true
        persist()
    }

    func resetForLogout() {
        tasks = []
        isServerSynced = false
        UserDefaults.standard.removeObject(forKey: storageKey)
    }

    func add(title: String, notes: String = "", dueDate: Date? = nil, priority: PAXTaskItem.Priority = .medium, auth: AuthStore) async {
        var task = PAXTaskItem(title: title, notes: notes, dueDate: dueDate, priority: priority)
        if let api = auth.api {
            do {
                let saved = try await api.savePlatformTask(task.apiPayload())
                task = PAXTaskItem(api: saved)
            } catch {
                return
            }
        }
        tasks.insert(task, at: 0)
        persist()
        await ActivityLogService.shared.log(
            category: L10n.ModuleTasks,
            title: L10n.ActivityTaskCreated,
            detail: title,
            module: PlatformModule.tasks.rawValue,
            severity: .action,
            auth: auth
        )
        WidgetDataStore.shared.syncFromApp()
    }

    func toggleComplete(_ task: PAXTaskItem, auth: AuthStore) async {
        guard let index = tasks.firstIndex(where: { $0.id == task.id }) else { return }
        tasks[index].isCompleted.toggle()
        if let api = auth.api {
            if let saved = try? await api.savePlatformTask(tasks[index].apiPayload()) {
                tasks[index] = PAXTaskItem(api: saved)
            }
        }
        persist()
        await ActivityLogService.shared.log(
            category: L10n.ModuleTasks,
            title: tasks[index].isCompleted ? L10n.ActivityTaskCompleted : L10n.ActivityTaskReopened,
            detail: task.title,
            module: PlatformModule.tasks.rawValue,
            severity: .success,
            auth: auth
        )
        WidgetDataStore.shared.syncFromApp()
    }

    func delete(_ task: PAXTaskItem, auth: AuthStore) async {
        if let api = auth.api {
            _ = try? await api.deletePlatformTask(id: task.id)
        }
        tasks.removeAll { $0.id == task.id }
        persist()
        WidgetDataStore.shared.syncFromApp()
    }

    private func load() {
        guard let data = UserDefaults.standard.data(forKey: storageKey),
              let decoded = try? JSONDecoder().decode([PAXTaskItem].self, from: data) else { return }
        tasks = decoded
        isServerSynced = !decoded.isEmpty
    }

    private func persist() {
        guard let data = try? JSONEncoder().encode(tasks) else { return }
        UserDefaults.standard.set(data, forKey: storageKey)
    }
}
