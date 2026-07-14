import SwiftUI

struct ArchivedSessionsView: View {
    @EnvironmentObject private var auth: AuthStore
    @EnvironmentObject private var coordinator: ChatCoordinator
    @EnvironmentObject private var settings: AppSettingsStore

    var onOpenSession: (String) -> Void = { _ in }

    private var archivedSessions: [LiveSession] {
        coordinator.sessions
            .filter { !$0.isTeamDM && settings.isSessionArchived($0.sessionId) }
            .sorted { $0.updatedAt > $1.updatedAt }
    }

    var body: some View {
        Group {
            if archivedSessions.isEmpty {
                emptyState
            } else {
                List {
                    ForEach(archivedSessions) { session in
                        let isUnread = settings.isSessionUnread(session)

                        Button {
                            onOpenSession(session.sessionId)
                            PAXHaptics.light()
                        } label: {
                            SessionArchivedRow(session: session, isUnread: isUnread)
                        }
                        .buttonStyle(.plain)
                        .listRowInsets(EdgeInsets(top: 2, leading: 0, bottom: 2, trailing: 0))
                        .listRowBackground(Color.clear)
                        .listRowSeparator(.hidden)
                        .swipeActions(edge: .leading, allowsFullSwipe: true) {
                            Button {
                                settings.unarchiveSession(session.sessionId)
                                PAXHaptics.success()
                            } label: {
                                Label { Text(L10n.SessionRestore) } icon: { PAXIcon("tray.and.arrow.up") }
                            }
                            .tint(.blue)
                        }
                        .swipeActions(edge: .trailing, allowsFullSwipe: false) {
                            Button {
                                requestDeleteSession(session)
                            } label: {
                                Label { Text(L10n.CommonDelete) } icon: { PAXIcon("trash") }
                            }
                            .tint(.red)
                        }
                    }
                }
                .listStyle(.plain)
                .scrollContentBackground(.hidden)
            }
        }
        .paxScreenBackground()
        .navigationTitle(L10n.SessionArchivedTitle)
        .navigationBarTitleDisplayMode(.large)
    }

    private func requestDeleteSession(_ session: LiveSession) {
        PAXDelete.confirm(
            message: L10n.SessionDeleteConfirm,
            itemTitle: session.displayName
        ) {
            Task { await coordinator.deleteSession(auth: auth, session: session) }
        }
    }

    private var emptyState: some View {
        VStack(spacing: 14) {
            PAXIcon("archivebox", size: .hero, emphasis: .tertiary)
            Text(L10n.SessionArchivedEmpty)
                .font(.headline)
            Text(L10n.SessionArchivedEmptyHint)
                .font(.subheadline)
                .foregroundStyle(PAXTheme.textSecondary)
                .multilineTextAlignment(.center)
                .padding(.horizontal, 24)
        }
        .frame(maxWidth: .infinity, maxHeight: .infinity)
    }
}

private struct SessionArchivedRow: View {
    let session: LiveSession
    let isUnread: Bool

    var body: some View {
        PAXListCard(highlighted: isUnread, accent: PAXBrand.accent) {
            HStack(alignment: .center, spacing: 14) {
                SessionAvatarView(
                    name: session.displayName,
                    size: 48,
                    isLive: false,
                    isTeam: false
                )

                VStack(alignment: .leading, spacing: 4) {
                    Text(session.displayName)
                        .font(.body.weight(isUnread ? .semibold : .regular))
                        .foregroundStyle(PAXTheme.textPrimary)
                        .lineLimit(1)

                    Text(session.lastPreview.isEmpty ? "—" : session.lastPreview)
                        .font(.subheadline)
                        .foregroundStyle(PAXTheme.textSecondary)
                        .lineLimit(1)
                }

                Spacer(minLength: 0)

                if isUnread {
                    Circle()
                        .fill(PAXBrand.accent)
                        .frame(width: 10, height: 10)
                }
            }
        }
        .padding(.horizontal, 12)
        .padding(.vertical, 3)
        .contentShape(Rectangle())
    }
}
