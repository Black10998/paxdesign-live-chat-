import SwiftUI
#if !SIDELOAD
import PhotosUI
#endif

struct TeamIAChatComposer: View {
    @Binding var draft: String
    @ObservedObject var voiceRecorder: TeamVoiceRecorderService

    var isSending: Bool = false
    var canSendText: Bool
    var onSendText: () -> Void
    var onBeginVoice: () -> Void
    var onFinishVoice: () -> Void
    var onCancelVoice: () -> Void
    var onPickPhoto: () -> Void
    var onPickFile: () -> Void
    var onPickLocation: () -> Void

    @FocusState private var isFocused: Bool

    private var hasText: Bool {
        !draft.trimmingCharacters(in: .whitespacesAndNewlines).isEmpty
    }

    private var showSend: Bool {
        hasText && canSendText && !voiceRecorder.isRecording
    }

    private var showAttachments: Bool {
        !hasText && !isFocused && !voiceRecorder.isRecording
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
            .padding(.trailing, 44)

            if showAttachments == false && !voiceRecorder.isRecording {
                leadingPlusButton
                    .transition(.move(edge: .leading).combined(with: .opacity))
            }

            trailingActionButton
        }
        .frame(maxWidth: .infinity)
        .frame(height: voiceRecorder.isRecording ? 300 : 44)
        .animation(.spring(response: 0.5, dampingFraction: 0.86), value: voiceRecorder.isRecording)
        .animation(.spring(response: 0.5, dampingFraction: 0.86), value: hasText)
        .animation(.spring(response: 0.5, dampingFraction: 0.86), value: isFocused)
    }

    private var attachmentStrip: some View {
        HStack(spacing: 2) {
            attachmentButton(icon: "location.fill", action: onPickLocation)
            attachmentButton(icon: "photo.on.rectangle", action: onPickPhoto)
            attachmentButton(icon: "doc.text", action: onPickFile)
        }
        .foregroundStyle(Color(red: 0.67, green: 0.67, blue: 0.67))
        .padding(.leading, 2)
    }

    private func attachmentButton(icon: String, action: @escaping () -> Void) -> some View {
        Button(action: action) {
            PAXIcon(icon, size: .card)
                .frame(width: 28, height: 28)
                .contentShape(Rectangle())
        }
        .buttonStyle(.plain)
        .disabled(isSending || voiceRecorder.isRecording)
    }

    private var textField: some View {
        TextField(L10n.TeamChatPlaceholder, text: $draft, axis: .vertical)
            .focused($isFocused)
            .lineLimit(1...6)
            .font(.system(size: 14, weight: .medium))
            .foregroundStyle(Color(red: 0.30, green: 0.30, blue: 0.30))
            .padding(.horizontal, 16)
            .padding(.vertical, 10)
            .padding(.trailing, showSend ? 8 : 0)
            .frame(maxWidth: hasText || isFocused ? .infinity : 190, alignment: .leading)
            .background(
                Capsule()
                    .fill(Color(red: 0.91, green: 0.91, blue: 0.91))
            )
            .disabled(voiceRecorder.isRecording || isSending)
    }

    private var leadingPlusButton: some View {
        Button(action: onPickFile) {
            PAXIcon("plus", size: .card)
                .foregroundStyle(Color(red: 0.58, green: 0.58, blue: 0.58))
                .frame(width: 32, height: 32)
                .background(
                    Capsule()
                        .fill(Color(red: 0.91, green: 0.91, blue: 0.91))
                )
        }
        .buttonStyle(.plain)
        .frame(maxWidth: .infinity, alignment: .leading)
        .disabled(isSending)
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
            PAXIcon("waveform", size: .card)
                .foregroundStyle(Color(red: 0.58, green: 0.58, blue: 0.58))
                .frame(width: 36, height: 36)
        }
        .buttonStyle(.plain)
        .disabled(isSending)
        .accessibilityLabel(L10n.TeamRecordVoice)
    }

    private var sendButton: some View {
        Button(action: onSendText) {
            PAXIcon("arrow.up", size: .card, emphasis: .onFill)
                .foregroundStyle(Color(red: 0.91, green: 0.91, blue: 0.91))
                .frame(width: 36, height: 36)
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
                        .shadow(color: .black.opacity(0.12), radius: 4, y: 2)
                )
        }
        .buttonStyle(.plain)
        .disabled(!canSendText || isSending)
        .transition(.scale.combined(with: .opacity))
        .accessibilityLabel(L10n.CommonSend)
    }

    private var voiceRecordingOverlay: some View {
        Button(action: onCancelVoice) {
            ZStack {
                Circle()
                    .fill(Color(red: 0.91, green: 0.91, blue: 0.91))
                    .frame(width: 300, height: 300)
                    .shadow(color: Color(red: 0, green: 0, blue: 0.24).opacity(0.25), radius: 20, y: 10)

                TeamVoiceOrbAnimation()

                VStack(spacing: 18) {
                    Text(L10n.TeamVoiceListening)
                        .font(.system(size: 20, weight: .medium))
                        .foregroundStyle(
                            LinearGradient(
                                colors: [
                                    Color(red: 0.58, green: 0.58, blue: 0.58),
                                    Color(red: 0.91, green: 0.44, blue: 0.80),
                                    Color(red: 1.0, green: 0.81, blue: 0.96),
                                    Color(red: 0.91, green: 0.44, blue: 0.80),
                                    Color(red: 0.58, green: 0.58, blue: 0.58)
                                ],
                                startPoint: .leading,
                                endPoint: .trailing
                            )
                        )

                    Text(L10n.TeamVoiceTapToCancel)
                        .font(.system(size: 12, weight: .regular))
                        .foregroundStyle(Color(red: 0.17, green: 0.17, blue: 0.17))
                }
                .padding(20)
            }
        }
        .buttonStyle(.plain)
        .overlay(alignment: .bottomTrailing) {
            Button(action: onFinishVoice) {
                PAXIcon("arrow.up", size: .card, emphasis: .onFill)
                    .foregroundStyle(.white)
                    .frame(width: 44, height: 44)
                    .background(Circle().fill(PAXTheme.textPrimary))
            }
            .buttonStyle(.plain)
            .padding(12)
            .accessibilityLabel(L10n.CommonSend)
        }
    }
}

private struct TeamVoiceOrbAnimation: View {
    @State private var pulse = false
    @State private var spin = false

    var body: some View {
        ZStack {
            Circle()
                .stroke(Color.white.opacity(0.55), lineWidth: 2)
                .frame(width: 90, height: 90)
                .scaleEffect(pulse ? 1.35 : 1)
                .opacity(pulse ? 0 : 0.9)
                .blur(radius: 2)

            Circle()
                .fill(
                    RadialGradient(
                        colors: [Color(red: 0.79, green: 0.47, blue: 0.93), Color(red: 0.45, green: 0.74, blue: 0.84)],
                        center: .center,
                        startRadius: 4,
                        endRadius: 70
                    )
                )
                .frame(width: 130, height: 130)
                .rotationEffect(.degrees(spin ? 360 : 0))

            Circle()
                .fill(
                    RadialGradient(
                        colors: [Color(red: 0.94, green: 0.47, blue: 0.55), Color(red: 0.91, green: 0.91, blue: 0.98)],
                        center: .center,
                        startRadius: 2,
                        endRadius: 45
                    )
                )
                .frame(width: 80, height: 80)
                .rotationEffect(.degrees(spin ? -270 : 0))

            Circle()
                .fill(Color(red: 0.43, green: 0.40, blue: 0.78))
                .frame(width: 120, height: 120)
                .rotationEffect(.degrees(spin ? 180 : 0))
                .opacity(0.85)

            Circle()
                .fill(.ultraThinMaterial)
                .frame(width: 110, height: 110)
                .overlay(
                    Circle()
                        .fill(
                            RadialGradient(
                                colors: [Color.white.opacity(0.7), .clear],
                                center: UnitPoint(x: 0.7, y: 0.3),
                                startRadius: 0,
                                endRadius: 60
                            )
                        )
                )
        }
        .onAppear {
            withAnimation(.easeInOut(duration: 1.5).repeatForever(autoreverses: false)) {
                pulse = true
            }
            withAnimation(.linear(duration: 5).repeatForever(autoreverses: false)) {
                spin = true
            }
        }
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
