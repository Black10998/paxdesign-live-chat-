import SwiftUI

struct ChatDisplaySettingsView: View {
    @StateObject private var settings = AppSettingsStore.shared

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
        .background(PAXBackground())
        .navigationTitle(L10n.SettingsChatDisplay)
        .navigationBarTitleDisplayMode(.inline)
    }
}

struct DataStorageSettingsView: View {
    @EnvironmentObject private var auth: AuthStore
    @StateObject private var settings = AppSettingsStore.shared
    @State private var showResetConfirm = false
    @State private var showOnboardingResetConfirm = false
    @State private var onboardingResetMessage: String?

    var body: some View {
        List {
            Section {
                LabeledContent(L10n.SettingsResetRead, value: "\(settings.readSessionIds.count)")
                Button(L10n.SettingsResetRead, role: .destructive) {
                    showResetConfirm = true
                }
            } footer: {
                Text(L10n.SettingsResetReadFooter)
            }

            Section(L10n.SettingsProfile) {
                if settings.profileImageData != nil {
                    Button(L10n.SettingsResetPhoto, role: .destructive) {
                        settings.profileImageData = nil
                        PAXHaptics.light()
                    }
                } else {
                    Text(L10n.SettingsResetPhoto)
                        .foregroundStyle(PAXTheme.textTertiary)
                }
            }

            if auth.canManageUsers {
                Section("Onboarding") {
                    Button("Einführung zurücksetzen", role: .destructive) {
                        showOnboardingResetConfirm = true
                    }
                    if let onboardingResetMessage {
                        Text(onboardingResetMessage)
                            .font(.caption)
                            .foregroundStyle(PAXTheme.textSecondary)
                    }
                } footer: {
                    Text("Setzt die Willkommens-Tour für den ausgewählten Mitarbeiter zurück.")
                }
            }
        }
        .listStyle(.insetGrouped)
        .scrollContentBackground(.hidden)
        .background(PAXBackground())
        .navigationTitle(L10n.SettingsDataStorage)
        .navigationBarTitleDisplayMode(.inline)
        .confirmationDialog(L10n.SettingsResetRead, isPresented: $showResetConfirm) {
            Button(L10n.SettingsResetRead, role: .destructive) {
                settings.readSessionIds.removeAll()
                PAXHaptics.success()
            }
        }
        .confirmationDialog("Onboarding zurücksetzen?", isPresented: $showOnboardingResetConfirm) {
            Button("Zurücksetzen", role: .destructive) {
                Task { await resetOnboardingForCurrentUser() }
            }
        } message: {
            Text("Die Einführungstour wird beim nächsten Start erneut angezeigt.")
        }
    }

    private func resetOnboardingForCurrentUser() async {
        guard let userId = auth.profile?.userId, let api = auth.api else { return }
        do {
            try await api.resetOnboarding(for: userId)
            settings.onboardingCompleted = false
            onboardingResetMessage = "Onboarding wurde zurückgesetzt."
            PAXHaptics.success()
        } catch {
            onboardingResetMessage = error.localizedDescription
        }
    }
}

struct SupportSettingsView: View {
    var body: some View {
        List {
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
        .background(PAXBackground())
        .navigationTitle(L10n.SettingsSupport)
        .navigationBarTitleDisplayMode(.inline)
    }
}
