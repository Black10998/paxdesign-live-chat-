import SwiftUI

struct LegalDocumentView: View {
    let title: String
    let sections: [(String, String)]
    var webLinks: [LegalWebLink] = []

    struct LegalWebLink: Identifiable {
        let id = UUID()
        let title: String
        let url: URL
    }

    var body: some View {
        ScrollView {
            VStack(alignment: .leading, spacing: 20) {
                if !webLinks.isEmpty {
                    VStack(alignment: .leading, spacing: 10) {
                        Text(L10n.CommonOnline)
                            .font(.headline)
                        ForEach(webLinks) { link in
                            Link(destination: link.url) {
                                HStack {
                                    Text(link.title)
                                        .font(.subheadline.weight(.medium))
                                    Spacer()
                                    Image(systemName: "arrow.up.right")
                                        .font(.caption)
                                        .foregroundStyle(PAXTheme.textTertiary)
                                }
                                .padding(12)
                                .paxGlassCardStyle(cornerRadius: 12, fillOpacity: 0.8, borderOpacity: 0.44, shadowOpacity: 0.1)
                            }
                        }
                    }
                }

                ForEach(Array(sections.enumerated()), id: \.offset) { _, section in
                    VStack(alignment: .leading, spacing: 8) {
                        Text(section.0)
                            .font(.headline)
                        Text(section.1)
                            .font(.subheadline)
                            .foregroundStyle(PAXTheme.textSecondary)
                            .fixedSize(horizontal: false, vertical: true)
                    }
                    .padding(14)
                    .paxGlassCardStyle(cornerRadius: 14, fillOpacity: 0.8, borderOpacity: 0.42, shadowOpacity: 0.12)
                }
            }
            .padding(20)
        }
        .paxScreenBackground()
        .navigationTitle(title)
        .navigationBarTitleDisplayMode(.inline)
    }
}

struct SecurityView: View {
    var body: some View {
        List {
            Section {
                NavigationLink {
                    AppLockSettingsView()
                } label: {
                    Label(L10n.LegalAppLockLabel, systemImage: "lock.shield.fill")
                }
            } footer: {
                Text(L10n.LegalAppLockFooter)
            }

            Section(L10n.LegalSecuritySection) {
                Text(L10n.LegalSecurityTransport)
                    .font(.footnote)
                    .foregroundStyle(PAXTheme.textSecondary)
                Text(L10n.LegalSecurityCredentials)
                    .font(.footnote)
                    .foregroundStyle(PAXTheme.textSecondary)
                Text(L10n.LegalSecurityNoTracking)
                    .font(.footnote)
                    .foregroundStyle(PAXTheme.textSecondary)
            }
        }
        .navigationTitle(L10n.LegalSecurity)
        .navigationBarTitleDisplayMode(.inline)
        .scrollContentBackground(.hidden)
        .paxScreenBackground()
    }
}

struct PrivacyPolicyView: View {
    var body: some View {
        LegalDocumentView(
            title: L10n.LegalPrivacy,
            sections: [
                (L10n.LegalPrivacyControllerTitle, L10n.LegalPrivacyControllerBody),
                (L10n.LegalPrivacyPurposeTitle, L10n.LegalPrivacyPurposeBody),
                (L10n.LegalPrivacyDataTitle, L10n.LegalPrivacyDataBody),
                (L10n.LegalPrivacyStorageTitle, L10n.LegalPrivacyStorageBody),
                (L10n.LegalPrivacyTransmissionTitle, L10n.LegalPrivacyTransmissionBody),
                (L10n.LegalPrivacyLegalBasisTitle, L10n.LegalPrivacyLegalBasisBody),
                (L10n.LegalPrivacyRightsTitle, L10n.LegalPrivacyRightsBody),
                (L10n.LegalPrivacyRetentionTitle, L10n.LegalPrivacyRetentionBody),
            ],
            webLinks: [
                .init(title: L10n.LegalPrivacyLinkTitle, url: PAXLegalLinks.privacyPolicy)
            ]
        )
    }
}

struct TermsOfServiceView: View {
    var body: some View {
        LegalDocumentView(
            title: L10n.LegalTerms,
            sections: [
                (L10n.LegalTermsScopeTitle, L10n.LegalTermsScopeBody),
                (L10n.LegalTermsAccessTitle, L10n.LegalTermsAccessBody),
                (L10n.LegalTermsDutiesTitle, L10n.LegalTermsDutiesBody),
                (L10n.LegalTermsAvailabilityTitle, L10n.LegalTermsAvailabilityBody),
                (L10n.LegalTermsLiabilityTitle, L10n.LegalTermsLiabilityBody),
            ],
            webLinks: [
                .init(title: L10n.LegalTermsLinkTitle, url: PAXLegalLinks.impressum)
            ]
        )
    }
}

struct DataHandlingView: View {
    var body: some View {
        LegalDocumentView(title: L10n.LegalDataHandlingTitle, sections: [
            (L10n.LegalDataLocalTitle, L10n.LegalDataLocalBody),
            (L10n.LegalDataNetworkTitle, L10n.LegalDataNetworkBody),
            (L10n.LegalDataPushTitle, L10n.LegalDataPushBody),
            (L10n.LegalDataDeletionTitle, L10n.LegalDataDeletionBody),
            (L10n.LegalDataAppStoreTitle, L10n.LegalDataAppStoreBody),
        ])
    }
}

struct AboutView: View {
    var body: some View {
        ScrollView {
            VStack(spacing: 0) {
                aboutHero
                    .padding(.top, 32)
                    .padding(.bottom, 28)

                VStack(spacing: 0) {
                    aboutInfoCard
                    featureHighlights
                        .padding(.top, 20)
                }
                .padding(.horizontal, 20)

                LegalFooterLinks()
                    .padding(.top, 28)
                    .padding(.bottom, 32)
            }
        }
        .paxScreenBackground()
        .navigationTitle(L10n.AccountAbout)
        .navigationBarTitleDisplayMode(.inline)
    }

    private var aboutHero: some View {
        VStack(spacing: 18) {
            PAXAppMark.image(size: 88)
                .shadow(color: PAXBrand.accent.opacity(0.2), radius: 16, y: 6)

            VStack(spacing: 8) {
                Text(L10n.AppName)
                    .font(.title2.weight(.bold))

                Text(L10n.AboutTagline)
                    .font(.subheadline)
                    .foregroundStyle(PAXTheme.textSecondary)
                    .multilineTextAlignment(.center)
                    .padding(.horizontal, 24)
            }

            HStack(spacing: 8) {
                aboutPill(L10n.AboutBuiltWith, icon: "swift")
                aboutPill(PAXAppInfo.fullVersion, icon: "number")
            }
        }
    }

    private func aboutPill(_ text: String, icon: String) -> some View {
        HStack(spacing: 5) {
            Image(systemName: icon)
                .font(.caption2.weight(.semibold))
            Text(text)
                .font(.caption.weight(.semibold))
        }
        .foregroundStyle(PAXBrand.accent)
        .padding(.horizontal, 12)
        .padding(.vertical, 6)
        .background(Capsule().fill(PAXBrand.accent.opacity(0.12)))
    }

    private var aboutInfoCard: some View {
        VStack(spacing: 0) {
            aboutRow(L10n.AboutVersionLabel, PAXAppInfo.fullVersion, showDivider: true)
            aboutRow(L10n.AboutWebsiteLabel, "https://paxdesign.at", showDivider: true)
            aboutRow(L10n.AboutManufacturerLabel, "PAXdesign / PrimoJob GmbH", showDivider: true)
            aboutRow(L10n.AboutSecurityLabel, "HTTPS/TLS, iOS-Schlüsselbund", showDivider: false)
        }
        .background(
            RoundedRectangle(cornerRadius: 16, style: .continuous)
                .fill(.ultraThinMaterial)
                .overlay(
                    RoundedRectangle(cornerRadius: 16, style: .continuous)
                        .fill(PAXTheme.surface.opacity(0.82))
                )
                .overlay(
                    RoundedRectangle(cornerRadius: 16, style: .continuous)
                        .stroke(PAXTheme.border.opacity(0.44), lineWidth: 1)
                )
        )
        .clipShape(RoundedRectangle(cornerRadius: 16, style: .continuous))
    }

    private var featureHighlights: some View {
        VStack(alignment: .leading, spacing: 14) {
            featureRow(icon: "bell.badge", title: L10n.AboutPushTitle, subtitle: L10n.AboutPushSubtitle)
            featureRow(icon: "person.wave.2", title: L10n.AboutLiveAgentTitle, subtitle: L10n.AboutLiveAgentSubtitle)
            featureRow(icon: "sparkles", title: L10n.AboutAiTitle, subtitle: L10n.AboutAiSubtitle)
            featureRow(icon: "lock.shield", title: L10n.AboutEnterpriseTitle, subtitle: L10n.AboutEnterpriseSubtitle)
        }
        .padding(18)
        .paxGlassCardStyle(cornerRadius: 16, fillOpacity: 0.78, borderOpacity: 0.42, shadowOpacity: 0.12)
    }

    private func featureRow(icon: String, title: String, subtitle: String) -> some View {
        HStack(spacing: 14) {
            Image(systemName: icon)
                .font(.body.weight(.medium))
                .foregroundStyle(PAXBrand.accent)
                .frame(width: 32, height: 32)
                .background(Circle().fill(PAXBrand.accent.opacity(0.12)))

            VStack(alignment: .leading, spacing: 2) {
                Text(title)
                    .font(.subheadline.weight(.semibold))
                Text(subtitle)
                    .font(.caption)
                    .foregroundStyle(PAXTheme.textSecondary)
            }
        }
    }

    private func aboutRow(_ title: String, _ value: String, showDivider: Bool) -> some View {
        VStack(spacing: 0) {
            HStack(alignment: .center) {
                Text(title)
                    .font(.subheadline.weight(.medium))
                Spacer()
                Text(value)
                    .font(.subheadline)
                    .foregroundStyle(PAXTheme.textSecondary)
                    .multilineTextAlignment(.trailing)
            }
            .padding(.horizontal, 16)
            .padding(.vertical, 14)

            if showDivider {
                Divider()
                    .padding(.leading, 16)
            }
        }
    }
}

struct HelpView: View {
    var body: some View {
        LegalDocumentView(title: L10n.LegalHelpTitle, sections: [
            (L10n.HelpAboutTitle, L10n.HelpAboutBody),
            (L10n.HelpLoginTitle, L10n.HelpLoginBody),
            (L10n.HelpLiveTitle, L10n.HelpLiveBody),
            (L10n.HelpChatsTitle, L10n.HelpChatsBody),
            (L10n.HelpImagesTitle, L10n.HelpImagesBody),
            (L10n.HelpAiTitle, L10n.HelpAiBody),
            (L10n.HelpNotificationsTitle, L10n.HelpNotificationsBody),
            (L10n.HelpTeamTitle, L10n.HelpTeamBody),
            (L10n.HelpPrivacyTitle, L10n.HelpPrivacyBody),
            (L10n.HelpAdminTitle, L10n.HelpAdminBody),
            (L10n.HelpSupportTitle, L10n.HelpSupportBody),
        ])
    }
}
