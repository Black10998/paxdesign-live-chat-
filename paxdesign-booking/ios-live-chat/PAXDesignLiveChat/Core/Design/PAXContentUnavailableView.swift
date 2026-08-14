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
        VStack(spacing: 16) {
            ZStack {
                Circle()
                    .fill(PAXTheme.surfaceElevated)
                    .frame(width: 64, height: 64)
                    .overlay(Circle().strokeBorder(PAXTheme.divider, lineWidth: 1))
                PAXIcon(systemImage, size: .display, emphasis: .secondary)
            }
            Text(title)
                .font(PAXTypography.subsection)
                .foregroundStyle(PAXTheme.textPrimary)
                .multilineTextAlignment(.center)
            description
                .font(PAXTypography.body)
                .foregroundStyle(PAXTheme.textSecondary)
                .multilineTextAlignment(.center)
        }
        .padding(28)
        .frame(maxWidth: .infinity, maxHeight: .infinity)
    }
}
