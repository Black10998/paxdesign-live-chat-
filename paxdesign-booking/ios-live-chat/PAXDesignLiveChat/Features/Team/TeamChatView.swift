import SwiftUI

struct TeamChatView: View {
    @EnvironmentObject private var auth: AuthStore
    @EnvironmentObject private var coordinator: ChatCoordinator
    @EnvironmentObject private var teamCoordinator: TeamMessagingCoordinator
    @EnvironmentObject private var settings: AppSettingsStore
    @Environment(\.dismiss) private var dismiss
    @ObservedObject private var thread: TeamChatThreadModel

    @State private var showDeleteConfirm = false
    @State private var deleteMode = "hide"
    @State private var isDeleting = false
    @State private var deleteFeedback: String?

    init(sessionId: String) {
        _thread = ObservedObject(wrappedValue: ChatThreadRegistry.shared.teamThread(sessionId: sessionId))
    }

    private var canPurgeForAll: Bool {
        auth.canManageUsers || auth.profile?.isSuperAdmin == true
    }

    var body: some View {
        VStack(spacing: 0) {
            if !thread.requestStatusLabel.isEmpty {
                teamStatusBanner
            }

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
                onReply: { _ in },
                onCopy: { UIPasteboard.general.string = $0.content },
                onDelete: { _ in },
                onImageTap: { _ in },
                teamOtherReadSeq: thread.otherReadSeq,
                teamFailedClientMsgIds: thread.failedClientMsgIds,
                onRetryTeamMessage: { clientId in
                    Task { await thread.retryFailedMessage(clientId, auth: auth, teamCoordinator: teamCoordinator) }
                },
                deletingMessageIds: []
            )
            .frame(maxWidth: .infinity, maxHeight: .infinity)
        }
        .paxChatScreenBackground()
        .safeAreaInset(edge: .bottom, spacing: 0) {
            if thread.canSend {
                teamComposer
                    .background(PAXBackground())
            } else {
                lockedComposer
                    .background(PAXBackground())
            }
        }
        .navigationTitle(thread.participantName.isEmpty ? L10n.TeamChatTitle : thread.participantName)
        .navigationBarTitleDisplayMode(.inline)
        .toolbar {
            ToolbarItem(placement: .principal) {
                VStack(spacing: 2) {
                    Text(thread.participantName.isEmpty ? L10n.TeamChatTitle : thread.participantName)
                        .font(.headline)
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
                            Label(L10n.TeamContextAcceptRequest, systemImage: "checkmark.circle")
                        }
                        Button(role: .destructive) {
                            Task { await thread.respondToRequest(accept: false, auth: auth, teamCoordinator: teamCoordinator) }
                        } label: {
                            Label(L10n.TeamContextDeclineRequest, systemImage: "xmark.circle")
                        }
                    }
                    Button {
                        Task { await teamCoordinator.pinConversation(sessionId: thread.sessionId, pinned: true, auth: auth) }
                    } label: {
                        Label(L10n.TeamContextPinConversation, systemImage: "pin")
                    }
                    Button(role: .destructive) {
                        deleteMode = "hide"
                        showDeleteConfirm = true
                    } label: {
                        Label(L10n.TeamContextRemoveFromList, systemImage: "eye.slash")
                    }
                    if canPurgeForAll {
                        Button(role: .destructive) {
                            deleteMode = "purge_all"
                            showDeleteConfirm = true
                        } label: {
                            Label(L10n.TeamContextDeleteForAll, systemImage: "trash")
                        }
                    }
                } label: {
                    Image(systemName: "ellipsis.circle")
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
        .onAppear {
            thread.onConversationRemoved = { dismiss() }
            thread.start(auth: auth)
            coordinator.activeSessionId = thread.sessionId
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
        .onReceive(NotificationCenter.default.publisher(for: .paxSessionSync)) { note in
            guard let syncedId = note.userInfo?["session_id"] as? String,
                  syncedId == thread.sessionId else { return }
            let inlineMessage = note.userInfo?["inline_message"]
            Task { await thread.refreshNow(auth: auth, inlineMessage: inlineMessage) }
        }
        .disabled(isDeleting)
    }

    private var presenceLabel: String {
        if thread.remoteTyping { return L10n.TeamPresenceTyping }
        if thread.requestStatus == "pending" {
            return thread.canRespond ? L10n.TeamPresenceRequestPending : thread.requestStatusLabel
        }
        if thread.otherPresence == "online" { return L10n.TeamPresenceOnline }
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
            Image(systemName: statusIcon)
                .foregroundStyle(statusTint)
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
            Image(systemName: "lock.fill")
                .foregroundStyle(PAXTheme.textTertiary)
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

    private var canSend: Bool {
        thread.canSend
            && !thread.draft.trimmingCharacters(in: .whitespacesAndNewlines).isEmpty
            && !thread.isSending
    }

    private var teamComposer: some View {
        HStack(spacing: 10) {
            TextField(L10n.TeamChatPlaceholder, text: $thread.draft, axis: .vertical)
                .lineLimit(1...5)
                .padding(.horizontal, 14)
                .padding(.vertical, 10)
                .paxGlassCardStyle(cornerRadius: 22, fillOpacity: 0.78, borderOpacity: 0.42, shadowOpacity: 0.08)

            Button {
                Task { await thread.send(auth: auth, teamCoordinator: teamCoordinator) }
            } label: {
                Image(systemName: "arrow.up.circle.fill")
                    .font(.system(size: 34))
                    .foregroundStyle(canSend ? PAXBrand.accent : PAXTheme.textTertiary)
            }
            .disabled(!canSend)
        }
        .padding(.horizontal, 12)
        .padding(.vertical, 10)
        .paxGlassCardStyle(cornerRadius: 18, fillOpacity: 0.82, borderOpacity: 0.44, shadowOpacity: 0.16)
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
                $0.name.lowercased().contains(q) || $0.email.lowercased().contains(q)
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
        guard let api = auth.api else { return }
        isLoading = true
        defer { isLoading = false }
        do {
            let response = try await api.fetchTeamContacts()
            staff = response.staff.deduplicatedByUserId()
        } catch {
            errorMessage = error.localizedDescription
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
        if member.isExecutive { return PAXTheme.accent }
        if member.isAdministrator { return PAXBrand.accent }
        if member.permissions.manageUsers { return .blue }
        return PAXTheme.textSecondary
    }

    var body: some View {
        Button(action: action) {
            HStack(spacing: 14) {
                ZStack(alignment: .bottomTrailing) {
                    SessionAvatarView(name: member.name, size: 48, isTeam: true)
                    if member.isOnline {
                        Circle()
                            .fill(Color.green)
                            .frame(width: 10, height: 10)
                            .overlay(Circle().stroke(Color.white, lineWidth: 2))
                            .offset(x: 2, y: 2)
                    }
                }

                VStack(alignment: .leading, spacing: 4) {
                    HStack(spacing: 6) {
                        Text(member.name)
                            .font(.body.weight(.semibold))
                            .foregroundStyle(PAXTheme.textPrimary)
                        if member.isExecutive {
                            Image(systemName: "crown.fill")
                                .font(.caption2)
                                .foregroundStyle(PAXTheme.accent)
                        }
                    }
                    Text(member.publicDisplaySubtitle)
                        .font(.caption.weight(.semibold))
                        .foregroundStyle(roleTint)
                }

                Spacer()

                if isOpening {
                    PAXInlineLoader(size: 18)
                } else {
                    Image(systemName: member.requiresEdRequest ? "paperplane" : "message.fill")
                        .foregroundStyle(roleTint)
                }
            }
        }
        .disabled(isDisabled)
    }
}
