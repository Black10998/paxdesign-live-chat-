import SwiftUI

struct AccountHubView: View {
    @EnvironmentObject private var auth: AuthStore

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

            Section {
                NavigationLink {
                    SettingsRootView()
                } label: {
                    Label(L10n.AccountSettings, systemImage: "gearshape")
                }
            }
        }
        .listStyle(.insetGrouped)
        .scrollContentBackground(.hidden)
        .background(PAXBackground())
        .navigationTitle(L10n.TabAccount)
    }
}
