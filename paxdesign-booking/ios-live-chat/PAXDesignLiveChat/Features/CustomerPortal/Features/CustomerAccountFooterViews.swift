import SwiftUI

// MARK: - Account page footer (website parity, native SwiftUI)

struct CustomerAccountFooterSection: View {
    @Environment(\.marketingTheme) private var theme
    @State private var showGitHubModal = false

    var body: some View {
        VStack(alignment: .leading, spacing: 32) {
            CustomerAccountLegalTerminalView()
            contactSection
        }
        .padding(.horizontal, 20)
        .padding(.vertical, 28)
        .frame(maxWidth: .infinity, alignment: .leading)
        .background(theme.background)
        .sheet(isPresented: $showGitHubModal) {
            CustomerGitHubPrivateSheet()
        }
    }

    private var contactSection: some View {
        VStack(alignment: .leading, spacing: 20) {
            Text(String(localized: "Contact"))
                .font(.title3.weight(.bold))
                .foregroundStyle(theme.textPrimary)

            VStack(alignment: .leading, spacing: 16) {
                footerLabel(String(localized: "E-Mail"))
                Link(destination: PAXLegalLinks.supportEmail) {
                    Text("info@paxdesign.at")
                        .font(.body)
                        .foregroundStyle(theme.textSecondary)
                }

                footerLabel(String(localized: "Contact"))
                Link(destination: PAXLegalLinks.contact) {
                    Text(String(localized: "Visit contact page"))
                        .font(.body)
                        .foregroundStyle(theme.textSecondary)
                }

                footerLabel(String(localized: "GitHub"))
                Button {
                    showGitHubModal = true
                } label: {
                    HStack(spacing: 10) {
                        Image(systemName: "chevron.left.forwardslash.chevron.right")
                            .font(.title2)
                        Image(systemName: "lock.fill")
                            .font(.caption2)
                            .foregroundStyle(PAXBrand.accent)
                        Text(String(localized: "Private"))
                            .font(.subheadline.weight(.semibold))
                    }
                    .foregroundStyle(.white)
                    .padding(.horizontal, 14)
                    .padding(.vertical, 10)
                    .background(Color(red: 0.07, green: 0.09, blue: 0.15))
                    .clipShape(RoundedRectangle(cornerRadius: 10, style: .continuous))
                }
                .buttonStyle(.plain)
                .accessibilityLabel(String(localized: "GitHub Private Repository"))
            }
        }
    }

    private func footerLabel(_ text: String) -> some View {
        Text(text.uppercased())
            .font(.caption2.weight(.semibold))
            .tracking(1)
            .foregroundStyle(theme.textSecondary)
    }
}

// MARK: - Legal terminal card

struct CustomerAccountLegalTerminalView: View {
    private let links: [(slug: String, title: String, url: URL)] = [
        ("impressum", "Impressum", PAXLegalLinks.impressum),
        ("datenschutz", "Datenschutz", PAXLegalLinks.privacyPolicy),
        ("agb", "AGB", PAXLegalLinks.terms),
        ("service-dokumentation", "Service-Doku", PAXLegalLinks.serviceDocumentation),
    ]

    var body: some View {
        VStack(spacing: 0) {
            HStack {
                HStack(spacing: 7) {
                    Circle().fill(Color(red: 1, green: 0.37, blue: 0.34)).frame(width: 12, height: 12)
                    Circle().fill(Color(red: 0.996, green: 0.737, blue: 0.18)).frame(width: 12, height: 12)
                    Circle().fill(Color(red: 0.157, green: 0.784, blue: 0.251)).frame(width: 12, height: 12)
                }
                Spacer()
                Text(String(localized: "Legal"))
                    .font(.system(size: 11, weight: .semibold, design: .monospaced))
                    .foregroundStyle(Color.white.opacity(0.82))
                    .padding(.horizontal, 10)
                    .padding(.vertical, 4)
                    .background(PAXBrand.accent.opacity(0.12))
                    .clipShape(RoundedRectangle(cornerRadius: 6, style: .continuous))
                    .overlay(
                        RoundedRectangle(cornerRadius: 6, style: .continuous)
                            .stroke(PAXBrand.accent.opacity(0.22), lineWidth: 1)
                    )
            }
            .padding(.horizontal, 20)
            .padding(.vertical, 12)
            .background(Color.black.opacity(0.35))

            VStack(spacing: 14) {
                ForEach(links, id: \.slug) { link in
                    Link(destination: link.url) {
                        Text(link.title)
                            .font(.body)
                            .foregroundStyle(Color.white.opacity(0.9))
                            .frame(maxWidth: .infinity)
                    }
                }
                Link(destination: PAXLegalLinks.contact) {
                    Text(String(localized: "Contact"))
                        .font(.body)
                        .foregroundStyle(Color.white.opacity(0.9))
                        .frame(maxWidth: .infinity)
                }
            }
            .padding(.horizontal, 24)
            .padding(.vertical, 20)
        }
        .background(
            LinearGradient(
                colors: [
                    Color(red: 0.12, green: 0.12, blue: 0.12).opacity(0.85),
                    Color(red: 0.04, green: 0.04, blue: 0.04).opacity(0.65),
                ],
                startPoint: .topLeading,
                endPoint: .bottomTrailing
            )
        )
        .clipShape(RoundedRectangle(cornerRadius: 18, style: .continuous))
        .overlay(
            RoundedRectangle(cornerRadius: 18, style: .continuous)
                .stroke(Color.white.opacity(0.12), lineWidth: 1)
        )
        .shadow(color: .black.opacity(0.35), radius: 16, y: 8)
    }
}

// MARK: - GitHub private modal

struct CustomerGitHubPrivateSheet: View {
    @Environment(\.dismiss) private var dismiss

    var body: some View {
        NavigationStack {
            ScrollView {
                VStack(spacing: 18) {
                    ZStack(alignment: .bottomTrailing) {
                        RoundedRectangle(cornerRadius: 18, style: .continuous)
                            .fill(Color(red: 0.07, green: 0.09, blue: 0.15))
                            .frame(width: 72, height: 72)
                        Image(systemName: "chevron.left.forwardslash.chevron.right")
                            .font(.largeTitle)
                            .foregroundStyle(.white)
                        Image(systemName: "lock.fill")
                            .font(.caption)
                            .foregroundStyle(Color(red: 0.11, green: 0.11, blue: 0.11))
                            .padding(6)
                            .background(PAXBrand.accent)
                            .clipShape(Circle())
                            .offset(x: 6, y: 6)
                    }
                    .padding(.top, 8)

                    Text(String(localized: "Private Repository"))
                        .font(.caption.weight(.bold))
                        .foregroundStyle(PAXBrand.accent)
                        .padding(.horizontal, 12)
                        .padding(.vertical, 6)
                        .background(PAXBrand.accent.opacity(0.12))
                        .clipShape(Capsule())

                    Text(String(localized: "Powered by GitHub — Not Open Source"))
                        .font(.title3.weight(.bold))
                        .multilineTextAlignment(.center)

                    Text(String(localized: "PAXDesign uses GitHub as part of its professional development and deployment infrastructure. Our source code is proprietary, securely maintained, and intentionally not publicly available."))
                        .font(.body)
                        .foregroundStyle(PAXTheme.textSecondary)
                        .multilineTextAlignment(.center)

                    Text(String(localized: "This project is not open source. Access is restricted to authorized PAXDesign team members only."))
                        .font(.body)
                        .multilineTextAlignment(.center)

                    HStack(alignment: .top, spacing: 10) {
                        Image(systemName: "info.circle.fill")
                            .foregroundStyle(PAXBrand.accent)
                        Text(String(localized: "For business inquiries, please contact us directly via email."))
                            .font(.footnote)
                            .foregroundStyle(PAXTheme.textSecondary)
                    }
                    .padding(14)
                    .frame(maxWidth: .infinity, alignment: .leading)
                    .background(Color.primary.opacity(0.05))
                    .clipShape(RoundedRectangle(cornerRadius: 12, style: .continuous))

                    Button(String(localized: "Understood")) { dismiss() }
                        .buttonStyle(CustomerPrimaryButtonStyleModifier(style: .filled))
                }
                .padding(24)
            }
            .navigationBarTitleDisplayMode(.inline)
            .toolbar {
                ToolbarItem(placement: .topBarTrailing) {
                    Button(String(localized: "Close")) { dismiss() }
                }
            }
        }
        .presentationDetents([.medium, .large])
    }
}
