import SwiftUI

enum CustomerPortalDesign {
    static let cardRadius: CGFloat = 16
    static let sectionSpacing: CGFloat = PAXSpacing.sectionGap
}

struct CustomerPortalCard<Content: View>: View {
    @ViewBuilder var content: Content

    var body: some View {
        content
            .padding(PAXSpacing.md)
            .frame(maxWidth: .infinity, alignment: .leading)
            .paxRevolutSurface(cornerRadius: CustomerPortalDesign.cardRadius, elevation: 0)
    }
}

struct CustomerPortalSectionHeader: View {
    let title: String
    var actionTitle: String? = nil
    var action: (() -> Void)? = nil

    var body: some View {
        HStack {
            Text(title)
                .font(PAXTypography.subsection)
                .foregroundStyle(PAXTheme.textPrimary)
            Spacer()
            if let actionTitle, let action {
                Button(actionTitle, action: action)
                    .font(PAXTypography.meta.weight(.semibold))
                    .foregroundStyle(PAXTheme.link)
            }
        }
    }
}

struct CustomerServiceIconView: View {
    let iconKey: String
    var size: CGFloat = 44

    var body: some View {
        ZStack {
            RoundedRectangle(cornerRadius: size * 0.27, style: .continuous)
                .fill(
                    LinearGradient(
                        colors: [PAXTheme.accent.opacity(0.18), PAXTheme.accent.opacity(0.08)],
                        startPoint: .topLeading,
                        endPoint: .bottomTrailing
                    )
                )
            PAXIcon(
                vectorIcon,
                size: paxSize,
                emphasis: .primary,
                tint: PAXTheme.accent
            )
        }
        .frame(width: size, height: size)
        .overlay(
            RoundedRectangle(cornerRadius: size * 0.27, style: .continuous)
                .stroke(PAXTheme.accent.opacity(0.2), lineWidth: 0.5)
        )
    }

    private var paxSize: PAXIconSize {
        if size >= 64 { return .display }
        if size >= 48 { return .action }
        if size >= 36 { return .card }
        return .row
    }

    private var vectorIcon: String {
        switch iconKey.lowercased() {
        case "website", "webapp", "pwa": return "globe"
        case "ios", "android", "crossplatform": return "iphone"
        case "security", "gdpr", "secintegrity", "sectamper", "secflash", "seclayers", "secruntime", "secobfusc", "sectoken", "seclicense": return "lock.shield"
        case "aichatbot", "aiautomation": return "sparkles"
        case "bookingsystem": return "calendar"
        case "ecommerce": return "briefcase"
        case "uiux", "branding": return "paintbrush"
        case "maintenance", "devops", "backend": return "gear"
        case "pagespeed", "analytics": return "chart.line"
        case "crm": return "person.2"
        case "enterprise": return "briefcase"
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

struct CustomerPrimaryButtonStyleModifier: ButtonStyle {
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
        style == .filled ? PAXTheme.onAccent : PAXTheme.accent
    }

    private func background(_ pressed: Bool) -> Color {
        switch style {
        case .filled:
            return PAXTheme.accent.opacity(pressed ? 0.88 : 1)
        case .tinted:
            return PAXTheme.accentSoft.opacity(pressed ? 0.9 : 1)
        }
    }
}

struct CustomerNotificationCategoryBadge: View {
    let category: String

    var body: some View {
        PAXLabel(title, icon: icon)
            .font(.caption.weight(.semibold))
            .padding(.horizontal, 8)
            .padding(.vertical, 4)
            .background(PAXTheme.accentSoft)
            .foregroundStyle(PAXTheme.accent)
            .clipShape(Capsule())
    }

    private var icon: String {
        switch category.lowercased() {
        case "chat": return "message.fill"
        case "project": return "folder.fill"
        case "order": return "doc.text.fill"
        case "news": return "newspaper.fill"
        case "security", "account": return "lock.shield.fill"
        default: return "bell.fill"
        }
    }

    private var title: String {
        switch category.lowercased() {
        case "chat": return String(localized: "Chat")
        case "project": return String(localized: "Project")
        case "order": return String(localized: "Request")
        case "news": return String(localized: "News")
        case "security": return String(localized: "Security")
        case "account": return String(localized: "Account")
        default: return String(localized: "Update")
        }
    }
}

struct CustomerFileRow: View {
    let name: String
    let subtitle: String
    let size: Int
    var isLoading = false
    let action: () -> Void

    var body: some View {
        Button(action: action) {
            HStack(spacing: 12) {
                PAXIcon("doc.fill", size: .hero, tint: PAXTheme.accent)
                    .frame(width: 36)
                VStack(alignment: .leading, spacing: 2) {
                    Text(name).font(.headline).foregroundStyle(PAXTheme.textPrimary)
                    Text(subtitle).font(.caption).foregroundStyle(PAXTheme.textSecondary)
                }
                Spacer()
                if isLoading {
                    ProgressView().controlSize(.small)
                } else {
                    PAXIcon("arrow.down.circle.fill", size: .hero, tint: PAXTheme.accent)
                }
            }
            .padding(.vertical, 4)
        }
        .buttonStyle(.plain)
        .disabled(isLoading)
    }
}

enum CustomerPortalFormatting {
    static func fileSize(_ bytes: Int) -> String {
        let formatter = ByteCountFormatter()
        formatter.countStyle = .file
        return formatter.string(fromByteCount: Int64(bytes))
    }

    static func relativeDate(_ iso: String) -> String {
        let parsers: [ISO8601DateFormatter] = {
            let full = ISO8601DateFormatter()
            full.formatOptions = [.withInternetDateTime, .withFractionalSeconds]
            let basic = ISO8601DateFormatter()
            basic.formatOptions = [.withInternetDateTime]
            return [full, basic]
        }()
        guard let date = parsers.compactMap({ $0.date(from: iso) }).first else { return iso }
        let formatter = RelativeDateTimeFormatter()
        formatter.unitsStyle = .abbreviated
        return formatter.localizedString(for: date, relativeTo: Date())
    }

    static func htmlPlainText(_ html: String) -> String {
        html
            .replacingOccurrences(of: "<br\\s*/?>", with: "\n", options: .regularExpression)
            .replacingOccurrences(of: "</p>", with: "\n\n", options: .caseInsensitive)
            .replacingOccurrences(of: "<[^>]+>", with: "", options: .regularExpression)
            .replacingOccurrences(of: "&nbsp;", with: " ")
            .replacingOccurrences(of: "&amp;", with: "&")
            .replacingOccurrences(of: "&quot;", with: "\"")
            .trimmingCharacters(in: .whitespacesAndNewlines)
    }
}
