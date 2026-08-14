import SwiftUI

struct SettingsRootView: View {
    @EnvironmentObject private var auth: AuthStore
    @EnvironmentObject private var settings: AppSettingsStore

    private var canManageSettings: Bool { auth.canManageSettings }

    var body: some View {
        List {
            profileHeaderSection

            Section(L10n.SettingsSectionGeneral) {
                NavigationLink {
                    GeneralSettingsView()
                } label: {
                    SettingsRowLabel(
                        title: L10n.SettingsSectionGeneral,
                        subtitle: L10n.SettingsGeneralSubtitle,
                        systemImage: "gearshape"
                    )
                }

                NavigationLink {
                    AppearanceSettingsView()
                } label: {
                    SettingsRowLabel(
                        title: L10n.SettingsSectionAppearance,
                        subtitle: settings.visualTheme.title,
                        systemImage: "paintbrush"
                    )
                }

                NavigationLink {
                    LanguageSettingsView()
                } label: {
                    SettingsRowLabel(
                        title: L10n.SettingsSectionLanguage,
                        subtitle: settings.languageMode.title,
                        systemImage: "globe"
                    )
                }
            }

            Section(L10n.SettingsSectionNotifications) {
                NavigationLink {
                    NotificationSettingsView()
                } label: {
                    SettingsRowLabel(
                        title: L10n.SettingsSectionNotifications,
                        subtitle: settings.notificationsEnabled ? L10n.CommonActive : L10n.SettingsDisabled,
                        systemImage: "bell.badge"
                    )
                }

                NavigationLink {
                    SoundSettingsView()
                } label: {
                    SettingsRowLabel(
                        title: L10n.SettingsSound,
                        subtitle: L10n.SettingsSoundSubtitle,
                        systemImage: "speaker.wave.2"
                    )
                }
            }

            Section(L10n.SettingsSectionSecurity) {
                NavigationLink {
                    AppLockSettingsView()
                } label: {
                    SettingsRowLabel(
                        title: L10n.SettingsAppLock,
                        subtitle: L10n.SettingsSecuritySubtitle,
                        systemImage: "lock.shield"
                    )
                }
            }

            Section(L10n.SettingsSectionLiveChat) {
                NavigationLink {
                    LiveChatSettingsView()
                } label: {
                    SettingsRowLabel(
                        title: L10n.SettingsSectionLiveChat,
                        subtitle: L10n.SettingsLiveChatSubtitle,
                        systemImage: "bubble.left.and.bubble.right"
                    )
                }

                NavigationLink {
                    ChatDisplaySettingsView()
                } label: {
                    SettingsRowLabel(
                        title: L10n.SettingsChatDisplay,
                        subtitle: L10n.SettingsChatDisplaySubtitle,
                        systemImage: "list.bullet.rectangle"
                    )
                }
            }

            if auth.canManageUsers {
                Section(L10n.SettingsTeamMessaging) {
                    NavigationLink {
                        TeamComposeView { _ in }
                    } label: {
                        SettingsRowLabel(
                            title: L10n.SettingsTeamMessaging,
                            subtitle: L10n.SettingsTeamMessagingSubtitle,
                            systemImage: "person.2.wave.2"
                        )
                    }
                }
            }

            if auth.canUseAI {
                Section(L10n.SettingsSectionAI) {
                    NavigationLink {
                        AIAssistantSettingsView()
                    } label: {
                        SettingsRowLabel(
                            title: L10n.SettingsSectionAI,
                            subtitle: settings.aiSuggestionsEnabled ? L10n.CommonActive : L10n.SettingsDisabled,
                            systemImage: "sparkles"
                        )
                    }
                }
            }

            Section(L10n.SettingsSectionPrivacy) {
                NavigationLink {
                    PrivacySettingsView()
                } label: {
                    SettingsRowLabel(
                        title: L10n.SettingsSectionPrivacy,
                        subtitle: L10n.SettingsPrivacySubtitle,
                        systemImage: "hand.raised"
                    )
                }

                NavigationLink {
                    DataStorageSettingsView()
                } label: {
                    SettingsRowLabel(
                        title: L10n.SettingsDataStorage,
                        subtitle: L10n.SettingsDataStorageSubtitle,
                        systemImage: "externaldrive"
                    )
                }
            }

            Section(L10n.SettingsSupport) {
                NavigationLink {
                    NetworkDiagnosticsView()
                } label: {
                    SettingsRowLabel(
                        title: "Netzwerk-Diagnose",
                        subtitle: "REST-Anfragen / Circuit Breaker",
                        systemImage: "antenna.radiowaves.left.and.right"
                    )
                }
                NavigationLink {
                    ModuleSettingsHubView()
                } label: {
                    SettingsRowLabel(
                        title: L10n.ModuleSettingsTitle,
                        subtitle: L10n.ModuleSettingsIntroBody,
                        systemImage: "slider.horizontal.3"
                    )
                }
                NavigationLink {
                    SupportSettingsView()
                } label: {
                    SettingsRowLabel(
                        title: L10n.SettingsSupport,
                        subtitle: L10n.SettingsSupportSubtitle,
                        systemImage: "lifepreserver"
                    )
                }
            }

            Section(L10n.SettingsSectionAbout) {
                NavigationLink {
                    AboutSettingsView()
                } label: {
                    SettingsRowLabel(
                        title: L10n.SettingsSectionAbout,
                        subtitle: PAXAppInfo.fullVersion,
                        systemImage: "info.circle"
                    )
                }
            }

            if canManageUsersSection {
                Section(L10n.AccountTeam) {
                    NavigationLink {
                        StaffManagementView()
                    } label: {
                        Label { Text(L10n.AccountTeam) } icon: { PAXIcon("person.3") }
                    }
                }
            }

            if !canManageSettings {
                Section {
                    Text(L10n.SettingsNoPermission)
                        .font(.footnote)
                        .foregroundStyle(PAXTheme.textSecondary)
                }
            }
        }
        .listStyle(.insetGrouped)
        .scrollContentBackground(.hidden)
        .paxScreenBackground()
        .navigationTitle(L10n.SettingsTitle)
        .navigationBarTitleDisplayMode(.large)
    }

    private var canManageUsersSection: Bool { auth.canManageUsers }

    private var profileHeaderSection: some View {
        Section {
            HStack(spacing: 16) {
                ProfileAvatarView(size: 64)
                VStack(alignment: .leading, spacing: 4) {
                    Text(auth.profile?.displayName ?? L10n.CommonAdministrator)
                        .font(PAXTypography.subsection)
                        .foregroundStyle(PAXTheme.textPrimary)
                    if auth.profile?.isSuperAdmin == true {
                        Text(L10n.RoleExecutiveDirector)
                            .font(PAXTypography.meta.weight(.semibold))
                            .foregroundStyle(PAXTheme.accent)
                    }
                }
            }
            .padding(.vertical, 8)
            .accessibilityElement(children: .combine)
        }
    }
}

struct SettingsRowLabel: View {
    let title: String
    let subtitle: String
    let systemImage: String

    var body: some View {
        HStack(spacing: 14) {
            PAXSettingsGlyph(systemImage: systemImage, tint: PAXTheme.accent)
            VStack(alignment: .leading, spacing: 2) {
                Text(title)
                    .font(PAXTypography.rowTitle)
                    .foregroundStyle(PAXTheme.textPrimary)
                Text(subtitle)
                    .font(PAXTypography.meta)
                    .foregroundStyle(PAXTheme.textSecondary)
            }
            Spacer(minLength: 0)
        }
        .padding(.vertical, 6)
        .frame(minHeight: 56)
    }
}

// Legacy entry point — redirects to the new root layout.
struct SettingsView: View {
    var body: some View {
        SettingsRootView()
    }
}
