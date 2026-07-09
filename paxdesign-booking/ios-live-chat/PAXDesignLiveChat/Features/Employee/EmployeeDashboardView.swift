import SwiftUI

struct EmployeeDashboardView: View {
    @EnvironmentObject private var auth: AuthStore
    @EnvironmentObject private var coordinator: ChatCoordinator
    @EnvironmentObject private var settings: AppSettingsStore
    @ObservedObject private var tasks = TaskStore.shared
    @ObservedObject private var platform = PlatformSyncService.shared

    private var mySessions: [LiveSession] {
        coordinator.sessions.filter { !$0.isTeamDM && ($0.isAdmin || $0.needsReply) }
    }

    private var unreadCount: Int {
        mySessions.filter { $0.needsReply && !settings.readSessionIds.contains($0.sessionId) }.count
    }

    var body: some View {
        List {
            Section {
                PlatformHeroHeader(
                    title: L10n.EmployeeWelcome(platform.employee?.name ?? auth.profile?.displayName ?? L10n.ProfileRoleStaff),
                    subtitle: L10n.ModuleEmployeeSubtitle,
                    systemImage: "person.crop.circle.badge.checkmark",
                    gradient: [.mint, .teal]
                )
                .listRowInsets(EdgeInsets())
                .listRowBackground(Color.clear)
            }

            Section(L10n.EmployeeToday) {
                metricRow(L10n.EmployeeAssignedChats, value: "\(platform.employee?.assignedChats ?? mySessions.count)", icon: "bubble.left.and.bubble.right.fill", tint: .blue)
                metricRow(L10n.EmployeeUnread, value: "\(platform.employee?.unreadChats ?? unreadCount)", icon: "envelope.badge.fill", tint: .orange)
                metricRow(L10n.EmployeeOpenTasks, value: "\(platform.employee?.openTasks ?? tasks.openCount)", icon: "checklist", tint: .green)
                metricRow(L10n.EmployeeRole, value: auth.roleLabel, icon: "person.badge.key.fill", tint: .purple)
            }

            Section(L10n.EmployeePermissions) {
                if let perms = auth.profile?.permissions, auth.profile?.isSuperAdmin != true {
                    ForEach(PermissionLabels.items(for: perms)) { item in
                        PermissionOverviewRow(title: item.title, enabled: item.enabled)
                    }
                } else {
                    Label(L10n.AccountSuperAdmin, systemImage: "star.fill")
                        .foregroundStyle(PAXTheme.accent)
                }
            }

            Section(L10n.EmployeeShortcuts) {
                NavigationLink { TasksModuleView() } label: {
                    Label(L10n.ModuleTasks, systemImage: "checklist")
                }
                NavigationLink { CalendarModuleView() } label: {
                    Label(L10n.ModuleCalendar, systemImage: "calendar")
                }
                NavigationLink { NotificationsCenterView() } label: {
                    Label(L10n.PlatformNotifications, systemImage: "bell.badge")
                }
            }
        }
        .listStyle(.insetGrouped)
        .scrollContentBackground(.hidden)
        .paxScreenBackground()
        .navigationTitle(L10n.ModuleEmployee)
        .navigationBarTitleDisplayMode(.large)
        .refreshable {
            await platform.refreshEmployee(auth: auth)
            await coordinator.refreshSessions(auth: auth)
        }
    }

    private func metricRow(_ title: String, value: String, icon: String, tint: Color) -> some View {
        HStack(spacing: 12) {
            Image(systemName: icon).foregroundStyle(tint).frame(width: 28)
            Text(title)
            Spacer()
            Text(value).font(.headline).foregroundStyle(tint)
        }
    }
}

private extension L10n {
    static func EmployeeWelcome(_ name: String) -> String {
        String(format: String(localized: "employee.welcome"), name)
    }
}
