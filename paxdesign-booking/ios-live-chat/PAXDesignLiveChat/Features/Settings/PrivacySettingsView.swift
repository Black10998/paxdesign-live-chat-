import SwiftUI

struct PrivacySettingsView: View {
    @EnvironmentObject private var auth: AuthStore

    private var canAccessSecurity: Bool { auth.canAccessSecurity }

    var body: some View {
        List {
            Section {
                NavigationLink {
                    PrivacyPolicyView()
                } label: {
                    Label { Text(L10n.AccountPrivacy) } icon: { PAXIcon("hand.raised") }
                }
                NavigationLink {
                    TermsOfServiceView()
                } label: {
                    Label { Text(L10n.AccountTerms) } icon: { PAXIcon("doc.text") }
                }
                NavigationLink {
                    DataHandlingView()
                } label: {
                    Label { Text(L10n.AccountDataHandling) } icon: { PAXIcon("externaldrive") }
                }
            }

            Section(L10n.AccountLegal) {
                Link(destination: PAXLegalLinks.privacyPolicy) {
                    Label { Text(L10n.AccountPrivacyWeb) } icon: { PAXIcon("safari") }
                }
                Link(destination: PAXLegalLinks.impressum) {
                    Label { Text(L10n.AccountImprintWeb) } icon: { PAXIcon("safari") }
                }
                if canAccessSecurity {
                    NavigationLink {
                        SecurityView()
                    } label: {
                        Label { Text(L10n.LegalSecurity) } icon: { PAXIcon("lock.shield") }
                    }
                }
            }
        }
        .listStyle(.insetGrouped)
        .scrollContentBackground(.hidden)
        .paxScreenBackground()
        .navigationTitle(L10n.SettingsSectionPrivacy)
        .navigationBarTitleDisplayMode(.inline)
    }
}
