import SwiftUI

enum PAXMotion {
    static let listInsert = AnyTransition.asymmetric(
        insertion: .opacity.combined(with: .move(edge: .top)).combined(with: .scale(scale: 0.98)),
        removal: .opacity
    )

    static let cardAppear = AnyTransition.opacity.combined(with: .scale(scale: 0.97))
    static let modulePush = AnyTransition.asymmetric(
        insertion: .opacity.combined(with: .move(edge: .trailing)).combined(with: .scale(scale: 0.98)),
        removal: .opacity.combined(with: .move(edge: .leading))
    )
    static let heroReveal = AnyTransition.opacity.combined(with: .offset(y: 12))

    static func pressable<S: Shape>(_ shape: S, scale: CGFloat = 0.97) -> some ViewModifier {
        PressableModifier(shape: shape, scale: scale)
    }
}

private struct PressableModifier<S: Shape>: ViewModifier {
    let shape: S
    let scale: CGFloat
    @State private var pressed = false

    func body(content: Content) -> some View {
        content
            .scaleEffect(pressed ? scale : 1)
            .animation(PAXTheme.quickSpring, value: pressed)
            .simultaneousGesture(
                DragGesture(minimumDistance: 0)
                    .onChanged { _ in pressed = true }
                    .onEnded { _ in pressed = false }
            )
    }
}

extension View {
    func paxListRowAnimation() -> some View {
        listRowInsets(EdgeInsets(top: 6, leading: 16, bottom: 6, trailing: 16))
            .listRowSeparator(.hidden)
            .listRowBackground(Color.clear)
    }

    func paxNativeCard() -> some View {
        padding(14)
            .background(
                RoundedRectangle(cornerRadius: 16, style: .continuous)
                    .fill(PAXTheme.surface.opacity(0.92))
                    .overlay(
                        RoundedRectangle(cornerRadius: 16, style: .continuous)
                            .stroke(PAXTheme.border.opacity(0.6), lineWidth: 0.5)
                    )
            )
    }

    func paxScreenBackground() -> some View {
        background(PAXBackground())
    }

    func paxModuleTransition() -> some View {
        transition(PAXMotion.modulePush)
    }
}
