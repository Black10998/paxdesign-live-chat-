import SwiftUI

struct SessionListView: View {
    @EnvironmentObject private var auth: AuthStore
    @EnvironmentObject private var coordinator: ChatCoordinator
    var onOpenSession: (String) -> Void = { _ in }

    var body: some View {
        ScrollView {
            LazyVStack(spacing: 14) {
                header

                if coordinator.liveCount > 0 {
                    liveBanner
                        .transition(.move(edge: .top).combined(with: .opacity))
                }

                if coordinator.sessions.isEmpty {
                    emptyState
                        .padding(.top, 40)
                } else {
                    ForEach(coordinator.sessions) { session in
                        Button {
                            PAXHaptics.light()
                            coordinator.activeSessionId = session.sessionId
                            onOpenSession(session.sessionId)
                        } label: {
                            SessionCard(session: session)
                        }
                        .buttonStyle(PAXPressButtonStyle())
                        .opacity(session.isClosed ? 0.56 : 1)
                        .swipeActions(edge: .trailing, allowsFullSwipe: true) {
                            Button(role: .destructive) {
                                PAXHaptics.warning()
                                Task { await coordinator.deleteSession(auth: auth, session: session) }
                            } label: {
                                Label("Löschen", systemImage: "trash")
                            }

                            Button {
                                PAXHaptics.light()
                                Task { await coordinator.archiveSession(auth: auth, session: session) }
                            } label: {
                                Label("Archivieren", systemImage: "archivebox")
                            }
                            .tint(.orange)
                        }
                    }
                }
            }
            .padding(.horizontal, 16)
            .padding(.bottom, 24)
        }
        .background(PAXBackground())
        .navigationTitle("Live Chat")
        .navigationBarTitleDisplayMode(.large)
        .refreshable { await coordinator.refreshSessions(auth: auth) }
        .animation(PAXTheme.spring, value: coordinator.liveCount)
        .animation(PAXTheme.spring, value: coordinator.sessions.count)
    }

    private var header: some View {
        PAXGlassCard {
            HStack(spacing: 14) {
                ProfileAvatarView(size: 52)

                VStack(alignment: .leading, spacing: 4) {
                    Text(auth.profile?.name ?? "Administrator")
                        .font(.headline)
                    Text(auth.profile?.email ?? auth.username)
                        .font(.caption)
                        .foregroundStyle(PAXTheme.textSecondary)
                }

                Spacer()

                VStack(alignment: .trailing, spacing: 4) {
                    Text("\(coordinator.sessions.count)")
                        .font(.title3.weight(.bold))
                    Text("Aktiv")
                        .font(.caption2.weight(.semibold))
                        .foregroundStyle(PAXTheme.textSecondary)
                }
            }
        }
        .padding(.top, 8)
    }

    private var liveBanner: some View {
        HStack(spacing: 12) {
            Image(systemName: "bell.and.waves.left.and.right.fill")
                .font(.title3)
                .foregroundStyle(PAXTheme.accent)
                .scaleEffect(1.0)
                .animation(.easeInOut(duration: 0.9).repeatForever(autoreverses: true), value: coordinator.liveCount)

            VStack(alignment: .leading, spacing: 2) {
                Text("\(coordinator.liveCount) Live-Anfrage(n)")
                    .font(.headline)
                Text("Kunden warten auf einen Agenten")
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
            Text("Keine Chats")
                .font(.title3.weight(.semibold))
            Text("Neue Gespräche erscheinen hier automatisch.")
                .font(.subheadline)
                .foregroundStyle(PAXTheme.textSecondary)
                .multilineTextAlignment(.center)
        }
        .frame(maxWidth: .infinity)
    }
}

private struct SessionCard: View {
    let session: LiveSession

    var body: some View {
        HStack(spacing: 14) {
            ZStack {
                Circle()
                    .fill(statusColor.opacity(0.14))
                    .frame(width: 48, height: 48)
                Image(systemName: session.isLiveRequest ? "person.wave.2.fill" : "person.fill")
                    .foregroundStyle(statusColor)
            }

            VStack(alignment: .leading, spacing: 6) {
                HStack {
                    Text(session.displayName)
                        .font(.headline)
                        .foregroundStyle(PAXTheme.textPrimary)
                    Spacer()
                    PAXStatusBadge(text: session.handlerLabel, color: statusColor)
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
        }
        .padding(16)
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
