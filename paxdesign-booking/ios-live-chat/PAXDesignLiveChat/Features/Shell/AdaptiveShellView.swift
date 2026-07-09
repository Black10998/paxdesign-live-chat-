import SwiftUI

struct AdaptiveShellView: View {
    @EnvironmentObject private var auth: AuthStore
    @EnvironmentObject private var coordinator: ChatCoordinator
    @EnvironmentObject private var teamCoordinator: TeamMessagingCoordinator
    @EnvironmentObject private var appLock: AppLockService
    @StateObject private var settings = AppSettingsStore.shared
    @State private var chatsPath = NavigationPath()
    @State private var teamPath = NavigationPath()
    @State private var livePath = NavigationPath()
    @State private var dashboardPath = NavigationPath()
    @State private var platformPath = NavigationPath()
    @State private var selectedTab = 0
    @State private var iPadSection: PlatformShellSection = .dashboard
    @State private var showGlobalSearch = false

    private var isPad: Bool { UIDevice.current.userInterfaceIdiom == .pad }

    private var unreadChatCount: Int {
        coordinator.sessions.filter {
            !$0.isTeamDM && $0.needsReply && !settings.readSessionIds.contains($0.sessionId)
        }.count
    }

    private var unreadTeamCount: Int {
        coordinator.sessions.filter {
            $0.isTeamDM && $0.needsReply && !settings.readSessionIds.contains($0.sessionId)
        }.count
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

    var body: some View {
        Group {
            if isPad {
                iPadShell
            } else {
                iPhoneShell
            }
        }
        .safeAreaInset(edge: .top, spacing: 0) {
            if canReplyChats, let incoming = coordinator.incomingRequest, !coordinator.incomingBannerDismissed {
                LiveRequestTopBanner(request: incoming) {
                    coordinator.presentIncomingFullscreen()
                } onDismiss: {
                    coordinator.dismissIncomingBanner()
                }
                .transition(.move(edge: .top).combined(with: .opacity))
            }
        }
        .tint(PAXTheme.accent)
        .sheet(isPresented: $showGlobalSearch) {
            NavigationStack { GlobalSearchView() }
        }
        .onAppear { syncPlatformServices() }
        .onChange(of: coordinator.sessions.count) { _ in syncPlatformServices() }
        .onChange(of: coordinator.liveCount) { count in
            syncPlatformServices()
            if count > 0 { PAXHaptics.medium() }
        }
        .onChange(of: coordinator.activeSessionId) { sessionId in
            guard let sessionId else { return }
            routeToSession(sessionId)
        }
        .onChange(of: auth.isLoggedIn) { loggedIn in
            QuickActionsManager.configure(
                isLoggedIn: loggedIn,
                canViewChats: auth.canViewChats,
                canManageUsers: auth.canManageUsers
            )
            if loggedIn {
                SpotlightIndexer.indexAppContent(auth: auth, coordinator: coordinator)
            }
        }
        .onReceive(NotificationCenter.default.publisher(for: .paxQuickAction)) { note in
            handleQuickAction(note.object as? String, query: note.userInfo?["query"] as? String)
        }
        .onChange(of: selectedTab) { _ in
            appLock.recordActivity()
            PAXKeyboard.dismiss()
        }
    }

    private var iPhoneShell: some View {
        let tags = tabTags
        return TabView(selection: $selectedTab) {
            NavigationStack(path: $dashboardPath) {
                DashboardView()
                    .navigationDestination(for: String.self, destination: sessionDestination)
                    .navigationDestination(for: PlatformModule.self) { module in
                        platformDestination(for: module)
                    }
            }
            .tabItem { Label(L10n.TabDashboard, systemImage: "house.fill") }
            .tag(tags.dashboard)

            if canViewChats, let chatsTag = tags.chats {
                NavigationStack(path: $chatsPath) {
                    SessionListView(onOpenSession: { openSession($0, path: $chatsPath) })
                        .navigationDestination(for: String.self, destination: sessionDestination)
                }
                .tabItem { Label(L10n.TabChats, systemImage: "bubble.left.and.bubble.right") }
                .tag(chatsTag)
                .modifier(ShellTabBadge(count: unreadChatCount))
            }

            if canViewChats, let teamTag = tags.team {
                NavigationStack(path: $teamPath) {
                    TeamMessagesHubView(onOpenSession: { openSession($0, path: $teamPath) })
                        .navigationDestination(for: String.self, destination: teamDestination)
                }
                .tabItem { Label(L10n.TabTeam, systemImage: "person.3.fill") }
                .tag(teamTag)
                .modifier(ShellTabBadge(count: unreadTeamCount))
            }

            NavigationStack(path: $livePath) {
                LiveTabView(onOpenSession: { openSession($0, path: $livePath) })
                    .navigationDestination(for: String.self) { sessionId in
                        if canViewChats { ChatView(sessionId: sessionId) }
                    }
            }
            .tabItem { Label(L10n.TabLive, systemImage: "bell.and.waves.left.and.right.fill") }
            .tag(tags.live)
            .modifier(ShellTabBadge(count: coordinator.liveCount))

            NavigationStack(path: $platformPath) {
                PlatformHubView()
                    .navigationDestination(for: String.self, destination: sessionDestination)
                    .navigationDestination(for: PlatformModule.self) { module in
                        platformDestination(for: module)
                    }
            }
            .tabItem { Label(L10n.TabPlatform, systemImage: "square.grid.2x2.fill") }
            .tag(tags.platform)
        }
    }

    private var iPadShell: some View {
        NavigationSplitView {
            List {
                ForEach(visibleSections) { section in
                    Button {
                        iPadSection = section
                    } label: {
                        Label(section.title, systemImage: section.systemImage)
                    }
                    .foregroundStyle(iPadSection == section ? PAXTheme.accent : PAXTheme.textPrimary)
                }
            }
            .navigationTitle("PAXDesign")
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
        } else if canViewChats {
            ChatView(sessionId: sessionId)
        }
    }

    @ViewBuilder
    private func teamDestination(_ sessionId: String) -> some View {
        TeamChatView(sessionId: sessionId)
    }

    private func openSession(_ sessionId: String, path: Binding<NavigationPath>) {
        appLock.recordActivity()
        coordinator.acknowledgeIncomingRequest(sessionId)
        settings.readSessionIds.insert(sessionId)
        path.wrappedValue = NavigationPath()
        path.wrappedValue.append(sessionId)
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

    private func syncPlatformServices() {
        ChatCoordinatorProxy.liveCount = coordinator.liveCount
        ChatCoordinatorProxy.sessions = coordinator.sessions
        WidgetDataStore.shared.syncFromApp()
        let topCustomer = coordinator.sessions.first(where: { $0.isLiveRequest })?.displayName
        LiveActivityManager.shared.updateLiveRequestCount(coordinator.liveCount, topCustomer: topCustomer)
        if auth.isLoggedIn {
            SpotlightIndexer.indexAppContent(auth: auth, coordinator: coordinator)
        }
    }
}

private struct ShellTabBadge: ViewModifier {
    let count: Int
    func body(content: Content) -> some View {
        if count > 0 { content.badge(count) } else { content }
    }
}
