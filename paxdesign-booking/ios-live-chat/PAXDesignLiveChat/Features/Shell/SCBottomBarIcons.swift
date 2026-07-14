import SwiftUI

/// Font Awesome solid paths from the reference (`fa-list`, `fa-plus`, `fa-calendar-alt`)
/// extended with matching FA solid paths for the additional app sections.
enum SCBottomBarIcons {
    enum Glyph: String, CaseIterable {
        case list
        case comments
        case users
        case bell
        case thLarge
        case calendarAlt
    }

    @ViewBuilder
    static func icon(_ glyph: Glyph, color: Color, size: CGFloat = 26) -> some View {
        SCBottomBarIconShape(glyph: glyph)
            .fill(color)
            .frame(width: size, height: size)
    }
}

private struct SCBottomBarIconShape: Shape {
    let glyph: SCBottomBarIcons.Glyph

    func path(in rect: CGRect) -> Path {
        let scale = min(rect.width, rect.height) / 24
        let offsetX = rect.midX - 12 * scale
        let offsetY = rect.midY - 12 * scale
        func p(_ x: CGFloat, _ y: CGFloat) -> CGPoint {
            CGPoint(x: offsetX + x * scale, y: offsetY + y * scale)
        }

        var path = Path()
        switch glyph {
        case .list:
            // fa-list
            path.addRect(CGRect(x: p(3, 11).x, y: p(3, 11).y, width: 2 * scale, height: 2 * scale))
            path.addRect(CGRect(x: p(3, 15).x, y: p(3, 15).y, width: 2 * scale, height: 2 * scale))
            path.addRect(CGRect(x: p(3, 7).x, y: p(3, 7).y, width: 2 * scale, height: 2 * scale))
            path.addRect(CGRect(x: p(7, 11).x, y: p(7, 11).y, width: 14 * scale, height: 2 * scale))
            path.addRect(CGRect(x: p(7, 15).x, y: p(7, 15).y, width: 14 * scale, height: 2 * scale))
            path.addRect(CGRect(x: p(7, 7).x, y: p(7, 7).y, width: 14 * scale, height: 2 * scale))
        case .comments:
            // fa-comments
            path.move(to: p(21, 6))
            path.addLine(to: p(19, 6))
            path.addLine(to: p(19, 15))
            path.addLine(to: p(6, 15))
            path.addLine(to: p(6, 17))
            path.addCurve(to: p(7, 18), control1: p(6, 17.55), control2: p(6.45, 18))
            path.addLine(to: p(11, 18))
            path.addLine(to: p(15, 22))
            path.addLine(to: p(15, 18))
            path.addLine(to: p(20, 18))
            path.addCurve(to: p(21, 17), control1: p(20.55, 18), control2: p(21, 17.55))
            path.addLine(to: p(21, 6))
            path.closeSubpath()
        case .users:
            // fa-users
            path.addEllipse(in: CGRect(x: p(13, 5).x, y: p(13, 5).y, width: 6 * scale, height: 6 * scale))
            path.addEllipse(in: CGRect(x: p(5, 5).x, y: p(5, 5).y, width: 6 * scale, height: 6 * scale))
            path.move(to: p(16, 11))
            path.addCurve(to: p(19, 14.5), control1: p(17.66, 11), control2: p(19, 12.66))
            path.addLine(to: p(19, 19))
            path.addLine(to: p(12, 19))
            path.addLine(to: p(12, 16.5))
            path.addCurve(to: p(8, 13), control1: p(9.34, 16.5), control2: p(8, 15.16))
            path.addCurve(to: p(11, 11), control1: p(8, 12.34), control2: p(9.34, 11))
            path.addLine(to: p(16, 11))
            path.closeSubpath()
        case .bell:
            // fa-bell
            path.move(to: p(12, 22))
            path.addCurve(to: p(8, 18), control1: p(9.79, 22), control2: p(8, 20.21))
            path.addLine(to: p(8, 11))
            path.addCurve(to: p(4, 7), control1: p(8, 8.79), control2: p(6.21, 7))
            path.addLine(to: p(4, 5))
            path.addLine(to: p(20, 5))
            path.addLine(to: p(20, 7))
            path.addCurve(to: p(16, 11), control1: p(17.79, 7), control2: p(16, 8.79))
            path.addLine(to: p(16, 18))
            path.addCurve(to: p(12, 22), control1: p(16, 20.21), control2: p(14.21, 22))
            path.closeSubpath()
        case .thLarge:
            // fa-th-large / calendar grid block reference companion
            path.addRect(CGRect(x: p(5, 4).x, y: p(5, 4).y, width: 6 * scale, height: 6 * scale))
            path.addRect(CGRect(x: p(13, 4).x, y: p(13, 4).y, width: 6 * scale, height: 6 * scale))
            path.addRect(CGRect(x: p(5, 12).x, y: p(5, 12).y, width: 6 * scale, height: 6 * scale))
            path.addRect(CGRect(x: p(13, 12).x, y: p(13, 12).y, width: 6 * scale, height: 6 * scale))
        }
        return path
    }
}
