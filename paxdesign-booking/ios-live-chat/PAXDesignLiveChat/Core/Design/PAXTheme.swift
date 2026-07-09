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
        ZStack {
            LinearGradient(
                colors: [
                    PAXTheme.background,
                    PAXTheme.background.opacity(0.94),
                    PAXTheme.surface.opacity(0.86)
                ],
                startPoint: .topLeading,
                endPoint: .bottomTrailing
            )

            RadialGradient(
                colors: [PAXTheme.accent.opacity(0.18), .clear],
                center: .topTrailing,
                startRadius: 20,
                endRadius: 360
            )
            .blendMode(.plusLighter)

            RadialGradient(
                colors: [PAXTheme.success.opacity(0.11), .clear],
                center: .bottomLeading,
                startRadius: 30,
                endRadius: 320
            )
            .blendMode(.plusLighter)
        }
            .ignoresSafeArea()
    }
}

struct PAXGlassSurface: ViewModifier {
    func body(content: Content) -> some View {
        content
            .paxGlassCardStyle(cornerRadius: 14, fillOpacity: 0.82, borderOpacity: 0.45, shadowOpacity: 0.2)
    }
}

private struct PAXGlassCardModifier: ViewModifier {
    let cornerRadius: CGFloat
    let fillOpacity: Double
    let borderOpacity: Double
    let shadowOpacity: Double

    @Environment(\.colorScheme) private var colorScheme

    func body(content: Content) -> some View {
        let effectiveFill = colorScheme == .dark ? min(fillOpacity + 0.08, 0.98) : fillOpacity
        let effectiveShadow = colorScheme == .dark ? shadowOpacity * 1.5 : shadowOpacity

        content
            .background(
                RoundedRectangle(cornerRadius: cornerRadius, style: .continuous)
                    .fill(.ultraThinMaterial)
                    .overlay(
                        RoundedRectangle(cornerRadius: cornerRadius, style: .continuous)
                            .fill(PAXTheme.surface.opacity(effectiveFill))
                    )
            )
            .overlay(
                RoundedRectangle(cornerRadius: cornerRadius, style: .continuous)
                    .stroke(PAXTheme.border.opacity(borderOpacity), lineWidth: 1)
            )
            .shadow(color: .black.opacity(effectiveShadow), radius: 16, x: 0, y: 10)
    }
}

extension View {
    func paxGlassSurface() -> some View {
        modifier(PAXGlassSurface())
    }

    func paxGlassCardStyle(
        cornerRadius: CGFloat = 14,
        fillOpacity: Double = 0.82,
        borderOpacity: Double = 0.45,
        shadowOpacity: Double = 0.2
    ) -> some View {
        modifier(
            PAXGlassCardModifier(
                cornerRadius: cornerRadius,
                fillOpacity: fillOpacity,
                borderOpacity: borderOpacity,
                shadowOpacity: shadowOpacity
            )
        )
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
