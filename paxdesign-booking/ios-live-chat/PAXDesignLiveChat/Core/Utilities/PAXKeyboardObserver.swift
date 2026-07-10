import Combine
import SwiftUI
import UIKit

/// Publishes keyboard height for chat screens — keeps composer flush above the keyboard.
@MainActor
final class PAXKeyboardObserver: ObservableObject {
    static let shared = PAXKeyboardObserver()

    @Published private(set) var height: CGFloat = 0
    @Published private(set) var isVisible = false

    private var cancellables = Set<AnyCancellable>()

    private init() {
        let willShow = NotificationCenter.default.publisher(for: UIResponder.keyboardWillShowNotification)
        let willHide = NotificationCenter.default.publisher(for: UIResponder.keyboardWillHideNotification)
        let willChange = NotificationCenter.default.publisher(for: UIResponder.keyboardWillChangeFrameNotification)

        Publishers.Merge3(willShow, willHide, willChange)
            .receive(on: RunLoop.main)
            .sink { [weak self] note in
                self?.apply(note)
            }
            .store(in: &cancellables)
    }

    private func apply(_ notification: Notification) {
        guard let frame = notification.userInfo?[UIResponder.keyboardFrameEndUserInfoKey] as? CGRect else {
            return
        }
        let screenHeight = UIScreen.main.bounds.height
        let overlap = max(0, screenHeight - frame.origin.y)
        let bottomInset = UIApplication.shared.connectedScenes
            .compactMap { $0 as? UIWindowScene }
            .flatMap(\.windows)
            .first(where: \.isKeyWindow)?
            .safeAreaInsets.bottom ?? 0
        height = max(0, overlap - bottomInset)
        isVisible = overlap > 0 && notification.name != UIResponder.keyboardWillHideNotification
    }
}

private struct KeyboardBottomInsetModifier: ViewModifier {
    @ObservedObject private var keyboard = PAXKeyboardObserver.shared

    func body(content: Content) -> some View {
        content
            .padding(.bottom, keyboard.isVisible ? max(0, keyboard.height - 2) : 0)
            .animation(.easeOut(duration: 0.22), value: keyboard.height)
    }
}

extension View {
    func paxKeyboardBottomInset() -> some View {
        modifier(KeyboardBottomInsetModifier())
    }
}
