import SwiftUI

/// Literal SVG paths from the Uiverse.io team chat composer reference (24×24 viewBox).
enum TeamComposerSVGIcons {
    static func location(color: Color) -> some View {
        TeamComposerSVGIcon(color: color) { rect, stroke in
            let scale = min(rect.width, rect.height) / 24
            let offsetX = rect.midX - 12 * scale
            let offsetY = rect.midY - 12 * scale
            func p(_ x: CGFloat, _ y: CGFloat) -> CGPoint {
                CGPoint(x: offsetX + x * scale, y: offsetY + y * scale)
            }

            var circle = Path()
            circle.addEllipse(in: CGRect(
                x: p(9, 10).x,
                y: p(9, 10).y,
                width: 6 * scale,
                height: 6 * scale
            ))

            var cloud = Path()
            cloud.move(to: p(9.778, 21))
            cloud.addCurve(
                to: p(14.222, 21),
                control1: p(11.5, 21),
                control2: p(13, 21)
            )
            cloud.addCurve(
                to: p(20.053, 19.796),
                control1: p(16.8, 21),
                control2: p(18.9, 20.5)
            )
            cloud.addCurve(
                to: p(21.25, 7.667),
                control1: p(21.5, 17),
                control2: p(21.25, 12)
            )
            cloud.addCurve(
                to: p(14.222, 3),
                control1: p(20, 4),
                control2: p(17.5, 3)
            )
            cloud.addLine(to: p(10.954, 3))
            cloud.addCurve(
                to: p(8.921, 4.636),
                control1: p(9.8, 3),
                control2: p(9.1, 3.6)
            )
            cloud.addCurve(
                to: p(5.696, 5.761),
                control1: p(7.8, 5.7),
                control2: p(6.7, 5.9)
            )
            cloud.addCurve(
                to: p(2.75, 7.667),
                control1: p(4.2, 6.2),
                control2: p(3.2, 6.8)
            )
            cloud.addCurve(
                to: p(2, 13.364),
                control1: p(2, 8.767),
                control2: p(2, 10.3)
            )
            cloud.addCurve(
                to: p(5.096, 21),
                control1: p(2, 18),
                control2: p(3.2, 20.2)
            )
            cloud.addLine(to: p(9.778, 21))
            cloud.closeSubpath()

            return Group {
                circle.stroke(color, style: stroke)
                cloud.stroke(color, style: stroke)
            }
        }
    }

    static func photo(color: Color) -> some View {
        TeamComposerSVGIcon(color: color) { rect, stroke in
            let scale = min(rect.width, rect.height) / 24
            let offsetX = rect.midX - 12 * scale
            let offsetY = rect.midY - 12 * scale
            func p(_ x: CGFloat, _ y: CGFloat) -> CGPoint {
                CGPoint(x: offsetX + x * scale, y: offsetY + y * scale)
            }

            let frameRect = CGRect(x: p(3, 3).x, y: p(3, 3).y, width: 18 * scale, height: 18 * scale)
            var frame = Path(roundedRect: frameRect, cornerRadius: 2 * scale, style: .continuous)

            var sun = Path()
            sun.addEllipse(in: CGRect(x: p(7, 7).x, y: p(7, 7).y, width: 4 * scale, height: 4 * scale))

            var mountain = Path()
            mountain.move(to: p(21, 15))
            mountain.addLine(to: p(17.914, 11.914))
            mountain.addCurve(to: p(15.086, 11.914), control1: p(16.8, 11.2), control2: p(16.2, 11.2))
            mountain.addLine(to: p(6, 21))

            return Group {
                frame.stroke(color, style: stroke)
                sun.stroke(color, style: stroke)
                mountain.stroke(color, style: stroke)
            }
        }
    }

    static func file(color: Color) -> some View {
        TeamComposerSVGIcon(color: color) { rect, stroke in
            let scale = min(rect.width, rect.height) / 24
            let offsetX = rect.midX - 12 * scale
            let offsetY = rect.midY - 12 * scale
            func p(_ x: CGFloat, _ y: CGFloat) -> CGPoint {
                CGPoint(x: offsetX + x * scale, y: offsetY + y * scale)
            }

            var folder = Path()
            folder.move(to: p(6, 14))
            folder.addLine(to: p(7.5, 11.1))
            folder.addCurve(to: p(9.24, 10), control1: p(7.9, 10.4), control2: p(8.5, 10))
            folder.addLine(to: p(20, 10))
            folder.addCurve(to: p(21.94, 12.5), control1: p(21.2, 10), control2: p(21.9, 11.1))
            folder.addLine(to: p(20.4, 18.5))
            folder.addCurve(to: p(18.45, 20), control1: p(20.2, 19.4), control2: p(19.4, 20))
            folder.addLine(to: p(4, 20))
            folder.addCurve(to: p(2, 18), control1: p(2.9, 20), control2: p(2, 19.1))
            folder.addLine(to: p(2, 5))
            folder.addCurve(to: p(4, 3), control1: p(2, 3.9), control2: p(2.9, 3))
            folder.addLine(to: p(7.9, 3))
            folder.addCurve(to: p(9.59, 3.9), control1: p(8.6, 3), control2: p(9.2, 3.3))
            folder.addLine(to: p(10.4, 5.1))
            folder.addCurve(to: p(12.07, 6), control1: p(10.8, 5.6), control2: p(11.4, 6))
            folder.addLine(to: p(18, 6))
            folder.addCurve(to: p(20, 8), control1: p(19.1, 6), control2: p(20, 6.9))
            folder.addLine(to: p(20, 10))

            return folder.stroke(color, style: stroke)
        }
    }

    static func plus(color: Color) -> some View {
        TeamComposerSVGIcon(color: color) { rect, stroke in
            let scale = min(rect.width, rect.height) / 24
            let offsetX = rect.midX - 12 * scale
            let offsetY = rect.midY - 12 * scale
            func p(_ x: CGFloat, _ y: CGFloat) -> CGPoint {
                CGPoint(x: offsetX + x * scale, y: offsetY + y * scale)
            }

            var path = Path()
            path.move(to: p(5, 12))
            path.addLine(to: p(19, 12))
            path.move(to: p(12, 5))
            path.addLine(to: p(12, 19))

            return path.stroke(color, style: stroke)
        }
    }

    static func voiceWaveform(color: Color) -> some View {
        TeamComposerSVGIcon(color: color) { rect, stroke in
            let scale = min(rect.width, rect.height) / 24
            let offsetX = rect.midX - 12 * scale
            let offsetY = rect.midY - 12 * scale
            func p(_ x: CGFloat, _ y: CGFloat) -> CGPoint {
                CGPoint(x: offsetX + x * scale, y: offsetY + y * scale)
            }

            var path = Path()
            path.move(to: p(12, 4))
            path.addLine(to: p(12, 20))
            path.move(to: p(16, 7))
            path.addLine(to: p(16, 17))
            path.move(to: p(8, 7))
            path.addLine(to: p(8, 17))
            path.move(to: p(20, 11))
            path.addLine(to: p(20, 13))
            path.move(to: p(4, 11))
            path.addLine(to: p(4, 13))

            return path.stroke(color, style: stroke)
        }
    }

    static func sendArrow(color: Color) -> some View {
        TeamComposerSVGIcon(color: color) { rect, stroke in
            let scale = min(rect.width, rect.height) / 24
            let offsetX = rect.midX - 12 * scale
            let offsetY = rect.midY - 12 * scale
            func p(_ x: CGFloat, _ y: CGFloat) -> CGPoint {
                CGPoint(x: offsetX + x * scale, y: offsetY + y * scale)
            }

            var path = Path()
            path.move(to: p(5, 12))
            path.addLine(to: p(12, 5))
            path.addLine(to: p(19, 12))
            path.move(to: p(12, 5))
            path.addLine(to: p(12, 19))

            return path.stroke(color, style: stroke)
        }
    }
}

private struct TeamComposerSVGIcon<Content: View>: View {
    let color: Color
    let content: (CGRect, StrokeStyle) -> Content

    var body: some View {
        GeometryReader { proxy in
            let rect = proxy.frame(in: .local)
            let stroke = StrokeStyle(lineWidth: 2, lineCap: .round, lineJoin: .round)
            content(rect, stroke)
        }
        .aspectRatio(1, contentMode: .fit)
    }
}
