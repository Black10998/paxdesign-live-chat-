import SwiftUI

struct EmployeeDashboardView: View {
    @EnvironmentObject private var auth: AuthStore
    @EnvironmentObject private var coordinator: ChatCoordinator
    @EnvironmentObject private var settings: AppSettingsStore
    @ObservedObject private var tasks = TaskStore.shared
    @ObservedObject private var platform = PlatformSyncService.shared
    @State private var isInitialLoading = true

    private var mySessions: [LiveSession] {
        coordinator.sessions.filter { !$0.isTeamDM && ($0.isAdmin || $0.needsReply) }
    }

    private var unreadCount: Int {
        mySessions.filter { $0.needsReply && !settings.readSessionIds.contains($0.sessionId) }.count
    }

    var body: some View {
        List {
            if isInitialLoading {
                Section {
                    PAXScreenLoadingStack(status: L10n.ModuleEmployee, rowCount: 4)
                }
            } else {
            Section {
                PlatformHeroHeader(
                    title: L10n.EmployeeWelcome(platform.employee?.name ?? auth.profile?.displayName ?? L10n.ProfileRoleStaff),
                    subtitle: L10n.ModuleEmployeeSubtitle,
                    systemImage: "person.crop.circle.badge.checkmark",
                    tint: PAXTheme.accent
                )
                .listRowInsets(EdgeInsets())
                .listRowBackground(Color.clear)
            }

            Section(L10n.EmployeeToday) {
                metricRow(L10n.EmployeeAssignedChats, value: "\(platform.employee?.assignedChats ?? mySessions.count)", icon: "bubble.left.and.bubble.right.fill", tint: PAXTheme.accent)
                metricRow(L10n.EmployeeUnread, value: "\(platform.employee?.unreadChats ?? unreadCount)", icon: "envelope.badge.fill", tint: PAXTheme.accent)
                metricRow(L10n.EmployeeOpenTasks, value: "\(platform.employee?.openTasks ?? tasks.openCount)", icon: "checklist", tint: PAXTheme.accent)
                metricRow(L10n.EmployeeRole, value: auth.roleLabel, icon: "person.badge.key.fill", tint: PAXTheme.accent)
            }

            Section(L10n.EmployeePermissions) {
                if let perms = auth.profile?.permissions, auth.profile?.isSuperAdmin != true {
                    ForEach(PermissionLabels.items(for: perms)) { item in
                        PermissionOverviewRow(title: item.title, enabled: item.enabled)
                    }
                } else {
                    HStack {
                        PAXIcon("star.fill", size: .row)
                        Text(L10n.RoleExecutiveDirector)
                            .foregroundStyle(PAXTheme.accent)
                    }
                }
            }

            Section(L10n.EmployeeShortcuts) {
                NavigationLink { TasksModuleView() } label: {
                    Label { Text(L10n.ModuleTasks) } icon: { PAXIcon("checklist") }
                }
                NavigationLink { CalendarModuleView() } label: {
                    Label { Text(L10n.ModuleCalendar) } icon: { PAXIcon("calendar") }
                }
                NavigationLink { NotificationsCenterView() } label: {
                    Label { Text(L10n.PlatformNotifications) } icon: { PAXIcon("bell.badge") }
                }
            }
            }
        }
        .listStyle(.insetGrouped)
        .scrollContentBackground(.hidden)
        .paxScreenBackground()
        .navigationTitle(L10n.ModuleEmployee)
        .navigationBarTitleDisplayMode(.large)
        .paxPremiumRefreshable(status: L10n.ModuleEmployee, rowCount: 4) {
            await platform.refreshEmployee(auth: auth)
            await coordinator.refreshSessions(auth: auth)
        }
        .onAppear {
            Task {
                await platform.refreshEmployee(auth: auth)
                try? await Task.sleep(nanoseconds: 450_000_000)
                withAnimation(.easeOut(duration: 0.25)) {
                    isInitialLoading = false
                }
            }
        }
    }

    private func metricRow(_ title: String, value: String, icon: String, tint: Color) -> some View {
        HStack(spacing: 12) {
            PAXIcon(icon, size: .row)
            Text(title)
            Spacer()
            Text(value).font(.headline).foregroundStyle(PAXTheme.textPrimary)
        }
    }
}

private extension L10n {
    static func EmployeeWelcome(_ name: String) -> String {
        String(format: String(localized: "employee.welcome"), name)
    }
}
