import SwiftUI

enum PAXBrand {
    /// Official PAXDesign brand accent (#C2FF00).
    static let accent = Color(red: 194 / 255, green: 1, blue: 0)
    /// Splash screen background — pure black per brand spec.
    static let launchBackground = Color.black

    /// Branded splash duration before transitioning to the shell (3–4 seconds).
    static let launchDuration: TimeInterval = 3.5
}
