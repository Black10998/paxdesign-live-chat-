import SwiftUI

enum PAXMessageStyle {
    static let bubbleRadius: CGFloat = 18
    static let bubblePaddingH: CGFloat = 14
    static let bubblePaddingV: CGFloat = 10
    static let bubblePaddingBottom: CGFloat = 10
    static let rowSpacing: CGFloat = 10
    static let threadSpacing: CGFloat = 2
    static let maxBubbleWidthRatio: CGFloat = 0.78
    static let imageMaxWidth: CGFloat = 220
    static let imageMaxHeight: CGFloat = 210
    static let imageCornerRadius: CGFloat = 16
    static let quoteHeight: CGFloat = 44
    static let tailWidth: CGFloat = 0
    static let tailHeight: CGFloat = 0
    static let outgoingTailHeight: CGFloat = 0
    static let outgoingTailWidth: CGFloat = 0
    static let bubbleFontSize: CGFloat = 15
    static let bubbleLineSpacing: CGFloat = 1.35

    static var incomingFill: Color { PAXTheme.surfaceElevated }
    static var outgoingGradient: LinearGradient { PAXBrandGradient.linear }

    static func bubbleColor(role: String, isOutgoing: Bool, palette: PAXThemePalette) -> Color {
        if isOutgoing { return PAXTheme.accent }
        switch role {
        case "system": return PAXTheme.accentSoft
        default: return incomingFill
        }
    }

    static func bubbleTextColor(isOutgoing: Bool) -> Color {
        isOutgoing ? PAXTheme.onAccent : PAXTheme.textPrimary
    }
}
