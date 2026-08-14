import SwiftUI

enum PAXMotion {
    static let listInsert = AnyTransition.opacity.combined(with: .move(edge: .bottom))
    static let cardAppear = AnyTransition.opacity.combined(with: .scale(scale: 0.97))
    static let modulePush = AnyTransition.opacity.combined(with: .move(edge: .trailing))
    static let heroReveal = AnyTransition.opacity.combined(with: .move(edge: .top))
    static let chatInsert = AnyTransition.asymmetric(
        insertion: .opacity.combined(with: .scale(scale: 0.94)).combined(with: .offset(y: 10)),
        removal: .opacity
    )

    static let tabSelect = Animation.spring(response: 0.32, dampingFraction: 0.82)
    static let chatInsertSpring = Animation.spring(response: 0.38, dampingFraction: 0.86)
    static let buttonPress = Animation.easeOut(duration: 0.15)

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
        modifier(PAXScreenBackgroundModifier())
    }

    func paxRevolutGroupedList() -> some View {
        listStyle(.insetGrouped)
            .scrollContentBackground(.hidden)
            .paxScreenBackground()
    }
}

private struct PAXScreenBackgroundModifier: ViewModifier {
    @ObservedObject private var settings = AppSettingsStore.shared
    @Environment(\.colorScheme) private var colorScheme

    private var toolbarScheme: ColorScheme {
        settings.resolvedIsDark(for: colorScheme) ? .dark : .light
    }

    func body(content: Content) -> some View {
        content
            .background(PAXBackground())
            .toolbarBackground(settings.palette.background(isDark: settings.resolvedIsDark(for: colorScheme)), for: .navigationBar)
            .toolbarBackground(.visible, for: .navigationBar)
            .toolbarColorScheme(toolbarScheme, for: .navigationBar)
    }
}

extension View {
    func paxModuleTransition() -> some View {
        self
    }
}
