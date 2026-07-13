import SwiftUI

/// Staff avatar with remote image support and monochrome fallback initials.
struct StaffAvatarView: View {
    let name: String
    var avatarUrl: String? = nil
    var size: CGFloat = 48

    var body: some View {
        Group {
            if let avatarUrl,
               let url = URL(string: avatarUrl),
               !avatarUrl.isEmpty {
                AsyncImage(url: url) { phase in
                    switch phase {
                    case .success(let image):
                        image.resizable().scaledToFill()
                    default:
                        initialsView
                    }
                }
            } else {
                initialsView
            }
        }
        .frame(width: size, height: size)
        .clipShape(Circle())
        .overlay(
            Circle()
                .stroke(PAXTheme.border.opacity(0.55), lineWidth: 0.5)
        )
        .accessibilityLabel(name)
    }

    private var initialsView: some View {
        ZStack {
            Circle()
                .fill(Color.primary.opacity(0.06))
            Text(initials)
                .font(.system(size: size * 0.34, weight: .semibold, design: .rounded))
                .foregroundStyle(PAXTheme.textSecondary)
        }
    }

    private var initials: String {
        let parts = name.split(separator: " ").prefix(2)
        let letters = parts.compactMap { $0.first.map(String.init) }.joined()
        if letters.isEmpty, let first = name.first {
            return String(first).uppercased()
        }
        return letters.uppercased()
    }
}

/// Monochrome presence ring — no color fills, Apple-style depth.
struct TeamPresenceGlyph: View {
    let status: String

    var body: some View {
        ZStack {
            Circle()
                .stroke(PAXTheme.border.opacity(0.35), lineWidth: 0.5)
                .frame(width: 10, height: 10)
            Circle()
                .fill(fillStyle)
                .frame(width: 6, height: 6)
        }
        .accessibilityLabel(presenceLabel)
    }

    private var fillStyle: Color {
        switch status {
        case "online": return PAXTheme.textPrimary.opacity(0.92)
        case "away": return PAXTheme.textSecondary.opacity(0.72)
        default: return PAXTheme.textTertiary.opacity(0.45)
        }
    }

    private var presenceLabel: String {
        switch status {
        case "online": return L10n.TeamPresenceOnline
        case "away": return L10n.TeamPresenceAway
        default: return L10n.TeamPresenceOffline
        }
    }
}
