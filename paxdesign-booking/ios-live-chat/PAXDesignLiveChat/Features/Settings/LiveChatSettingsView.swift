import SwiftUI
import UIKit

struct LiveChatSettingsView: View {
    @EnvironmentObject private var settings: AppSettingsStore
    @EnvironmentObject private var auth: AuthStore

    private var canManageSettings: Bool { auth.canManageSettings }
    private var canReplyChats: Bool { auth.canReplyChats }

    var body: some View {
        List {
            if canManageSettings {
                Section(L10n.SettingsQuickLinksTitle) {
                    NavigationLink {
                        QuickLinksSettingsView()
                    } label: {
                        SettingsRowLabel(
                            title: L10n.SettingsQuickLinksTitle,
                            subtitle: L10n.SettingsQuickLinksSubtitle,
                            systemImage: "link.badge.plus"
                        )
                    }
                }
            }

            Section {
                Toggle(L10n.SettingsCompactList, isOn: $settings.compactListMode)
                Toggle(L10n.SettingsShowTimestamps, isOn: $settings.showListTimestamps)
            } header: {
                Text(L10n.SettingsChatDisplay)
            } footer: {
                Text(L10n.SettingsChatDisplaySubtitle)
            }

            if canReplyChats {
                Section {
                    Button(L10n.SettingsResetPrivacyBanner) {
                        settings.privacyBannerDismissed = false
                        PAXHaptics.success()
                    }

                    Button(L10n.SettingsClearReadState) {
                        PAXDelete.confirm(
                            message: L10n.SettingsLiveChatFooter,
                            confirmTitle: L10n.SettingsClearReadState
                        ) {
                            settings.readSessionIds = []
                            PAXHaptics.light()
                        }
                    }
                } header: {
                    Text(L10n.SettingsLiveChatBehavior)
                } footer: {
                    Text(L10n.SettingsLiveChatFooter)
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
        .navigationTitle(L10n.SettingsSectionLiveChat)
        .navigationBarTitleDisplayMode(.inline)
    }
}
