import SwiftUI

struct PlatformHubView: View {
    @EnvironmentObject private var auth: AuthStore
    @EnvironmentObject private var coordinator: ChatCoordinator
    @State private var showSearch = false

    private var canManageUsers: Bool { auth.canManageUsers }
    private var canAccessSecurity: Bool { auth.canAccessSecurity }

    private var unreadCount: Int {
        coordinator.unreadChatCount
    }

    private var websiteURL: URL {
        if let url = URL(string: auth.siteURLString), !auth.siteURLString.isEmpty { return url }
        return PAXLegalLinks.support
    }

    var body: some View {
        ScrollView {
            VStack(alignment: .leading, spacing: 22) {
                profileCard

                ForEach(PlatformModuleCategory.allCases) { category in
                    let modules = modules(for: category)
                    if !modules.isEmpty {
                        moduleSection(category: category, modules: modules)
                    }
                }

                legalSection
                signOutSection
            }
            .padding(.horizontal, 16)
            .padding(.vertical, 12)
        }
        .paxScreenBackground()
        .navigationTitle(L10n.PlatformTitle)
        .navigationBarTitleDisplayMode(.large)
        .toolbar {
            ToolbarItem(placement: .topBarTrailing) {
                Button { showSearch = true } label: {
                    Image(systemName: "magnifyingglass")
                }
            }
            ToolbarItem(placement: .topBarTrailing) {
                NavigationLink { ModuleSettingsHubView() } label: {
                    Image(systemName: "slider.horizontal.3")
                }
            }
        }
        .sheet(isPresented: $showSearch) {
            NavigationStack { GlobalSearchView() }
        }
    }

    private func modules(for category: PlatformModuleCategory) -> [PlatformModule] {
        var items = PlatformModuleAccess.availableHubModules(auth: auth).filter { $0.category == category }
        if category == .management {
            items += PlatformModule.adminModules.filter { PlatformModuleAccess.isAvailable($0, auth: auth) }
        }
        if category == .system {
            items += [.profile, .help, .about]
        }
        return items
    }

    private func moduleSection(category: PlatformModuleCategory, modules: [PlatformModule]) -> some View {
        VStack(alignment: .leading, spacing: 12) {
            Text(category.title)
                .font(.headline)
                .foregroundStyle(PAXTheme.textPrimary)
                .padding(.horizontal, 4)

            LazyVGrid(columns: [GridItem(.flexible(), spacing: 12), GridItem(.flexible(), spacing: 12)], spacing: 12) {
                ForEach(modules) { module in
                    NavigationLink(value: module) {
                        PlatformModuleCard(
                            title: module.title,
                            subtitle: module.subtitle,
                            systemImage: module.systemImage,
                            tint: module.tint,
                            badge: badge(for: module),
                            helpText: module.helpDescription
                        )
                    }
                    .buttonStyle(.plain)
                }
            }
        }
    }

    private func badge(for module: PlatformModule) -> Int {
        switch module {
        case .notifications: return unreadCount + coordinator.liveCount
        case .tasks: return TaskStore.shared.openCount
        default: return 0
        }
    }

    private var profileCard: some View {
        NavigationLink(value: PlatformModule.profile) {
            HStack(spacing: 16) {
                ProfileAvatarView(size: 64)

                VStack(alignment: .leading, spacing: 5) {
                    Text(auth.profile?.displayName ?? L10n.CommonAdministrator)
                        .font(.title3.weight(.semibold))
                        .foregroundStyle(PAXTheme.textPrimary)
                    if auth.profile?.isSuperAdmin == true {
                        Text(L10n.AccountSuperAdmin)
                            .font(.caption2.weight(.bold))
                            .foregroundStyle(PAXTheme.accent)
                            .padding(.horizontal, 8)
                            .padding(.vertical, 3)
                            .background(Capsule().fill(PAXTheme.accentSoft))
                    }
                }

                Spacer(minLength: 0)

                Image(systemName: "chevron.right")
                    .font(.caption.weight(.semibold))
                    .foregroundStyle(PAXTheme.textTertiary)
            }
            .paxCard(.hero)
        }
        .buttonStyle(.plain)
        .simultaneousGesture(TapGesture().onEnded { PAXHaptics.light() })
    }

    private var legalSection: some View {
        VStack(alignment: .leading, spacing: 10) {
            Text(L10n.PlatformLegal)
                .font(.headline)
                .foregroundStyle(PAXTheme.textPrimary)
                .padding(.horizontal, 4)

            VStack(spacing: 0) {
                Link(destination: websiteURL) {
                    platformLinkRow(L10n.AccountOfficialWebsite, systemImage: "globe", detail: websiteURL.host ?? "")
                }
                Divider().padding(.leading, 44)
                Link(destination: PAXLegalLinks.privacyPolicy) {
                    platformLinkRow(L10n.AccountPrivacyWeb, systemImage: "safari")
                }
                if canAccessSecurity {
                    Divider().padding(.leading, 44)
                    NavigationLink {
                        SecurityView()
                    } label: {
                        platformLinkRow(L10n.LegalSecurity, systemImage: "lock.shield")
                    }
                }
            }
            .paxCard(.list)
        }
    }

    private var signOutSection: some View {
        Button(L10n.SettingsSignOut) {
            PAXDelete.confirm(
                title: L10n.SettingsSignOut,
                message: L10n.SettingsSignOutMessage,
                confirmTitle: L10n.SettingsSignOut
            ) {
                Task {
                    await PushService.shared.unregisterTokenFromBackend(auth: auth)
                    auth.logout()
                }
            }
        }
        .font(.body.weight(.semibold))
        .frame(maxWidth: .infinity)
        .padding(.vertical, 14)
        .background(
            RoundedRectangle(cornerRadius: 16, style: .continuous)
                .fill(PAXTheme.danger.opacity(0.14))
                .overlay(
                    RoundedRectangle(cornerRadius: 16, style: .continuous)
                        .stroke(PAXTheme.danger.opacity(0.28), lineWidth: 1)
                )
        )
        .padding(.top, 4)
    }

    private func platformLinkRow(_ title: String, systemImage: String, detail: String = "") -> some View {
        HStack(spacing: 12) {
            Image(systemName: systemImage)
                .font(.body)
                .foregroundStyle(PAXTheme.accent)
                .frame(width: 28)
            Text(title)
                .foregroundStyle(PAXTheme.textPrimary)
            Spacer()
            if !detail.isEmpty {
                Text(detail)
                    .font(.caption)
                    .foregroundStyle(PAXTheme.textTertiary)
            }
            Image(systemName: "chevron.right")
                .font(.caption2.weight(.semibold))
                .foregroundStyle(PAXTheme.textTertiary)
        }
        .padding(.horizontal, 14)
        .padding(.vertical, 12)
    }
}
