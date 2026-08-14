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
        VStack(spacing: PAXSpacing.sm + 2) {
            PAXIcon(systemImage, size: .display, emphasis: .secondary)
            Text(title)
                .font(PAXTypography.subsection)
                .foregroundStyle(PAXTheme.textPrimary)
            description
                .font(PAXTypography.body)
                .foregroundStyle(PAXTheme.textSecondary)
                .multilineTextAlignment(.center)
        }
        .padding(PAXSpacing.xl)
        .frame(maxWidth: .infinity, maxHeight: .infinity)
    }
}
