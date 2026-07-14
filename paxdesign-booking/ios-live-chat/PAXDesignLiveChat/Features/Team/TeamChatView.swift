import SwiftUI
import UniformTypeIdentifiers
#if !SIDELOAD
import PhotosUI
#endif

struct TeamChatView: View {
    @EnvironmentObject private var auth: AuthStore
    @EnvironmentObject private var coordinator: ChatCoordinator
    @EnvironmentObject private var teamCoordinator: TeamMessagingCoordinator
    @EnvironmentObject private var settings: AppSettingsStore
    @Environment(\.colorScheme) private var colorScheme
    @Environment(\.dismiss) private var dismiss
    @ObservedObject private var thread: TeamChatThreadModel
    @ObservedObject private var voiceRecorder = TeamVoiceRecorderService.shared

    @State private var showDeleteConfirm = false
    @State private var deleteMode = "hide"
    @State private var isDeleting = false
    @State private var deleteFeedback: String?
    @State private var imageViewer: TeamImageViewerItem?
    #if !SIDELOAD
    @State private var photoItem: PhotosPickerItem?
    #endif
    @State private var pendingImage: UIImage?
    @State private var showImagePreview = false
    @State private var mediaError: String?
    @State private var sendError: String?
    @State private var showFileImporter = false
    @State private var showLocationPicker = false
    @State private var pendingDeleteMessage: LiveMessage?
    @State private var showMessageDeleteConfirm = false
    #if !SIDELOAD
    @State private var showPhotoPicker = false
    #endif

    init(sessionId: String) {
        _thread = ObservedObject(wrappedValue: ChatThreadRegistry.shared.teamThread(sessionId: sessionId))
    }

    private var canPurgeForAll: Bool {
        auth.canManageUsers || auth.profile?.isSuperAdmin == true
    }

    private var canDeleteTeamMessages: Bool {
        auth.profile?.isSuperAdmin == true
    }

    var body: some View {
        ChatMessageListView(
            messages: thread.messages,
            messagesRevision: thread.messagesRevision,
            sessionId: thread.sessionId,
            userTyping: thread.remoteTyping,
            canReply: false,
            handler: "team",
            isLoading: thread.isLoadingMessages,
            agentDisplayName: auth.profile?.displayName ?? L10n.ChatAgent,
            customerDisplayName: thread.participantName.isEmpty ? L10n.TeamChatTitle : thread.participantName,
            layoutRevision: teamLayoutRevision,
            onReply: { _ in },
            onCopy: { UIPasteboard.general.string = $0.content },
            onDelete: { message in
                pendingDeleteMessage = message
                showMessageDeleteConfirm = true
            },
            onImageTap: { imageViewer = TeamImageViewerItem(url: $0) },
            teamOtherReadSeq: thread.otherReadSeq,
            teamFailedClientMsgIds: thread.failedClientMsgIds,
            onRetryTeamMessage: { clientId in
                Task { await thread.retryFailedMessage(clientId, auth: auth, teamCoordinator: teamCoordinator) }
            },
            canDeleteTeamMessages: canDeleteTeamMessages,
            deletingMessageIds: thread.deletingMessageIds
        )
        .frame(maxWidth: .infinity, maxHeight: .infinity)
        .paxChatScreenBackground()
        .safeAreaInset(edge: .top, spacing: 0) {
            if showsTeamStatusBanner {
                teamStatusBanner
            }
        }
        .safeAreaInset(edge: .bottom, spacing: 0) {
            if thread.canSend {
                teamComposer
                    .background(PAXBackground())
            } else {
                lockedComposer
                    .background(PAXBackground())
            }
        }
        .navigationBarTitleDisplayMode(.inline)
        .toolbarBackground(.visible, for: .navigationBar)
        .toolbar {
            ToolbarItem(placement: .principal) {
                VStack(spacing: 2) {
                    HStack(spacing: 5) {
                        Text(thread.participantName.isEmpty ? L10n.TeamChatTitle : thread.participantName)
                            .font(.headline)
                            .lineLimit(1)
                        if thread.requestStatus == "accepted" {
                            TeamVerifiedBadge(size: 14)
                        }
                    }
                    Text(presenceLabel)
                        .font(.caption2)
                        .foregroundStyle(PAXTheme.textSecondary)
                }
            }
            ToolbarItem(placement: .topBarTrailing) {
                Menu {
                    if thread.canRespond {
                        Button {
                            Task { await thread.respondToRequest(accept: true, auth: auth, teamCoordinator: teamCoordinator) }
                        } label: {
                            Label { Text(L10n.TeamContextAcceptRequest) } icon: { PAXIcon("checkmark.circle") }
                        }
                        Button(role: .destructive) {
                            Task { await thread.respondToRequest(accept: false, auth: auth, teamCoordinator: teamCoordinator) }
                        } label: {
                            Label { Text(L10n.TeamContextDeclineRequest) } icon: { PAXIcon("xmark.circle") }
                        }
                    }
                    Button {
                        Task { await teamCoordinator.pinConversation(sessionId: thread.sessionId, pinned: true, auth: auth) }
                    } label: {
                        Label { Text(L10n.TeamContextPinConversation) } icon: { PAXIcon("pin") }
                    }
                    Button(role: .destructive) {
                        deleteMode = "hide"
                        showDeleteConfirm = true
                    } label: {
                        Label { Text(L10n.TeamContextRemoveFromList) } icon: { PAXIcon("eye.slash") }
                    }
                    if canPurgeForAll {
                        Button(role: .destructive) {
                            deleteMode = "purge_all"
                            showDeleteConfirm = true
                        } label: {
                            Label { Text(L10n.TeamContextDeleteForAll) } icon: { PAXIcon("trash") }
                        }
                    }
                } label: {
                    PAXIcon("ellipsis.circle")
                }
                .accessibilityLabel(L10n.TeamContextConversationOptions)
            }
        }
        .alert(deleteAlertTitle, isPresented: $showDeleteConfirm) {
            Button(L10n.CommonCancel, role: .cancel) {}
            Button(deleteConfirmLabel, role: .destructive) {
                Task { await performDelete() }
            }
        } message: {
            Text(deleteAlertMessage)
        }
        .alert(L10n.TeamDeleteFeedbackTitle, isPresented: Binding(
            get: { deleteFeedback != nil },
            set: { if !$0 { deleteFeedback = nil } }
        )) {
            Button(L10n.CommonOK, role: .cancel) { deleteFeedback = nil }
        } message: {
            Text(deleteFeedback ?? "")
        }
        .alert(L10n.TeamMediaErrorTitle, isPresented: Binding(
            get: { mediaError != nil },
            set: { if !$0 { mediaError = nil } }
        )) {
            Button(L10n.CommonOK, role: .cancel) { mediaError = nil }
        } message: {
            Text(mediaError ?? "")
        }
        .alert(L10n.TeamSendErrorTitle, isPresented: Binding(
            get: { sendError != nil },
            set: { if !$0 { sendError = nil } }
        )) {
            Button(L10n.CommonOK, role: .cancel) { sendError = nil }
        } message: {
            Text(sendError ?? "")
        }
        .alert(L10n.ChatDeleteMessage, isPresented: $showMessageDeleteConfirm) {
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
        .sheet(isPresented: $showLocationPicker) {
            TeamLocationPickerSheet { latitude, longitude, label in
                Task {
                    await thread.sendLocation(
                        auth: auth,
                        teamCoordinator: teamCoordinator,
                        latitude: latitude,
                        longitude: longitude,
                        label: label
                    )
                    ChatScrollHelper.scrollToBottom(sessionId: thread.sessionId)
                }
            }
        }
        #if !SIDELOAD
        .photosPicker(isPresented: $showPhotoPicker, selection: $photoItem, matching: .images)
        #endif
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
        .sheet(item: $imageViewer) { item in
            FullScreenImageView(url: item.url)
        }
        #if !SIDELOAD
        .onChange(of: photoItem) { item in
            Task { await handlePhotoSelection(item) }
        }
        #endif
        .onAppear {
            thread.onConversationRemoved = { dismiss() }
            thread.start(auth: auth)
            coordinator.activeSessionId = thread.sessionId
            AppRefreshPolicy.setActiveSession(thread.sessionId)
            AppRefreshPolicy.update(liveCount: coordinator.liveCount, openChat: true)
            settings.markSessionRead(thread.sessionId, seq: thread.currentSeq)
            Task {
                await thread.markRead(auth: auth)
                await teamCoordinator.touchPresence(auth: auth)
            }
        }
        .onDisappear {
            thread.suspend()
            if coordinator.activeSessionId == thread.sessionId {
                coordinator.activeSessionId = nil
                AppRefreshPolicy.setActiveSession(nil)
            }
            AppRefreshPolicy.update(liveCount: coordinator.liveCount, openChat: false)
            settings.markSessionRead(thread.sessionId, seq: thread.currentSeq)
        }
        .onChange(of: thread.currentSeq) { seq in
            settings.markSessionRead(thread.sessionId, seq: seq)
            Task { await thread.markRead(auth: auth) }
        }
        .onChange(of: thread.draft) { _ in
            thread.handleDraftChange(auth: auth)
        }
        .onChange(of: teamLayoutRevision) { _ in
            ChatScrollHelper.scrollToBottom(sessionId: thread.sessionId)
        }
        .onChange(of: thread.errorMessage) { error in
            guard let error, !error.isEmpty else { return }
            sendError = error
            thread.errorMessage = nil
        }
        .onReceive(NotificationCenter.default.publisher(for: .paxSessionSync)) { note in
            guard let syncedId = note.userInfo?["session_id"] as? String,
                  syncedId == thread.sessionId else { return }
            let inlineMessage = note.userInfo?["inline_message"]
            Task { await thread.refreshNow(auth: auth, inlineMessage: inlineMessage) }
        }
        .fileImporter(
            isPresented: $showFileImporter,
            allowedContentTypes: [.item],
            allowsMultipleSelection: false
        ) { result in
            Task { await handleFileImport(result) }
        }
        .disabled(isDeleting)
    }

    private func handleFileImport(_ result: Result<[URL], Error>) async {
        switch result {
        case .failure(let error):
            mediaError = error.localizedDescription
        case .success(let urls):
            guard let url = urls.first else { return }
            let accessed = url.startAccessingSecurityScopedResource()
            defer { if accessed { url.stopAccessingSecurityScopedResource() } }
            do {
                let data = try Data(contentsOf: url)
                let filename = url.lastPathComponent
                await thread.sendFile(
                    auth: auth,
                    teamCoordinator: teamCoordinator,
                    fileData: data,
                    filename: filename
                )
            } catch {
                mediaError = error.localizedDescription
            }
        }
    }

    private var showsTeamStatusBanner: Bool {
        switch thread.requestStatus {
        case "pending", "declined", "locked":
            return true
        default:
            return !thread.canSend
        }
    }

    private var presenceLabel: String {
        if thread.remoteTyping { return L10n.TeamPresenceTyping }
        if thread.requestStatus == "pending" {
            return thread.canRespond ? L10n.TeamPresenceRequestPending : L10n.TeamWaitingApproval
        }
        if thread.requestStatus == "declined" || thread.requestStatus == "locked" {
            return L10n.TeamLockedConversation
        }
        if thread.otherPresence == "online" { return L10n.TeamPresenceOnline }
        if thread.otherPresence == "away" { return L10n.TeamPresenceAway }
        if thread.otherLastSeen > 0,
           let label = MessageTimeFormatter.relativeUpdatedLabel(from: teamLastSeenTimestamp(thread.otherLastSeen)) {
            return L10n.TeamPresenceLastSeen(label)
        }
        return L10n.TeamPresenceOffline
    }

    private func teamLastSeenTimestamp(_ unix: Int) -> String {
        let formatter = DateFormatter()
        formatter.locale = Locale(identifier: "en_US_POSIX")
        formatter.timeZone = TimeZone.current
        formatter.dateFormat = "yyyy-MM-dd HH:mm:ss"
        return formatter.string(from: Date(timeIntervalSince1970: TimeInterval(unix)))
    }

    private var teamStatusBanner: some View {
        HStack(spacing: 8) {
            PAXIcon(statusIcon)
            VStack(alignment: .leading, spacing: 2) {
                Text(thread.requestStatusLabel)
                    .font(.caption.weight(.semibold))
                if thread.requestStatus == "pending", thread.canRespond {
                    Text(L10n.TeamBannerReviewRequest)
                        .font(.caption2)
                        .foregroundStyle(PAXTheme.textSecondary)
                } else if !thread.canSend {
                    Text(L10n.TeamBannerLockedMessaging)
                        .font(.caption2)
                        .foregroundStyle(PAXTheme.textSecondary)
                }
            }
            Spacer(minLength: 0)
            if thread.canRespond {
                HStack(spacing: 8) {
                    Button(L10n.TeamActionDecline) {
                        Task { await thread.respondToRequest(accept: false, auth: auth, teamCoordinator: teamCoordinator) }
                    }
                    .font(.caption.weight(.semibold))
                    .foregroundStyle(PAXTheme.danger)
                    Button(L10n.TeamActionAccept) {
                        Task { await thread.respondToRequest(accept: true, auth: auth, teamCoordinator: teamCoordinator) }
                    }
                    .font(.caption.weight(.semibold))
                    .foregroundStyle(PAXBrand.accent)
                }
            }
        }
        .padding(.horizontal, 14)
        .padding(.vertical, 10)
        .background(
            RoundedRectangle(cornerRadius: 12, style: .continuous)
                .fill(statusTint.opacity(0.12))
                .overlay(
                    RoundedRectangle(cornerRadius: 12, style: .continuous)
                        .stroke(statusTint.opacity(0.28), lineWidth: 1)
                )
        )
        .padding(.horizontal, 12)
        .padding(.top, 8)
    }

    private var statusIcon: String {
        switch thread.requestStatus {
        case "pending": return "hourglass"
        case "declined", "locked": return "lock.fill"
        default: return "checkmark.seal.fill"
        }
    }

    private var statusTint: Color {
        switch thread.requestStatus {
        case "pending": return .orange
        case "declined", "locked": return PAXTheme.danger
        default: return PAXBrand.accent
        }
    }

    private var lockedComposer: some View {
        HStack(spacing: 10) {
            PAXIcon("lock.fill", emphasis: .tertiary)
            Text(thread.requestStatus == "declined" || thread.requestStatus == "locked"
                 ? L10n.TeamLockedConversation
                 : L10n.TeamWaitingApproval)
                .font(.subheadline)
                .foregroundStyle(PAXTheme.textSecondary)
        }
        .frame(maxWidth: .infinity, alignment: .leading)
        .padding(.horizontal, 14)
        .padding(.vertical, 14)
        .paxGlassCardStyle(cornerRadius: 18, fillOpacity: 0.82, borderOpacity: 0.44, shadowOpacity: 0.16)
        .padding(.horizontal, 12)
        .padding(.vertical, 10)
    }

    private var deleteAlertTitle: String {
        deleteMode == "purge_all" ? L10n.TeamDeleteEveryoneTitle : L10n.TeamDeleteRemoveTitle
    }

    private var deleteConfirmLabel: String {
        deleteMode == "purge_all" ? L10n.TeamDeleteConfirmAll : L10n.TeamDeleteConfirmRemove
    }

    private var deleteAlertMessage: String {
        deleteMode == "purge_all" ? L10n.TeamDeleteMessageAll : L10n.TeamDeleteMessageRemove
    }

    private func performDelete() async {
        guard !isDeleting else { return }
        isDeleting = true
        defer { isDeleting = false }

        let result = await teamCoordinator.deleteConversation(
            sessionId: thread.sessionId,
            mode: deleteMode,
            auth: auth
        )
        if result.success {
            settings.markSessionRead(thread.sessionId, seq: thread.currentSeq)
            coordinator.updateUnreadCounts()
            PAXHaptics.success()
            dismiss()
        } else {
            deleteFeedback = result.message ?? L10n.TeamDeleteFailed
        }
    }

    private var teamLayoutRevision: Int {
        var hasher = Hasher()
        hasher.combine(voiceRecorder.isRecording)
        return hasher.finalize()
    }

    private var canSend: Bool {
        thread.canSend
            && !thread.draft.trimmingCharacters(in: .whitespacesAndNewlines).isEmpty
    }

    private var teamComposer: some View {
        TeamIAChatComposer(
            draft: $thread.draft,
            voiceRecorder: voiceRecorder,
            canSendText: canSend,
            onSendText: {
                Task { await thread.send(auth: auth, teamCoordinator: teamCoordinator) }
            },
            onBeginVoice: {
                Task { await beginVoiceRecording() }
            },
            onFinishVoice: {
                Task { await finishVoiceRecording() }
            },
            onCancelVoice: {
                voiceRecorder.cancelRecording()
            },
            onPickPhoto: {
                #if SIDELOAD
                mediaError = L10n.TeamSendPhoto
                #else
                showPhotoPicker = true
                #endif
            },
            onPickFile: {
                showFileImporter = true
            },
            onPickLocation: {
                showLocationPicker = true
            }
        )
        .padding(.horizontal, 12)
        .padding(.vertical, 10)
        .padding(.bottom, 2)
        .paxGlassCardStyle(cornerRadius: 18, fillOpacity: colorScheme == .dark ? 0.55 : 0.82, borderOpacity: 0.44, shadowOpacity: 0.16)
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
        let caption = thread.draft.trimmingCharacters(in: .whitespacesAndNewlines)
        thread.draft = ""
        await thread.sendImage(
            auth: auth,
            teamCoordinator: teamCoordinator,
            imageData: prepared.data,
            filename: prepared.filename,
            caption: caption
        )
        pendingImage = nil
    }

    private func beginVoiceRecording() async {
        let granted = await voiceRecorder.requestPermission()
        guard granted else {
            mediaError = L10n.TeamMicrophoneDenied
            return
        }
        do {
            try voiceRecorder.startRecording()
        } catch {
            mediaError = error.localizedDescription
        }
    }

    private func finishVoiceRecording() async {
        do {
            let recording = try voiceRecorder.stopRecording()
            await thread.sendAudio(
                auth: auth,
                teamCoordinator: teamCoordinator,
                audioData: recording.data,
                filename: recording.filename,
                duration: recording.duration,
                waveform: recording.waveform
            )
            ChatScrollHelper.scrollToBottom(sessionId: thread.sessionId)
        } catch {
            mediaError = error.localizedDescription
        }
    }
}

private struct TeamImageViewerItem: Identifiable {
    let id = UUID()
    let url: URL

    init(url: URL) {
        self.url = url
    }
}

struct TeamComposeView: View {
    @EnvironmentObject private var auth: AuthStore
    @EnvironmentObject private var teamCoordinator: TeamMessagingCoordinator
    @Environment(\.dismiss) private var dismiss

    @State private var staff: [StaffMember] = []
    @State private var searchText = ""
    @State private var isLoading = true
    @State private var errorMessage: String?
    @State private var openingUserId: Int?
    @State private var requestNote = ""
    @State private var selectedMember: StaffMember?
    @FocusState private var isSearchFocused: Bool

    var onOpenConversation: (String) -> Void

    private var filteredStaff: [StaffMember] {
        let currentId = auth.profile?.userId ?? 0
        var items = staff.filter { $0.userId != currentId && $0.enabled }
        if !searchText.isEmpty {
            let q = searchText.lowercased()
            items = items.filter {
                $0.displayName.lowercased().contains(q) || $0.email.lowercased().contains(q)
            }
        }
        return items.sorted { lhs, rhs in
            let rank: (StaffMember) -> Int = { member in
                if member.isExecutive { return 0 }
                if member.isAdministrator { return 1 }
                if member.permissions.manageUsers { return 2 }
                return 3
            }
            let lr = rank(lhs)
            let rr = rank(rhs)
            if lr != rr { return lr < rr }
            return lhs.name.localizedCaseInsensitiveCompare(rhs.name) == .orderedAscending
        }
    }

    var body: some View {
        List {
            Section {
                PAXNativeSearchField(text: $searchText, prompt: L10n.SearchPrompt, isFocused: $isSearchFocused)
                    .listRowInsets(EdgeInsets(top: 8, leading: 0, bottom: 8, trailing: 0))
                    .listRowBackground(Color.clear)
            }

            if let selectedMember {
                Section(selectedMember.requiresEdRequest ? L10n.TeamComposeSectionEdRequest : L10n.TeamComposeSectionRequestNote) {
                    TextField(
                        selectedMember.requiresEdRequest
                            ? L10n.TeamComposePlaceholderEd
                            : L10n.TeamComposePlaceholderRequest,
                        text: $requestNote,
                        axis: .vertical
                    )
                    .lineLimit(2...4)
                    Text(selectedMember.requiresEdRequest
                         ? L10n.TeamComposeHintEd
                         : (selectedMember.requiresContactRequest
                            ? L10n.TeamComposeHintApprovalRequired
                            : L10n.TeamComposeHintOpen))
                        .font(.caption)
                        .foregroundStyle(PAXTheme.textSecondary)
                }
            }

            if isLoading {
                Section {
                    PAXScreenLoadingStack(status: L10n.LoadingTeamList, rowCount: 4)
                }
            } else if let errorMessage {
                Section {
                    Text(errorMessage)
                        .font(.footnote)
                        .foregroundStyle(PAXTheme.accent)
                }
            } else if filteredStaff.isEmpty {
                Section {
                    Text(L10n.TeamComposeEmpty)
                        .foregroundStyle(PAXTheme.textSecondary)
                }
            } else {
                Section(L10n.TeamComposeSection) {
                    ForEach(filteredStaff) { member in
                        StaffComposeRow(
                            member: member,
                            isOpening: openingUserId == member.userId,
                            isDisabled: openingUserId != nil
                        ) {
                            selectedMember = member
                            Task { await openChat(with: member) }
                        }
                    }
                }
            }
        }
        .listStyle(.insetGrouped)
        .scrollContentBackground(.hidden)
        .paxScreenBackground()
        .navigationTitle(L10n.TeamComposeTitle)
        .navigationBarTitleDisplayMode(.inline)
        .task { await loadStaff() }
    }

    private func loadStaff() async {
        isLoading = true
        defer { isLoading = false }
        do {
            staff = try await TeamContactsCache.shared.fetch(auth: auth, force: false)
            errorMessage = nil
        } catch {
            if staff.isEmpty {
                errorMessage = error.localizedDescription
            }
        }
    }

    private func openChat(with member: StaffMember) async {
        openingUserId = member.userId
        defer { openingUserId = nil }
        if let sessionId = await teamCoordinator.openConversation(
            with: member.userId,
            auth: auth,
            requestNote: requestNote
        ) {
            dismiss()
            onOpenConversation(sessionId)
        }
    }
}

private struct StaffComposeRow: View {
    let member: StaffMember
    let isOpening: Bool
    let isDisabled: Bool
    let action: () -> Void

    private var roleTint: Color {
        PAXTheme.textSecondary
    }

    var body: some View {
        Button(action: action) {
            HStack(spacing: 14) {
                ZStack(alignment: .bottomTrailing) {
                    StaffAvatarView(name: member.displayName, avatarUrl: member.avatarUrl, size: 48)
                    TeamPresenceGlyph(status: member.presenceStatus)
                        .offset(x: 2, y: 2)
                }

                VStack(alignment: .leading, spacing: 4) {
                    HStack(spacing: 6) {
                        Text(member.displayName)
                            .font(.body.weight(.semibold))
                            .foregroundStyle(PAXTheme.textPrimary)
                        if member.isExecutive {
                            PAXIcon("crown.fill", size: .inline, emphasis: .secondary)
                        }
                    }
                    Text(member.publicDisplaySubtitle)
                        .font(.caption.weight(.medium))
                        .foregroundStyle(roleTint)
                }

                Spacer()

                if isOpening {
                    PAXInlineLoader(size: 18)
                } else {
                    PAXIcon(member.requiresEdRequest ? "paperplane" : "message.fill")
                }
            }
        }
        .disabled(isDisabled)
    }
}
