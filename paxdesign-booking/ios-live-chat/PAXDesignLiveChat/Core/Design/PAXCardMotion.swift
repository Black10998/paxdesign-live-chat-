import SwiftUI

// MARK: - Entrance animations

struct PAXStaggeredAppearModifier: ViewModifier {
    let index: Int
    let baseDelay: Double
    @State private var visible = false

    func body(content: Content) -> some View {
        content
            .opacity(visible ? 1 : 0)
            .offset(y: visible ? 0 : 14)
            .scaleEffect(visible ? 1 : 0.97)
            .onAppear {
                withAnimation(.spring(response: 0.48, dampingFraction: 0.86).delay(baseDelay + Double(index) * 0.05)) {
                    visible = true
                }
            }
    }
}

extension View {
    func paxStaggeredAppear(index: Int, baseDelay: Double = 0.04) -> some View {
        modifier(PAXStaggeredAppearModifier(index: index, baseDelay: baseDelay))
    }
}

// MARK: - Animated glass reflection

struct PAXAnimatedGlassReflection: ViewModifier {
    let cornerRadius: CGFloat
    @State private var phase: CGFloat = -0.35

    func body(content: Content) -> some View {
        content
            .overlay {
                GeometryReader { geo in
                    RoundedRectangle(cornerRadius: cornerRadius, style: .continuous)
                        .fill(
                            LinearGradient(
                                colors: [
                                    .clear,
                                    Color.white.opacity(0.14),
                                    Color.white.opacity(0.05),
                                    .clear
                                ],
                                startPoint: UnitPoint(x: phase, y: 0),
                                endPoint: UnitPoint(x: phase + 0.42, y: 1)
                            )
                        )
                        .blendMode(.overlay)
                        .allowsHitTesting(false)
                        .onAppear {
                            withAnimation(.easeInOut(duration: 5.5).repeatForever(autoreverses: true)) {
                                phase = 0.65
                            }
                        }
                }
            }
    }
}

extension View {
    func paxAnimatedGlassReflection(cornerRadius: CGFloat) -> some View {
        modifier(PAXAnimatedGlassReflection(cornerRadius: cornerRadius))
    }
}

// MARK: - Decorative card accents

struct PAXCardDecorLayer: View {
    let cornerRadius: CGFloat
    let tint: Color

    var body: some View {
        ZStack(alignment: .topTrailing) {
            Circle()
                .fill(tint.opacity(0.07))
                .frame(width: 72, height: 72)
                .offset(x: 18, y: -22)
                .blur(radius: 1)

            RoundedRectangle(cornerRadius: cornerRadius * 0.55, style: .continuous)
                .stroke(tint.opacity(0.12), lineWidth: 1)
                .frame(width: 44, height: 44)
                .rotationEffect(.degrees(-12))
                .offset(x: -10, y: 28)
                .allowsHitTesting(false)
        }
        .allowsHitTesting(false)
    }
}

// MARK: - Long-press help

struct PAXCardHelpModifier: ViewModifier {
    let helpText: String
    @State private var showHelp = false

    func body(content: Content) -> some View {
        content
            .simultaneousGesture(
                LongPressGesture(minimumDuration: 0.55)
                    .onEnded { _ in
                        PAXHaptics.light()
                        showHelp = true
                    }
            )
            .popover(isPresented: $showHelp, arrowEdge: .bottom) {
                Text(helpText)
                    .font(.subheadline)
                    .foregroundStyle(PAXTheme.textPrimary)
                    .multilineTextAlignment(.leading)
                    .padding(14)
                    .frame(maxWidth: 280)
                    .presentationCompactAdaptation(.popover)
            }
    }
}

extension View {
    func paxCardHelp(_ text: String) -> some View {
        modifier(PAXCardHelpModifier(helpText: text))
    }
}
