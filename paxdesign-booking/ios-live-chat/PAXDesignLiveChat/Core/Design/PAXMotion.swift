import SwiftUI

enum PAXMotion {
    static let listInsert = AnyTransition.opacity.combined(with: .move(edge: .bottom))
    static let cardAppear = AnyTransition.opacity.combined(with: .scale(scale: 0.97))
    static let modulePush = AnyTransition.opacity.combined(with: .move(edge: .trailing))
    static let heroReveal = AnyTransition.opacity.combined(with: .move(edge: .top))

    static func pressable<S: Shape>(_ shape: S, scale: CGFloat = 0.98) -> some ViewModifier {
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
            .simultaneousGesture(
                DragGesture(minimumDistance: 0)
                    .onChanged { _ in pressed = true }
                    .onEnded { _ in pressed = false }
            )
    }
}

extension View {
    func paxListRowAnimation() -> some View {
        listRowInsets(EdgeInsets(top: 4, leading: 16, bottom: 4, trailing: 16))
    }

    func paxNativeCard() -> some View {
        paxCard(.list)
    }

    func paxScreenBackground() -> some View {
        background(PAXBackground())
    }

    func paxModuleTransition() -> some View {
        self
    }
}
