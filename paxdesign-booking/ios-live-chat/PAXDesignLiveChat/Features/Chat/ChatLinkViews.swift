import SwiftUI

// MARK: - Link scan badge (premium animated)

struct LinkScanBadgeView: View {
    let message: LiveMessage
    @State private var displayedStatus: LinkScanStatus

    init(message: LiveMessage) {
        self.message = message
        let initial = LinkScanSupport.resolvedStatus(for: message)
        _displayedStatus = State(initialValue: initial == .none && message.showsLinkScanBadge ? .checking : initial)
    }

    var body: some View {
        HStack(spacing: 12) {
            LinkScanShieldAnimationView(status: displayedStatus, glow: displayedStatus == .checking)
                .frame(width: 40, height: 40)

            VStack(alignment: .leading, spacing: 6) {
                Text(displayedStatus.label)
                    .font(.caption.weight(.semibold))
                    .foregroundStyle(tint)
                    .contentTransition(.numericText())
                    .animation(.easeInOut(duration: 0.35), value: displayedStatus.label)

                if displayedStatus == .checking {
                    ProgressView()
                        .progressViewStyle(.linear)
                        .tint(tint)
                        .frame(height: 4)
                }
            }
        }
        .padding(.horizontal, 12)
        .padding(.vertical, 10)
        .background(
            RoundedRectangle(cornerRadius: 12, style: .continuous)
                .fill(background)
                .overlay(
                    RoundedRectangle(cornerRadius: 12, style: .continuous)
                        .stroke(border, lineWidth: 1)
                )
        )
        .onChange(of: message.linkScanStatus) { _, newValue in
            let resolved = LinkScanStatus(raw: newValue)
            if resolved != .none {
                withAnimation(.spring(response: 0.45, dampingFraction: 0.82)) {
                    displayedStatus = resolved
                }
            }
        }
        .animation(.spring(response: 0.45, dampingFraction: 0.82), value: displayedStatus)
    }

    private var tint: Color {
        switch displayedStatus {
        case .checking: return PAXTheme.textSecondary
        case .safe: return Color(red: 0.09, green: 0.50, blue: 0.24)
        case .suspicious: return Color(red: 0.71, green: 0.33, blue: 0.04)
        case .dangerous: return PAXTheme.danger
        case .failed, .timeout, .incomplete: return Color(red: 0.45, green: 0.48, blue: 0.56)
        case .none: return PAXTheme.textSecondary
        }
    }

    private var background: Color {
        switch displayedStatus {
        case .checking: return PAXTheme.surface.opacity(0.78)
        case .safe: return Color(red: 0.13, green: 0.77, blue: 0.37, opacity: 0.12)
        case .suspicious: return Color(red: 0.96, green: 0.62, blue: 0.04, opacity: 0.14)
        case .dangerous: return PAXTheme.danger.opacity(0.12)
        case .failed, .timeout, .incomplete: return Color(red: 0.45, green: 0.48, blue: 0.56, opacity: 0.12)
        case .none: return PAXTheme.surface.opacity(0.72)
        }
    }

    private var border: Color { tint.opacity(0.28) }
}

private struct LinkScanShieldAnimationView: View {
    let status: LinkScanStatus
    let glow: Bool

    var body: some View {
        TimelineView(.animation(minimumInterval: 1 / 30)) { timeline in
            let t = timeline.date.timeIntervalSinceReferenceDate
            ZStack {
                Circle()
                    .fill(accent.opacity(glow ? 0.16 : 0.08))
                    .scaleEffect(glow ? 1.08 + CGFloat(sin(t * 2.4)) * 0.04 : 1)
                    .blur(radius: glow ? 6 : 2)

                ShieldShape()
                    .fill(
                        LinearGradient(
                            colors: [accent.opacity(0.95), accent.opacity(0.55)],
                            startPoint: .topLeading,
                            endPoint: .bottomTrailing
                        )
                    )
                    .overlay(ShieldShape().stroke(Color.white.opacity(0.35), lineWidth: 0.8))
                    .frame(width: 26, height: 30)
                    .rotationEffect(status == .checking ? .degrees(t.truncatingRemainder(dividingBy: 1) * 12 - 6) : .zero)

                resultGlyph
            }
        }
    }

    @ViewBuilder
    private var resultGlyph: some View {
        switch status {
        case .safe:
            Image(systemName: "checkmark")
                .font(.caption2.weight(.bold))
                .foregroundStyle(.white)
                .transition(.scale.combined(with: .opacity))
        case .suspicious:
            Image(systemName: "exclamationmark")
                .font(.caption2.weight(.bold))
                .foregroundStyle(.white)
        case .dangerous:
            Image(systemName: "xmark")
                .font(.caption2.weight(.bold))
                .foregroundStyle(.white)
        case .failed, .timeout, .incomplete:
            Image(systemName: "questionmark")
                .font(.caption2.weight(.bold))
                .foregroundStyle(.white)
        case .checking:
            Circle()
                .trim(from: 0.08, to: 0.72)
                .stroke(Color.white.opacity(0.9), style: StrokeStyle(lineWidth: 2, lineCap: .round))
                .frame(width: 12, height: 12)
                .rotationEffect(.degrees(-90))
        case .none:
            EmptyView()
        }
    }

    private var accent: Color {
        switch status {
        case .checking: return Color(red: 0.45, green: 0.52, blue: 0.64)
        case .safe: return Color(red: 0.13, green: 0.72, blue: 0.38)
        case .suspicious: return Color(red: 0.92, green: 0.58, blue: 0.08)
        case .dangerous: return PAXTheme.danger
        case .failed, .timeout, .incomplete: return Color(red: 0.45, green: 0.48, blue: 0.56)
        case .none: return PAXTheme.textSecondary
        }
    }
}

private struct ShieldShape: Shape {
    func path(in rect: CGRect) -> Path {
        var path = Path()
        let w = rect.width
        let h = rect.height
        path.move(to: CGPoint(x: w * 0.5, y: 0))
        path.addLine(to: CGPoint(x: w, y: h * 0.18))
        path.addQuadCurve(to: CGPoint(x: w * 0.5, y: h), control: CGPoint(x: w * 0.92, y: h * 0.72))
        path.addQuadCurve(to: CGPoint(x: 0, y: h * 0.18), control: CGPoint(x: w * 0.08, y: h * 0.72))
        path.closeSubpath()
        return path
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
                        .fill(
                            LinearGradient(
                                colors: [
                                    PAXTheme.accent.opacity(0.14),
                                    PAXTheme.accent.opacity(0.05),
                                ],
                                startPoint: .topLeading,
                                endPoint: .bottomTrailing
                            )
                        )
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
