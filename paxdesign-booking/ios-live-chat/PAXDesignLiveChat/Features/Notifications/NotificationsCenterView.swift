import SwiftUI

struct NotificationsCenterView: View {
    @EnvironmentObject private var auth: AuthStore
    @EnvironmentObject private var coordinator: ChatCoordinator
    @EnvironmentObject private var permissions: PermissionCoordinator
    @StateObject private var settings = AppSettingsStore.shared

    private var unreadSessions: [LiveSession] {
        coordinator.sessions.filter {
            !$0.isTeamDM && $0.needsReply && !settings.readSessionIds.contains($0.sessionId)
        }
    }

    private var liveSessions: [LiveSession] {
        coordinator.sessions.filter { $0.isLiveRequest }
    }

    var body: some View {
        List {
            Section {
                PlatformHeroHeader(
                    title: L10n.NotificationsCenterTitle,
                    subtitle: L10n.NotificationsCenterSubtitle,
                    systemImage: "bell.badge.fill",
                    gradient: [.orange, .pink]
                )
                .listRowInsets(EdgeInsets(top: 8, leading: 0, bottom: 8, trailing: 0))
                .listRowBackground(Color.clear)
                .listRowSeparator(.hidden)
            }

            Section(L10n.NotificationsSummary) {
                notificationMetric(
                    title: L10n.NotificationsUnreadChats,
                    count: unreadSessions.count,
                    systemImage: "bubble.left.and.bubble.right.fill",
                    tint: PAXTheme.accent
                )
                notificationMetric(
                    title: L10n.NotificationsLiveRequests,
                    count: coordinator.liveCount,
                    systemImage: "bell.and.waves.left.and.right.fill",
                    tint: .orange
                )
                LabeledContent(L10n.SettingsPush) {
                    Text(settings.notificationsEnabled ? L10n.CommonActive : L10n.SettingsDisabled)
                        .foregroundStyle(settings.notificationsEnabled ? PAXTheme.success : PAXTheme.textTertiary)
                }
            }

            if !unreadSessions.isEmpty || coordinator.liveCount > 0 {
                Section(L10n.NotificationsRecentActivity) {
                    if coordinator.liveCount > 0 {
                        ForEach(liveSessions.prefix(5)) { session in
                            activityRow(session, badge: L10n.FilterLive, tint: .orange)
                        }
                    }
                    ForEach(unreadSessions.prefix(8)) { session in
                        activityRow(session, badge: L10n.FilterUnread, tint: PAXTheme.accent)
                    }
                }
            }

            Section {
                NavigationLink {
                    NotificationSettingsView()
                } label: {
                    Label(L10n.SettingsSectionNotifications, systemImage: "bell.badge")
                }
                NavigationLink {
                    SoundSettingsView()
                } label: {
                    Label(L10n.SettingsSound, systemImage: "speaker.wave.2")
                }
                if permissions.notificationStatus == .denied {
                    Button {
                        permissions.openSystemSettings()
                    } label: {
                        Label(L10n.SettingsOpenIosSettings, systemImage: "arrow.up.forward.app")
                    }
                }
            }

            if !unreadSessions.isEmpty {
                Section {
                    Button {
                        markAllRead()
                    } label: {
                        Label(L10n.NotificationsMarkAllRead, systemImage: "envelope.open.fill")
                    }
                }
            }
        }
        .listStyle(.insetGrouped)
        .scrollContentBackground(.hidden)
        .background(PAXBackground())
        .navigationTitle(L10n.NotificationsCenterTitle)
        .navigationBarTitleDisplayMode(.large)
        .refreshable {
            await coordinator.refreshSessions(auth: auth)
        }
    }

    private func notificationMetric(title: String, count: Int, systemImage: String, tint: Color) -> some View {
        HStack(spacing: 14) {
            Image(systemName: systemImage)
                .font(.title3)
                .foregroundStyle(tint)
                .frame(width: 32)
            Text(title)
            Spacer()
            Text("\(count)")
                .font(.title3.weight(.bold))
                .foregroundStyle(count > 0 ? tint : PAXTheme.textTertiary)
        }
    }

    private func activityRow(_ session: LiveSession, badge: String, tint: Color) -> some View {
        HStack(spacing: 12) {
            SessionAvatarView(
                name: session.displayName,
                size: 40,
                isLive: session.isLiveRequest,
                isTeam: session.isTeamDM
            )
            VStack(alignment: .leading, spacing: 3) {
                Text(session.displayName)
                    .font(.subheadline.weight(.semibold))
                Text(session.lastPreview.isEmpty ? session.detectedService : session.lastPreview)
                    .font(.caption)
                    .foregroundStyle(PAXTheme.textSecondary)
                    .lineLimit(1)
            }
            Spacer()
            Text(badge)
                .font(.caption2.weight(.bold))
                .foregroundStyle(tint)
                .padding(.horizontal, 6)
                .padding(.vertical, 2)
                .background(Capsule().fill(tint.opacity(0.14)))
        }
        .padding(.vertical, 2)
    }

    private func markAllRead() {
        for session in unreadSessions {
            settings.readSessionIds.insert(session.sessionId)
        }
        PAXHaptics.success()
    }
}
