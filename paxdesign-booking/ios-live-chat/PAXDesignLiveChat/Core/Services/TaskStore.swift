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

    var openCount: Int { tasks.filter { !$0.isCompleted }.count }
    var overdueCount: Int {
        tasks.filter { !$0.isCompleted && ($0.dueDate ?? .distantFuture) < Date() }.count
    }

    private init() {
        load()
        if tasks.isEmpty { seedDefaults() }
    }

    func add(title: String, notes: String = "", dueDate: Date? = nil, priority: PAXTaskItem.Priority = .medium) {
        let task = PAXTaskItem(title: title, notes: notes, dueDate: dueDate, priority: priority)
        tasks.insert(task, at: 0)
        persist()
        ActivityLogService.shared.log(
            category: L10n.ModuleTasks,
            title: L10n.ActivityTaskCreated,
            detail: title,
            module: PlatformModule.tasks.rawValue,
            severity: .action
        )
        WidgetDataStore.shared.syncFromApp()
    }

    func toggleComplete(_ task: PAXTaskItem) {
        guard let index = tasks.firstIndex(where: { $0.id == task.id }) else { return }
        tasks[index].isCompleted.toggle()
        persist()
        ActivityLogService.shared.log(
            category: L10n.ModuleTasks,
            title: tasks[index].isCompleted ? L10n.ActivityTaskCompleted : L10n.ActivityTaskReopened,
            detail: task.title,
            module: PlatformModule.tasks.rawValue,
            severity: .success
        )
        WidgetDataStore.shared.syncFromApp()
    }

    func delete(_ task: PAXTaskItem) {
        tasks.removeAll { $0.id == task.id }
        persist()
        WidgetDataStore.shared.syncFromApp()
    }

    private func seedDefaults() {
        tasks = [
            PAXTaskItem(title: L10n.TaskSampleFollowUp, notes: L10n.TaskSampleFollowUpNote, dueDate: Calendar.current.date(byAdding: .day, value: 1, to: Date()), priority: .high),
            PAXTaskItem(title: L10n.TaskSampleReview, priority: .medium)
        ]
        persist()
    }

    private func load() {
        guard let data = UserDefaults.standard.data(forKey: storageKey),
              let decoded = try? JSONDecoder().decode([PAXTaskItem].self, from: data) else { return }
        tasks = decoded
    }

    private func persist() {
        guard let data = try? JSONEncoder().encode(tasks) else { return }
        UserDefaults.standard.set(data, forKey: storageKey)
    }
}
