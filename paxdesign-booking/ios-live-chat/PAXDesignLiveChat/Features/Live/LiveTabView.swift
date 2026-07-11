import SwiftUI

struct LiveTabView: View {
    @EnvironmentObject private var auth: AuthStore
    @EnvironmentObject private var coordinator: ChatCoordinator
    var onOpenSession: (String) -> Void = { _ in }

    private var liveSessions: [LiveSession] {
        coordinator.sessions.filter { $0.isLiveRequest }
    }

    private var canReply: Bool { auth.canReplyChats }
    private var canViewChats: Bool { auth.canViewChats }

    var body: some View {
        List {
            Section {
                liveHero
            }

            if coordinator.isLoading && coordinator.sessions.isEmpty {
                Section {
                    PAXScreenLoadingStack(status: L10n.LiveTitle, rowCount: 3)
                }
            } else if liveSessions.isEmpty {
                Section {
                    emptyLiveState
                }
            } else {
                Section {
                    ForEach(liveSessions) { session in
                        LiveRequestCard(
                            session: session,
                            canReply: canReply,
                            canOpenChat: canViewChats
                        ) {
                            if canViewChats { onOpenSession(session.sessionId) }
                            Task {
                                await coordinator.acceptLiveRequest(auth: auth, session: session)
                            }
                        } onDecline: {
                            PAXDelete.confirm(
                                message: L10n.LiveDeclineConfirm,
                                itemTitle: session.displayName,
                                confirmTitle: L10n.CommonDecline
                            ) {
                                Task { await coordinator.declineLiveRequest(auth: auth, session: session) }
                            }
                        } onOpen: {
                            if canViewChats { onOpenSession(session.sessionId) }
                        }
                    }
                }
            }
        }
        .listStyle(.insetGrouped)
        .scrollContentBackground(.hidden)
        .paxScreenBackground()
        .navigationTitle(L10n.LiveTitle)
        .navigationBarTitleDisplayMode(.large)
        .paxPremiumRefreshable(status: L10n.LiveTitle, rowCount: 3) {
            await coordinator.refreshSessions(auth: auth)
        }
        .scrollDismissesKeyboard(.interactively)
    }

    private var liveHero: some View {
        PAXAccentBannerCard(
            title: L10n.LiveSupport,
            subtitle: coordinator.liveCount > 0
                ? L10n.LiveWaitingCount(coordinator.liveCount)
                : L10n.LiveNoRequests,
            systemImage: "bell.and.waves.left.and.right.fill"
        )
    }

    private var emptyLiveState: some View {
        VStack(spacing: 14) {
            Image(systemName: "checkmark.seal.fill")
                .font(.system(size: 44))
                .foregroundStyle(PAXTheme.success.opacity(0.85))
            Text(L10n.LiveAllClear)
                .font(.title3.weight(.semibold))
            Text(L10n.LiveEmptyHint)
                .font(.subheadline)
                .foregroundStyle(PAXTheme.textSecondary)
                .multilineTextAlignment(.center)
        }
        .frame(maxWidth: .infinity)
        .padding(.vertical, 48)
        .paxCard(.feature, tint: PAXTheme.success)
    }
}

private struct LiveRequestCard: View {
    let session: LiveSession
    var canReply: Bool = true
    var canOpenChat: Bool = true
    let onAccept: () -> Void
    let onDecline: () -> Void
    let onOpen: () -> Void

    var body: some View {
        VStack(alignment: .leading, spacing: 12) {
            HStack {
                VStack(alignment: .leading, spacing: 4) {
                    Text(session.displayName)
                        .font(.headline)
                    if !session.detectedService.isEmpty {
                        Text(session.detectedService)
                            .font(.caption.weight(.medium))
                            .foregroundStyle(PAXTheme.textSecondary)
                    }
                }
                Spacer()
                Text(L10n.LiveBadge)
                    .font(.caption2.weight(.heavy))
                    .foregroundStyle(.white)
                    .padding(.horizontal, 8)
                    .padding(.vertical, 4)
                    .background(Capsule().fill(PAXTheme.accent))
            }

            if !session.lastPreview.isEmpty {
                Text(session.lastPreview)
                    .font(.subheadline)
                    .foregroundStyle(PAXTheme.textTertiary)
                    .lineLimit(2)
            }

            HStack(spacing: 10) {
                if canReply {
                    Button(L10n.CommonDecline, action: onDecline)
                        .buttonStyle(.bordered)
                        .tint(PAXTheme.danger)
                    Button(L10n.CommonTakeover, action: onAccept)
                        .buttonStyle(.borderedProminent)
                        .tint(PAXTheme.accent)
                }
                Spacer()
                if canOpenChat {
                    Button(L10n.CommonOpen, action: onOpen)
                        .font(.caption.weight(.semibold))
                }
            }
        }
        .paxCard(.accent, tint: PAXTheme.accent)
    }
}

private extension L10n {
    static func LiveWaitingCount(_ count: Int) -> String {
        String(format: String(localized: "live.waiting_count"), count)
    }
}
