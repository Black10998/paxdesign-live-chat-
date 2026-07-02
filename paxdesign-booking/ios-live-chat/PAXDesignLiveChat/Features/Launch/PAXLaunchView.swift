import SwiftUI

/// Premium animated launch experience — icon-first, minimal, Apple HIG inspired.
struct PAXLaunchView: View {
    enum Phase: Int {
        case hidden = 0
        case iconReveal = 1
        case breathe = 2
        case complete = 3
    }

    @State private var phase: Phase = .hidden
    @State private var ringScale: CGFloat = 0.6
    @State private var ringOpacity: Double = 0
    @State private var iconScale: CGFloat = 0.72
    @State private var iconOpacity: Double = 0
    @State private var shimmerOffset: CGFloat = -120

    private let iconSize: CGFloat = 108

    var body: some View {
        ZStack {
            launchBackground

            ZStack {
                // Ambient accent ring
                Circle()
                    .stroke(
                        LinearGradient(
                            colors: [
                                PAXTheme.accent.opacity(0.45),
                                PAXTheme.accent.opacity(0.05),
                                Color.clear
                            ],
                            startPoint: .topLeading,
                            endPoint: .bottomTrailing
                        ),
                        lineWidth: 1.5
                    )
                    .frame(width: iconSize * 1.55, height: iconSize * 1.55)
                    .scaleEffect(ringScale)
                    .opacity(ringOpacity)

                // Soft radial glow
                Circle()
                    .fill(
                        RadialGradient(
                            colors: [PAXTheme.accent.opacity(0.18), .clear],
                            center: .center,
                            startRadius: 4,
                            endRadius: iconSize * 0.85
                        )
                    )
                    .frame(width: iconSize * 1.4, height: iconSize * 1.4)
                    .opacity(phase.rawValue >= Phase.breathe.rawValue ? 0.9 : 0.4)
                    .scaleEffect(phase == .breathe ? 1.04 : 1.0)
                    .animation(
                        phase == .breathe
                            ? .easeInOut(duration: 1.6).repeatForever(autoreverses: true)
                            : .default,
                        value: phase
                    )

                PAXAppMark.image(size: iconSize)
                    .scaleEffect(iconScale)
                    .opacity(iconOpacity)
                    .shadow(color: PAXTheme.accent.opacity(0.25), radius: 20, y: 8)

                // Subtle shimmer sweep
                RoundedRectangle(cornerRadius: iconSize * PAXAppMark.cornerRadiusRatio, style: .continuous)
                    .fill(
                        LinearGradient(
                            colors: [.clear, .white.opacity(0.12), .clear],
                            startPoint: .leading,
                            endPoint: .trailing
                        )
                    )
                    .frame(width: iconSize * 0.35, height: iconSize * 1.1)
                    .rotationEffect(.degrees(18))
                    .offset(x: shimmerOffset)
                    .opacity(phase.rawValue >= Phase.iconReveal.rawValue ? 0.7 : 0)
                    .mask(
                        PAXAppMark.image(size: iconSize)
                    )
            }
        }
        .onAppear { runLaunchSequence() }
    }

    private var launchBackground: some View {
        Color("LaunchBackground")
            .ignoresSafeArea()
    }

    private func runLaunchSequence() {
        withAnimation(.spring(response: 0.55, dampingFraction: 0.78)) {
            phase = .iconReveal
            iconScale = 1.0
            iconOpacity = 1.0
            ringScale = 1.0
            ringOpacity = 0.85
        }

        withAnimation(.easeOut(duration: 0.9).delay(0.15)) {
            shimmerOffset = 120
        }

        DispatchQueue.main.asyncAfter(deadline: .now() + 0.5) {
            withAnimation(.easeInOut(duration: 0.4)) {
                phase = .breathe
            }
        }
    }
}
