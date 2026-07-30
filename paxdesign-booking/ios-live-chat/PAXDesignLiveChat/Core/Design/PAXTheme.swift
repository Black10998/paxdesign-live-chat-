import SwiftUI
import UIKit

enum PAXTheme {
    private(set) static var cachedPalette: PAXThemePalette = PAXThemePalette.palette(for: .classic)
    private(set) static var cachedIsDark = false

    static func applyPalette(_ palette: PAXThemePalette, isDark: Bool) {
        cachedPalette = palette
        cachedIsDark = isDark
    }

    static var accent: Color { PAXBrand.appearanceAccent(isDark: cachedIsDark) }
    static var accentSecondary: Color { cachedPalette.accentSecondary }
    static var success: Color { cachedPalette.success }
    static var danger: Color { cachedPalette.danger }
    static var adminBubble: Color { cachedPalette.adminBubble }

    static var border: Color { Color(.separator) }
    static var textPrimary: Color { .primary }
    static var textSecondary: Color {
        cachedIsDark ? Color(UIColor.secondaryLabel) : Color(red: 0.16, green: 0.18, blue: 0.22)
    }
    static var textTertiary: Color {
        cachedIsDark ? Color(UIColor.tertiaryLabel) : Color(red: 0.28, green: 0.30, blue: 0.34)
    }
    static var link: Color {
        cachedIsDark ? accent : Color(uiColor: .systemBlue)
    }
    static var onAccent: Color {
        PAXBrand.accentLabelColor(isDark: cachedIsDark)
    }
    static var icon: Color { .primary }
    static var iconSecondary: Color { textSecondary }
    static var iconTertiary: Color { textTertiary }
    static var iconOnFill: Color { .white }
    static var userBubble: Color { Color(.systemGray5) }

    static var background: Color {
        cachedPalette.background(isDark: cachedIsDark)
    }
    static var surface: Color {
        cachedPalette.surface(isDark: cachedIsDark)
    }
    static var surfaceElevated: Color {
        cachedPalette.surfaceElevated(isDark: cachedIsDark)
    }

    static var systemBubble: Color { accent.opacity(0.14) }
    static var accentSoft: Color { accent.opacity(0.12) }

    static let spring = Animation.easeInOut(duration: 0.22)
    static let quickSpring = Animation.easeOut(duration: 0.16)
    static let fade = Animation.easeInOut(duration: 0.2)
}

enum PAXGlassTier {
    case subtle
    case standard
    case premium
    case hero
    case tabBar

    var material: Material {
        switch self {
        case .subtle: .ultraThinMaterial
        case .standard, .tabBar: .regularMaterial
        case .premium, .hero: .regularMaterial
        }
    }

    func borderOpacity(for scheme: ColorScheme) -> Double {
        scheme == .dark ? 0.28 : 0.22
    }

    func shadowOpacity(for scheme: ColorScheme) -> Double {
        scheme == .dark ? 0.18 : 0.08
    }
}

struct PAXBackground: View {
    @EnvironmentObject private var settings: AppSettingsStore
    @Environment(\.colorScheme) private var colorScheme

    var body: some View {
        settings.palette.background(isDark: settings.resolvedIsDark(for: colorScheme))
            .ignoresSafeArea()
    }
}

struct PAXGlassSurface: ViewModifier {
    func body(content: Content) -> some View {
        content.paxPremiumGlass(tier: .standard, cornerRadius: 14)
    }
}

private struct PAXPremiumGlassModifier: ViewModifier {
    let tier: PAXGlassTier
    let cornerRadius: CGFloat
    let accent: Color?

    @Environment(\.colorScheme) private var colorScheme

    func body(content: Content) -> some View {
        let border = tier.borderOpacity(for: colorScheme)
        let shadow = tier.shadowOpacity(for: colorScheme)

        content
            .background(
                RoundedRectangle(cornerRadius: cornerRadius, style: .continuous)
                    .fill(tier.material)
                    .overlay(
                        RoundedRectangle(cornerRadius: cornerRadius, style: .continuous)
                            .stroke(PAXTheme.border.opacity(border), lineWidth: 0.5)
                    )
            )
            .shadow(color: .black.opacity(shadow), radius: 3, x: 0, y: 1)
    }
}

private struct PAXGlassCardModifier: ViewModifier {
    let cornerRadius: CGFloat
    let fillOpacity: Double
    let borderOpacity: Double
    let shadowOpacity: Double

    func body(content: Content) -> some View {
        content.paxPremiumGlass(tier: .standard, cornerRadius: cornerRadius)
    }
}

extension View {
    func paxGlassSurface() -> some View {
        modifier(PAXGlassSurface())
    }

    func paxPremiumGlass(
        tier: PAXGlassTier = .standard,
        cornerRadius: CGFloat = 16,
        accent: Color? = nil
    ) -> some View {
        modifier(PAXPremiumGlassModifier(tier: tier, cornerRadius: cornerRadius, accent: accent))
    }

    func paxGlassCardStyle(
        cornerRadius: CGFloat = 14,
        fillOpacity: Double = 0.82,
        borderOpacity: Double = 0.45,
        shadowOpacity: Double = 0.2
    ) -> some View {
        modifier(
            PAXGlassCardModifier(
                cornerRadius: cornerRadius,
                fillOpacity: fillOpacity,
                borderOpacity: borderOpacity,
                shadowOpacity: shadowOpacity
            )
        )
    }
}

struct PAXAppearanceObserver: ViewModifier {
    @ObservedObject var settings: AppSettingsStore
    @Environment(\.colorScheme) private var colorScheme

    private var resolvedIsDark: Bool {
        settings.resolvedIsDark(for: colorScheme)
    }

    private var resolvedPalette: PAXThemePalette {
        settings.basePalette.withAccent(PAXBrand.appearanceAccent(isDark: resolvedIsDark))
    }

    func body(content: Content) -> some View {
        content
            .environment(\.paxPalette, resolvedPalette)
            .tint(PAXBrand.appearanceAccent(isDark: resolvedIsDark))
            .preferredColorScheme(settings.appearanceMode.colorScheme)
            .onAppear {
                syncTheme()
            }
            .onChange(of: colorScheme) { _ in
                syncTheme()
            }
            .onChange(of: settings.appearanceMode) { _ in
                syncTheme()
            }
            .onChange(of: settings.visualTheme) { _ in
                syncTheme()
            }
            .onChange(of: settings.themeRevision) { _ in
                syncTheme()
            }
    }

    private func syncTheme() {
        PAXTheme.applyPalette(resolvedPalette, isDark: resolvedIsDark)
    }
}

extension View {
    func paxObserveAppearance(settings: AppSettingsStore) -> some View {
        modifier(PAXAppearanceObserver(settings: settings))
    }
}

enum PAXHaptics {
    private static let lightGenerator = UIImpactFeedbackGenerator(style: .light)
    private static let mediumGenerator = UIImpactFeedbackGenerator(style: .medium)
    private static let notificationGenerator = UINotificationFeedbackGenerator()

    static func prepare() {
        lightGenerator.prepare()
        mediumGenerator.prepare()
        notificationGenerator.prepare()
    }

    static func light() {
        lightGenerator.impactOccurred(intensity: 0.85)
        lightGenerator.prepare()
    }

    static func medium() {
        mediumGenerator.impactOccurred(intensity: 0.9)
        mediumGenerator.prepare()
    }

    static func success() {
        notificationGenerator.notificationOccurred(.success)
        notificationGenerator.prepare()
    }

    static func warning() {
        notificationGenerator.notificationOccurred(.warning)
        notificationGenerator.prepare()
    }
}
