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
            !$0.isTeamDM && settings.isSessionUnread($0)
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
                .listRowInsets(EdgeInsets())
                .listRowBackground(Color.clear)
                .listRowSeparator(.hidden)
                notificationMetric(
                    title: L10n.NotificationsLiveRequests,
                    count: platform.notifications?.liveRequests ?? coordinator.liveCount,
                    systemImage: "bell.and.waves.left.and.right.fill",
                    tint: PAXTheme.danger
                )
                .listRowInsets(EdgeInsets())
                .listRowBackground(Color.clear)
                .listRowSeparator(.hidden)
                if let openTasks = platform.notifications?.openTasks {
                    notificationMetric(
                        title: L10n.DashboardMetricTasks,
                        count: openTasks,
                        systemImage: "checklist",
                        tint: PAXTheme.success
                    )
                    .listRowInsets(EdgeInsets())
                    .listRowBackground(Color.clear)
                    .listRowSeparator(.hidden)
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
                    Label { Text(L10n.SettingsSectionNotifications) } icon: { PAXIcon("bell.badge") }
                }
                NavigationLink {
                    SoundSettingsView()
                } label: {
                    Label { Text(L10n.SettingsSound) } icon: { PAXIcon("speaker.wave.2") }
                }
                if permissions.notificationStatus == .denied {
                    Button {
                        permissions.openSystemSettings()
                    } label: {
                        Label { Text(L10n.SettingsOpenIosSettings) } icon: { PAXIcon("arrow.up.forward.app") }
                    }
                }
            }

            if !unreadSessions.isEmpty {
                Section {
                    Button {
                        markAllRead()
                    } label: {
                        Label { Text(L10n.NotificationsMarkAllRead) } icon: { PAXIcon("envelope.open.fill") }
                    }
                }
            }
            }
        }
        .paxRevolutGroupedList()
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
        PAXRevolutMetricTile(
            title: title,
            value: "\(count)",
            systemImage: systemImage,
            tint: tint
        )
        .padding(.horizontal, PAXSpacing.screenHorizontal)
        .padding(.vertical, 4)
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
                    settings.markSessionRead(session.sessionId, seq: session.seq)
                    coordinator.activeSessionId = session.sessionId
                    coordinator.updateUnreadCounts()
                    PAXHaptics.light()
                } label: {
                    Label { Text(L10n.CommonMarkRead) } icon: { PAXIcon("envelope.open") }
                        .font(.caption.weight(.semibold))
                }
                .buttonStyle(.bordered)

                if session.isLiveRequest {
                    Button {
                        coordinator.presentIncomingFullscreen()
                        PAXHaptics.medium()
                    } label: {
                        Label { Text(L10n.CommonOpen) } icon: { PAXIcon("bell.and.waves.left.and.right") }
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
                settings.markSessionRead(session.sessionId, seq: session.seq)
                coordinator.updateUnreadCounts()
            } label: {
                Label { Text(L10n.CommonMarkRead) } icon: { PAXIcon("envelope.open") }
            }
            Button {
                coordinator.activeSessionId = session.sessionId
            } label: {
                Label { Text(L10n.CommonOpen) } icon: { PAXIcon("arrow.up.right.circle") }
            }
        }
        .swipeActions(edge: .leading, allowsFullSwipe: true) {
            Button {
                settings.markSessionRead(session.sessionId, seq: session.seq)
                coordinator.updateUnreadCounts()
                PAXHaptics.success()
            } label: {
                Label { Text(L10n.CommonMarkRead) } icon: { PAXIcon("envelope.open") }
            }
            .tint(.blue)
        }
    }

    private func markAllRead() {
        for session in unreadSessions {
            settings.markSessionRead(session.sessionId, seq: session.seq)
        }
        coordinator.updateUnreadCounts()
        PAXHaptics.success()
    }
}
