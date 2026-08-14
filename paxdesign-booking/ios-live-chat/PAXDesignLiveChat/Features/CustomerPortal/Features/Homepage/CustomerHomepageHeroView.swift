import SwiftUI

/// Product-style home intro for Cybercrime / services — Revolut language, not a marketing landing.
struct CustomerHomepageHeroView: View {
    let hero: CustomerHomepageResponse.Hero
    var services: [CustomerHomepageResponse.ServiceCard] = []
    var onPrimaryAction: () -> Void
    var onSecondaryAction: () -> Void
    var onService: ((CustomerHomepageResponse.ServiceCard) -> Void)? = nil
    var onCybercrime: (() -> Void)? = nil

    @Environment(\.marketingTheme) private var theme

    var body: some View {
        VStack(alignment: .leading, spacing: 22) {
            Text(eyebrow)
                .font(PAXTypography.labelUpper)
                .tracking(0.8)
                .foregroundStyle(PAXTheme.textTertiary)

            VStack(alignment: .leading, spacing: 10) {
                Text(hero.lead)
                    .font(PAXTypography.titleLarge)
                    .foregroundStyle(PAXTheme.textPrimary)
                    .fixedSize(horizontal: false, vertical: true)
                Text(hero.mid)
                    .font(PAXTypography.body)
                    .foregroundStyle(PAXTheme.textSecondary)
                    .fixedSize(horizontal: false, vertical: true)
                if !hero.sub.isEmpty {
                    Text(hero.sub)
                        .font(PAXTypography.meta)
                        .foregroundStyle(PAXTheme.textTertiary)
                        .fixedSize(horizontal: false, vertical: true)
                }
            }

            if !hero.tags.isEmpty {
                HStack(spacing: 8) {
                    ForEach(Array(hero.tags.prefix(3).enumerated()), id: \.offset) { index, tag in
                        VStack(alignment: .leading, spacing: 4) {
                            Text(String(format: "%02d", index + 1))
                                .font(.system(size: 11, weight: .bold, design: .rounded))
                                .foregroundStyle(PAXTheme.accent)
                            Text(tag)
                                .font(PAXTypography.caption.weight(.semibold))
                                .foregroundStyle(PAXTheme.textPrimary)
                                .lineLimit(2)
                                .minimumScaleFactor(0.8)
                        }
                        .padding(12)
                        .frame(maxWidth: .infinity, alignment: .leading)
                        .paxRevolutSurface(cornerRadius: 14, elevation: 0)
                    }
                }
            }

            VStack(spacing: 10) {
                PAXRevolutPrimaryButton(title: hero.cta_primary.isEmpty ? String(localized: "Explore Services") : hero.cta_primary, action: onPrimaryAction)
                Button(hero.cta_secondary.isEmpty ? String(localized: "Start a request") : hero.cta_secondary, action: onSecondaryAction)
                    .buttonStyle(PAXRevolutPressableStyle())
                    .font(PAXTypography.button)
                    .foregroundStyle(PAXTheme.textPrimary)
                    .frame(maxWidth: .infinity, minHeight: 48)
                    .paxRevolutSurface(cornerRadius: 16, elevation: 1)
            }

            if let onCybercrime {
                Button(action: onCybercrime) {
                    HStack(spacing: 12) {
                        PAXRevolutGlyphAvatar(systemImage: "shield.checkered", size: 40, tint: PAXTheme.accent)
                        VStack(alignment: .leading, spacing: 2) {
                            Text(String(localized: "Cybercrime Support"))
                                .font(PAXTypography.rowTitle)
                                .foregroundStyle(PAXTheme.textPrimary)
                            Text(String(localized: "Submit and track a confidential report"))
                                .font(PAXTypography.meta)
                                .foregroundStyle(PAXTheme.textSecondary)
                        }
                        Spacer()
                        PAXIcon("chevron.right", size: .inline, emphasis: .tertiary)
                    }
                    .padding(14)
                    .paxRevolutSurface(cornerRadius: 16, elevation: 0)
                }
                .buttonStyle(PAXRevolutPressableStyle())
            }

            if !services.isEmpty {
                VStack(alignment: .leading, spacing: 12) {
                    Text(String(localized: "Product stack").uppercased())
                        .font(PAXTypography.labelUpper)
                        .tracking(0.6)
                        .foregroundStyle(PAXTheme.textTertiary)
                    ScrollView(.horizontal, showsIndicators: false) {
                        HStack(spacing: 12) {
                            ForEach(Array(services.enumerated()), id: \.element.id) { index, card in
                                Button {
                                    onService?(card)
                                } label: {
                                    VStack(alignment: .leading, spacing: 10) {
                                        Text(String(format: "%02d", index + 1))
                                            .font(.system(size: 12, weight: .bold, design: .rounded))
                                            .foregroundStyle(PAXTheme.accent)
                                        Text(card.title)
                                            .font(PAXTypography.rowTitle)
                                            .foregroundStyle(PAXTheme.textPrimary)
                                            .lineLimit(2)
                                            .minimumScaleFactor(0.85)
                                            .fixedSize(horizontal: false, vertical: true)
                                        Text(card.description)
                                            .font(PAXTypography.meta)
                                            .foregroundStyle(PAXTheme.textSecondary)
                                            .lineLimit(3)
                                            .fixedSize(horizontal: false, vertical: true)
                                        Spacer(minLength: 0)
                                        Text(String(localized: "Open"))
                                            .font(PAXTypography.caption.weight(.bold))
                                            .foregroundStyle(PAXTheme.accent)
                                    }
                                    .padding(16)
                                    .frame(width: 220, height: 188, alignment: .leading)
                                    .paxRevolutSurface(cornerRadius: 18, elevation: 0)
                                }
                                .buttonStyle(PAXRevolutPressableStyle())
                            }
                        }
                    }
                }
            }
        }
        .padding(.horizontal, PAXSpacing.screenHorizontal)
        .padding(.top, 28)
        .padding(.bottom, 8)
        .frame(maxWidth: .infinity, alignment: .leading)
        .background(theme.background)
    }

    private var eyebrow: String {
        if hero.tags.isEmpty {
            return String(localized: "PAXDESIGN PLATFORM")
        }
        return hero.tags.prefix(3).joined(separator: "  ·  ").uppercased()
    }
}
