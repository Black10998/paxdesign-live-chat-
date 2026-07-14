import SwiftUI
#if !SIDELOAD
import PhotosUI
#endif

struct TeamIAChatComposer: View {
    @Binding var draft: String
    @ObservedObject var voiceRecorder: TeamVoiceRecorderService

    var canSendText: Bool
    var onSendText: () -> Void
    var onBeginVoice: () -> Void
    var onFinishVoice: () -> Void
    var onCancelVoice: () -> Void
    var onPickPhoto: () -> Void
    var onPickFile: () -> Void
    var onPickLocation: () -> Void

    @Environment(\.colorScheme) private var colorScheme
    @FocusState private var isFocused: Bool

    private let voiceOverlaySize: CGFloat = 228
    private let trailingButtonSize: CGFloat = 36

    private var hasText: Bool {
        !draft.trimmingCharacters(in: .whitespacesAndNewlines).isEmpty
    }

    private var showSend: Bool {
        hasText && canSendText && !voiceRecorder.isRecording
    }

    private var showAttachments: Bool {
        !hasText && !isFocused && !voiceRecorder.isRecording
    }

    private var fieldFill: Color {
        Color(.tertiarySystemFill)
    }

    private var fieldText: Color {
        Color(.label)
    }

    private var placeholderColor: Color {
        Color(.placeholderText)
    }

    private var iconMuted: Color {
        Color(.tertiaryLabel)
    }

    private var iconActive: Color {
        Color(.secondaryLabel)
    }

    private var voiceOverlayFill: Color {
        colorScheme == .dark
            ? Color(.secondarySystemFill)
            : Color(.tertiarySystemFill)
    }

    var body: some View {
        ZStack(alignment: .trailing) {
            HStack(alignment: .center, spacing: 0) {
                if showAttachments {
                    attachmentStrip
                        .transition(.opacity.combined(with: .blur))
                }

                if !voiceRecorder.isRecording {
                    textField
                        .padding(.leading, showAttachments ? 4 : 0)
                }

                Spacer(minLength: 0)
            }
            .padding(.trailing, trailingButtonSize + 8)

            if showAttachments == false && !voiceRecorder.isRecording {
                leadingPlusButton
                    .transition(.move(edge: .leading).combined(with: .opacity))
            }

            trailingActionButton
        }
        .frame(maxWidth: .infinity)
        .frame(height: voiceRecorder.isRecording ? voiceOverlaySize : 44)
        .animation(composerSpring, value: voiceRecorder.isRecording)
        .animation(composerSpring, value: hasText)
        .animation(composerSpring, value: isFocused)
    }

    private var composerSpring: Animation {
        .spring(response: 0.5, dampingFraction: 0.86)
    }

    private var attachmentStrip: some View {
        HStack(spacing: 2) {
            attachmentButton { TeamComposerSVGIcons.location(color: iconMuted) } action: onPickLocation
            attachmentButton { TeamComposerSVGIcons.photo(color: iconMuted) } action: onPickPhoto
            attachmentButton { TeamComposerSVGIcons.file(color: iconMuted) } action: onPickFile
        }
        .padding(.leading, 2)
    }

    private func attachmentButton<Icon: View>(
        @ViewBuilder icon: () -> Icon,
        action: @escaping () -> Void
    ) -> some View {
        Button(action: action) {
            icon()
                .frame(width: 24, height: 24)
                .padding(2)
                .frame(width: 28, height: 28)
                .contentShape(Rectangle())
        }
        .buttonStyle(.plain)
        .disabled(voiceRecorder.isRecording)
    }

    private var textField: some View {
        TextField(L10n.TeamChatPlaceholder, text: $draft, axis: .vertical)
            .focused($isFocused)
            .lineLimit(1...6)
            .font(.system(size: 14, weight: .medium))
            .foregroundStyle(fieldText)
            .tint(PAXTheme.accent)
            .padding(.horizontal, 16)
            .padding(.vertical, 10)
            .padding(.trailing, showSend ? 8 : 0)
            .frame(maxWidth: hasText || isFocused ? .infinity : 190, alignment: .leading)
            .background(
                Capsule()
                    .fill(fieldFill)
            )
            .disabled(voiceRecorder.isRecording)
    }

    private var leadingPlusButton: some View {
        Button(action: onPickFile) {
            TeamComposerSVGIcons.plus(color: iconMuted)
                .frame(width: 24, height: 24)
                .frame(width: 32, height: 32)
                .background(
                    Capsule()
                        .fill(fieldFill)
                )
        }
        .buttonStyle(.plain)
        .frame(maxWidth: .infinity, alignment: .leading)
    }

    @ViewBuilder
    private var trailingActionButton: some View {
        if voiceRecorder.isRecording {
            voiceRecordingOverlay
        } else if showSend {
            sendButton
        } else {
            voiceButton
        }
    }

    private var voiceButton: some View {
        Button(action: onBeginVoice) {
            TeamComposerSVGIcons.voiceWaveform(color: iconMuted)
                .frame(width: 24, height: 24)
                .frame(width: trailingButtonSize, height: trailingButtonSize)
        }
        .buttonStyle(.plain)
        .accessibilityLabel(L10n.TeamRecordVoice)
    }

    private var sendButton: some View {
        Button(action: onSendText) {
            TeamComposerSVGIcons.sendArrow(color: Color(.systemBackground))
                .frame(width: 24, height: 24)
                .frame(width: trailingButtonSize, height: trailingButtonSize)
                .background(
                    Circle()
                        .fill(
                            LinearGradient(
                                colors: [
                                    Color(red: 0.57, green: 0.28, blue: 1.0),
                                    Color(red: 1.0, green: 0.25, blue: 0.25)
                                ],
                                startPoint: .bottomLeading,
                                endPoint: .topTrailing
                            )
                        )
                        .shadow(color: .black.opacity(colorScheme == .dark ? 0.35 : 0.12), radius: 4, y: 2)
                )
        }
        .buttonStyle(.plain)
        .disabled(!canSendText)
        .transition(.scale.combined(with: .opacity))
        .accessibilityLabel(L10n.CommonSend)
    }

    private var voiceRecordingOverlay: some View {
        Button(action: onCancelVoice) {
            ZStack {
                Circle()
                    .fill(voiceOverlayFill)
                    .frame(width: voiceOverlaySize, height: voiceOverlaySize)
                    .shadow(
                        color: Color.black.opacity(colorScheme == .dark ? 0.45 : 0.25),
                        radius: 20,
                        y: 10
                    )
                    .overlay(
                        Circle()
                            .strokeBorder(Color.white.opacity(colorScheme == .dark ? 0.08 : 0.18), lineWidth: 0.5)
                    )

                TeamVoiceOrbAnimation(size: voiceOverlaySize * 0.72)

                VStack(spacing: 14) {
                    TeamVoiceGradientTitle(text: L10n.TeamVoiceListening)

                    Text(L10n.TeamVoiceTapToCancel)
                        .font(.system(size: 12, weight: .regular))
                        .foregroundStyle(Color(.secondaryLabel))
                }
                .padding(18)
            }
        }
        .buttonStyle(.plain)
        .overlay(alignment: .bottomTrailing) {
            Button(action: onFinishVoice) {
                TeamComposerSVGIcons.sendArrow(color: Color(.systemBackground))
                    .frame(width: 24, height: 24)
                    .frame(width: 40, height: 40)
                    .background(Circle().fill(Color(.label)))
            }
            .buttonStyle(.plain)
            .padding(10)
            .accessibilityLabel(L10n.CommonSend)
        }
    }
}

private struct TeamVoiceGradientTitle: View {
    let text: String
    @State private var phase: CGFloat = 0

    var body: some View {
        Text(text)
            .font(.system(size: 18, weight: .medium))
            .foregroundStyle(
                LinearGradient(
                    colors: [
                        Color(.tertiaryLabel),
                        Color(red: 0.91, green: 0.44, blue: 0.80),
                        Color(red: 1.0, green: 0.81, blue: 0.96),
                        Color(red: 0.91, green: 0.44, blue: 0.80),
                        Color(.tertiaryLabel)
                    ],
                    startPoint: UnitPoint(x: phase, y: 0.5),
                    endPoint: UnitPoint(x: phase + 0.65, y: 0.5)
                )
            )
            .onAppear {
                withAnimation(.linear(duration: 6).repeatForever(autoreverses: false)) {
                    phase = 1
                }
            }
    }
}

private struct TeamVoiceOrbAnimation: View {
    let size: CGFloat

    @State private var pulse = false
    @State private var spin1 = false
    @State private var spin2 = false
    @State private var spin3 = false
    @State private var spin4 = false
    @State private var breathe = false

    var body: some View {
        ZStack {
            Circle()
                .stroke(Color.white.opacity(0.55), lineWidth: 2)
                .frame(width: size * 0.32, height: size * 0.32)
                .scaleEffect(pulse ? 1.35 : 1)
                .opacity(pulse ? 0 : 0.9)
                .blur(radius: 2)

            Circle()
                .stroke(Color.white.opacity(0.35), lineWidth: 2)
                .frame(width: size * 0.32, height: size * 0.32)
                .scaleEffect(pulse ? 1.55 : 1.1)
                .opacity(pulse ? 0 : 0.55)
                .blur(radius: 3)
                .animation(.easeInOut(duration: 1.5).repeatForever(autoreverses: false).delay(0.4), value: pulse)

            ZStack {
                orbLayer(
                    colors: [Color(red: 0.79, green: 0.47, blue: 0.93), Color(red: 0.45, green: 0.74, blue: 0.84)],
                    diameter: size * 0.66,
                    rotation: spin1 ? 360 : 0,
                    duration: 5.5
                )
                orbLayer(
                    colors: [Color(red: 0.94, green: 0.47, blue: 0.55), Color(red: 0.91, green: 0.91, blue: 0.98)],
                    diameter: size * 0.34,
                    rotation: spin2 ? -360 : 0,
                    duration: 6
                )
                orbLayer(
                    colors: [Color(red: 0.92, green: 0.50, blue: 0.78), .clear],
                    diameter: size * 0.5,
                    rotation: spin3 ? 270 : 0,
                    duration: 4.8,
                    opacity: 0.6
                )
                Circle()
                    .fill(Color(red: 0.43, green: 0.40, blue: 0.78))
                    .frame(width: size * 0.4, height: size * 0.4)
                    .rotationEffect(.degrees(spin4 ? 180 : 0))
                    .opacity(0.85)
            }
            .frame(width: size * 0.78, height: size * 0.78)
            .background(
                Circle()
                    .fill(Color(red: 0.71, green: 0.66, blue: 0.97))
            )
            .clipShape(Circle())
            .scaleEffect(breathe ? 1 : 0.97)
            .overlay(
                Circle()
                    .fill(
                        RadialGradient(
                            colors: [Color.white.opacity(0.55), .clear],
                            center: UnitPoint(x: 0.7, y: 0.3),
                            startRadius: 0,
                            endRadius: size * 0.35
                        )
                    )
                    .blur(radius: 1)
            )
            .overlay(
                Circle()
                    .fill(.ultraThinMaterial)
                    .opacity(0.35)
            )
        }
        .onAppear {
            withAnimation(.easeInOut(duration: 1.5).repeatForever(autoreverses: false)) {
                pulse = true
            }
            withAnimation(.linear(duration: 5.5).repeatForever(autoreverses: false)) {
                spin1 = true
            }
            withAnimation(.linear(duration: 6).repeatForever(autoreverses: false)) {
                spin2 = true
            }
            withAnimation(.linear(duration: 4.8).repeatForever(autoreverses: false)) {
                spin3 = true
            }
            withAnimation(.linear(duration: 5.2).repeatForever(autoreverses: false)) {
                spin4 = true
            }
            withAnimation(.easeInOut(duration: 5).repeatForever(autoreverses: true)) {
                breathe = true
            }
        }
    }

    private func orbLayer(
        colors: [Color],
        diameter: CGFloat,
        rotation: Double,
        duration: Double,
        opacity: Double = 1
    ) -> some View {
        Circle()
            .fill(
                RadialGradient(
                    colors: colors,
                    center: .center,
                    startRadius: 2,
                    endRadius: diameter / 2
                )
            )
            .frame(width: diameter, height: diameter)
            .rotationEffect(.degrees(rotation))
            .opacity(opacity)
    }
}

private extension AnyTransition {
    static var blur: AnyTransition {
        .modifier(
            active: BlurModifier(radius: 5, opacity: 0),
            identity: BlurModifier(radius: 0, opacity: 1)
        )
    }
}

private struct BlurModifier: ViewModifier {
    let radius: CGFloat
    let opacity: Double

    func body(content: Content) -> some View {
        content
            .blur(radius: radius)
            .opacity(opacity)
    }
}
