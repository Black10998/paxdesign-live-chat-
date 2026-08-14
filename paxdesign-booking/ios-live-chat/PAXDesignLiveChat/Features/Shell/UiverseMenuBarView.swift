import SwiftUI

enum UiverseMenuMetrics {
    static let horizontalMargin: CGFloat = 0
    static let maxWidth: CGFloat = .infinity
    static let homeIndicatorGap: CGFloat = 0
    static let menuPadding: CGFloat = 6
    static let itemGap: CGFloat = 0
    static let itemPaddingVertical: CGFloat = 4
    static let itemPaddingHorizontal: CGFloat = 0
    static let iconSize: CGFloat = 24
    static let labelFontSize: CGFloat = 10
    static let labelMarginTop: CGFloat = 4
    static let pillRadius: CGFloat = 10

    static let scrollScaleFull: CGFloat = 1.0
    static let scrollScaleStage2: CGFloat = 1.0
    static let scrollScaleStage3: CGFloat = 1.0
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
        PAXSpacing.tabBarHeight
    }

    static var scrollInset: CGFloat {
        menuHeight + 12
    }
}

@MainActor
final class UiverseMenuScrollState: ObservableObject {
    @Published private(set) var barScale: CGFloat = 1

    func ingestScrollOffset(_ offset: CGFloat, reduceMotion: Bool) {
        _ = offset
        _ = reduceMotion
    }

    func reset(reduceMotion: Bool) {
        barScale = 1
    }

    static func scale(forScrollOffset offset: CGFloat) -> CGFloat {
        _ = offset
        return 1
    }
}

struct UiverseMenuBarItem: Identifiable {
    let tag: Int
    let icon: String
    let title: String
    var badge: Int = 0

    var id: Int { tag }
}

/// Full-width Revolut-style tab bar: 56pt + home indicator, blur material, 24pt SF Symbols.
/// Active state is a soft circular fill + accent icon — no sliding line or capsule indicator.
struct UiverseMenuBarView: View {
    let items: [UiverseMenuBarItem]
    @Binding var selection: Int
    let reduceMotion: Bool

    var body: some View {
        VStack(spacing: 0) {
            Rectangle()
                .fill(PAXTheme.divider.opacity(0.55))
                .frame(height: PAXShellLayout.tabBarHairlineHeight)

            HStack(spacing: 0) {
                ForEach(items) { item in
                    tabItem(item)
                }
            }
            .padding(.top, 6)
            .padding(.bottom, 4)
            .frame(minHeight: PAXSpacing.tabBarHeight)
        }
        .background {
            Rectangle()
                .fill(.regularMaterial)
                .overlay(PAXTheme.background.opacity(0.78))
                .ignoresSafeArea(edges: .bottom)
        }
        .accessibilityElement(children: .contain)
    }

    private func tabItem(_ item: UiverseMenuBarItem) -> some View {
        let isActive = selection == item.tag
        return Button {
            guard selection != item.tag else { return }
            withAnimation(reduceMotion ? nil : PAXMotion.tabSelect) {
                selection = item.tag
            }
            PAXHaptics.light()
        } label: {
            VStack(spacing: 4) {
                ZStack(alignment: .topTrailing) {
                    ZStack {
                        Circle()
                            .fill(isActive ? PAXTheme.accent.opacity(0.16) : Color.clear)
                            .frame(width: 40, height: 40)
                        PAXIcon(
                            item.icon,
                            size: .tab,
                            tint: isActive ? PAXTheme.accent : PAXTheme.textTertiary
                        )
                        .scaleEffect(isActive ? 1.0 : 0.96)
                    }
                    .frame(width: 44, height: 36)

                    if item.badge > 0 {
                        Text(item.badge > 99 ? "99+" : "\(item.badge)")
                            .font(.system(size: 9, weight: .bold))
                            .foregroundStyle(.white)
                            .padding(.horizontal, item.badge > 9 ? 4 : 3)
                            .padding(.vertical, 2)
                            .background(Color(uiColor: PAXDynamic.spend))
                            .clipShape(Capsule())
                            .offset(x: 10, y: -4)
                    }
                }

                Text(item.title)
                    .font(PAXTypography.tabLabel)
                    .foregroundStyle(isActive ? PAXTheme.textPrimary : PAXTheme.textTertiary)
                    .lineLimit(1)
                    .minimumScaleFactor(0.7)
            }
            .frame(maxWidth: .infinity)
            .contentShape(Rectangle())
        }
        .buttonStyle(PAXRevolutPressableStyle())
        .animation(reduceMotion ? nil : PAXMotion.tabSelect, value: isActive)
        .accessibilityLabel(item.title)
        .accessibilityValue(isActive ? L10n.ShellSelected : "")
        .accessibilityAddTraits(isActive ? [.isSelected, .isButton] : .isButton)
    }
}
