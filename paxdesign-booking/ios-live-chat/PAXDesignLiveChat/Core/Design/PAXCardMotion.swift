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
        EmptyView()
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
            }
    }
}

extension View {
    func paxCardHelp(_ text: String) -> some View {
        modifier(PAXCardHelpModifier(helpText: text))
    }
}
