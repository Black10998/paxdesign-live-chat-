import SwiftUI

struct AboutSettingsView: View {
    @EnvironmentObject private var auth: AuthStore

    var body: some View {
        List {
            Section(L10n.SettingsAppSection) {
                LabeledContent(L10n.CommonVersion, value: PAXAppInfo.fullVersion)
                LabeledContent(L10n.CommonPlugin, value: auth.profile?.pluginVer ?? "—")
            }

            Section {
                NavigationLink {
                    HelpView()
                } label: {
                    Label(L10n.AccountHelp, systemImage: "questionmark.circle")
                }
                NavigationLink {
                    AboutView()
                } label: {
                    Label(L10n.AccountAbout, systemImage: "info.circle")
                }
            }
        }
        .listStyle(.insetGrouped)
        .scrollContentBackground(.hidden)
        .paxScreenBackground()
        .navigationTitle(L10n.SettingsSectionAbout)
        .navigationBarTitleDisplayMode(.inline)
    }
}
