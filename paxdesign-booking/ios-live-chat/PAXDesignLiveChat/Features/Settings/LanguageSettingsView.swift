import SwiftUI

struct LanguageSettingsView: View {
    @EnvironmentObject private var settings: AppSettingsStore

    var body: some View {
        List {
            Section {
                Picker(L10n.SettingsSectionLanguage, selection: $settings.languageMode) {
                    ForEach(AppSettingsStore.LanguageMode.allCases) { mode in
                        Text(mode.title).tag(mode)
                    }
                }
                .pickerStyle(.inline)
            } footer: {
                Text(L10n.LanguageFooter)
            }
        }
        .listStyle(.insetGrouped)
        .scrollContentBackground(.hidden)
        .paxScreenBackground()
        .navigationTitle(L10n.SettingsSectionLanguage)
        .navigationBarTitleDisplayMode(.inline)
    }
}
