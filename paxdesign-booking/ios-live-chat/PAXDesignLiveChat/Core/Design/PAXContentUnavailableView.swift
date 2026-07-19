import SwiftUI

struct PAXContentUnavailableView: View {
    let title: String
    let systemImage: String
    let description: Text

    init(_ title: String, systemImage: String, description: Text) {
        self.title = title
        self.systemImage = systemImage
        self.description = description
    }

    var body: some View {
        VStack(spacing: 14) {
            PAXIcon(systemImage, size: .display, emphasis: .secondary)
            Text(title)
                .font(.headline)
                .foregroundStyle(PAXTheme.textPrimary)
            description
                .font(.subheadline)
                .foregroundStyle(PAXTheme.textSecondary)
                .multilineTextAlignment(.center)
        }
        .padding(24)
        .frame(maxWidth: .infinity, maxHeight: .infinity)
    }
}
