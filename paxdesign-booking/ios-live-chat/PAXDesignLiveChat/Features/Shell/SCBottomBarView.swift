import SwiftUI

// MARK: - Reference timing (literal CSS cubic-bezier + duration)

private enum SCBarMotion {
    static let bar = Animation.timingCurve(0.57, 0.23, 0.08, 0.96, duration: 0.45)
    static let indicator = Animation.timingCurve(0.45, 0.73, 0, 0.59, duration: 0.3)
    static let item = Animation.easeInOut(duration: 0.5)
}

private struct SCItemCenterKey: PreferenceKey {
    static var defaultValue: [Int: CGFloat] = [:]
    static func reduce(value: inout [Int: CGFloat], nextValue: () -> [Int: CGFloat]) {
        value.merge(nextValue(), uniquingKeysWith: { $1 })
    }
}

struct SCBottomBarItem: Identifiable {
    let tag: Int
    let glyph: SCBottomBarIcons.Glyph
    let title: String
    var badgeCount: Int = 0

    var id: Int { tag }
}

/// Literal port of the `.sc-bottom-bar` reference — dark glossy glass theme only.
struct SCBottomBarView: View {
    let items: [SCBottomBarItem]
    @Binding var selection: Int
    let reduceMotion: Bool

    @State private var itemCenters: [Int: CGFloat] = [:]

    /// `menu_position = offsetLeft - 16` from the reference script.
    private var indicatorLeft: CGFloat {
        guard let center = itemCenters[selection] else { return 0 }
        return center - 16
    }

    /// `backgroundPosition = menu_position - 8` from the reference script.
    private var gradientFocusX: CGFloat {
        indicatorLeft - 8
    }

    var body: some View {
        ZStack(alignment: .bottomLeading) {
            SCBottomBarGlassBackground(gradientFocusX: gradientFocusX)
                .frame(height: SCBarMetrics.barHeight)
                .animation(reduceMotion ? nil : SCBarMotion.bar, value: gradientFocusX)

            SCBottomBarIndicator()
                .offset(x: indicatorLeft, y: -SCBarMetrics.indicatorBottomOffset)
                .animation(reduceMotion ? nil : SCBarMotion.indicator, value: indicatorLeft)

            HStack(spacing: 0) {
                ForEach(items) { item in
                    menuItem(item)
                }
            }
            .padding(.horizontal, SCBarMetrics.horizontalPadding)
            .padding(.vertical, SCBarMetrics.verticalPadding)
            .frame(maxWidth: .infinity, height: SCBarMetrics.barHeight, alignment: .bottom)
        }
        .frame(maxWidth: .infinity)
        .frame(height: SCBarMetrics.totalHeight, alignment: .bottom)
        .coordinateSpace(name: "sc-bottom-bar")
        .onPreferenceChange(SCItemCenterKey.self) { centers in
            itemCenters = centers
        }
        .accessibilityElement(children: .contain)
    }

    private func menuItem(_ item: SCBottomBarItem) -> some View {
        let isCurrent = selection == item.tag
        return Button {
            guard selection != item.tag else { return }
            if reduceMotion {
                selection = item.tag
            } else {
                withAnimation(SCBarMotion.indicator) {
                    selection = item.tag
                }
            }
            PAXHaptics.light()
        } label: {
            ZStack(alignment: .topTrailing) {
                SCBottomBarIcons.icon(
                    item.glyph,
                    color: isCurrent ? .white : Color.white.opacity(0.65),
                    size: SCBarMetrics.iconSize
                )
                .offset(y: isCurrent ? -SCBarMetrics.selectedLift : 0)
                .zIndex(isCurrent ? 3 : 1)
                .animation(reduceMotion ? nil : SCBarMotion.item, value: isCurrent)

                if item.badgeCount > 0, !isCurrent {
                    Text("\(min(item.badgeCount, 99))")
                        .font(.system(size: 9, weight: .bold))
                        .foregroundStyle(.white)
                        .padding(.horizontal, 4)
                        .padding(.vertical, 1)
                        .background(Capsule().fill(PAXTheme.danger))
                        .offset(x: 8, y: -6)
                }
            }
            .frame(maxWidth: .infinity)
            .frame(height: SCBarMetrics.iconRowHeight)
            .background(
                GeometryReader { geo in
                    Color.clear.preference(
                        key: SCItemCenterKey.self,
                        value: [item.tag: geo.frame(in: .named("sc-bottom-bar")).midX]
                    )
                }
            )
        }
        .buttonStyle(.plain)
        .accessibilityLabel(item.title)
        .accessibilityValue(isCurrent ? L10n.ShellSelected : "")
    }
}

enum SCBarMetrics {
    static let horizontalPadding: CGFloat = 36
    static let verticalPadding: CGFloat = 16
    static let iconRowHeight: CGFloat = 32
    static let iconSize: CGFloat = 26
    static let selectedLift: CGFloat = 22
    static let indicatorSize: CGFloat = 56
    static let indicatorBottomOffset: CGFloat = 28
    static let cornerRadius: CGFloat = 30
    static let radialHoleRadius: CGFloat = 36

    static var barHeight: CGFloat {
        iconRowHeight + verticalPadding * 2
    }

    static var totalHeight: CGFloat {
        indicatorBottomOffset + indicatorSize + 8
    }

    static var scrollInset: CGFloat {
        totalHeight + 12
    }
}

private struct SCBottomBarGlassBackground: View {
    let gradientFocusX: CGFloat

    private var holeCenterX: CGFloat {
        gradientFocusX + SCBarMetrics.radialHoleRadius
    }

    var body: some View {
        let shape = SCBottomBarRadialGlassShape(
            holeCenterX: holeCenterX,
            holeCenterY: 6,
            holeRadius: SCBarMetrics.radialHoleRadius,
            cornerRadius: SCBarMetrics.cornerRadius
        )

        ZStack(alignment: .top) {
            shape
                .fill(
                    LinearGradient(
                        colors: [
                            Color(red: 0.12, green: 0.12, blue: 0.14).opacity(0.94),
                            Color(red: 0.07, green: 0.07, blue: 0.09).opacity(0.98),
                        ],
                        startPoint: .top,
                        endPoint: .bottom
                    ),
                    style: FillStyle(eoFill: true)
                )
                .background(.ultraThinMaterial.opacity(0.55))

            shape
                .fill(
                    LinearGradient(
                        colors: [
                            Color.white.opacity(0.22),
                            Color.white.opacity(0.04),
                            Color.clear,
                        ],
                        startPoint: .top,
                        endPoint: .center
                    ),
                    style: FillStyle(eoFill: true)
                )
        }
        .shadow(color: Color.black.opacity(0.12), radius: 12, x: 0, y: -2)
        .shadow(color: Color.black.opacity(0.08), radius: 6, x: 0, y: -1)
    }
}

private struct SCBottomBarRadialGlassShape: Shape {
    var holeCenterX: CGFloat
    var holeCenterY: CGFloat
    var holeRadius: CGFloat
    var cornerRadius: CGFloat

    var animatableData: CGFloat {
        get { holeCenterX }
        set { holeCenterX = newValue }
    }

    func path(in rect: CGRect) -> Path {
        var path = Path()
        path.addRoundedRect(
            in: CGRect(x: 0, y: 0, width: rect.width, height: rect.height),
            cornerSize: CGSize(width: cornerRadius, height: cornerRadius),
            style: .continuous
        )
        path.addEllipse(
            in: CGRect(
                x: holeCenterX - holeRadius,
                y: holeCenterY - holeRadius,
                width: holeRadius * 2,
                height: holeRadius * 2
            )
        )
        return path
    }
}

private struct SCBottomBarIndicator: View {
    var body: some View {
        ZStack {
            Circle()
                .fill(
                    RadialGradient(
                        colors: [
                            Color(red: 0.22, green: 0.22, blue: 0.24),
                            Color.black,
                        ],
                        center: .topLeading,
                        startRadius: 4,
                        endRadius: 34
                    )
                )
            Circle()
                .stroke(Color.white.opacity(0.14), lineWidth: 1)
            Circle()
                .fill(
                    LinearGradient(
                        colors: [
                            Color.white.opacity(0.28),
                            Color.clear,
                        ],
                        startPoint: .top,
                        endPoint: .center
                    )
                )
                .padding(6)
                .clipShape(Circle())
        }
        .frame(width: SCBarMetrics.indicatorSize, height: SCBarMetrics.indicatorSize)
        .shadow(color: Color.black.opacity(0.12), radius: 12, x: 0, y: 3)
        .shadow(color: Color.black.opacity(0.08), radius: 6, x: 0, y: 3)
    }
}
