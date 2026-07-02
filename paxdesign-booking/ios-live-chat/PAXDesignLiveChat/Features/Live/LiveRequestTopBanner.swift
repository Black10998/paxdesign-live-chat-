import SwiftUI

struct LiveRequestTopBanner: View {
    let request: IncomingLiveRequest
    let onTap: () -> Void
    let onDismiss: () -> Void

    @State private var pulse = false

    var body: some View {
        Button(action: onTap) {
            HStack(spacing: 12) {
                ZStack {
                    Circle()
                        .fill(PAXTheme.accent.opacity(pulse ? 0.28 : 0.12))
                        .frame(width: 40, height: 40)
                        .scaleEffect(pulse ? 1.08 : 1)
                    Image(systemName: "bell.and.waves.left.and.right.fill")
                        .font(.body.weight(.semibold))
                        .foregroundStyle(PAXTheme.accent)
                        .scaleEffect(pulse ? 1.06 : 0.94)
                }

                VStack(alignment: .leading, spacing: 2) {
                    HStack(spacing: 6) {
                        Text("LIVE")
                            .font(.caption2.weight(.heavy))
                            .foregroundStyle(.white)
                            .padding(.horizontal, 6)
                            .padding(.vertical, 2)
                            .background(Capsule().fill(PAXTheme.accent))
                        Text("Neue Live-Anfrage")
                            .font(.subheadline.weight(.semibold))
                            .foregroundStyle(PAXTheme.textPrimary)
                    }
                    Text(request.session.displayName)
                        .font(.caption)
                        .foregroundStyle(PAXTheme.textSecondary)
                        .lineLimit(1)
                }

                Spacer(minLength: 4)

                Button(action: onDismiss) {
                    Image(systemName: "xmark")
                        .font(.caption.weight(.bold))
                        .foregroundStyle(PAXTheme.textTertiary)
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
                            .stroke(PAXTheme.accent.opacity(0.45), lineWidth: 1)
                    )
                    .shadow(color: PAXTheme.accent.opacity(0.18), radius: 12, y: 4)
            )
        }
        .buttonStyle(.plain)
        .padding(.horizontal, 12)
        .padding(.top, 6)
        .onAppear {
            withAnimation(.easeInOut(duration: 0.9).repeatForever(autoreverses: true)) {
                pulse = true
            }
        }
    }
}
