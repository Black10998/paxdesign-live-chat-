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
    @State private var showCustomerOverview = false
    @State private var pendingImage: UIImage?
    @State private var showCamera = false
    @State private var showImagePreview = false
    @State private var showQuickLinksSheet = false
    @State private var pendingDeleteMessage: LiveMessage?
    @State private var showDeleteConfirm = false

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
                .padding(.top, 4)
                .transition(.opacity)
            }

            if showCustomerOverview {
                customerOverviewPanel
            }

            ChatMessageListView(
                messages: thread.messages,
                messagesRevision: thread.messagesRevision,
                sessionId: thread.sessionId,
                userTyping: thread.userTyping,
                canReply: canReply,
                handler: thread.handler,
                isLoading: thread.isLoadingMessages,
                agentDisplayName: agentDisplayName,
                customerDisplayName: thread.customerName,
                onReply: { thread.setReply(to: $0) },
                onCopy: copyMessage,
                onDelete: { message in
                    pendingDeleteMessage = message
                    showDeleteConfirm = true
                },
                onImageTap: { imageViewer = ImageViewerItem(url: $0) },
                siteBaseURL: auth.profile?.siteUrl ?? auth.siteURLString
            )
            .frame(maxWidth: .infinity, maxHeight: .infinity)
        }
        .paxChatScreenBackground()
        .safeAreaInset(edge: .bottom, spacing: 0) {
            chatInputBar
        }
        .navigationTitle(thread.customerName)
        .navigationBarTitleDisplayMode(.inline)
        .toolbar { toolbarContent }
        .safeAreaInset(edge: .top, spacing: 0) {
            compactStatusBar
        }
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
        .sheet(isPresented: $showQuickLinksSheet) {
            QuickLinksSheet(
                links: thread.quickLinks,
                isSending: thread.isSending,
                onSelect: { link in
                    showQuickLinksSheet = false
                    Task { await thread.sendQuickLink(link, auth: auth) }
                }
            )
        }
        .alert(L10n.ChatDeleteMessage, isPresented: $showDeleteConfirm) {
            Button(L10n.CommonCancel, role: .cancel) {
                pendingDeleteMessage = nil
            }
            Button(L10n.CommonDelete, role: .destructive) {
                guard let message = pendingDeleteMessage else { return }
                pendingDeleteMessage = nil
                Task { await thread.deleteMessage(message.id, auth: auth) }
            }
        } message: {
            Text(L10n.ChatDeleteMessageConfirm)
        }
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
            Circle().fill(statusColor).frame(width: 6, height: 6)
            Text(statusLabel)
                .font(.caption2.weight(.medium))
                .foregroundStyle(PAXTheme.textSecondary)
                .lineLimit(1)
            if let ratingView = SessionRatingBadge(rating: thread.sessionRating), canViewRatings {
                ratingView
            }
            Spacer(minLength: 0)
            if thread.handler != "admin" {
                Text(L10n.ChatReadOnly)
                    .font(.caption2)
                    .foregroundStyle(PAXTheme.textTertiary)
            }
        }
        .padding(.horizontal, 12)
        .padding(.vertical, 4)
        .background(PAXTheme.surface.opacity(0.55))
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
                overviewRow(icon: "sparkles", title: L10n.ChatOverviewTopic, value: thread.detectedService)
            }
            overviewRow(icon: "number", title: L10n.ChatOverviewSession, value: thread.sessionId)
            overviewRow(icon: "bubble.left.and.bubble.right", title: L10n.ChatOverviewMessages, value: "\(thread.messages.count)")
            if !thread.adminName.isEmpty, thread.handler == "admin" {
                overviewRow(icon: "person.badge.shield.checkmark", title: L10n.ChatAgent, value: agentDisplayName)
            }
            if let updated = MessageTimeFormatter.relativeUpdatedLabel(from: thread.updatedAt) {
                overviewRow(icon: "clock", title: L10n.ChatOverviewUpdated, value: updated)
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
            .accessibilityLabel(L10n.ChatOverviewAccessibility)
        }
        ToolbarItemGroup(placement: .topBarTrailing) {
            if thread.handler == "live_request", canReply {
                Button(L10n.CommonTakeover) {
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
                Button(L10n.CommonRelease) {
                    PAXHaptics.light()
                    Task {
                        do {
                            _ = try await auth.api?.release(thread.sessionId)
                            await thread.reloadAfterTakeover(auth: auth)
                            await coordinator.refreshSessions(auth: auth)
                            PAXHaptics.success()
                        } catch {
                            thread.errorMessage = error.localizedDescription
                        }
                    }
                }
            }
            if thread.handler == "closed", canReply {
                Button(L10n.CommonReopen) {
                    PAXHaptics.light()
                    Task {
                        try? await auth.api?.reopen(thread.sessionId)
                        await thread.reloadAfterTakeover(auth: auth)
                        await coordinator.refreshSessions(auth: auth)
                    }
                }
            } else if canReply {
                Button(L10n.CommonClose) {
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
                    ForEach(thread.filteredQuickReplies()) { item in
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

    private var chatInputBar: some View {
        VStack(spacing: 0) {
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
        .background(PAXBackground())
    }

    private var canReply: Bool { auth.canReplyChats }
    private var canUseAI: Bool { auth.canUseAI }
    private var canSendImages: Bool { auth.canSendImages }
    private var canViewRatings: Bool { auth.canViewRatings }

    private var composer: some View {
        HStack(alignment: .bottom, spacing: 8) {
            if thread.handler == "admin", canReply {
                Menu {
                    Button {
                        showQuickLinksSheet = true
                    } label: {
                        Label(L10n.ChatQuickLinksTitle, systemImage: "link.badge.plus")
                    }
                    if canSendImages {
                        #if SIDELOAD
                        Button {
                            showPhotoLibrary = true
                        } label: {
                            Label(L10n.ChatPhotoLibrary, systemImage: "photo.on.rectangle")
                        }
                        #else
                        PhotosPicker(selection: $photoItem, matching: .images) {
                            Label(L10n.ChatPhotoLibrary, systemImage: "photo.on.rectangle")
                        }
                        #endif
                        Button {
                            showCamera = true
                        } label: {
                            Label(L10n.ChatCamera, systemImage: "camera")
                        }
                    }
                } label: {
                    Image(systemName: "plus.circle")
                        .font(.title3)
                        .foregroundStyle(PAXTheme.accent)
                        .frame(width: 32, height: 32)
                }
                .disabled(thread.isSending)
            }

            TextField(L10n.ChatMessagePlaceholder, text: $thread.draft, axis: .vertical)
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
