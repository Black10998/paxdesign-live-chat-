import SwiftUI

/// Subtle verified indicator for accepted team conversations.
struct TeamVerifiedBadge: View {
    var size: CGFloat = 15

    var body: some View {
        Image(systemName: "checkmark.seal.fill")
            .symbolRenderingMode(.palette)
            .foregroundStyle(Color.green, Color.green.opacity(0.22))
            .font(.system(size: size, weight: .semibold))
            .accessibilityLabel(L10n.TeamVerifiedConversation)
    }
}
