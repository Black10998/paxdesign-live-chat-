import SwiftUI

enum CustomerPortalDesign {
    static let cardRadius: CGFloat = 16
    static let sectionSpacing: CGFloat = 16
}

struct CustomerPortalCard<Content: View>: View {
    @ViewBuilder var content: Content

    var body: some View {
        content
            .padding(16)
            .frame(maxWidth: .infinity, alignment: .leading)
            .background(PAXTheme.surfaceElevated)
            .clipShape(RoundedRectangle(cornerRadius: CustomerPortalDesign.cardRadius, style: .continuous))
            .overlay(
                RoundedRectangle(cornerRadius: CustomerPortalDesign.cardRadius, style: .continuous)
                    .stroke(PAXTheme.border.opacity(0.35), lineWidth: 0.5)
            )
    }
}

struct CustomerPortalSectionHeader: View {
    let title: String
    var actionTitle: String? = nil
    var action: (() -> Void)? = nil

    var body: some View {
        HStack {
            Text(title)
                .font(.title3.weight(.semibold))
            Spacer()
            if let actionTitle, let action {
                Button(actionTitle, action: action)
                    .font(.subheadline.weight(.medium))
            }
        }
    }
}

struct CustomerServiceIconView: View {
    let iconKey: String
    var size: CGFloat = 44

    var body: some View {
        ZStack {
            RoundedRectangle(cornerRadius: 12, style: .continuous)
                .fill(PAXTheme.accentSoft)
            Image(systemName: systemIcon)
                .font(.system(size: size * 0.42, weight: .semibold))
                .foregroundStyle(PAXTheme.accent)
        }
        .frame(width: size, height: size)
    }

    private var systemIcon: String {
        switch iconKey.lowercased() {
        case "website", "webapp", "pwa": return "globe"
        case "ios", "android", "crossplatform": return "apps.iphone"
        case "security", "gdpr", "secintegrity", "sectamper", "secflash", "seclayers", "secruntime", "secobfusc", "sectoken", "seclicense": return "lock.shield"
        case "aichatbot", "aiautomation": return "brain.head.profile"
        case "bookingsystem": return "calendar"
        case "ecommerce": return "cart"
        case "uiux", "branding": return "paintbrush"
        case "maintenance", "devops", "backend": return "server.rack"
        case "pagespeed", "analytics": return "chart.line.uptrend.xyaxis"
        case "crm": return "person.2"
        case "enterprise": return "building.2"
        default: return "sparkles"
        }
    }
}

struct CustomerSafariLink: View {
    let title: String
    let url: URL
    var style: CustomerPrimaryButtonStyle = .filled

    var body: some View {
        Link(destination: url) {
            Text(title)
                .frame(maxWidth: .infinity)
        }
        .buttonStyle(CustomerPrimaryButtonStyleModifier(style: style))
    }
}

enum CustomerPrimaryButtonStyle {
    case filled, tinted
}

private struct CustomerPrimaryButtonStyleModifier: ButtonStyle {
    let style: CustomerPrimaryButtonStyle

    func makeBody(configuration: Configuration) -> some View {
        configuration.label
            .font(.headline)
            .padding(.vertical, 14)
            .padding(.horizontal, 18)
            .background(background(configuration.isPressed))
            .foregroundStyle(foreground)
            .clipShape(RoundedRectangle(cornerRadius: 14, style: .continuous))
            .scaleEffect(configuration.isPressed ? 0.98 : 1)
            .animation(PAXTheme.quickSpring, value: configuration.isPressed)
    }

    private var foreground: Color {
        style == .filled ? .white : PAXTheme.accent
    }

    private func background(_ pressed: Bool) -> Color {
        switch style {
        case .filled:
            return PAXTheme.accent.opacity(pressed ? 0.85 : 1)
        case .tinted:
            return PAXTheme.accentSoft.opacity(pressed ? 0.9 : 1)
        }
    }
}
