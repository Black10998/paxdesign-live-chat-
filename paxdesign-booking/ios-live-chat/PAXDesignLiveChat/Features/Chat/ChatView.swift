import SwiftUI
#if !SIDELOAD
import PhotosUI
#endif

struct ChatView: View {
    @EnvironmentObject private var auth: AuthStore
    @EnvironmentObject private var coordinator: ChatCoordinator
    @ObservedObject private var thread: ChatThreadModel
    @Environment(\.dismiss) private var dismiss
    @EnvironmentObject private var settings: AppSettingsStore

    @State private var imageViewer: ImageViewerItem?
    #if SIDELOAD
    @State private var showPhotoLibrary = false
    #else
    @State private var photoItem: PhotosPickerItem?
    #endif
    @State private var showCustomerOverview = true
    @State private var pendingImage: UIImage?
    @State private var showCamera = false
    @State private var showImagePreview = false

    init(sessionId: String) {
        _thread = ObservedObject(wrappedValue: ChatThreadRegistry.shared.bookingThread(sessionId: sessionId))
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

            if let historyError = thread.errorMessage, !historyError.isEmpty {
                historyErrorBanner(historyError)
            }

            if showCustomerOverview {
                customerOverviewPanel
            }

            ChatMessageListView(
                messages: thread.messages,
                userTyping: thread.userTyping,
                canReply: canReply,
                handler: thread.handler,
                isLoading: thread.isLoadingMessages,
                agentDisplayName: agentDisplayName,
                customerDisplayName: thread.customerName,
                onReply: { thread.setReply(to: $0) },
                onCopy: copyMessage,
                onImageTap: { imageViewer = ImageViewerItem(url: $0) }
            )

            if thread.handler == "admin", canUseAI, settings.aiSuggestionsEnabled {
                assistStrip
            }

            if let reply = thread.replyToMessage {
                ReplyBarView(
                    message: reply,
                    agentDisplayName: agentDisplayName,
                    customerDisplayName: thread.customerName
                ) {
                    thread.clearReply()
                }
            }

            composer
        }
        .paxScreenBackground()
        .navigationTitle(thread.customerName)
        .navigationBarTitleDisplayMode(.inline)
        .toolbar { toolbarContent }
        .onAppear {
            let serverSeq = coordinator.serverSeq(for: thread.sessionId)
            thread.start(auth: auth, expectedServerSeq: serverSeq)
            coordinator.activeSessionId = thread.sessionId
            AppRefreshPolicy.update(liveCount: coordinator.liveCount, openChat: true)
            settings.markSessionRead(thread.sessionId, seq: serverSeq)
        }
        .onDisappear {
            AdminTypingSound.shared.stop()
            thread.suspend()
            if coordinator.activeSessionId == thread.sessionId {
                coordinator.activeSessionId = nil
            }
            AppRefreshPolicy.update(liveCount: coordinator.liveCount, openChat: false)
        }
        .onReceive(NotificationCenter.default.publisher(for: .paxSessionSync)) { note in
            guard let syncedId = note.userInfo?["session_id"] as? String,
                  syncedId == thread.sessionId else { return }
            let serverSeq = coordinator.serverSeq(for: syncedId)
            let inlineMessage = note.userInfo?["inline_message"]
            Task { await thread.refreshNow(auth: auth, expectedServerSeq: serverSeq, inlineMessage: inlineMessage) }
        }
        .sheet(item: $imageViewer) { item in
            FullScreenImageView(url: item.url)
        }
        .sheet(isPresented: $showImagePreview) {
            if let pendingImage {
                ImagePreviewSheet(
                    image: pendingImage,
                    caption: $thread.draft,
                    onSend: {
                        showImagePreview = false
                        Task { await sendPendingImage(pendingImage) }
                    },
                    onCancel: {
                        showImagePreview = false
                        self.pendingImage = nil
                    }
                )
            }
        }
        .sheet(isPresented: $showCamera) {
            CameraImagePicker { image in
                pendingImage = image
                showImagePreview = true
            }
        }
        #if SIDELOAD
        .sheet(isPresented: $showPhotoLibrary) {
            LibraryImagePicker { image in
                pendingImage = image
                showImagePreview = true
            }
        }
        #else
        .onChange(of: photoItem) { item in
            Task { await handlePhotoSelection(item) }
        }
        #endif
    }

    private func copyMessage(_ message: LiveMessage) {
        UIPasteboard.general.string = message.content
        PAXHaptics.success()
    }

    private var agentDisplayName: String {
        if let agent = thread.assignedAgent, !agent.name.isEmpty { return agent.name }
        let profileName = auth.profile?.displayName.trimmingCharacters(in: .whitespacesAndNewlines) ?? ""
        if !profileName.isEmpty { return profileName }
        let admin = thread.adminName.trimmingCharacters(in: .whitespacesAndNewlines)
        if !admin.isEmpty { return admin }
        return L10n.ChatAgent
    }

    private var compactStatusBar: some View {
        HStack(spacing: 6) {
            Circle().fill(statusColor).frame(width: 7, height: 7)
            Text(statusLabel)
                .font(.caption2.weight(.medium))
                .foregroundStyle(PAXTheme.textSecondary)
            if let ratingView = SessionRatingBadge(rating: thread.sessionRating), canViewRatings {
                ratingView
            }
            Spacer()
            if thread.handler != "admin" {
                Text(L10n.ChatReadOnly)
                    .font(.caption2)
                    .foregroundStyle(PAXTheme.textTertiary)
            }
        }
        .padding(.horizontal, 14)
        .padding(.vertical, 8)
        .background(
            RoundedRectangle(cornerRadius: 12, style: .continuous)
                .fill(.ultraThinMaterial)
                .overlay(
                    RoundedRectangle(cornerRadius: 12, style: .continuous)
                        .fill(PAXTheme.surface.opacity(0.68))
                )
                .overlay(
                    RoundedRectangle(cornerRadius: 12, style: .continuous)
                        .stroke(PAXTheme.border.opacity(0.4), lineWidth: 1)
                )
        )
    }

    private var customerOverviewPanel: some View {
        VStack(alignment: .leading, spacing: 8) {
            HStack {
                Text(L10n.ChatCustomerOverview)
                    .font(.caption.weight(.semibold))
                    .foregroundStyle(PAXTheme.textSecondary)
                Spacer()
                Button {
                    withAnimation(PAXTheme.quickSpring) { showCustomerOverview = false }
                } label: {
                    Image(systemName: "chevron.up")
                        .font(.caption2.weight(.semibold))
                        .foregroundStyle(PAXTheme.textTertiary)
                }
                .buttonStyle(.plain)
            }

            if !thread.detectedService.isEmpty {
                overviewRow(icon: "sparkles", title: "Thema", value: thread.detectedService)
            }
            overviewRow(icon: "number", title: "Session", value: thread.sessionId)
            overviewRow(icon: "bubble.left.and.bubble.right", title: "Nachrichten", value: "\(thread.messages.count)")
            if !thread.adminName.isEmpty, thread.handler == "admin" {
                overviewRow(icon: "person.badge.shield.checkmark", title: L10n.ChatAgent, value: agentDisplayName)
            }
            if let updated = MessageTimeFormatter.relativeUpdatedLabel(from: thread.updatedAt) {
                overviewRow(icon: "clock", title: "Aktualisiert", value: updated)
            }
        }
        .padding(.horizontal, 14)
        .padding(.vertical, 10)
        .background(
            RoundedRectangle(cornerRadius: 14, style: .continuous)
                .fill(.ultraThinMaterial)
                .overlay(
                    RoundedRectangle(cornerRadius: 14, style: .continuous)
                        .fill(PAXTheme.surface.opacity(0.64))
                )
                .overlay(
                    RoundedRectangle(cornerRadius: 14, style: .continuous)
                        .stroke(PAXTheme.border.opacity(0.4), lineWidth: 1)
                )
        )
        .overlay(alignment: .bottom) {
            Divider().background(PAXTheme.border.opacity(0.5))
        }
    }

    private func overviewRow(icon: String, title: String, value: String) -> some View {
        HStack(spacing: 8) {
            Image(systemName: icon)
                .font(.caption2)
                .foregroundStyle(PAXTheme.accent)
                .frame(width: 16)
            Text(title)
                .font(.caption2)
                .foregroundStyle(PAXTheme.textTertiary)
                .frame(width: 72, alignment: .leading)
            Text(value)
                .font(.caption)
                .foregroundStyle(PAXTheme.textPrimary)
                .lineLimit(2)
            Spacer(minLength: 0)
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
        ToolbarItem(placement: .topBarLeading) {
            Button {
                withAnimation(PAXTheme.quickSpring) {
                    showCustomerOverview.toggle()
                }
            } label: {
                Image(systemName: showCustomerOverview ? "person.crop.circle.fill" : "person.crop.circle")
            }
            .accessibilityLabel("Kundenübersicht")
        }
        ToolbarItemGroup(placement: .topBarTrailing) {
            if thread.handler == "live_request", canReply {
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
            if thread.handler == "admin", canReply {
                Button("Freigeben") {
                    PAXHaptics.light()
                    Task { try? await auth.api?.release(thread.sessionId) }
                }
            }
            if thread.handler == "closed", canReply {
                Button("Wiederöffnen") {
                    PAXHaptics.light()
                    Task {
                        try? await auth.api?.reopen(thread.sessionId)
                        await thread.reloadAfterTakeover(auth: auth)
                        await coordinator.refreshSessions(auth: auth)
                    }
                }
            } else if canReply {
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
                        HStack(spacing: 8) {
                            PAXSkeletonBlock(width: 88, height: 28, cornerRadius: 14)
                            PAXSkeletonBlock(width: 124, height: 28, cornerRadius: 14)
                        }
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
        .paxGlassCardStyle(cornerRadius: 18, fillOpacity: 0.82, borderOpacity: 0.46, shadowOpacity: 0.16)
    }

    private var canReply: Bool { auth.canReplyChats }
    private var canUseAI: Bool { auth.canUseAI }
    private var canSendImages: Bool { auth.canSendImages }
    private var canViewRatings: Bool { auth.canViewRatings }

    private var composer: some View {
        HStack(alignment: .bottom, spacing: 8) {
            if thread.handler == "admin", canSendImages {
                Menu {
                    #if SIDELOAD
                    Button {
                        showPhotoLibrary = true
                    } label: {
                        Label("Fotomediathek", systemImage: "photo.on.rectangle")
                    }
                    #else
                    PhotosPicker(selection: $photoItem, matching: .images) {
                        Label("Fotomediathek", systemImage: "photo.on.rectangle")
                    }
                    #endif
                    Button {
                        showCamera = true
                    } label: {
                        Label("Kamera", systemImage: "camera")
                    }
                } label: {
                    Image(systemName: "plus.circle")
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
                .paxGlassCardStyle(cornerRadius: 20, fillOpacity: 0.78, borderOpacity: 0.42, shadowOpacity: 0.08)
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
        .paxGlassCardStyle(cornerRadius: 18, fillOpacity: 0.8, borderOpacity: 0.4, shadowOpacity: 0.14)
    }

    private var canSend: Bool {
        thread.handler == "admin"
            && canReply
            && !thread.draft.trimmingCharacters(in: .whitespacesAndNewlines).isEmpty
            && !thread.isSending
    }

    #if !SIDELOAD
    private func handlePhotoSelection(_ item: PhotosPickerItem?) async {
        guard let item,
              let raw = try? await item.loadTransferable(type: Data.self),
              let image = UIImage(data: raw) else { return }
        await MainActor.run {
            pendingImage = image
            showImagePreview = true
            photoItem = nil
        }
    }
    #endif

    private func sendPendingImage(_ image: UIImage) async {
        guard let prepared = ImageUploadPreprocessor.prepareForUpload(image) else {
            pendingImage = nil
            return
        }
        await thread.sendImage(auth: auth, imageData: prepared.data, filename: prepared.filename)
        pendingImage = nil
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
            .background(
                Circle()
                    .fill(.ultraThinMaterial)
                    .overlay(Circle().fill(PAXTheme.surface.opacity(0.75)))
                    .overlay(Circle().stroke(PAXTheme.border.opacity(0.42), lineWidth: 1))
            )
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
                .background(
                    Capsule()
                        .fill(.ultraThinMaterial)
                        .overlay(Capsule().fill(PAXTheme.surface.opacity(0.74)))
                        .overlay(Capsule().stroke(PAXTheme.border.opacity(0.42), lineWidth: 1))
                )
        }
        .buttonStyle(.plain)
        .accessibilityHint(subtitle)
    }
}

private extension ChatView {
    func historyErrorBanner(_ message: String) -> some View {
        HStack(spacing: 8) {
            Image(systemName: "exclamationmark.triangle.fill")
                .foregroundStyle(.orange)
            Text(message)
                .font(.caption)
                .foregroundStyle(PAXTheme.textSecondary)
                .multilineTextAlignment(.leading)
            Spacer(minLength: 0)
        }
        .padding(.horizontal, 14)
        .padding(.vertical, 8)
        .background(
            RoundedRectangle(cornerRadius: 10, style: .continuous)
                .fill(Color.orange.opacity(0.12))
        )
        .padding(.horizontal, 12)
        .padding(.top, 6)
    }

    var statusLabel: String {
        if thread.handler == "admin" {
            return agentDisplayName
        }
        return SessionHandlerLocalization.label(handler: thread.handler)
    }
}

private extension ChatThreadModel {
    var handlerLabel: String {
        SessionHandlerLocalization.label(handler: handler)
    }
}

private struct ImageViewerItem: Identifiable {
    let id = UUID()
    let url: URL
}
