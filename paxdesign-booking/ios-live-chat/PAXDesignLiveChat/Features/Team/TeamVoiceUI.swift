import AVFoundation
import SwiftUI

// MARK: - Waveform

struct TeamVoiceWaveformView: View {
    let levels: [CGFloat]
    var progress: Double = 0
    var barColor: Color = Color.white.opacity(0.88)
    var playedColor: Color?
    var idleLevel: CGFloat = 0.08
    var maxHeight: CGFloat = 40
    var barWidth: CGFloat = 2
    var barSpacing: CGFloat = 2

    private var normalizedLevels: [CGFloat] {
        guard !levels.isEmpty else {
            return Array(repeating: idleLevel, count: 24)
        }
        return levels
    }

    var body: some View {
        GeometryReader { proxy in
            let count = normalizedLevels.count
            let spacing = barSpacing
            let width = barWidth
            let totalWidth = CGFloat(count) * width + CGFloat(max(0, count - 1)) * spacing
            let scale = totalWidth > proxy.size.width ? proxy.size.width / totalWidth : 1
            let clampedProgress = min(1, max(0, progress))

            HStack(alignment: .center, spacing: spacing) {
                ForEach(Array(normalizedLevels.enumerated()), id: \.offset) { index, level in
                    let barProgress = CGFloat(index + 1) / CGFloat(max(count, 1))
                    Capsule()
                        .fill(color(for: barProgress, overall: clampedProgress))
                        .frame(width: width * scale, height: max(2, maxHeight * level))
                }
            }
            .frame(maxWidth: .infinity, maxHeight: .infinity, alignment: .center)
        }
        .frame(height: maxHeight)
    }

    private func color(for barProgress: CGFloat, overall: CGFloat) -> Color {
        if let playedColor, overall > 0, barProgress <= overall {
            return playedColor
        }
        return barColor
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
    var progress: Double = 0
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
        let value: TimeInterval
        switch mode {
        case .recording:
            value = elapsed
        case .playback:
            value = duration * progress
        }
        return Self.formatDuration(value)
    }

    private var totalLabel: String {
        let value = mode == .recording ? elapsed : duration
        return Self.formatDuration(value)
    }

    static func formatDuration(_ value: TimeInterval) -> String {
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
                if mode == .playback {
                    Text("\(timeLabel) / \(totalLabel)")
                        .font(.caption.monospacedDigit())
                        .foregroundStyle(Color.white.opacity(0.55))
                } else {
                    Text(totalLabel)
                        .font(.caption.monospacedDigit())
                        .foregroundStyle(Color.white.opacity(0.55))
                }
            }

            TeamVoiceWaveformView(
                levels: levels,
                progress: mode == .playback ? progress : 0,
                barColor: Color.white.opacity(0.35),
                playedColor: Color.white.opacity(0.9)
            )

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
    @Published private(set) var activeMessageId: Int?

    private var player: AVAudioPlayer?
    private var timer: Timer?
    private var storedLevels: [CGFloat] = []

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
        storedLevels = []
        activeMessageId = nil
        try? AVAudioSession.sharedInstance().setActive(false, options: .notifyOthersOnDeactivation)
    }

    func levels(for message: LiveMessage) -> [CGFloat] {
        if activeMessageId == message.id, !storedLevels.isEmpty {
            return storedLevels
        }
        if let waveform = message.audioWaveform, !waveform.isEmpty {
            return waveform
        }
        let barCount = TeamVoiceWaveformAnalyzer.barCount(for: message.audioDuration ?? 1)
        return Array(repeating: 0.12, count: barCount)
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

        storedLevels = levels(for: message)

        do {
            let (data, _) = try await URLSession.shared.data(from: url)
            await MainActor.run {
                configurePlaybackSession()
                player = try? AVAudioPlayer(data: data)
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
        timer = Timer.scheduledTimer(withTimeInterval: 0.03, repeats: true) { [weak self] _ in
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
        progress = min(1, player.currentTime / total)

        if !player.isPlaying, progress > 0.98 {
            stop()
        }
    }
}
