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
        case .hero:
            content
                .padding(18)
                .paxPremiumGlass(tier: .standard, cornerRadius: 20)
        case .metric:
            content
                .padding(14)
                .frame(maxWidth: .infinity, alignment: .leading)
                .paxPremiumGlass(tier: .standard, cornerRadius: 16)
        case .feature:
            content
                .padding(16)
                .paxPremiumGlass(tier: .standard, cornerRadius: 18)
        case .list:
            content
                .padding(.horizontal, 16)
                .padding(.vertical, 12)
                .paxPremiumGlass(tier: .subtle, cornerRadius: 14)
        case .accent:
            content
                .padding(16)
                .paxPremiumGlass(tier: .standard, cornerRadius: 16)
        case .compact:
            content
                .padding(.horizontal, 12)
                .padding(.vertical, 9)
                .paxPremiumGlass(tier: .subtle, cornerRadius: 12)
        }
    }
}

extension View {
    func paxCard(_ variant: PAXCardVariant, tint: Color = PAXTheme.accent, animateReflection: Bool = false) -> some View {
        modifier(PAXCardVariantModifier(variant: variant, tint: tint, animateReflection: animateReflection))
    }

    /// Subtle crystal specular on card surfaces (not on icons).
    fileprivate func paxCardGlassReflection(
        cornerRadius: CGFloat,
        alignment: Alignment,
        intensity: Double = 0.08
    ) -> some View {
        self
    }

    fileprivate func paxCardGlassRefraction(cornerRadius: CGFloat) -> some View {
        self
    }

    fileprivate func paxAnimatedGlassReflection(cornerRadius: CGFloat, enabled: Bool) -> some View {
        self
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
            PAXIcon( systemImage)
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
                PAXIcon( icon)
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
            PAXIcon( systemImage)
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

            PAXIcon( "chevron.right")
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
                    RoundedRectangle(cornerRadius: 14, style: .continuous)
                        .stroke(PAXTheme.border, lineWidth: 1)
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
            PAXIcon( systemImage)
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
