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
            Section {
                PlatformHeroHeader(
                    title: L10n.TeamHubTitle,
                    subtitle: L10n.TeamHubSubtitle,
                    systemImage: "person.3.fill",
                    gradient: [PAXBrand.accent, PAXBrand.accent.opacity(0.65)]
                )
                .listRowInsets(EdgeInsets(top: 8, leading: 0, bottom: 8, trailing: 0))
                .listRowBackground(Color.clear)
                .listRowSeparator(.hidden)
            }

            if unreadCount > 0 || teamSessionCount > 0 {
                Section {
                    HStack(spacing: 12) {
                        TeamStatPill(value: "\(teamSessionCount)", label: L10n.TeamHubConversations, tint: PAXBrand.accent)
                        if unreadCount > 0 {
                            TeamStatPill(value: "\(unreadCount)", label: L10n.FilterUnread, tint: .orange)
                        }
                    }
                    .listRowInsets(EdgeInsets(top: 4, leading: 0, bottom: 4, trailing: 0))
                    .listRowBackground(Color.clear)
                    .listRowSeparator(.hidden)
                }
            }

            if canComposeTeam {
                Section {
                    HStack {
                        Text("Start a conversation")
                            .font(.headline)
                        Spacer()
                        Button {
                            showCompose = true
                        } label: {
                            Label(L10n.TeamNewMessage, systemImage: "square.and.pencil")
                                .font(.subheadline.weight(.semibold))
                        }
                    }
                    .listRowBackground(Color.clear)
                }

                if contactsLoading && teamContacts.isEmpty {
                    Section {
                        PAXScreenLoadingStack(status: "Contacts werden geladen", rowCount: 2)
                    }
                } else if !teamContacts.isEmpty {
                    Section("Leadership & Team") {
                        ForEach(teamContacts) { member in
                            TeamContactRow(
                                member: member,
                                revealFullEmail: auth.profile?.isSuperAdmin == true,
                                isOpening: openingContactId == member.userId
                            ) {
                                Task { await openContact(member) }
                            }
                        }
                    }
                }
            }

            if let error = teamCoordinator.errorMessage, !error.isEmpty {
                Section {
                    Text(error)
                        .font(.caption)
                        .foregroundStyle(PAXTheme.danger)
                }
            }

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
        .listStyle(.insetGrouped)
        .scrollContentBackground(.hidden)
        .paxScreenBackground()
        .navigationTitle(L10n.TeamHubTitle)
        .navigationBarTitleDisplayMode(.large)
        .toolbar {
            if canComposeTeam {
                ToolbarItem(placement: .topBarTrailing) {
                    Button {
                        PAXHaptics.light()
                        showCompose = true
                    } label: {
                        Image(systemName: "square.and.pencil")
                            .font(.body.weight(.semibold))
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
        .paxPremiumRefreshable(status: "Team-Unterhaltungen werden geladen", rowCount: 4) {
            await coordinator.refreshSessions(auth: auth)
            await teamCoordinator.refresh(auth: auth)
            await loadContacts()
        }
        .onAppear {
            scheduleRecompute(immediate: true)
            Task { await loadContacts() }
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
    }

    private var teamLoadingState: some View {
        PAXScreenLoadingStack(status: "Team-Unterhaltungen werden geladen", rowCount: 4)
    }

    private func loadContacts() async {
        guard canComposeTeam, let api = auth.api else { return }
        contactsLoading = true
        defer { contactsLoading = false }
        if let response = try? await api.fetchTeamContacts() {
            let currentId = auth.profile?.userId ?? 0
            teamContacts = response.staff.filter { $0.userId != currentId && $0.enabled }
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
                    Image(systemName: "person.3.fill")
                        .font(.system(size: 9, weight: .bold))
                        .foregroundStyle(.white)
                        .padding(3)
                        .background(Circle().fill(PAXBrand.accent))
                        .offset(x: 3, y: 3)
                }

                VStack(alignment: .leading, spacing: 5) {
                    HStack {
                        Text(session.displayName)
                            .font(.body.weight(isUnread ? .bold : .semibold))
                            .foregroundStyle(PAXTheme.textPrimary)
                            .lineLimit(1)
                        Spacer(minLength: 4)
                        if let time = MessageTimeFormatter.relativeUpdatedLabel(from: session.updatedAt) {
                            Text(time)
                                .font(.caption)
                                .foregroundStyle(isUnread ? PAXBrand.accent : PAXTheme.textTertiary)
                        }
                    }

                    HStack(spacing: 8) {
                        Text(L10n.SessionTeamBadge)
                            .font(.caption2.weight(.bold))
                            .foregroundStyle(PAXBrand.accent)
                            .padding(.horizontal, 6)
                            .padding(.vertical, 2)
                            .background(Capsule().fill(PAXBrand.accent.opacity(0.15)))

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
        .listRowInsets(EdgeInsets(top: 2, leading: 0, bottom: 2, trailing: 0))
        .listRowSeparator(.hidden)
        .listRowBackground(Color.clear)
        .contextMenu {
            Button {
                settings.markSessionRead(session.sessionId, seq: session.seq)
                PAXHaptics.light()
            } label: {
                Label(L10n.CommonMarkRead, systemImage: "envelope.open")
            }
            Button {
                settings.markSessionUnread(session.sessionId)
                PAXHaptics.light()
            } label: {
                Label(L10n.CommonMarkUnread, systemImage: "envelope.badge")
            }
            Button(role: .destructive) {
                Task { await deleteConversation(session.sessionId, mode: "hide") }
            } label: {
                Label("Remove from my list", systemImage: "eye.slash")
            }
        }
        .swipeActions(edge: .leading, allowsFullSwipe: true) {
            if isUnread {
                Button {
                    settings.markSessionRead(session.sessionId, seq: session.seq)
                    PAXHaptics.success()
                } label: {
                    Label(L10n.CommonMarkRead, systemImage: "envelope.open")
                }
                .tint(.blue)
            } else {
                Button {
                    settings.markSessionUnread(session.sessionId)
                    PAXHaptics.light()
                } label: {
                    Label(L10n.CommonMarkUnread, systemImage: "envelope.badge")
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
            }
        }

        let unread = allSessions.filter { settings.isSessionUnread($0) }.count
        return TeamHubListState(displayedSessions: filtered, teamSessionCount: allSessions.count, unreadCount: unread)
    }

    private var teamEmptyState: some View {
        VStack(spacing: 16) {
            Image(systemName: "person.3.sequence.fill")
                .font(.system(size: 48, weight: .light))
                .foregroundStyle(PAXBrand.accent.opacity(0.7))
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
                    Label(L10n.TeamNewMessage, systemImage: "square.and.pencil")
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
    let revealFullEmail: Bool
    let isOpening: Bool
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
                    if !member.profileTitle.orEmpty.isEmpty {
                        Text(member.profileTitle ?? "")
                            .font(.caption)
                            .foregroundStyle(PAXTheme.textSecondary)
                    } else {
                        Text(PrivacyMask.email(member.email, revealFull: revealFullEmail))
                            .font(.caption)
                            .foregroundStyle(PAXTheme.textSecondary)
                    }
                }
                Spacer()
                if isOpening {
                    ProgressView()
                } else {
                    Image(systemName: "message.fill")
                        .foregroundStyle(roleTint)
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
