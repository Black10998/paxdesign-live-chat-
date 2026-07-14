import SwiftUI

// MARK: - Reference CSS tokens (Uiverse.io by mymiamo)

enum UiverseMenuMetrics {
    static let horizontalMargin: CGFloat = 10
    static let maxWidth: CGFloat = 520
    /// Flush with safe-area padding for a native iOS tab bar feel.
    static let bottomOffset: CGFloat = 0
    static let menuPadding: CGFloat = 8
    static let itemGap: CGFloat = 8
    static let itemPaddingVertical: CGFloat = 10
    static let itemPaddingHorizontal: CGFloat = 6
    static let iconSize: CGFloat = 22.4
    static let labelFontSize: CGFloat = 12.8
    static let labelMarginTop: CGFloat = 4
    static let pillRadius: CGFloat = 9_999
    static let scrollCompressedScale: CGFloat = 0.94

    static var itemContentHeight: CGFloat {
        iconSize + labelMarginTop + labelFontSize
    }

    static var itemHeight: CGFloat {
        itemPaddingVertical * 2 + itemContentHeight
    }

    static var menuHeight: CGFloat {
        menuPadding * 2 + itemHeight
    }

    static var scrollInset: CGFloat {
        bottomOffset + menuHeight + 12
    }
}

// MARK: - Native scroll shrink state

@MainActor
final class UiverseMenuScrollState: ObservableObject {
    @Published private(set) var barScale: CGFloat = 1

    private var lastOffset: CGFloat = 0

    func ingestScrollOffset(_ offset: CGFloat, reduceMotion: Bool) {
        let delta = offset - lastOffset
        lastOffset = offset

        let target: CGFloat
        if offset <= 8 {
            target = 1
        } else if delta > 1.5 {
            target = UiverseMenuMetrics.scrollCompressedScale
        } else if delta < -1.5 {
            target = 1
        } else {
            return
        }
        applyScale(target, reduceMotion: reduceMotion)
    }

    func reset(reduceMotion: Bool) {
        lastOffset = 0
        applyScale(1, reduceMotion: reduceMotion)
    }

    private func applyScale(_ scale: CGFloat, reduceMotion: Bool) {
        guard abs(barScale - scale) > 0.001 else { return }
        if reduceMotion {
            barScale = scale
        } else {
            withAnimation(.spring(response: 0.38, dampingFraction: 0.84)) {
                barScale = scale
            }
        }
    }
}

/// Adaptive glass palette — distinct Light and Dark appearances, same structure.
private struct UiverseMenuPalette {
    let colorScheme: ColorScheme

    var menuBackground: Color {
        Color(uiColor: UIColor { traits in
            traits.userInterfaceStyle == .dark
                ? UIColor(red: 0.04, green: 0.22, blue: 0.42, alpha: 0.72)
                : UIColor(red: 0, green: 0.478, blue: 1, alpha: 0.404)
        })
    }

    var inactiveColor: Color {
        Color(uiColor: UIColor { traits in
            traits.userInterfaceStyle == .dark
                ? UIColor(white: 1, alpha: 0.74)
                : UIColor(white: 1, alpha: 0.9)
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
                : UIColor(red: 237 / 255, green: 237 / 255, blue: 237 / 255, alpha: 0.6)
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
                ? UIColor(white: 1, alpha: 0.1)
                : UIColor(white: 1, alpha: 0.4)
        })
    }

    static let springAnimation = Animation.timingCurve(0.34, 1.56, 0.64, 1, duration: 0.18)
    static let scrollScaleAnimation = Animation.spring(response: 0.38, dampingFraction: 0.84)
}

struct UiverseMenuBarItem: Identifiable {
    let tag: Int
    let glyph: UiverseMenuIcons.Glyph
    let title: String

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
        .padding(.bottom, UiverseMenuMetrics.bottomOffset)
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
        .shadow(color: palette.menuShadow, radius: 30, x: 0, y: 10)
        .animation(reduceMotion ? nil : UiverseMenuPalette.springAnimation, value: colorScheme)
    }

    private func menuItem(_ item: UiverseMenuBarItem) -> some View {
        let isActive = selection == item.tag
        return Button {
            guard selection != item.tag else { return }
            selection = item.tag
            PAXHaptics.light()
        } label: {
            VStack(spacing: UiverseMenuMetrics.labelMarginTop) {
                UiverseMenuIcons.icon(
                    item.glyph,
                    color: isActive ? palette.activeColor : palette.inactiveColor,
                    size: UiverseMenuMetrics.iconSize
                )
                Text(item.title)
                    .font(.system(size: UiverseMenuMetrics.labelFontSize, weight: .semibold))
                    .lineLimit(1)
                    .minimumScaleFactor(0.7)
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

// MARK: - Glass background (backdrop-filter: blur(12px) saturate(180%) contrast(200%))

private struct UiverseMenuGlassBackground: View {
    let palette: UiverseMenuPalette
    @Environment(\.colorScheme) private var colorScheme

    var body: some View {
        ZStack {
            Rectangle()
                .fill(colorScheme == .dark ? .ultraThinMaterial : .thinMaterial)
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
