import PhotosUI
import SwiftUI

struct ChatView: View {
    @EnvironmentObject private var auth: AuthStore
    @EnvironmentObject private var coordinator: ChatCoordinator
    @StateObject private var thread: ChatThreadModel
    @Environment(\.dismiss) private var dismiss
    @StateObject private var settings = AppSettingsStore.shared

    @State private var imageViewer: ImageViewerItem?
    @State private var photoItem: PhotosPickerItem?

    init(sessionId: String) {
        _thread = StateObject(wrappedValue: ChatThreadModel(sessionId: sessionId))
    }

    var body: some View {
        VStack(spacing: 0) {
            if !settings.privacyBannerDismissed {
                PrivacyBannerView {
                    settings.privacyBannerDismissed = true
                }
                .padding(.horizontal, 12)
                .padding(.top, 8)
                .transition(.opacity)
            }

            compactStatusBar

            ScrollViewReader { proxy in
                ScrollView {
                    LazyVStack(alignment: .leading, spacing: PAXMessageStyle.threadSpacing) {
                        ForEach(Array(thread.messages.enumerated()), id: \.element.id) { index, message in
                            let previous = index > 0 ? thread.messages[index - 1] : nil
                            let next = index + 1 < thread.messages.count ? thread.messages[index + 1] : nil

                            if MessageTimeFormatter.shouldShowDayHeader(current: message, previous: previous),
                               let header = MessageTimeFormatter.dayHeader(from: message.ts) {
                                Text(header)
                                    .font(.caption2.weight(.medium))
                                    .foregroundStyle(PAXTheme.textTertiary)
                                    .frame(maxWidth: .infinity)
                                    .padding(.vertical, 8)
                            }

                            MessageBubbleView(
                                message: message,
                                quotedMessage: quotedMessage(for: message),
                                canReply: thread.handler == "admin" && canReply && message.role != "system",
                                showTimestamp: MessageTimeFormatter.shouldShowTimestamp(current: message, next: next),
                                onReply: { thread.setReply(to: message) },
                                onCopy: { copyMessage(message) },
                                onImageTap: { imageViewer = ImageViewerItem(url: $0) }
                            )
                            .id(message.id)
                        }
                        if thread.userTyping {
                            TypingIndicator()
                                .id("typing-indicator")
                        }
                    }
                    .padding(.horizontal, 12)
                    .padding(.vertical, 10)
                }
                .onChange(of: thread.messages.count) { _ in scrollToBottom(proxy: proxy) }
                .onChange(of: thread.userTyping) { _ in scrollToBottom(proxy: proxy) }
            }

            if thread.handler == "admin", canUseAI {
                assistStrip
            }

            if let reply = thread.replyToMessage {
                ReplyBarView(message: reply) {
                    thread.clearReply()
                }
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
        .sheet(item: $imageViewer) { item in
            FullScreenImageView(url: item.url)
        }
        .onChange(of: photoItem) { item in
            Task { await handlePhotoSelection(item) }
        }
    }

    private func quotedMessage(for message: LiveMessage) -> LiveMessage? {
        guard let replyId = message.replyTo else { return nil }
        return thread.messages.first { $0.id == replyId }
    }

    private func copyMessage(_ message: LiveMessage) {
        UIPasteboard.general.string = message.content
        PAXHaptics.success()
    }

    private func scrollToBottom(proxy: ScrollViewProxy) {
        withAnimation(PAXTheme.quickSpring) {
            if thread.userTyping {
                proxy.scrollTo("typing-indicator", anchor: .bottom)
            } else if let last = thread.messages.last {
                proxy.scrollTo(last.id, anchor: .bottom)
            }
        }
    }

    private var compactStatusBar: some View {
        HStack(spacing: 6) {
            Circle().fill(statusColor).frame(width: 7, height: 7)
            Text(thread.handlerLabel)
                .font(.caption2.weight(.medium))
                .foregroundStyle(PAXTheme.textSecondary)
            if let ratingView = SessionRatingBadge(rating: thread.sessionRating), canViewRatings {
                ratingView
            }
            Spacer()
            if thread.handler != "admin" {
                Text("Nur-Lese")
                    .font(.caption2)
                    .foregroundStyle(PAXTheme.textTertiary)
            }
        }
        .padding(.horizontal, 14)
        .padding(.vertical, 8)
        .background(PAXTheme.surface.opacity(0.65))
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
        VStack(alignment: .leading, spacing: 8) {
            if !thread.quickReplies.isEmpty {
                AssistChipRow {
                    ForEach(thread.quickReplies) { item in
                        AssistChip(title: item.label, subtitle: item.text) {
                            thread.applyQuickReply(item.text)
                        }
                    }
                }
            }
            if thread.suggestionsLoading || !thread.aiSuggestions.isEmpty {
                AssistChipRow {
                    if thread.suggestionsLoading {
                        ProgressView().scaleEffect(0.8)
                    }
                    ForEach(Array(thread.aiSuggestions.enumerated()), id: \.offset) { _, text in
                        AssistChip(title: String(text.prefix(48)) + (text.count > 48 ? "…" : ""), subtitle: text) {
                            thread.applySuggestion(text)
                        }
                    }
                }
            }
        }
        .padding(.horizontal, 12)
        .padding(.vertical, 8)
        .background(PAXTheme.surface.opacity(0.88))
    }

    private var canReply: Bool { auth.profile?.perms.replyChats ?? true }
    private var canUseAI: Bool { auth.profile?.perms.useAI ?? true }
    private var canSendImages: Bool { auth.profile?.perms.sendImages ?? true }
    private var canViewRatings: Bool { auth.profile?.perms.viewRatings ?? true }

    private var composer: some View {
        HStack(alignment: .bottom, spacing: 8) {
            if thread.handler == "admin", canSendImages {
                PhotosPicker(selection: $photoItem, matching: .images) {
                    Image(systemName: "photo")
                        .font(.title3)
                        .foregroundStyle(PAXTheme.accent)
                        .frame(width: 32, height: 32)
                }
                .disabled(thread.isSending)
            }

            TextField("Nachricht", text: $thread.draft, axis: .vertical)
                .font(.subheadline)
                .lineLimit(1...4)
                .padding(.horizontal, 12)
                .padding(.vertical, 9)
                .background(
                    RoundedRectangle(cornerRadius: 20, style: .continuous)
                        .fill(PAXTheme.surfaceElevated)
                        .overlay(RoundedRectangle(cornerRadius: 20, style: .continuous).stroke(PAXTheme.border, lineWidth: 1))
                )
                .disabled(thread.handler != "admin" || !canReply)
                .onChange(of: thread.draft) { _ in
                    thread.handleDraftChange(auth: auth)
                }

            Button {
                PAXHaptics.light()
                Task { await thread.send(auth: auth) }
            } label: {
                Image(systemName: "arrow.up.circle.fill")
                    .font(.system(size: 30))
                    .foregroundStyle(canSend ? PAXTheme.accent : PAXTheme.textTertiary)
            }
            .disabled(!canSend)
        }
        .padding(.horizontal, 10)
        .padding(.vertical, 8)
        .background(.ultraThinMaterial)
    }

    private var canSend: Bool {
        thread.handler == "admin"
            && canReply
            && !thread.draft.trimmingCharacters(in: .whitespacesAndNewlines).isEmpty
            && !thread.isSending
    }

    private func handlePhotoSelection(_ item: PhotosPickerItem?) async {
        guard let item,
              let raw = try? await item.loadTransferable(type: Data.self),
              let prepared = ImageUploadPreprocessor.prepareForUpload(raw) else { return }
        await thread.sendImage(auth: auth, imageData: prepared.data, filename: prepared.filename)
        photoItem = nil
    }
}

// Reuse existing small components from prior ChatView
struct MessageReactionBadge: View {
    let reaction: String
    var body: some View {
        Image(systemName: reaction == "like" ? "heart.fill" : "hand.thumbsdown.fill")
            .font(.caption2)
            .foregroundStyle(reaction == "like" ? .pink : .orange)
            .padding(5)
            .background(Circle().fill(PAXTheme.surfaceElevated))
    }
}

struct SessionRatingBadge: View {
    let rating: Int
    init?(rating: Int) {
        guard rating == 5 || rating == 1 else { return nil }
        self.rating = rating
    }
    var body: some View {
        Image(systemName: rating == 5 ? "heart.fill" : "hand.thumbsdown.fill")
            .font(.caption2)
            .foregroundStyle(rating == 5 ? .pink : .orange)
    }
}

private struct AssistChipRow<Content: View>: View {
    @ViewBuilder let content: Content
    var body: some View {
        ScrollView(.horizontal, showsIndicators: false) {
            HStack(spacing: 6) { content }
        }
    }
}

private struct AssistChip: View {
    let title: String
    let subtitle: String
    let action: () -> Void
    var body: some View {
        Button(action: action) {
            Text(title)
                .font(.caption2.weight(.medium))
                .padding(.horizontal, 10)
                .padding(.vertical, 6)
                .background(Capsule().fill(PAXTheme.surfaceElevated))
        }
        .buttonStyle(.plain)
        .accessibilityHint(subtitle)
    }
}

private struct TypingIndicator: View {
    @State private var animate = false
    var body: some View {
        HStack(spacing: 6) {
            HStack(spacing: 4) {
                ForEach(0..<3, id: \.self) { i in
                    Circle().fill(PAXTheme.textSecondary).frame(width: 5, height: 5)
                        .opacity(animate ? 1 : 0.3)
                        .animation(.easeInOut(duration: 0.45).repeatForever().delay(Double(i) * 0.12), value: animate)
                }
            }
            .padding(.horizontal, 12)
            .padding(.vertical, 8)
            .background(Capsule().fill(PAXTheme.userBubble))
            Text("Kunde schreibt …")
                .font(.caption2)
                .foregroundStyle(PAXTheme.textSecondary)
        }
        .onAppear { animate = true }
    }
}

private extension ChatThreadModel {
    var handlerLabel: String {
        switch handler {
        case "live_request": return "Live-Anfrage"
        case "admin": return "Aktiv"
        case "closed": return "Geschlossen"
        default: return "KI aktiv"
        }
    }
}

private struct ImageViewerItem: Identifiable {
    let id = UUID()
    let url: URL
}
