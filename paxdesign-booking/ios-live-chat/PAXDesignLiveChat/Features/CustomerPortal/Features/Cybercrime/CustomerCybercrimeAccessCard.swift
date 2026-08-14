import SwiftUI

/// First-class in-app entry to Cybercrime Support — Home, Services, Account, and workspace.
struct CustomerCybercrimeAccessCard: View {
    var compact = false
    var onOpen: () -> Void

    var body: some View {
        Button {
            PAXHaptics.light()
            onOpen()
        } label: {
            VStack(alignment: .leading, spacing: compact ? 10 : 14) {
                HStack(spacing: 12) {
                    PAXRevolutGlyphAvatar(systemImage: "shield.checkered", size: compact ? 40 : 48, tint: PAXTheme.accent)
                    VStack(alignment: .leading, spacing: 3) {
                        Text(String(localized: "Cybercrime Support"))
                            .font(PAXTypography.rowTitle)
                            .foregroundStyle(PAXTheme.textPrimary)
                        Text(String(localized: "Report, track, and message the team in-app"))
                            .font(PAXTypography.meta)
                            .foregroundStyle(PAXTheme.textSecondary)
                            .fixedSize(horizontal: false, vertical: true)
                    }
                    Spacer(minLength: 8)
                    PAXIcon("chevron.right", size: .inline, emphasis: .tertiary)
                }
                if !compact {
                    Text(String(localized: "Submit a confidential report, follow its status, and talk with PAXDesign Support — the same workflow as the website, inside this app."))
                        .font(PAXTypography.meta)
                        .foregroundStyle(PAXTheme.textSecondary)
                        .fixedSize(horizontal: false, vertical: true)
                    Text(String(localized: "Open Cybercrime Support"))
                        .font(PAXTypography.button)
                        .foregroundStyle(PAXTheme.accent)
                }
            }
            .padding(compact ? 14 : 18)
            .frame(maxWidth: .infinity, alignment: .leading)
            .paxRevolutSurface(cornerRadius: 18, elevation: 1)
        }
        .buttonStyle(PAXRevolutPressableStyle())
        .accessibilityLabel(String(localized: "Cybercrime Support"))
        .accessibilityHint(String(localized: "Submit and track a confidential cybercrime report in the app."))
    }
}

struct CustomerCybercrimeDestinationView: View {
    let destination: CustomerPortalDestination

    var body: some View {
        switch destination.kind {
        case .cybercrime:
            CustomerCybercrimePortalView()
        case .cybercrimeReport(let reference):
            CustomerCybercrimeReportDetailView(reference: reference)
        default:
            EmptyView()
        }
    }
}
