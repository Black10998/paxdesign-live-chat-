import SwiftUI
import UIKit

enum PAXTheme {
    static let accent = Color(red: 1.0, green: 0.55, blue: 0.0)
    static let success = Color(red: 0.20, green: 0.78, blue: 0.45)
    static let danger = Color(red: 0.95, green: 0.30, blue: 0.28)
    static let adminBubble = Color(red: 0.12, green: 0.45, blue: 0.95)

    static let background = adaptive(
        light: UIColor(red: 0.96, green: 0.97, blue: 0.98, alpha: 1),
        dark: UIColor(red: 0.06, green: 0.07, blue: 0.09, alpha: 1)
    )
    static let surface = adaptive(
        light: UIColor(red: 1.0, green: 1.0, blue: 1.0, alpha: 1),
        dark: UIColor(red: 0.10, green: 0.11, blue: 0.14, alpha: 1)
    )
    static let surfaceElevated = adaptive(
        light: UIColor(red: 0.94, green: 0.95, blue: 0.97, alpha: 1),
        dark: UIColor(red: 0.14, green: 0.15, blue: 0.19, alpha: 1)
    )
    static let border = adaptive(
        light: UIColor(white: 0.0, alpha: 0.10),
        dark: UIColor(white: 1.0, alpha: 0.08)
    )
    static let textPrimary = adaptive(
        light: UIColor(red: 0.08, green: 0.09, blue: 0.11, alpha: 1),
        dark: UIColor(white: 1.0, alpha: 1)
    )
    static let textSecondary = adaptive(
        light: UIColor(red: 0.08, green: 0.09, blue: 0.11, alpha: 0.62),
        dark: UIColor(white: 1.0, alpha: 0.62)
    )
    static let textTertiary = adaptive(
        light: UIColor(red: 0.08, green: 0.09, blue: 0.11, alpha: 0.38),
        dark: UIColor(white: 1.0, alpha: 0.38)
    )
    static let userBubble = adaptive(
        light: UIColor(white: 0.0, alpha: 0.06),
        dark: UIColor(white: 1.0, alpha: 0.10)
    )
    static let systemBubble = Color.orange.opacity(0.18)

    static var accentSoft: Color { accent.opacity(0.16) }

    static let spring = Animation.spring(response: 0.42, dampingFraction: 0.82)
    static let quickSpring = Animation.spring(response: 0.32, dampingFraction: 0.78)
    static let fade = Animation.easeInOut(duration: 0.28)

    private static func adaptive(light: UIColor, dark: UIColor) -> Color {
        Color(UIColor { traits in
            traits.userInterfaceStyle == .dark ? dark : light
        })
    }
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
