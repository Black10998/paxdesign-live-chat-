import AVFoundation
import SwiftUI

// MARK: - Waveform

struct TeamVoiceWaveformView: View {
    let levels: [CGFloat]
    var barColor: Color = Color.white.opacity(0.88)
    var idleLevel: CGFloat = 0.08
    var maxHeight: CGFloat = 40

    private var normalizedLevels: [CGFloat] {
        guard !levels.isEmpty else {
            return Array(repeating: idleLevel, count: 9)
        }
        return levels
    }

    var body: some View {
        HStack(alignment: .center, spacing: 4) {
            ForEach(Array(normalizedLevels.enumerated()), id: \.offset) { index, level in
                Capsule()
                    .fill(barColor)
                    .frame(width: 2, height: max(2, maxHeight * level))
                    .animation(
                        .spring(response: 0.22, dampingFraction: 0.72)
                            .delay(Double(index) * 0.03),
                        value: level
                    )
            }
        }
        .frame(maxWidth: .infinity)
        .frame(height: maxHeight)
    }
}

// MARK: - Activity panel (recording + playback)

enum TeamVoicePanelMode {
    case recording
    case playback
}

struct TeamVoiceActivityPanel: View {
    let mode: TeamVoicePanelMode
    var elapsed: TimeInterval = 0
    var duration: TimeInterval = 0
    var levels: [CGFloat] = []
    var isPlaying = false
    var onCancel: (() -> Void)?
    var onSend: (() -> Void)?
    var onTogglePlayback: (() -> Void)?

    private var headline: String {
        switch mode {
        case .recording:
            return L10n.TeamVoiceListening
        case .playback:
            return isPlaying ? L10n.TeamVoicePlaying : L10n.TeamVoicePaused
        }
    }

    private var timeLabel: String {
        let value = mode == .recording ? elapsed : duration
        let total = max(0, Int(value.rounded()))
        let minutes = total / 60
        let seconds = total % 60
        return String(format: "%d:%02d", minutes, seconds)
    }

    var body: some View {
        VStack(spacing: 10) {
            HStack(spacing: 6) {
                PAXIcon(mode == .recording ? "mic.fill" : (isPlaying ? "pause.fill" : "play.fill"), size: .inline)
                    .foregroundStyle(Color.white.opacity(0.72))
                Text(headline)
                    .font(.subheadline.weight(.medium))
                    .foregroundStyle(Color.white.opacity(0.72))
                Spacer(minLength: 0)
                Text(timeLabel)
                    .font(.caption.monospacedDigit())
                    .foregroundStyle(Color.white.opacity(0.55))
            }

            TeamVoiceWaveformView(levels: levels)

            if mode == .recording {
                HStack(spacing: 12) {
                    Button(L10n.CommonCancel) {
                        onCancel?()
                    }
                    .font(.subheadline.weight(.semibold))
                    .foregroundStyle(Color.white.opacity(0.7))

                    Spacer()

                    Button(L10n.TeamSendVoice) {
                        onSend?()
                    }
                    .font(.subheadline.weight(.bold))
                    .foregroundStyle(PAXBrand.accent)
                }
            } else {
                Button {
                    onTogglePlayback?()
                } label: {
                    Label {
                        Text(isPlaying ? L10n.TeamVoicePause : L10n.TeamVoicePlay)
                    } icon: {
                        PAXIcon(isPlaying ? "pause.circle.fill" : "play.circle.fill", size: .card)
                    }
                    .font(.subheadline.weight(.semibold))
                    .foregroundStyle(PAXBrand.accent)
                }
                .buttonStyle(.plain)
            }
        }
        .padding(.horizontal, 16)
        .padding(.vertical, 12)
        .background(
            RoundedRectangle(cornerRadius: 16, style: .continuous)
                .fill(Color(red: 0.035, green: 0.035, blue: 0.035))
                .overlay(
                    RoundedRectangle(cornerRadius: 16, style: .continuous)
                        .stroke(Color.white.opacity(0.08), lineWidth: 1)
                )
        )
        .padding(.horizontal, 12)
    }
}

// MARK: - Playback controller

@MainActor
final class TeamVoicePlaybackController: ObservableObject {
    static let shared = TeamVoicePlaybackController()

    @Published private(set) var isPlaying = false
    @Published private(set) var progress: Double = 0
    @Published private(set) var levels: [CGFloat] = Array(repeating: 0.1, count: 9)
    @Published private(set) var activeMessageId: Int?

    private var player: AVAudioPlayer?
    private var timer: Timer?
    private var meterPhase = 0

    private init() {}

    func toggle(message: LiveMessage) {
        if (message.audioUrl ?? "").hasPrefix("pending://") { return }
        if activeMessageId == message.id, isPlaying {
            pause()
            return
        }
        if activeMessageId == message.id, player != nil {
            resume()
            return
        }
        Task { await loadAndPlay(message: message) }
    }

    func stop() {
        timer?.invalidate()
        timer = nil
        player?.stop()
        player = nil
        isPlaying = false
        progress = 0
        levels = Array(repeating: 0.1, count: 9)
        activeMessageId = nil
        try? AVAudioSession.sharedInstance().setActive(false, options: .notifyOthersOnDeactivation)
    }

    private func pause() {
        player?.pause()
        isPlaying = false
        timer?.invalidate()
        timer = nil
    }

    private func resume() {
        guard let player else { return }
        configurePlaybackSession()
        player.play()
        isPlaying = true
        startProgressTimer()
    }

    private func loadAndPlay(message: LiveMessage) async {
        stop()
        guard let urlString = message.audioUrl,
              !urlString.hasPrefix("pending://"),
              let url = URL(string: urlString) else { return }

        do {
            let (data, _) = try await URLSession.shared.data(from: url)
            await MainActor.run {
                configurePlaybackSession()
                player = try? AVAudioPlayer(data: data)
                player?.isMeteringEnabled = true
                player?.prepareToPlay()
                player?.play()
                activeMessageId = message.id
                isPlaying = true
                progress = 0
                startProgressTimer()
            }
        } catch {
            await MainActor.run { stop() }
        }
    }

    private func configurePlaybackSession() {
        let session = AVAudioSession.sharedInstance()
        try? session.setCategory(.playback, mode: .default, options: [.duckOthers])
        try? session.setActive(true)
    }

    private func startProgressTimer() {
        timer?.invalidate()
        timer = Timer.scheduledTimer(withTimeInterval: 0.05, repeats: true) { [weak self] _ in
            Task { @MainActor in
                self?.tick()
            }
        }
        if let timer {
            RunLoop.main.add(timer, forMode: .common)
        }
    }

    private func tick() {
        guard let player else {
            stop()
            return
        }
        let total = max(player.duration, 0.1)
        progress = player.currentTime / total
        player.updateMeters()
        let power = player.averagePower(forChannel: 0)
        let level = TeamVoiceRecorderService.normalizedMeterLevel(power)
        var next = levels
        if !next.isEmpty {
            next.removeFirst()
            next.append(level)
        } else {
            next = Array(repeating: level, count: 9)
        }
        levels = next
        meterPhase &+= 1

        if !player.isPlaying, progress > 0.98 {
            stop()
        }
    }
}
