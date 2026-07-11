import SwiftUI

struct LinkScanBadgeView: View {
    let message: LiveMessage
    @State private var displayedStatus: LinkScanStatus

    init(message: LiveMessage) {
        self.message = message
        let initial = LinkScanSupport.resolvedStatus(for: message)
        _displayedStatus = State(initialValue: initial == .none && message.showsLinkScanBadge ? .checking : initial)
    }

    var body: some View {
        HStack(spacing: 8) {
            Group {
                if displayedStatus == .checking {
                    Image(systemName: displayedStatus.symbolName)
                        .rotationEffect(.degrees(spin ? 360 : 0))
                        .animation(.linear(duration: 0.9).repeatForever(autoreverses: false), value: spin)
                } else {
                    Image(systemName: displayedStatus.symbolName)
                }
            }
            .font(.caption.weight(.semibold))
            .foregroundStyle(tint)
            .frame(width: 16)

            Text(displayedStatus.label)
                .font(.caption.weight(.semibold))
                .foregroundStyle(tint)
        }
        .padding(.horizontal, 10)
        .padding(.vertical, 7)
        .background(
            RoundedRectangle(cornerRadius: 10, style: .continuous)
                .fill(background)
                .overlay(
                    RoundedRectangle(cornerRadius: 10, style: .continuous)
                        .stroke(border, lineWidth: 1)
                )
        )
        .onAppear {
            if displayedStatus == .checking {
                spin = true
                resolveCheckingStatus()
            }
        }
    }

    @State private var spin = false

    private var tint: Color {
        switch displayedStatus {
        case .checking: return PAXTheme.textSecondary
        case .safe: return Color(red: 0.09, green: 0.50, blue: 0.24)
        case .suspicious: return Color(red: 0.71, green: 0.33, blue: 0.04)
        case .dangerous: return PAXTheme.danger
        case .none: return PAXTheme.textSecondary
        }
    }

    private var background: Color {
        switch displayedStatus {
        case .checking: return PAXTheme.surface.opacity(0.72)
        case .safe: return Color(red: 0.13, green: 0.77, blue: 0.37, opacity: 0.12)
        case .suspicious: return Color(red: 0.96, green: 0.62, blue: 0.04, opacity: 0.14)
        case .dangerous: return PAXTheme.danger.opacity(0.12)
        case .none: return PAXTheme.surface.opacity(0.72)
        }
    }

    private var border: Color {
        tint.opacity(0.28)
    }

    private func resolveCheckingStatus() {
        guard displayedStatus == .checking else { return }
        Task { @MainActor in
            try? await Task.sleep(nanoseconds: 420_000_000)
            let resolved = LinkScanSupport.resolvedStatus(for: message)
            displayedStatus = resolved == .none ? .safe : resolved
            spin = false
        }
    }
}

struct LinkCardBubbleView: View {
    let message: LiveMessage
    var siteBaseURL: String?

    var body: some View {
        if let url = LinkScanSupport.resolveURL(message.linkUrl ?? "", siteBase: siteBaseURL) {
            Link(destination: url) {
                HStack(spacing: 10) {
                    Text(message.linkIcon ?? "🔗")
                        .font(.title3)
                    Text(LinkScanSupport.linkCardLabel(for: message))
                        .font(.subheadline.weight(.semibold))
                        .foregroundStyle(PAXTheme.textPrimary)
                        .multilineTextAlignment(.leading)
                    Spacer(minLength: 0)
                    Image(systemName: "arrow.up.right")
                        .font(.caption.weight(.bold))
                        .foregroundStyle(PAXTheme.accent)
                }
                .padding(.horizontal, 12)
                .padding(.vertical, 11)
                .background(
                    RoundedRectangle(cornerRadius: 12, style: .continuous)
                        .fill(
                            LinearGradient(
                                colors: [
                                    PAXTheme.accent.opacity(0.16),
                                    PAXTheme.accent.opacity(0.06),
                                ],
                                startPoint: .topLeading,
                                endPoint: .bottomTrailing
                            )
                        )
                        .overlay(
                            RoundedRectangle(cornerRadius: 12, style: .continuous)
                                .stroke(PAXTheme.accent.opacity(0.28), lineWidth: 1)
                        )
                )
            }
            .buttonStyle(.plain)
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
                        Text(L10n.ChatQuickLinksEmptyBody)
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
                                Text(link.icon)
                                    .font(.title3)
                                    .frame(width: 28)
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
