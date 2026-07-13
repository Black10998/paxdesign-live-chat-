import AVFoundation
import Foundation

@MainActor
final class TeamVoiceRecorderService: NSObject, ObservableObject {
    static let shared = TeamVoiceRecorderService()

    @Published private(set) var isRecording = false
    @Published private(set) var elapsed: TimeInterval = 0
    @Published private(set) var audioLevels: [CGFloat] = Array(repeating: 0.08, count: 9)

    private var recorder: AVAudioRecorder?
    private var timer: Timer?
    private var fileURL: URL?
    private let maxDuration: TimeInterval = 60

    private override init() {
        super.init()
    }

    static func normalizedMeterLevel(_ power: Float) -> CGFloat {
        let clamped = max(-60, min(0, power))
        let normalized = (clamped + 60) / 60
        return CGFloat(max(0.08, min(1, normalized)))
    }

    static func teamConversationId(currentUserId: Int, otherUserId: Int) -> String {
        let a = min(currentUserId, otherUserId)
        let b = max(currentUserId, otherUserId)
        return "team_\(a)_\(b)"
    }

    func requestPermission() async -> Bool {
        await withCheckedContinuation { continuation in
            AVAudioSession.sharedInstance().requestRecordPermission { granted in
                continuation.resume(returning: granted)
            }
        }
    }

    func startRecording() throws {
        guard !isRecording else { return }

        let session = AVAudioSession.sharedInstance()
        try session.setCategory(.playAndRecord, mode: .spokenAudio, options: [.defaultToSpeaker, .allowBluetooth])
        try session.setActive(true)

        let url = FileManager.default.temporaryDirectory
            .appendingPathComponent("team-voice-\(UUID().uuidString.lowercased()).m4a")
        let settings: [String: Any] = [
            AVFormatIDKey: Int(kAudioFormatMPEG4AAC),
            AVSampleRateKey: 44_100,
            AVNumberOfChannelsKey: 1,
            AVEncoderAudioQualityKey: AVAudioQuality.high.rawValue,
        ]

        let recorder = try AVAudioRecorder(url: url, settings: settings)
        recorder.isMeteringEnabled = true
        recorder.delegate = self
        guard recorder.record() else {
            throw NSError(domain: "TeamVoiceRecorder", code: 1, userInfo: [NSLocalizedDescriptionKey: "Recording failed"])
        }

        self.recorder = recorder
        self.fileURL = url
        isRecording = true
        elapsed = 0
        audioLevels = Array(repeating: 0.08, count: 9)
        startTimers()
    }

    func stopRecording() throws -> (data: Data, duration: TimeInterval, filename: String) {
        guard isRecording, let recorder, let url = fileURL else {
            throw NSError(domain: "TeamVoiceRecorder", code: 2, userInfo: [NSLocalizedDescriptionKey: "No active recording"])
        }

        stopTimers()
        recorder.stop()
        self.recorder = nil
        isRecording = false

        let duration = max(0.3, elapsed)
        elapsed = 0
        audioLevels = Array(repeating: 0.08, count: 9)
        defer {
            try? AVAudioSession.sharedInstance().setActive(false, options: .notifyOthersOnDeactivation)
        }

        let data = try Data(contentsOf: url)
        try? FileManager.default.removeItem(at: url)
        fileURL = nil
        return (data, duration, "voice.m4a")
    }

    func cancelRecording() {
        stopTimers()
        recorder?.stop()
        recorder = nil
        isRecording = false
        elapsed = 0
        audioLevels = Array(repeating: 0.08, count: 9)
        if let url = fileURL {
            try? FileManager.default.removeItem(at: url)
        }
        fileURL = nil
        try? AVAudioSession.sharedInstance().setActive(false, options: .notifyOthersOnDeactivation)
    }

    private func startTimers() {
        timer?.invalidate()
        let timer = Timer(timeInterval: 0.05, repeats: true) { [weak self] _ in
            Task { @MainActor in
                self?.tick()
            }
        }
        RunLoop.main.add(timer, forMode: .common)
        self.timer = timer
    }

    private func stopTimers() {
        timer?.invalidate()
        timer = nil
    }

    private func tick() {
        guard isRecording, let recorder else { return }
        elapsed += 0.05
        recorder.updateMeters()
        let level = Self.normalizedMeterLevel(recorder.averagePower(forChannel: 0))
        var next = audioLevels
        if !next.isEmpty {
            next.removeFirst()
            next.append(level)
        } else {
            next = Array(repeating: level, count: 9)
        }
        audioLevels = next

        if elapsed >= maxDuration {
            _ = try? stopRecording()
        }
    }
}

extension TeamVoiceRecorderService: AVAudioRecorderDelegate {
    nonisolated func audioRecorderEncodeErrorDidOccur(_ recorder: AVAudioRecorder, error: Error?) {
        Task { @MainActor in
            self.cancelRecording()
        }
    }
}
