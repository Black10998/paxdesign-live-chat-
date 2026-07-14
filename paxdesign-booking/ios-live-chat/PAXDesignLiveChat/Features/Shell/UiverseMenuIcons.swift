import SwiftUI

/// Exact Heroicons solid 24×24 SVG paths from the Uiverse.io mymiamo menu reference,
/// plus matching Heroicons solid paths for the additional app tabs.
enum UiverseMenuIcons {
    enum Glyph: String, CaseIterable {
        case home
        case inbox
        case calendar
        case cog
        case chatBubble
        case users
        case bell
    }

    @ViewBuilder
    static func icon(_ glyph: Glyph, color: Color, size: CGFloat = UiverseMenuMetrics.iconSize) -> some View {
        UiverseMenuIconView(glyph: glyph, color: color, size: size)
    }
}

// MARK: - SVG assets (literal path data from reference + Heroicons solid)

private struct UiverseMenuSVGAsset {
    let paths: [UiverseMenuSVGPath]

    static let home = UiverseMenuSVGAsset(paths: [
        UiverseMenuSVGPath(
            d: "M11.47 3.841a.75.75 0 0 1 1.06 0l8.69 8.69a.75.75 0 1 0 1.06-1.061l-8.689-8.69a2.25 2.25 0 0 0-3.182 0l-8.69 8.69a.75.75 0 1 0 1.061 1.06l8.69-8.689Z"
        ),
        UiverseMenuSVGPath(
            d: "m12 5.432 8.159 8.159c.03.03.06.058.091.086v6.198c0 1.035-.84 1.875-1.875 1.875H15a.75.75 0 0 1-.75-.75v-4.5a.75.75 0 0 0-.75-.75h-3a.75.75 0 0 0-.75.75V21a.75.75 0 0 1-.75.75H5.625a1.875 1.875 0 0 1-1.875-1.875v-6.198a2.29 2.29 0 0 0 .091-.086L12 5.432Z"
        ),
    ])

    static let inbox = UiverseMenuSVGAsset(paths: [
        UiverseMenuSVGPath(
            d: "M19.5 21a3 3 0 0 0 3-3v-4.5a3 3 0 0 0-3-3h-15a3 3 0 0 0-3 3V18a3 3 0 0 0 3 3h15ZM1.5 10.146V6a3 3 0 0 1 3-3h5.379a2.25 2.25 0 0 1 1.59.659l2.122 2.121c.14.141.331.22.53.22H19.5a3 3 0 0 1 3 3v1.146A4.483 4.483 0 0 0 19.5 9h-15a4.483 4.483 0 0 0-3 1.146Z"
        ),
    ])

    static let calendar = UiverseMenuSVGAsset(paths: [
        UiverseMenuSVGPath(
            d: "M6.75 2.25A.75.75 0 0 1 7.5 3v1.5h9V3A.75.75 0 0 1 18 3v1.5h.75a3 3 0 0 1 3 3v11.25a3 3 0 0 1-3 3H5.25a3 3 0 0 1-3-3V7.5a3 3 0 0 1 3-3H6V3a.75.75 0 0 1 .75-.75Zm13.5 9a1.5 1.5 0 0 0-1.5-1.5H5.25a1.5 1.5 0 0 0-1.5 1.5v7.5a1.5 1.5 0 0 0 1.5 1.5h13.5a1.5 1.5 0 0 0 1.5-1.5v-7.5Z",
            fillRule: .evenOdd
        ),
    ])

    static let cog = UiverseMenuSVGAsset(paths: [
        UiverseMenuSVGPath(
            d: "M17.004 10.407c.138.435-.216.842-.672.842h-3.465a.75.75 0 0 1-.65-.375l-1.732-3c-.229-.396-.053-.907.393-1.004a5.252 5.252 0 0 1 6.126 3.537ZM8.12 8.464c.307-.338.838-.235 1.066.16l1.732 3a.75.75 0 0 1 0 .75l-1.732 3c-.229.397-.76.5-1.067.161A5.23 5.23 0 0 1 6.75 12a5.23 5.23 0 0 1 1.37-3.536ZM10.878 17.13c-.447-.098-.623-.608-.394-1.004l1.733-3.002a.75.75 0 0 1 .65-.375h3.465c.457 0 .81.407.672.842a5.252 5.252 0 0 1-6.126 3.539Z"
        ),
        UiverseMenuSVGPath(
            d: "M21 12.75a.75.75 0 1 0 0-1.5h-.783a8.22 8.22 0 0 0-.237-1.357l.734-.267a.75.75 0 1 0-.513-1.41l-.735.268a8.24 8.24 0 0 0-.689-1.192l.6-.503a.75.75 0 1 0-.964-1.149l-.6.504a8.3 8.3 0 0 0-1.054-.885l.391-.678a.75.75 0 1 0-1.299-.75l-.39.676a8.188 8.188 0 0 0-1.295-.47l.136-.77a.75.75 0 0 0-1.477-.26l-.136.77a8.36 8.36 0 0 0-1.377 0l-.136-.77a.75.75 0 1 0-1.477.26l.136.77c-.448.121-.88.28-1.294.47l-.39-.676a.75.75 0 0 0-1.3.75l.392.678a8.29 8.29 0 0 0-1.054.885l-.6-.504a.75.75 0 1 0-.965 1.149l.6.503a8.243 8.243 0 0 0-.689 1.192L3.8 8.216a.75.75 0 1 0-.513 1.41l.735.267a8.222 8.222 0 0 0-.238 1.356h-.783a.75.75 0 0 0 0 1.5h.783c.042.464.122.917.238 1.356l-.735.268a.75.75 0 0 0 .513 1.41l.735-.268c.197.417.428.816.69 1.191l-.6.504a.75.75 0 0 0 .963 1.15l.601-.505c.326.323.679.62 1.054.885l-.392.68a.75.75 0 0 0 1.3.75l.39-.679c.414.192.847.35 1.294.471l-.136.77a.75.75 0 0 0 1.477.261l.137-.772a8.332 8.332 0 0 0 1.376 0l.136.772a.75.75 0 1 0 1.477-.26l-.136-.771a8.19 8.19 0 0 0 1.294-.47l.391.677a.75.75 0 0 0 1.3-.75l-.393-.679a8.29 8.29 0 0 0 1.054-.885l.601.504a.75.75 0 0 0 .964-1.15l-.6-.503c.261-.375.492-.774.69-1.191l.735.267a.75.75 0 1 0 .512-1.41l-.734-.267c.115-.439.195-.892.237-1.356h.784Zm-2.657-3.06a6.744 6.744 0 0 0-1.19-2.053 6.784 6.784 0 0 0-1.82-1.51A6.705 6.705 0 0 0 12 5.25a6.8 6.8 0 0 0-1.225.11 6.7 6.7 0 0 0-2.15.793 6.784 6.784 0 0 0-2.952 3.489.76.76 0 0 1-.036.098A6.74 6.74 0 0 0 5.251 12a6.74 6.74 0 0 0 3.366 5.842l.009.005a6.704 6.704 0 0 0 2.18.798l.022.003a6.792 6.792 0 0 0 2.368-.004 6.704 6.704 0 0 0 2.205-.811 6.785 6.785 0 0 0 1.762-1.484l.009-.01.009-.01a6.743 6.743 0 0 0 1.18-2.066c.253-.707.39-1.469.39-2.263a6.74 6.74 0 0 0-.408-2.309Z",
            fillRule: .evenOdd
        ),
    ])

    static let chatBubble = UiverseMenuSVGAsset(paths: [
        UiverseMenuSVGPath(
            d: "M4.91307 2.65823C6.9877 2.38888 9.10296 2.25 11.2503 2.25C13.3974 2.25 15.5124 2.38885 17.5869 2.65815C19.5091 2.90769 20.8783 4.51937 20.9923 6.38495C20.6665 6.27614 20.3212 6.20396 19.96 6.17399C18.5715 6.05874 17.1673 6 15.75 6C14.3326 6 12.9285 6.05874 11.54 6.17398C9.1817 6.36971 7.5 8.36467 7.5 10.6082V14.8937C7.5 16.5844 8.45468 18.1326 9.9328 18.8779L7.28033 21.5303C7.06583 21.7448 6.74324 21.809 6.46299 21.6929C6.18273 21.5768 6 21.3033 6 21V16.9705C5.63649 16.9316 5.27417 16.8887 4.91308 16.8418C2.90466 16.581 1.5 14.8333 1.5 12.8626V6.63738C1.5 4.66672 2.90466 2.91899 4.91307 2.65823Z"
        ),
        UiverseMenuSVGPath(
            d: "M15.75 7.5C14.3741 7.5 13.0114 7.55702 11.6641 7.66884C10.1248 7.7966 9 9.10282 9 10.6082V14.8937C9 16.4014 10.128 17.7083 11.6692 17.8341C12.9131 17.9357 14.17 17.9912 15.4384 17.999L18.2197 20.7803C18.4342 20.9948 18.7568 21.059 19.037 20.9429C19.3173 20.8268 19.5 20.5533 19.5 20.25V17.8601C19.6103 17.8518 19.7206 17.8432 19.8307 17.8342C21.372 17.7085 22.5 16.4015 22.5 14.8938V10.6082C22.5 9.10283 21.3752 7.79661 19.836 7.66885C18.4886 7.55702 17.1259 7.5 15.75 7.5Z"
        ),
    ])

    static let users = UiverseMenuSVGAsset(paths: [
        UiverseMenuSVGPath(
            d: "M4.5 6.375C4.5 4.09683 6.34683 2.25 8.625 2.25C10.9032 2.25 12.75 4.09683 12.75 6.375C12.75 8.65317 10.9032 10.5 8.625 10.5C6.34683 10.5 4.5 8.65317 4.5 6.375Z"
        ),
        UiverseMenuSVGPath(
            d: "M14.25 8.625C14.25 6.76104 15.761 5.25 17.625 5.25C19.489 5.25 21 6.76104 21 8.625C21 10.489 19.489 12 17.625 12C15.761 12 14.25 10.489 14.25 8.625Z"
        ),
        UiverseMenuSVGPath(
            d: "M1.5 19.125C1.5 15.19 4.68997 12 8.625 12C12.56 12 15.75 15.19 15.75 19.125V19.1276C15.75 19.1674 15.7496 19.2074 15.749 19.2469C15.7446 19.5054 15.6074 19.7435 15.3859 19.8768C13.4107 21.0661 11.0966 21.75 8.625 21.75C6.15343 21.75 3.8393 21.0661 1.86406 19.8768C1.64256 19.7435 1.50537 19.5054 1.50103 19.2469C1.50034 19.2064 1.5 19.1657 1.5 19.125Z"
        ),
        UiverseMenuSVGPath(
            d: "M17.2498 19.1281C17.2498 19.1762 17.2494 19.2244 17.2486 19.2722C17.2429 19.6108 17.1612 19.9378 17.0157 20.232C17.2172 20.2439 17.4203 20.25 17.6248 20.25C19.2206 20.25 20.732 19.8803 22.0764 19.2213C22.3234 19.1002 22.4843 18.8536 22.4957 18.5787C22.4984 18.5111 22.4998 18.4432 22.4998 18.375C22.4998 15.6826 20.3172 13.5 17.6248 13.5C16.8784 13.5 16.1711 13.6678 15.5387 13.9676C16.6135 15.4061 17.2498 17.1912 17.2498 19.125V19.1281Z"
        ),
    ])

    static let bell = UiverseMenuSVGAsset(paths: [
        UiverseMenuSVGPath(
            d: "M5.25001 8.9998C5.25012 5.27197 8.27215 2.25 12 2.25C15.7279 2.25 18.75 5.27208 18.75 9L18.7498 9.04919V9.75C18.7498 11.8731 19.5508 13.8074 20.8684 15.2699C21.0349 15.4547 21.0989 15.71 21.0393 15.9516C20.9797 16.1931 20.8042 16.3893 20.5709 16.4755C19.0269 17.0455 17.4105 17.4659 15.7396 17.7192C15.7465 17.812 15.75 17.9056 15.75 18C15.75 20.0711 14.0711 21.75 12 21.75C9.92894 21.75 8.25001 20.0711 8.25001 18C8.25001 17.9056 8.25351 17.812 8.2604 17.7192C6.58934 17.4659 4.97287 17.0455 3.42875 16.4755C3.19539 16.3893 3.01992 16.1931 2.96033 15.9516C2.90073 15.71 2.96476 15.4547 3.13126 15.2699C4.44879 13.8074 5.24981 11.8731 5.24981 9.75L5.25001 8.9998ZM9.75221 17.8993C9.75075 17.9326 9.75001 17.9662 9.75001 18C9.75001 19.2426 10.7574 20.25 12 20.25C13.2427 20.25 14.25 19.2426 14.25 18C14.25 17.9662 14.2493 17.9326 14.2478 17.8992C13.5072 17.9659 12.7574 18 11.9998 18C11.2424 18 10.4927 17.966 9.75221 17.8993Z",
            fillRule: .evenOdd
        ),
    ])

    static func asset(for glyph: UiverseMenuIcons.Glyph) -> UiverseMenuSVGAsset {
        switch glyph {
        case .home: .home
        case .inbox: .inbox
        case .calendar: .calendar
        case .cog: .cog
        case .chatBubble: .chatBubble
        case .users: .users
        case .bell: .bell
        }
    }
}

private struct UiverseMenuSVGPath {
    let d: String
    var fillRule: UiverseSVGFillRule = .nonZero
}

private enum UiverseSVGFillRule {
    case nonZero
    case evenOdd
}

private struct UiverseMenuIconView: View {
    let glyph: UiverseMenuIcons.Glyph
    let color: Color
    let size: CGFloat

    var body: some View {
        let asset = UiverseMenuSVGAsset.asset(for: glyph)
        ZStack {
            ForEach(Array(asset.paths.enumerated()), id: \.offset) { _, path in
                UiverseMenuIconShape(pathData: path.d)
                    .fill(
                        color,
                        style: path.fillRule == .evenOdd
                            ? FillStyle(eoFill: true)
                            : FillStyle()
                    )
            }
        }
        .frame(width: size, height: size)
    }
}

private struct UiverseMenuIconShape: Shape {
    let pathData: String

    func path(in rect: CGRect) -> Path {
        let scale = rect.height / 24
        let offsetX = rect.midX - 12 * scale
        let offsetY = rect.midY - 12 * scale
        let transform = CGAffineTransform(translationX: offsetX, y: offsetY).scaledBy(x: scale, y: scale)
        var path = UiverseMenuSVGPathParser.path(from: pathData, transform: transform)
        // Shape fill rule is applied at fill time via FillStyle in parent - use eoFill for evenodd
        return path
    }
}

// MARK: - SVG path parser

private enum UiverseMenuSVGPathParser {
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
                    addArc(to: &path, from: current, to: absEnd, rx: rx, ry: ry, angle: angle,
                           largeArc: largeArc != 0, sweep: sweep != 0, transform: transform)
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
        to path: inout Path, from start: CGPoint, to end: CGPoint,
        rx: CGFloat, ry: CGFloat, angle: CGFloat, largeArc: Bool, sweep: Bool, transform: CGAffineTransform
    ) {
        _ = angle
        guard start != end else { return }
        var rx = abs(rx), ry = abs(ry)
        if rx == 0 || ry == 0 { path.addLine(to: end.applying(transform)); return }

        let dx = (start.x - end.x) / 2, dy = (start.y - end.y) / 2
        let x1 = dx, y1 = dy
        var radiiScale = (x1 * x1) / (rx * rx) + (y1 * y1) / (ry * ry)
        if radiiScale > 1 { let s = sqrt(radiiScale); rx *= s; ry *= s }

        let rxSq = rx * rx, rySq = ry * ry
        let numerator = max(0, rxSq * rySq - rxSq * y1 * y1 - rySq * x1 * x1)
        let coef = sqrt(numerator / (rxSq * y1 * y1 + rySq * x1 * x1))
        let sign: CGFloat = (largeArc == sweep) ? -1 : 1
        let cx1 = sign * coef * (rx * y1 / ry), cy1 = sign * coef * -(ry * x1 / rx)
        let cx = cx1 + (start.x + end.x) / 2, cy = cy1 + (start.y + end.y) / 2

        func angleBetween(_ ux: CGFloat, _ uy: CGFloat, _ vx: CGFloat, _ vy: CGFloat) -> CGFloat {
            let dot = ux * vx + uy * vy
            let len = sqrt((ux * ux + uy * uy) * (vx * vx + vy * vy))
            var ang = acos(min(1, max(-1, dot / len)))
            if ux * vy - uy * vx < 0 { ang = -ang }
            return ang
        }

        let ux = (x1 - cx1) / rx, uy = (y1 - cy1) / ry
        let vx = (-x1 - cx1) / rx, vy = (-y1 - cy1) / ry
        var sweepAngle = angleBetween(ux, uy, vx, vy)
        if !sweep, sweepAngle > 0 { sweepAngle -= 2 * .pi }
        if sweep, sweepAngle < 0 { sweepAngle += 2 * .pi }

        let segments = max(1, Int(ceil(abs(sweepAngle) / (.pi / 2))))
        var currentAngle = atan2((y1 - cy1) / ry, (x1 - cx1) / rx)
        for segment in 0..<segments {
            let nextAngle = currentAngle + sweepAngle / CGFloat(segments)
            let midAngle = (currentAngle + nextAngle) / 2
            let control = CGPoint(x: cx + rx * cos(midAngle), y: cy + ry * sin(midAngle))
            let endPoint = segment == segments - 1 ? end : CGPoint(x: cx + rx * cos(nextAngle), y: cy + ry * sin(nextAngle))
            path.addQuadCurve(to: endPoint.applying(transform), control: control.applying(transform))
            currentAngle = nextAngle
        }
    }

    private enum Token { case command(Character); case number(CGFloat) }

    private static func tokenize(_ data: String) -> [Token] {
        var tokens: [Token] = []
        var number = ""
        var index = data.startIndex
        func flush() {
            guard !number.isEmpty, let value = Double(number) else { number = ""; return }
            tokens.append(.number(CGFloat(value)))
            number = ""
        }
        while index < data.endIndex {
            let char = data[index]
            if char.isLetter {
                flush(); tokens.append(.command(char)); index = data.index(after: index)
            } else if char == "-" || char == "." || char.isNumber {
                if number.isEmpty, char == "-", let last = tokens.last, case .number = last { number.append(char) }
                else { if !number.isEmpty, number.last?.isNumber == true, char == "-" { flush() }; number.append(char) }
                index = data.index(after: index)
            } else { flush(); index = data.index(after: index) }
        }
        flush()
        return tokens
    }
}
