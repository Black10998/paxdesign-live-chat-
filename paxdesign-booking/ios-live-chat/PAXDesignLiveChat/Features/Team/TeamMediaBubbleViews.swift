import AVFoundation
import MapKit
import SwiftUI

struct VoiceMessageBubbleView: View {
    let message: LiveMessage
    let isOutgoing: Bool

    @State private var player: AVAudioPlayer?
    @State private var isPlaying = false
    @State private var progress: Double = 0

    private var durationLabel: String {
        let seconds = message.audioDuration ?? 0
        let total = max(1, Int(seconds.rounded()))
        let minutes = total / 60
        let remainder = total % 60
        return String(format: "%d:%02d", minutes, remainder)
    }

    var body: some View {
        HStack(spacing: 10) {
            Button {
                togglePlayback()
            } label: {
                PAXIcon(isPlaying ? "pause.circle.fill" : "play.circle.fill", size: .card)
            }
            .buttonStyle(.plain)

            VStack(alignment: .leading, spacing: 4) {
                ProgressView(value: progress)
                    .tint(isOutgoing ? PAXBrand.accent : PAXTheme.accent)
                Text(durationLabel)
                    .font(.caption2)
                    .foregroundStyle(PAXTheme.textSecondary)
            }
            .frame(minWidth: 120)
        }
        .onDisappear {
            stopPlayback()
        }
    }

    private func togglePlayback() {
        guard let urlString = message.audioUrl, let url = URL(string: urlString) else { return }
        if isPlaying {
            stopPlayback()
            return
        }
        Task {
            do {
                let (data, _) = try await URLSession.shared.data(from: url)
                await MainActor.run {
                    player = try? AVAudioPlayer(data: data)
                    player?.delegate = PlaybackDelegate.shared
                    PlaybackDelegate.shared.onFinish = {
                        Task { @MainActor in
                            isPlaying = false
                            progress = 0
                        }
                    }
                    player?.play()
                    isPlaying = true
                    progress = 0
                    startProgressTimer()
                }
            } catch {
                await MainActor.run { isPlaying = false }
            }
        }
    }

    private func startProgressTimer() {
        Timer.scheduledTimer(withTimeInterval: 0.1, repeats: true) { timer in
            Task { @MainActor in
                guard let player, isPlaying else {
                    timer.invalidate()
                    return
                }
                let total = max(player.duration, 0.1)
                progress = player.currentTime / total
                if !player.isPlaying {
                    isPlaying = false
                    progress = 0
                    timer.invalidate()
                }
            }
        }
    }

    private func stopPlayback() {
        player?.stop()
        player = nil
        isPlaying = false
        progress = 0
    }
}

private final class PlaybackDelegate: NSObject, AVAudioPlayerDelegate {
    static let shared = PlaybackDelegate()
    var onFinish: (() -> Void)?

    func audioPlayerDidFinishPlaying(_ player: AVAudioPlayer, successfully flag: Bool) {
        onFinish?()
    }
}

struct LocationMessageBubbleView: View {
    let message: LiveMessage

    @State private var region: MKCoordinateRegion

    init(message: LiveMessage) {
        self.message = message
        if let lat = message.locationLat, let lng = message.locationLng {
            let center = CLLocationCoordinate2D(latitude: lat, longitude: lng)
            _region = State(initialValue: MKCoordinateRegion(
                center: center,
                span: MKCoordinateSpan(latitudeDelta: 0.01, longitudeDelta: 0.01)
            ))
        } else {
            _region = State(initialValue: MKCoordinateRegion(
                center: CLLocationCoordinate2D(latitude: 48.2082, longitude: 16.3738),
                span: MKCoordinateSpan(latitudeDelta: 0.05, longitudeDelta: 0.05)
            ))
        }
    }

    private var coordinate: CLLocationCoordinate2D? {
        guard let lat = message.locationLat, let lng = message.locationLng else { return nil }
        return CLLocationCoordinate2D(latitude: lat, longitude: lng)
    }

    var body: some View {
        VStack(alignment: .leading, spacing: 8) {
            if let coordinate {
                Map(coordinateRegion: $region, annotationItems: [LocationPin(coordinate: coordinate)]) { pin in
                    MapMarker(coordinate: pin.coordinate, tint: PAXBrand.accent)
                }
                .frame(height: 140)
                .clipShape(RoundedRectangle(cornerRadius: 12, style: .continuous))
                .allowsHitTesting(false)
            }

            HStack(spacing: 8) {
                PAXIcon("location.fill", size: .inline)
                Text(locationTitle)
                    .font(.subheadline.weight(.medium))
                    .foregroundStyle(PAXTheme.textPrimary)
                    .lineLimit(2)
            }

            if let coordinate, let url = mapsURL(for: coordinate) {
                Link(destination: url) {
                    Label { Text(L10n.TeamOpenInMaps) } icon: { PAXIcon("map") }
                        .font(.caption.weight(.semibold))
                }
            }
        }
    }

    private var locationTitle: String {
        if let label = message.locationLabel, !label.isEmpty { return label }
        if !message.content.isEmpty { return message.content }
        if let lat = message.locationLat, let lng = message.locationLng {
            return String(format: "%.5f, %.5f", lat, lng)
        }
        return L10n.TeamSharedLocation
    }

    private func mapsURL(for coordinate: CLLocationCoordinate2D) -> URL? {
        URL(string: "http://maps.apple.com/?ll=\(coordinate.latitude),\(coordinate.longitude)")
    }
}

private struct LocationPin: Identifiable {
    let id = UUID()
    let coordinate: CLLocationCoordinate2D
}
