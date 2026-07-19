import Foundation

@MainActor
final class PlatformSyncService: ObservableObject {
    static let shared = PlatformSyncService()

    @Published private(set) var isSyncing = false
    @Published private(set) var lastSyncDate: Date?
    @Published private(set) var dashboard: PlatformDashboardPayload?
    @Published private(set) var reports: PlatformReportsPayload?
    @Published private(set) var employee: PlatformEmployeePayload?
    @Published private(set) var notifications: PlatformNotificationsSummary?
    @Published private(set) var lastError: String?

    private init() {}

    func sync(auth: AuthStore) async {
        guard let api = auth.api else { return }
        guard !isSyncing else { return }

        isSyncing = true
        defer { isSyncing = false }

        do {
            let payload = try await api.fetchPlatformSync()
            apply(payload, auth: auth)
            lastSyncDate = Date()
            lastError = nil
        } catch {
            lastError = error.localizedDescription
        }
    }

    func refreshDashboard(auth: AuthStore) async {
        guard let api = auth.api else { return }
        if let payload = try? await api.fetchPlatformDashboard() {
            dashboard = payload
        }
    }

    func refreshNotifications(auth: AuthStore) async {
        guard let api = auth.api else { return }
        if let payload = try? await api.fetchPlatformNotifications() {
            notifications = payload
        }
    }

    func refreshReports(auth: AuthStore) async {
        guard let api = auth.api else { return }
        if let payload = try? await api.fetchPlatformReports() {
            reports = payload
        }
    }

    func refreshEmployee(auth: AuthStore) async {
        guard let api = auth.api else { return }
        if let payload = try? await api.fetchPlatformEmployee() {
            employee = payload
        }
    }

    private func apply(_ payload: PlatformSyncResponse, auth: AuthStore) {
        dashboard = payload.dashboard
        reports = payload.reports
        employee = payload.employee
        notifications = payload.notifications

        if var profile = auth.profile {
            profile = profile.updating(modulePermissions: payload.permissions.modulePermissions)
            auth.applyProfileUpdate(profile)
        }

        Task(priority: .utility) {
            await MainActor.run {
                TaskStore.shared.applyServerTasks(payload.tasks.map(PAXTaskItem.init(api:)))
                CalendarStore.shared.applyServerEvents(
                    payload.calendar.map(PAXCalendarEvent.init(api:)),
                    upcoming: payload.upcoming.map(PAXCalendarEvent.init(api:))
                )
                FileLibraryStore.shared.applyServerFiles(payload.files.map(PAXDocumentItem.init(api:)))
                ActivityLogService.shared.applyServerEntries(payload.activity.map(ActivityLogEntry.init(api:)))
                PlatformModuleSettingsStore.shared.applyServerSettings(payload.settings)
                WidgetDataStore.shared.syncFromApp()
            }
        }
    }

    func reset() {
        dashboard = nil
        reports = nil
        employee = nil
        notifications = nil
        lastSyncDate = nil
        lastError = nil
        TaskStore.shared.resetForLogout()
        CalendarStore.shared.resetForLogout()
        FileLibraryStore.shared.resetForLogout()
        ActivityLogService.shared.resetForLogout()
    }
}
