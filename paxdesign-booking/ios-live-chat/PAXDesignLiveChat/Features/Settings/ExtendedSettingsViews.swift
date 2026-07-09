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
    @StateObject private var settings = AppSettingsStore.shared
    @State private var showResetConfirm = false

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
