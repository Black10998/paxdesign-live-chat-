import Foundation

/// Short typing feedback while the admin composes.
@MainActor
final class AdminTypingSound {
    static let shared = AdminTypingSound()

    private static let idleNanoseconds: UInt64 = 1_500_000_000
    private let minInterval: TimeInterval = 0.65

    private var idleTask: Task<Void, Never>?
    private var lastPlayedAt: Date = .distantPast

    func typingActivity() {
        guard AppSettingsStore.shared.typingSoundEnabled else { return }

        idleTask?.cancel()
        let now = Date()
        if now.timeIntervalSince(lastPlayedAt) >= minInterval {
            lastPlayedAt = now
            PAXNotificationSound.shared.play(.typing)
        }

        idleTask = Task { [weak self] in
            try? await Task.sleep(nanoseconds: Self.idleNanoseconds)
            guard !Task.isCancelled else { return }
            self?.stop()
        }
    }

    func stop() {
        idleTask?.cancel()
        idleTask = nil
    }
}
