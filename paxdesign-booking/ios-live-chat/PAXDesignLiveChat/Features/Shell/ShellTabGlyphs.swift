import SwiftUI

enum ShellTabKind: String, CaseIterable {
    case dashboard
    case chats
    case team
    case live
    case platform
}

struct ShellAnimatedTabGlyph: View {
    let kind: ShellTabKind
    var progress: CGFloat
    var reduceMotion: Bool

    private var clampedProgress: CGFloat {
        min(1, max(0, progress))
    }

    var body: some View {
        ZStack {
            glyph
                .foregroundStyle(foreground)
        }
        .frame(width: 26, height: 26)
        .animation(reduceMotion ? nil : .interactiveSpring(response: 0.34, dampingFraction: 0.82), value: clampedProgress)
    }

    private var foreground: Color {
        Color.primary.opacity(0.28 + (0.72 * Double(clampedProgress)))
    }

    @ViewBuilder
    private var glyph: some View {
        switch kind {
        case .dashboard:
            DashboardGlyph(progress: clampedProgress)
        case .chats:
            ChatsGlyph(progress: clampedProgress)
        case .team:
            TeamGlyph(progress: clampedProgress)
        case .live:
            LiveGlyph(progress: clampedProgress)
        case .platform:
            PlatformGlyph(progress: clampedProgress)
        }
    }
}

private struct DashboardGlyph: View {
    let progress: CGFloat

    var body: some View {
        Canvas { context, size in
            let stroke = StrokeStyle(lineWidth: 1.65, lineCap: .round, lineJoin: .round)
            let inset: CGFloat = 3
            let rect = CGRect(x: inset, y: inset + (1 - progress) * 2, width: size.width - inset * 2, height: size.height - inset * 2 - (1 - progress) * 2)
            var frame = Path(roundedRect: rect, cornerRadius: 4)
            context.stroke(frame, with: .foreground, style: stroke)

            let barY = rect.minY + 5 + progress * 1.5
            var bar = Path()
            bar.move(to: CGPoint(x: rect.minX + 4, y: barY))
            bar.addLine(to: CGPoint(x: rect.maxX - 4, y: barY))
            context.stroke(bar, with: .foreground, style: stroke)

            let chartBase = rect.maxY - 5
            let heights: [CGFloat] = [6, 10, 8, 12]
            for (index, height) in heights.enumerated() {
                let x = rect.minX + 5 + CGFloat(index) * 4.5
                let animated = height * (0.55 + 0.45 * progress)
                var line = Path()
                line.move(to: CGPoint(x: x, y: chartBase))
                line.addLine(to: CGPoint(x: x, y: chartBase - animated))
                context.stroke(line, with: .foreground, style: stroke)
            }
        }
    }
}

private struct ChatsGlyph: View {
    let progress: CGFloat

    var body: some View {
        Canvas { context, size in
            let stroke = StrokeStyle(lineWidth: 1.65, lineCap: .round, lineJoin: .round)
            let offset = (1 - progress) * 1.5
            var left = Path(roundedRect: CGRect(x: 2, y: 5 + offset, width: 12, height: 10), cornerRadius: 4)
            context.stroke(left, with: .foreground, style: stroke)
            var right = Path(roundedRect: CGRect(x: 10, y: 3 - offset, width: 12, height: 10), cornerRadius: 4)
            context.stroke(right, with: .foreground, style: stroke)

            if progress > 0.2 {
                var tail = Path()
                tail.move(to: CGPoint(x: 6, y: 15 + offset))
                tail.addLine(to: CGPoint(x: 4, y: 18 + offset))
                tail.addLine(to: CGPoint(x: 9, y: 15 + offset))
                context.fill(tail, with: .foreground)
            }
        }
    }
}

private struct TeamGlyph: View {
    let progress: CGFloat

    var body: some View {
        Canvas { context, size in
            let stroke = StrokeStyle(lineWidth: 1.65, lineCap: .round, lineJoin: .round)
            let centerY = size.height * 0.5
            let spread = 3.5 * progress
            for offset in [-spread, 0, spread] {
                let x = size.width * 0.5 + offset
                let radius = 3.2 - abs(offset) * 0.15
                let circle = Path(ellipseIn: CGRect(x: x - radius, y: centerY - 6, width: radius * 2, height: radius * 2))
                context.stroke(circle, with: .foreground, style: stroke)
                var body = Path()
                body.move(to: CGPoint(x: x - 4.5, y: centerY + 1.5))
                body.addQuadCurve(to: CGPoint(x: x + 4.5, y: centerY + 1.5), control: CGPoint(x: x, y: centerY + 6.5 + progress * 1.5))
                context.stroke(body, with: .foreground, style: stroke)
            }
        }
    }
}

private struct LiveGlyph: View {
    let progress: CGFloat

    var body: some View {
        Canvas { context, size in
            let stroke = StrokeStyle(lineWidth: 1.65, lineCap: .round, lineJoin: .round)
            let bell = Path(roundedRect: CGRect(x: 7, y: 4, width: 12, height: 11), cornerRadius: 3)
            context.stroke(bell, with: .foreground, style: stroke)

            var clapper = Path()
            clapper.move(to: CGPoint(x: 13, y: 15))
            clapper.addLine(to: CGPoint(x: 13, y: 17 + progress))
            context.stroke(clapper, with: .foreground, style: stroke)

            let waveCount = 3
            for index in 0..<waveCount {
                let amp = CGFloat(index + 1) * (2 + progress * 2)
                var wave = Path()
                let baseX: CGFloat = index == 0 ? 4 : 19
                let direction: CGFloat = index == 0 ? -1 : 1
                wave.move(to: CGPoint(x: baseX, y: 10))
                wave.addQuadCurve(
                    to: CGPoint(x: baseX + direction * amp, y: 10),
                    control: CGPoint(x: baseX + direction * amp * 0.5, y: 10 - amp)
                )
                wave.addQuadCurve(
                    to: CGPoint(x: baseX, y: 10),
                    control: CGPoint(x: baseX + direction * amp * 0.5, y: 10 + amp)
                )
                context.stroke(wave, with: .foreground, style: stroke)
            }
        }
    }
}

private struct PlatformGlyph: View {
    let progress: CGFloat

    var body: some View {
        Canvas { context, size in
            let stroke = StrokeStyle(lineWidth: 1.65, lineCap: .round, lineJoin: .round)
            let inset: CGFloat = 3
            let cell = (size.width - inset * 2 - 3) / 2
            let lift = (1 - progress) * 1.2
            for row in 0..<2 {
                for col in 0..<2 {
                    let idx = row * 2 + col
                    let extra = idx == 0 ? progress * 1.5 : 0
                    let rect = CGRect(
                        x: inset + CGFloat(col) * (cell + 3),
                        y: inset + CGFloat(row) * (cell + 3) - lift - extra,
                        width: cell,
                        height: cell
                    )
                    let tile = Path(roundedRect: rect, cornerRadius: 3.5)
                    context.stroke(tile, with: .foreground, style: stroke)
                }
            }
        }
    }
}
