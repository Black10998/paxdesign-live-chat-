import SwiftUI

struct PlatformHubView: View {
    @EnvironmentObject private var auth: AuthStore
    @EnvironmentObject private var coordinator: ChatCoordinator
    @StateObject private var settings = AppSettingsStore.shared

    private var canManageUsers: Bool { auth.canManageUsers }
    private var canAccessSecurity: Bool { auth.canAccessSecurity }

    private var unreadCount: Int {
        coordinator.sessions.filter {
            !$0.isTeamDM && $0.needsReply && !settings.readSessionIds.contains($0.sessionId)
        }.count
    }

    private var websiteURL: URL {
        if let url = URL(string: auth.siteURLString), !auth.siteURLString.isEmpty { return url }
        return PAXLegalLinks.support
    }

    var body: some View {
        ScrollView {
            VStack(alignment: .leading, spacing: 22) {
                profileCard

                VStack(alignment: .leading, spacing: 12) {
                    Text(L10n.PlatformModules)
                        .font(.headline)
                        .foregroundStyle(PAXTheme.textPrimary)
                        .padding(.horizontal, 4)

                    LazyVGrid(columns: [GridItem(.flexible(), spacing: 12), GridItem(.flexible(), spacing: 12)], spacing: 12) {
                        NavigationLink {
                            NotificationsCenterView()
                        } label: {
                            PlatformModuleCard(
                                title: L10n.PlatformNotifications,
                                subtitle: L10n.PlatformNotificationsSubtitle,
                                systemImage: "bell.badge.fill",
                                tint: .orange,
                                badge: unreadCount + coordinator.liveCount
                            )
                        }
                        .buttonStyle(.plain)

                        NavigationLink {
                            SettingsRootView()
                        } label: {
                            PlatformModuleCard(
                                title: L10n.AccountSettings,
                                subtitle: L10n.PlatformSettingsSubtitle,
                                systemImage: "gearshape.fill",
                                tint: PAXTheme.accent
                            )
                        }
                        .buttonStyle(.plain)

                        if canManageUsers {
                            NavigationLink {
                                DeviceManagementView()
                            } label: {
                                PlatformModuleCard(
                                    title: L10n.PlatformDevices,
                                    subtitle: L10n.PlatformDevicesSubtitle,
                                    systemImage: "iphone.and.arrow.forward",
                                    tint: .blue
                                )
                            }
                            .buttonStyle(.plain)

                            NavigationLink {
                                AdministrationHubView()
                            } label: {
                                PlatformModuleCard(
                                    title: L10n.PlatformAdministration,
                                    subtitle: L10n.PlatformAdministrationSubtitle,
                                    systemImage: "shield.lefthalf.filled",
                                    tint: .purple
                                )
                            }
                            .buttonStyle(.plain)
                        }

                        NavigationLink {
                            HelpView()
                        } label: {
                            PlatformModuleCard(
                                title: L10n.AccountHelp,
                                subtitle: L10n.PlatformHelpSubtitle,
                                systemImage: "questionmark.circle.fill",
                                tint: .teal
                            )
                        }
                        .buttonStyle(.plain)

                        NavigationLink {
                            AboutView()
                        } label: {
                            PlatformModuleCard(
                                title: L10n.AccountAbout,
                                subtitle: PAXAppInfo.fullVersion,
                                systemImage: "info.circle.fill",
                                tint: .indigo
                            )
                        }
                        .buttonStyle(.plain)
                    }
                }

                legalSection
                signOutSection
            }
            .padding(.horizontal, 16)
            .padding(.vertical, 12)
        }
        .background(PAXBackground())
        .navigationTitle(L10n.PlatformTitle)
        .navigationBarTitleDisplayMode(.large)
    }

    private var profileCard: some View {
        NavigationLink {
            ProfileView()
        } label: {
            HStack(spacing: 16) {
                ProfileAvatarView(size: 64)

                VStack(alignment: .leading, spacing: 5) {
                    Text(auth.profile?.name ?? L10n.CommonAdministrator)
                        .font(.title3.weight(.semibold))
                        .foregroundStyle(PAXTheme.textPrimary)
                    Text(auth.profile?.displayEmail ?? PrivacyMask.email(auth.username, revealFull: false))
                        .font(.subheadline)
                        .foregroundStyle(PAXTheme.textSecondary)
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
            .padding(18)
            .background(
                RoundedRectangle(cornerRadius: 20, style: .continuous)
                    .fill(PAXTheme.surface.opacity(0.94))
                    .overlay(
                        RoundedRectangle(cornerRadius: 20, style: .continuous)
                            .stroke(PAXTheme.border.opacity(0.55), lineWidth: 0.5)
                    )
            )
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
            .background(
                RoundedRectangle(cornerRadius: 18, style: .continuous)
                    .fill(PAXTheme.surface.opacity(0.92))
            )
        }
    }

    private var signOutSection: some View {
        Button(L10n.SettingsSignOut, role: .destructive) {
            Task {
                await PushService.shared.unregisterTokenFromBackend(auth: auth)
                auth.logout()
            }
        }
        .font(.body.weight(.semibold))
        .frame(maxWidth: .infinity)
        .padding(.vertical, 14)
        .background(
            RoundedRectangle(cornerRadius: 16, style: .continuous)
                .fill(PAXTheme.danger.opacity(0.12))
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
