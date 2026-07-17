import SwiftUI
import PhotosUI
import AVFoundation
import CoreLocation

struct CustomerChatBubble: View {
    let message: CustomerChatPoll.ChatMessage

    var body: some View {
        VStack(alignment: message.role == "user" ? .trailing : .leading, spacing: 4) {
            if let name = message.sender_name, !name.isEmpty, message.role != "user" {
                Text(name).font(.caption).foregroundStyle(.secondary)
            }
            messageContent
                .padding(10)
                .background(message.role == "user" ? Color.accentColor.opacity(0.15) : Color(.secondarySystemBackground))
                .clipShape(RoundedRectangle(cornerRadius: 12))
        }
        .frame(maxWidth: .infinity, alignment: message.role == "user" ? .trailing : .leading)
        .accessibilityElement(children: .combine)
        .accessibilityLabel(message.content.isEmpty ? String(localized: "Attachment") : message.content)
    }

    @ViewBuilder
    private var messageContent: some View {
        if let url = message.image_url, let imageURL = URL(string: url) {
            AsyncImage(url: imageURL) { phase in
                switch phase {
                case .success(let image): image.resizable().scaledToFit()
                case .failure: Image(systemName: "photo").foregroundStyle(.secondary)
                default: ProgressView()
                }
            }
            .frame(maxWidth: 220)
            .clipShape(RoundedRectangle(cornerRadius: 12))
        } else if message.attachment_type == "voice", let url = message.audio_url, let audioURL = URL(string: url) {
            CustomerVoicePlaybackView(url: audioURL)
        } else if message.attachment_type == "location",
                  let lat = message.location_lat, let lng = message.location_lng {
            Link(destination: URL(string: "http://maps.apple.com/?ll=\(lat),\(lng)")!) {
                Label(message.location_label ?? String(localized: "Shared location"), systemImage: "mappin.and.ellipse")
            }
        } else if let fileURL = message.file_url, let url = URL(string: fileURL) {
            Link(message.file_name ?? URL(string: fileURL)?.lastPathComponent ?? fileURL, destination: url)
        } else if !message.content.isEmpty {
            Text(message.content)
        }
    }
}

struct CustomerPhotoPicker: UIViewControllerRepresentable {
    var onPick: (Data) -> Void

    func makeUIViewController(context: Context) -> PHPickerViewController {
        var config = PHPickerConfiguration()
        config.filter = .images
        config.selectionLimit = 1
        let picker = PHPickerViewController(configuration: config)
        picker.delegate = context.coordinator
        return picker
    }

    func updateUIViewController(_ uiViewController: PHPickerViewController, context: Context) {}

    func makeCoordinator() -> Coordinator { Coordinator(onPick: onPick) }

    final class Coordinator: NSObject, PHPickerViewControllerDelegate {
        let onPick: (Data) -> Void
        init(onPick: @escaping (Data) -> Void) { self.onPick = onPick }

        func picker(_ picker: PHPickerViewController, didFinishPicking results: [PHPickerResult]) {
            picker.dismiss(animated: true)
            guard let provider = results.first?.itemProvider, provider.canLoadObject(ofClass: UIImage.self) else { return }
            provider.loadObject(ofClass: UIImage.self) { object, _ in
                guard let image = object as? UIImage, let data = image.jpegData(compressionQuality: 0.85) else { return }
                DispatchQueue.main.async { self.onPick(data) }
            }
        }
    }
}

@MainActor
final class CustomerVoiceRecorder: ObservableObject {
    private var recorder: AVAudioRecorder?
    private var startedAt: Date?

    func start() async throws {
        let session = AVAudioSession.sharedInstance()
        try session.setCategory(.playAndRecord, mode: .default, options: [.defaultToSpeaker])
        try session.setActive(true)
        let url = FileManager.default.temporaryDirectory.appendingPathComponent("voice-\(UUID().uuidString).m4a")
        let settings: [String: Any] = [
            AVFormatIDKey: Int(kAudioFormatMPEG4AAC),
            AVSampleRateKey: 44100,
            AVNumberOfChannelsKey: 1,
            AVEncoderAudioQualityKey: AVAudioQuality.high.rawValue,
        ]
        recorder = try AVAudioRecorder(url: url, settings: settings)
        recorder?.record()
        startedAt = Date()
    }

    func stop() -> (data: Data, duration: Double)? {
        guard let recorder else { return nil }
        recorder.stop()
        let duration = Date().timeIntervalSince(startedAt ?? Date())
        defer { self.recorder = nil; startedAt = nil }
        guard let data = try? Data(contentsOf: recorder.url) else { return nil }
        return (data, duration)
    }
}

struct CustomerVoicePlaybackView: View {
    let url: URL
    @State private var player: AVAudioPlayer?

    var body: some View {
        Button {
            if player?.isPlaying == true {
                player?.stop()
            } else {
                player = try? AVAudioPlayer(contentsOf: url)
                player?.play()
            }
        } label: {
            Label(String(localized: "Play voice message"), systemImage: "play.circle.fill")
        }
    }
}

struct CustomerLocationShareSheet: View {
    @Environment(\.dismiss) private var dismiss
    @StateObject private var location = CustomerLocationProvider()
    var onShare: (Double, Double, String) -> Void

    var body: some View {
        NavigationStack {
            Form {
                if let error = location.error {
                    Text(error).foregroundStyle(.red)
                } else if let coord = location.coordinate {
                    Text(String(localized: "Latitude: \(coord.latitude, specifier: "%.5f"), Longitude: \(coord.longitude, specifier: "%.5f")"))
                } else {
                    ProgressView(String(localized: "Requesting location…"))
                }
            }
            .navigationTitle(String(localized: "Share location"))
            .toolbar {
                ToolbarItem(placement: .cancellationAction) {
                    Button(String(localized: "Cancel")) { dismiss() }
                }
                ToolbarItem(placement: .confirmationAction) {
                    Button(String(localized: "Share")) {
                        if let coord = location.coordinate {
                            onShare(coord.latitude, coord.longitude, String(localized: "My location"))
                            dismiss()
                        }
                    }.disabled(location.coordinate == nil)
                }
            }
            .task { await location.request() }
        }
    }
}

@MainActor
final class CustomerLocationProvider: NSObject, ObservableObject, CLLocationManagerDelegate {
    @Published var coordinate: CLLocationCoordinate2D?
    @Published var error: String?
    private let manager = CLLocationManager()

    override init() {
        super.init()
        manager.delegate = self
        manager.desiredAccuracy = kCLLocationAccuracyNearestTenMeters
    }

    func request() async {
        let status = manager.authorizationStatus
        if status == .notDetermined {
            manager.requestWhenInUseAuthorization()
        } else if status == .denied || status == .restricted {
            error = String(localized: "Location permission denied.")
        } else {
            manager.requestLocation()
        }
    }

    nonisolated func locationManager(_ manager: CLLocationManager, didUpdateLocations locations: [CLLocation]) {
        Task { @MainActor in coordinate = locations.last?.coordinate }
    }

    nonisolated func locationManager(_ manager: CLLocationManager, didFailWithError error: Error) {
        Task { @MainActor in self.error = error.localizedDescription }
    }

    nonisolated func locationManagerDidChangeAuthorization(_ manager: CLLocationManager) {
        Task { @MainActor in
            switch manager.authorizationStatus {
            case .authorizedWhenInUse, .authorizedAlways:
                manager.requestLocation()
            case .denied, .restricted:
                error = String(localized: "Location permission denied.")
            default: break
            }
        }
    }
}
