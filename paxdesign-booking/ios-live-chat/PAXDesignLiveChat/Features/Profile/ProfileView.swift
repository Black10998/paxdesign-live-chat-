import SwiftUI

struct ProfileView: View {
    @EnvironmentObject private var auth: AuthStore

    private var profile: AdminProfile? { auth.profile }
    private var permissions: AdminPermissions { profile?.permissions ?? .full }

    var body: some View {
        List {
            Section {
                HStack(spacing: 18) {
                    ProfileAvatarView(size: 80)
                    VStack(alignment: .leading, spacing: 6) {
                        Text(profile?.displayName ?? L10n.CommonAdministrator)
                            .font(.title2.weight(.bold))
                        roleBadge
                    }
                    .padding(.vertical, 8)
                }
            }

            Section(L10n.ProfileAccountInfo) {
                LabeledContent(L10n.SettingsProfile, value: profile?.displayName ?? L10n.CommonAdministrator)
                    .font(.subheadline)
                LabeledContent(L10n.StaffWordpressEmail, value: profile?.displayEmail ?? PrivacyMask.email(auth.username, revealFull: false))
                    .font(.subheadline)
                LabeledContent(L10n.LoginWebsite, value: profile?.siteUrl ?? auth.siteURLString)
                    .font(.subheadline)
                if let username = profile?.displayUsernameIfDistinct {
                    LabeledContent(L10n.LoginUsername, value: username)
                        .font(.subheadline)
                }
                LabeledContent(L10n.CommonPlugin, value: profile?.pluginVer ?? "—")
                    .font(.subheadline)
                LabeledContent(L10n.CommonVersion, value: PAXAppInfo.fullVersion)
                    .font(.subheadline)
            }

            Section(L10n.ProfilePermissions) {
                if profile?.isSuperAdmin == true {
                    HStack {
                        Label(L10n.AccountSuperAdmin, systemImage: "star.fill")
                            .foregroundStyle(PAXTheme.accent)
                        Spacer()
                        Text(L10n.CommonActive)
                            .font(.caption.weight(.semibold))
                            .foregroundStyle(PAXTheme.accent)
                    }
                } else {
                    ForEach(PermissionLabels.items(for: permissions)) { item in
                        PermissionOverviewRow(title: item.title, enabled: item.enabled)
                    }
                }
            }

            Section {
                NavigationLink {
                    SettingsRootView()
                } label: {
                    Label(L10n.AccountSettings, systemImage: "gearshape")
                }
                NavigationLink {
                    AppLockSettingsView()
                } label: {
                    Label(L10n.SettingsAppLock, systemImage: "lock.shield")
                }
            }

            Section {
                Button(L10n.SettingsSignOut, role: .destructive) {
                    Task {
                        await PushService.shared.unregisterTokenFromBackend(auth: auth)
                        auth.logout()
                    }
                }
            }
        }
        .listStyle(.insetGrouped)
        .scrollContentBackground(.hidden)
        .background(PAXBackground())
        .navigationTitle(L10n.ProfileTitle)
        .navigationBarTitleDisplayMode(.large)
    }

    @ViewBuilder
    private var roleBadge: some View {
        if profile?.isSuperAdmin == true {
            Text(L10n.AccountSuperAdmin)
                .font(.caption2.weight(.bold))
                .foregroundStyle(PAXTheme.accent)
                .padding(.horizontal, 8)
                .padding(.vertical, 3)
                .background(Capsule().fill(PAXTheme.accentSoft))
        } else if auth.canManageUsers {
            Text(L10n.ProfileRoleManager)
                .font(.caption2.weight(.bold))
                .foregroundStyle(.purple)
                .padding(.horizontal, 8)
                .padding(.vertical, 3)
                .background(Capsule().fill(Color.purple.opacity(0.12)))
        } else {
            Text(L10n.ProfileRoleStaff)
                .font(.caption2.weight(.bold))
                .foregroundStyle(PAXTheme.textSecondary)
                .padding(.horizontal, 8)
                .padding(.vertical, 3)
                .background(Capsule().fill(PAXTheme.surface))
        }
    }
}
