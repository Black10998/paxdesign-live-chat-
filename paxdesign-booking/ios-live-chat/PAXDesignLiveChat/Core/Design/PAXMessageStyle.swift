import SwiftUI

enum PAXMessageStyle {
    static let bubbleRadius: CGFloat = 18
    static let bubblePaddingH: CGFloat = 12
    static let bubblePaddingV: CGFloat = 8
    static let rowSpacing: CGFloat = 4
    static let threadSpacing: CGFloat = 2
    static let maxBubbleWidthRatio: CGFloat = 0.76
    static let imageMaxHeight: CGFloat = 220
    static let imageCornerRadius: CGFloat = 14
    static let quoteHeight: CGFloat = 44

    static func bubbleColor(role: String, isOutgoing: Bool) -> Color {
        if isOutgoing { return PAXTheme.adminBubble }
        switch role {
        case "user": return PAXTheme.userBubble
        case "system": return PAXTheme.systemBubble
        default: return PAXTheme.userBubble
        }
    }
}
