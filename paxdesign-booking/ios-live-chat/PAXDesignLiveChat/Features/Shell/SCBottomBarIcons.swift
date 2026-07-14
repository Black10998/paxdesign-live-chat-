import SwiftUI

/// Font Awesome 5 solid SVG paths from the reference (`fa-list`, `fa-plus`, `fa-calendar-alt`)
/// plus matching FA solid paths for the additional app sections.
enum SCBottomBarIcons {
    enum Glyph: String, CaseIterable {
        case list
        case plus
        case comments
        case users
        case bell
        case calendarAlt
    }

    @ViewBuilder
    static func icon(_ glyph: Glyph, color: Color, size: CGFloat = 26) -> some View {
        SCBottomBarSVGIcon(glyph: glyph, color: color, size: size)
    }
}

// MARK: - Exact FA SVG assets

private enum SCBottomBarSVGAsset {
    let viewBox: CGSize
    let pathData: String

    static let list = SCBottomBarSVGAsset(
        viewBox: CGSize(width: 512, height: 512),
        pathData: "M80 368H16a16 16 0 0 0-16 16v64a16 16 0 0 0 16 16h64a16 16 0 0 0 16-16v-64a16 16 0 0 0-16-16zm0-320H16A16 16 0 0 0 0 64v64a16 16 0 0 0 16 16h64a16 16 0 0 0 16-16V64a16 16 0 0 0-16-16zm0 160H16a16 16 0 0 0-16 16v64a16 16 0 0 0 16 16h64a16 16 0 0 0 16-16v-64a16 16 0 0 0-16-16zm416 176H176a16 16 0 0 0-16 16v32a16 16 0 0 0 16 16h320a16 16 0 0 0 16-16v-32a16 16 0 0 0-16-16zm0-320H176a16 16 0 0 0-16 16v32a16 16 0 0 0 16 16h320a16 16 0 0 0 16-16V80a16 16 0 0 0-16-16zm0 160H176a16 16 0 0 0-16 16v32a16 16 0 0 0 16 16h320a16 16 0 0 0 16-16v-32a16 16 0 0 0-16-16z"
    )

    static let plus = SCBottomBarSVGAsset(
        viewBox: CGSize(width: 448, height: 512),
        pathData: "M416 208H272V64c0-17.67-14.33-32-32-32h-32c-17.67 0-32 14.33-32 32v144H32c-17.67 0-32 14.33-32 32v32c0 17.67 14.33 32 32 32h144v144c0 17.67 14.33 32 32 32h32c17.67 0 32-14.33 32-32V304h144c17.67 0 32-14.33 32-32v-32c0-17.67-14.33-32-32-32z"
    )

    static let calendarAlt = SCBottomBarSVGAsset(
        viewBox: CGSize(width: 448, height: 512),
        pathData: "M0 464c0 26.5 21.5 48 48 48h352c26.5 0 48-21.5 48-48V192H0v272zm320-196c0-6.6 5.4-12 12-12h40c6.6 0 12 5.4 12 12v40c0 6.6-5.4 12-12 12h-40c-6.6 0-12-5.4-12-12v-40zm0 128c0-6.6 5.4-12 12-12h40c6.6 0 12 5.4 12 12v40c0 6.6-5.4 12-12 12h-40c-6.6 0-12-5.4-12-12v-40zM192 268c0-6.6 5.4-12 12-12h40c6.6 0 12 5.4 12 12v40c0 6.6-5.4 12-12 12h-40c-6.6 0-12-5.4-12-12v-40zm0 128c0-6.6 5.4-12 12-12h40c6.6 0 12 5.4 12 12v40c0 6.6-5.4 12-12 12h-40c-6.6 0-12-5.4-12-12v-40zM64 268c0-6.6 5.4-12 12-12h40c6.6 0 12 5.4 12 12v40c0 6.6-5.4 12-12 12H76c-6.6 0-12-5.4-12-12v-40zm0 128c0-6.6 5.4-12 12-12h40c6.6 0 12 5.4 12 12v40c0 6.6-5.4 12-12 12H76c-6.6 0-12-5.4-12-12v-40zM400 64h-48V16c0-8.8-7.2-16-16-16h-32c-8.8 0-16 7.2-16 16v48H160V16c0-8.8-7.2-16-16-16h-32c-8.8 0-16 7.2-16 16v48H48C21.5 64 0 85.5 0 112v48h448v-48c0-26.5-21.5-48-48-48z"
    )

    static let comments = SCBottomBarSVGAsset(
        viewBox: CGSize(width: 576, height: 512),
        pathData: "M416 192c0-88.4-93.1-160-208-160S0 103.6 0 192c0 34.3 14.1 65.9 38 92-13.4 30.2-35.5 54.2-35.8 54.5-2.2 2.3-2.8 5.7-1.5 8.7S4.8 352 8 352c36.6 0 66.9-12.3 88.7-25 32.2 15.7 70.3 25 111.3 25 114.9 0 208-71.6 208-160zm122 220c23.9-26 38-57.7 38-92 0-66.9-53.5-124.2-129.3-148.1.9 6.6 1.3 13.3 1.3 20.1 0 105.9-107.7 192-240 192-10.8 0-21.3-.8-31.7-1.9C207.8 439.6 281.8 480 368 480c41 0 79.1-9.2 111.3-25 21.8 12.7 52.1 25 88.7 25 3.2 0 6.1-1.9 7.3-4.8 1.3-2.9.7-6.3-1.5-8.7-.3-.3-22.4-24.2-35.8-54.5z"
    )

    static let users = SCBottomBarSVGAsset(
        viewBox: CGSize(width: 640, height: 512),
        pathData: "M96 224c35.3 0 64-28.7 64-64s-28.7-64-64-64-64 28.7-64 64 28.7 64 64 64zm448 0c35.3 0 64-28.7 64-64s-28.7-64-64-64-64 28.7-64 64 28.7 64 64 64zm32 32h-64c-17.6 0-33.5 7.1-45.1 18.6 40.3 22.1 68.9 62 75.1 109.4h66c17.7 0 32-14.3 32-32v-32c0-35.3-28.7-64-64-64zm-256 0c61.9 0 112-50.1 112-112S381.9 32 320 32 208 82.1 208 144s50.1 112 112 112zm76.8 32h-8.3c-20.8 10-43.9 16-68.5 16s-47.6-6-68.5-16h-8.3C179.6 288 128 339.6 128 403.2V432c0 26.5 21.5 48 48 48h288c26.5 0 48-21.5 48-48v-28.8c0-63.6-51.6-115.2-115.2-115.2zm-223.7-13.4C161.5 263.1 145.6 256 128 256H64c-35.3 0-64 28.7-64 64v32c0 17.7 14.3 32 32 32h65.9c6.3-47.4 34.9-87.3 75.2-109.4z"
    )

    static let bell = SCBottomBarSVGAsset(
        viewBox: CGSize(width: 448, height: 512),
        pathData: "M224 512c35.32 0 63.97-28.65 63.97-64H160.03c0 35.35 28.65 64 63.97 64zm215.39-149.71c-19.32-20.76-55.47-51.99-55.47-154.29 0-77.7-54.48-139.9-127.94-155.16V32c0-17.67-14.32-32-31.98-32s-31.98 14.33-31.98 32v20.84C118.56 68.1 64.08 130.3 64.08 208c0 102.3-36.15 133.53-55.47 154.29-6 6.45-8.66 14.16-8.61 21.71.11 16.4 12.98 32 32.1 32h383.8c19.12 0 32-15.6 32.1-32 .05-7.55-2.61-15.27-8.61-21.71z"
    )

    static func asset(for glyph: SCBottomBarIcons.Glyph) -> SCBottomBarSVGAsset {
        switch glyph {
        case .list: .list
        case .plus: .plus
        case .comments: .comments
        case .users: .users
        case .bell: .bell
        case .calendarAlt: .calendarAlt
        }
    }
}

private struct SCBottomBarSVGIcon: View {
    let glyph: SCBottomBarIcons.Glyph
    let color: Color
    let size: CGFloat

    var body: some View {
        let asset = SCBottomBarSVGAsset.asset(for: glyph)
        SCBottomBarSVGIconShape(asset: asset)
            .fill(color)
            .frame(width: size, height: size)
    }
}

private struct SCBottomBarSVGIconShape: Shape {
    let asset: SCBottomBarSVGAsset

    func path(in rect: CGRect) -> Path {
        let scale = rect.height / asset.viewBox.height
        let scaledWidth = asset.viewBox.width * scale
        let offsetX = rect.midX - scaledWidth / 2
        let offsetY = rect.midY - (asset.viewBox.height * scale) / 2
        return SCBottomBarSVGPathParser.path(
            from: asset.pathData,
            transform: CGAffineTransform(translationX: offsetX, y: offsetY)
                .scaledBy(x: scale, y: scale)
        )
    }
}

// MARK: - Minimal SVG path parser (Font Awesome solid paths)

private enum SCBottomBarSVGPathParser {
    static func path(from data: String, transform: CGAffineTransform) -> Path {
        var path = Path()
        var current = CGPoint.zero
        var start = CGPoint.zero
        var lastControl = CGPoint.zero
        var lastCommand: Character?

        let tokens = tokenize(data)
        var index = 0

        func readNumber() -> CGFloat? {
            guard index < tokens.count, case .number(let value) = tokens[index] else { return nil }
            index += 1
            return value
        }

        func readPoint() -> CGPoint? {
            guard let x = readNumber(), let y = readNumber() else { return nil }
            return CGPoint(x: x, y: y)
        }

        func apply(_ point: CGPoint) -> CGPoint {
            point.applying(transform)
        }

        while index < tokens.count {
            guard case .command(let command) = tokens[index] else {
                index += 1
                continue
            }
            index += 1
            let isRelative = command.isLowercase
            let cmd = Character(command.uppercased())

            switch cmd {
            case "M":
                if let point = readPoint() {
                    let absolute = isRelative ? CGPoint(x: current.x + point.x, y: current.y + point.y) : point
                    current = absolute
                    start = absolute
                    path.move(to: apply(absolute))
                    lastCommand = "M"
                    while let next = readPoint() {
                        let linePoint = isRelative ? CGPoint(x: current.x + next.x, y: current.y + next.y) : next
                        current = linePoint
                        path.addLine(to: apply(linePoint))
                        lastCommand = "L"
                    }
                }
            case "L":
                while let point = readPoint() {
                    let absolute = isRelative ? CGPoint(x: current.x + point.x, y: current.y + point.y) : point
                    current = absolute
                    path.addLine(to: apply(absolute))
                }
                lastCommand = "L"
            case "H":
                while let x = readNumber() {
                    let absoluteX = isRelative ? current.x + x : x
                    current = CGPoint(x: absoluteX, y: current.y)
                    path.addLine(to: apply(current))
                }
                lastCommand = "H"
            case "V":
                while let y = readNumber() {
                    let absoluteY = isRelative ? current.y + y : y
                    current = CGPoint(x: current.x, y: absoluteY)
                    path.addLine(to: apply(current))
                }
                lastCommand = "V"
            case "C":
                while let c1 = readPoint(), let c2 = readPoint(), let end = readPoint() {
                    let absC1 = isRelative ? CGPoint(x: current.x + c1.x, y: current.y + c1.y) : c1
                    let absC2 = isRelative ? CGPoint(x: current.x + c2.x, y: current.y + c2.y) : c2
                    let absEnd = isRelative ? CGPoint(x: current.x + end.x, y: current.y + end.y) : end
                    path.addCurve(to: apply(absEnd), control1: apply(absC1), control2: apply(absC2))
                    lastControl = absC2
                    current = absEnd
                }
                lastCommand = "C"
            case "S":
                while let c2 = readPoint(), let end = readPoint() {
                    let reflected = CGPoint(x: 2 * current.x - lastControl.x, y: 2 * current.y - lastControl.y)
                    let absC2 = isRelative ? CGPoint(x: current.x + c2.x, y: current.y + c2.y) : c2
                    let absEnd = isRelative ? CGPoint(x: current.x + end.x, y: current.y + end.y) : end
                    let absC1 = (lastCommand == "C" || lastCommand == "S") ? reflected : current
                    path.addCurve(to: apply(absEnd), control1: apply(absC1), control2: apply(absC2))
                    lastControl = absC2
                    current = absEnd
                }
                lastCommand = "S"
            case "Q":
                while let c1 = readPoint(), let end = readPoint() {
                    let absC1 = isRelative ? CGPoint(x: current.x + c1.x, y: current.y + c1.y) : c1
                    let absEnd = isRelative ? CGPoint(x: current.x + end.x, y: current.y + end.y) : end
                    path.addQuadCurve(to: apply(absEnd), control: apply(absC1))
                    lastControl = absC1
                    current = absEnd
                }
                lastCommand = "Q"
            case "T":
                while let end = readPoint() {
                    let reflected = CGPoint(x: 2 * current.x - lastControl.x, y: 2 * current.y - lastControl.y)
                    let absEnd = isRelative ? CGPoint(x: current.x + end.x, y: current.y + end.y) : end
                    let absC1 = (lastCommand == "Q" || lastCommand == "T") ? reflected : current
                    path.addQuadCurve(to: apply(absEnd), control: apply(absC1))
                    lastControl = absC1
                    current = absEnd
                }
                lastCommand = "T"
            case "A":
                while let rx = readNumber(), let ry = readNumber(), let angle = readNumber(),
                      let largeArc = readNumber(), let sweep = readNumber(), let end = readPoint() {
                    let absEnd = isRelative ? CGPoint(x: current.x + end.x, y: current.y + end.y) : end
                    addArc(
                        to: &path,
                        from: current,
                        to: absEnd,
                        rx: rx,
                        ry: ry,
                        angle: angle,
                        largeArc: largeArc != 0,
                        sweep: sweep != 0,
                        transform: transform
                    )
                    current = absEnd
                }
                lastCommand = "A"
            case "Z":
                path.closeSubpath()
                current = start
                lastCommand = "Z"
            default:
                break
            }
        }

        return path
    }

    private static func addArc(
        to path: inout Path,
        from start: CGPoint,
        to end: CGPoint,
        rx: CGFloat,
        ry: CGFloat,
        angle: CGFloat,
        largeArc: Bool,
        sweep: Bool,
        transform: CGAffineTransform
    ) {
        guard start != end else { return }
        var rx = abs(rx)
        var ry = abs(ry)
        if rx == 0 || ry == 0 {
            path.addLine(to: end.applying(transform))
            return
        }

        let radians = angle * .pi / 180
        let cosAngle = cos(radians)
        let sinAngle = sin(radians)

        let dx = (start.x - end.x) / 2
        let dy = (start.y - end.y) / 2
        let x1 = cosAngle * dx + sinAngle * dy
        let y1 = -sinAngle * dx + cosAngle * dy

        let rxSq = rx * rx
        let rySq = ry * ry
        let x1Sq = x1 * x1
        let y1Sq = y1 * y1
        var radiiScale = (x1Sq / rxSq) + (y1Sq / rySq)
        if radiiScale > 1 {
            let scale = sqrt(radiiScale)
            rx *= scale
            ry *= scale
        }

        let rxSq2 = rx * rx
        let rySq2 = ry * ry
        let numerator = max(0, (rxSq2 * rySq2) - (rxSq2 * y1Sq) - (rySq2 * x1Sq))
        let coef = sqrt(numerator / (rxSq2 * y1Sq + rySq2 * x1Sq))
        let sign: CGFloat = (largeArc == sweep) ? -1 : 1
        let cx1 = sign * coef * (rx * y1 / ry)
        let cy1 = sign * coef * -(ry * x1 / rx)

        let cx = cosAngle * cx1 - sinAngle * cy1 + (start.x + end.x) / 2
        let cy = sinAngle * cx1 + cosAngle * cy1 + (start.y + end.y) / 2

        func angleBetween(_ ux: CGFloat, _ uy: CGFloat, _ vx: CGFloat, _ vy: CGFloat) -> CGFloat {
            let dot = ux * vx + uy * vy
            let len = sqrt((ux * ux + uy * uy) * (vx * vx + vy * vy))
            var ang = acos(min(1, max(-1, dot / len)))
            if ux * vy - uy * vx < 0 { ang = -ang }
            return ang
        }

        let ux = (x1 - cx1) / rx
        let uy = (y1 - cy1) / ry
        let vx = (-x1 - cx1) / rx
        let vy = (-y1 - cy1) / ry
        var sweepAngle = angleBetween(ux, uy, vx, vy)
        if !sweep, sweepAngle > 0 { sweepAngle -= 2 * .pi }
        if sweep, sweepAngle < 0 { sweepAngle += 2 * .pi }

        let segments = max(1, Int(ceil(abs(sweepAngle) / (.pi / 2))))
        var currentPoint = start
        var currentAngle = atan2((y1 - cy1) / ry, (x1 - cx1) / rx)

        for segment in 0..<segments {
            let nextAngle = currentAngle + sweepAngle / CGFloat(segments)
            let midAngle = (currentAngle + nextAngle) / 2
            let control = CGPoint(
                x: cx + rx * cos(midAngle) * cosAngle - ry * sin(midAngle) * sinAngle,
                y: cy + rx * cos(midAngle) * sinAngle + ry * sin(midAngle) * cosAngle
            )
            let endPoint = segment == segments - 1 ? end : CGPoint(
                x: cx + rx * cos(nextAngle) * cosAngle - ry * sin(nextAngle) * sinAngle,
                y: cy + rx * cos(nextAngle) * sinAngle + ry * sin(nextAngle) * cosAngle
            )
            path.addQuadCurve(to: endPoint.applying(transform), control: control.applying(transform))
            currentAngle = nextAngle
            currentPoint = endPoint
        }
        _ = currentPoint
    }

    private enum Token {
        case command(Character)
        case number(CGFloat)
    }

    private static func tokenize(_ data: String) -> [Token] {
        var tokens: [Token] = []
        var number = ""
        var index = data.startIndex

        func flushNumber() {
            guard !number.isEmpty else { return }
            if let value = Double(number) {
                tokens.append(.number(CGFloat(value)))
            }
            number = ""
        }

        while index < data.endIndex {
            let char = data[index]
            if char.isLetter {
                flushNumber()
                tokens.append(.command(char))
                index = data.index(after: index)
            } else if char == "-" || char == "." || char.isNumber {
                if number.isEmpty, char == "-",
                   let last = tokens.last,
                   case .number = last {
                    number.append(char)
                } else if number.isEmpty || number.last == "-" || number.last == "." || number.last?.isNumber == true {
                    number.append(char)
                } else {
                    flushNumber()
                    number.append(char)
                }
                index = data.index(after: index)
            } else if char == "," || char.isWhitespace {
                flushNumber()
                index = data.index(after: index)
            } else {
                flushNumber()
                index = data.index(after: index)
            }
        }
        flushNumber()
        return tokens
    }
}
