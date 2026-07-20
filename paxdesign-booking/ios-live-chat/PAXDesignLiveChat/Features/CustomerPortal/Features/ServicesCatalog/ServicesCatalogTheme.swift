import SwiftUI

/// Adaptive marketing palette — follows system Light/Dark with appearance-aware accent.
struct CustomerMarketingTheme: Equatable {
    let colorScheme: ColorScheme
    let accent: Color

    init(colorScheme: ColorScheme, accent: Color? = nil) {
        self.colorScheme = colorScheme
        self.accent = accent ?? Self.resolvedAccentColor(for: colorScheme)
    }

    static func resolvedAccentColor(for colorScheme: ColorScheme) -> Color {
        PAXBrand.appearanceAccent(isDark: colorScheme == .dark)
    }

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
        colorScheme == .dark ? Color.white.opacity(0.12) : Color.black.opacity(0.12)
    }

    var textPrimary: Color {
        colorScheme == .dark ? Color.white : Color(red: 0.06, green: 0.06, blue: 0.08)
    }

    var textSecondary: Color {
        colorScheme == .dark ? Color(red: 0.78, green: 0.78, blue: 0.78) : Color(red: 0.16, green: 0.18, blue: 0.22)
    }

    var textTertiary: Color {
        colorScheme == .dark ? Color(red: 0.62, green: 0.62, blue: 0.62) : Color(red: 0.28, green: 0.30, blue: 0.34)
    }

    var accentOnAccent: Color {
        PAXBrand.accentLabelColor(isDark: colorScheme == .dark)
    }

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
    @Environment(\.colorScheme) private var colorScheme
    @Binding var language: CustomerServicesCatalogLanguage

    private var ink: Color { colorScheme == .dark ? .white : .black }
    private var muted: Color { colorScheme == .dark ? Color.white.opacity(0.55) : Color.black.opacity(0.45) }
    private var fill: Color { colorScheme == .dark ? Color(white: 0.12) : Color(white: 0.94) }
    private var stroke: Color { colorScheme == .dark ? Color.white.opacity(0.14) : Color.black.opacity(0.1) }

    var body: some View {
        HStack(spacing: 2) {
            ForEach(CustomerServicesCatalogLanguage.allCases) { lang in
                Button {
                    language = lang
                } label: {
                    Text(lang.label)
                        .font(.system(size: 12, weight: .semibold))
                        .tracking(0.4)
                        .frame(minWidth: 36)
                        .padding(.vertical, 7)
                        .padding(.horizontal, 10)
                        .background(language == lang ? ink : Color.clear)
                        .foregroundStyle(language == lang ? (colorScheme == .dark ? Color.black : Color.white) : muted)
                        .clipShape(Capsule())
                }
                .buttonStyle(.plain)
                .accessibilityAddTraits(language == lang ? .isSelected : [])
            }
        }
        .padding(3)
        .background(fill)
        .overlay(Capsule().stroke(stroke, lineWidth: 1))
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
                            theme.accent,
                            theme.accent,
                            theme.colorScheme == .dark ? Color(red: 0.66, green: 0.9, blue: 0) : PAXBrand.lightModeAccent.opacity(0.85)
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
