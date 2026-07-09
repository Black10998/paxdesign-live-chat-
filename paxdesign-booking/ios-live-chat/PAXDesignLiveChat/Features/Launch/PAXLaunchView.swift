import SwiftUI

/// Premium animated launch loader — native SwiftUI interpretation of the PAX stroke loader.
struct PAXLaunchView: View {
    var onFinished: (() -> Void)?

    @State private var dashPhase: CGFloat = 0
    @State private var spinRotation: Double = 0
    @State private var lettersOpacity: Double = 0
    @State private var subtitleOpacity: Double = 0
    @State private var exitOpacity: Double = 1

    private let letterSize: CGFloat = 72
    private let letterSpacing: CGFloat = 14

    var body: some View {
        ZStack {
            PAXBrand.launchBackground
                .ignoresSafeArea()

            RadialGradient(
                colors: [PAXBrand.accent.opacity(0.08), .clear],
                center: .center,
                startRadius: 20,
                endRadius: 280
            )
            .ignoresSafeArea()

            VStack(spacing: 28) {
                HStack(spacing: letterSpacing) {
                    PAXAnimatedLetter(
                        letter: .p,
                        size: letterSize,
                        dashPhase: dashPhase,
                        spinRotation: 0,
                        accent: PAXBrand.accent
                    )
                    PAXAnimatedLetter(
                        letter: .a,
                        size: letterSize,
                        dashPhase: dashPhase,
                        spinRotation: 0,
                        accent: PAXBrand.accent
                    )
                    PAXAnimatedLetter(
                        letter: .x,
                        size: letterSize,
                        dashPhase: dashPhase,
                        spinRotation: spinRotation,
                        accent: PAXBrand.accent
                    )
                }
                .opacity(lettersOpacity)

                Text("PAXDesign Live Chat")
                    .font(.system(size: 15, weight: .medium, design: .rounded))
                    .tracking(0.6)
                    .foregroundStyle(.white.opacity(0.55))
                    .opacity(subtitleOpacity)
            }
            .opacity(exitOpacity)
        }
        .onAppear { runLaunchSequence() }
    }

    private func runLaunchSequence() {
        withAnimation(.easeOut(duration: 0.6)) {
            lettersOpacity = 1
        }

        withAnimation(.easeOut(duration: 0.8).delay(0.35)) {
            subtitleOpacity = 1
        }

        withAnimation(.linear(duration: 2).repeatForever(autoreverses: false)) {
            dashPhase = 1
        }

        withAnimation(.easeInOut(duration: 8).repeatForever(autoreverses: false)) {
            spinRotation = 360
        }

        DispatchQueue.main.asyncAfter(deadline: .now() + PAXBrand.launchDuration - 0.45) {
            withAnimation(.easeInOut(duration: 0.45)) {
                exitOpacity = 0
            }
        }

        DispatchQueue.main.asyncAfter(deadline: .now() + PAXBrand.launchDuration) {
            onFinished?()
        }
    }
}

// MARK: - Animated Letter

private struct PAXAnimatedLetter: View {
    enum Letter { case p, a, x }

    let letter: Letter
    let size: CGFloat
    let dashPhase: CGFloat
    let spinRotation: Double
    let accent: Color

    private var strokeWidth: CGFloat {
        switch letter {
        case .p: return size * 0.08
        case .a: return size * 0.12
        case .x: return size * 0.11
        }
    }

    var body: some View {
        ZStack {
            letterPath
                .trim(from: 0, to: dashTrimEnd)
                .stroke(
                    accent,
                    style: StrokeStyle(
                        lineWidth: strokeWidth,
                        lineCap: .round,
                        lineJoin: .round,
                        dash: dashPattern,
                        dashPhase: dashPhase * 360
                    )
                )
                .frame(width: size, height: size)
                .rotationEffect(.degrees(letter == .x ? spinRotation : 0))
        }
    }

    private var dashTrimEnd: CGFloat {
        0.85 + 0.15 * sin(dashPhase * .pi * 2)
    }

    private var dashPattern: [CGFloat] {
        switch letter {
        case .p, .a:
            return [size * 0.6, size * 0.15]
        case .x:
            return [size * 0.5, size * 0.2]
        }
    }

    private var letterPath: Path {
        switch letter {
        case .p: return pPath
        case .a: return aPath
        case .x: return xPath
        }
    }

    private var pPath: Path {
        var path = Path()
        let s = size
        let m = s * 0.2
        path.move(to: CGPoint(x: m, y: m))
        path.addLine(to: CGPoint(x: s * 0.8, y: m))
        path.addLine(to: CGPoint(x: s * 0.8, y: s * 0.27))
        path.addLine(to: CGPoint(x: s * 0.27, y: s * 0.27))
        path.addLine(to: CGPoint(x: s * 0.27, y: s * 0.5))
        path.addLine(to: CGPoint(x: s * 0.7, y: s * 0.5))
        path.addLine(to: CGPoint(x: s * 0.7, y: s * 0.57))
        path.addLine(to: CGPoint(x: s * 0.25, y: s * 0.57))
        path.addLine(to: CGPoint(x: s * 0.25, y: s * 0.8))
        path.addLine(to: CGPoint(x: s * 0.8, y: s * 0.8))
        path.addLine(to: CGPoint(x: s * 0.8, y: s * 0.87))
        path.addLine(to: CGPoint(x: m, y: s * 0.87))
        path.closeSubpath()
        return path
    }

    private var aPath: Path {
        var path = Path()
        let s = size
        let m = s * 0.2
        path.move(to: CGPoint(x: m, y: m))
        path.addLine(to: CGPoint(x: s * 0.5, y: s * 0.8))
        path.addLine(to: CGPoint(x: s * 0.8, y: m))
        return path
    }

    private var xPath: Path {
        var path = Path()
        let s = size
        let inset = s * 0.22
        path.move(to: CGPoint(x: inset, y: inset))
        path.addLine(to: CGPoint(x: s - inset, y: s - inset))
        path.move(to: CGPoint(x: s - inset, y: inset))
        path.addLine(to: CGPoint(x: inset, y: s - inset))
        return path
    }
}
