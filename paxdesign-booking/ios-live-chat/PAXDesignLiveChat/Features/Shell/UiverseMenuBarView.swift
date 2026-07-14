import SwiftUI

// MARK: - Reference CSS tokens (Uiverse.io by mymiamo)

enum UiverseMenuMetrics {
    static let horizontalMargin: CGFloat = 10
    static let maxWidth: CGFloat = 520
    static let bottomOffset: CGFloat = 12
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

private enum UiverseMenuStyle {
    static let glassBorder = Color.white.opacity(0.35)
    static let menuBackground = Color(red: 0, green: 122 / 255, blue: 1, opacity: 0.404)
    static let inactiveColor = Color.white.opacity(0.9)
    static let activeColor = Color(red: 0, green: 122 / 255, blue: 1, opacity: 0.9)
    static let activeBackground = Color(red: 237 / 255, green: 237 / 255, blue: 237 / 255, opacity: 0.6)
    static let hoverBackground = Color.white.opacity(0.3)
    static let hoverColor = Color(red: 0, green: 122 / 255, blue: 1, opacity: 0.7)
    static let menuShadow = Color.black.opacity(0.06)

    static let springAnimation = Animation.timingCurve(0.34, 1.56, 0.64, 1, duration: 0.18)
    static let shadowAnimation = Animation.easeInOut(duration: 0.3)
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
        .background { UiverseMenuGlassBackground() }
        .clipShape(Capsule())
        .overlay { UiverseMenuInsetHighlightOverlay() }
        .overlay {
            Capsule()
                .strokeBorder(UiverseMenuStyle.glassBorder, lineWidth: 1)
        }
        .shadow(color: UiverseMenuStyle.menuShadow, radius: 30, x: 0, y: 10)
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
                    color: isActive ? UiverseMenuStyle.activeColor : UiverseMenuStyle.inactiveColor,
                    size: UiverseMenuMetrics.iconSize
                )
                Text(item.title)
                    .font(.system(size: UiverseMenuMetrics.labelFontSize, weight: .semibold))
                    .lineLimit(1)
                    .minimumScaleFactor(0.7)
            }
            .foregroundStyle(isActive ? UiverseMenuStyle.activeColor : UiverseMenuStyle.inactiveColor)
            .frame(maxWidth: .infinity)
            .padding(.vertical, UiverseMenuMetrics.itemPaddingVertical)
            .padding(.horizontal, UiverseMenuMetrics.itemPaddingHorizontal)
            .background {
                Capsule()
                    .fill(isActive ? UiverseMenuStyle.activeBackground : Color.clear)
            }
            .animation(reduceMotion ? nil : UiverseMenuStyle.springAnimation, value: isActive)
        }
        .buttonStyle(UiverseMenuItemButtonStyle(reduceMotion: reduceMotion))
        .accessibilityLabel(item.title)
        .accessibilityValue(isActive ? L10n.ShellSelected : "")
    }
}

// MARK: - Glass background (backdrop-filter: blur(12px) saturate(180%) contrast(200%))

private struct UiverseMenuGlassBackground: View {
    var body: some View {
        ZStack {
            UiverseMenuBackdropBlur()
            UiverseMenuStyle.menuBackground
        }
    }
}

private struct UiverseMenuBackdropBlur: UIViewRepresentable {
    func makeUIView(context: Context) -> UIVisualEffectView {
        let view = UIVisualEffectView(effect: UIBlurEffect(style: .systemUltraThinMaterial))
        view.backgroundColor = .clear
        return view
    }

    func updateUIView(_ uiView: UIVisualEffectView, context: Context) {}
}

/// Reference `.menu::after` inset highlight shadows.
private struct UiverseMenuInsetHighlightOverlay: View {
    var body: some View {
        Capsule()
            .strokeBorder(Color.clear, lineWidth: 0)
            .background {
                Capsule()
                    .fill(
                        LinearGradient(
                            colors: [Color.white.opacity(0.4), Color.clear],
                            startPoint: .topLeading,
                            endPoint: .center
                        )
                    )
                    .padding(2)
                    .blur(radius: 2)
                    .mask(Capsule())
            }
            .overlay(alignment: .topLeading) {
                Capsule()
                    .fill(Color.white.opacity(0.2))
                    .frame(height: 2)
                    .padding(.horizontal, 8)
                    .offset(y: 1)
            }
            .allowsHitTesting(false)
    }
}

// MARK: - Item interaction (reference :active scale(0.98), :hover spring transitions)

private struct UiverseMenuItemButtonStyle: ButtonStyle {
    let reduceMotion: Bool

    func makeBody(configuration: Configuration) -> some View {
        configuration.label
            .scaleEffect(configuration.isPressed ? 0.98 : 1)
            .animation(reduceMotion ? nil : UiverseMenuStyle.springAnimation, value: configuration.isPressed)
    }
}
