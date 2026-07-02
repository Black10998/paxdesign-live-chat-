import SwiftUI
import UIKit

struct LiveChatSettingsView: View {
    @StateObject private var settings = AppSettingsStore.shared

    var body: some View {
        List {
            Section {
                Button(L10n.SettingsResetPrivacyBanner) {
                    settings.privacyBannerDismissed = false
                    PAXHaptics.success()
                }

                Button(L10n.SettingsClearReadState, role: .destructive) {
                    settings.readSessionIds = []
                    PAXHaptics.light()
                }
            } header: {
                Text(L10n.SettingsLiveChatBehavior)
            } footer: {
                Text(L10n.SettingsLiveChatFooter)
            }
        }
        .listStyle(.insetGrouped)
        .scrollContentBackground(.hidden)
        .background(PAXBackground())
        .navigationTitle(L10n.SettingsSectionLiveChat)
        .navigationBarTitleDisplayMode(.inline)
    }
}
