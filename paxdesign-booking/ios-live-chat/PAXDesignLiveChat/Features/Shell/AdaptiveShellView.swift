import SwiftUI

struct AdaptiveShellView: View {
    @EnvironmentObject private var auth: AuthStore
    @EnvironmentObject private var coordinator: ChatCoordinator
    @EnvironmentObject private var teamCoordinator: TeamMessagingCoordinator
    @EnvironmentObject private var appLock: AppLockService
    @EnvironmentObject private var settings: AppSettingsStore
    @EnvironmentObject private var permissions: PermissionCoordinator
    @EnvironmentObject private var push: PushService
    @Environment(\.accessibilityReduceMotion) private var reduceMotion
    @Environment(\.scenePhase) private var scenePhase
    @State private var chatsPath = NavigationPath()
    @State private var teamPath = NavigationPath()
    @State private var livePath = NavigationPath()
    @State private var dashboardPath = NavigationPath()
    @State private var platformPath = NavigationPath()
    @State private var selectedTab = 0
    @State private var iPadSection: PlatformShellSection = .dashboard
    @State private var showGlobalSearch = false
    @State private var syncTask: Task<Void, Never>?
    @State private var loadedTabs: Set<Int> = [0]
    @State private var routingSessionId: String?
    @StateObject private var menuScrollState = UiverseMenuScrollState()

    private var isPad: Bool { UIDevice.current.userInterfaceIdiom == .pad }

    private var unreadChatCount: Int { coordinator.unreadChatCount }
    private var unreadTeamCount: Int {
        teamCoordinator.unreadCount(settings: settings, coordinatorSessions: coordinator.sessions)
    }

    private var canViewChats: Bool { auth.canViewChats }
    private var canReplyChats: Bool { auth.canReplyChats }

    private var visibleSections: [PlatformShellSection] {
        var sections: [PlatformShellSection] = [.dashboard]
        if canViewChats { sections += [.chats, .team] }
        sections += [.live, .platform]
        return sections
    }

    private var tabTags: (dashboard: Int, chats: Int?, team: Int?, live: Int, platform: Int) {
        var tag = 0
        let dashboard = tag
        tag += 1
        var chats: Int?
        var team: Int?
        if canViewChats {
            chats = tag
            tag += 1
            team = tag
            tag += 1
        }
        let live = tag
        tag += 1
        let platform = tag
        return (dashboard, chats, team, live, platform)
    }

    private var isShellDetailActive: Bool {
        !dashboardPath.isEmpty
            || !chatsPath.isEmpty
            || !teamPath.isEmpty
            || !livePath.isEmpty
            || !platformPath.isEmpty
    }

    private var shouldShowBottomTabBar: Bool {
        !isPad && !isShellDetailActive
    }

    private var uiverseMenuItems: [UiverseMenuBarItem] {
        iPhoneTabItems.map { item in
            UiverseMenuBarItem(
                tag: item.tag,
                icon: item.icon,
                title: item.title
            )
        }
    }

    private var iPhoneTabItems: [ShellTabItem] {
        let tags = tabTags
        var items: [ShellTabItem] = [
            .init(
                tag: tags.dashboard,
                title: L10n.TabDashboard,
                icon: "dashboard.fill"
            )
        ]

        if canViewChats, let chatsTag = tags.chats {
            items.append(
                .init(
                    tag: chatsTag,
                    title: L10n.TabChats,
                    icon: "chats.fill"
                )
            )
        }

        if canViewChats, let teamTag = tags.team {
            items.append(
                .init(
                    tag: teamTag,
                    title: L10n.TabTeam,
                    icon: "team.fill"
                )
            )
        }

        items.append(
            .init(
                tag: tags.live,
                title: L10n.TabLive,
                icon: "live.fill"
            )
        )
        items.append(
            .init(
                tag: tags.platform,
                title: L10n.TabPlatform,
                icon: "platform.fill"
            )
        )
        return items
    }

    var body: some View {
        Group {
            if isPad {
                iPadShell
            } else {
                iPhoneShell
            }
        }
        .safeAreaInset(edge: .top, spacing: 0) {
            VStack(spacing: 8) {
                if canReplyChats, let incoming = coordinator.incomingRequest, !coordinator.incomingBannerDismissed {
                    LiveRequestTopBanner(request: incoming) {
                        Task { await coordinator.openSessionFromBanner(incoming.session.sessionId, auth: auth) }
                    } onDismiss: {
                        coordinator.dismissIncomingBanner()
                    }
                    .transition(.move(edge: .top).combined(with: .opacity))
                }
                if canReplyChats, let message = coordinator.pendingCustomerMessage {
                    StaffCustomerMessageBanner(banner: message) {
                        Task { await coordinator.openSessionFromBanner(message.sessionId, auth: auth) }
                    } onDismiss: {
                        coordinator.dismissCustomerMessageBanner()
                    }
                    .transition(.move(edge: .top).combined(with: .opacity))
                }
            }
        }
        .overlay(alignment: .bottom) {
            if shouldShowBottomTabBar {
                UiverseMenuBarView(
                    items: uiverseMenuItems,
                    selection: $selectedTab,
                    reduceMotion: reduceMotion
                )
                .scaleEffect(menuScrollState.barScale, anchor: .bottom)
                .padding(.horizontal, UiverseMenuMetrics.horizontalMargin)
                .padding(.bottom, UiverseMenuMetrics.homeIndicatorGap)
                .ignoresSafeArea(edges: .bottom)
                .transition(.move(edge: .bottom).combined(with: .opacity))
            }
        }
        .animation(reduceMotion ? nil : .spring(response: 0.32, dampingFraction: 0.9), value: shouldShowBottomTabBar)
        .environment(\.shellTabBarVisible, shouldShowBottomTabBar)
        .environment(\.shellTabBarScrollInset, shouldShowBottomTabBar ? PAXShellLayout.uiverseMenuScrollInset : 0)
        .environment(\.shellMenuScrollState, shouldShowBottomTabBar ? menuScrollState : nil)
        .onChange(of: shouldShowBottomTabBar) { isVisible in
            if !isVisible {
                menuScrollState.reset(reduceMotion: reduceMotion)
            }
        }
        .tint(PAXTheme.accent)
        .sheet(isPresented: $showGlobalSearch) {
            NavigationStack { GlobalSearchView() }
        }
        .sheet(
            isPresented: $permissions.showNotificationPrompt,
            onDismiss: { permissions.completeNotificationPrompt() }
        ) {
            NotificationPermissionPromptView()
                .environmentObject(PushService.shared)
                .presentationDetents([.medium, .large])
                .presentationDragIndicator(.visible)
        }
        .onAppear {
            loadedTabs.insert(selectedTab)
            coordinator.updateUnreadCounts()
            schedulePlatformSync()
            PAXHaptics.prepare()
            permissions.presentNotificationPromptIfNeeded(isLoggedIn: auth.isLoggedIn)
            Task {
                await PushDeepLinkRouter.shared.consumeIfReady(
                    auth: auth,
                    coordinator: coordinator,
                    teamCoordinator: teamCoordinator,
                    isShellReady: true
                )
            }
        }
        .onChange(of: scenePhase) { phase in
            if phase == .background {
                syncPlatformServices()
            }
        }
        .onChange(of: settings.readSessionIds) { _ in
            coordinator.updateUnreadCounts()
        }
        .onChange(of: settings.readUpToSeq) { _ in
            coordinator.updateUnreadCounts()
        }
        .onChange(of: coordinator.sessions.count) { _ in schedulePlatformSync() }
        .onChange(of: coordinator.liveCount) { count in
            schedulePlatformSync()
            if count > 0 { PAXHaptics.medium() }
        }
        .onChange(of: coordinator.activeSessionId) { sessionId in
            guard let sessionId else {
                routingSessionId = nil
                AppRefreshPolicy.setActiveSession(nil)
                return
            }
            routeToSession(sessionId)
        }
        .onChange(of: auth.isLoggedIn) { loggedIn in
            QuickActionsManager.configure(
                isLoggedIn: loggedIn,
                canViewChats: auth.canViewChats,
                canManageUsers: auth.canManageUsers
            )
        }
        .onReceive(NotificationCenter.default.publisher(for: .paxQuickAction)) { note in
            handleQuickAction(note.object as? String, query: note.userInfo?["query"] as? String)
        }
        .onChange(of: selectedTab) { tab in
            loadedTabs.insert(tab)
            appLock.recordActivity()
        }
    }

    private var iPhoneShell: some View {
        let tags = tabTags
        return ZStack {
            iPhoneTabPane(tags.dashboard) {
                NavigationStack(path: $dashboardPath) {
                    DashboardView()
                        .navigationDestination(for: String.self, destination: sessionDestination)
                        .navigationDestination(for: PlatformModule.self) { module in
                            platformDestination(for: module)
                        }
                }
            }

            if canViewChats, let chatsTag = tags.chats {
                iPhoneTabPane(chatsTag) {
                    NavigationStack(path: $chatsPath) {
                        SessionListView(onOpenSession: { openSession($0, path: $chatsPath) })
                            .navigationDestination(for: String.self, destination: sessionDestination)
                    }
                }
            }

            if canViewChats, let teamTag = tags.team {
                iPhoneTabPane(teamTag) {
                    NavigationStack(path: $teamPath) {
                        TeamMessagesHubView(onOpenSession: { openSession($0, path: $teamPath) })
                            .navigationDestination(for: String.self, destination: teamDestination)
                    }
                }
            }

            iPhoneTabPane(tags.live) {
                NavigationStack(path: $livePath) {
                    LiveTabView(onOpenSession: { openSession($0, path: $livePath) })
                        .navigationDestination(for: String.self) { sessionId in
                            if canViewChats { ChatView(sessionId: sessionId) }
                        }
                }
            }

            iPhoneTabPane(tags.platform) {
                NavigationStack(path: $platformPath) {
                    PlatformHubView()
                        .navigationDestination(for: String.self, destination: sessionDestination)
                        .navigationDestination(for: PlatformModule.self) { module in
                            platformDestination(for: module)
                        }
                }
            }
        }
    }

    @ViewBuilder
    private func iPhoneTabPane<Content: View>(_ tag: Int, @ViewBuilder content: () -> Content) -> some View {
        if loadedTabs.contains(tag) {
            content()
                .opacity(selectedTab == tag ? 1 : 0)
                .allowsHitTesting(selectedTab == tag)
                .accessibilityHidden(selectedTab != tag)
                .zIndex(selectedTab == tag ? 1 : 0)
        }
    }

    private var iPadShell: some View {
        NavigationSplitView {
            List {
                ForEach(visibleSections) { section in
                    Button {
                        iPadSection = section
                    } label: {
                        Label { Text(section.title) } icon: { PAXIcon(section.systemImage) }
                    }
                    .foregroundStyle(iPadSection == section ? PAXTheme.accent : PAXTheme.textPrimary)
                }
            }
            .navigationTitle(L10n.AppShortName)
        } detail: {
            iPadDetail(for: iPadSection)
        }
        .navigationSplitViewStyle(.balanced)
    }

    @ViewBuilder
    private func iPadDetail(for section: PlatformShellSection) -> some View {
        switch section {
        case .dashboard:
            NavigationStack(path: $dashboardPath) {
                DashboardView()
                    .navigationDestination(for: String.self, destination: sessionDestination)
                    .navigationDestination(for: PlatformModule.self) { module in
                        platformDestination(for: module)
                    }
            }
        case .chats:
            NavigationStack(path: $chatsPath) {
                SessionListView(onOpenSession: { openSession($0, path: $chatsPath) })
                    .navigationDestination(for: String.self, destination: sessionDestination)
            }
        case .team:
            NavigationStack(path: $teamPath) {
                TeamMessagesHubView(onOpenSession: { openSession($0, path: $teamPath) })
                    .navigationDestination(for: String.self, destination: teamDestination)
            }
        case .live:
            NavigationStack(path: $livePath) {
                LiveTabView(onOpenSession: { openSession($0, path: $livePath) })
                    .navigationDestination(for: String.self, destination: sessionDestination)
            }
        case .platform:
            NavigationStack(path: $platformPath) {
                PlatformHubView()
                    .navigationDestination(for: String.self, destination: sessionDestination)
                    .navigationDestination(for: PlatformModule.self) { module in
                        platformDestination(for: module)
                    }
            }
        }
    }

    @ViewBuilder
    private func sessionDestination(_ sessionId: String) -> some View {
        if sessionId.hasPrefix("team_") {
            TeamChatView(sessionId: sessionId)
                .id(sessionId)
        } else if canViewChats {
            ChatView(sessionId: sessionId)
                .id(sessionId)
        }
    }

    @ViewBuilder
    private func teamDestination(_ sessionId: String) -> some View {
        TeamChatView(sessionId: sessionId)
            .id(sessionId)
    }

    private func openSession(_ sessionId: String, path: Binding<NavigationPath>) {
        if routingSessionId == sessionId, !path.wrappedValue.isEmpty {
            return
        }

        let isNewRoute = routingSessionId != sessionId
        if isNewRoute {
            routingSessionId = sessionId
            appLock.recordActivity()
            coordinator.acknowledgeIncomingRequest(sessionId)
            coordinator.activeSessionId = sessionId
            AppRefreshPolicy.setActiveSession(sessionId)
            AppRefreshPolicy.update(liveCount: coordinator.liveCount, openChat: true)

            if let api = AuthStore.shared.api {
                ConversationPrefetcher.shared.schedulePrefetch(
                    sessionId: sessionId,
                    api: api,
                    isTeam: sessionId.hasPrefix("team_"),
                    priority: true
                )
            }

            Task { @MainActor in
                if let session = coordinator.sessions.first(where: { $0.sessionId == sessionId })
                    ?? teamCoordinator.teamSessions.first(where: { $0.sessionId == sessionId }) {
                    settings.markSessionRead(sessionId, seq: session.seq)
                } else {
                    settings.markSessionRead(sessionId)
                }
            }
        }

        // Ensure list root + chat destination so Back returns to the inbox.
        if path.wrappedValue.isEmpty || isNewRoute {
            path.wrappedValue = NavigationPath()
            path.wrappedValue.append(sessionId)
        }
    }

    private func routeToSession(_ sessionId: String) {
        guard canViewChats else { return }
        if isPad {
            if sessionId.hasPrefix("team_") {
                iPadSection = .team
                openSession(sessionId, path: $teamPath)
            } else {
                iPadSection = .chats
                openSession(sessionId, path: $chatsPath)
            }
            return
        }
        let tags = tabTags
        if sessionId.hasPrefix("team_"), let teamTag = tags.team {
            selectedTab = teamTag
            openSession(sessionId, path: $teamPath)
        } else if let chatsTag = tags.chats {
            selectedTab = chatsTag
            openSession(sessionId, path: $chatsPath)
        }
    }

    private func handleQuickAction(_ type: String?, query: String?) {
        guard let type else { return }
        if let query, !query.isEmpty { /* reserved for pre-filled search */ }
        if isPad {
            switch type {
            case QuickActionsManager.openDashboard: iPadSection = .dashboard
            case QuickActionsManager.openLive: iPadSection = .live
            case QuickActionsManager.openSearch: showGlobalSearch = true
            case QuickActionsManager.composeTeam: iPadSection = .team
            default: break
            }
            return
        }
        let tags = tabTags
        switch type {
        case QuickActionsManager.openDashboard: selectedTab = tags.dashboard
        case QuickActionsManager.openLive: selectedTab = tags.live
        case QuickActionsManager.openSearch: showGlobalSearch = true
        case QuickActionsManager.composeTeam: if let teamTag = tags.team { selectedTab = teamTag }
        default: break
        }
    }

    private func schedulePlatformSync() {
        syncTask?.cancel()
        syncTask = Task { @MainActor in
            try? await Task.sleep(nanoseconds: 350_000_000)
            guard !Task.isCancelled else { return }
            syncPlatformServices()
        }
    }

    private func syncPlatformServices() {
        ChatCoordinatorProxy.liveCount = coordinator.liveCount
        ChatCoordinatorProxy.sessions = coordinator.sessions
        WidgetDataStore.shared.syncFromApp()
        let topCustomer = coordinator.sessions.first(where: { $0.isLiveRequest })?.displayName
        LiveActivityManager.shared.updateLiveRequestCount(coordinator.liveCount, topCustomer: topCustomer)
    }
}

private struct ShellTabItem: Identifiable {
    let tag: Int
    let title: String
    let icon: String

    var id: Int { tag }
}
