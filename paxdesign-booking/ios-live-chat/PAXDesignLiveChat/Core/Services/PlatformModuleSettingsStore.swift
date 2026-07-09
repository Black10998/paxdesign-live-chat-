import Foundation
import SwiftUI

@MainActor
final class PlatformModuleSettingsStore: ObservableObject {
    static let shared = PlatformModuleSettingsStore()

    @Published var calendarWeekStartMonday = true
    @Published var calendarShowWeekNumbers = false
    @Published var tasksSortByDueDate = true
    @Published var tasksShowCompleted = true
    @Published var filesGroupByCategory = true
    @Published var reportsIncludeClosed = true
    @Published var activityVerboseLogging = false
    @Published var dashboardShowChart = true
    @Published var dashboardShowUpcoming = true
    @Published var notificationsInteractiveCards = true

    private var saveTask: Task<Void, Never>?
    private var isApplyingServer = false

    private init() {}

    func applyServerSettings(_ settings: [String: Bool]) {
        isApplyingServer = true
        defer { isApplyingServer = false }

        calendarWeekStartMonday = settings["calendar_week_start_monday"] ?? calendarWeekStartMonday
        calendarShowWeekNumbers = settings["calendar_show_week_numbers"] ?? calendarShowWeekNumbers
        tasksSortByDueDate = settings["tasks_sort_due_date"] ?? tasksSortByDueDate
        tasksShowCompleted = settings["tasks_show_completed"] ?? tasksShowCompleted
        filesGroupByCategory = settings["files_group_category"] ?? filesGroupByCategory
        reportsIncludeClosed = settings["reports_include_closed"] ?? reportsIncludeClosed
        activityVerboseLogging = settings["activity_verbose"] ?? activityVerboseLogging
        dashboardShowChart = settings["dashboard_show_chart"] ?? dashboardShowChart
        dashboardShowUpcoming = settings["dashboard_show_upcoming"] ?? dashboardShowUpcoming
        notificationsInteractiveCards = settings["notifications_interactive"] ?? notificationsInteractiveCards
    }

    func scheduleSave(auth: AuthStore) {
        guard !isApplyingServer else { return }
        saveTask?.cancel()
        saveTask = Task {
            try? await Task.sleep(nanoseconds: 450_000_000)
            guard !Task.isCancelled else { return }
            await persist(auth: auth)
        }
    }

    func persist(auth: AuthStore) async {
        guard let api = auth.api else { return }
        let settings: [String: Bool] = [
            "calendar_week_start_monday": calendarWeekStartMonday,
            "calendar_show_week_numbers": calendarShowWeekNumbers,
            "tasks_sort_due_date": tasksSortByDueDate,
            "tasks_show_completed": tasksShowCompleted,
            "files_group_category": filesGroupByCategory,
            "reports_include_closed": reportsIncludeClosed,
            "activity_verbose": activityVerboseLogging,
            "dashboard_show_chart": dashboardShowChart,
            "dashboard_show_upcoming": dashboardShowUpcoming,
            "notifications_interactive": notificationsInteractiveCards,
        ]
        _ = try? await api.savePlatformSettings(settings)
    }
}
