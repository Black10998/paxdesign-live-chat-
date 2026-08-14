import SwiftUI

enum PAXMessageStyle {
    static let bubbleRadius: CGFloat = 10
    static let bubblePaddingH: CGFloat = 10
    static let bubblePaddingV: CGFloat = 6
    static let bubblePaddingBottom: CGFloat = 7
    static let rowSpacing: CGFloat = 8
    static let threadSpacing: CGFloat = 1
    static let maxBubbleWidthRatio: CGFloat = 0.74
    static let imageMaxWidth: CGFloat = 210
    static let imageMaxHeight: CGFloat = 200
    static let imageCornerRadius: CGFloat = 12
    static let quoteHeight: CGFloat = 40
    static let tailWidth: CGFloat = 7
    static let tailHeight: CGFloat = 6
    static let outgoingTailHeight: CGFloat = 4
    static let outgoingTailWidth: CGFloat = 5
    static let bubbleFontSize: CGFloat = 11
    static let bubbleLineSpacing: CGFloat = 1.4

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
            return PAXRevolutColors.surface2(isDark: isDark)
        case "system":
            return palette.accent.opacity(isDark ? 0.18 : 0.12)
        default:
            return PAXRevolutColors.surface2(isDark: isDark)
        }
    }

    static func bubbleTextColor(isOutgoing: Bool, isDark: Bool = PAXTheme.cachedIsDark) -> Color {
        if isOutgoing { return .white }
        return PAXRevolutColors.textPrimary(isDark: isDark)
    }
}
