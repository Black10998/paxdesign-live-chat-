import SwiftUI

/// Adaptive marketing palette — follows system Light/Dark while keeping PAXdesign brand accent.
struct CustomerMarketingTheme: Equatable {
    let colorScheme: ColorScheme

    var background: Color {
        colorScheme == .dark ? Color.black : Color(red: 0.96, green: 0.96, blue: 0.97)
    }

    var panel: Color {
        colorScheme == .dark ? Color(red: 0.06, green: 0.06, blue: 0.06) : Color.white
    }

    var cardBackground: Color {
        colorScheme == .dark ? Color(red: 0.09, green: 0.09, blue: 0.09) : Color.white
    }

    var border: Color {
        colorScheme == .dark ? Color.white.opacity(0.08) : Color.black.opacity(0.08)
    }

    var textPrimary: Color {
        colorScheme == .dark ? Color.white : Color(red: 0.08, green: 0.08, blue: 0.1)
    }

    var textSecondary: Color {
        colorScheme == .dark ? Color(red: 0.55, green: 0.55, blue: 0.55) : Color(red: 0.42, green: 0.44, blue: 0.48)
    }

    var accent: Color { Color(red: 0.76, green: 1.0, blue: 0.0) }

    var accentOnAccent: Color { Color.black }

    var shadowLight: Color {
        colorScheme == .dark ? Color(red: 0.12, green: 0.12, blue: 0.12) : Color.white
    }

    var shadowDark: Color {
        colorScheme == .dark ? Color.black : Color.black.opacity(0.12)
    }

    var linkBlue: Color {
        colorScheme == .dark ? Color(red: 0.45, green: 0.72, blue: 1.0) : Color(red: 0.0, green: 0.35, blue: 0.75)
    }

    var heroGradientTop: Color {
        colorScheme == .dark ? Color.black.opacity(0.15) : Color.black.opacity(0.25)
    }

    var heroGradientBottom: Color {
        colorScheme == .dark ? Color.black.opacity(0.82) : Color.black.opacity(0.72)
    }

    var heroTagColor: Color {
        colorScheme == .dark ? Color.white.opacity(0.72) : Color.white.opacity(0.88)
    }

    var chipSelectedForeground: Color { accentOnAccent }
    var chipUnselectedBackground: Color { panel }
}

private struct CustomerMarketingThemeKey: EnvironmentKey {
    static let defaultValue = CustomerMarketingTheme(colorScheme: .light)
}

extension EnvironmentValues {
    var marketingTheme: CustomerMarketingTheme {
        CustomerMarketingTheme(colorScheme: colorScheme)
    }
}

/// Backward-compatible alias used across marketing screens.
enum ServicesCatalogTheme {
    static func theme(_ scheme: ColorScheme) -> CustomerMarketingTheme {
        CustomerMarketingTheme(colorScheme: scheme)
    }
}

struct ServicesNeumorphicCard<Content: View>: View {
    @Environment(\.marketingTheme) private var theme
    var highlighted: Bool = false
    @ViewBuilder var content: () -> Content

    var body: some View {
        content()
            .background(theme.cardBackground)
            .clipShape(RoundedRectangle(cornerRadius: 16, style: .continuous))
            .shadow(color: theme.shadowDark, radius: 8, x: 8, y: 8)
            .shadow(color: theme.shadowLight, radius: 8, x: -8, y: -8)
            .overlay {
                if highlighted {
                    RoundedRectangle(cornerRadius: 16, style: .continuous)
                        .stroke(theme.accent.opacity(0.25), lineWidth: 1)
                        .shadow(color: theme.accent.opacity(0.08), radius: 10)
                }
            }
    }
}

struct ServicesRotatingDisc: View {
    @Environment(\.marketingTheme) private var theme
    @State private var rotation: Double = 0
    var size: CGFloat = 20

    var body: some View {
        Circle()
            .trim(from: 0, to: 0.65)
            .stroke(theme.accent, style: StrokeStyle(lineWidth: 2, dash: [10, 5]))
            .frame(width: size, height: size)
            .rotationEffect(.degrees(rotation))
            .onAppear {
                withAnimation(.linear(duration: 3).repeatForever(autoreverses: false)) {
                    rotation = 360
                }
            }
            .accessibilityHidden(true)
    }
}

struct ServicesLanguageSwitcher: View {
    @Environment(\.marketingTheme) private var theme
    @Binding var language: CustomerServicesCatalogLanguage

    var body: some View {
        HStack(spacing: 4) {
            ForEach(CustomerServicesCatalogLanguage.allCases) { lang in
                Button {
                    withAnimation(.easeInOut(duration: 0.2)) { language = lang }
                } label: {
                    Text(lang.label)
                        .font(.system(size: 12, weight: .semibold))
                        .tracking(0.5)
                        .frame(minWidth: 44)
                        .padding(.vertical, 8)
                        .padding(.horizontal, 14)
                        .background(language == lang ? theme.cardBackground : Color.clear)
                        .foregroundStyle(language == lang ? theme.textPrimary : theme.textSecondary)
                        .clipShape(Capsule())
                }
                .buttonStyle(.plain)
                .accessibilityAddTraits(language == lang ? .isSelected : [])
            }
        }
        .padding(4)
        .background(theme.panel)
        .overlay(Capsule().stroke(theme.border, lineWidth: 1))
        .clipShape(Capsule())
    }
}

struct ServicesInsetButton: View {
    @Environment(\.marketingTheme) private var theme
    let title: String
    var action: () -> Void

    var body: some View {
        Button(action: action) {
            Text(title.uppercased())
                .font(.system(size: 15, weight: .bold))
                .tracking(0.5)
                .frame(maxWidth: .infinity)
                .padding(.vertical, 14)
                .foregroundStyle(theme.textPrimary)
                .background(theme.cardBackground)
                .clipShape(RoundedRectangle(cornerRadius: 12, style: .continuous))
                .shadow(color: theme.shadowDark, radius: 4, x: 4, y: 4)
                .shadow(color: theme.shadowLight, radius: 4, x: -4, y: -4)
        }
        .buttonStyle(.plain)
    }
}

struct ServicesCornerRibbon: View {
    @Environment(\.marketingTheme) private var theme
    let label: String
    var isRTL: Bool = false

    var body: some View {
        ZStack(alignment: isRTL ? .topLeading : .topTrailing) {
            Color.clear
            Text(label)
                .font(.system(size: 10, weight: .heavy))
                .tracking(0.6)
                .textCase(.uppercase)
                .foregroundStyle(theme.accentOnAccent)
                .padding(.vertical, 6)
                .frame(width: 125)
                .background(
                    LinearGradient(
                        colors: [
                            Color(red: 0.83, green: 1, blue: 0.2),
                            theme.accent,
                            Color(red: 0.66, green: 0.9, blue: 0)
                        ],
                        startPoint: .topLeading,
                        endPoint: .bottomTrailing
                    )
                )
                .rotationEffect(.degrees(isRTL ? -45 : 45))
                .offset(x: isRTL ? -32 : 32, y: 16)
        }
        .frame(width: 88, height: 88)
        .clipped()
        .allowsHitTesting(false)
        .accessibilityHidden(true)
    }
}
