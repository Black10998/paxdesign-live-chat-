import SwiftUI

struct SessionListView: View {
    @EnvironmentObject private var auth: AuthStore
    @EnvironmentObject private var coordinator: ChatCoordinator
    @EnvironmentObject private var teamCoordinator: TeamMessagingCoordinator
    @EnvironmentObject private var settings: AppSettingsStore
    @State private var searchText = ""
    @State private var filter: SessionFilter = .all
    @State private var displayedSessions: [LiveSession] = []
    @State private var recomputeTask: Task<Void, Never>?
    @FocusState private var isSearchFocused: Bool
    var onOpenSession: (String) -> Void = { _ in }

    private enum SessionFilter: CaseIterable, Hashable {
        case all, live, unread, active, closed

        var title: String {
            switch self {
            case .all: return L10n.FilterAll
            case .live: return L10n.FilterLive
            case .unread: return L10n.FilterUnread
            case .active: return L10n.FilterActive
            case .closed: return L10n.FilterClosed
            }
        }
    }

    private func scheduleDisplayedSessionsRecompute(immediate: Bool) {
        recomputeTask?.cancel()

        let sessions = coordinator.sessions
        let readIds = settings.readSessionIds
        let activeFilter = filter
        let trimmedSearch = searchText.trimmingCharacters(in: .whitespacesAndNewlines).lowercased()

        recomputeTask = Task(priority: .userInitiated) {
            if !immediate {
                try? await Task.sleep(nanoseconds: 120_000_000)
            }
            guard !Task.isCancelled else { return }

            let result = Self.computeDisplayedSessions(
                sessions: sessions,
                filter: activeFilter,
                searchText: trimmedSearch,
                readSessionIds: readIds
            )

            guard !Task.isCancelled else { return }
            await MainActor.run {
                if displayedSessions != result {
                    displayedSessions = result
                }
            }
        }
    }

    private static func computeDisplayedSessions(
        sessions: [LiveSession],
        filter: SessionFilter,
        searchText: String,
        readSessionIds: Set<String>
    ) -> [LiveSession] {
        var items = sessions
            .filter { !$0.isTeamDM }
            .sorted { $0.updatedAt > $1.updatedAt }

        if !searchText.isEmpty {
            items = items.filter {
                $0.displayName.lowercased().contains(searchText)
                    || $0.detectedService.lowercased().contains(searchText)
                    || $0.lastPreview.lowercased().contains(searchText)
            }
        }

        switch filter {
        case .all:
            break
        case .live:
            items = items.filter(\.isLiveRequest)
        case .unread:
            items = items.filter { $0.needsReply && !readSessionIds.contains($0.sessionId) }
        case .active:
            items = items.filter { $0.isAdmin || $0.isLiveRequest }
        case .closed:
            items = items.filter(\.isClosed)
        }

        return items
    }

    private var canViewChats: Bool { auth.canViewChats }
    private var canReplyChats: Bool { auth.canReplyChats }
    private var canViewRatings: Bool { auth.canViewRatings }

    var body: some View {
        Group {
            if canViewChats {
                sessionListContent
            } else {
                noAccessView
            }
        }
        .paxScreenBackground()
        .navigationTitle(L10n.SessionTitle)
        .navigationBarTitleDisplayMode(.large)
        .onAppear { scheduleDisplayedSessionsRecompute(immediate: true) }
        .onDisappear {
            recomputeTask?.cancel()
            recomputeTask = nil
        }
        .onChange(of: coordinator.sessions) { _ in scheduleDisplayedSessionsRecompute(immediate: true) }
        .onChange(of: searchText) { _ in scheduleDisplayedSessionsRecompute(immediate: false) }
        .onChange(of: filter) { _ in scheduleDisplayedSessionsRecompute(immediate: true) }
        .onChange(of: settings.readSessionIds) { _ in scheduleDisplayedSessionsRecompute(immediate: true) }
    }

    private var sessionListContent: some View {
        List {
            if let error = coordinator.errorMessage, !error.isEmpty {
                Section {
                    syncErrorBanner(error)
                        .listRowInsets(EdgeInsets())
                        .listRowBackground(Color.clear)
                        .listRowSeparator(.hidden)
                }
            }

            Section {
                searchAndFilters
                    .listRowInsets(EdgeInsets(top: 4, leading: 16, bottom: 4, trailing: 16))
                    .listRowBackground(Color.clear)
                    .listRowSeparator(.hidden)
            }

            if coordinator.liveCount > 0 {
                Section {
                    liveBanner
                        .listRowInsets(EdgeInsets(top: 4, leading: 16, bottom: 4, trailing: 16))
                        .listRowBackground(Color.clear)
                        .listRowSeparator(.hidden)
                }
            }

            if displayedSessions.isEmpty {
                Section {
                    if coordinator.isSyncing && searchText.trimmingCharacters(in: .whitespacesAndNewlines).isEmpty {
                        loadingState
                            .listRowInsets(EdgeInsets(top: 14, leading: 16, bottom: 14, trailing: 16))
                    } else {
                        emptyState
                            .listRowInsets(EdgeInsets(top: 32, leading: 16, bottom: 32, trailing: 16))
                    }
                }
                .listRowBackground(Color.clear)
                .listRowSeparator(.hidden)
            } else {
                Section {
                    ForEach(displayedSessions) { session in
                        let isUnread = session.needsReply && !settings.readSessionIds.contains(session.sessionId)

                        Button {
                            isSearchFocused = false
                            PAXKeyboard.dismiss()
                            onOpenSession(session.sessionId)
                            PAXHaptics.light()
                        } label: {
                            SessionRow(
                                session: session,
                                isUnread: isUnread,
                                showRating: canViewRatings,
                                showTimestamp: settings.showListTimestamps,
                                compact: settings.compactListMode
                            )
                        }
                        .buttonStyle(.plain)
                        .opacity(session.isClosed ? 0.55 : 1)
                        .onAppear {
                            if let api = auth.api {
                                ConversationPrefetcher.shared.schedulePrefetch(
                                    sessionId: session.sessionId,
                                    api: api,
                                    isTeam: session.isTeamDM
                                )
                            }
                        }
                        .listRowInsets(EdgeInsets(top: 2, leading: 0, bottom: 2, trailing: 0))
                        .listRowBackground(Color.clear)
                        .listRowSeparator(.hidden)
                        .contextMenu {
                            Button {
                                onOpenSession(session.sessionId)
                            } label: {
                                Label(L10n.CommonOpen, systemImage: "arrow.up.right.circle")
                            }
                            if isUnread {
                                Button {
                                    settings.markSessionRead(session.sessionId)
                                    PAXHaptics.light()
                                } label: {
                                    Label(L10n.CommonMarkRead, systemImage: "envelope.open")
                                }
                            } else {
                                Button {
                                    settings.markSessionUnread(session.sessionId)
                                    PAXHaptics.light()
                                } label: {
                                    Label(L10n.CommonMarkUnread, systemImage: "envelope.badge")
                                }
                            }
                            if canReplyChats {
                                Button {
                                    PAXHaptics.light()
                                    Task { await coordinator.archiveSession(auth: auth, session: session) }
                                } label: {
                                    Label(L10n.CommonArchive, systemImage: "archivebox")
                                }
                                Button {
                                    requestDeleteSession(session)
                                } label: {
                                    Label(L10n.CommonDelete, systemImage: "trash")
                                }
                            }
                        }
                        .swipeActions(edge: .leading, allowsFullSwipe: true) {
                            if isUnread {
                                Button {
                                    settings.markSessionRead(session.sessionId)
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
                        .swipeActions(edge: .trailing, allowsFullSwipe: false) {
                            if canReplyChats {
                                Button {
                                    requestDeleteSession(session)
                                } label: {
                                    Label(L10n.CommonDelete, systemImage: "trash")
                                }
                                .tint(.red)

                                Button {
                                    PAXHaptics.light()
                                    Task { await coordinator.archiveSession(auth: auth, session: session) }
                                } label: {
                                    Label(L10n.CommonArchive, systemImage: "archivebox")
                                }
                                .tint(.orange)
                            }
                        }
                    }
                }
            }
        }
        .listStyle(.plain)
        .scrollContentBackground(.hidden)
        .scrollDismissesKeyboard(.interactively)
        .paxPremiumRefreshable(status: "Unterhaltungen werden geladen", rowCount: 5) {
            await coordinator.fullConversationSync(auth: auth)
            await teamCoordinator.fullConversationSync(auth: auth)
        }
    }

    private func requestDeleteSession(_ session: LiveSession) {
        PAXDelete.confirm(
            message: "Diese Unterhaltung wird dauerhaft gelöscht.",
            itemTitle: session.displayName
        ) {
            Task { await coordinator.deleteSession(auth: auth, session: session) }
        }
    }

    private var noAccessView: some View {
        VStack(spacing: 14) {
            Image(systemName: "lock.fill")
                .font(.system(size: 42))
                .foregroundStyle(PAXTheme.textTertiary)
            Text(L10n.SessionNoAccessTitle)
                .font(.title3.weight(.semibold))
            Text(L10n.SessionNoAccessMessage)
                .font(.subheadline)
                .foregroundStyle(PAXTheme.textSecondary)
                .multilineTextAlignment(.center)
                .padding(.horizontal, 24)
        }
        .frame(maxWidth: .infinity, maxHeight: .infinity)
    }

    private var searchAndFilters: some View {
        VStack(alignment: .leading, spacing: 10) {
            PAXNativeSearchField(
                text: $searchText,
                prompt: L10n.SearchPrompt,
                isFocused: $isSearchFocused
            )

            filterChips
        }
    }

    private var filterChips: some View {
        ScrollView(.horizontal, showsIndicators: false) {
            HStack(spacing: 8) {
                ForEach(availableFilters, id: \.self) { item in
                    Button {
                        filter = item
                        PAXHaptics.light()
                    } label: {
                        Text(item.title)
                            .font(.caption.weight(.semibold))
                            .padding(.horizontal, 12)
                            .padding(.vertical, 7)
                            .background(
                                Capsule()
                                    .fill(filter == item ? PAXBrand.accent.opacity(0.18) : PAXTheme.surface.opacity(0.68))
                            )
                            .overlay(Capsule().stroke(filter == item ? PAXBrand.accent.opacity(0.5) : PAXTheme.border.opacity(0.55), lineWidth: 1))
                            .foregroundStyle(filter == item ? PAXTheme.textPrimary : PAXTheme.textSecondary)
                    }
                    .buttonStyle(.plain)
                }
            }
        }
    }

    private var availableFilters: [SessionFilter] {
        SessionFilter.allCases
    }

    private func syncErrorBanner(_ message: String) -> some View {
        HStack(alignment: .top, spacing: 10) {
            Image(systemName: "exclamationmark.triangle.fill")
                .foregroundStyle(.orange)
            VStack(alignment: .leading, spacing: 4) {
                Text(L10n.SessionSyncFailed)
                    .font(.subheadline.weight(.semibold))
                Text(message)
                    .font(.caption)
                    .foregroundStyle(PAXTheme.textSecondary)
            }
            Spacer()
            Button(L10n.CommonRetry) {
                Task { await coordinator.refreshSessions(auth: auth) }
            }
            .font(.caption.weight(.semibold))
        }
        .padding(14)
        .background(
            RoundedRectangle(cornerRadius: 14, style: .continuous)
                .fill(Color.orange.opacity(0.12))
        )
    }

    private var liveBanner: some View {
        HStack(spacing: 12) {
            Image(systemName: "bell.and.waves.left.and.right.fill")
                .font(.title3)
                .foregroundStyle(PAXTheme.accent)

            VStack(alignment: .leading, spacing: 2) {
                Text(L10n.SessionLiveRequests(coordinator.liveCount))
                    .font(.subheadline.weight(.semibold))
                Text(L10n.SessionLiveWaiting)
                    .font(.caption)
                    .foregroundStyle(PAXTheme.textSecondary)
            }
            Spacer()
        }
        .padding(.horizontal, 14)
        .padding(.vertical, 12)
        .background(
            RoundedRectangle(cornerRadius: 14, style: .continuous)
                .fill(PAXTheme.accentSoft)
        )
    }

    private var emptyState: some View {
        VStack(spacing: 14) {
            Image(systemName: "bubble.left.and.bubble.right")
                .font(.system(size: 40, weight: .light))
                .foregroundStyle(PAXTheme.textTertiary)
            Text(L10n.SessionNoChats)
                .font(.headline)
            Text(L10n.SessionNoChatsHint)
                .font(.subheadline)
                .foregroundStyle(PAXTheme.textSecondary)
                .multilineTextAlignment(.center)
        }
        .frame(maxWidth: .infinity)
        .padding(.horizontal, 16)
        .padding(.vertical, 22)
        .paxGlassCardStyle(cornerRadius: 20, fillOpacity: 0.78, borderOpacity: 0.5, shadowOpacity: 0.18)
    }

    private var loadingState: some View {
        PAXScreenLoadingStack(status: "Unterhaltungen werden geladen", rowCount: 5)
    }
}

private struct SessionRow: View {
    let session: LiveSession
    var isUnread: Bool = false
    var showRating: Bool = true
    var showTimestamp: Bool = true
    var compact: Bool = false

    private var avatarSize: CGFloat { compact ? 44 : 52 }
    private var rowPadding: CGFloat { compact ? 10 : 12 }

    var body: some View {
        PAXListCard(highlighted: isUnread, accent: PAXBrand.accent) {
            HStack(alignment: .center, spacing: 14) {
                SessionAvatarView(
                    name: session.displayName,
                    size: avatarSize,
                    isLive: session.isLiveRequest,
                    isTeam: session.isTeamDM
                )

                VStack(alignment: .leading, spacing: compact ? 3 : 5) {
                    HStack(alignment: .firstTextBaseline, spacing: 8) {
                        Text(session.displayName)
                            .font(.body.weight(isUnread ? .semibold : .regular))
                            .foregroundStyle(PAXTheme.textPrimary)
                            .lineLimit(1)

                        Spacer(minLength: 4)

                        if showTimestamp, let time = MessageTimeFormatter.relativeUpdatedLabel(from: session.updatedAt) {
                            Text(time)
                                .font(.caption)
                                .foregroundStyle(isUnread ? PAXBrand.accent : PAXTheme.textTertiary)
                                .lineLimit(1)
                        }
                    }

                    HStack(alignment: .center, spacing: 8) {
                        if session.isLiveRequest {
                            liveIndicator
                        } else if session.isTeamDM {
                            teamBadge
                        } else if showRating, let rating = SessionRatingBadge(rating: session.sessionRating) {
                            rating
                        }

                        Text(previewText)
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
        }
        .padding(.horizontal, 12)
        .padding(.vertical, 3)
        .contentShape(Rectangle())
    }

    private var previewText: String {
        if !session.lastPreview.isEmpty { return session.lastPreview }
        if session.isTeamDM { return L10n.TeamChatPlaceholder }
        if !session.detectedService.isEmpty { return session.detectedService }
        return "—"
    }

    private var liveIndicator: some View {
        Text(L10n.FilterLive)
            .font(.caption2.weight(.bold))
            .foregroundStyle(.white)
            .padding(.horizontal, 6)
            .padding(.vertical, 2)
            .background(Capsule().fill(PAXTheme.accent))
    }

    private var teamBadge: some View {
        Text(L10n.SessionTeamBadge)
            .font(.caption2.weight(.bold))
            .foregroundStyle(PAXBrand.accent)
            .padding(.horizontal, 6)
            .padding(.vertical, 2)
            .background(Capsule().fill(PAXBrand.accent.opacity(0.15)))
    }
}

private extension L10n {
    static func SessionLiveRequests(_ count: Int) -> String {
        String(format: String(localized: "session.live_requests"), count)
    }
}
