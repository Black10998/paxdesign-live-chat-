import SwiftUI

struct TeamMessagesHubView: View {
    @EnvironmentObject private var auth: AuthStore
    @EnvironmentObject private var coordinator: ChatCoordinator
    @EnvironmentObject private var teamCoordinator: TeamMessagingCoordinator
    @EnvironmentObject private var settings: AppSettingsStore
    @State private var searchText = ""
    @State private var showCompose = false
    @FocusState private var isSearchFocused: Bool
    var onOpenSession: (String) -> Void = { _ in }

    private var teamSessions: [LiveSession] {
        var items = coordinator.sessions.filter { $0.isTeamDM }
        let teamOnly = teamCoordinator.teamSessions.filter { team in
            !items.contains { $0.sessionId == team.sessionId }
        }
        items.append(contentsOf: teamOnly)
        return items.sorted { $0.updatedAt > $1.updatedAt }
    }

    private var filteredSessions: [LiveSession] {
        guard !searchText.isEmpty else { return teamSessions }
        let q = searchText.lowercased()
        return teamSessions.filter {
            $0.displayName.lowercased().contains(q) || $0.lastPreview.lowercased().contains(q)
        }
    }

    private var unreadCount: Int {
        teamSessions.filter { $0.needsReply && !settings.readSessionIds.contains($0.sessionId) }.count
    }

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

            if unreadCount > 0 || !teamSessions.isEmpty {
                Section {
                    HStack(spacing: 12) {
                        TeamStatPill(value: "\(teamSessions.count)", label: L10n.TeamHubConversations, tint: PAXBrand.accent)
                        if unreadCount > 0 {
                            TeamStatPill(value: "\(unreadCount)", label: L10n.FilterUnread, tint: .orange)
                        }
                    }
                    .listRowInsets(EdgeInsets(top: 4, leading: 0, bottom: 4, trailing: 0))
                    .listRowBackground(Color.clear)
                    .listRowSeparator(.hidden)
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

            if filteredSessions.isEmpty {
                Section {
                    teamEmptyState
                        .listRowInsets(EdgeInsets(top: 24, leading: 0, bottom: 24, trailing: 0))
                        .listRowBackground(Color.clear)
                        .listRowSeparator(.hidden)
                }
            } else {
                Section(L10n.TeamHubConversations) {
                    ForEach(filteredSessions) { session in
                        teamConversationRow(session)
                            .transition(PAXMotion.listInsert)
                    }
                }
            }
        }
        .listStyle(.insetGrouped)
        .scrollContentBackground(.hidden)
        .background(PAXBackground())
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
        .refreshable {
            await coordinator.refreshSessions(auth: auth)
            await teamCoordinator.refresh(auth: auth)
        }
    }

    private func teamConversationRow(_ session: LiveSession) -> some View {
        let isUnread = session.needsReply && !settings.readSessionIds.contains(session.sessionId)

        return NavigationLink(value: session.sessionId) {
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
            .padding(.vertical, 4)
        }
        .simultaneousGesture(TapGesture().onEnded {
            PAXHaptics.light()
            isSearchFocused = false
            PAXKeyboard.dismiss()
            settings.readSessionIds.insert(session.sessionId)
            coordinator.activeSessionId = session.sessionId
        })
        .listRowBackground(
            RoundedRectangle(cornerRadius: 14, style: .continuous)
                .fill(PAXBrand.accent.opacity(isUnread ? 0.08 : 0.04))
                .padding(.vertical, 2)
        )
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
