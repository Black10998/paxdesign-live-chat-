import AVFoundation

/// Short typing feedback while the admin composes — stops immediately when typing stops (no looping).
@MainActor
final class AdminTypingSound {
    static let shared = AdminTypingSound()

    private static let soundURL = URL(string: "https://paxdesign.at/wp-content/uploads/2026/06/freesound_community-writing-a-text-message-41141.mp3")!
    private static let idleNanoseconds: UInt64 = 1_800_000_000
    private static let volume: Float = 0.32

    private var player: AVAudioPlayer?
    private var idleTask: Task<Void, Never>?

    func typingActivity() {
        guard AppSettingsStore.shared.typingSoundEnabled else { return }

        idleTask?.cancel()
        if player?.isPlaying != true {
            playOnce()
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
        player?.stop()
        player?.currentTime = 0
        player = nil
    }

    private func playOnce() {
        let session = AVAudioSession.sharedInstance()
        try? session.setCategory(.ambient, mode: .default, options: [.mixWithOthers])
        try? session.setActive(true)

        guard let audio = try? AVAudioPlayer(contentsOf: Self.soundURL) else { return }
        audio.volume = AppSettingsStore.shared.ringtoneVolume * 0.36
        audio.numberOfLoops = 0
        audio.prepareToPlay()
        audio.play()
        player = audio
    }
}
