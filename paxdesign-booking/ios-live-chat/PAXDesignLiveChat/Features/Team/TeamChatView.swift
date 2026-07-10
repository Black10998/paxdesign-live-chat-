import SwiftUI

struct TeamChatView: View {
    @EnvironmentObject private var auth: AuthStore
    @EnvironmentObject private var coordinator: ChatCoordinator
    @EnvironmentObject private var teamCoordinator: TeamMessagingCoordinator
    @EnvironmentObject private var settings: AppSettingsStore
    @Environment(\.dismiss) private var dismiss
    @StateObject private var thread: TeamChatThreadModel

    @State private var showDeleteConfirm = false
    @State private var deleteMode = "hide"
    @State private var isDeleting = false
    @State private var deleteFeedback: String?

    init(sessionId: String) {
        _thread = StateObject(wrappedValue: TeamChatThreadModel(sessionId: sessionId))
    }

    private var canPurgeForAll: Bool {
        auth.canManageUsers || auth.profile?.isSuperAdmin == true
    }

    var body: some View {
        VStack(spacing: 0) {
            ChatMessageListView(
                messages: thread.messages,
                userTyping: false,
                canReply: false,
                handler: "team",
                isLoading: thread.isLoadingMessages,
                agentDisplayName: auth.profile?.displayName ?? L10n.ChatAgent,
                customerDisplayName: thread.participantName.isEmpty ? L10n.TeamChatTitle : thread.participantName,
                onReply: { _ in },
                onCopy: { UIPasteboard.general.string = $0.content },
                onImageTap: { _ in }
            )

            teamComposer
        }
        .paxScreenBackground()
        .navigationTitle(thread.participantName.isEmpty ? L10n.TeamChatTitle : thread.participantName)
        .navigationBarTitleDisplayMode(.inline)
        .toolbar {
            ToolbarItem(placement: .topBarTrailing) {
                Menu {
                    Button(role: .destructive) {
                        deleteMode = "hide"
                        showDeleteConfirm = true
                    } label: {
                        Label("Remove from my Team list", systemImage: "eye.slash")
                    }

                    if canPurgeForAll {
                        Button(role: .destructive) {
                            deleteMode = "purge_all"
                            showDeleteConfirm = true
                        } label: {
                            Label("Delete for all participants", systemImage: "trash")
                        }
                    }
                } label: {
                    Image(systemName: "ellipsis.circle")
                }
                .accessibilityLabel("Conversation options")
            }
        }
        .alert(deleteAlertTitle, isPresented: $showDeleteConfirm) {
            Button("Cancel", role: .cancel) {}
            Button(deleteConfirmLabel, role: .destructive) {
                Task { await performDelete() }
            }
        } message: {
            Text(deleteAlertMessage)
        }
        .alert("Delete conversation", isPresented: Binding(
            get: { deleteFeedback != nil },
            set: { if !$0 { deleteFeedback = nil } }
        )) {
            Button("OK", role: .cancel) { deleteFeedback = nil }
        } message: {
            Text(deleteFeedback ?? "")
        }
        .onAppear {
            thread.onConversationRemoved = { dismiss() }
            thread.start(auth: auth)
            coordinator.activeSessionId = thread.sessionId
            AppRefreshPolicy.update(liveCount: coordinator.liveCount, openChat: true)
            settings.markSessionRead(thread.sessionId, seq: thread.currentSeq)
            Task { await thread.markRead(auth: auth) }
        }
        .onDisappear {
            thread.stop()
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
        .onReceive(NotificationCenter.default.publisher(for: .paxSessionSync)) { note in
            guard let syncedId = note.userInfo?["session_id"] as? String,
                  syncedId == thread.sessionId else { return }
            let inlineMessage = note.userInfo?["inline_message"]
            Task { await thread.refreshNow(auth: auth, inlineMessage: inlineMessage) }
        }
        .disabled(isDeleting)
    }

    private var deleteAlertTitle: String {
        deleteMode == "purge_all" ? "Delete for everyone?" : "Remove conversation?"
    }

    private var deleteConfirmLabel: String {
        deleteMode == "purge_all" ? "Delete for all" : "Remove for me"
    }

    private var deleteAlertMessage: String {
        if deleteMode == "purge_all" {
            return "This permanently deletes the conversation and all messages for every participant. This cannot be undone."
        }
        return "This removes the conversation from your Team list only. The other participant can still see it unless they also remove it."
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
            deleteFeedback = result.message ?? "Could not delete conversation."
        }
    }

    private var canSend: Bool {
        !thread.draft.trimmingCharacters(in: .whitespacesAndNewlines).isEmpty && !thread.isSending
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

            if isLoading {
                Section {
                    PAXScreenLoadingStack(status: "Teamliste wird geladen", rowCount: 4)
                }
            } else if let errorMessage {
                Section {
                    Text(errorMessage)
                        .font(.footnote)
                        .foregroundStyle(.orange)
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
                            revealFullEmail: auth.profile?.isSuperAdmin == true,
                            isOpening: openingUserId == member.userId,
                            isDisabled: openingUserId != nil
                        ) {
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
            staff = response.staff
        } catch {
            errorMessage = error.localizedDescription
        }
    }

    private func openChat(with member: StaffMember) async {
        openingUserId = member.userId
        defer { openingUserId = nil }
        if let sessionId = await teamCoordinator.openConversation(with: member.userId, auth: auth) {
            dismiss()
            onOpenConversation(sessionId)
        }
    }
}

private struct StaffComposeRow: View {
    let member: StaffMember
    let revealFullEmail: Bool
    let isOpening: Bool
    let isDisabled: Bool
    let action: () -> Void

    private var roleTint: Color {
        if member.isExecutive { return .purple }
        if member.isAdministrator { return PAXBrand.accent }
        if member.permissions.manageUsers { return .blue }
        return PAXTheme.textSecondary
    }

    var body: some View {
        Button(action: action) {
            HStack(spacing: 14) {
                SessionAvatarView(name: member.name, size: 48, isTeam: true)

                VStack(alignment: .leading, spacing: 4) {
                    Text(member.name)
                        .font(.body.weight(.semibold))
                        .foregroundStyle(PAXTheme.textPrimary)
                    Text(member.displayRoleLabel)
                        .font(.caption.weight(.semibold))
                        .foregroundStyle(roleTint)
                    Text(PrivacyMask.email(member.email, revealFull: revealFullEmail))
                        .font(.caption)
                        .foregroundStyle(PAXTheme.textSecondary)
                }

                Spacer()

                if isOpening {
                    PAXInlineLoader(size: 18)
                } else {
                    Image(systemName: "message.fill")
                        .foregroundStyle(roleTint)
                }
            }
        }
        .disabled(isDisabled)
    }
}
