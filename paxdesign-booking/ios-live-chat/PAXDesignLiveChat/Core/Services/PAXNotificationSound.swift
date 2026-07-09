import AVFoundation
import AudioToolbox

/// Bundled notification tones with volume control — used across send, message, live, and AI alerts.
@MainActor
final class PAXNotificationSound {
    static let shared = PAXNotificationSound()

    enum Tone: String, CaseIterable {
        case message = "pax-message"
        case liveRequest = "pax-live-request"
        case aiAlert = "pax-ai-alert"
        case send = "pax-send"
        case incoming = "pax-incoming"

        var fileExtension: String { "wav" }
    }

    private var players: [Tone: AVAudioPlayer] = [:]
    private var liveLoopTask: Task<Void, Never>?

    private init() {
        preload()
    }

    private func preload() {
        for tone in Tone.allCases {
            guard let url = Bundle.main.url(forResource: tone.rawValue, withExtension: tone.fileExtension) else {
                continue
            }
            if let player = try? AVAudioPlayer(contentsOf: url) {
                player.prepareToPlay()
                players[tone] = player
            }
        }
    }

    private var volume: Float {
        AppSettingsStore.shared.ringtoneVolume
    }

    func play(_ tone: Tone, respectSettings: Bool = true) {
        if respectSettings {
            switch tone {
            case .message, .incoming:
                guard AppSettingsStore.shared.messageSoundEnabled else { return }
            case .liveRequest:
                guard AppSettingsStore.shared.incomingCallSoundEnabled else { return }
            case .send:
                guard AppSettingsStore.shared.sendSoundEnabled else { return }
            case .aiAlert:
                guard AppSettingsStore.shared.messageSoundEnabled else { return }
            }
        }

        activateSession(for: tone)

        guard let template = players[tone] else {
            playSystemFallback(for: tone)
            return
        }

        if template.isPlaying {
            template.stop()
            template.currentTime = 0
        }
        template.volume = volume
        template.play()
    }

    func startLiveRequestLoop() {
        guard AppSettingsStore.shared.incomingCallSoundEnabled else { return }
        stopLiveRequestLoop()

        liveLoopTask = Task { @MainActor in
            activateSession(for: .liveRequest)
            while !Task.isCancelled {
                play(.liveRequest, respectSettings: false)
                try? await Task.sleep(nanoseconds: 2_200_000_000)
            }
        }
    }

    func stopLiveRequestLoop() {
        liveLoopTask?.cancel()
        liveLoopTask = nil
        players[.liveRequest]?.stop()
    }

    private func activateSession(for tone: Tone) {
        let session = AVAudioSession.sharedInstance()
        let category: AVAudioSession.Category = tone == .liveRequest ? .playback : .ambient
        let options: AVAudioSession.CategoryOptions = tone == .liveRequest ? [.duckOthers] : [.mixWithOthers]
        try? session.setCategory(category, mode: .default, options: options)
        try? session.setActive(true)
    }

    private func playSystemFallback(for tone: Tone) {
        let id: SystemSoundID
        switch tone {
        case .send: id = 1004
        case .liveRequest: id = 1005
        case .message, .incoming: id = 1003
        case .aiAlert: id = 1013
        }
        AudioServicesPlaySystemSound(id)
    }
}
