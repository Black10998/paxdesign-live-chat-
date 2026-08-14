import SwiftUI
import UIKit

enum PAXTheme {
    private(set) static var cachedPalette: PAXThemePalette = PAXThemePalette.palette(for: .classic)
    private(set) static var cachedIsDark = false

    static func applyPalette(_ palette: PAXThemePalette, isDark: Bool) {
        cachedPalette = palette
        cachedIsDark = isDark
        PAXRevolutAppearance.apply()
    }

    /// Brand accent — lime in Dark, system blue in Light. Always tracks live traits.
    static var accent: Color { PAXBrand.adaptiveAccent }
    static var accentSecondary: Color { PAXDynamic.color(UIColor.systemIndigo, UIColor(red: 0.55, green: 0.82, blue: 1, alpha: 1)) }
    static var success: Color { Color(uiColor: PAXDynamic.income) }
    static var danger: Color { Color(uiColor: PAXDynamic.spend) }
    static var adminBubble: Color { accent }

    static var border: Color { PAXDynamic.color(PAXDynamic.dividerLight, PAXDynamic.dividerDark) }
    static var borderSubtle: Color { PAXDynamic.color(PAXDynamic.borderLight, PAXDynamic.borderDark) }
    static var divider: Color { border }
    static var textPrimary: Color { PAXDynamic.color(PAXDynamic.textPrimaryLight, PAXDynamic.textPrimaryDark) }
    static var textSecondary: Color { PAXDynamic.color(PAXDynamic.textSecondaryLight, PAXDynamic.textSecondaryDark) }
    static var textTertiary: Color { PAXDynamic.color(PAXDynamic.textTertiaryLight, PAXDynamic.textTertiaryDark) }
    static var link: Color { accent }
    static var onAccent: Color { PAXBrand.adaptiveOnAccent }
    static var icon: Color { textPrimary }
    static var iconSecondary: Color { textSecondary }
    static var iconTertiary: Color { textTertiary }
    static var iconOnFill: Color { onAccent }
    static var userBubble: Color { surfaceElevated }

    static var background: Color { PAXDynamic.color(PAXDynamic.canvasLight, PAXDynamic.canvasDark) }
    static var surface: Color { PAXDynamic.color(PAXDynamic.surface1Light, PAXDynamic.surface1Dark) }
    static var surfaceElevated: Color { PAXDynamic.color(PAXDynamic.surface2Light, PAXDynamic.surface2Dark) }
    static var surface3: Color { PAXDynamic.color(PAXDynamic.surface3Light, PAXDynamic.surface3Dark) }
    static var cardShadow: Color {
        PAXDynamic.color(UIColor.black.withAlphaComponent(0.06), UIColor.black.withAlphaComponent(0.45))
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
    @ObservedObject private var settings = AppSettingsStore.shared
    @Environment(\.colorScheme) private var colorScheme

    var body: some View {
        // Resolve from appearanceMode immediately so Light/Dark never waits on a stale trait.
        settings.palette.background(isDark: settings.resolvedIsDark(for: colorScheme))
            .ignoresSafeArea()
            .id(settings.themeRevision)
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

    func body(content: Content) -> some View {
        let elevation: Int = {
            switch tier {
            case .subtle: return 0
            case .standard, .tabBar: return 0
            case .premium, .hero: return 1
            }
        }()
        content.paxRevolutSurface(cornerRadius: cornerRadius, elevation: elevation)
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
            .tint(PAXBrand.adaptiveAccent)
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
        PAXRevolutAppearance.apply()
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
