import SwiftUI

struct ChatView: View {
    @EnvironmentObject private var auth: AuthStore
    @EnvironmentObject private var coordinator: ChatCoordinator
    @StateObject private var thread: ChatThreadModel
    @Environment(\.dismiss) private var dismiss

    init(sessionId: String) {
        _thread = StateObject(wrappedValue: ChatThreadModel(sessionId: sessionId))
    }

    var body: some View {
        VStack(spacing: 0) {
            statusBar

            ScrollViewReader { proxy in
                ScrollView {
                    LazyVStack(alignment: .leading, spacing: 12) {
                        ForEach(thread.messages) { message in
                            MessageBubble(message: message)
                                .id(message.id)
                                .transition(.asymmetric(
                                    insertion: .move(edge: message.role == "admin" ? .trailing : .leading).combined(with: .opacity),
                                    removal: .opacity
                                ))
                        }
                        if thread.userTyping {
                            TypingIndicator()
                                .transition(.opacity.combined(with: .scale))
                        }
                    }
                    .padding(.horizontal, 16)
                    .padding(.vertical, 12)
                }
                .onChange(of: thread.messages.count) { _ in
                    if let last = thread.messages.last {
                        withAnimation(PAXTheme.spring) {
                            proxy.scrollTo(last.id, anchor: .bottom)
                        }
                    }
                }
                .onChange(of: thread.userTyping) { _ in
                    if let last = thread.messages.last {
                        withAnimation(PAXTheme.spring) {
                            proxy.scrollTo(last.id, anchor: .bottom)
                        }
                    }
                }
            }

            composer
        }
        .background(PAXBackground())
        .navigationTitle(thread.customerName)
        .navigationBarTitleDisplayMode(.inline)
        .toolbar { toolbarContent }
        .onAppear { thread.start(auth: auth) }
        .onDisappear { thread.stop() }
        .animation(PAXTheme.spring, value: thread.messages.count)
        .animation(PAXTheme.fade, value: thread.userTyping)
    }

    private var statusBar: some View {
        HStack {
            Circle()
                .fill(statusColor)
                .frame(width: 8, height: 8)
            Text(thread.handlerLabel)
                .font(.caption.weight(.semibold))
                .foregroundStyle(PAXTheme.textSecondary)
            Spacer()
            if thread.handler != "admin" {
                Text("Nur-Lese-Modus")
                    .font(.caption2.weight(.medium))
                    .foregroundStyle(PAXTheme.textTertiary)
            }
        }
        .padding(.horizontal, 16)
        .padding(.vertical, 10)
        .background(PAXTheme.surface.opacity(0.72))
    }

    private var handlerLabel: String {
        switch thread.handler {
        case "live_request": return "Live-Anfrage"
        case "admin": return "Sie chatten aktiv"
        case "closed": return "Geschlossen"
        default: return "KI aktiv"
        }
    }

    private var statusColor: Color {
        switch thread.handler {
        case "live_request": return PAXTheme.accent
        case "admin": return PAXTheme.success
        case "closed": return .gray
        default: return .blue
        }
    }

    @ToolbarContentBuilder
    private var toolbarContent: some ToolbarContent {
        ToolbarItemGroup(placement: .topBarTrailing) {
            if thread.handler == "live_request" {
                Button("Übernehmen") {
                    PAXHaptics.medium()
                    Task {
                        if let session = coordinator.sessions.first(where: { $0.sessionId == thread.sessionId }) {
                            await coordinator.acceptLiveRequest(auth: auth, session: session)
                            await thread.reloadAfterTakeover(auth: auth)
                            PAXHaptics.success()
                        }
                    }
                }
                .font(.subheadline.weight(.semibold))
            }
            if thread.handler == "admin" {
                Button("Freigeben") {
                    PAXHaptics.light()
                    Task { try? await auth.api?.release(thread.sessionId) }
                }
            }
            if thread.handler == "closed" {
                Button("Wiederöffnen") {
                    PAXHaptics.light()
                    Task {
                        try? await auth.api?.reopen(thread.sessionId)
                        await thread.reloadAfterTakeover(auth: auth)
                        await coordinator.refreshSessions(auth: auth)
                    }
                }
            } else {
                Button("Schließen") {
                    PAXHaptics.warning()
                    Task {
                        try? await auth.api?.close(thread.sessionId)
                        await coordinator.refreshSessions(auth: auth)
                        dismiss()
                    }
                }
            }
        }
    }

    private var composer: some View {
        HStack(alignment: .bottom, spacing: 10) {
            TextField("Nachricht schreiben …", text: $thread.draft, axis: .vertical)
                .lineLimit(1...5)
                .padding(.horizontal, 14)
                .padding(.vertical, 12)
                .background(
                    RoundedRectangle(cornerRadius: 22, style: .continuous)
                        .fill(PAXTheme.surfaceElevated)
                        .overlay(
                            RoundedRectangle(cornerRadius: 22, style: .continuous)
                                .stroke(PAXTheme.border, lineWidth: 1)
                        )
                )
                .disabled(thread.handler != "admin")
                .onChange(of: thread.draft) { _ in
                    Task { await thread.notifyTyping(auth: auth) }
                }

            Button {
                PAXHaptics.light()
                Task { await thread.send(auth: auth) }
            } label: {
                Image(systemName: "arrow.up.circle.fill")
                    .font(.system(size: 34))
                    .foregroundStyle(canSend ? PAXTheme.accent : PAXTheme.textTertiary)
            }
            .disabled(!canSend)
        }
        .padding(.horizontal, 14)
        .padding(.vertical, 12)
        .background(.ultraThinMaterial)
    }

    private var canSend: Bool {
        thread.handler == "admin"
            && !thread.draft.trimmingCharacters(in: .whitespacesAndNewlines).isEmpty
            && !thread.isSending
    }
}

private struct MessageBubble: View {
    let message: LiveMessage

    var body: some View {
        HStack(alignment: .bottom, spacing: 8) {
            if isOutgoing { Spacer(minLength: 48) }

            VStack(alignment: isOutgoing ? .trailing : .leading, spacing: 4) {
                Text(message.content)
                    .font(.body)
                    .foregroundStyle(PAXTheme.textPrimary)
                    .padding(.horizontal, 14)
                    .padding(.vertical, 10)
                    .background(
                        RoundedRectangle(cornerRadius: 18, style: .continuous)
                            .fill(bubbleColor)
                    )

                if message.role == "system" {
                    Text("System")
                        .font(.caption2)
                        .foregroundStyle(PAXTheme.textTertiary)
                }
            }

            if !isOutgoing { Spacer(minLength: 48) }
        }
    }

    private var isOutgoing: Bool { message.role == "admin" }

    private var bubbleColor: Color {
        switch message.role {
        case "user": return PAXTheme.userBubble
        case "admin": return PAXTheme.adminBubble
        case "system": return PAXTheme.systemBubble
        default: return PAXTheme.userBubble
        }
    }
}

private struct TypingIndicator: View {
    @State private var animate = false

    var body: some View {
        HStack(spacing: 8) {
            HStack(spacing: 5) {
                ForEach(0..<3, id: \.self) { index in
                    Circle()
                        .fill(PAXTheme.textSecondary)
                        .frame(width: 7, height: 7)
                        .opacity(animate ? 1 : 0.25)
                        .animation(
                            .easeInOut(duration: 0.45)
                                .repeatForever(autoreverses: true)
                                .delay(Double(index) * 0.12),
                            value: animate
                        )
                }
            }
            .padding(.horizontal, 14)
            .padding(.vertical, 10)
            .background(Capsule().fill(PAXTheme.userBubble))

            Text("Kunde schreibt …")
                .font(.caption)
                .foregroundStyle(PAXTheme.textSecondary)
        }
        .onAppear { animate = true }
    }
}

private extension ChatThreadModel {
    var handlerLabel: String {
        switch handler {
        case "live_request": return "Live-Anfrage"
        case "admin": return "Sie chatten aktiv"
        case "closed": return "Geschlossen"
        default: return "KI aktiv"
        }
    }
}
