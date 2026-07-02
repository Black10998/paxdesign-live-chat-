import SwiftUI
import UIKit

struct PAXThemePalette: Equatable {
    let accent: Color
    let accentSecondary: Color
    let success: Color
    let danger: Color
    let adminBubble: Color
    let glowPrimary: Color
    let glowSecondary: Color
    let glowTertiary: Color
    let usesGlass: Bool

    let backgroundLight: UIColor
    let backgroundDark: UIColor
    let surfaceLight: UIColor
    let surfaceDark: UIColor
    let surfaceElevatedLight: UIColor
    let surfaceElevatedDark: UIColor

    func background(isDark: Bool) -> Color {
        Color(isDark ? backgroundDark : backgroundLight)
    }

    func surface(isDark: Bool) -> Color {
        Color(isDark ? surfaceDark : surfaceLight)
    }

    func surfaceElevated(isDark: Bool) -> Color {
        Color(isDark ? surfaceElevatedDark : surfaceElevatedLight)
    }

    static func palette(for theme: AppSettingsStore.VisualTheme) -> PAXThemePalette {
        switch theme {
        case .classic:
            return PAXThemePalette(
                accent: Color(red: 1.0, green: 0.55, blue: 0.0),
                accentSecondary: Color(red: 0.12, green: 0.45, blue: 0.95),
                success: Color(red: 0.20, green: 0.78, blue: 0.45),
                danger: Color(red: 0.95, green: 0.30, blue: 0.28),
                adminBubble: Color(red: 0.12, green: 0.45, blue: 0.95),
                glowPrimary: Color(red: 1.0, green: 0.55, blue: 0.0),
                glowSecondary: Color.blue,
                glowTertiary: Color.purple.opacity(0.35),
                usesGlass: true,
                backgroundLight: UIColor(red: 0.96, green: 0.97, blue: 0.98, alpha: 1),
                backgroundDark: UIColor(red: 0.06, green: 0.07, blue: 0.09, alpha: 1),
                surfaceLight: UIColor(red: 1.0, green: 1.0, blue: 1.0, alpha: 1),
                surfaceDark: UIColor(red: 0.10, green: 0.11, blue: 0.14, alpha: 1),
                surfaceElevatedLight: UIColor(red: 0.94, green: 0.95, blue: 0.97, alpha: 1),
                surfaceElevatedDark: UIColor(red: 0.14, green: 0.15, blue: 0.19, alpha: 1)
            )
        case .aurora:
            return PAXThemePalette(
                accent: Color(red: 0.55, green: 0.35, blue: 0.98),
                accentSecondary: Color(red: 0.20, green: 0.82, blue: 0.78),
                success: Color(red: 0.28, green: 0.82, blue: 0.62),
                danger: Color(red: 0.95, green: 0.35, blue: 0.45),
                adminBubble: Color(red: 0.45, green: 0.32, blue: 0.92),
                glowPrimary: Color(red: 0.55, green: 0.35, blue: 0.98),
                glowSecondary: Color(red: 0.20, green: 0.82, blue: 0.78),
                glowTertiary: Color(red: 0.95, green: 0.45, blue: 0.75),
                usesGlass: true,
                backgroundLight: UIColor(red: 0.95, green: 0.96, blue: 0.99, alpha: 1),
                backgroundDark: UIColor(red: 0.05, green: 0.06, blue: 0.12, alpha: 1),
                surfaceLight: UIColor(red: 1.0, green: 1.0, blue: 1.0, alpha: 0.92),
                surfaceDark: UIColor(red: 0.11, green: 0.12, blue: 0.20, alpha: 0.94),
                surfaceElevatedLight: UIColor(red: 0.93, green: 0.94, blue: 0.99, alpha: 1),
                surfaceElevatedDark: UIColor(red: 0.15, green: 0.16, blue: 0.26, alpha: 1)
            )
        case .midnight:
            return PAXThemePalette(
                accent: Color(red: 0.35, green: 0.62, blue: 1.0),
                accentSecondary: Color(red: 0.55, green: 0.45, blue: 0.95),
                success: Color(red: 0.30, green: 0.78, blue: 0.55),
                danger: Color(red: 0.92, green: 0.38, blue: 0.38),
                adminBubble: Color(red: 0.28, green: 0.52, blue: 0.92),
                glowPrimary: Color(red: 0.25, green: 0.45, blue: 0.95),
                glowSecondary: Color(red: 0.55, green: 0.35, blue: 0.88),
                glowTertiary: Color(red: 0.15, green: 0.75, blue: 0.95),
                usesGlass: true,
                backgroundLight: UIColor(red: 0.94, green: 0.96, blue: 0.99, alpha: 1),
                backgroundDark: UIColor(red: 0.04, green: 0.05, blue: 0.10, alpha: 1),
                surfaceLight: UIColor(red: 1.0, green: 1.0, blue: 1.0, alpha: 1),
                surfaceDark: UIColor(red: 0.09, green: 0.11, blue: 0.18, alpha: 1),
                surfaceElevatedLight: UIColor(red: 0.92, green: 0.94, blue: 0.98, alpha: 1),
                surfaceElevatedDark: UIColor(red: 0.13, green: 0.15, blue: 0.24, alpha: 1)
            )
        case .ocean:
            return PAXThemePalette(
                accent: Color(red: 0.12, green: 0.72, blue: 0.82),
                accentSecondary: Color(red: 0.20, green: 0.55, blue: 0.95),
                success: Color(red: 0.22, green: 0.78, blue: 0.58),
                danger: Color(red: 0.94, green: 0.40, blue: 0.35),
                adminBubble: Color(red: 0.15, green: 0.58, blue: 0.88),
                glowPrimary: Color(red: 0.12, green: 0.72, blue: 0.82),
                glowSecondary: Color(red: 0.20, green: 0.55, blue: 0.95),
                glowTertiary: Color(red: 0.35, green: 0.88, blue: 0.75),
                usesGlass: true,
                backgroundLight: UIColor(red: 0.94, green: 0.98, blue: 0.99, alpha: 1),
                backgroundDark: UIColor(red: 0.04, green: 0.08, blue: 0.10, alpha: 1),
                surfaceLight: UIColor(red: 1.0, green: 1.0, blue: 1.0, alpha: 1),
                surfaceDark: UIColor(red: 0.08, green: 0.12, blue: 0.15, alpha: 1),
                surfaceElevatedLight: UIColor(red: 0.90, green: 0.96, blue: 0.98, alpha: 1),
                surfaceElevatedDark: UIColor(red: 0.12, green: 0.17, blue: 0.21, alpha: 1)
            )
        case .rosegold:
            return PAXThemePalette(
                accent: Color(red: 0.92, green: 0.55, blue: 0.48),
                accentSecondary: Color(red: 0.85, green: 0.68, blue: 0.42),
                success: Color(red: 0.35, green: 0.75, blue: 0.52),
                danger: Color(red: 0.90, green: 0.32, blue: 0.35),
                adminBubble: Color(red: 0.78, green: 0.45, blue: 0.55),
                glowPrimary: Color(red: 0.92, green: 0.55, blue: 0.48),
                glowSecondary: Color(red: 0.85, green: 0.68, blue: 0.42),
                glowTertiary: Color(red: 0.95, green: 0.75, blue: 0.82),
                usesGlass: true,
                backgroundLight: UIColor(red: 0.99, green: 0.96, blue: 0.95, alpha: 1),
                backgroundDark: UIColor(red: 0.10, green: 0.07, blue: 0.08, alpha: 1),
                surfaceLight: UIColor(red: 1.0, green: 1.0, blue: 1.0, alpha: 1),
                surfaceDark: UIColor(red: 0.14, green: 0.11, blue: 0.12, alpha: 1),
                surfaceElevatedLight: UIColor(red: 0.97, green: 0.93, blue: 0.92, alpha: 1),
                surfaceElevatedDark: UIColor(red: 0.18, green: 0.14, blue: 0.15, alpha: 1)
            )
        case .forest:
            return PAXThemePalette(
                accent: Color(red: 0.28, green: 0.72, blue: 0.42),
                accentSecondary: Color(red: 0.45, green: 0.62, blue: 0.35),
                success: Color(red: 0.32, green: 0.78, blue: 0.48),
                danger: Color(red: 0.90, green: 0.38, blue: 0.30),
                adminBubble: Color(red: 0.22, green: 0.58, blue: 0.45),
                glowPrimary: Color(red: 0.28, green: 0.72, blue: 0.42),
                glowSecondary: Color(red: 0.45, green: 0.62, blue: 0.35),
                glowTertiary: Color(red: 0.55, green: 0.82, blue: 0.55),
                usesGlass: true,
                backgroundLight: UIColor(red: 0.95, green: 0.98, blue: 0.95, alpha: 1),
                backgroundDark: UIColor(red: 0.05, green: 0.08, blue: 0.06, alpha: 1),
                surfaceLight: UIColor(red: 1.0, green: 1.0, blue: 1.0, alpha: 1),
                surfaceDark: UIColor(red: 0.09, green: 0.12, blue: 0.10, alpha: 1),
                surfaceElevatedLight: UIColor(red: 0.92, green: 0.96, blue: 0.93, alpha: 1),
                surfaceElevatedDark: UIColor(red: 0.13, green: 0.17, blue: 0.14, alpha: 1)
            )
        }
    }
}

private struct PAXThemePaletteKey: EnvironmentKey {
    static let defaultValue = PAXThemePalette.palette(for: .classic)
}

extension EnvironmentValues {
    var paxPalette: PAXThemePalette {
        get { self[PAXThemePaletteKey.self] }
        set { self[PAXThemePaletteKey.self] = newValue }
    }
}
