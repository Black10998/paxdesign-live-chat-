import SwiftUI

enum PAXBrand {
    /// Official PAXDesign brand accent (#C2FF00) — used in Dark Mode.
    static let accent = Color(red: 194 / 255, green: 1, blue: 0)
    /// Default Apple system blue — used in Light Mode.
    static let lightModeAccent = Color(uiColor: .systemBlue)
    /// Splash screen background — pure black per brand spec.
    static let launchBackground = Color.black

    /// Branded splash duration before transitioning to the shell (3–4 seconds).
    static let launchDuration: TimeInterval = 3.5

    static func appearanceAccent(isDark: Bool) -> Color {
        isDark ? accent : lightModeAccent
    }

    static func accentLabelColor(isDark: Bool) -> Color {
        isDark ? .black : .white
    }
}
