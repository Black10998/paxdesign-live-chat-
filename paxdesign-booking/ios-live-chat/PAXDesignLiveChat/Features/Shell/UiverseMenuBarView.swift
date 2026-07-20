import SwiftUI

// MARK: - Reference CSS tokens (Uiverse.io by mymiamo)

enum UiverseMenuMetrics {
    static let horizontalMargin: CGFloat = 8
    static let maxWidth: CGFloat = 520
    /// Small gap above the Home Indicator when the bar overlays the bottom edge.
    static let homeIndicatorGap: CGFloat = 3
    static let menuPadding: CGFloat = 4
    static let itemGap: CGFloat = 3
    static let itemPaddingVertical: CGFloat = 5
    static let itemPaddingHorizontal: CGFloat = 3
    static let iconSize: CGFloat = 19
    static let labelFontSize: CGFloat = 10
    static let labelMarginTop: CGFloat = 2
    static let pillRadius: CGFloat = 9_999

    static let scrollScaleFull: CGFloat = 1.0
    static let scrollScaleStage2: CGFloat = 0.92
    static let scrollScaleStage3: CGFloat = 0.86
    static let scrollCompressionStart: CGFloat = 8
    static let scrollStage1Distance: CGFloat = 50
    static let scrollStage2Distance: CGFloat = 80

    static var itemContentHeight: CGFloat {
        iconSize + labelMarginTop + labelFontSize
    }

    static var itemHeight: CGFloat {
        itemPaddingVertical * 2 + itemContentHeight
    }

    static var menuHeight: CGFloat {
        menuPadding * 2 + itemHeight
    }

    /// Measured menu chrome height (shell uses VStack layout, not scroll inset padding).
    static var scrollInset: CGFloat {
        menuHeight + homeIndicatorGap + 12
    }
}

// MARK: - Native scroll shrink state

@MainActor
final class UiverseMenuScrollState: ObservableObject {
    @Published private(set) var barScale: CGFloat = UiverseMenuMetrics.scrollScaleFull

    func ingestScrollOffset(_ offset: CGFloat, reduceMotion: Bool) {
        let scale = Self.scale(forScrollOffset: offset)
        if barScale == scale { return }
        barScale = scale
    }

    func reset(reduceMotion: Bool) {
        if reduceMotion {
            barScale = UiverseMenuMetrics.scrollScaleFull
        } else {
            withAnimation(.spring(response: 0.38, dampingFraction: 0.84)) {
                barScale = UiverseMenuMetrics.scrollScaleFull
            }
        }
    }

    /// Progressive three-stage scale tied directly to scroll distance (no delta jumps).
    static func scale(forScrollOffset offset: CGFloat) -> CGFloat {
        let compressed = max(0, offset - UiverseMenuMetrics.scrollCompressionStart)
        let d1 = UiverseMenuMetrics.scrollStage1Distance
        let d2 = UiverseMenuMetrics.scrollStage2Distance

        if compressed <= 0 {
            return UiverseMenuMetrics.scrollScaleFull
        }
        if compressed <= d1 {
            let t = compressed / d1
            return UiverseMenuMetrics.scrollScaleFull
                + t * (UiverseMenuMetrics.scrollScaleStage2 - UiverseMenuMetrics.scrollScaleFull)
        }
        if compressed <= d1 + d2 {
            let t = (compressed - d1) / d2
            return UiverseMenuMetrics.scrollScaleStage2
                + t * (UiverseMenuMetrics.scrollScaleStage3 - UiverseMenuMetrics.scrollScaleStage2)
        }
        return UiverseMenuMetrics.scrollScaleStage3
    }
}

/// Adaptive glass palette — neutral system glass in Light/Dark, accent only on active tab.
private struct UiverseMenuPalette {
    let colorScheme: ColorScheme

    var menuBackground: Color {
        Color(uiColor: UIColor { traits in
            traits.userInterfaceStyle == .dark
                ? UIColor(red: 0.14, green: 0.15, blue: 0.19, alpha: 0.58)
                : UIColor(white: 1.0, alpha: 0.68)
        })
    }

    var inactiveColor: Color {
        Color(uiColor: UIColor { traits in
            traits.userInterfaceStyle == .dark
                ? UIColor(white: 1, alpha: 0.74)
                : UIColor(white: 0.42, alpha: 0.88)
        })
    }

    var activeColor: Color {
        Color(uiColor: UIColor { traits in
            traits.userInterfaceStyle == .dark
                ? UIColor(red: 0.45, green: 0.78, blue: 1, alpha: 0.96)
                : UIColor(red: 0, green: 0.478, blue: 1, alpha: 0.9)
        })
    }

    var activeBackground: Color {
        Color(uiColor: UIColor { traits in
            traits.userInterfaceStyle == .dark
                ? UIColor(white: 1, alpha: 0.14)
                : UIColor(white: 0.92, alpha: 0.55)
        })
    }

    var glassBorder: Color {
        Color(uiColor: UIColor { traits in
            traits.userInterfaceStyle == .dark
                ? UIColor(white: 1, alpha: 0.12)
                : UIColor(white: 1, alpha: 0.35)
        })
    }

    var menuShadow: Color {
        Color(uiColor: UIColor { traits in
            traits.userInterfaceStyle == .dark
                ? UIColor(white: 0, alpha: 0.42)
                : UIColor(white: 0, alpha: 0.06)
        })
    }

    var insetHighlight: Color {
        Color(uiColor: UIColor { traits in
            traits.userInterfaceStyle == .dark
                ? UIColor(white: 1, alpha: 0.08)
                : UIColor(white: 1, alpha: 0.4)
        })
    }

    static let springAnimation = Animation.timingCurve(0.34, 1.56, 0.64, 1, duration: 0.18)
}

struct UiverseMenuBarItem: Identifiable {
    let tag: Int
    let icon: String
    let title: String
    var badge: Int = 0

    var id: Int { tag }
}

/// Literal port of the Uiverse.io mymiamo `.menu` bottom navigation reference.
struct UiverseMenuBarView: View {
    let items: [UiverseMenuBarItem]
    @Binding var selection: Int
    let reduceMotion: Bool

    @Environment(\.colorScheme) private var colorScheme

    private var palette: UiverseMenuPalette {
        UiverseMenuPalette(colorScheme: colorScheme)
    }

    var body: some View {
        HStack(spacing: 0) {
            Spacer(minLength: UiverseMenuMetrics.horizontalMargin)
            menuContainer
                .frame(maxWidth: UiverseMenuMetrics.maxWidth)
            Spacer(minLength: UiverseMenuMetrics.horizontalMargin)
        }
        .accessibilityElement(children: .contain)
    }

    private var menuContainer: some View {
        HStack(spacing: UiverseMenuMetrics.itemGap) {
            ForEach(items) { item in
                menuItem(item)
            }
        }
        .padding(UiverseMenuMetrics.menuPadding)
        .background { UiverseMenuGlassBackground(palette: palette) }
        .clipShape(Capsule())
        .overlay { UiverseMenuInsetHighlightOverlay(palette: palette) }
        .overlay {
            Capsule()
                .strokeBorder(palette.glassBorder, lineWidth: 1)
        }
        .shadow(color: palette.menuShadow, radius: 16, x: 0, y: 6)
        .animation(reduceMotion ? nil : UiverseMenuPalette.springAnimation, value: colorScheme)
    }

    private func menuItem(_ item: UiverseMenuBarItem) -> some View {
        let isActive = selection == item.tag
        return Button {
            guard selection != item.tag else { return }
            selection = item.tag
            PAXHaptics.light()
        } label: {
            ZStack(alignment: .topTrailing) {
                VStack(spacing: UiverseMenuMetrics.labelMarginTop) {
                    PAXIcon(
                        item.icon,
                        size: .tab,
                        tint: isActive ? palette.activeColor : palette.inactiveColor
                    )
                    Text(item.title)
                        .font(.system(size: UiverseMenuMetrics.labelFontSize, weight: .semibold))
                        .lineLimit(1)
                        .minimumScaleFactor(0.7)
                }
                if item.badge > 0 {
                    Text(item.badge > 99 ? "99+" : "\(item.badge)")
                        .font(.system(size: 9, weight: .bold))
                        .foregroundStyle(.white)
                        .padding(.horizontal, item.badge > 9 ? 4 : 3)
                        .padding(.vertical, 2)
                        .background(Color.red)
                        .clipShape(Capsule())
                        .offset(x: 8, y: -2)
                }
            }
            .foregroundStyle(isActive ? palette.activeColor : palette.inactiveColor)
            .frame(maxWidth: .infinity)
            .padding(.vertical, UiverseMenuMetrics.itemPaddingVertical)
            .padding(.horizontal, UiverseMenuMetrics.itemPaddingHorizontal)
            .background {
                Capsule()
                    .fill(isActive ? palette.activeBackground : Color.clear)
            }
            .animation(reduceMotion ? nil : UiverseMenuPalette.springAnimation, value: isActive)
            .animation(reduceMotion ? nil : UiverseMenuPalette.springAnimation, value: colorScheme)
        }
        .buttonStyle(UiverseMenuItemButtonStyle(reduceMotion: reduceMotion))
        .accessibilityLabel(item.title)
        .accessibilityValue(isActive ? L10n.ShellSelected : "")
    }
}

// MARK: - Glass background (neutral system material + subtle tint)

private struct UiverseMenuGlassBackground: View {
    let palette: UiverseMenuPalette
    @Environment(\.colorScheme) private var colorScheme

    var body: some View {
        ZStack {
            Rectangle()
                .fill(colorScheme == .dark ? .regularMaterial : .thinMaterial)
            palette.menuBackground
        }
    }
}

/// Reference `.menu::after` inset highlight shadows (no top separator line).
private struct UiverseMenuInsetHighlightOverlay: View {
    let palette: UiverseMenuPalette

    var body: some View {
        Capsule()
            .fill(
                LinearGradient(
                    colors: [palette.insetHighlight, Color.clear],
                    startPoint: .topLeading,
                    endPoint: .center
                )
            )
            .padding(2)
            .blur(radius: 2)
            .mask(Capsule())
            .allowsHitTesting(false)
    }
}

// MARK: - Item interaction (reference :active scale(0.98), :hover spring transitions)

private struct UiverseMenuItemButtonStyle: ButtonStyle {
    let reduceMotion: Bool

    func makeBody(configuration: Configuration) -> some View {
        configuration.label
            .scaleEffect(configuration.isPressed ? 0.98 : 1)
            .animation(reduceMotion ? nil : UiverseMenuPalette.springAnimation, value: configuration.isPressed)
    }
}
