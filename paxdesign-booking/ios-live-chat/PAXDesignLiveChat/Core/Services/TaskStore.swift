import Foundation

struct PAXTaskItem: Codable, Identifiable, Hashable {
    let id: String
    var title: String
    var notes: String
    var dueDate: Date?
    var isCompleted: Bool
    var priority: Priority
    let createdAt: Date
    var createdByUserId: Int
    var assignedUserId: Int
    var assignedUserName: String

    enum CodingKeys: String, CodingKey {
        case id, title, notes, dueDate, isCompleted, priority, createdAt
        case createdByUserId, assignedUserId, assignedUserName
    }

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

    init(
        title: String,
        notes: String = "",
        dueDate: Date? = nil,
        priority: Priority = .medium,
        createdByUserId: Int = 0,
        assignedUserId: Int = 0,
        assignedUserName: String = ""
    ) {
        self.id = UUID().uuidString
        self.title = title
        self.notes = notes
        self.dueDate = dueDate
        self.isCompleted = false
        self.priority = priority
        self.createdAt = Date()
        self.createdByUserId = createdByUserId
        self.assignedUserId = assignedUserId
        self.assignedUserName = assignedUserName
    }

    init(from decoder: Decoder) throws {
        let container = try decoder.container(keyedBy: CodingKeys.self)
        id = try container.decode(String.self, forKey: .id)
        title = try container.decode(String.self, forKey: .title)
        notes = (try? container.decode(String.self, forKey: .notes)) ?? ""
        dueDate = try? container.decode(Date.self, forKey: .dueDate)
        isCompleted = (try? container.decode(Bool.self, forKey: .isCompleted)) ?? false
        priority = (try? container.decode(Priority.self, forKey: .priority)) ?? .medium
        createdAt = (try? container.decode(Date.self, forKey: .createdAt)) ?? Date()
        createdByUserId = (try? container.decode(Int.self, forKey: .createdByUserId)) ?? 0
        assignedUserId = (try? container.decode(Int.self, forKey: .assignedUserId)) ?? 0
        assignedUserName = (try? container.decode(String.self, forKey: .assignedUserName)) ?? ""
    }

    func encode(to encoder: Encoder) throws {
        var container = encoder.container(keyedBy: CodingKeys.self)
        try container.encode(id, forKey: .id)
        try container.encode(title, forKey: .title)
        try container.encode(notes, forKey: .notes)
        try container.encodeIfPresent(dueDate, forKey: .dueDate)
        try container.encode(isCompleted, forKey: .isCompleted)
        try container.encode(priority, forKey: .priority)
        try container.encode(createdAt, forKey: .createdAt)
        try container.encode(createdByUserId, forKey: .createdByUserId)
        try container.encode(assignedUserId, forKey: .assignedUserId)
        try container.encode(assignedUserName, forKey: .assignedUserName)
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

    func add(
        title: String,
        notes: String = "",
        dueDate: Date? = nil,
        priority: PAXTaskItem.Priority = .medium,
        assignedUserId: Int = 0,
        auth: AuthStore
    ) async {
        var task = PAXTaskItem(
            title: title,
            notes: notes,
            dueDate: dueDate,
            priority: priority,
            createdByUserId: auth.profile?.userId ?? 0,
            assignedUserId: assignedUserId,
            assignedUserName: ""
        )
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
        DeferredUserDefaults.setData(data, forKey: storageKey)
    }
}
