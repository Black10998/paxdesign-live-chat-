import AVFoundation
import MapKit
import SwiftUI

struct VoiceMessageBubbleView: View {
    let message: LiveMessage
    let isOutgoing: Bool

    @Environment(\.colorScheme) private var colorScheme
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

    private var cardFill: Color {
        Color(.secondarySystemGroupedBackground)
    }

    private var cardBorder: Color {
        Color(.separator).opacity(colorScheme == .dark ? 0.35 : 0.25)
    }

    private var waveformTint: Color {
        Color.primary.opacity(colorScheme == .dark ? 0.82 : 0.68)
    }

    private var playFill: Color {
        Color(.tertiarySystemFill)
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
                .fill(playFill)
                .frame(width: 36, height: 36)
            PAXIcon(
                isActiveMessage && playback.isPlaying ? "pause.fill" : "play.fill",
                size: .inline,
                emphasis: .primary
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
                        .foregroundStyle(PAXTheme.textSecondary)
                }
            }
            .padding(12)
            .background(
                RoundedRectangle(cornerRadius: 16, style: .continuous)
                    .fill(Color(.secondarySystemGroupedBackground))
                    .overlay(
                        RoundedRectangle(cornerRadius: 16, style: .continuous)
                            .stroke(Color(.separator).opacity(0.35), lineWidth: 0.5)
                    )
            )
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

struct TeamFileBubbleView: View {
    let message: LiveMessage
    let isOutgoing: Bool

    @State private var showPreview = false

    private var isPending: Bool {
        (message.fileUrl ?? "").hasPrefix("pending://")
    }

    private var displayName: String {
        if let name = message.fileName, !name.isEmpty { return name }
        if !message.content.isEmpty { return message.content }
        return L10n.TeamSendFile
    }

    var body: some View {
        Button {
            openFile()
        } label: {
            HStack(spacing: 12) {
                PAXIcon("doc.text", size: .card, emphasis: .secondary)
                    .frame(width: 36, height: 36)
                    .background(
                        RoundedRectangle(cornerRadius: 10, style: .continuous)
                            .fill(Color.primary.opacity(0.05))
                    )
                VStack(alignment: .leading, spacing: 3) {
                    Text(displayName)
                        .font(.subheadline.weight(.medium))
                        .foregroundStyle(PAXTheme.textPrimary)
                        .lineLimit(2)
                    if isPending {
                        Text(L10n.TeamVoiceSending)
                            .font(.caption2)
                            .foregroundStyle(PAXTheme.textTertiary)
                    } else {
                        Text(L10n.TeamOpenFile)
                            .font(.caption2)
                            .foregroundStyle(PAXTheme.textSecondary)
                    }
                }
                Spacer(minLength: 0)
                if !isPending {
                    PAXIcon("arrow.up.right", size: .inline, emphasis: .tertiary)
                }
            }
            .padding(12)
            .background(
                RoundedRectangle(cornerRadius: 14, style: .continuous)
                    .fill(Color(.secondarySystemGroupedBackground))
                    .overlay(
                        RoundedRectangle(cornerRadius: 14, style: .continuous)
                            .stroke(Color(.separator).opacity(0.35), lineWidth: 0.5)
                    )
            )
        }
        .buttonStyle(.plain)
        .disabled(isPending)
        .sheet(isPresented: $showPreview) {
            if let urlString = message.fileUrl,
               !urlString.hasPrefix("pending://"),
               let url = URL(string: urlString) {
                FilePreviewSheet(
                    url: url,
                    fileName: displayName,
                    mimeType: message.fileMime
                )
            }
        }
    }

    private func openFile() {
        guard let urlString = message.fileUrl,
              !urlString.hasPrefix("pending://"),
              URL(string: urlString) != nil else { return }
        showPreview = true
    }
}

struct TeamImageBubbleView: View {
    let message: LiveMessage
    let onImageTap: (URL) -> Void

    private var isPending: Bool {
        (message.imageUrl ?? "").hasPrefix("pending://")
    }

    private var caption: String {
        let text = message.content.trimmingCharacters(in: .whitespacesAndNewlines)
        return text
    }

    var body: some View {
        VStack(alignment: .leading, spacing: 8) {
            if isPending {
                pendingImageCard
            } else if let imageUrl = message.imageUrl,
                      let url = URL(string: imageUrl) {
                CachedChatImage(url: url) {
                    onImageTap(url)
                }
            }

            if !caption.isEmpty {
                Text(caption)
                    .font(.subheadline)
                    .foregroundStyle(PAXTheme.textPrimary)
                    .padding(.horizontal, 4)
            }
        }
        .padding(8)
        .background(
            RoundedRectangle(cornerRadius: 16, style: .continuous)
                .fill(Color(.secondarySystemGroupedBackground))
                .overlay(
                    RoundedRectangle(cornerRadius: 16, style: .continuous)
                        .stroke(Color(.separator).opacity(0.35), lineWidth: 0.5)
                )
        )
    }

    private var pendingImageCard: some View {
        RoundedRectangle(cornerRadius: PAXMessageStyle.imageCornerRadius, style: .continuous)
            .fill(Color(.tertiarySystemFill))
            .frame(width: 180, height: 120)
            .overlay {
                VStack(spacing: 8) {
                    PAXInlineLoader(size: 24)
                    Text(L10n.TeamVoiceSending)
                        .font(.caption2.weight(.medium))
                        .foregroundStyle(PAXTheme.textSecondary)
                }
            }
    }
}
