import SwiftUI

struct LanguageSettingsView: View {
    @StateObject private var settings = AppSettingsStore.shared

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
        .background(PAXBackground())
        .navigationTitle(L10n.SettingsSectionLanguage)
        .navigationBarTitleDisplayMode(.inline)
    }
}
