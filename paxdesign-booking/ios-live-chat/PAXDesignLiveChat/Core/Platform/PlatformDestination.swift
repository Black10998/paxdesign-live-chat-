import SwiftUI

@ViewBuilder
func platformDestination(for module: PlatformModule) -> some View {
    switch module {
    case .dashboard: DashboardView()
    case .calendar: CalendarModuleView()
    case .tasks: TasksModuleView()
    case .files: FilesModuleView()
    case .orders: StaffOrdersListView()
    case .notifications: NotificationsCenterView()
    case .reports: ReportsAnalyticsView()
    case .activityLog: ActivityLogView()
    case .employee: EmployeeDashboardView()
    case .administration: AdministrationHubView()
    case .devices: DeviceManagementView()
    case .settings: SettingsRootView()
    case .profile: ProfileView()
    case .help: HelpView()
    case .about: AboutView()
    case .chats, .team, .live: EmptyView()
    }
}
