import SwiftUI

/// Official GitHub Invertocat, rendered as a template so Light/Dark login buttons invert correctly.
struct GitHubMarkIcon: View {
    var size: CGFloat = 20

    var body: some View {
        Image("GitHubMark")
            .renderingMode(.template)
            .resizable()
            .scaledToFit()
            .frame(width: size, height: size)
            .accessibilityHidden(true)
    }
}

struct PAXContinueWithGitHubButton: View {
    @ObservedObject private var settings = AppSettingsStore.shared
    @Environment(\.colorScheme) private var colorScheme
    var isLoading = false
    let action: () -> Void

    private var isDark: Bool { settings.resolvedIsDark(for: colorScheme) }

    var body: some View {
        Button(action: action) {
            HStack(spacing: 10) {
                if isLoading {
                    ProgressView()
                        .progressViewStyle(.circular)
                        .tint(isDark ? .black : .white)
                } else {
                    GitHubMarkIcon(size: 20)
                    Text(String(localized: "Continue with GitHub"))
                        .font(PAXTypography.button)
                }
            }
            .foregroundStyle(isDark ? Color.black : Color.white)
            .frame(maxWidth: .infinity, minHeight: PAXSpacing.primaryButtonHeight)
            .background(isDark ? Color.white : Color.black)
            .clipShape(RoundedRectangle(cornerRadius: 16, style: .continuous))
            .overlay(
                RoundedRectangle(cornerRadius: 16, style: .continuous)
                    .stroke(isDark ? Color.black.opacity(0.08) : Color.white.opacity(0.12), lineWidth: 1)
            )
        }
        .buttonStyle(PAXRevolutPressableStyle())
        .disabled(isLoading)
        .opacity(isLoading ? 0.7 : 1)
        .accessibilityLabel(String(localized: "Continue with GitHub"))
    }
}
