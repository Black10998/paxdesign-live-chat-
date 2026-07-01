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
                                .id("typing-indicator")
                                .transition(.opacity.combined(with: .scale))
                        }
                    }
                    .padding(.horizontal, 16)
                    .padding(.vertical, 12)
                }
                .onChange(of: thread.messages.count) { _ in
                    scrollToBottom(proxy: proxy)
                }
                .onChange(of: thread.userTyping) { _ in
                    scrollToBottom(proxy: proxy)
                }
            }

            if thread.handler == "admin" {
                assistStrip
            }

            composer
        }
        .background(PAXBackground())
        .navigationTitle(thread.customerName)
        .navigationBarTitleDisplayMode(.inline)
        .toolbar { toolbarContent }
        .onAppear { thread.start(auth: auth) }
        .onDisappear {
            AdminTypingSound.shared.stop()
            thread.stop()
        }
        .onReceive(NotificationCenter.default.publisher(for: .paxSessionSync)) { note in
            guard let syncedId = note.userInfo?["session_id"] as? String,
                  syncedId == thread.sessionId else { return }
            Task { await thread.refreshNow(auth: auth) }
        }
        .animation(PAXTheme.spring, value: thread.messages.count)
        .animation(PAXTheme.fade, value: thread.userTyping)
    }

    private func scrollToBottom(proxy: ScrollViewProxy) {
        withAnimation(PAXTheme.spring) {
            if thread.userTyping {
                proxy.scrollTo("typing-indicator", anchor: .bottom)
            } else if let last = thread.messages.last {
                proxy.scrollTo(last.id, anchor: .bottom)
            }
        }
    }

    private var statusBar: some View {
        HStack(spacing: 8) {
            Circle()
                .fill(statusColor)
                .frame(width: 8, height: 8)
            Text(thread.handlerLabel)
                .font(.caption.weight(.semibold))
                .foregroundStyle(PAXTheme.textSecondary)

            if let ratingView = SessionRatingBadge(rating: thread.sessionRating) {
                ratingView
            }

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

    private var assistStrip: some View {
        VStack(alignment: .leading, spacing: 10) {
            if !thread.quickReplies.isEmpty {
                VStack(alignment: .leading, spacing: 6) {
                    Text("Schnellantworten")
                        .font(.caption2.weight(.semibold))
                        .foregroundStyle(PAXTheme.textTertiary)
                    AssistChipRow {
                        ForEach(thread.quickReplies) { item in
                            AssistChip(title: item.label, subtitle: item.text) {
                                thread.applyQuickReply(item.text)
                            }
                        }
                    }
                }
            }

            VStack(alignment: .leading, spacing: 6) {
                HStack(spacing: 6) {
                    Image(systemName: "sparkles")
                        .font(.caption.weight(.semibold))
                        .foregroundStyle(PAXTheme.accent)
                    Text("KI-Vorschläge")
                        .font(.caption2.weight(.semibold))
                        .foregroundStyle(PAXTheme.textTertiary)
                    if thread.suggestionsLoading {
                        ProgressView()
                            .scaleEffect(0.7)
                    }
                    Spacer()
                    Text("Tippen zum Einfügen")
                        .font(.caption2)
                        .foregroundStyle(PAXTheme.textTertiary)
                }

                if let error = thread.suggestionsError, thread.aiSuggestions.isEmpty {
                    Text(error)
                        .font(.caption)
                        .foregroundStyle(PAXTheme.textSecondary)
                } else if !thread.aiSuggestions.isEmpty {
                    AssistChipRow {
                        ForEach(Array(thread.aiSuggestions.enumerated()), id: \.offset) { _, text in
                            AssistChip(title: String(text.prefix(72)) + (text.count > 72 ? "…" : ""), subtitle: text) {
                                thread.applySuggestion(text)
                            }
                        }
                    }
                }
            }
        }
        .padding(.horizontal, 14)
        .padding(.vertical, 10)
        .background(PAXTheme.surface.opacity(0.88))
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
                    thread.handleDraftChange(auth: auth)
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
                HStack(alignment: .bottom, spacing: 6) {
                    Text(message.content)
                        .font(.body)
                        .foregroundStyle(PAXTheme.textPrimary)
                        .padding(.horizontal, 14)
                        .padding(.vertical, 10)
                        .background(
                            RoundedRectangle(cornerRadius: 18, style: .continuous)
                                .fill(bubbleColor)
                        )

                    if let reaction = message.reaction {
                        MessageReactionBadge(reaction: reaction)
                    }
                }

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

struct MessageReactionBadge: View {
    let reaction: String

    var body: some View {
        Image(systemName: reaction == "like" ? "heart.fill" : "hand.thumbsdown.fill")
            .font(.caption)
            .foregroundStyle(reaction == "like" ? .pink : .orange)
            .padding(6)
            .background(
                Circle()
                    .fill(PAXTheme.surfaceElevated)
                    .overlay(Circle().stroke(PAXTheme.border, lineWidth: 1))
            )
            .accessibilityLabel(reaction == "like" ? "Gefällt mir" : "Gefällt mir nicht")
    }
}

struct SessionRatingBadge: View {
    let rating: Int

    init?(rating: Int) {
        guard rating == 5 || rating == 1 else { return nil }
        self.rating = rating
    }

    var body: some View {
        HStack(spacing: 4) {
            Image(systemName: rating == 5 ? "heart.fill" : "hand.thumbsdown.fill")
                .font(.caption2)
            Text(rating == 5 ? "Gefällt mir" : "Gefällt mir nicht")
                .font(.caption2.weight(.medium))
        }
        .foregroundStyle(rating == 5 ? .pink : .orange)
        .padding(.horizontal, 8)
        .padding(.vertical, 4)
        .background(
            Capsule()
                .fill((rating == 5 ? Color.pink : Color.orange).opacity(0.12))
        )
        .accessibilityLabel("Kundenbewertung")
    }
}

private struct AssistChipRow<Content: View>: View {
    @ViewBuilder let content: Content

    var body: some View {
        ScrollView(.horizontal, showsIndicators: false) {
            HStack(spacing: 8) {
                content
            }
        }
    }
}

private struct AssistChip: View {
    let title: String
    let subtitle: String
    let action: () -> Void

    var body: some View {
        Button {
            PAXHaptics.light()
            action()
        } label: {
            Text(title)
                .font(.caption.weight(.medium))
                .foregroundStyle(PAXTheme.textPrimary)
                .lineLimit(2)
                .multilineTextAlignment(.leading)
                .padding(.horizontal, 12)
                .padding(.vertical, 8)
                .background(
                    Capsule()
                        .fill(PAXTheme.surfaceElevated)
                        .overlay(Capsule().stroke(PAXTheme.border, lineWidth: 1))
                )
        }
        .buttonStyle(.plain)
        .accessibilityHint(subtitle)
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
