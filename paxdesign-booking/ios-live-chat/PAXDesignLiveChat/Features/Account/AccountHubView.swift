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
                        Text(auth.profile?.name ?? L10n.CommonAdministrator)
                            .font(.headline)
                        Text(auth.profile?.displayEmail ?? PrivacyMask.email(auth.username, revealFull: false))
                            .font(.subheadline)
                            .foregroundStyle(PAXTheme.textSecondary)
                        if auth.profile?.isSuperAdmin == true {
                            Text(L10n.AccountSuperAdmin)
                                .font(.caption2.weight(.semibold))
                                .foregroundStyle(PAXTheme.accent)
                        }
                    }
                }
                .padding(.vertical, 4)
            }

            Section(L10n.CommonWebsite) {
                Link(destination: websiteURL) {
                    HStack {
                        Label(L10n.AccountOfficialWebsite, systemImage: "globe")
                        Spacer()
                        Text(websiteURL.host ?? "paxdesign.at")
                            .font(.caption)
                            .foregroundStyle(PAXTheme.textTertiary)
                    }
                }
            }

            Section(L10n.SettingsAppSection) {
                NavigationLink {
                    AppLockSettingsView()
                } label: {
                    Label(L10n.SettingsAppLock, systemImage: "lock.shield")
                }
                if canManageUsers {
                    NavigationLink {
                        StaffManagementView()
                    } label: {
                        Label(L10n.AccountTeam, systemImage: "person.3")
                    }
                }
                NavigationLink {
                    SettingsRootView()
                } label: {
                    Label(L10n.AccountSettings, systemImage: "gearshape")
                }
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

            Section {
                Button(L10n.SettingsSignOut, role: .destructive) {
                    Task {
                        await PushService.shared.unregisterTokenFromBackend(auth: auth)
                        auth.logout()
                    }
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
        }
        .listStyle(.insetGrouped)
        .scrollContentBackground(.hidden)
        .background(PAXBackground())
        .navigationTitle(L10n.TabAccount)
    }
}
