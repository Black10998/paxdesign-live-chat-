import SwiftUI
import UIKit

enum PAXTheme {
    static let background = Color(red: 0.06, green: 0.07, blue: 0.09)
    static let surface = Color(red: 0.10, green: 0.11, blue: 0.14)
    static let surfaceElevated = Color(red: 0.14, green: 0.15, blue: 0.19)
    static let border = Color.white.opacity(0.08)
    static let textPrimary = Color.white
    static let textSecondary = Color.white.opacity(0.62)
    static let textTertiary = Color.white.opacity(0.38)
    static let accent = Color(red: 1.0, green: 0.55, blue: 0.0)
    static let accentSoft = Color(red: 1.0, green: 0.55, blue: 0.0).opacity(0.16)
    static let success = Color(red: 0.20, green: 0.78, blue: 0.45)
    static let danger = Color(red: 0.95, green: 0.30, blue: 0.28)
    static let adminBubble = Color(red: 0.12, green: 0.45, blue: 0.95)
    static let userBubble = Color.white.opacity(0.10)
    static let systemBubble = Color.orange.opacity(0.18)

    static let spring = Animation.spring(response: 0.42, dampingFraction: 0.82)
    static let quickSpring = Animation.spring(response: 0.32, dampingFraction: 0.78)
    static let fade = Animation.easeInOut(duration: 0.28)
}

struct PAXBackground: View {
    var body: some View {
        ZStack {
            PAXTheme.background
            RadialGradient(
                colors: [PAXTheme.accent.opacity(0.12), .clear],
                center: .topLeading,
                startRadius: 20,
                endRadius: 420
            )
            RadialGradient(
                colors: [Color.blue.opacity(0.08), .clear],
                center: .bottomTrailing,
                startRadius: 10,
                endRadius: 360
            )
        }
        .ignoresSafeArea()
    }
}

enum PAXHaptics {
    static func light() {
        UIImpactFeedbackGenerator(style: .light).impactOccurred()
    }

    static func medium() {
        UIImpactFeedbackGenerator(style: .medium).impactOccurred()
    }

    static func success() {
        UINotificationFeedbackGenerator().notificationOccurred(.success)
    }

    static func warning() {
        UINotificationFeedbackGenerator().notificationOccurred(.warning)
    }
}
