import SwiftUI

enum PAXMessageStyle {
    static let bubbleRadius: CGFloat = 17
    static let bubblePaddingH: CGFloat = 11
    static let bubblePaddingV: CGFloat = 7
    static let rowSpacing: CGFloat = 3
    static let threadSpacing: CGFloat = 1
    static let maxBubbleWidthRatio: CGFloat = 0.74
    static let imageMaxWidth: CGFloat = 210
    static let imageMaxHeight: CGFloat = 200
    static let imageCornerRadius: CGFloat = 12
    static let quoteHeight: CGFloat = 40
    static let tailWidth: CGFloat = 6
    static let tailHeight: CGFloat = 10

    static func bubbleColor(role: String, isOutgoing: Bool, palette: PAXThemePalette) -> Color {
        if isOutgoing { return palette.adminBubble }
        switch role {
        case "user": return PAXTheme.userBubble
        case "system": return palette.accent.opacity(0.14)
        default: return PAXTheme.userBubble
        }
    }
}
