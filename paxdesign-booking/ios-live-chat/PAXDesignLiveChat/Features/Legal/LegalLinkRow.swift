import SwiftUI

struct LegalWebLinkRow: View {
    let title: String
    let url: URL

    var body: some View {
        Link(destination: url) {
            HStack {
                Label { Text(title) } icon: { PAXIcon("safari") }
                Spacer()
                PAXIcon( "arrow.up.right")
                    .font(.caption.weight(.semibold))
                    .foregroundStyle(PAXTheme.textTertiary)
            }
        }
    }
}

struct LegalFooterLinks: View {
    var body: some View {
        VStack(spacing: 10) {
            LegalWebLinkRow(title: L10n.AccountPrivacyWeb, url: PAXLegalLinks.privacyPolicy)
            LegalWebLinkRow(title: L10n.AccountImprintWeb, url: PAXLegalLinks.impressum)
        }
        .font(.footnote)
    }
}
