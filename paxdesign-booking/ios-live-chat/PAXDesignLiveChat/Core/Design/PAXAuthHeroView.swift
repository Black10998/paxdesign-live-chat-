import SwiftUI

/// Branded hero header for login, registration, and onboarding screens.
struct PAXAuthHeroView: View {
    enum Style {
        case animatedLogo
        case icon(String)
    }

    let style: Style
    let title: String
    let subtitle: String
    var markWidth: CGFloat = 128
    var showsTitle: Bool = true

    @ObservedObject private var settings = AppSettingsStore.shared
    @Environment(\.colorScheme) private var colorScheme

    private var isDark: Bool { settings.resolvedIsDark(for: colorScheme) }

    var body: some View {
        VStack(spacing: 18) {
            heroMark
            VStack(spacing: 8) {
                if showsTitle {
                    Text(title)
                        .font(PAXTypography.titleLarge)
                        .foregroundStyle(PAXTheme.textPrimary)
                        .multilineTextAlignment(.center)
                }
                Text(subtitle)
                    .font(PAXTypography.body)
                    .foregroundStyle(PAXTheme.textSecondary)
                    .multilineTextAlignment(.center)
                    .fixedSize(horizontal: false, vertical: true)
            }
        }
        .frame(maxWidth: .infinity)
        .accessibilityElement(children: .combine)
        .id(settings.themeRevision)
    }

    @ViewBuilder
    private var heroMark: some View {
        switch style {
        case .animatedLogo:
            PAXAnimatedLogoView(markWidth: markWidth)
                .frame(maxWidth: .infinity)
                .padding(.vertical, 4)
        case .icon(let name):
            PAXRevolutGlyphAvatar(systemImage: name, size: 88, tint: PAXTheme.accent)
                .shadow(color: PAXBrandGradient.glow, radius: isDark ? 16 : 8, y: 6)
                .accessibilityHidden(true)
        }
    }
}

struct PAXOnboardingIllustration: View {
    let systemImage: String
    var tint: Color = PAXTheme.accent

    var body: some View {
        PAXRevolutGlyphAvatar(systemImage: systemImage, size: 88, tint: tint)
            .shadow(color: tint.opacity(0.22), radius: 16, y: 8)
            .accessibilityHidden(true)
    }
}
