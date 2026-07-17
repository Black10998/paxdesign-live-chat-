import SwiftUI

enum PAXBrand {
    /// PAXDesign brand accent — lime green (#CCFF00 family).
    static let accent = Color(red: 204 / 255, green: 1, blue: 0)
    /// Splash screen background — pure black per brand spec.
    static let launchBackground = Color.black

    /// Branded splash duration before transitioning to the shell (3–4 seconds).
    static let launchDuration: TimeInterval = 3.5
}
