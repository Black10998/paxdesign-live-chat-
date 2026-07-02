import AVFoundation
import AudioToolbox

/// Distinctive live-request alert — premium two-tone pattern, App Store-safe volume.
@MainActor
final class IncomingCallRingtone {
    static let shared = IncomingCallRingtone()

    private var ringTask: Task<Void, Never>?
    private let pattern: [SystemSoundID] = [1005, 1013]

    func startRinging() {
        guard AppSettingsStore.shared.incomingCallSoundEnabled else { return }
        stopRinging()

        ringTask = Task { @MainActor in
            let session = AVAudioSession.sharedInstance()
            try? session.setCategory(.playback, mode: .default, options: [.duckOthers])
            try? session.setActive(true)

            var index = 0
            while !Task.isCancelled {
                let sound = pattern[index % pattern.count]
                AudioServicesPlaySystemSound(sound)
                index += 1
                try? await Task.sleep(nanoseconds: 1_100_000_000)
            }
        }
    }

    func stopRinging() {
        ringTask?.cancel()
        ringTask = nil
    }
}
