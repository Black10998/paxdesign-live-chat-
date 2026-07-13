import AVFoundation
import MapKit
import SwiftUI

struct VoiceMessageBubbleView: View {
    let message: LiveMessage
    let isOutgoing: Bool

    @Environment(\.colorScheme) private var colorScheme
    @Environment(\.paxPalette) private var palette
    @ObservedObject private var playback = TeamVoicePlaybackController.shared
    @State private var showPlaybackPanel = false

    private var isPendingUpload: Bool {
        (message.audioUrl ?? "").hasPrefix("pending://")
    }

    private var isActiveMessage: Bool {
        playback.activeMessageId == message.id
    }

    private var durationLabel: String {
        let seconds = message.audioDuration ?? 0
        let total = max(1, Int(seconds.rounded()))
        let minutes = total / 60
        let remainder = total % 60
        return String(format: "%d:%02d", minutes, remainder)
    }

    private var displayLevels: [CGFloat] {
        if isActiveMessage, !playback.levels.isEmpty {
            return playback.levels
        }
        return [0.18, 0.42, 0.28, 0.56, 0.34, 0.48, 0.22, 0.38, 0.3]
    }

    private var accentTint: Color { palette.accent }

    private var cardFill: Color {
        if isOutgoing {
            return accentTint.opacity(colorScheme == .dark ? 0.24 : 0.16)
        }
        return Color(.tertiarySystemFill)
    }

    private var cardBorder: Color {
        accentTint.opacity(isOutgoing ? 0.28 : 0.12)
    }

    private var waveformTint: Color {
        isOutgoing ? accentTint.opacity(0.95) : Color.primary.opacity(colorScheme == .dark ? 0.82 : 0.68)
    }

    var body: some View {
        Group {
            if isPendingUpload {
                pendingCard
            } else {
                playableCard
            }
        }
        .sheet(isPresented: $showPlaybackPanel, onDismiss: {
            playback.stop()
        }) {
            NavigationStack {
                VStack(spacing: 16) {
                    TeamVoiceActivityPanel(
                        mode: .playback,
                        duration: message.audioDuration ?? 0,
                        levels: displayLevels,
                        isPlaying: isActiveMessage && playback.isPlaying,
                        onTogglePlayback: {
                            playback.toggle(message: message)
                        }
                    )
                    Spacer(minLength: 0)
                }
                .padding(.top, 20)
                .frame(maxWidth: .infinity, maxHeight: .infinity)
                .background(Color.black.opacity(0.96))
                .navigationTitle(L10n.TeamVoiceMessage)
                .navigationBarTitleDisplayMode(.inline)
                .toolbar {
                    ToolbarItem(placement: .topBarTrailing) {
                        Button(L10n.CommonDone) {
                            showPlaybackPanel = false
                        }
                    }
                }
            }
            .presentationDetents([.height(220)])
            .presentationDragIndicator(.visible)
        }
        .onDisappear {
            if isActiveMessage {
                playback.stop()
            }
        }
    }

    private var pendingCard: some View {
        voiceCard {
            HStack(spacing: 12) {
                PAXInlineLoader(size: 24)
                VStack(alignment: .leading, spacing: 8) {
                    TeamVoiceWaveformView(
                        levels: displayLevels,
                        barColor: waveformTint,
                        idleLevel: 0.12,
                        maxHeight: 30
                    )
                    Text(L10n.TeamVoiceSending)
                        .font(.caption2.weight(.medium))
                        .foregroundStyle(PAXTheme.textSecondary)
                }
                Spacer(minLength: 0)
                Text(durationLabel)
                    .font(.caption.monospacedDigit().weight(.semibold))
                    .foregroundStyle(PAXTheme.textSecondary)
            }
        }
    }

    private var playableCard: some View {
        Button {
            showPlaybackPanel = true
            playback.toggle(message: message)
        } label: {
            voiceCard {
                HStack(spacing: 12) {
                    playButton

                    TeamVoiceWaveformView(
                        levels: displayLevels,
                        barColor: waveformTint,
                        idleLevel: 0.12,
                        maxHeight: 30
                    )
                    .frame(maxWidth: .infinity)

                    Text(durationLabel)
                        .font(.caption.monospacedDigit().weight(.semibold))
                        .foregroundStyle(PAXTheme.textSecondary)
                        .frame(minWidth: 34, alignment: .trailing)
                }
            }
        }
        .buttonStyle(.plain)
    }

    private var playButton: some View {
        ZStack {
            Circle()
                .fill(isOutgoing ? accentTint : accentTint.opacity(0.18))
                .frame(width: 36, height: 36)
            PAXIcon(
                isActiveMessage && playback.isPlaying ? "pause.fill" : "play.fill",
                size: .inline,
                emphasis: isOutgoing ? .onFill : .primary
            )
        }
        .accessibilityLabel(isActiveMessage && playback.isPlaying ? "Pause" : "Play")
    }

    private func voiceCard<Content: View>(@ViewBuilder content: () -> Content) -> some View {
        content()
            .padding(.horizontal, 12)
            .padding(.vertical, 10)
            .frame(minWidth: 196, maxWidth: 260)
            .background(
                RoundedRectangle(cornerRadius: 18, style: .continuous)
                    .fill(cardFill)
                    .overlay(
                        RoundedRectangle(cornerRadius: 18, style: .continuous)
                            .stroke(cardBorder, lineWidth: 0.5)
                    )
            )
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
        Button {
            openInMaps()
        } label: {
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
                    Spacer(minLength: 0)
                    PAXIcon("arrow.triangle.turn.up.right.circle.fill", size: .inline)
                        .foregroundStyle(PAXBrand.accent)
                }
            }
        }
        .buttonStyle(.plain)
    }

    private var locationTitle: String {
        if let label = message.locationLabel, !label.isEmpty { return label }
        if !message.content.isEmpty { return message.content }
        if let lat = message.locationLat, let lng = message.locationLng {
            return String(format: "%.5f, %.5f", lat, lng)
        }
        return L10n.TeamSharedLocation
    }

    private func openInMaps() {
        guard let coordinate else { return }
        let latitude = coordinate.latitude
        let longitude = coordinate.longitude
        let destination = "\(latitude),\(longitude)"
        let encodedName = locationTitle.addingPercentEncoding(withAllowedCharacters: .urlQueryAllowed) ?? ""
        let candidates = [
            URL(string: "maps://?daddr=\(destination)&dirflg=d"),
            URL(string: "http://maps.apple.com/?daddr=\(destination)&dirflg=d&q=\(encodedName)"),
            URL(string: "maps://?ll=\(destination)&q=\(encodedName)"),
        ]
        for url in candidates.compactMap({ $0 }) {
            if UIApplication.shared.canOpenURL(url) {
                UIApplication.shared.open(url)
                return
            }
        }
    }
}

private struct LocationPin: Identifiable {
    let id = UUID()
    let coordinate: CLLocationCoordinate2D
}
