import AVFoundation
import AudioToolbox

/// Subtle send confirmation — professional, non-repetitive.
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

        let session = AVAudioSession.sharedInstance()
        try? session.setCategory(.ambient, mode: .default, options: [.mixWithOthers])
        try? session.setActive(true)

        AudioServicesPlaySystemSound(1004)
    }
}
