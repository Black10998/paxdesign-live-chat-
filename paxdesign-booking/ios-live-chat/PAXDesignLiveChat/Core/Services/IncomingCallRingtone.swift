import AVFoundation
import UIKit

@MainActor
final class IncomingCallRingtone {
    static let shared = IncomingCallRingtone()

    private var player: AVAudioPlayer?
    private var ringTask: Task<Void, Never>?

    func startRinging() {
        guard AppSettingsStore.shared.incomingCallSoundEnabled else { return }
        stopRinging()

        ringTask = Task { @MainActor in
            let session = AVAudioSession.sharedInstance()
            try? session.setCategory(.playback, mode: .default, options: [.duckOthers])
            try? session.setActive(true)

            guard let url = Bundle.main.url(forResource: "pax-live-request", withExtension: "wav") else { return }
            guard let audio = try? AVAudioPlayer(contentsOf: url) else { return }
            audio.numberOfLoops = -1
            audio.volume = AppSettingsStore.shared.ringtoneVolume
            audio.prepareToPlay()
            audio.play()
            player = audio

            while !Task.isCancelled, player != nil {
                try? await Task.sleep(nanoseconds: 500_000_000)
            }
        }
    }

    func stopRinging() {
        ringTask?.cancel()
        ringTask = nil
        player?.stop()
        player = nil
        try? AVAudioSession.sharedInstance().setActive(false, options: .notifyOthersOnDeactivation)
    }
}
