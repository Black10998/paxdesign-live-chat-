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
    @State private var isClosingChat = false

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
                layoutRevision: chatLayoutRevision,
                onReply: { thread.setReply(to: $0) },
                onCopy: copyMessage,
                onDelete: { message in
                    pendingDeleteMessage = message
                    showDeleteConfirm = true
                },
                onAnalyze: canUseAI && settings.aiSuggestionsEnabled && thread.handler == "admin"
                    ? { thread.fetchSuggestions(messageId: $0.id) }
                    : nil,
                onLinkReview: { message, action in
                    Task { await thread.submitLinkScanReview(messageId: message.id, action: action, auth: auth) }
                },
                linkReviewSubmittingIds: thread.linkReviewSubmittingIds,
                onImageTap: { imageViewer = ImageViewerItem(url: $0) },
                siteBaseURL: auth.profile?.siteUrl ?? auth.siteURLString,
                deletingMessageIds: thread.deletingMessageIds
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
            AppRefreshPolicy.setActiveSession(thread.sessionId)
            AppRefreshPolicy.update(liveCount: coordinator.liveCount, openChat: true)
            settings.markSessionRead(thread.sessionId, seq: serverSeq)
        }
        .onDisappear {
            AdminTypingSound.shared.stop()
            thread.suspend()
            if coordinator.activeSessionId == thread.sessionId {
                coordinator.activeSessionId = nil
                AppRefreshPolicy.setActiveSession(nil)
            }
            AppRefreshPolicy.update(liveCount: coordinator.liveCount, openChat: false)
        }
        .onReceive(NotificationCenter.default.publisher(for: .paxSessionSync)) { note in
            guard !isClosingChat else { return }
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
        .onChange(of: chatLayoutRevision) { _ in
            ChatScrollHelper.scrollToBottom(sessionId: thread.sessionId)
        }
    }

    private func copyMessage(_ message: LiveMessage) {
        UIPasteboard.general.string = message.content
        PAXHaptics.success()
    }

    /// Dismisses immediately so the staff UI never blocks on close/refresh network calls.
    private func closeChatSession() {
        guard !isClosingChat else { return }
        isClosingChat = true
        PAXHaptics.warning()

        let sessionId = thread.sessionId
        AdminTypingSound.shared.stop()
        thread.suspend()
        if coordinator.activeSessionId == sessionId {
            coordinator.activeSessionId = nil
            AppRefreshPolicy.setActiveSession(nil)
        }
        AppRefreshPolicy.update(liveCount: coordinator.liveCount, openChat: false)
        dismiss()

        Task {
            do {
                try await auth.api?.close(sessionId)
            } catch {
                // Session list refresh below still reconciles server state.
            }
            await coordinator.refreshSessions(auth: auth)
        }
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
                    PAXIcon("chevron.up", size: .inline, emphasis: .tertiary)
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
            PAXIcon(icon, size: .inline)
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
                PAXIcon(showCustomerOverview ? "person.crop.circle.fill" : "person.crop.circle")
            }
            .accessibilityLabel(L10n.ChatOverviewAccessibility)
        }
        ToolbarItemGroup(placement: .topBarTrailing) {
            if thread.handler == "admin", canUseAI, settings.aiSuggestionsEnabled {
                Button {
                    PAXHaptics.light()
                    thread.maybeFetchSuggestionsForLatestUserMessage()
                } label: {
                    PAXIcon("sparkles")
                }
                .disabled(thread.suggestionsLoading)
                .accessibilityLabel(L10n.ChatAnalyzeLatestMessage)
            }
            if (thread.handler == "live_request" || thread.handler == "ai"), canTakeOverChats {
                Button(L10n.CommonTakeover) {
                    PAXHaptics.medium()
                    Task {
                        if thread.handler == "live_request",
                           let session = coordinator.sessions.first(where: { $0.sessionId == thread.sessionId }) {
                            let response = try await auth.api?.takeover(thread.sessionId)
                            guard !isClosingChat else { return }
                            coordinator.acknowledgeIncomingRequest(session.sessionId)
                            if let response {
                                coordinator.applyHandlerTransition(response, sessionId: thread.sessionId, auth: auth)
                            }
                            await thread.reloadAfterTakeover(auth: auth)
                            PAXHaptics.success()
                        } else {
                            do {
                                let response = try await auth.api?.takeover(thread.sessionId)
                                guard !isClosingChat else { return }
                                if let response {
                                    coordinator.applyHandlerTransition(response, sessionId: thread.sessionId, auth: auth)
                                }
                                await thread.reloadAfterTakeover(auth: auth)
                                Task { await coordinator.refreshSessions(auth: auth) }
                                PAXHaptics.success()
                            } catch {
                                guard !isClosingChat else { return }
                                thread.errorMessage = error.localizedDescription
                            }
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
                            let response = try await auth.api?.release(thread.sessionId)
                            guard !isClosingChat else { return }
                            if let response {
                                coordinator.applyHandlerTransition(response, sessionId: thread.sessionId, auth: auth)
                            }
                            Task { await coordinator.refreshSessions(auth: auth) }
                            PAXHaptics.success()
                        } catch {
                            guard !isClosingChat else { return }
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
                    closeChatSession()
                }
                .disabled(isClosingChat)
            }
        }
    }

    private var showQuickReplyStrip: Bool {
        thread.handler == "admin" && canReply && !thread.quickReplies.isEmpty
    }

    private var showAISuggestionsStrip: Bool {
        guard thread.handler == "admin", canUseAI, settings.aiSuggestionsEnabled else { return false }
        return thread.suggestionsLoading
            || !thread.aiSuggestions.isEmpty
            || (thread.suggestionsError != nil && !thread.suggestionsError!.isEmpty)
    }

    private var showAssistStrip: Bool {
        showQuickReplyStrip || showAISuggestionsStrip
    }

    private var chatLayoutRevision: Int {
        var hasher = Hasher()
        hasher.combine(showAssistStrip)
        hasher.combine(thread.replyToMessage?.id ?? -1)
        hasher.combine(thread.suggestionsLoading)
        hasher.combine(thread.aiSuggestions.count)
        hasher.combine(thread.suggestionsError ?? "")
        hasher.combine(thread.filteredQuickReplies().count)
        hasher.combine(showCustomerOverview)
        return hasher.finalize()
    }

    private var assistStrip: some View {
        VStack(alignment: .leading, spacing: 10) {
            if showQuickReplyStrip {
                VStack(alignment: .leading, spacing: 6) {
                    Text(L10n.ChatQuickRepliesTitle)
                        .font(.caption2.weight(.semibold))
                        .foregroundStyle(PAXTheme.textTertiary)
                    AssistChipRow {
                        ForEach(thread.filteredQuickReplies()) { item in
                            AssistChip(title: item.label, subtitle: item.text) {
                                thread.applyQuickReply(item.text)
                            }
                        }
                    }
                }
            }

            if showAISuggestionsStrip {
                VStack(alignment: .leading, spacing: 6) {
                    HStack(spacing: 6) {
                        PAXIcon("sparkles", size: .inline)
                        Text(L10n.ChatAISuggestionsTitle)
                            .font(.caption2.weight(.semibold))
                            .foregroundStyle(PAXTheme.textTertiary)
                        if thread.suggestionsLoading {
                            ProgressView()
                                .scaleEffect(0.7)
                        }
                        Spacer()
                        if !thread.aiSuggestions.isEmpty {
                            Text(L10n.ChatAITapToInsert)
                                .font(.caption2)
                                .foregroundStyle(PAXTheme.textTertiary)
                        }
                    }

                    if let error = thread.suggestionsError, thread.aiSuggestions.isEmpty, !thread.suggestionsLoading {
                        Text(error)
                            .font(.caption)
                            .foregroundStyle(PAXTheme.textSecondary)
                    } else if thread.suggestionsLoading || !thread.aiSuggestions.isEmpty {
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
            }
        }
        .padding(.horizontal, 14)
        .padding(.vertical, 10)
        .paxGlassCardStyle(cornerRadius: 18, fillOpacity: 0.82, borderOpacity: 0.46, shadowOpacity: 0.16)
    }

    private var chatInputBar: some View {
        PAXRevolutComposerBar {
            VStack(spacing: 0) {
                if showAssistStrip {
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
        }
    }

    private var canReply: Bool { auth.canReplyChats }
    private var canTakeOverChats: Bool { auth.canTakeOverChats }
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
                        Label { Text(L10n.ChatQuickLinksTitle) } icon: { PAXIcon("link.badge.plus") }
                    }
                    if canSendImages {
                        #if SIDELOAD
                        Button {
                            showPhotoLibrary = true
                        } label: {
                            Label { Text(L10n.ChatPhotoLibrary) } icon: { PAXIcon("photo.on.rectangle") }
                        }
                        #else
                        PhotosPicker(selection: $photoItem, matching: .images) {
                            Label { Text(L10n.ChatPhotoLibrary) } icon: { PAXIcon("photo.on.rectangle") }
                        }
                        #endif
                        Button {
                            showCamera = true
                        } label: {
                            Label { Text(L10n.ChatCamera) } icon: { PAXIcon("camera") }
                        }
                    }
                } label: {
                    PAXIcon("plus.circle", size: .card)
                        .frame(width: 32, height: 32)
                }
                .disabled(thread.isSending)
            }

            TextField(L10n.ChatMessagePlaceholder, text: $thread.draft, axis: .vertical)
                .font(PAXTypography.body)
                .lineLimit(1...6)
                .layoutPriority(0)
                .padding(.horizontal, PAXSpacing.sm + 2)
                .padding(.vertical, PAXSpacing.sm)
                .frame(minHeight: 44)
                .paxRevolutSurface(cornerRadius: 22, elevation: 0)
                .disabled(!canComposeInThread || !canReply)
                .onChange(of: thread.draft) { _ in
                    thread.handleDraftChange(auth: auth)
                }

            PAXSendButton(isEnabled: canSend) {
                PAXHaptics.light()
                Task {
                    await thread.send(auth: auth)
                    ChatScrollHelper.scrollToBottom(sessionId: thread.sessionId)
                }
            }
        }
        .padding(.horizontal, 10)
        .padding(.vertical, 8)
        .paxGlassCardStyle(cornerRadius: 18, fillOpacity: 0.8, borderOpacity: 0.4, shadowOpacity: 0.14)
    }

    private var canComposeInThread: Bool {
        thread.handler == "admin"
            || (canTakeOverChats && (thread.handler == "ai" || thread.handler == "live_request"))
    }

    private var canSend: Bool {
        canComposeInThread
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
        PAXIcon(reaction == "like" ? "heart.fill" : "hand.thumbsdown.fill", size: .inline)
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
        PAXIcon(rating == 5 ? "heart.fill" : "hand.thumbsdown.fill", size: .inline)
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
