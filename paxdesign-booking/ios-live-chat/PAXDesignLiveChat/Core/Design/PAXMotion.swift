import SwiftUI

enum PAXMotion {
    static let listInsert = AnyTransition.opacity
    static let cardAppear = AnyTransition.opacity
    static let modulePush = AnyTransition.opacity
    static let heroReveal = AnyTransition.opacity

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
        padding(12)
            .background(Color(.secondarySystemGroupedBackground), in: RoundedRectangle(cornerRadius: 12, style: .continuous))
    }

    func paxScreenBackground() -> some View {
        background(PAXBackground())
    }

    func paxModuleTransition() -> some View {
        self
    }
}
