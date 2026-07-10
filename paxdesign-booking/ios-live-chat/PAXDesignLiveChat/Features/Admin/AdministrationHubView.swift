import SwiftUI

struct AdministrationHubView: View {
    @EnvironmentObject private var auth: AuthStore
    @EnvironmentObject private var coordinator: ChatCoordinator
    @EnvironmentObject private var teamCoordinator: TeamMessagingCoordinator
    @State private var staffCount = 0
    @State private var isLoadingStaff = true

    private var canManageUsers: Bool { auth.canManageUsers }
    private var canManageTeamPermissions: Bool { auth.canManageTeamPermissions || auth.canManageUsers }
    private var canManageCustomers: Bool { auth.canManageCustomerProfiles || auth.canManageUsers }

    var body: some View {
        List {
            Section {
                PlatformHeroHeader(
                    title: L10n.AdminTitle,
                    subtitle: L10n.AdminSubtitle,
                    systemImage: "shield.lefthalf.filled",
                    gradient: [.purple, .indigo]
                )
                .listRowInsets(EdgeInsets(top: 8, leading: 0, bottom: 8, trailing: 0))
                .listRowBackground(Color.clear)
                .listRowSeparator(.hidden)
            }

            if auth.profile?.isSuperAdmin == true {
                Section {
                    NavigationLink {
                        ProfileView()
                    } label: {
                        adminProfileRow
                    }
                }
            }

            if isLoadingStaff {
                Section {
                    PAXScreenLoadingStack(status: "Administration wird geladen", rowCount: 3)
                }
            }

            Section(L10n.AdminActivity) {
                activityTile(
                    value: "\(customerSessionCount)",
                    label: L10n.AdminActivitySessions,
                    systemImage: "bubble.left.and.bubble.right",
                    tint: PAXTheme.accent
                )
                activityTile(
                    value: "\(coordinator.liveCount)",
                    label: L10n.AdminActivityLive,
                    systemImage: "bell.and.waves.left.and.right",
                    tint: .orange
                )
                activityTile(
                    value: isLoadingStaff ? "…" : "\(staffCount)",
                    label: L10n.AdminActivityTeam,
                    systemImage: "person.3",
                    tint: .teal
                )
            }

            Section(L10n.AdminManagement) {
                if canManageTeamPermissions {
                    NavigationLink {
                        StaffManagementView()
                    } label: {
                        Label(L10n.AdminEmployeeManagement, systemImage: "person.2.badge.gearshape")
                    }
                }
                NavigationLink {
                    TeamMessagesHubView()
                } label: {
                    Label(L10n.AdminTeamManagement, systemImage: "person.3.sequence")
                }
                if canManageCustomers {
                    NavigationLink {
                        CustomerProfilesView()
                    } label: {
                        Label("Kundenprofile", systemImage: "person.crop.circle.badge.checkmark")
                    }
                }
                NavigationLink {
                    DeviceManagementView()
                } label: {
                    Label(L10n.PlatformDevices, systemImage: "iphone.and.arrow.forward")
                }
            }

            Section(L10n.AdminPermissionsOverview) {
                if auth.profile?.isSuperAdmin == true {
                    Label(L10n.AccountSuperAdmin, systemImage: "star.fill")
                        .foregroundStyle(PAXTheme.accent)
                } else if let perms = auth.profile?.permissions {
                    ForEach(PermissionLabels.items(for: perms)) { item in
                        PermissionOverviewRow(title: item.title, enabled: item.enabled)
                    }
                }
            }

            Section(L10n.AdminSettings) {
                NavigationLink {
                    SettingsRootView()
                } label: {
                    Label(L10n.AccountSettings, systemImage: "gearshape")
                }
                if auth.canAccessSecurity {
                    NavigationLink {
                        SecurityView()
                    } label: {
                        Label(L10n.LegalSecurity, systemImage: "lock.shield")
                    }
                }
                NavigationLink {
                    LiveChatSettingsView()
                } label: {
                    Label(L10n.SettingsSectionLiveChat, systemImage: "bubble.left.and.bubble.right")
                }
            }
        }
        .listStyle(.insetGrouped)
        .scrollContentBackground(.hidden)
        .paxScreenBackground()
        .navigationTitle(L10n.AdminTitle)
        .navigationBarTitleDisplayMode(.large)
        .task { await loadStaffCount() }
        .paxPremiumRefreshable(status: "Administration wird geladen", rowCount: 3) {
            await coordinator.refreshSessions(auth: auth)
            await teamCoordinator.refresh(auth: auth)
            await loadStaffCount()
        }
    }

    private var customerSessionCount: Int {
        coordinator.sessions.filter { !$0.isTeamDM }.count
    }

    private var adminProfileRow: some View {
        HStack(spacing: 14) {
            ProfileAvatarView(size: 52)
            VStack(alignment: .leading, spacing: 4) {
                Text(auth.profile?.displayName ?? L10n.CommonAdministrator)
                    .font(.headline)
                Text(L10n.AccountSuperAdmin)
                    .font(.caption.weight(.semibold))
                    .foregroundStyle(PAXTheme.accent)
            }
        }
        .padding(.vertical, 4)
    }

    private func activityTile(value: String, label: String, systemImage: String, tint: Color) -> some View {
        HStack(spacing: 14) {
            Image(systemName: systemImage)
                .font(.title3)
                .foregroundStyle(tint)
                .frame(width: 32)
            VStack(alignment: .leading, spacing: 2) {
                Text(label)
                    .font(.subheadline)
                Text(value)
                    .font(.title2.weight(.bold))
                    .foregroundStyle(tint)
            }
        }
        .padding(.vertical, 4)
    }

    private func loadStaffCount() async {
        guard canManageTeamPermissions, let api = auth.api else {
            isLoadingStaff = false
            return
        }
        isLoadingStaff = true
        defer { isLoadingStaff = false }
        if let response = try? await api.fetchStaff() {
            staffCount = response.staff.filter(\.enabled).count
        }
    }
}
