import SwiftUI

struct SettingsRootView: View {
    @EnvironmentObject private var auth: AuthStore
    @StateObject private var settings = AppSettingsStore.shared

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
                        Label(L10n.AccountTeam, systemImage: "person.3")
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
        .background(PAXBackground())
        .navigationTitle(L10n.SettingsTitle)
        .navigationBarTitleDisplayMode(.large)
    }

    private var canManageUsersSection: Bool { auth.canManageUsers }

    private var profileHeaderSection: some View {
        Section {
            HStack(spacing: 16) {
                ProfileAvatarView(size: 64)
                VStack(alignment: .leading, spacing: 4) {
                    Text(auth.profile?.name ?? L10n.CommonAdministrator)
                        .font(.title3.weight(.semibold))
                    Text(auth.profile?.displayEmail ?? PrivacyMask.email(auth.username, revealFull: false))
                        .font(.subheadline)
                        .foregroundStyle(PAXTheme.textSecondary)
                    if auth.profile?.isSuperAdmin == true {
                        Text(L10n.AccountSuperAdmin)
                            .font(.caption2.weight(.semibold))
                            .foregroundStyle(PAXTheme.accent)
                    }
                }
            }
            .padding(.vertical, 4)
            .accessibilityElement(children: .combine)
        }
    }
}

struct SettingsRowLabel: View {
    let title: String
    let subtitle: String
    let systemImage: String

    var body: some View {
        HStack(spacing: 12) {
            Image(systemName: systemImage)
                .font(.body.weight(.medium))
                .foregroundStyle(PAXTheme.accent)
                .frame(width: 28, height: 28)
                .accessibilityHidden(true)

            VStack(alignment: .leading, spacing: 2) {
                Text(title)
                    .foregroundStyle(PAXTheme.textPrimary)
                Text(subtitle)
                    .font(.caption)
                    .foregroundStyle(PAXTheme.textSecondary)
            }
        }
        .padding(.vertical, 2)
    }
}

// Legacy entry point — redirects to the new root layout.
struct SettingsView: View {
    var body: some View {
        SettingsRootView()
    }
}
