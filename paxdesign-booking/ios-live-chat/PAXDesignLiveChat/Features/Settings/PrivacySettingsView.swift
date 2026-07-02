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
                    Label(L10n.AccountPrivacy, systemImage: "hand.raised")
                }
                NavigationLink {
                    TermsOfServiceView()
                } label: {
                    Label(L10n.AccountTerms, systemImage: "doc.text")
                }
                NavigationLink {
                    DataHandlingView()
                } label: {
                    Label(L10n.AccountDataHandling, systemImage: "externaldrive")
                }
            }

            Section(L10n.AccountLegal) {
                Link(destination: PAXLegalLinks.privacyPolicy) {
                    Label(L10n.AccountPrivacyWeb, systemImage: "safari")
                }
                Link(destination: PAXLegalLinks.impressum) {
                    Label(L10n.AccountImprintWeb, systemImage: "safari")
                }
                if canAccessSecurity {
                    NavigationLink {
                        SecurityView()
                    } label: {
                        Label(L10n.LegalSecurity, systemImage: "lock.shield")
                    }
                }
            }
        }
        .listStyle(.insetGrouped)
        .scrollContentBackground(.hidden)
        .background(PAXBackground())
        .navigationTitle(L10n.SettingsSectionPrivacy)
        .navigationBarTitleDisplayMode(.inline)
    }
}
