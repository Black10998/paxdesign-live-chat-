import SwiftUI

struct NotificationsCenterView: View {
    @EnvironmentObject private var auth: AuthStore
    @EnvironmentObject private var coordinator: ChatCoordinator
    @EnvironmentObject private var permissions: PermissionCoordinator
    @EnvironmentObject private var settings: AppSettingsStore
    @ObservedObject private var platform = PlatformSyncService.shared
    @State private var isInitialLoading = true

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
            if isInitialLoading && coordinator.isLoading && coordinator.sessions.isEmpty {
                Section {
                    PAXScreenLoadingStack(status: L10n.NotificationsCenterTitle, rowCount: 4)
                }
            } else {
            Section {
                PlatformHeroHeader(
                    title: L10n.NotificationsCenterTitle,
                    subtitle: L10n.NotificationsCenterSubtitle,
                    systemImage: "bell.badge.fill",
                    tint: PAXTheme.accent
                )
                .listRowInsets(EdgeInsets(top: 8, leading: 0, bottom: 8, trailing: 0))
                .listRowBackground(Color.clear)
                .listRowSeparator(.hidden)
            }

            Section(L10n.NotificationsSummary) {
                notificationMetric(
                    title: L10n.NotificationsUnreadChats,
                    count: platform.notifications?.unreadChats ?? unreadSessions.count,
                    systemImage: "bubble.left.and.bubble.right.fill",
                    tint: PAXTheme.accent
                )
                notificationMetric(
                    title: L10n.NotificationsLiveRequests,
                    count: platform.notifications?.liveRequests ?? coordinator.liveCount,
                    systemImage: "bell.and.waves.left.and.right.fill",
                    tint: PAXTheme.accent
                )
                if let openTasks = platform.notifications?.openTasks {
                    notificationMetric(
                        title: L10n.DashboardMetricTasks,
                        count: openTasks,
                        systemImage: "checklist",
                        tint: PAXTheme.accent
                    )
                }
                LabeledContent(L10n.SettingsPush) {
                    Text(settings.notificationsEnabled ? L10n.CommonActive : L10n.SettingsDisabled)
                        .foregroundStyle(settings.notificationsEnabled ? PAXTheme.success : PAXTheme.textTertiary)
                }
            }

            if !unreadSessions.isEmpty || coordinator.liveCount > 0 {
                Section(L10n.NotificationsRecentActivity) {
                    if coordinator.liveCount > 0 {
                        ForEach(liveSessions.prefix(5)) { session in
                            activityRow(session, badge: L10n.FilterLive, tint: PAXTheme.accent)
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
        }
        .listStyle(.insetGrouped)
        .scrollContentBackground(.hidden)
        .paxScreenBackground()
        .navigationTitle(L10n.NotificationsCenterTitle)
        .navigationBarTitleDisplayMode(.large)
        .paxPremiumRefreshable(status: L10n.NotificationsCenterTitle, rowCount: 4) {
            await coordinator.refreshSessions(auth: auth)
            await platform.refreshNotifications(auth: auth)
        }
        .onAppear {
            Task {
                try? await Task.sleep(nanoseconds: 450_000_000)
                withAnimation(.easeOut(duration: 0.25)) {
                    isInitialLoading = false
                }
            }
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
        VStack(alignment: .leading, spacing: 8) {
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

            HStack(spacing: 10) {
                Button {
                    settings.readSessionIds.insert(session.sessionId)
                    coordinator.activeSessionId = session.sessionId
                    PAXHaptics.light()
                } label: {
                    Label(L10n.CommonMarkRead, systemImage: "envelope.open")
                        .font(.caption.weight(.semibold))
                }
                .buttonStyle(.bordered)

                if session.isLiveRequest {
                    Button {
                        coordinator.presentIncomingFullscreen()
                        PAXHaptics.medium()
                    } label: {
                        Label(L10n.CommonOpen, systemImage: "bell.and.waves.left.and.right")
                            .font(.caption.weight(.semibold))
                    }
                    .buttonStyle(.borderedProminent)
                    .tint(PAXTheme.accent)
                }
            }
        }
        .padding(.vertical, 4)
        .contextMenu {
            Button {
                settings.readSessionIds.insert(session.sessionId)
            } label: {
                Label(L10n.CommonMarkRead, systemImage: "envelope.open")
            }
            Button {
                coordinator.activeSessionId = session.sessionId
            } label: {
                Label(L10n.CommonOpen, systemImage: "arrow.up.right.circle")
            }
        }
        .swipeActions(edge: .leading, allowsFullSwipe: true) {
            Button {
                settings.readSessionIds.insert(session.sessionId)
                PAXHaptics.success()
            } label: {
                Label(L10n.CommonMarkRead, systemImage: "envelope.open")
            }
            .tint(.blue)
        }
    }

    private func markAllRead() {
        for session in unreadSessions {
            settings.readSessionIds.insert(session.sessionId)
        }
        PAXHaptics.success()
    }
}
