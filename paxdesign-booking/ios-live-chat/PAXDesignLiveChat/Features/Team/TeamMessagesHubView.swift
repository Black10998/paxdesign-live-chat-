import SwiftUI

struct TeamMessagesHubView: View {
    @EnvironmentObject private var auth: AuthStore
    @EnvironmentObject private var coordinator: ChatCoordinator
    @EnvironmentObject private var teamCoordinator: TeamMessagingCoordinator
    @EnvironmentObject private var settings: AppSettingsStore

    @State private var showCompose = false
    @State private var showBroadcast = false
    @State private var displayedSessions: [LiveSession] = []
    @State private var teamSessionCount = 0
    @State private var unreadCount = 0
    @State private var waitingCount = 0
    @State private var alertCount = 0
    @State private var recomputeTask: Task<Void, Never>?
    @State private var teamContacts: [StaffMember] = []
    @State private var contactsLoading = false
    @State private var contactsRevision = 0
    @State private var openingContactId: Int?
    @State private var deletingSessionIds = Set<String>()

    var onOpenSession: (String) -> Void = { _ in }

    private var canComposeTeam: Bool { auth.canViewChats }

    var body: some View {
        ScrollView {
            LazyVStack(spacing: 18) {
                profileSection
                metricsSection
                actionsSection
                pendingRequestsSection
                teamMembersSection
                liveAlertsSection
                conversationsSection
            }
            .padding(.horizontal, 16)
            .padding(.top, 8)
            .padding(.bottom, 24)
        }
        .scrollIndicators(.hidden)
        .paxScreenBackground()
        .navigationTitle(L10n.TeamHubTitle)
        .navigationBarTitleDisplayMode(.inline)
        .toolbarBackground(.visible, for: .navigationBar)
        .toolbar {
            ToolbarItem(placement: .topBarTrailing) {
                HStack(spacing: 16) {
                    Button {
                        PAXHaptics.light()
                        showCompose = true
                    } label: {
                        PAXIcon("archivebox", size: .row, emphasis: .secondary)
                    }
                    .accessibilityLabel(L10n.TeamHubConversations)
                    Button {
                        PAXHaptics.light()
                        showCompose = true
                    } label: {
                        PAXIcon("team.headset", size: .row, emphasis: .secondary)
                    }
                    .accessibilityLabel(L10n.TeamStartConversation)
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
        .sheet(isPresented: $showBroadcast) {
            NavigationStack {
                TeamBroadcastSheet(
                    recipients: teamContacts,
                    onOpenSession: onOpenSession
                )
            }
            .environmentObject(auth)
            .environmentObject(teamCoordinator)
        }
        .refreshable {
            await refreshAll()
        }
        .onAppear {
            scheduleRecompute(immediate: true)
            Task { await refreshAll() }
        }
        .onDisappear {
            recomputeTask?.cancel()
            recomputeTask = nil
        }
        .onChange(of: coordinator.sessions) { _ in scheduleRecompute(immediate: true) }
        .onChange(of: teamCoordinator.teamSessions) { _ in scheduleRecompute(immediate: true) }
        .onChange(of: settings.readSessionIds) { _ in scheduleRecompute(immediate: true) }
        .onChange(of: settings.readUpToSeq) { _ in scheduleRecompute(immediate: true) }
        .onChange(of: teamCoordinator.pendingRequests) { _ in scheduleRecompute(immediate: true) }
        .onChange(of: contactsRevision) { _ in scheduleRecompute(immediate: true) }
    }

    // MARK: - Sections

    private var profileSection: some View {
        TeamHubProfileCard(
            displayName: auth.profile?.displayName ?? L10n.CommonAdministrator,
            roleLabel: auth.roleLabel,
            email: auth.profile?.email.trimmingCharacters(in: .whitespacesAndNewlines) ?? ""
        )
    }

    private var metricsSection: some View {
        HStack(spacing: 10) {
            TeamHubMetricTile(
                icon: "chat.bubble",
                value: "\(teamSessionCount)",
                label: L10n.TeamMetricActiveConversations
            )
            TeamHubMetricTile(
                icon: "team.alert",
                value: "\(alertCount)",
                label: L10n.TeamMetricNewAlerts
            )
            TeamHubMetricTile(
                icon: "clock.history",
                value: "\(waitingCount)",
                label: L10n.TeamMetricWaitingReply
            )
        }
    }

    @ViewBuilder
    private var actionsSection: some View {
        if canComposeTeam {
            VStack(spacing: 10) {
                TeamHubActionRow(
                    icon: "team.headset",
                    title: L10n.TeamStartConversation,
                    subtitle: L10n.TeamStartConversationHint
                ) {
                    PAXHaptics.light()
                    showCompose = true
                }
                TeamHubActionRow(
                    icon: "team.broadcast",
                    title: L10n.TeamMessageTeam,
                    subtitle: L10n.TeamMessageTeamHint
                ) {
                    PAXHaptics.light()
                    showBroadcast = true
                }
            }
        }
    }

    @ViewBuilder
    private var pendingRequestsSection: some View {
        if !teamCoordinator.pendingRequests.isEmpty {
            VStack(alignment: .leading, spacing: 10) {
                sectionHeader(L10n.TeamPendingRequests)
                ForEach(teamCoordinator.pendingRequests) { session in
                    pendingRequestCard(session)
                }
            }
        }
    }

    @ViewBuilder
    private var teamMembersSection: some View {
        if canComposeTeam {
            VStack(alignment: .leading, spacing: 10) {
                sectionHeader(L10n.TeamSectionLeadership)
                if contactsLoading && teamContacts.isEmpty {
                    PAXScreenLoadingStack(status: L10n.LoadingContacts, rowCount: 3)
                } else if teamContacts.isEmpty {
                    Text(L10n.TeamHubEmptyHint)
                        .font(.caption)
                        .foregroundStyle(PAXTheme.textSecondary)
                        .padding(.vertical, 8)
                } else {
                    ForEach(teamContacts) { member in
                        TeamHubMemberRow(
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
    private var liveAlertsSection: some View {
        let alerts = buildLiveAlerts()
        if !alerts.isEmpty {
            VStack(alignment: .leading, spacing: 10) {
                HStack {
                    sectionHeader(L10n.TeamLiveAlerts)
                    Spacer()
                    if alerts.count > 3 {
                        Text(L10n.TeamShowAll)
                            .font(.caption.weight(.medium))
                            .foregroundStyle(PAXTheme.textSecondary)
                    }
                }
                ForEach(alerts.prefix(5)) { alert in
                    TeamHubAlertRow(
                        icon: alert.icon,
                        title: alert.title,
                        subtitle: alert.subtitle,
                        timeLabel: alert.timeLabel
                    ) {
                        onOpenSession(alert.sessionId)
                    }
                }
            }
        }
    }

    @ViewBuilder
    private var conversationsSection: some View {
        if !displayedSessions.isEmpty {
            VStack(alignment: .leading, spacing: 10) {
                sectionHeader(L10n.TeamHubConversations)
                List {
                    ForEach(displayedSessions) { session in
                        conversationRow(session)
                            .listRowInsets(EdgeInsets(top: 4, leading: 0, bottom: 4, trailing: 0))
                            .listRowSeparator(.hidden)
                            .listRowBackground(Color.clear)
                    }
                }
                .listStyle(.plain)
                .scrollDisabled(true)
                .frame(height: CGFloat(displayedSessions.count) * 72)
            }
        } else if teamCoordinator.isLoading {
            PAXScreenLoadingStack(status: L10n.LoadingTeamConversations, rowCount: 2)
        }
    }

    // MARK: - Rows

    private func sectionHeader(_ title: String) -> some View {
        Text(title)
            .font(.footnote.weight(.semibold))
            .foregroundStyle(PAXTheme.textSecondary)
            .textCase(.uppercase)
            .tracking(0.6)
    }

    private func pendingRequestCard(_ session: LiveSession) -> some View {
        VStack(alignment: .leading, spacing: 12) {
            HStack(spacing: 12) {
                StaffAvatarView(name: session.displayName, size: 42)
                VStack(alignment: .leading, spacing: 3) {
                    Text(session.displayName)
                        .font(.subheadline.weight(.semibold))
                    Text(session.localizedOtherRoleLabel)
                        .font(.caption)
                        .foregroundStyle(PAXTheme.textSecondary)
                }
                Spacer()
                Text(session.requestStatusLabel)
                    .font(.caption2.weight(.medium))
                    .foregroundStyle(PAXTheme.textTertiary)
            }
            if !session.lastPreview.isEmpty {
                Text(session.lastPreview)
                    .font(.caption)
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
                Button(L10n.TeamActionAccept) {
                    Task {
                        _ = await teamCoordinator.respondToRequest(sessionId: session.sessionId, accept: true, auth: auth)
                        onOpenSession(session.sessionId)
                    }
                }
                .buttonStyle(.borderedProminent)
                .tint(PAXTheme.textPrimary)
            }
        }
        .padding(14)
        .paxPremiumGlass(tier: .standard, cornerRadius: 16)
    }

    private func conversationRow(_ session: LiveSession) -> some View {
        let isUnread = settings.isSessionUnread(session)
        return Button {
            PAXHaptics.light()
            onOpenSession(session.sessionId)
        } label: {
            HStack(spacing: 12) {
                StaffAvatarView(name: session.displayName, size: 44)
                VStack(alignment: .leading, spacing: 4) {
                    HStack {
                        Text(session.displayName)
                            .font(.subheadline.weight(isUnread ? .semibold : .medium))
                            .foregroundStyle(PAXTheme.textPrimary)
                            .lineLimit(1)
                        Spacer()
                        if let time = MessageTimeFormatter.relativeUpdatedLabel(from: session.updatedAt) {
                            Text(time)
                                .font(.caption2)
                                .foregroundStyle(PAXTheme.textTertiary)
                        }
                    }
                    Text(session.lastPreview.isEmpty ? L10n.TeamChatPlaceholder : session.lastPreview)
                        .font(.caption)
                        .foregroundStyle(isUnread ? PAXTheme.textPrimary : PAXTheme.textSecondary)
                        .lineLimit(1)
                }
                if isUnread {
                    Circle()
                        .fill(PAXTheme.textPrimary.opacity(0.85))
                        .frame(width: 7, height: 7)
                }
            }
            .padding(.horizontal, 14)
            .padding(.vertical, 12)
            .paxPremiumGlass(tier: .subtle, cornerRadius: 14)
        }
        .buttonStyle(.plain)
        .swipeActions(edge: .trailing, allowsFullSwipe: true) {
            Button(role: .destructive) {
                Task { await deleteTeamSession(session) }
            } label: {
                Label { Text(L10n.CommonDelete) } icon: { PAXIcon("trash") }
            }
        }
        .swipeActions(edge: .leading, allowsFullSwipe: true) {
            Button(role: .destructive) {
                Task { await deleteTeamSession(session) }
            } label: {
                Label { Text(L10n.CommonDelete) } icon: { PAXIcon("trash") }
            }
        }
        .opacity(deletingSessionIds.contains(session.sessionId) ? 0.45 : 1)
    }

    private func deleteTeamSession(_ session: LiveSession) async {
        guard !deletingSessionIds.contains(session.sessionId) else { return }
        deletingSessionIds.insert(session.sessionId)
        let result = await teamCoordinator.deleteConversation(sessionId: session.sessionId, mode: "hide", auth: auth)
        deletingSessionIds.remove(session.sessionId)
        if result.success {
            displayedSessions.removeAll { $0.sessionId == session.sessionId }
            settings.markSessionRead(session.sessionId, seq: session.seq)
            PAXHaptics.success()
            scheduleRecompute(immediate: true)
        }
    }

    // MARK: - Data

    private func refreshAll() async {
        await ConversationSyncCoordinator.performUnifiedFullSync(
            auth: auth,
            chatCoordinator: coordinator,
            teamCoordinator: teamCoordinator
        )
        await loadContacts(force: true)
        await teamCoordinator.refreshPendingRequests(auth: auth)
        await teamCoordinator.touchPresence(auth: auth)
        scheduleRecompute(immediate: true)
    }

    private func loadContacts(force: Bool = false) async {
        guard canComposeTeam, auth.isLoggedIn else { return }
        contactsLoading = true
        defer { contactsLoading = false }
        do {
            teamContacts = try await TeamContactsCache.shared.fetch(auth: auth, force: force)
            if force {
                contactsRevision &+= 1
            }
        } catch {
            if teamContacts.isEmpty {
                contactsRevision &+= 1
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

    private func scheduleRecompute(immediate: Bool) {
        recomputeTask?.cancel()
        let coordinatorSessions = coordinator.sessions
        let teamOnlySessions = teamCoordinator.teamSessions
        let pending = teamCoordinator.pendingRequests

        recomputeTask = Task(priority: .userInitiated) {
            if !immediate {
                try? await Task.sleep(nanoseconds: 120_000_000)
            }
            guard !Task.isCancelled else { return }

            let state = Self.computeListState(
                coordinatorSessions: coordinatorSessions,
                teamSessions: teamOnlySessions,
                pendingRequests: pending,
                settings: settings
            )

            guard !Task.isCancelled else { return }
            await MainActor.run {
                displayedSessions = state.displayedSessions
                teamSessionCount = state.teamSessionCount
                unreadCount = state.unreadCount
                waitingCount = state.waitingCount
                alertCount = state.alertCount
            }
        }
    }

    private func buildLiveAlerts() -> [TeamHubAlert] {
        var alerts: [TeamHubAlert] = []
        for session in teamCoordinator.pendingRequests {
            alerts.append(TeamHubAlert(
                id: "req-\(session.sessionId)",
                sessionId: session.sessionId,
                icon: "exclamationmark.triangle",
                title: L10n.TeamAlertPendingRequest,
                subtitle: session.displayName,
                timeLabel: MessageTimeFormatter.relativeUpdatedLabel(from: session.updatedAt) ?? "—"
            ))
        }
        let unreadSessions = displayedSessions.filter { settings.isSessionUnread($0) }
        for session in unreadSessions.prefix(4) {
            alerts.append(TeamHubAlert(
                id: "unread-\(session.sessionId)",
                sessionId: session.sessionId,
                icon: "envelope.badge",
                title: L10n.TeamAlertUnreadMessage,
                subtitle: session.lastPreview.isEmpty ? session.displayName : session.lastPreview,
                timeLabel: MessageTimeFormatter.relativeUpdatedLabel(from: session.updatedAt) ?? "—"
            ))
        }
        return alerts
    }

    private static func computeListState(
        coordinatorSessions: [LiveSession],
        teamSessions: [LiveSession],
        pendingRequests: [LiveSession],
        settings: AppSettingsStore
    ) -> TeamHubListState {
        let allSessions = TeamMessagingCoordinator.mergeTeamSessions(
            teamSessions: teamSessions,
            coordinatorSessions: coordinatorSessions
        )
        let sorted = allSessions.sorted { lhs, rhs in
            if lhs.isPinned != rhs.isPinned { return lhs.isPinned && !rhs.isPinned }
            if lhs.otherRoleRank != rhs.otherRoleRank { return lhs.otherRoleRank < rhs.otherRoleRank }
            return lhs.updatedAt > rhs.updatedAt
        }

        var seenOtherUserIds = Set<Int>()
        let deduped = sorted.filter { session in
            guard session.isTeamDM, session.otherUserId > 0 else { return true }
            if seenOtherUserIds.contains(session.otherUserId) { return false }
            seenOtherUserIds.insert(session.otherUserId)
            return true
        }

        let unread = allSessions.filter { settings.isSessionUnread($0) }.count
        let waiting = allSessions.filter { session in
            settings.isSessionUnread(session) && session.lastRole != "admin"
        }.count
        let alerts = pendingRequests.count + unread

        return TeamHubListState(
            displayedSessions: deduped,
            teamSessionCount: allSessions.count,
            unreadCount: unread,
            waitingCount: waiting,
            alertCount: alerts
        )
    }
}

private struct TeamHubListState {
    let displayedSessions: [LiveSession]
    let teamSessionCount: Int
    let unreadCount: Int
    let waitingCount: Int
    let alertCount: Int
}

private struct TeamHubAlert: Identifiable {
    let id: String
    let sessionId: String
    let icon: String
    let title: String
    let subtitle: String
    let timeLabel: String
}
