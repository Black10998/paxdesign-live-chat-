import SwiftUI

/// Subtle verified indicator for accepted team conversations.
struct TeamVerifiedBadge: View {
    var size: CGFloat = 15

    var body: some View {
        PAXIcon("checkmark.seal.fill", size: .inline, tint: .green)
            .accessibilityLabel(L10n.TeamVerifiedConversation)
    }
}
