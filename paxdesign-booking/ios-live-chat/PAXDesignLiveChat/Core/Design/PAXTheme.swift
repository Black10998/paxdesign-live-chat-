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

    static let spring = Animation.easeInOut(duration: 0.22)
    static let quickSpring = Animation.easeOut(duration: 0.16)
    static let fade = Animation.easeInOut(duration: 0.2)
}

enum PAXGlassTier {
    case subtle
    case standard
    case premium
    case hero

    var material: Material {
        switch self {
        case .subtle: .ultraThinMaterial
        case .standard: .thinMaterial
        case .premium: .regularMaterial
        case .hero: .thickMaterial
        }
    }

    func fillOpacity(for scheme: ColorScheme) -> Double {
        let base: Double
        switch self {
        case .subtle: base = 0.52
        case .standard: base = 0.62
        case .premium: base = 0.72
        case .hero: base = 0.78
        }
        return scheme == .dark ? min(base + 0.1, 0.92) : base
    }

    func borderOpacity(for scheme: ColorScheme) -> Double {
        scheme == .dark ? 0.58 : 0.48
    }

    func shadowOpacity(for scheme: ColorScheme) -> Double {
        let base: Double
        switch self {
        case .subtle: base = 0.12
        case .standard: base = 0.18
        case .premium: base = 0.24
        case .hero: base = 0.3
        }
        return scheme == .dark ? base * 1.6 : base
    }
}

struct PAXBackground: View {
    @Environment(\.colorScheme) private var colorScheme

    var body: some View {
        ZStack {
            LinearGradient(
                colors: [
                    PAXTheme.background,
                    PAXTheme.background.opacity(0.92),
                    PAXTheme.surface.opacity(0.78)
                ],
                startPoint: .topLeading,
                endPoint: .bottomTrailing
            )

            RadialGradient(
                colors: [PAXTheme.accent.opacity(colorScheme == .dark ? 0.24 : 0.16), .clear],
                center: .topTrailing,
                startRadius: 16,
                endRadius: 420
            )
            .blendMode(.plusLighter)

            RadialGradient(
                colors: [PAXTheme.success.opacity(0.12), .clear],
                center: .bottomLeading,
                startRadius: 24,
                endRadius: 360
            )
            .blendMode(.plusLighter)

            LinearGradient(
                colors: [
                    Color.white.opacity(colorScheme == .dark ? 0.04 : 0.14),
                    .clear,
                    Color.white.opacity(colorScheme == .dark ? 0.02 : 0.06)
                ],
                startPoint: .top,
                endPoint: .bottom
            )
            .blendMode(.overlay)
        }
        .ignoresSafeArea()
    }
}

struct PAXGlassSurface: ViewModifier {
    func body(content: Content) -> some View {
        content.paxPremiumGlass(tier: .standard, cornerRadius: 14)
    }
}

private struct PAXPremiumGlassModifier: ViewModifier {
    let tier: PAXGlassTier
    let cornerRadius: CGFloat
    let accent: Color?

    @Environment(\.colorScheme) private var colorScheme

    func body(content: Content) -> some View {
        let fill = tier.fillOpacity(for: colorScheme)
        let border = tier.borderOpacity(for: colorScheme)
        let shadow = tier.shadowOpacity(for: colorScheme)
        let accentColor = accent ?? PAXTheme.accent

        content
            .background(
                ZStack {
                    RoundedRectangle(cornerRadius: cornerRadius, style: .continuous)
                        .fill(tier.material)

                    RoundedRectangle(cornerRadius: cornerRadius, style: .continuous)
                        .fill(
                            LinearGradient(
                                colors: [
                                    PAXTheme.surface.opacity(fill + 0.08),
                                    PAXTheme.surface.opacity(fill),
                                    PAXTheme.surface.opacity(fill - 0.06)
                                ],
                                startPoint: .topLeading,
                                endPoint: .bottomTrailing
                            )
                        )

                    RoundedRectangle(cornerRadius: cornerRadius, style: .continuous)
                        .fill(
                            LinearGradient(
                                colors: [
                                    Color.white.opacity(colorScheme == .dark ? 0.16 : 0.34),
                                    Color.white.opacity(colorScheme == .dark ? 0.04 : 0.08),
                                    .clear
                                ],
                                startPoint: .top,
                                endPoint: .center
                            )
                        )
                        .blendMode(.overlay)

                    RoundedRectangle(cornerRadius: cornerRadius, style: .continuous)
                        .stroke(
                            LinearGradient(
                                colors: [
                                    accentColor.opacity(colorScheme == .dark ? 0.28 : 0.18),
                                    PAXTheme.border.opacity(border),
                                    accentColor.opacity(0.08)
                                ],
                                startPoint: .topLeading,
                                endPoint: .bottomTrailing
                            ),
                            lineWidth: 1
                        )
                }
            )
            .shadow(color: .black.opacity(shadow), radius: tier == .hero ? 22 : 16, x: 0, y: tier == .hero ? 14 : 10)
            .shadow(color: accentColor.opacity(colorScheme == .dark ? 0.12 : 0.06), radius: 24, x: 0, y: 8)
    }
}

private struct PAXGlassCardModifier: ViewModifier {
    let cornerRadius: CGFloat
    let fillOpacity: Double
    let borderOpacity: Double
    let shadowOpacity: Double

    func body(content: Content) -> some View {
        content.paxPremiumGlass(tier: .standard, cornerRadius: cornerRadius)
    }
}

extension View {
    func paxGlassSurface() -> some View {
        modifier(PAXGlassSurface())
    }

    func paxPremiumGlass(
        tier: PAXGlassTier = .standard,
        cornerRadius: CGFloat = 16,
        accent: Color? = nil
    ) -> some View {
        modifier(PAXPremiumGlassModifier(tier: tier, cornerRadius: cornerRadius, accent: accent))
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
