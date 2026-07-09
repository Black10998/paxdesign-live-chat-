import SwiftUI

struct SessionAvatarView: View {
    let name: String
    var size: CGFloat = 52
    var isLive: Bool = false
    var isTeam: Bool = false

    private var initials: String {
        let parts = name.split(separator: " ").prefix(2)
        let letters = parts.compactMap { $0.first.map(String.init) }.joined()
        if letters.isEmpty, let first = name.first {
            return String(first).uppercased()
        }
        return letters.uppercased()
    }

    private var avatarColor: Color {
        if isTeam { return PAXBrand.accent.opacity(0.85) }
        if isLive { return PAXTheme.accent }
        let hash = abs(name.hashValue)
        let hue = Double(hash % 360) / 360.0
        return Color(hue: hue, saturation: 0.42, brightness: 0.72)
    }

    var body: some View {
        ZStack {
            Circle()
                .fill(avatarColor.opacity(0.22))
                .frame(width: size, height: size)

            Text(initials)
                .font(.system(size: size * 0.34, weight: .semibold, design: .rounded))
                .foregroundStyle(avatarColor)
        }
        .overlay(
            Circle()
                .stroke(Color.primary.opacity(0.06), lineWidth: 0.5)
        )
    }
}

struct SessionUnreadBadge: View {
    let count: Int

    var body: some View {
        if count > 0 {
            Text(count > 99 ? "99+" : "\(count)")
                .font(.system(size: 12, weight: .bold, design: .rounded))
                .foregroundStyle(.white)
                .padding(.horizontal, count > 9 ? 6 : 0)
                .frame(minWidth: 22, minHeight: 22)
                .background(Capsule().fill(PAXBrand.accent))
        }
    }
}
