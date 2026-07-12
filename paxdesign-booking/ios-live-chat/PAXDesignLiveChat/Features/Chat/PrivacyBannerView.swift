import SwiftUI

struct PrivacyBannerView: View {
    var onDismiss: (() -> Void)?

    var body: some View {
        HStack(spacing: 10) {
            PAXIcon("lock.shield.fill", size: .inline)

            Text(L10n.ChatPrivacyBanner)
                .font(.caption2)
                .foregroundStyle(PAXTheme.textSecondary)
                .fixedSize(horizontal: false, vertical: true)

            if let onDismiss {
                Button(action: onDismiss) {
                    PAXIcon("xmark", size: .inline, emphasis: .tertiary)
                        .padding(6)
                }
                .buttonStyle(.plain)
            }
        }
        .padding(.horizontal, 12)
        .padding(.vertical, 8)
        .background(
            RoundedRectangle(cornerRadius: 12, style: .continuous)
                .fill(PAXTheme.success.opacity(0.10))
                .overlay(
                    RoundedRectangle(cornerRadius: 12, style: .continuous)
                        .stroke(PAXTheme.success.opacity(0.22), lineWidth: 1)
                )
        )
        .accessibilityElement(children: .combine)
        .accessibilityLabel(L10n.ChatPrivacyBanner)
    }
}
