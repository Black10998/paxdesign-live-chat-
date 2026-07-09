import AVFoundation

/// Subtle send confirmation using bundled tone.
@MainActor
final class MessageSendSound {
    static let shared = MessageSendSound()

    private var lastPlayed: Date?
    private let minInterval: TimeInterval = 0.35

    func playIfEnabled() {
        guard AppSettingsStore.shared.sendSoundEnabled else { return }
        let now = Date()
        if let last = lastPlayed, now.timeIntervalSince(last) < minInterval { return }
        lastPlayed = now
        PAXNotificationSound.shared.play(.send)
    }
}
