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

    @Environment(\.colorScheme) private var colorScheme

    private var accent: Color {
        PAXBrand.appearanceAccent(isDark: colorScheme == .dark)
    }

    var body: some View {
        VStack(spacing: 18) {
            heroMark
            VStack(spacing: 8) {
                Text(title)
                    .font(.title2.weight(.semibold))
                    .multilineTextAlignment(.center)
                Text(subtitle)
                    .font(.subheadline)
                    .foregroundStyle(.secondary)
                    .multilineTextAlignment(.center)
                    .fixedSize(horizontal: false, vertical: true)
            }
        }
        .frame(maxWidth: .infinity)
        .accessibilityElement(children: .combine)
    }

    @ViewBuilder
    private var heroMark: some View {
        switch style {
        case .animatedLogo:
            PAXAnimatedLogoView(markWidth: markWidth)
                .frame(maxWidth: .infinity)
                .padding(.vertical, 4)
        case .icon(let name):
            ZStack {
                Circle()
                    .fill(
                        RadialGradient(
                            colors: [accent.opacity(colorScheme == .dark ? 0.28 : 0.18), .clear],
                            center: .center,
                            startRadius: 8,
                            endRadius: 56
                        )
                    )
                    .frame(width: 112, height: 112)
                Circle()
                    .strokeBorder(accent.opacity(0.35), lineWidth: 1.2)
                    .background(Circle().fill(.ultraThinMaterial))
                    .frame(width: 88, height: 88)
                PAXIcon(name, size: .display)
                    .foregroundStyle(accent)
            }
            .accessibilityHidden(true)
        }
    }
}

struct PAXOnboardingIllustration: View {
    let systemImage: String
    var tint: Color = PAXTheme.accent

    var body: some View {
        PAXIcon(systemImage, size: .display)
            .foregroundStyle(tint)
            .frame(width: 72, height: 72)
            .accessibilityHidden(true)
    }
}
