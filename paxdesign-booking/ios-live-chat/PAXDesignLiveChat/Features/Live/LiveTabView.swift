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
        ScrollView {
            VStack(alignment: .leading, spacing: 16) {
                liveHero

                if liveSessions.isEmpty {
                    emptyLiveState
                } else {
                    VStack(spacing: 10) {
                        ForEach(liveSessions) { session in
                            LiveRequestCard(
                                session: session,
                                canReply: canReply,
                                canOpenChat: canViewChats
                            ) {
                                PAXHaptics.medium()
                                Task { await coordinator.acceptLiveRequest(auth: auth, session: session) }
                                if canViewChats { onOpenSession(session.sessionId) }
                            } onDecline: {
                                PAXHaptics.warning()
                                Task { await coordinator.declineLiveRequest(auth: auth, session: session) }
                            } onOpen: {
                                if canViewChats { onOpenSession(session.sessionId) }
                            }
                        }
                    }
                }
            }
            .padding(.horizontal, 16)
            .padding(.vertical, 12)
        }
        .background(PAXBackground())
        .navigationTitle(L10n.LiveTitle)
        .navigationBarTitleDisplayMode(.large)
        .refreshable { await coordinator.refreshSessions(auth: auth) }
        .scrollDismissesKeyboard(.interactively)
        .onAppear {
            coordinator.start(auth: auth)
            Task { await coordinator.refreshSessions(auth: auth) }
        }
    }

    private var liveHero: some View {
        HStack(spacing: 14) {
            ZStack {
                Circle()
                    .fill(PAXTheme.accentSoft)
                    .frame(width: 52, height: 52)
                Image(systemName: "bell.and.waves.left.and.right.fill")
                    .font(.title2)
                    .foregroundStyle(PAXTheme.accent)
            }
            VStack(alignment: .leading, spacing: 4) {
                Text(L10n.LiveSupport)
                    .font(.headline)
                Text(coordinator.liveCount > 0
                     ? L10n.LiveWaitingCount(coordinator.liveCount)
                     : L10n.LiveNoRequests)
                    .font(.subheadline)
                    .foregroundStyle(PAXTheme.textSecondary)
            }
            Spacer()
            if coordinator.liveCount > 0 {
                Text("\(coordinator.liveCount)")
                    .font(.title3.weight(.bold))
                    .foregroundStyle(PAXTheme.accent)
            }
        }
        .padding(16)
        .background(
            RoundedRectangle(cornerRadius: 18, style: .continuous)
                .fill(PAXTheme.surface.opacity(0.94))
                .overlay(RoundedRectangle(cornerRadius: 18, style: .continuous).stroke(PAXTheme.border, lineWidth: 1))
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
                    Button(L10n.CommonDecline, role: .destructive, action: onDecline)
                        .buttonStyle(.bordered)
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
        .padding(16)
        .background(
            RoundedRectangle(cornerRadius: 18, style: .continuous)
                .fill(PAXTheme.accentSoft.opacity(0.55))
                .overlay(RoundedRectangle(cornerRadius: 18, style: .continuous).stroke(PAXTheme.accent.opacity(0.3), lineWidth: 1))
        )
    }
}

private extension L10n {
    static func LiveWaitingCount(_ count: Int) -> String {
        String(format: String(localized: "live.waiting_count"), count)
    }
}
