import SwiftUI

struct AIAssistantSettingsView: View {
    @StateObject private var settings = AppSettingsStore.shared

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
                Label(L10n.SettingsAIInfo, systemImage: "sparkles")
                    .font(.subheadline)
                    .foregroundStyle(PAXTheme.textSecondary)
            }
        }
        .listStyle(.insetGrouped)
        .scrollContentBackground(.hidden)
        .background(PAXBackground())
        .navigationTitle(L10n.SettingsSectionAI)
        .navigationBarTitleDisplayMode(.inline)
    }
}
