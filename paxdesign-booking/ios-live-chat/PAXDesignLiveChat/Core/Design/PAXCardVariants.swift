import SwiftUI

enum PAXCardVariant {
    case standard
    case hero
    case metric
    case feature
    case list
    case accent
    case compact
}

private struct PAXCardVariantModifier: ViewModifier {
    let variant: PAXCardVariant
    let tint: Color
    let animateReflection: Bool

    func body(content: Content) -> some View {
        switch variant {
        case .standard:
            content
                .paxPremiumGlass(tier: .standard, cornerRadius: 16)
                .paxCardGlassReflection(cornerRadius: 16, alignment: .topTrailing, intensity: 0.07)
                .paxCardGlassRefraction(cornerRadius: 16)
                .paxAnimatedGlassReflection(cornerRadius: 16, enabled: animateReflection)
        case .hero:
            content
                .padding(18)
                .paxPremiumGlass(tier: .hero, cornerRadius: 24, accent: tint)
                .paxCardGlassReflection(cornerRadius: 24, alignment: .topTrailing, intensity: 0.12)
                .paxCardGlassReflection(cornerRadius: 24, alignment: .bottomLeading, intensity: 0.04)
                .paxCardGlassRefraction(cornerRadius: 24)
                .paxAnimatedGlassReflection(cornerRadius: 24, enabled: animateReflection)
                .overlay(alignment: .topTrailing) {
                    PAXCardDecorLayer(cornerRadius: 24, tint: tint)
                }
        case .metric:
            content
                .padding(14)
                .frame(maxWidth: .infinity, alignment: .leading)
                .paxPremiumGlass(tier: .premium, cornerRadius: 20, accent: tint)
                .paxCardGlassReflection(cornerRadius: 20, alignment: .topTrailing, intensity: 0.11)
                .paxCardGlassReflection(cornerRadius: 20, alignment: .bottomLeading, intensity: 0.035)
                .paxCardGlassRefraction(cornerRadius: 20)
                .paxAnimatedGlassReflection(cornerRadius: 20, enabled: animateReflection)
                .overlay(alignment: .topLeading) {
                    RoundedRectangle(cornerRadius: 3, style: .continuous)
                        .fill(
                            LinearGradient(
                                colors: [tint, tint.opacity(0.35)],
                                startPoint: .top,
                                endPoint: .bottom
                            )
                        )
                        .frame(width: 4, height: 42)
                        .padding(.leading, 0)
                        .offset(x: -1, y: 14)
                }
                .overlay(alignment: .bottomTrailing) {
                    Circle()
                        .fill(tint.opacity(0.06))
                        .frame(width: 56, height: 56)
                        .offset(x: 12, y: 14)
                        .allowsHitTesting(false)
                }
        case .feature:
            content
                .padding(16)
                .paxPremiumGlass(tier: .premium, cornerRadius: 22, accent: tint)
                .paxCardGlassReflection(cornerRadius: 22, alignment: .topTrailing, intensity: 0.1)
                .paxCardGlassReflection(cornerRadius: 22, alignment: .bottomLeading, intensity: 0.04)
                .paxCardGlassRefraction(cornerRadius: 22)
                .paxAnimatedGlassReflection(cornerRadius: 22, enabled: animateReflection)
                .overlay(alignment: .topTrailing) {
                    PAXCardDecorLayer(cornerRadius: 22, tint: tint)
                }
        case .list:
            content
                .padding(.horizontal, 16)
                .padding(.vertical, 12)
                .paxPremiumGlass(tier: .subtle, cornerRadius: 18)
                .paxCardGlassReflection(cornerRadius: 18, alignment: .topTrailing, intensity: 0.05)
                .paxCardGlassRefraction(cornerRadius: 18)
        case .accent:
            content
                .padding(16)
                .background(
                    RoundedRectangle(cornerRadius: 20, style: .continuous)
                        .fill(
                            LinearGradient(
                                colors: [tint.opacity(0.1), tint.opacity(0.04)],
                                startPoint: .topLeading,
                                endPoint: .bottomTrailing
                            )
                        )
                )
                .paxPremiumGlass(tier: .standard, cornerRadius: 20, accent: tint)
                .paxCardGlassReflection(cornerRadius: 20, alignment: .topLeading, intensity: 0.08)
                .paxCardGlassRefraction(cornerRadius: 20)
        case .compact:
            content
                .padding(.horizontal, 12)
                .padding(.vertical, 9)
                .paxPremiumGlass(tier: .subtle, cornerRadius: 14)
        }
    }
}

extension View {
    func paxCard(_ variant: PAXCardVariant, tint: Color = PAXTheme.accent, animateReflection: Bool = true) -> some View {
        modifier(PAXCardVariantModifier(variant: variant, tint: tint, animateReflection: animateReflection))
    }

    /// Subtle crystal specular on card surfaces (not on icons).
    fileprivate func paxCardGlassReflection(
        cornerRadius: CGFloat,
        alignment: Alignment,
        intensity: Double = 0.08
    ) -> some View {
        overlay(alignment: alignment) {
            RoundedRectangle(cornerRadius: cornerRadius, style: .continuous)
                .fill(
                    RadialGradient(
                        colors: [
                            Color.white.opacity(intensity + 0.06),
                            Color.white.opacity(intensity * 0.45),
                            Color.white.opacity(intensity * 0.12),
                            .clear
                        ],
                        center: .center,
                        startRadius: 0,
                        endRadius: 68
                    )
                )
                .frame(width: 112, height: 112)
                .offset(
                    x: alignment == .topTrailing || alignment == .bottomTrailing ? 28 : -28,
                    y: alignment == .bottomTrailing || alignment == .bottomLeading ? 32 : -32
                )
                .blendMode(.overlay)
                .allowsHitTesting(false)
        }
    }

    /// Soft diagonal refraction line for crystal glass depth.
    fileprivate func paxCardGlassRefraction(cornerRadius: CGFloat) -> some View {
        overlay {
            RoundedRectangle(cornerRadius: cornerRadius, style: .continuous)
                .fill(
                    LinearGradient(
                        colors: [
                            Color.white.opacity(0.22),
                            Color.white.opacity(0.06),
                            .clear,
                            Color.white.opacity(0.04),
                            Color.white.opacity(0.1),
                            .clear
                        ],
                        startPoint: .topLeading,
                        endPoint: .bottomTrailing
                    )
                )
                .blendMode(.overlay)
                .allowsHitTesting(false)
        }
        .overlay {
            RoundedRectangle(cornerRadius: cornerRadius, style: .continuous)
                .stroke(
                    LinearGradient(
                        colors: [
                            Color.white.opacity(0.28),
                            .clear,
                            Color.white.opacity(0.08)
                        ],
                        startPoint: .topLeading,
                        endPoint: .bottomTrailing
                    ),
                    lineWidth: 0.5
                )
                .blendMode(.overlay)
                .allowsHitTesting(false)
        }
    }

    fileprivate func paxAnimatedGlassReflection(cornerRadius: CGFloat, enabled: Bool) -> some View {
        Group {
            if enabled {
                self.paxAnimatedGlassReflection(cornerRadius: cornerRadius)
            } else {
                self
            }
        }
    }
}

struct PAXHeroCard: View {
    let title: String
    let subtitle: String
    let systemImage: String
    var tint: Color = PAXTheme.accent
    var helpText: String?

    var body: some View {
        HStack(alignment: .center, spacing: 14) {
            Image(systemName: systemImage)
                .font(.title2.weight(.semibold))
                .symbolRenderingMode(.hierarchical)
                .foregroundStyle(tint)

            VStack(alignment: .leading, spacing: 4) {
                Text(title)
                    .font(.title3.weight(.bold))
                    .foregroundStyle(PAXTheme.textPrimary)
                Text(subtitle)
                    .font(.subheadline)
                    .foregroundStyle(PAXTheme.textSecondary)
                    .fixedSize(horizontal: false, vertical: true)
            }

            Spacer(minLength: 0)
        }
        .paxCard(.hero, tint: tint)
        .modifier(OptionalCardHelp(helpText: helpText))
    }
}

struct PAXMetricCard: View {
    let title: String
    let value: String
    let icon: String
    var tint: Color = PAXTheme.accent
    var helpText: String?

    var body: some View {
        VStack(alignment: .leading, spacing: 10) {
            HStack {
                Image(systemName: icon)
                    .font(.body.weight(.semibold))
                    .foregroundStyle(tint)
                Spacer(minLength: 0)
            }
            Text(value)
                .font(.title.weight(.bold))
                .foregroundStyle(PAXTheme.textPrimary)
            Text(title)
                .font(.caption.weight(.medium))
                .foregroundStyle(PAXTheme.textSecondary)
        }
        .paxCard(.metric, tint: tint)
        .modifier(OptionalCardHelp(helpText: helpText))
    }
}

struct PAXFeatureCard: View {
    let title: String
    let subtitle: String
    let systemImage: String
    var tint: Color = PAXTheme.accent
    var badge: Int = 0
    var helpText: String?

    var body: some View {
        HStack(spacing: 14) {
            Image(systemName: systemImage)
                .font(.title3.weight(.medium))
                .foregroundStyle(tint)

            VStack(alignment: .leading, spacing: 3) {
                HStack(spacing: 6) {
                    Text(title)
                        .font(.subheadline.weight(.semibold))
                        .foregroundStyle(PAXTheme.textPrimary)
                        .lineLimit(1)
                    if badge > 0 {
                        Text("\(badge)")
                            .font(.caption2.weight(.bold))
                            .foregroundStyle(.white)
                            .padding(.horizontal, 6)
                            .padding(.vertical, 2)
                            .background(Capsule().fill(tint))
                    }
                }
                Text(subtitle)
                    .font(.caption)
                    .foregroundStyle(PAXTheme.textSecondary)
                    .lineLimit(2)
            }

            Spacer(minLength: 0)

            Image(systemName: "chevron.right")
                .font(.caption.weight(.semibold))
                .foregroundStyle(PAXTheme.textTertiary)
                .flipsForRightToLeftLayoutDirection(true)
        }
        .paxCard(.feature, tint: tint)
        .modifier(OptionalCardHelp(helpText: helpText))
    }
}

private struct OptionalCardHelp: ViewModifier {
    let helpText: String?

    func body(content: Content) -> some View {
        if let helpText, !helpText.isEmpty {
            content.paxCardHelp(helpText)
        } else {
            content
        }
    }
}

struct PAXListCard<Content: View>: View {
    var highlighted: Bool = false
    var accent: Color = PAXTheme.accent
    @ViewBuilder let content: () -> Content

    var body: some View {
        content()
            .paxCard(highlighted ? .accent : .list, tint: accent, animateReflection: false)
            .overlay {
                if highlighted {
                    RoundedRectangle(cornerRadius: 18, style: .continuous)
                        .stroke(accent.opacity(0.42), lineWidth: 1.2)
                }
            }
    }
}

struct PAXAccentBannerCard: View {
    let title: String
    let subtitle: String
    let systemImage: String
    var tint: Color = PAXTheme.accent

    var body: some View {
        HStack(spacing: 12) {
            Image(systemName: systemImage)
                .font(.title3)
                .foregroundStyle(tint)
            VStack(alignment: .leading, spacing: 2) {
                Text(title)
                    .font(.subheadline.weight(.semibold))
                Text(subtitle)
                    .font(.caption)
                    .foregroundStyle(PAXTheme.textSecondary)
            }
            Spacer(minLength: 0)
        }
        .paxCard(.accent, tint: tint)
    }
}
