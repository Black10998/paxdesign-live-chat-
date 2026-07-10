import SwiftUI
import UIKit

struct LiveChatSettingsView: View {
    @EnvironmentObject private var settings: AppSettingsStore

    var body: some View {
        List {
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
        .listStyle(.insetGrouped)
        .scrollContentBackground(.hidden)
        .paxScreenBackground()
        .navigationTitle(L10n.SettingsSectionLiveChat)
        .navigationBarTitleDisplayMode(.inline)
    }
}
