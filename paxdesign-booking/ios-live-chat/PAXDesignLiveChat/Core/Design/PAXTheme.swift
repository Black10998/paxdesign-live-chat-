import SwiftUI
import UIKit

enum PAXTheme {
    private static var palette: PAXThemePalette {
        AppSettingsStore.shared.palette
    }

    static var accent: Color { palette.accent }
    static var success: Color { palette.success }
    static var danger: Color { palette.danger }
    static var adminBubble: Color { palette.adminBubble }

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

    static var background: Color {
        adaptivePalette { palette, isDark in palette.background(isDark: isDark) }
    }

    static var surface: Color {
        adaptivePalette { palette, isDark in palette.surface(isDark: isDark) }
    }

    static var surfaceElevated: Color {
        adaptivePalette { palette, isDark in palette.surfaceElevated(isDark: isDark) }
    }

    static var systemBubble: Color { accent.opacity(0.18) }
    static var accentSoft: Color { accent.opacity(0.16) }

    static let spring = Animation.spring(response: 0.42, dampingFraction: 0.82)
    static let quickSpring = Animation.spring(response: 0.32, dampingFraction: 0.78)
    static let fade = Animation.easeInOut(duration: 0.28)

    private static func adaptive(light: UIColor, dark: UIColor) -> Color {
        Color(UIColor { traits in
            traits.userInterfaceStyle == .dark ? dark : light
        })
    }

    private static func adaptivePalette(_ builder: (PAXThemePalette, Bool) -> Color) -> Color {
        let currentPalette = palette
        return Color(UIColor { traits in
            let isDark = traits.userInterfaceStyle == .dark
            return UIColor(builder(currentPalette, isDark))
        })
    }
}

struct PAXBackground: View {
    @Environment(\.colorScheme) private var colorScheme
    @ObservedObject private var settings = AppSettingsStore.shared

    var body: some View {
        let palette = settings.palette
        let isDark = colorScheme == .dark

        ZStack {
            palette.background(isDark: isDark)

            RadialGradient(
                colors: [palette.glowPrimary.opacity(isDark ? 0.18 : 0.14), .clear],
                center: .topLeading,
                startRadius: 24,
                endRadius: 480
            )

            RadialGradient(
                colors: [palette.glowSecondary.opacity(isDark ? 0.14 : 0.10), .clear],
                center: .bottomTrailing,
                startRadius: 16,
                endRadius: 420
            )

            RadialGradient(
                colors: [palette.glowTertiary.opacity(isDark ? 0.10 : 0.06), .clear],
                center: .center,
                startRadius: 40,
                endRadius: 520
            )

            if palette.usesGlass {
                LinearGradient(
                    colors: [
                        palette.accent.opacity(isDark ? 0.04 : 0.03),
                        .clear,
                        palette.accentSecondary.opacity(isDark ? 0.05 : 0.04)
                    ],
                    startPoint: .topLeading,
                    endPoint: .bottomTrailing
                )
            }
        }
        .ignoresSafeArea()
        .animation(PAXTheme.fade, value: settings.visualTheme)
    }
}

struct PAXGlassSurface: ViewModifier {
    @Environment(\.colorScheme) private var colorScheme
    @ObservedObject private var settings = AppSettingsStore.shared

    func body(content: Content) -> some View {
        let palette = settings.palette
        let isDark = colorScheme == .dark

        content
            .background {
                if palette.usesGlass {
                    RoundedRectangle(cornerRadius: 12, style: .continuous)
                        .fill(.ultraThinMaterial)
                        .overlay(
                            RoundedRectangle(cornerRadius: 12, style: .continuous)
                                .stroke(palette.accent.opacity(isDark ? 0.12 : 0.08), lineWidth: 0.5)
                        )
                } else {
                    RoundedRectangle(cornerRadius: 12, style: .continuous)
                        .fill(palette.surface(isDark: isDark))
                }
            }
    }
}

extension View {
    func paxGlassSurface() -> some View {
        modifier(PAXGlassSurface())
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
