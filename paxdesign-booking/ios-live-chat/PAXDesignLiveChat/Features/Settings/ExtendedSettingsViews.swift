import SwiftUI

struct ChatDisplaySettingsView: View {
    @EnvironmentObject private var settings: AppSettingsStore

    var body: some View {
        List {
            Section {
                Toggle(L10n.SettingsCompactList, isOn: $settings.compactListMode)
                Toggle(L10n.SettingsShowTimestamps, isOn: $settings.showListTimestamps)
            } footer: {
                Text(L10n.SettingsChatDisplaySubtitle)
            }
        }
        .listStyle(.insetGrouped)
        .scrollContentBackground(.hidden)
        .paxScreenBackground()
        .navigationTitle(L10n.SettingsChatDisplay)
        .navigationBarTitleDisplayMode(.inline)
    }
}

struct DataStorageSettingsView: View {
    @EnvironmentObject private var auth: AuthStore
    @EnvironmentObject private var settings: AppSettingsStore
    @State private var onboardingResetMessage: String?

    var body: some View {
        List {
            Section {
                LabeledContent(L10n.SettingsResetRead, value: "\(settings.readSessionIds.count)")
                Button(L10n.SettingsResetRead) {
                    PAXDelete.confirm(
                        message: L10n.SettingsResetReadFooter,
                        confirmTitle: L10n.SettingsResetRead
                    ) {
                        settings.readSessionIds.removeAll()
                        PAXHaptics.success()
                    }
                }
            } footer: {
                Text(L10n.SettingsResetReadFooter)
            }

            Section(L10n.SettingsProfile) {
                if settings.profileImageData != nil {
                    Button(L10n.SettingsResetPhoto) {
                        PAXDelete.confirm(
                            message: L10n.SettingsResetPhotoMessage,
                            confirmTitle: L10n.SettingsResetPhoto
                        ) {
                            settings.profileImageData = nil
                            PAXHaptics.light()
                        }
                    }
                } else {
                    Text(L10n.SettingsResetPhoto)
                        .foregroundStyle(PAXTheme.textTertiary)
                }
            }

            if auth.canManageUsers {
                Section {
                    Button(L10n.SettingsResetOnboarding) {
                        PAXDelete.confirm(
                            message: L10n.SettingsResetOnboardingMessage,
                            confirmTitle: L10n.SettingsResetOnboardingConfirm
                        ) {
                            Task { await resetOnboardingForCurrentUser() }
                        }
                    }
                    if let onboardingResetMessage {
                        Text(onboardingResetMessage)
                            .font(.caption)
                            .foregroundStyle(PAXTheme.textSecondary)
                    }
                } header: {
                    Text(L10n.SettingsOnboardingSection)
                } footer: {
                    Text(L10n.SettingsOnboardingFooter)
                }
            }
        }
        .listStyle(.insetGrouped)
        .scrollContentBackground(.hidden)
        .paxScreenBackground()
        .navigationTitle(L10n.SettingsDataStorage)
        .navigationBarTitleDisplayMode(.inline)
    }

    private func resetOnboardingForCurrentUser() async {
        guard let userId = auth.profile?.userId, let api = auth.api else { return }
        do {
            try await api.resetOnboarding(for: userId)
            settings.onboardingCompleted = false
            settings.firstLaunchOnboardingCompleted = false
            onboardingResetMessage = L10n.SettingsOnboardingResetDone
            PAXHaptics.success()
        } catch {
            onboardingResetMessage = error.localizedDescription
        }
    }
}

struct SupportSettingsView: View {
    @EnvironmentObject private var settings: AppSettingsStore
    @State private var tourResetMessage: String?

    var body: some View {
        List {
            Section {
                Button(L10n.SettingsRestartTour) {
                    settings.dashboardTourCompleted = false
                    tourResetMessage = L10n.SettingsTourResetDone
                    PAXHaptics.success()
                }
                if let tourResetMessage {
                    Text(tourResetMessage)
                        .font(.caption)
                        .foregroundStyle(PAXTheme.textSecondary)
                }
            } header: {
                Text(L10n.SettingsTourSection)
            } footer: {
                Text(L10n.SettingsTourFooter)
            }

            Section {
                NavigationLink {
                    HelpView()
                } label: {
                    Label(L10n.AccountHelp, systemImage: "questionmark.circle")
                }

                Link(destination: PAXLegalLinks.support) {
                    Label(L10n.SettingsContactSupport, systemImage: "envelope")
                }

                Link(destination: URL(string: "https://paxdesign.at")!) {
                    Label(L10n.AccountOfficialWebsite, systemImage: "globe")
                }
            }

            Section {
                NavigationLink {
                    AboutView()
                } label: {
                    Label(L10n.AccountAbout, systemImage: "info.circle")
                }

                LabeledContent(L10n.CommonVersion, value: PAXAppInfo.fullVersion)
            }
        }
        .listStyle(.insetGrouped)
        .scrollContentBackground(.hidden)
        .paxScreenBackground()
        .navigationTitle(L10n.SettingsSupport)
        .navigationBarTitleDisplayMode(.inline)
    }
}
