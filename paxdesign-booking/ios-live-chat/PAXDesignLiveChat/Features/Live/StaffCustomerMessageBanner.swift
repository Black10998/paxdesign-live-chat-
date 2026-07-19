import SwiftUI

struct StaffCustomerMessageBanner: View {
    let banner: PendingCustomerMessageBanner
    let onTap: () -> Void
    let onDismiss: () -> Void

    var body: some View {
        Button(action: onTap) {
            HStack(spacing: 12) {
                PAXIcon("message.fill", size: .row, tint: PAXTheme.accent)
                    .frame(width: 36, height: 36)
                    .background(PAXTheme.accentSoft)
                    .clipShape(RoundedRectangle(cornerRadius: 10, style: .continuous))

                VStack(alignment: .leading, spacing: 2) {
                    Text(banner.customerName.isEmpty ? L10n.NotifyNewMessageTitle : banner.customerName)
                        .font(.subheadline.weight(.semibold))
                        .foregroundStyle(PAXTheme.textPrimary)
                        .lineLimit(1)
                    Text(banner.preview.isEmpty ? L10n.NotifyNewMessageBody : banner.preview)
                        .font(.caption)
                        .foregroundStyle(PAXTheme.textSecondary)
                        .lineLimit(2)
                }

                Spacer(minLength: 4)

                Button(action: onDismiss) {
                    PAXIcon("xmark", size: .inline, emphasis: .tertiary)
                        .padding(8)
                }
                .buttonStyle(.plain)
            }
            .padding(.horizontal, 14)
            .padding(.vertical, 11)
            .background(
                RoundedRectangle(cornerRadius: 16, style: .continuous)
                    .fill(.ultraThinMaterial)
                    .overlay(
                        RoundedRectangle(cornerRadius: 16, style: .continuous)
                            .stroke(PAXTheme.border, lineWidth: 1)
                    )
            )
        }
        .buttonStyle(.plain)
        .padding(.horizontal, 12)
        .padding(.top, 6)
    }
}
