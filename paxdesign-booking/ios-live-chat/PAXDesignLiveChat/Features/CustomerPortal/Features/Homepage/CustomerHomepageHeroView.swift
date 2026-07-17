import SwiftUI

/// Website-parity hero for the customer homepage (`#pax-isolated-hero`).
struct CustomerHomepageHeroView: View {
    let hero: CustomerHomepageResponse.Hero
    var onPrimaryAction: () -> Void
    var onSecondaryAction: () -> Void

    @Environment(\.horizontalSizeClass) private var horizontalSizeClass
    @Environment(\.dynamicTypeSize) private var dynamicTypeSize

    private let accentDot = Color(red: 194 / 255, green: 1, blue: 0, opacity: 0.72)

    var body: some View {
        VStack(spacing: metrics.contentGap) {
            HeroTagsRow(tags: hero.tags, metrics: metrics, accentDot: accentDot)
            HeroTextStack(hero: hero, metrics: metrics)
            HeroCombinedCTA(
                primaryTitle: hero.cta_primary,
                secondaryAccessibility: hero.cta_secondary,
                metrics: metrics,
                onPrimary: onPrimaryAction,
                onSecondary: onSecondaryAction
            )
        }
        .padding(.horizontal, metrics.horizontalPadding)
        .padding(.top, metrics.topPadding)
        .padding(.bottom, metrics.bottomPadding)
        .frame(maxWidth: metrics.contentMaxWidth)
        .frame(maxWidth: .infinity)
        .background {
            CustomerHomepageHeroBackground(imageURL: hero.image_url)
        }
        .background(Color.black)
        .clipped()
    }

    private var metrics: HeroMetrics {
        let width = UIScreen.main.bounds.width
        return HeroMetrics(width: width, dynamicType: dynamicTypeSize, isRegular: horizontalSizeClass == .regular)
    }
}

// MARK: - Background

private struct CustomerHomepageHeroBackground: View {
    let imageURL: String?

    var body: some View {
        ZStack {
            Color.black

            if let imageURL, let url = URL(string: imageURL) {
                AsyncImage(url: url) { phase in
                    switch phase {
                    case .success(let image):
                        image
                            .resizable()
                            .scaledToFill()
                            .scaleEffect(1.06)
                            .saturation(0.92)
                            .contrast(1.08)
                            .brightness(-0.28)
                    default:
                        Color.black
                    }
                }
            }

            // paxhero-bg-glass
            LinearGradient(
                colors: [
                    Color.white.opacity(0.05),
                    Color.white.opacity(0),
                    Color.black.opacity(0.08),
                ],
                startPoint: .top,
                endPoint: .bottom
            )
            .background(Color(red: 8 / 255, green: 8 / 255, blue: 8 / 255).opacity(0.18))

            // paxhero-bg-shade
            ZStack {
                RadialGradient(
                    colors: [
                        Color(red: 189 / 255, green: 220 / 255, blue: 114 / 255, opacity: 0.10),
                        Color.clear,
                    ],
                    center: UnitPoint(x: 0.5, y: 0.42),
                    startRadius: 0,
                    endRadius: 280
                )
                LinearGradient(
                    colors: [
                        Color.black.opacity(0.34),
                        Color.black.opacity(0.12),
                        Color.black.opacity(0.38),
                    ],
                    startPoint: UnitPoint(x: 0, y: 0.2),
                    endPoint: UnitPoint(x: 1, y: 0.8)
                )
                LinearGradient(
                    colors: [Color.black.opacity(0.18), Color.black.opacity(0.52)],
                    startPoint: .top,
                    endPoint: .bottom
                )
            }
        }
        .clipped()
        .allowsHitTesting(false)
    }
}

// MARK: - Tags

private struct HeroTagsRow: View {
    let tags: [String]
    let metrics: HeroMetrics
    let accentDot: Color

    var body: some View {
        HStack(spacing: 0) {
            ForEach(Array(tags.enumerated()), id: \.offset) { index, tag in
                if index > 0 {
                    Text(" · ")
                        .foregroundStyle(accentDot)
                        .fontWeight(.bold)
                }
                Text(tag.uppercased())
                    .foregroundStyle(Color.white.opacity(0.72))
            }
        }
        .font(.system(size: metrics.tagFontSize, weight: .semibold))
        .tracking(metrics.tagTracking)
        .multilineTextAlignment(.center)
        .frame(maxWidth: metrics.bodyMaxWidth)
        .frame(maxWidth: .infinity, alignment: .center)
        .fixedSize(horizontal: false, vertical: true)
    }
}

// MARK: - Text

private struct HeroTextStack: View {
    let hero: CustomerHomepageResponse.Hero
    let metrics: HeroMetrics

    var body: some View {
        VStack(spacing: metrics.textGap) {
            Text(hero.lead)
                .font(.system(size: metrics.leadFontSize, weight: .bold))
                .tracking(-0.02 * metrics.leadFontSize)
                .foregroundStyle(.white)
                .multilineTextAlignment(.center)
                .fixedSize(horizontal: false, vertical: true)
                .frame(maxWidth: metrics.leadMaxWidth)
                .minimumScaleFactor(0.82)
                .layoutPriority(1)

            Text(hero.mid)
                .font(.system(size: metrics.midFontSize))
                .lineSpacing(metrics.midFontSize * 0.6)
                .foregroundStyle(Color.white.opacity(0.82))
                .multilineTextAlignment(.center)
                .fixedSize(horizontal: false, vertical: true)
                .frame(maxWidth: metrics.bodyMaxWidth)
                .minimumScaleFactor(0.88)

            Text(hero.sub)
                .font(.system(size: metrics.subFontSize))
                .lineSpacing(metrics.subFontSize * 0.6)
                .foregroundStyle(Color.white.opacity(0.62))
                .multilineTextAlignment(.center)
                .fixedSize(horizontal: false, vertical: true)
                .frame(maxWidth: metrics.subMaxWidth)
                .minimumScaleFactor(0.9)
        }
        .frame(maxWidth: .infinity)
    }
}

// MARK: - CTA

private struct HeroCombinedCTA: View {
    let primaryTitle: String
    let secondaryAccessibility: String
    let metrics: HeroMetrics
    var onPrimary: () -> Void
    var onSecondary: () -> Void

    @State private var pulsePhase = false
    @State private var arrowRotation: Double = 0

    var body: some View {
        HStack(spacing: 10) {
            Button(action: onPrimary) {
                Text(primaryTitle.uppercased())
                    .font(.system(size: metrics.ctaTextFontSize, weight: .semibold))
                    .tracking(0.04 * metrics.ctaTextFontSize)
                    .foregroundStyle(Color.white.opacity(0.58))
                    .multilineTextAlignment(.leading)
                    .fixedSize(horizontal: false, vertical: true)
                    .minimumScaleFactor(0.85)
            }
            .buttonStyle(.plain)

            Button(action: onSecondary) {
                HeroArrowButtonIcon(size: metrics.ctaButtonSize, rotation: arrowRotation)
            }
            .buttonStyle(.plain)
            .accessibilityLabel(secondaryAccessibility)
        }
        .padding(.horizontal, metrics.ctaHorizontalPadding)
        .padding(.vertical, metrics.ctaVerticalPadding)
        .background(
            RoundedRectangle(cornerRadius: metrics.ctaCornerRadius, style: .continuous)
                .fill(Color(red: 27 / 255, green: 27 / 255, blue: 27 / 255, opacity: 0.72))
        )
        .overlay(
            RoundedRectangle(cornerRadius: metrics.ctaCornerRadius, style: .continuous)
                .stroke(
                    Color.white.opacity(pulsePhase ? 0.48 : 0.14),
                    lineWidth: 1.5
                )
        )
        .shadow(
            color: Color.white.opacity(pulsePhase ? 0.18 : 0),
            radius: pulsePhase ? 8 : 0
        )
        .clipShape(RoundedRectangle(cornerRadius: metrics.ctaCornerRadius, style: .continuous))
        .onAppear {
            withAnimation(.easeInOut(duration: 2.8).repeatForever(autoreverses: true)) {
                pulsePhase = true
            }
            withAnimation(.linear(duration: 6).repeatForever(autoreverses: false)) {
                arrowRotation = 360
            }
        }
    }
}

private struct HeroArrowButtonIcon: View {
    let size: CGFloat
    let rotation: Double

    var body: some View {
        ZStack {
            RoundedRectangle(cornerRadius: 10, style: .continuous)
                .fill(
                    LinearGradient(
                        colors: [
                            Color(red: 41 / 255, green: 41 / 255, blue: 41 / 255),
                            Color(red: 85 / 255, green: 85 / 255, blue: 85 / 255),
                            Color(red: 41 / 255, green: 41 / 255, blue: 41 / 255),
                        ],
                        startPoint: .bottom,
                        endPoint: .top
                    )
                )
                .padding(2)

            RoundedRectangle(cornerRadius: 8, style: .continuous)
                .fill(Color(red: 15 / 255, green: 15 / 255, blue: 15 / 255))
                .padding(4)

            Image(systemName: "paperplane.fill")
                .font(.system(size: size * 0.42, weight: .semibold))
                .foregroundStyle(Color(red: 139 / 255, green: 139 / 255, blue: 139 / 255))
                .rotationEffect(.degrees(rotation))
        }
        .frame(width: size, height: size)
    }
}

// MARK: - Metrics

private struct HeroMetrics {
    let width: CGFloat
    let dynamicType: DynamicTypeSize
    let isRegular: Bool

    private var typeScale: CGFloat {
        switch dynamicType {
        case .xSmall, .small: return 0.94
        case .medium: return 1.0
        case .large, .xLarge: return 1.06
        case .xxLarge, .xxxLarge: return 1.12
        case .accessibility1, .accessibility2: return 1.18
        default: return 1.24
        }
    }

    private func clamp(_ min: CGFloat, _ vwFactor: CGFloat, _ max: CGFloat) -> CGFloat {
        Swift.min(Swift.max(min, width * vwFactor), max) * typeScale
    }

    var isCompact: Bool { width < 390 }

    var contentMaxWidth: CGFloat { Swift.min(1240, width) }
    var horizontalPadding: CGFloat { clamp(10, 0.05, 64) }
    var topPadding: CGFloat { clamp(44, 0.07, 88) }
    var bottomPadding: CGFloat { clamp(14, 0.03, 36) }
    var contentGap: CGFloat { clamp(12, 0.02, 28) }
    var textGap: CGFloat { clamp(10, 0.015, 20) }

    var tagFontSize: CGFloat { clamp(10, 0.028, 12) }
    var tagTracking: CGFloat { tagFontSize * 1.4 }

    var leadFontSize: CGFloat { clamp(24, 0.07, 52) }
    var midFontSize: CGFloat { clamp(14, 0.038, 19) }
    var subFontSize: CGFloat { clamp(12, 0.034, 16) }

    /// ~36ch lead width cap from website CSS.
    var leadMaxWidth: CGFloat { Swift.min(width * 0.92, leadFontSize * 20) }
    /// ~78ch body width cap.
    var bodyMaxWidth: CGFloat { Swift.min(width * 0.94, midFontSize * 42) }
    /// ~68ch sub width cap.
    var subMaxWidth: CGFloat { Swift.min(width * 0.94, subFontSize * 38) }

    var ctaTextFontSize: CGFloat { clamp(10, 0.028, 16) }
    var ctaButtonSize: CGFloat { clamp(26, 0.065, 36) }
    var ctaHorizontalPadding: CGFloat { isCompact ? 12 : 22 }
    var ctaVerticalPadding: CGFloat { isCompact ? 7 : 12 }
    var ctaCornerRadius: CGFloat { isCompact ? 999 : 12 }
}
