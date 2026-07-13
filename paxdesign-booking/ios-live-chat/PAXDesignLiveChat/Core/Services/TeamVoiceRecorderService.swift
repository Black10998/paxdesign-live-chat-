import AVFoundation
import Foundation

@MainActor
final class TeamVoiceRecorderService: NSObject, ObservableObject {
    static let shared = TeamVoiceRecorderService()

    @Published private(set) var isRecording = false
    @Published private(set) var elapsed: TimeInterval = 0

    private var recorder: AVAudioRecorder?
    private var timer: Timer?
    private var fileURL: URL?
    private let maxDuration: TimeInterval = 60

    private override init() {
        super.init()
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
        guard recorder.record() else {
            throw NSError(domain: "TeamVoiceRecorder", code: 1, userInfo: [NSLocalizedDescriptionKey: "Recording failed"])
        }

        self.recorder = recorder
        self.fileURL = url
        isRecording = true
        elapsed = 0
        timer?.invalidate()
        timer = Timer.scheduledTimer(withTimeInterval: 0.1, repeats: true) { [weak self] _ in
            Task { @MainActor in
                guard let self, self.isRecording else { return }
                self.elapsed += 0.1
                if self.elapsed >= self.maxDuration {
                    _ = try? self.stopRecording()
                }
            }
        }
    }

    func stopRecording() throws -> (data: Data, duration: TimeInterval, filename: String) {
        guard isRecording, let recorder, let url = fileURL else {
            throw NSError(domain: "TeamVoiceRecorder", code: 2, userInfo: [NSLocalizedDescriptionKey: "No active recording"])
        }

        timer?.invalidate()
        timer = nil
        recorder.stop()
        self.recorder = nil
        isRecording = false

        let duration = elapsed
        elapsed = 0
        defer {
            try? AVAudioSession.sharedInstance().setActive(false, options: .notifyOthersOnDeactivation)
        }

        let data = try Data(contentsOf: url)
        try? FileManager.default.removeItem(at: url)
        fileURL = nil
        return (data, duration, "voice.m4a")
    }

    func cancelRecording() {
        timer?.invalidate()
        timer = nil
        recorder?.stop()
        recorder = nil
        isRecording = false
        elapsed = 0
        if let url = fileURL {
            try? FileManager.default.removeItem(at: url)
        }
        fileURL = nil
        try? AVAudioSession.sharedInstance().setActive(false, options: .notifyOthersOnDeactivation)
    }
}
