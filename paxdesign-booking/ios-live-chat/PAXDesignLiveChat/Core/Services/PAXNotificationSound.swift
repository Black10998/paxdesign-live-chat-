import AVFoundation
import AudioToolbox

/// Reliable notification tone playback with configurable styles and system fallback.
@MainActor
final class PAXNotificationSound {
    static let shared = PAXNotificationSound()

    enum Tone: String, CaseIterable {
        case message
        case liveRequest
        case aiAlert
        case send
        case typing
    }

    struct ToneOption: Identifiable, Hashable {
        let id: AppSettingsStore.NotificationToneStyle
        let title: String
    }

    private var activePlayer: AVAudioPlayer?
    private var liveLoopPlayer: AVAudioPlayer?
    private var liveLoopTask: Task<Void, Never>?

    private init() {}

    func options(for tone: Tone) -> [ToneOption] {
        AppSettingsStore.NotificationToneStyle.allCases.map {
            ToneOption(id: $0, title: $0.title)
        }
    }

    func selectedStyle(for tone: Tone) -> AppSettingsStore.NotificationToneStyle {
        let settings = AppSettingsStore.shared
        switch tone {
        case .message: return settings.messageToneStyle
        case .liveRequest: return settings.liveToneStyle
        case .aiAlert: return settings.aiToneStyle
        case .send: return settings.sendToneStyle
        case .typing: return settings.typingToneStyle
        }
    }

    func setSelectedStyle(_ style: AppSettingsStore.NotificationToneStyle, for tone: Tone) {
        switch tone {
        case .message:
            AppSettingsStore.shared.messageToneStyle = style
        case .liveRequest:
            AppSettingsStore.shared.liveToneStyle = style
        case .aiAlert:
            AppSettingsStore.shared.aiToneStyle = style
        case .send:
            AppSettingsStore.shared.sendToneStyle = style
        case .typing:
            AppSettingsStore.shared.typingToneStyle = style
        }
    }

    private var volume: Float {
        AppSettingsStore.shared.ringtoneVolume
    }

    func play(_ tone: Tone, respectSettings: Bool = true) {
        if respectSettings {
            switch tone {
            case .message:
                guard AppSettingsStore.shared.messageSoundEnabled else { return }
            case .liveRequest:
                guard AppSettingsStore.shared.incomingCallSoundEnabled else { return }
            case .send:
                guard AppSettingsStore.shared.sendSoundEnabled else { return }
            case .aiAlert:
                guard AppSettingsStore.shared.messageSoundEnabled else { return }
            case .typing:
                guard AppSettingsStore.shared.typingSoundEnabled else { return }
            }
        }

        activateSession(for: tone)
        let style = selectedStyle(for: tone)

        guard let player = playerForBundleTone(tone, style: style) else {
            playSystemFallback(for: tone)
            return
        }

        player.stop()
        player.currentTime = 0
        player.volume = volume
        player.numberOfLoops = 0
        player.prepareToPlay()
        player.play()
        activePlayer = player
    }

    func startLiveRequestLoop() {
        guard AppSettingsStore.shared.incomingCallSoundEnabled else { return }
        stopLiveRequestLoop()

        activateSession(for: .liveRequest)
        let style = selectedStyle(for: .liveRequest)

        if let loopPlayer = playerForBundleTone(.liveRequest, style: style) {
            loopPlayer.volume = volume
            loopPlayer.numberOfLoops = -1
            loopPlayer.prepareToPlay()
            loopPlayer.play()
            liveLoopPlayer = loopPlayer
            return
        }

        liveLoopTask = Task { @MainActor in
            while !Task.isCancelled {
                play(.liveRequest, respectSettings: false)
                try? await Task.sleep(nanoseconds: 2_200_000_000)
            }
        }
    }

    func stopLiveRequestLoop() {
        liveLoopTask?.cancel()
        liveLoopTask = nil
        liveLoopPlayer?.stop()
        liveLoopPlayer = nil
    }

    private func activateSession(for tone: Tone) {
        let session = AVAudioSession.sharedInstance()
        let category: AVAudioSession.Category = tone == .liveRequest ? .playback : .ambient
        let options: AVAudioSession.CategoryOptions = tone == .liveRequest ? [.duckOthers] : [.mixWithOthers, .duckOthers]
        try? session.setCategory(category, mode: .default, options: options)
        try? session.setActive(true)
    }

    private func playerForBundleTone(_ tone: Tone, style: AppSettingsStore.NotificationToneStyle) -> AVAudioPlayer? {
        let baseNames = bundleToneCandidates(for: tone, style: style)
        for name in baseNames {
            for ext in ["wav", "mp3", "caf", "aiff", "m4a"] {
                guard let url = Bundle.main.url(forResource: name, withExtension: ext) else { continue }
                if let player = try? AVAudioPlayer(contentsOf: url) {
                    return player
                }
            }
        }
        return nil
    }

    private func bundleToneCandidates(for tone: Tone, style: AppSettingsStore.NotificationToneStyle) -> [String] {
        let tonePrefix: String
        switch tone {
        case .message: tonePrefix = "pax-message"
        case .liveRequest: tonePrefix = "pax-live-request"
        case .aiAlert: tonePrefix = "pax-ai-alert"
        case .send: tonePrefix = "pax-send"
        case .typing: tonePrefix = "pax-typing"
        }
        return [
            "\(tonePrefix)-\(style.rawValue)",
            tonePrefix,
        ]
    }

    private func playSystemFallback(for tone: Tone) {
        let id: SystemSoundID
        switch (tone, selectedStyle(for: tone)) {
        case (.message, .classic): id = 1003
        case (.message, .chime): id = 1016
        case (.message, .pulse): id = 1110
        case (.message, .bell): id = 1013
        case (.message, .digital): id = 1104
        case (.message, .soft): id = 1007
        case (.message, .echo): id = 1021

        case (.liveRequest, .classic): id = 1005
        case (.liveRequest, .chime): id = 1009
        case (.liveRequest, .pulse): id = 1014
        case (.liveRequest, .bell): id = 1013
        case (.liveRequest, .digital): id = 1107
        case (.liveRequest, .soft): id = 1007
        case (.liveRequest, .echo): id = 1021

        case (.aiAlert, .classic): id = 1013
        case (.aiAlert, .chime): id = 1022
        case (.aiAlert, .pulse): id = 1111
        case (.aiAlert, .bell): id = 1025
        case (.aiAlert, .digital): id = 1103
        case (.aiAlert, .soft): id = 1007
        case (.aiAlert, .echo): id = 1021

        case (.send, .classic): id = 1004
        case (.send, .chime): id = 1106
        case (.send, .pulse): id = 1110
        case (.send, .bell): id = 1012
        case (.send, .digital): id = 1104
        case (.send, .soft): id = 1007
        case (.send, .echo): id = 1021

        case (.typing, .classic): id = 1104
        case (.typing, .chime): id = 1106
        case (.typing, .pulse): id = 1110
        case (.typing, .bell): id = 1011
        case (.typing, .digital): id = 1103
        case (.typing, .soft): id = 1007
        case (.typing, .echo): id = 1021
        }
        AudioServicesPlaySystemSound(id)
    }
}
