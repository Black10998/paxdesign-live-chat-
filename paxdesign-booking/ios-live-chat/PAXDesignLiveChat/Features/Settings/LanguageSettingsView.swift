import SwiftUI

struct LanguageSettingsView: View {
    @EnvironmentObject private var settings: AppSettingsStore
    @EnvironmentObject private var auth: AuthStore
    @State private var spokenSelection: Set<String> = []
    @State private var isSaving = false
    @State private var saveError: String?
    @State private var didLoadSpoken = false

    private let spokenOptions: [(code: String, title: String)] = [
        ("de", L10n.LanguageGerman),
        ("en", L10n.LanguageEnglish),
        ("ar", L10n.LanguageArabic),
    ]

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

            Section {
                ForEach(spokenOptions, id: \.code) { option in
                    Toggle(isOn: spokenBinding(option.code)) {
                        Text(option.title)
                    }
                }
            } header: {
                Text(L10n.LanguageSpokenHeader)
            } footer: {
                Text(L10n.LanguageSpokenFooter)
            }
        }
        .listStyle(.insetGrouped)
        .scrollContentBackground(.hidden)
        .paxScreenBackground()
        .navigationTitle(L10n.SettingsSectionLanguage)
        .navigationBarTitleDisplayMode(.inline)
        .onAppear {
            spokenSelection = Set(auth.profile?.spokenLanguages ?? ["de", "en"])
            didLoadSpoken = true
        }
        .onChange(of: spokenSelection) { _ in
            guard didLoadSpoken else { return }
            Task { await saveSpokenLanguages() }
        }
        .overlay(alignment: .bottom) {
            if let saveError {
                Text(saveError)
                    .font(.caption)
                    .foregroundStyle(.red)
                    .padding(.bottom, 8)
            }
        }
        .disabled(isSaving)
    }

    private func spokenBinding(_ code: String) -> Binding<Bool> {
        Binding(
            get: { spokenSelection.contains(code) },
            set: { enabled in
                if enabled {
                    spokenSelection.insert(code)
                } else if spokenSelection.count > 1 {
                    spokenSelection.remove(code)
                }
            }
        )
    }

    private func saveSpokenLanguages() async {
        guard let api = auth.api else { return }
        isSaving = true
        saveError = nil
        defer { isSaving = false }
        let sorted = spokenOptions.map(\.code).filter { spokenSelection.contains($0) }
        do {
            let profile = try await api.updateSpokenLanguages(sorted)
            auth.applyProfileUpdate(profile)
        } catch {
            saveError = error.localizedDescription
        }
    }
}
