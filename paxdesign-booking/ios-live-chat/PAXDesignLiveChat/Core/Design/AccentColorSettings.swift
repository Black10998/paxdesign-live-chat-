import SwiftUI

enum AccentColorPreset: String, CaseIterable, Identifiable {
    case themeDefault
    case orange
    case blue
    case purple
    case teal
    case pink
    case green
    case red

    var id: String { rawValue }

    var title: String {
        switch self {
        case .themeDefault: return L10n.AccentThemeDefault
        case .orange: return L10n.AccentOrange
        case .blue: return L10n.AccentBlue
        case .purple: return L10n.AccentPurple
        case .teal: return L10n.AccentTeal
        case .pink: return L10n.AccentPink
        case .green: return L10n.AccentGreen
        case .red: return L10n.AccentRed
        }
    }

    var color: Color? {
        switch self {
        case .themeDefault: return nil
        case .orange: return Color(red: 1.0, green: 0.55, blue: 0.0)
        case .blue: return Color(red: 0.12, green: 0.45, blue: 0.95)
        case .purple: return Color(red: 0.55, green: 0.35, blue: 0.98)
        case .teal: return Color(red: 0.20, green: 0.72, blue: 0.68)
        case .pink: return Color(red: 0.95, green: 0.35, blue: 0.58)
        case .green: return Color(red: 0.20, green: 0.78, blue: 0.45)
        case .red: return Color(red: 0.95, green: 0.30, blue: 0.28)
        }
    }
}

extension AppSettingsStore {
    /// Base theme palette without accent override (must not call `palette` — that property applies overrides).
    var basePalette: PAXThemePalette {
        PAXThemePalette.palette(for: visualTheme)
    }

    var effectivePalette: PAXThemePalette {
        let base = basePalette
        guard let override = accentColorPreset.color else { return base }
        return base.withAccent(override)
    }
}

extension PAXThemePalette {
    func withAccent(_ color: Color) -> PAXThemePalette {
        PAXThemePalette(
            accent: color,
            accentSecondary: accentSecondary,
            success: success,
            danger: danger,
            adminBubble: adminBubble,
            glowPrimary: color,
            glowSecondary: glowSecondary,
            glowTertiary: glowTertiary,
            usesGlass: usesGlass,
            backgroundLight: backgroundLight,
            backgroundDark: backgroundDark,
            surfaceLight: surfaceLight,
            surfaceDark: surfaceDark,
            surfaceElevatedLight: surfaceElevatedLight,
            surfaceElevatedDark: surfaceElevatedDark
        )
    }
}
