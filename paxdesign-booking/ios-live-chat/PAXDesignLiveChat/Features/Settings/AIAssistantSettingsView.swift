import SwiftUI

struct AIAssistantSettingsView: View {
    @EnvironmentObject private var settings: AppSettingsStore

    var body: some View {
        List {
            Section {
                Toggle(L10n.SettingsAISuggestions, isOn: $settings.aiSuggestionsEnabled)
            } header: {
                Text(L10n.SettingsSectionAI)
            } footer: {
                Text(L10n.SettingsAIFooter)
            }

            Section {
                Label { Text(L10n.SettingsAIInfo) } icon: { PAXIcon("sparkles") }
                    .font(.subheadline)
                    .foregroundStyle(PAXTheme.textSecondary)
            }
        }
        .listStyle(.insetGrouped)
        .scrollContentBackground(.hidden)
        .paxScreenBackground()
        .navigationTitle(L10n.SettingsSectionAI)
        .navigationBarTitleDisplayMode(.inline)
    }
}
