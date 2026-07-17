import SwiftUI

/// Coordinates splash visibility until bootstrap and the launch animation both finish.
@MainActor
final class LaunchSplashController: ObservableObject {
    @Published private(set) var isVisible = true

    private var animationFinished = false
    private var bootstrapFinished = false
    private var interactiveLoginPending = false

    func markAnimationFinished() {
        animationFinished = true
        dismissIfReady()
    }

    func markBootstrapFinished() {
        bootstrapFinished = true
        dismissIfReady()
    }

    /// Replay the splash after an interactive login (black background + logo animation).
    func replayAfterLogin() {
        guard !interactiveLoginPending else { return }
        interactiveLoginPending = true
        animationFinished = false
        isVisible = true
    }

    func clearInteractiveLoginReplay() {
        interactiveLoginPending = false
    }

    private func dismissIfReady() {
        guard animationFinished, bootstrapFinished, isVisible else { return }
        withAnimation(.easeOut(duration: 0.28)) {
            isVisible = false
        }
        if interactiveLoginPending {
            interactiveLoginPending = false
        }
    }
}
