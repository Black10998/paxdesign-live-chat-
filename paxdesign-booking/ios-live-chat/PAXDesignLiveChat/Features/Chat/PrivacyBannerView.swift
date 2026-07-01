import SwiftUI

struct PrivacyBannerView: View {
    var onDismiss: (() -> Void)?

    var body: some View {
        HStack(spacing: 10) {
            Image(systemName: "lock.shield.fill")
                .font(.caption)
                .foregroundStyle(PAXTheme.success)

            Text("Nachrichten werden per TLS (HTTPS) übertragen. Keine Ende-zu-Ende-Verschlüsselung.")
                .font(.caption2)
                .foregroundStyle(PAXTheme.textSecondary)
                .fixedSize(horizontal: false, vertical: true)

            if let onDismiss {
                Button(action: onDismiss) {
                    Image(systemName: "xmark")
                        .font(.caption2.weight(.semibold))
                        .foregroundStyle(PAXTheme.textTertiary)
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
        .accessibilityLabel("Sicherheitshinweis: Nachrichten werden per TLS übertragen.")
    }
}
