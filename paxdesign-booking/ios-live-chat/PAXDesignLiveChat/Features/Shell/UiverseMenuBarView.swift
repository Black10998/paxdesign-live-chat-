import SwiftUI

// MARK: - Reference CSS tokens (Uiverse.io by mymiamo)

enum UiverseMenuMetrics {
    static let horizontalMargin: CGFloat = 10
    static let maxWidth: CGFloat = 520
    /// Small gap above the home indicator (native iOS tab bar feel).
    static let bottomOffset: CGFloat = 4
    static let menuPadding: CGFloat = 8
    static let itemGap: CGFloat = 8
    static let itemPaddingVertical: CGFloat = 10
    static let itemPaddingHorizontal: CGFloat = 6
    static let iconSize: CGFloat = 22.4
    static let labelFontSize: CGFloat = 12.8
    static let labelMarginTop: CGFloat = 4
    static let pillRadius: CGFloat = 9_999

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

/// Adaptive glass palette — same Apple glass style, colors follow system appearance.
private struct UiverseMenuPalette {
    let colorScheme: ColorScheme

    var menuBackground: Color {
        Color(red: 0, green: 122 / 255, blue: 1, opacity: colorScheme == .dark ? 0.34 : 0.404)
    }

    var inactiveColor: Color {
        colorScheme == .dark
            ? Color.white.opacity(0.88)
            : Color.white.opacity(0.9)
    }

    var activeColor: Color {
        Color(red: 0, green: 122 / 255, blue: 1, opacity: colorScheme == .dark ? 0.95 : 0.9)
    }

    var activeBackground: Color {
        colorScheme == .dark
            ? Color.white.opacity(0.16)
            : Color(red: 237 / 255, green: 237 / 255, blue: 237 / 255, opacity: 0.6)
    }

    var glassBorder: Color {
        colorScheme == .dark
            ? Color.white.opacity(0.2)
            : Color.white.opacity(0.35)
    }

    var menuShadow: Color {
        colorScheme == .dark
            ? Color.black.opacity(0.28)
            : Color.black.opacity(0.06)
    }

    var insetHighlight: Color {
        colorScheme == .dark
            ? Color.white.opacity(0.22)
            : Color.white.opacity(0.4)
    }

    static let springAnimation = Animation.timingCurve(0.34, 1.56, 0.64, 1, duration: 0.18)
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

    var body: some View {
        ZStack {
            UiverseMenuBackdropBlur()
            palette.menuBackground
        }
    }
}

private struct UiverseMenuBackdropBlur: UIViewRepresentable {
    @Environment(\.colorScheme) private var colorScheme

    func makeUIView(context: Context) -> UIVisualEffectView {
        let view = UIVisualEffectView(effect: blurEffect)
        view.backgroundColor = .clear
        return view
    }

    func updateUIView(_ uiView: UIVisualEffectView, context: Context) {
        uiView.effect = blurEffect
    }

    private var blurEffect: UIBlurEffect {
        UIBlurEffect(style: colorScheme == .dark ? .systemUltraThinMaterialDark : .systemUltraThinMaterialLight)
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
