import SwiftUI

struct SessionListView: View {
    @EnvironmentObject private var auth: AuthStore
    @EnvironmentObject private var coordinator: ChatCoordinator
    @StateObject private var settings = AppSettingsStore.shared
    @State private var searchText = ""
    @State private var filter: SessionFilter = .all
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

    private var filteredSessions: [LiveSession] {
        var items = coordinator.sessions
        if !searchText.isEmpty {
            let q = searchText.lowercased()
            items = items.filter {
                $0.displayName.lowercased().contains(q)
                    || $0.detectedService.lowercased().contains(q)
                    || $0.lastPreview.lowercased().contains(q)
            }
        }
        switch filter {
        case .all: break
        case .live: items = items.filter { $0.isLiveRequest }
        case .unread: items = items.filter { $0.needsReply && !settings.readSessionIds.contains($0.sessionId) }
        case .active: items = items.filter { $0.isAdmin || $0.isLiveRequest }
        case .closed: items = items.filter { $0.isClosed }
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
        .background(PAXBackground())
        .navigationTitle(L10n.SessionTitle)
        .navigationBarTitleDisplayMode(.large)
        .searchable(text: $searchText, placement: .navigationBarDrawer(displayMode: .always), prompt: L10n.SearchPrompt)
        .scrollDismissesKeyboard(.interactively)
    }

    private var sessionListContent: some View {
        List {
            if let error = coordinator.errorMessage, !error.isEmpty {
                Section {
                    syncErrorBanner(error)
                        .listRowInsets(EdgeInsets(top: 0, leading: 0, bottom: 8, trailing: 0))
                        .listRowBackground(Color.clear)
                        .listRowSeparator(.hidden)
                }
            }

            Section {
                filterChips
                    .listRowInsets(EdgeInsets(top: 4, leading: 0, bottom: 8, trailing: 0))
                    .listRowBackground(Color.clear)
                    .listRowSeparator(.hidden)
            }

            Section {
                header
                    .listRowInsets(EdgeInsets(top: 8, leading: 0, bottom: 8, trailing: 0))
                    .listRowBackground(Color.clear)
                    .listRowSeparator(.hidden)
            }

            if coordinator.liveCount > 0 {
                Section {
                    liveBanner
                        .listRowInsets(EdgeInsets())
                        .listRowBackground(Color.clear)
                        .listRowSeparator(.hidden)
                }
            }

            if filteredSessions.isEmpty {
                Section {
                    emptyState
                        .listRowInsets(EdgeInsets(top: 24, leading: 0, bottom: 24, trailing: 0))
                        .listRowBackground(Color.clear)
                        .listRowSeparator(.hidden)
                }
            } else {
                Section {
                    ForEach(filteredSessions) { session in
                        Button {
                            PAXHaptics.light()
                            coordinator.activeSessionId = session.sessionId
                            onOpenSession(session.sessionId)
                        } label: {
                            SessionCard(
                                session: session,
                                isUnread: session.needsReply && !settings.readSessionIds.contains(session.sessionId),
                                showRating: canViewRatings
                            )
                        }
                        .buttonStyle(PAXPressButtonStyle())
                        .opacity(session.isClosed ? 0.56 : 1)
                        .listRowInsets(EdgeInsets(top: 5, leading: 0, bottom: 5, trailing: 0))
                        .listRowBackground(Color.clear)
                        .listRowSeparator(.hidden)
                        .swipeActions(edge: .trailing, allowsFullSwipe: true) {
                            if canReplyChats {
                                Button(role: .destructive) {
                                    PAXHaptics.warning()
                                    Task { await coordinator.deleteSession(auth: auth, session: session) }
                                } label: {
                                    Label(L10n.CommonDelete, systemImage: "trash")
                                }

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
        .refreshable { await coordinator.refreshSessions(auth: auth) }
        .onAppear {
            coordinator.start(auth: auth)
            Task { await coordinator.refreshSessions(auth: auth) }
        }
        .animation(PAXTheme.spring, value: coordinator.listRevision)
        .animation(PAXTheme.spring, value: coordinator.liveCount)
        .animation(PAXTheme.spring, value: coordinator.sessions.count)
        .animation(PAXTheme.spring, value: filter)
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

    private var filterChips: some View {
        ScrollView(.horizontal, showsIndicators: false) {
            HStack(spacing: 8) {
                ForEach(SessionFilter.allCases, id: \.self) { item in
                    Button {
                        filter = item
                        PAXHaptics.light()
                    } label: {
                        Text(item.title)
                            .font(.caption.weight(.semibold))
                            .padding(.horizontal, 12)
                            .padding(.vertical, 7)
                            .background(Capsule().fill(filter == item ? PAXTheme.accentSoft : PAXTheme.surface))
                            .overlay(Capsule().stroke(filter == item ? PAXTheme.accent.opacity(0.4) : PAXTheme.border, lineWidth: 1))
                    }
                    .buttonStyle(.plain)
                }
            }
        }
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
            RoundedRectangle(cornerRadius: 16, style: .continuous)
                .fill(Color.orange.opacity(0.12))
        )
    }

    private var header: some View {
        PAXGlassCard {
            HStack(spacing: 14) {
                ProfileAvatarView(size: 52)

                VStack(alignment: .leading, spacing: 4) {
                    Text(auth.profile?.name ?? L10n.CommonAdministrator)
                        .font(.headline)
                    Text(auth.profile?.displayEmail ?? PrivacyMask.email(auth.username, revealFull: false))
                        .font(.caption)
                        .foregroundStyle(PAXTheme.textSecondary)
                }

                Spacer()

                VStack(alignment: .trailing, spacing: 4) {
                    Text("\(coordinator.sessions.count)")
                        .font(.title3.weight(.bold))
                    Text(L10n.CommonActive)
                        .font(.caption2.weight(.semibold))
                        .foregroundStyle(PAXTheme.textSecondary)
                }
            }
        }
    }

    private var liveBanner: some View {
        HStack(spacing: 12) {
            Image(systemName: "bell.and.waves.left.and.right.fill")
                .font(.title3)
                .foregroundStyle(PAXTheme.accent)
                .scaleEffect(1.0)
                .animation(.easeInOut(duration: 0.9).repeatForever(autoreverses: true), value: coordinator.liveCount)

            VStack(alignment: .leading, spacing: 2) {
                Text(L10n.SessionLiveRequests(coordinator.liveCount))
                    .font(.headline)
                Text(L10n.SessionLiveWaiting)
                    .font(.caption)
                    .foregroundStyle(PAXTheme.textSecondary)
            }
            Spacer()
        }
        .padding(16)
        .background(
            RoundedRectangle(cornerRadius: 18, style: .continuous)
                .fill(PAXTheme.accentSoft)
                .overlay(
                    RoundedRectangle(cornerRadius: 18, style: .continuous)
                        .stroke(PAXTheme.accent.opacity(0.25), lineWidth: 1)
                )
        )
    }

    private var emptyState: some View {
        VStack(spacing: 14) {
            Image(systemName: "bubble.left.and.bubble.right.fill")
                .font(.system(size: 42))
                .foregroundStyle(PAXTheme.textTertiary)
            Text(L10n.SessionNoChats)
                .font(.title3.weight(.semibold))
            Text(L10n.SessionNoChatsHint)
                .font(.subheadline)
                .foregroundStyle(PAXTheme.textSecondary)
                .multilineTextAlignment(.center)
        }
        .frame(maxWidth: .infinity)
    }
}

private struct SessionCard: View {
    let session: LiveSession
    var isUnread: Bool = false
    var showRating: Bool = true

    var body: some View {
        HStack(spacing: 14) {
            ZStack(alignment: .topTrailing) {
                ZStack {
                    Circle()
                        .fill(statusColor.opacity(0.14))
                        .frame(width: 44, height: 44)
                    Image(systemName: session.isLiveRequest ? "person.wave.2.fill" : "person.fill")
                        .foregroundStyle(statusColor)
                }
                if isUnread {
                    Circle()
                        .fill(PAXTheme.accent)
                        .frame(width: 10, height: 10)
                        .offset(x: 2, y: -2)
                }
            }

            VStack(alignment: .leading, spacing: 6) {
                HStack {
                    Text(session.displayName)
                        .font(.headline)
                        .foregroundStyle(PAXTheme.textPrimary)
                    Spacer()
                    if showRating, let rating = SessionRatingBadge(rating: session.sessionRating) {
                        rating
                    }
                    PAXStatusBadge(text: SessionHandlerLocalization.label(handler: session.handler), color: statusColor)
                }

                if !session.detectedService.isEmpty {
                    Text(session.detectedService)
                        .font(.caption.weight(.medium))
                        .foregroundStyle(PAXTheme.textSecondary)
                }

                if !session.lastPreview.isEmpty {
                    Text(session.lastPreview)
                        .font(.subheadline)
                        .foregroundStyle(PAXTheme.textTertiary)
                        .lineLimit(2)
                }
            }

            Image(systemName: "chevron.right")
                .font(.caption.weight(.semibold))
                .foregroundStyle(PAXTheme.textTertiary)
                .flipsForRightToLeftLayoutDirection(true)
        }
        .padding(14)
        .background(
            RoundedRectangle(cornerRadius: 20, style: .continuous)
                .fill(session.isClosed ? PAXTheme.surface.opacity(0.72) : PAXTheme.surface.opacity(0.94))
                .overlay(
                    RoundedRectangle(cornerRadius: 20, style: .continuous)
                        .stroke(session.isClosed ? PAXTheme.border.opacity(0.6) : PAXTheme.border, lineWidth: 1)
                )
        )
    }

    private var statusColor: Color {
        if session.isLiveRequest { return PAXTheme.accent }
        if session.isAdmin { return PAXTheme.success }
        if session.isClosed { return .gray }
        return .blue
    }
}

private extension L10n {
    static func SessionLiveRequests(_ count: Int) -> String {
        String(format: String(localized: "session.live_requests"), count)
    }
}
