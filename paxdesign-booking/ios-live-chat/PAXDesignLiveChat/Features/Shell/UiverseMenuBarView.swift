import SwiftUI

// MARK: - Revolut-style bottom tab bar (replaces floating Uiverse capsule)

enum UiverseMenuMetrics {
    static let tabBarContentHeight: CGFloat = 56
    static let iconSize: CGFloat = 24
    static let labelFontSize: CGFloat = 10
    static let labelMarginTop: CGFloat = 4

    static var itemContentHeight: CGFloat {
        iconSize + labelMarginTop + labelFontSize
    }

    static var menuHeight: CGFloat {
        tabBarContentHeight
    }

    static var scrollInset: CGFloat {
        menuHeight + 12
    }

    // Legacy scroll-shrink metrics (kept for API compatibility)
    static let scrollScaleFull: CGFloat = 1.0
    static let scrollScaleStage2: CGFloat = 1.0
    static let scrollScaleStage3: CGFloat = 1.0
    static let scrollCompressionStart: CGFloat = 8
    static let scrollStage1Distance: CGFloat = 50
    static let scrollStage2Distance: CGFloat = 80
    static let horizontalMargin: CGFloat = 0
    static let maxWidth: CGFloat = .infinity
    static let homeIndicatorGap: CGFloat = 0
    static let menuPadding: CGFloat = 0
    static let itemGap: CGFloat = 0
    static let itemPaddingVertical: CGFloat = 6
    static let itemPaddingHorizontal: CGFloat = 0
    static let pillRadius: CGFloat = 0
}

@MainActor
final class UiverseMenuScrollState: ObservableObject {
    @Published private(set) var barScale: CGFloat = UiverseMenuMetrics.scrollScaleFull

    func ingestScrollOffset(_ offset: CGFloat, reduceMotion: Bool) {
        // Revolut tab bar stays fixed — no scroll shrink.
    }

    func reset(reduceMotion: Bool) {
        barScale = UiverseMenuMetrics.scrollScaleFull
    }

    static func scale(forScrollOffset offset: CGFloat) -> CGFloat {
        UiverseMenuMetrics.scrollScaleFull
    }
}

struct UiverseMenuBarItem: Identifiable {
    let tag: Int
    let icon: String
    let title: String
    var badge: Int = 0

    var id: Int { tag }
}

/// Full-width Revolut tab bar — blur material, 56pt, accent active indicator.
struct UiverseMenuBarView: View {
    let items: [UiverseMenuBarItem]
    @Binding var selection: Int
    let reduceMotion: Bool

    @Environment(\.colorScheme) private var colorScheme

    private var isDark: Bool { colorScheme == .dark }

    var body: some View {
        VStack(spacing: 0) {
            Divider()
                .background(PAXRevolutColors.divider(isDark: isDark))

            HStack(spacing: 0) {
                ForEach(items) { item in
                    tabItem(item)
                }
            }
            .frame(height: UiverseMenuMetrics.tabBarContentHeight)
            .padding(.horizontal, PAXSpacing.xxs)
        }
        .background(tabBarBackground)
        .accessibilityElement(children: .contain)
    }

    @ViewBuilder
    private var tabBarBackground: some View {
        ZStack {
            if isDark {
                Rectangle().fill(.regularMaterial)
            } else {
                Rectangle().fill(.thinMaterial)
            }
            PAXRevolutColors.canvas(isDark: isDark).opacity(isDark ? 0.92 : 0.96)
        }
        .ignoresSafeArea(edges: .bottom)
    }

    private func tabItem(_ item: UiverseMenuBarItem) -> some View {
        let isActive = selection == item.tag
        return Button {
            guard selection != item.tag else { return }
            selection = item.tag
            PAXHaptics.light()
        } label: {
            VStack(spacing: UiverseMenuMetrics.labelMarginTop) {
                ZStack(alignment: .topTrailing) {
                    VStack(spacing: 3) {
                        if isActive {
                            Capsule()
                                .fill(PAXTheme.accent)
                                .frame(width: 20, height: 2)
                        } else {
                            Color.clear.frame(height: 2)
                        }
                        PAXIcon(
                            item.icon,
                            size: .tab,
                            tint: isActive
                                ? PAXTheme.accent
                                : PAXRevolutColors.textTertiary(isDark: isDark)
                        )
                    }

                    if item.badge > 0 {
                        Text(item.badge > 99 ? "99+" : "\(item.badge)")
                            .font(.system(size: 9, weight: .bold))
                            .foregroundStyle(PAXTheme.onAccent)
                            .padding(.horizontal, item.badge > 9 ? 4 : 3)
                            .padding(.vertical, 2)
                            .background(PAXTheme.danger)
                            .clipShape(Capsule())
                            .offset(x: 10, y: -4)
                    }
                }

                Text(item.title)
                    .font(.system(size: UiverseMenuMetrics.labelFontSize, weight: .semibold))
                    .foregroundStyle(
                        isActive
                            ? PAXRevolutColors.textPrimary(isDark: isDark)
                            : PAXRevolutColors.textTertiary(isDark: isDark)
                    )
                    .lineLimit(1)
                    .minimumScaleFactor(0.7)
            }
            .frame(maxWidth: .infinity)
            .padding(.vertical, UiverseMenuMetrics.itemPaddingVertical)
        }
        .buttonStyle(PAXRevolutPressableStyle())
        .accessibilityLabel(item.title)
        .accessibilityValue(isActive ? L10n.ShellSelected : "")
    }
}
