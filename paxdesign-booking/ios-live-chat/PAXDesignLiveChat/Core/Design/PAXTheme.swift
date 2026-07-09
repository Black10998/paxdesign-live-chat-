import SwiftUI
import UIKit

enum PAXTheme {
    static var accent: Color { .accentColor }
    static var success: Color { .green }
    static var danger: Color { .red }
    static var adminBubble: Color { Color(.systemBlue) }

    static var border: Color { Color(.separator) }
    static var textPrimary: Color { .primary }
    static var textSecondary: Color { .secondary }
    static var textTertiary: Color { Color(.tertiaryLabel) }
    static var userBubble: Color { Color(.systemGray5) }

    static var background: Color { Color(.systemGroupedBackground) }
    static var surface: Color { Color(.secondarySystemGroupedBackground) }
    static var surfaceElevated: Color { Color(.tertiarySystemGroupedBackground) }

    static var systemBubble: Color { accent.opacity(0.14) }
    static var accentSoft: Color { accent.opacity(0.12) }

    /// Native-feeling motion — short, no bounce.
    static let spring = Animation.easeInOut(duration: 0.22)
    static let quickSpring = Animation.easeOut(duration: 0.16)
    static let fade = Animation.easeInOut(duration: 0.2)
}

struct PAXBackground: View {
    var body: some View {
        Color(.systemGroupedBackground)
            .ignoresSafeArea()
    }
}

struct PAXGlassSurface: ViewModifier {
    func body(content: Content) -> some View {
        content
            .background(Color(.secondarySystemGroupedBackground), in: RoundedRectangle(cornerRadius: 10, style: .continuous))
    }
}

extension View {
    func paxGlassSurface() -> some View {
        modifier(PAXGlassSurface())
    }
}

enum PAXHaptics {
    private static let lightGenerator = UIImpactFeedbackGenerator(style: .light)
    private static let mediumGenerator = UIImpactFeedbackGenerator(style: .medium)
    private static let notificationGenerator = UINotificationFeedbackGenerator()

    static func prepare() {
        lightGenerator.prepare()
        mediumGenerator.prepare()
        notificationGenerator.prepare()
    }

    static func light() {
        lightGenerator.impactOccurred(intensity: 0.85)
        lightGenerator.prepare()
    }

    static func medium() {
        mediumGenerator.impactOccurred(intensity: 0.9)
        mediumGenerator.prepare()
    }

    static func success() {
        notificationGenerator.notificationOccurred(.success)
        notificationGenerator.prepare()
    }

    static func warning() {
        notificationGenerator.notificationOccurred(.warning)
        notificationGenerator.prepare()
    }
}
