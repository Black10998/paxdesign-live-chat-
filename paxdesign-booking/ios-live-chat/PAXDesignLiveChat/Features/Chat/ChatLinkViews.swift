import SwiftUI

// MARK: - Compact inline link scan badge

struct LinkScanBadgeView: View {
    let message: LiveMessage
    var useStaffDisplay: Bool = false
    @State private var displayedStatus: LinkScanStatus

    init(message: LiveMessage, useStaffDisplay: Bool = false) {
        self.message = message
        self.useStaffDisplay = useStaffDisplay
        let initial = useStaffDisplay
            ? LinkScanSupport.staffDisplayStatus(for: message)
            : LinkScanSupport.resolvedStatus(for: message)
        _displayedStatus = State(initialValue: initial == .none && message.showsLinkScanBadge ? .checking : initial)
    }

    var body: some View {
        HStack(spacing: 5) {
            Image(systemName: iconName)
                .font(.system(size: 10, weight: .bold))
            Text(displayedStatus.label)
                .font(.caption2.weight(.bold))
                .lineLimit(1)
        }
        .foregroundStyle(foreground)
        .padding(.horizontal, 8)
        .padding(.vertical, 4)
        .background(
            Capsule(style: .continuous)
                .fill(background)
                .overlay(
                    Capsule(style: .continuous)
                        .strokeBorder(border, lineWidth: 1.5)
                )
        )
        .onChange(of: message.linkScanStatus) { _ in
            refreshDisplayedStatus()
        }
        .onChange(of: message.linkScanSystemStatus) { _ in
            refreshDisplayedStatus()
        }
        .onChange(of: message.linkScanReviewPending) { _ in
            refreshDisplayedStatus()
        }
    }

    private func refreshDisplayedStatus() {
        let resolved = useStaffDisplay
            ? LinkScanSupport.staffDisplayStatus(for: message)
            : LinkScanSupport.resolvedStatus(for: message)
        if resolved != .none {
            withAnimation(.easeInOut(duration: 0.28)) {
                displayedStatus = resolved
            }
        }
    }

    private var iconName: String {
        switch displayedStatus {
        case .checking: return "shield.lefthalf.filled.badge.checkmark"
        case .safe: return "checkmark.shield.fill"
        case .suspicious: return "exclamationmark.shield.fill"
        case .dangerous: return "xmark.shield.fill"
        case .failed, .timeout, .incomplete: return "questionmark.circle.fill"
        case .none: return "shield"
        }
    }

    private var foreground: Color {
        switch displayedStatus {
        case .checking: return Color(red: 0.15, green: 0.39, blue: 0.92)
        case .safe: return Color(red: 0.09, green: 0.64, blue: 0.29)
        case .suspicious: return Color(red: 0.85, green: 0.47, blue: 0.02)
        case .dangerous: return Color(red: 0.86, green: 0.15, blue: 0.15)
        case .failed, .timeout, .incomplete: return Color(red: 0.28, green: 0.33, blue: 0.41)
        case .none: return PAXTheme.textSecondary
        }
    }

    private var background: Color {
        switch displayedStatus {
        case .checking: return Color(red: 0.93, green: 0.96, blue: 1.0)
        case .safe: return Color(red: 0.94, green: 0.99, blue: 0.96)
        case .suspicious: return Color(red: 1.0, green: 0.98, blue: 0.94)
        case .dangerous: return Color(red: 1.0, green: 0.95, blue: 0.95)
        case .failed, .timeout, .incomplete: return Color(red: 0.97, green: 0.98, blue: 0.99)
        case .none: return PAXTheme.surface.opacity(0.72)
        }
    }

    private var border: Color { foreground.opacity(0.55) }
}

struct LinkScanReviewActionsView: View {
    let message: LiveMessage
    let isSubmitting: Bool
    let onAction: (String) -> Void

    var body: some View {
        VStack(alignment: .leading, spacing: 8) {
            Text(L10n.ChatLinkScanReviewTitle)
                .font(.caption2.weight(.bold))
                .foregroundStyle(PAXTheme.textSecondary)
                .textCase(.uppercase)

            HStack(spacing: 6) {
                reviewButton(
                    title: L10n.ChatLinkScanMarkSafe,
                    tint: Color(red: 0.09, green: 0.64, blue: 0.29),
                    action: "mark_safe"
                )
                reviewButton(
                    title: L10n.ChatLinkScanMarkUnsafe,
                    tint: Color(red: 0.86, green: 0.15, blue: 0.15),
                    action: "mark_unsafe"
                )
            }

            reviewButton(
                title: L10n.ChatLinkScanDeleteWarn,
                tint: Color(red: 0.85, green: 0.47, blue: 0.02),
                action: "delete_warn",
                fullWidth: true
            )
        }
        .padding(.top, 4)
    }

    @ViewBuilder
    private func reviewButton(title: String, tint: Color, action: String, fullWidth: Bool = false) -> some View {
        Button {
            onAction(action)
        } label: {
            Text(title)
                .font(.caption2.weight(.bold))
                .foregroundStyle(tint)
                .frame(maxWidth: fullWidth ? .infinity : nil)
                .padding(.horizontal, 10)
                .padding(.vertical, 6)
                .background(
                    Capsule(style: .continuous)
                        .fill(tint.opacity(0.12))
                        .overlay(
                            Capsule(style: .continuous)
                                .strokeBorder(tint.opacity(0.45), lineWidth: 1)
                        )
                )
        }
        .buttonStyle(.plain)
        .disabled(isSubmitting)
    }
}

// MARK: - Compact link card with SVG icon

struct LinkCardBubbleView: View {
    let message: LiveMessage
    var siteBaseURL: String?

    var body: some View {
        if let url = LinkScanSupport.resolveURL(message.linkUrl ?? "", siteBase: siteBaseURL) {
            Link(destination: url) {
                HStack(spacing: 8) {
                    QuickLinkIconView(
                        icon: message.linkIcon ?? "svg:link",
                        label: message.linkLabel ?? message.content,
                        size: 26
                    )
                    Text(LinkScanSupport.linkCardLabel(for: message))
                        .font(.caption.weight(.semibold))
                        .foregroundStyle(PAXTheme.textPrimary)
                        .lineLimit(1)
                    Spacer(minLength: 0)
                    Image(systemName: "arrow.up.right")
                        .font(.caption2.weight(.bold))
                        .foregroundStyle(PAXTheme.accent)
                }
                .padding(.horizontal, 10)
                .padding(.vertical, 8)
                .background(
                    RoundedRectangle(cornerRadius: 11, style: .continuous)
                        .fill(PAXTheme.accent.opacity(0.1))
                        .overlay(
                            RoundedRectangle(cornerRadius: 11, style: .continuous)
                                .stroke(PAXTheme.accent.opacity(0.24), lineWidth: 1)
                        )
                )
            }
            .buttonStyle(.plain)
        }
    }
}

struct QuickLinkIconView: View {
    let icon: String
    let label: String
    let size: CGFloat

    var body: some View {
        ZStack {
            RoundedRectangle(cornerRadius: size * 0.28, style: .continuous)
                .fill(PAXTheme.accent.opacity(0.14))
                .frame(width: size, height: size)
            if icon.hasPrefix("sf:") {
                Image(systemName: String(icon.dropFirst(3)))
                    .font(.system(size: size * 0.42, weight: .semibold))
                    .foregroundStyle(PAXTheme.accent)
            } else {
                QuickLinkVectorIcon(name: resolvedIconKey)
                    .frame(width: size * 0.52, height: size * 0.52)
                    .foregroundStyle(PAXTheme.accent)
            }
        }
    }

    private var resolvedIconKey: String {
        let raw = icon.hasPrefix("svg:") ? String(icon.dropFirst(4)) : icon
        if raw.range(of: #"^[a-z0-9_-]+$"#, options: .regularExpression) != nil, raw.count <= 24 {
            return raw
        }
        let lower = label.lowercased()
        if lower.contains("service") { return "services" }
        if lower.contains("project") { return "projects" }
        if lower.contains("pric") { return "pricing" }
        if lower.contains("contact") { return "contact" }
        if lower.contains("about") { return "about" }
        if lower.contains("faq") { return "faq" }
        if lower.contains("portfolio") { return "portfolio" }
        return "link"
    }
}

private struct QuickLinkVectorIcon: View {
    let name: String

    var body: some View {
        Canvas { context, size in
            let rect = CGRect(origin: .zero, size: size)
            var path = Path()
            switch name {
            case "services":
                path.move(to: CGPoint(x: rect.minX + rect.width * 0.15, y: rect.midY - rect.height * 0.18))
                path.addLine(to: CGPoint(x: rect.maxX - rect.width * 0.15, y: rect.midY - rect.height * 0.18))
                path.move(to: CGPoint(x: rect.minX + rect.width * 0.15, y: rect.midY))
                path.addLine(to: CGPoint(x: rect.maxX - rect.width * 0.35, y: rect.midY))
                path.move(to: CGPoint(x: rect.minX + rect.width * 0.15, y: rect.midY + rect.height * 0.18))
                path.addLine(to: CGPoint(x: rect.maxX - rect.width * 0.15, y: rect.midY + rect.height * 0.18))
            case "projects":
                path.move(to: CGPoint(x: rect.midX - rect.width * 0.28, y: rect.maxY - rect.height * 0.12))
                path.addLine(to: CGPoint(x: rect.midX, y: rect.minY + rect.height * 0.12))
                path.addLine(to: CGPoint(x: rect.midX + rect.width * 0.28, y: rect.maxY - rect.height * 0.12))
                path.closeSubpath()
            case "pricing":
                path.addEllipse(in: rect.insetBy(dx: rect.width * 0.12, dy: rect.height * 0.12))
                path.move(to: CGPoint(x: rect.midX, y: rect.midY - rect.height * 0.12))
                path.addLine(to: CGPoint(x: rect.midX, y: rect.midY + rect.height * 0.12))
                path.move(to: CGPoint(x: rect.midX - rect.width * 0.14, y: rect.midY))
                path.addLine(to: CGPoint(x: rect.midX + rect.width * 0.14, y: rect.midY))
            case "contact":
                path.addRoundedRect(in: rect.insetBy(dx: rect.width * 0.1, dy: rect.height * 0.18), cornerSize: CGSize(width: 2, height: 2))
            case "about":
                path.addEllipse(in: rect.insetBy(dx: rect.width * 0.12, dy: rect.height * 0.12))
                path.addEllipse(in: CGRect(x: rect.midX - 1.2, y: rect.midY + rect.height * 0.08, width: 2.4, height: 2.4))
            case "faq":
                path.addEllipse(in: rect.insetBy(dx: rect.width * 0.12, dy: rect.height * 0.12))
            case "portfolio":
                path.addRoundedRect(in: rect.insetBy(dx: rect.width * 0.1, dy: rect.height * 0.16), cornerSize: CGSize(width: 2, height: 2))
            default:
                path.addEllipse(in: CGRect(x: rect.midX - rect.width * 0.22, y: rect.midY - rect.height * 0.08, width: rect.width * 0.44, height: rect.height * 0.16))
                path.addEllipse(in: CGRect(x: rect.midX - rect.width * 0.22, y: rect.midY + rect.height * 0.08, width: rect.width * 0.44, height: rect.height * 0.16))
            }
            context.stroke(path, with: .foreground, style: StrokeStyle(lineWidth: 1.6, lineCap: .round, lineJoin: .round))
        }
    }
}

struct QuickLinksSheet: View {
    let links: [QuickLink]
    let isSending: Bool
    let onSelect: (QuickLink) -> Void
    @Environment(\.dismiss) private var dismiss

    var body: some View {
        NavigationStack {
            Group {
                if links.isEmpty {
                    VStack(spacing: 10) {
                        Image(systemName: "link.badge.plus")
                            .font(.system(size: 34))
                            .foregroundStyle(PAXTheme.textTertiary)
                        Text(L10n.ChatQuickLinksEmptyTitle)
                            .font(.headline)
                        Text(L10n.SettingsQuickLinksEmptyBody)
                            .font(.subheadline)
                            .foregroundStyle(PAXTheme.textSecondary)
                            .multilineTextAlignment(.center)
                            .padding(.horizontal, 24)
                    }
                    .frame(maxWidth: .infinity, maxHeight: .infinity)
                } else {
                    List(links) { link in
                        Button {
                            onSelect(link)
                        } label: {
                            HStack(spacing: 12) {
                                QuickLinkIconView(icon: link.icon, label: link.label, size: 30)
                                VStack(alignment: .leading, spacing: 2) {
                                    Text(link.label)
                                        .font(.subheadline.weight(.semibold))
                                        .foregroundStyle(PAXTheme.textPrimary)
                                    Text(link.url)
                                        .font(.caption)
                                        .foregroundStyle(PAXTheme.textSecondary)
                                        .lineLimit(1)
                                }
                                Spacer(minLength: 0)
                            }
                            .padding(.vertical, 4)
                        }
                        .disabled(isSending)
                    }
                    .listStyle(.insetGrouped)
                }
            }
            .navigationTitle(L10n.ChatQuickLinksTitle)
            .navigationBarTitleDisplayMode(.inline)
            .toolbar {
                ToolbarItem(placement: .cancellationAction) {
                    Button(L10n.CommonClose) { dismiss() }
                }
            }
        }
    }
}
