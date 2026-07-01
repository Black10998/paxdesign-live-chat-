import SwiftUI

struct AccountHubView: View {
    @EnvironmentObject private var auth: AuthStore

    var body: some View {
        List {
            Section {
                HStack(spacing: 14) {
                    ProfileAvatarView(size: 56)
                    VStack(alignment: .leading, spacing: 4) {
                        Text(auth.profile?.name ?? "Administrator")
                            .font(.headline)
                        Text(auth.profile?.email ?? auth.username)
                            .font(.subheadline)
                            .foregroundStyle(PAXTheme.textSecondary)
                    }
                }
                .padding(.vertical, 4)
            }

            Section("App") {
                NavigationLink {
                    SettingsView()
                } label: {
                    Label("Einstellungen", systemImage: "gearshape")
                }
                NavigationLink {
                    HelpView()
                } label: {
                    Label("Hilfe", systemImage: "questionmark.circle")
                }
                NavigationLink {
                    AboutView()
                } label: {
                    Label("Über die App", systemImage: "info.circle")
                }
            }

            Section("Rechtliches") {
                NavigationLink {
                    SecurityView()
                } label: {
                    Label("Sicherheit", systemImage: "lock.shield")
                }
                NavigationLink {
                    PrivacyPolicyView()
                } label: {
                    Label("Datenschutzerklärung", systemImage: "hand.raised")
                }
                NavigationLink {
                    TermsOfServiceView()
                } label: {
                    Label("Nutzungsbedingungen", systemImage: "doc.text")
                }
                NavigationLink {
                    DataHandlingView()
                } label: {
                    Label("Datenverarbeitung", systemImage: "externaldrive")
                }
            }
        }
        .listStyle(.insetGrouped)
        .scrollContentBackground(.hidden)
        .background(PAXBackground())
        .navigationTitle("Konto")
    }
}
