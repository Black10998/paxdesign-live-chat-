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
    @FocusState private var isSearchFocused: Bool
    var onOpenSession: (String) -> Void = { _ in }

    private var canManageTeam: Bool { auth.canManageUsers }

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
            if canManageTeam {
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
        }
        .onAppear { scheduleRecompute(immediate: true) }
        .onDisappear {
            recomputeTask?.cancel()
            recomputeTask = nil
        }
        .onChange(of: coordinator.sessions) { _ in scheduleRecompute(immediate: true) }
        .onChange(of: teamCoordinator.teamSessions) { _ in scheduleRecompute(immediate: true) }
        .onChange(of: searchText) { _ in scheduleRecompute(immediate: false) }
        .onChange(of: settings.readSessionIds) { _ in scheduleRecompute(immediate: true) }
    }

    private var teamLoadingState: some View {
        PAXScreenLoadingStack(status: "Team-Unterhaltungen werden geladen", rowCount: 4)
    }

    private func teamConversationRow(_ session: LiveSession) -> some View {
        let isUnread = session.needsReply && !settings.readSessionIds.contains(session.sessionId)

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
                settings.readSessionIds.insert(session.sessionId)
                PAXHaptics.light()
            } label: {
                Label(L10n.CommonMarkRead, systemImage: "envelope.open")
            }
            Button {
                settings.readSessionIds.remove(session.sessionId)
                PAXHaptics.light()
            } label: {
                Label(L10n.CommonMarkUnread, systemImage: "envelope.badge")
            }
        }
        .swipeActions(edge: .leading, allowsFullSwipe: true) {
            if isUnread {
                Button {
                    settings.readSessionIds.insert(session.sessionId)
                    PAXHaptics.success()
                } label: {
                    Label(L10n.CommonMarkRead, systemImage: "envelope.open")
                }
                .tint(.blue)
            } else {
                Button {
                    settings.readSessionIds.remove(session.sessionId)
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
        let readIds = settings.readSessionIds
        let query = searchText.trimmingCharacters(in: .whitespacesAndNewlines).lowercased()

        recomputeTask = Task(priority: .userInitiated) {
            if !immediate {
                try? await Task.sleep(nanoseconds: 120_000_000)
            }
            guard !Task.isCancelled else { return }

            let state = Self.computeListState(
                coordinatorSessions: coordinatorSessions,
                teamSessions: teamOnlySessions,
                readSessionIds: readIds,
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
        readSessionIds: Set<String>,
        searchText: String
    ) -> TeamHubListState {
        var merged: [String: LiveSession] = [:]
        for session in coordinatorSessions where session.isTeamDM {
            merged[session.sessionId] = session
        }
        for session in teamSessions {
            if let existing = merged[session.sessionId] {
                merged[session.sessionId] = existing.updatedAt >= session.updatedAt ? existing : session
            } else {
                merged[session.sessionId] = session
            }
        }

        let allSessions = merged.values.sorted { $0.updatedAt > $1.updatedAt }
        let filtered: [LiveSession]
        if searchText.isEmpty {
            filtered = allSessions
        } else {
            filtered = allSessions.filter {
                $0.displayName.lowercased().contains(searchText)
                    || $0.lastPreview.lowercased().contains(searchText)
            }
        }

        let unread = allSessions.filter { $0.needsReply && !readSessionIds.contains($0.sessionId) }.count
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
            if canManageTeam {
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

    var body: some View {
        VStack(spacing: 4) {
            Text(value)
                .font(.title2.weight(.bold))
                .foregroundStyle(tint)
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
                .fill(tint.opacity(0.1))
        )
    }
}
