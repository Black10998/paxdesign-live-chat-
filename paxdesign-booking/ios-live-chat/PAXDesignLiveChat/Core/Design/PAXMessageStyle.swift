import SwiftUI

enum PAXMessageStyle {
    static let bubbleRadius: CGFloat = 16
    static let bubblePaddingH: CGFloat = 14
    static let bubblePaddingV: CGFloat = 10
    static let bubblePaddingBottom: CGFloat = 10
    static let rowSpacing: CGFloat = 10
    static let threadSpacing: CGFloat = 4
    static let maxBubbleWidthRatio: CGFloat = 0.78
    static let imageMaxWidth: CGFloat = 210
    static let imageMaxHeight: CGFloat = 200
    static let imageCornerRadius: CGFloat = 14
    static let quoteHeight: CGFloat = 40
    static let tailWidth: CGFloat = 0
    static let tailHeight: CGFloat = 0
    static let outgoingTailHeight: CGFloat = 0
    static let outgoingTailWidth: CGFloat = 0
    static let bubbleFontSize: CGFloat = 15
    static let bubbleLineSpacing: CGFloat = 2

    static let incomingFill = Color.black.opacity(0.3)
    static let outgoingGradient = LinearGradient(
        colors: [
            Color(red: 36 / 255, green: 138 / 255, blue: 82 / 255),
            Color(red: 37 / 255, green: 114 / 255, blue: 135 / 255),
        ],
        startPoint: .topLeading,
        endPoint: .bottomTrailing
    )

    static func bubbleColor(role: String, isOutgoing: Bool, palette: PAXThemePalette, isDark: Bool = PAXTheme.cachedIsDark) -> Color {
        if isOutgoing { return palette.adminBubble }
        switch role {
        case "user":
            return PAXRevolutColors.surface1(isDark: isDark)
        case "system":
            return palette.accent.opacity(isDark ? 0.16 : 0.10)
        default:
            return PAXRevolutColors.surface1(isDark: isDark)
        }
    }

    static func bubbleTextColor(isOutgoing: Bool, isDark: Bool = PAXTheme.cachedIsDark) -> Color {
        if isOutgoing { return .white }
        return PAXRevolutColors.textPrimary(isDark: isDark)
    }
}
