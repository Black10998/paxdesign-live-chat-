import SwiftUI

struct AccountHubView: View {
    @EnvironmentObject private var auth: AuthStore

    private var canManageUsers: Bool { auth.canManageUsers }
    private var canManageSettings: Bool { auth.canManageSettings }
    private var canAccessSecurity: Bool { auth.canAccessSecurity }
    private var websiteURL: URL {
        if let url = URL(string: auth.siteURLString), !auth.siteURLString.isEmpty { return url }
        return PAXLegalLinks.support
    }

    var body: some View {
        List {
            Section {
                HStack(spacing: 14) {
                    ProfileAvatarView(size: 56)
                    VStack(alignment: .leading, spacing: 4) {
                        Text(auth.profile?.name ?? "Administrator")
                            .font(.headline)
                        Text(auth.profile?.displayEmail ?? PrivacyMask.email(auth.username, revealFull: false))
                            .font(.subheadline)
                            .foregroundStyle(PAXTheme.textSecondary)
                        if auth.profile?.isSuperAdmin == true {
                            Text("Hauptadministrator")
                                .font(.caption2.weight(.semibold))
                                .foregroundStyle(PAXTheme.accent)
                        }
                    }
                }
                .padding(.vertical, 4)
            }

            Section("Website") {
                Link(destination: websiteURL) {
                    HStack {
                        Label("Offizielle Website", systemImage: "globe")
                        Spacer()
                        Text(websiteURL.host ?? "paxdesign.at")
                            .font(.caption)
                            .foregroundStyle(PAXTheme.textTertiary)
                    }
                }
            }

            Section("App") {
                if canManageUsers {
                    NavigationLink {
                        StaffManagementView()
                    } label: {
                        Label("Team & Berechtigungen", systemImage: "person.3")
                    }
                }
                if canManageSettings {
                    NavigationLink {
                        SettingsView()
                    } label: {
                        Label("Einstellungen", systemImage: "gearshape")
                    }
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

            Section {
                Button("Abmelden", role: .destructive) {
                    Task {
                        await PushService.shared.unregisterTokenFromBackend(auth: auth)
                        auth.logout()
                    }
                }
            }

            Section("Rechtliches") {
                Link(destination: PAXLegalLinks.privacyPolicy) {
                    Label("Datenschutz (Web)", systemImage: "safari")
                }
                Link(destination: PAXLegalLinks.impressum) {
                    Label("Impressum (Web)", systemImage: "safari")
                }
                if canAccessSecurity {
                    NavigationLink {
                        SecurityView()
                    } label: {
                        Label("Sicherheit", systemImage: "lock.shield")
                    }
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
