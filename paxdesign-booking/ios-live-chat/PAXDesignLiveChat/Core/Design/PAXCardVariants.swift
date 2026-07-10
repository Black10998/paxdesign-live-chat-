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

    func body(content: Content) -> some View {
        switch variant {
        case .standard:
            content.paxPremiumGlass(tier: .standard, cornerRadius: 16)
        case .hero:
            content
                .padding(18)
                .paxPremiumGlass(tier: .hero, cornerRadius: 22, accent: tint)
                .paxCardGlassReflection(tint: tint, cornerRadius: 22, alignment: .topTrailing)
        case .metric:
            content
                .padding(14)
                .frame(maxWidth: .infinity, alignment: .leading)
                .paxPremiumGlass(tier: .premium, cornerRadius: 18, accent: tint)
                .paxCardGlassReflection(tint: tint, cornerRadius: 18, alignment: .topTrailing, intensity: 0.22)
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
        case .feature:
            content
                .padding(16)
                .paxPremiumGlass(tier: .premium, cornerRadius: 20, accent: tint)
                .paxCardGlassReflection(tint: tint, cornerRadius: 20, alignment: .bottomTrailing, intensity: 0.18)
        case .list:
            content
                .padding(.horizontal, 16)
                .padding(.vertical, 12)
                .paxPremiumGlass(tier: .subtle, cornerRadius: 16)
        case .accent:
            content
                .padding(16)
                .background(
                    RoundedRectangle(cornerRadius: 18, style: .continuous)
                        .fill(
                            LinearGradient(
                                colors: [tint.opacity(0.22), tint.opacity(0.08)],
                                startPoint: .topLeading,
                                endPoint: .bottomTrailing
                            )
                        )
                )
                .paxPremiumGlass(tier: .standard, cornerRadius: 18, accent: tint)
                .paxCardGlassReflection(tint: tint, cornerRadius: 18, alignment: .topLeading, intensity: 0.16)
        case .compact:
            content
                .padding(.horizontal, 12)
                .padding(.vertical, 9)
                .paxPremiumGlass(tier: .subtle, cornerRadius: 14)
        }
    }
}

extension View {
    func paxCard(_ variant: PAXCardVariant, tint: Color = PAXTheme.accent) -> some View {
        modifier(PAXCardVariantModifier(variant: variant, tint: tint))
    }

    /// Subtle ambient glass reflection on card surfaces (not on icons).
    fileprivate func paxCardGlassReflection(
        tint: Color,
        cornerRadius: CGFloat,
        alignment: Alignment,
        intensity: Double = 0.28
    ) -> some View {
        overlay(alignment: alignment) {
            RoundedRectangle(cornerRadius: cornerRadius, style: .continuous)
                .fill(
                    RadialGradient(
                        colors: [Color.white.opacity(intensity + 0.08), tint.opacity(intensity), .clear],
                        center: .center,
                        startRadius: 0,
                        endRadius: 72
                    )
                )
                .frame(width: 132, height: 132)
                .offset(
                    x: alignment == .topTrailing || alignment == .bottomTrailing ? 34 : -34,
                    y: alignment == .bottomTrailing || alignment == .bottomLeading ? 36 : -36
                )
                .blendMode(.plusLighter)
                .allowsHitTesting(false)
        }
    }
}

struct PAXHeroCard: View {
    let title: String
    let subtitle: String
    let systemImage: String
    var tint: Color = PAXTheme.accent

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
    }
}

struct PAXMetricCard: View {
    let title: String
    let value: String
    let icon: String
    var tint: Color = PAXTheme.accent

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
    }
}

struct PAXFeatureCard: View {
    let title: String
    let subtitle: String
    let systemImage: String
    var tint: Color = PAXTheme.accent
    var badge: Int = 0

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
        }
        .paxCard(.feature, tint: tint)
    }
}

struct PAXListCard<Content: View>: View {
    var highlighted: Bool = false
    var accent: Color = PAXTheme.accent
    @ViewBuilder let content: () -> Content

    var body: some View {
        content()
            .paxCard(highlighted ? .accent : .list, tint: accent)
            .overlay {
                if highlighted {
                    RoundedRectangle(cornerRadius: 16, style: .continuous)
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
