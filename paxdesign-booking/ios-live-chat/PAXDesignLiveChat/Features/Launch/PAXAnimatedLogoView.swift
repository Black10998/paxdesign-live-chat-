import SwiftUI

/// Pixel-accurate reproduction of the PAXdesign website header logo animation
/// (`Paxdesign_Dtr_Header_Logo` v1.6.0).
struct PAXAnimatedLogoView: View {
    @Environment(\.accessibilityReduceMotion) private var reduceMotion

    /// Matches website `--paxlogo-mark-w: clamp(118px, 44vw, 168px)` on mobile.
    var markWidth: CGFloat = 148

    private let holdDuration: TimeInterval = 1.2
    private let morphDuration: TimeInterval = 0.24
    private let revealDuration: TimeInterval = 0.45
    private let paxRevealDelay: TimeInterval = 0.12
    private let designRevealDelay: TimeInterval = 0.2
    private let paxColor = Color(red: 204 / 255, green: 1, blue: 0)
    private let designColor = Color.white

    @State private var symbolIndex = 0
    @State private var isMorphing = false
    @State private var paxVisible = false
    @State private var designVisible = false
    @State private var shineOffset: CGFloat = -18
    @State private var symbolTask: Task<Void, Never>?

    private var iconSize: CGFloat { markWidth * 0.128 }
    private var wordmarkFontSize: CGFloat { markWidth * (38 / 148) }

    var body: some View {
        HStack(alignment: .center, spacing: max(2, markWidth * 0.02)) {
            PAXLogoSymbolView(iconIndex: symbolIndex, size: iconSize, isMorphing: isMorphing)
            wordmark
        }
        .accessibilityLabel("PAXdesign")
        .onAppear { startAnimations() }
        .onDisappear { symbolTask?.cancel() }
    }

    private var wordmark: some View {
        HStack(alignment: .firstTextBaseline, spacing: 0) {
            ZStack(alignment: .leading) {
                Text("pax")
                    .font(.system(size: wordmarkFontSize, weight: .bold, design: .default))
                    .tracking(wordmarkFontSize * -0.05)
                    .foregroundStyle(paxColor)
                if paxVisible, !reduceMotion {
                    LinearGradient(
                        colors: [
                            Color(red: 191 / 255, green: 1, blue: 0),
                            Color(red: 204 / 255, green: 1, blue: 0),
                            Color(red: 191 / 255, green: 1, blue: 0),
                        ],
                        startPoint: .leading,
                        endPoint: .trailing
                    )
                    .frame(width: markWidth * 0.55, height: wordmarkFontSize * 1.2)
                    .offset(x: shineOffset)
                    .opacity(0.1)
                    .mask {
                        Text("pax")
                            .font(.system(size: wordmarkFontSize, weight: .bold, design: .default))
                            .tracking(wordmarkFontSize * -0.05)
                    }
                }
            }
            .opacity(paxVisible ? 1 : 0)
            .offset(y: paxVisible ? 0 : 5)

            Text("design")
                .font(.system(size: wordmarkFontSize, weight: .light, design: .default))
                .tracking(wordmarkFontSize * -0.05)
                .foregroundStyle(designColor)
                .opacity(designVisible ? 1 : 0)
                .offset(y: designVisible ? 0 : 5)
        }
    }

    private func startAnimations() {
        if reduceMotion {
            paxVisible = true
            designVisible = true
            symbolIndex = 0
            return
        }

        withAnimation(.timingCurve(0.22, 1, 0.36, 1, duration: revealDuration).delay(paxRevealDelay)) {
            paxVisible = true
        }
        withAnimation(.timingCurve(0.22, 1, 0.36, 1, duration: revealDuration).delay(designRevealDelay)) {
            designVisible = true
        }

        withAnimation(.easeInOut(duration: 12).repeatForever(autoreverses: true)) {
            shineOffset = 10
        }

        symbolTask?.cancel()
        symbolTask = Task {
            while !Task.isCancelled {
                try? await Task.sleep(nanoseconds: UInt64(holdDuration * 1_000_000_000))
                guard !Task.isCancelled else { break }
                await morphToNextSymbol()
            }
        }
    }

    @MainActor
    private func morphToNextSymbol() async {
        withAnimation(.timingCurve(0.22, 1, 0.36, 1, duration: morphDuration)) {
            isMorphing = true
        }
        try? await Task.sleep(nanoseconds: UInt64(morphDuration * 0.42 * 1_000_000_000))
        symbolIndex = (symbolIndex + 1) % PAXLogoSymbolView.iconCount
        withAnimation(.timingCurve(0.22, 1, 0.36, 1, duration: morphDuration)) {
            isMorphing = false
        }
    }
}

// MARK: - Animated symbol slot

private struct PAXLogoSymbolView: View {
    static let iconCount = 8

    let iconIndex: Int
    let size: CGFloat
    let isMorphing: Bool

    private var strokeWidth: CGFloat { size * 0.065 }

    var body: some View {
        Canvas { context, canvasSize in
            let scale = min(canvasSize.width, canvasSize.height) / 24
            let stroke = StrokeStyle(lineWidth: strokeWidth, lineCap: .round, lineJoin: .round)
            var strokeContext = context
            strokeContext.stroke(PAXLogoSymbolPaths.strokedPaths(for: iconIndex, scale: scale), with: .color(.white), style: stroke)
            var fillContext = context
            fillContext.fill(PAXLogoSymbolPaths.filledPaths(for: iconIndex, scale: scale), with: .color(.white))
        }
        .frame(width: size, height: size)
        .scaleEffect(isMorphing ? 0.84 : 1)
    }
}

// MARK: - Symbol geometry (viewBox 0…24, from website ICONS array)

private enum PAXLogoSymbolPaths {
    static func strokedPaths(for index: Int, scale: CGFloat) -> Path {
        var path = Path()
        switch index % 8 {
        case 0:
            line(&path, scale, 8, 7, 4, 12); line(&path, scale, 4, 12, 8, 17)
            line(&path, scale, 16, 7, 20, 12); line(&path, scale, 20, 12, 16, 17)
            line(&path, scale, 13.2, 6.2, 10.8, 17.8)
        case 1:
            arc(&path, scale, 9.2, 7, 6.2, 10.6, 7.4, 7, 6.2, 8.6)
            line(&path, scale, 6.2, 10.6, 6.2, 13.4)
            arc(&path, scale, 6.2, 13.4, 9.2, 17, 6.2, 15.4, 7.4, 17)
            arc(&path, scale, 14.8, 7, 17.8, 10.6, 16.6, 7, 17.8, 8.6)
            line(&path, scale, 17.8, 10.6, 17.8, 13.4)
            arc(&path, scale, 17.8, 13.4, 14.8, 17, 17.8, 15.4, 16.6, 17)
        case 2:
            roundedRect(&path, scale, 3.5, 5.5, 17, 13, 2)
            line(&path, scale, 7.2, 10.2, 9.8, 12); line(&path, scale, 9.8, 12, 7.2, 13.8)
            line(&path, scale, 12.2, 14.2, 16.8, 14.2)
        case 3:
            line(&path, scale, 12, 7, 6.6, 16.8); line(&path, scale, 12, 7, 17.4, 16.8)
        case 4:
            line(&path, scale, 5.2, 8.2, 5.2, 15.8); line(&path, scale, 5.2, 12, 16.8, 12)
        case 5:
            line(&path, scale, 4.2, 12, 13.2, 12)
            line(&path, scale, 10.8, 8.2, 15.8, 12); line(&path, scale, 15.8, 12, 10.8, 15.8)
        case 6:
            polyline(&path, scale, [8.2, 7.2, 5.2, 7.2, 5.2, 16.8, 8.2, 16.8])
            polyline(&path, scale, [15.8, 7.2, 18.8, 7.2, 18.8, 16.8, 15.8, 16.8])
        default:
            roundedRect(&path, scale, 4, 4, 6.2, 6.2, 1.2)
            roundedRect(&path, scale, 13.8, 4, 6.2, 6.2, 1.2)
            roundedRect(&path, scale, 4, 13.8, 6.2, 6.2, 1.2)
            roundedRect(&path, scale, 13.8, 13.8, 6.2, 6.2, 1.2)
        }
        return path
    }

    static func filledPaths(for index: Int, scale: CGFloat) -> Path {
        var path = Path()
        switch index % 8 {
        case 3:
            circle(&path, scale, 12, 5.2, 1.7)
            circle(&path, scale, 5.4, 18.4, 1.7)
            circle(&path, scale, 18.6, 18.4, 1.7)
        case 4:
            circle(&path, scale, 5.2, 6.2, 2)
            circle(&path, scale, 5.2, 17.8, 2)
            circle(&path, scale, 18.8, 12, 2)
        default:
            break
        }
        return path
    }

    private static func point(_ scale: CGFloat, _ x: CGFloat, _ y: CGFloat) -> CGPoint {
        CGPoint(x: x * scale, y: y * scale)
    }

    private static func line(_ path: inout Path, _ scale: CGFloat, _ x1: CGFloat, _ y1: CGFloat, _ x2: CGFloat, _ y2: CGFloat) {
        path.move(to: point(scale, x1, y1))
        path.addLine(to: point(scale, x2, y2))
    }

    private static func polyline(_ path: inout Path, _ scale: CGFloat, _ values: [CGFloat]) {
        guard values.count >= 4, values.count.isMultiple(of: 2) else { return }
        path.move(to: point(scale, values[0], values[1]))
        for index in stride(from: 2, to: values.count, by: 2) {
            path.addLine(to: point(scale, values[index], values[index + 1]))
        }
    }

    private static func arc(
        _ path: inout Path,
        _ scale: CGFloat,
        _ x1: CGFloat, _ y1: CGFloat,
        _ x2: CGFloat, _ y2: CGFloat,
        _ cx1: CGFloat, _ cy1: CGFloat,
        _ cx2: CGFloat, _ cy2: CGFloat
    ) {
        path.move(to: point(scale, x1, y1))
        path.addCurve(to: point(scale, x2, y2), control1: point(scale, cx1, cy1), control2: point(scale, cx2, cy2))
    }

    private static func roundedRect(_ path: inout Path, _ scale: CGFloat, _ x: CGFloat, _ y: CGFloat, _ w: CGFloat, _ h: CGFloat, _ r: CGFloat) {
        path.addRoundedRect(in: CGRect(x: x * scale, y: y * scale, width: w * scale, height: h * scale), cornerSize: CGSize(width: r * scale, height: r * scale))
    }

    private static func circle(_ path: inout Path, _ scale: CGFloat, _ cx: CGFloat, _ cy: CGFloat, _ radius: CGFloat) {
        path.addEllipse(in: CGRect(x: (cx - radius) * scale, y: (cy - radius) * scale, width: radius * 2 * scale, height: radius * 2 * scale))
    }
}
