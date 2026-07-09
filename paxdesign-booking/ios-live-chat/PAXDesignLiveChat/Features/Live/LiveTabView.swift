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

            if liveSessions.isEmpty {
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
                            PAXHaptics.warning()
                            Task { await coordinator.declineLiveRequest(auth: auth, session: session) }
                        } onOpen: {
                            if canViewChats { onOpenSession(session.sessionId) }
                        }
                    }
                }
            }
        }
        .listStyle(.insetGrouped)
        .scrollContentBackground(.hidden)
        .background(PAXBackground())
        .navigationTitle(L10n.LiveTitle)
        .navigationBarTitleDisplayMode(.large)
        .refreshable { await coordinator.refreshSessions(auth: auth) }
        .scrollDismissesKeyboard(.interactively)
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
        .paxGlassCardStyle(cornerRadius: 18, fillOpacity: 0.82, borderOpacity: 0.44, shadowOpacity: 0.18)
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
        .paxGlassCardStyle(cornerRadius: 18, fillOpacity: 0.78, borderOpacity: 0.42, shadowOpacity: 0.12)
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
                .fill(.ultraThinMaterial)
                .overlay(
                    RoundedRectangle(cornerRadius: 18, style: .continuous)
                        .fill(PAXTheme.accentSoft.opacity(0.42))
                )
                .overlay(
                    RoundedRectangle(cornerRadius: 18, style: .continuous)
                        .stroke(PAXTheme.accent.opacity(0.34), lineWidth: 1)
                )
                .shadow(color: .black.opacity(0.16), radius: 12, x: 0, y: 8)
        )
    }
}

private extension L10n {
    static func LiveWaitingCount(_ count: Int) -> String {
        String(format: String(localized: "live.waiting_count"), count)
    }
}
