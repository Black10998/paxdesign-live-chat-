import SwiftUI

struct TeamMessagesHubView: View {
    @EnvironmentObject private var auth: AuthStore
    @EnvironmentObject private var coordinator: ChatCoordinator
    @EnvironmentObject private var teamCoordinator: TeamMessagingCoordinator
    @EnvironmentObject private var settings: AppSettingsStore
    @State private var searchText = ""
    @State private var showCompose = false
    @State private var displayedSessions: [LiveSession] = []
    @State private var teamSessionCount = 0
    @State private var unreadCount = 0
    @State private var recomputeTask: Task<Void, Never>?
    @State private var teamContacts: [StaffMember] = []
    @State private var contactsLoading = false
    @State private var openingContactId: Int?
    @FocusState private var isSearchFocused: Bool
    var onOpenSession: (String) -> Void = { _ in }

    private var canComposeTeam: Bool { auth.canViewChats }

    var body: some View {
        List {
            heroSection
            pendingRequestsSection
            statsSection
            composeSection
            contactsSection
            errorSection
            searchSection
            conversationsSection
        }
        .listStyle(.insetGrouped)
        .scrollContentBackground(.hidden)
        .paxScreenBackground()
        .navigationTitle(L10n.TeamHubTitle)
        .navigationBarTitleDisplayMode(.inline)
        .toolbarBackground(.visible, for: .navigationBar)
        .toolbar {
            if canComposeTeam {
                ToolbarItem(placement: .topBarTrailing) {
                    Button {
                        PAXHaptics.light()
                        showCompose = true
                    } label: {
                        PAXIcon("square.and.pencil", size: .row)
                    }
                    .accessibilityLabel(L10n.TeamNewMessage)
                }
            }
        }
        .sheet(isPresented: $showCompose) {
            NavigationStack {
                TeamComposeView { sessionId in
                    onOpenSession(sessionId)
                }
            }
            .environmentObject(auth)
            .environmentObject(teamCoordinator)
        }
        .refreshable {
            await coordinator.fullConversationSync(auth: auth)
            await teamCoordinator.fullConversationSync(auth: auth)
            await loadContacts()
        }
        .onAppear {
            scheduleRecompute(immediate: true)
            Task {
                await loadContacts()
                await teamCoordinator.refreshPendingRequests(auth: auth)
                await teamCoordinator.touchPresence(auth: auth)
            }
        }
        .onDisappear {
            recomputeTask?.cancel()
            recomputeTask = nil
        }
        .onChange(of: coordinator.sessions) { _ in scheduleRecompute(immediate: true) }
        .onChange(of: teamCoordinator.teamSessions) { _ in scheduleRecompute(immediate: true) }
        .onChange(of: searchText) { _ in scheduleRecompute(immediate: false) }
        .onChange(of: settings.readSessionIds) { _ in scheduleRecompute(immediate: true) }
        .onChange(of: settings.readUpToSeq) { _ in scheduleRecompute(immediate: true) }
        .onChange(of: teamCoordinator.pendingRequests) { _ in scheduleRecompute(immediate: true) }
    }

    private var heroSection: some View {
        Section {
            HStack(spacing: 14) {
                ProfileAvatarView(size: 56)
                VStack(alignment: .leading, spacing: 4) {
                    Text(auth.profile?.displayName ?? L10n.CommonAdministrator)
                        .font(.title3.weight(.bold))
                        .foregroundStyle(PAXTheme.textPrimary)
                    Text(auth.roleLabel)
                        .font(.subheadline)
                        .foregroundStyle(PAXTheme.textSecondary)
                    if let email = auth.profile?.email.trimmingCharacters(in: .whitespacesAndNewlines), !email.isEmpty {
                        Text(email)
                            .font(.caption)
                            .foregroundStyle(PAXTheme.textTertiary)
                            .lineLimit(1)
                    }
                }
                Spacer(minLength: 0)
                PAXIcon("person.3.fill", size: .hero, emphasis: .tertiary)
            }
            .padding(.vertical, 4)
            .listRowInsets(EdgeInsets(top: 8, leading: 0, bottom: 8, trailing: 0))
            .listRowBackground(Color.clear)
            .listRowSeparator(.hidden)
        }
    }

    @ViewBuilder
    private var pendingRequestsSection: some View {
        if !teamCoordinator.pendingRequests.isEmpty {
            Section(L10n.TeamPendingRequests) {
                ForEach(teamCoordinator.pendingRequests) { session in
                    pendingRequestRow(session)
                }
            }
        }
    }

    private func pendingRequestRow(_ session: LiveSession) -> some View {
        VStack(alignment: .leading, spacing: 10) {
            HStack {
                SessionAvatarView(name: session.displayName, size: 42, isTeam: true)
                VStack(alignment: .leading, spacing: 4) {
                    Text(session.displayName)
                        .font(.body.weight(.semibold))
                    Text(session.localizedOtherRoleLabel)
                        .font(.caption)
                        .foregroundStyle(PAXTheme.accent)
                }
                Spacer()
                Text(session.requestStatusLabel)
                    .font(.caption2.weight(.bold))
                    .foregroundStyle(PAXTheme.accent)
            }
            if !session.lastPreview.isEmpty {
                Text(session.lastPreview)
                    .font(.subheadline)
                    .foregroundStyle(PAXTheme.textSecondary)
                    .lineLimit(2)
            }
            HStack(spacing: 12) {
                Button(L10n.TeamActionDecline) {
                    Task {
                        _ = await teamCoordinator.respondToRequest(sessionId: session.sessionId, accept: false, auth: auth)
                        scheduleRecompute(immediate: true)
                    }
                }
                .buttonStyle(.bordered)
                .tint(PAXTheme.danger)
                Button(L10n.TeamActionAccept) {
                    Task {
                        _ = await teamCoordinator.respondToRequest(sessionId: session.sessionId, accept: true, auth: auth)
                        onOpenSession(session.sessionId)
                    }
                }
                .buttonStyle(.borderedProminent)
                .tint(PAXBrand.accent)
            }
        }
        .padding(.vertical, 4)
    }

    @ViewBuilder
    private var statsSection: some View {
        if unreadCount > 0 || teamSessionCount > 0 {
            Section {
                HStack(spacing: 12) {
                    TeamStatPill(value: "\(teamSessionCount)", label: L10n.TeamHubConversations, tint: PAXBrand.accent)
                    if unreadCount > 0 {
                        TeamStatPill(value: "\(unreadCount)", label: L10n.FilterUnread, tint: PAXTheme.accent)
                    }
                }
                .listRowInsets(EdgeInsets(top: 4, leading: 0, bottom: 4, trailing: 0))
                .listRowBackground(Color.clear)
                .listRowSeparator(.hidden)
            }
        }
    }

    @ViewBuilder
    private var composeSection: some View {
        if canComposeTeam {
            Section {
                HStack {
                    Text(L10n.TeamStartConversation)
                        .font(.headline)
                    Spacer()
                    Button {
                        showCompose = true
                    } label: {
                        Label { Text(L10n.TeamNewMessage) } icon: { PAXIcon("square.and.pencil") }
                            .font(.subheadline.weight(.semibold))
                    }
                }
                .listRowBackground(Color.clear)
            }
        }
    }

    @ViewBuilder
    private var contactsSection: some View {
        if canComposeTeam {
            if contactsLoading && teamContacts.isEmpty {
                Section {
                    PAXScreenLoadingStack(status: L10n.LoadingContacts, rowCount: 2)
                }
            } else if !teamContacts.isEmpty {
                Section(L10n.TeamSectionLeadership) {
                    ForEach(teamContacts) { member in
                        TeamContactRow(
                            member: member,
                            isOpening: openingContactId == member.userId
                        ) {
                            Task { await openContact(member) }
                        }
                    }
                }
            }
        }
    }

    @ViewBuilder
    private var errorSection: some View {
        if let error = teamCoordinator.errorMessage, !error.isEmpty {
            Section {
                Text(error)
                    .font(.caption)
                    .foregroundStyle(PAXTheme.danger)
            }
        }
    }

    private var searchSection: some View {
        Section {
            PAXNativeSearchField(
                text: $searchText,
                prompt: L10n.SearchPrompt,
                isFocused: $isSearchFocused
            )
            .listRowInsets(EdgeInsets(top: 4, leading: 0, bottom: 4, trailing: 0))
            .listRowBackground(Color.clear)
            .listRowSeparator(.hidden)
        }
    }

    @ViewBuilder
    private var conversationsSection: some View {
        if displayedSessions.isEmpty {
            Section {
                if teamCoordinator.isLoading && searchText.trimmingCharacters(in: .whitespacesAndNewlines).isEmpty {
                    teamLoadingState
                        .listRowInsets(EdgeInsets(top: 14, leading: 0, bottom: 14, trailing: 0))
                } else {
                    teamEmptyState
                        .listRowInsets(EdgeInsets(top: 24, leading: 0, bottom: 24, trailing: 0))
                }
            }
            .listRowBackground(Color.clear)
            .listRowSeparator(.hidden)
        } else {
            Section(L10n.TeamHubConversations) {
                ForEach(displayedSessions) { session in
                    teamConversationRow(session)
                }
            }
        }
    }

    private var teamLoadingState: some View {
        PAXScreenLoadingStack(status: L10n.LoadingTeamConversations, rowCount: 4)
    }

    private func loadContacts() async {
        guard canComposeTeam, let api = auth.api else { return }
        contactsLoading = true
        defer { contactsLoading = false }
        if let response = try? await api.fetchTeamContacts() {
            let currentId = auth.profile?.userId ?? 0
            teamContacts = response.staff
                .deduplicatedByUserId()
                .filter { $0.userId != currentId && $0.enabled }
                .sorted { lhs, rhs in
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
    }

    private func openContact(_ member: StaffMember) async {
        openingContactId = member.userId
        defer { openingContactId = nil }
        if let sessionId = await teamCoordinator.openConversation(with: member.userId, auth: auth) {
            onOpenSession(sessionId)
        }
    }

    private func deleteConversation(_ sessionId: String, mode: String) async {
        let result = await teamCoordinator.deleteConversation(sessionId: sessionId, mode: mode, auth: auth)
        if result.success {
            settings.markSessionRead(sessionId, seq: 0)
            coordinator.updateUnreadCounts()
            scheduleRecompute(immediate: true)
            PAXHaptics.success()
        }
    }

    private func teamConversationRow(_ session: LiveSession) -> some View {
        let isUnread = settings.isSessionUnread(session)

        return Button {
            isSearchFocused = false
            PAXKeyboard.dismiss()
            onOpenSession(session.sessionId)
            PAXHaptics.light()
        } label: {
            HStack(spacing: 14) {
                ZStack(alignment: .bottomTrailing) {
                    SessionAvatarView(name: session.displayName, size: 48, isTeam: true)
                    PAXIcon("person.3.fill", size: .micro)
                        .padding(3)
                        .background(Circle().fill(Color(.tertiarySystemFill)))
                        .offset(x: 3, y: 3)
                }

                VStack(alignment: .leading, spacing: 5) {
                    HStack(spacing: 6) {
                        Text(session.displayName)
                            .font(.body.weight(isUnread ? .bold : .semibold))
                            .foregroundStyle(PAXTheme.textPrimary)
                            .lineLimit(1)
                        if session.isExecutiveConversation {
                            PAXIcon("crown.fill", size: .inline)
                        }
                        if session.isPinned {
                            PAXIcon("pin.fill", size: .inline)
                        }
                        Spacer(minLength: 4)
                        if let time = MessageTimeFormatter.relativeUpdatedLabel(from: session.updatedAt) {
                            Text(time)
                                .font(.caption)
                                .foregroundStyle(isUnread ? PAXBrand.accent : PAXTheme.textTertiary)
                        }
                    }

                    HStack(spacing: 8) {
                        if !session.otherRoleLabel.isEmpty {
                            Text(session.localizedOtherRoleLabel)
                                .font(.caption2.weight(.bold))
                                .foregroundStyle(session.isExecutiveConversation ? .purple : PAXBrand.accent)
                                .padding(.horizontal, 6)
                                .padding(.vertical, 2)
                                .background(Capsule().fill(PAXTheme.accent.opacity(0.15)))
                        } else {
                            Text(L10n.SessionTeamBadge)
                                .font(.caption2.weight(.bold))
                                .foregroundStyle(PAXBrand.accent)
                                .padding(.horizontal, 6)
                                .padding(.vertical, 2)
                                .background(Capsule().fill(PAXBrand.accent.opacity(0.15)))
                        }

                        if session.isRequestPending {
                            Text(session.requestStatusLabel)
                                .font(.caption2.weight(.semibold))
                                .foregroundStyle(PAXTheme.accent)
                        }

                        Text(session.lastPreview.isEmpty ? L10n.TeamChatPlaceholder : session.lastPreview)
                            .font(.subheadline)
                            .fontWeight(isUnread ? .medium : .regular)
                            .foregroundStyle(isUnread ? PAXTheme.textPrimary : PAXTheme.textSecondary)
                            .lineLimit(1)

                        Spacer(minLength: 0)

                        if isUnread {
                            Circle()
                                .fill(PAXBrand.accent)
                                .frame(width: 10, height: 10)
                        }
                    }
                }
            }
            .padding(.horizontal, 16)
            .padding(.vertical, 10)
            .padding(.horizontal, 12)
            .padding(.vertical, 3)
            .background(
                RoundedRectangle(cornerRadius: 16, style: .continuous)
                    .fill(.ultraThinMaterial)
                    .overlay(
                        RoundedRectangle(cornerRadius: 16, style: .continuous)
                            .fill(PAXTheme.surface.opacity(isUnread ? 0.82 : 0.74))
                    )
                    .overlay(
                        RoundedRectangle(cornerRadius: 16, style: .continuous)
                            .stroke(isUnread ? PAXTheme.accent.opacity(0.42) : PAXTheme.border.opacity(0.42), lineWidth: 1)
                    )
                    .shadow(color: .black.opacity(isUnread ? 0.2 : 0.12), radius: 12, x: 0, y: 8)
            )
        }
        .buttonStyle(.plain)
        .onAppear {
            if let api = auth.api {
                ConversationPrefetcher.shared.schedulePrefetch(
                    sessionId: session.sessionId,
                    api: api,
                    isTeam: true
                )
            }
        }
        .listRowInsets(EdgeInsets(top: 2, leading: 0, bottom: 2, trailing: 0))
        .listRowSeparator(.hidden)
        .listRowBackground(Color.clear)
        .contextMenu {
            Button {
                settings.markSessionRead(session.sessionId, seq: session.seq)
                PAXHaptics.light()
            } label: {
                Label { Text(L10n.CommonMarkRead) } icon: { PAXIcon("envelope.open") }
            }
            Button {
                settings.markSessionUnread(session.sessionId)
                PAXHaptics.light()
            } label: {
                Label { Text(L10n.CommonMarkUnread) } icon: { PAXIcon("envelope.badge") }
            }
            Button(role: .destructive) {
                Task { await deleteConversation(session.sessionId, mode: "hide") }
            } label: {
                Label { Text(L10n.TeamContextRemoveFromList) } icon: { PAXIcon("eye.slash") }
            }
        }
        .swipeActions(edge: .leading, allowsFullSwipe: true) {
            if isUnread {
                Button {
                    settings.markSessionRead(session.sessionId, seq: session.seq)
                    PAXHaptics.success()
                } label: {
                    Label { Text(L10n.CommonMarkRead) } icon: { PAXIcon("envelope.open") }
                }
                .tint(.blue)
            } else {
                Button {
                    settings.markSessionUnread(session.sessionId)
                    PAXHaptics.light()
                } label: {
                    Label { Text(L10n.CommonMarkUnread) } icon: { PAXIcon("envelope.badge") }
                }
                .tint(.gray)
            }
        }
    }

    private func scheduleRecompute(immediate: Bool) {
        recomputeTask?.cancel()
        let coordinatorSessions = coordinator.sessions
        let teamOnlySessions = teamCoordinator.teamSessions
        let query = searchText.trimmingCharacters(in: .whitespacesAndNewlines).lowercased()

        recomputeTask = Task(priority: .userInitiated) {
            if !immediate {
                try? await Task.sleep(nanoseconds: 120_000_000)
            }
            guard !Task.isCancelled else { return }

            let state = Self.computeListState(
                coordinatorSessions: coordinatorSessions,
                teamSessions: teamOnlySessions,
                settings: settings,
                searchText: query
            )

            guard !Task.isCancelled else { return }
            await MainActor.run {
                displayedSessions = state.displayedSessions
                teamSessionCount = state.teamSessionCount
                unreadCount = state.unreadCount
            }
        }
    }

    private static func computeListState(
        coordinatorSessions: [LiveSession],
        teamSessions: [LiveSession],
        settings: AppSettingsStore,
        searchText: String
    ) -> TeamHubListState {
        let allSessions = TeamMessagingCoordinator.mergeTeamSessions(
            teamSessions: teamSessions,
            coordinatorSessions: coordinatorSessions
        )
        let filtered: [LiveSession]
        if searchText.isEmpty {
            filtered = allSessions
        } else {
            filtered = allSessions.filter {
                $0.displayName.lowercased().contains(searchText)
                    || $0.lastPreview.lowercased().contains(searchText)
                    || $0.otherRoleLabel.lowercased().contains(searchText)
            }
        }

        let sorted = filtered.sorted { lhs, rhs in
            if lhs.isPinned != rhs.isPinned { return lhs.isPinned && !rhs.isPinned }
            if lhs.otherRoleRank != rhs.otherRoleRank { return lhs.otherRoleRank < rhs.otherRoleRank }
            return lhs.updatedAt > rhs.updatedAt
        }

        let unread = allSessions.filter { settings.isSessionUnread($0) }.count
        return TeamHubListState(displayedSessions: sorted, teamSessionCount: allSessions.count, unreadCount: unread)
    }

    private var teamEmptyState: some View {
        VStack(spacing: 16) {
            PAXIcon("person.3.sequence.fill", size: .hero)
            Text(L10n.TeamHubEmpty)
                .font(.title3.weight(.semibold))
            Text(L10n.TeamHubEmptyHint)
                .font(.subheadline)
                .foregroundStyle(PAXTheme.textSecondary)
                .multilineTextAlignment(.center)
            if canComposeTeam {
                Button {
                    showCompose = true
                } label: {
                    Label { Text(L10n.TeamNewMessage) } icon: { PAXIcon("square.and.pencil") }
                        .font(.subheadline.weight(.semibold))
                        .padding(.horizontal, 18)
                        .padding(.vertical, 10)
                        .background(Capsule().fill(PAXBrand.accent.opacity(0.18)))
                }
                .buttonStyle(.plain)
                .padding(.top, 4)
            }
        }
        .frame(maxWidth: .infinity)
    }
}

private struct TeamHubListState {
    let displayedSessions: [LiveSession]
    let teamSessionCount: Int
    let unreadCount: Int
}

private struct TeamStatPill: View {
    let value: String
    let label: String
    let tint: Color

    @State private var pulse = false

    var body: some View {
        VStack(spacing: 4) {
            Text(value)
                .font(.title2.weight(.bold))
                .foregroundStyle(tint)
                .contentTransition(.numericText())
                .scaleEffect(pulse ? 1.08 : 1)
                .animation(.spring(response: 0.35, dampingFraction: 0.8), value: value)
            Text(label)
                .font(.caption2.weight(.medium))
                .foregroundStyle(PAXTheme.textSecondary)
                .lineLimit(1)
                .minimumScaleFactor(0.8)
        }
        .frame(maxWidth: .infinity)
        .padding(.vertical, 12)
        .background(
            RoundedRectangle(cornerRadius: 14, style: .continuous)
                .fill(tint.opacity(pulse ? 0.22 : 0.12))
        )
        .onChange(of: value) { _ in
            pulse = true
            DispatchQueue.main.asyncAfter(deadline: .now() + 0.45) {
                pulse = false
            }
        }
    }
}

private struct TeamContactRow: View {
    let member: StaffMember
    let isOpening: Bool
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
                        Circle().fill(Color.green).frame(width: 10, height: 10)
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
                            PAXIcon("crown.fill", size: .inline)
                        }
                    }
                    Text(member.publicDisplaySubtitle)
                        .font(.caption.weight(.semibold))
                        .foregroundStyle(roleTint)
                    if !member.localizedProfileTitle.isEmpty {
                        Text(member.localizedProfileTitle)
                            .font(.caption)
                            .foregroundStyle(PAXTheme.textSecondary)
                    }
                }
                Spacer()
                if isOpening {
                    ProgressView()
                } else {
                    PAXIcon(member.requiresEdRequest ? "paperplane" : "message.fill")
                }
            }
            .padding(.vertical, 4)
        }
        .buttonStyle(.plain)
        .disabled(isOpening)
    }
}

private extension Optional where Wrapped == String {
    var orEmpty: String { self ?? "" }
}
