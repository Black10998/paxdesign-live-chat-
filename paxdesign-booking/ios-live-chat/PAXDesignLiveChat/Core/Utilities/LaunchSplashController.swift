import SwiftUI

/// Coordinates splash visibility until bootstrap and the launch animation both finish.
@MainActor
final class LaunchSplashController: ObservableObject {
    @Published private(set) var isVisible = true

    private var animationFinished = false
    private var bootstrapFinished = false

    func markAnimationFinished() {
        animationFinished = true
        dismissIfReady()
    }

    func markBootstrapFinished() {
        bootstrapFinished = true
        dismissIfReady()
    }

    private func dismissIfReady() {
        guard animationFinished, bootstrapFinished, isVisible else { return }
        withAnimation(.easeOut(duration: 0.24)) {
            isVisible = false
        }
    }
}
