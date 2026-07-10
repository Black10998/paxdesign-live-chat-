import SwiftUI

struct AdaptiveShellView: View {
    @EnvironmentObject private var auth: AuthStore
    @EnvironmentObject private var coordinator: ChatCoordinator
    @EnvironmentObject private var teamCoordinator: TeamMessagingCoordinator
    @EnvironmentObject private var appLock: AppLockService
    @EnvironmentObject private var settings: AppSettingsStore
    @Environment(\.accessibilityReduceMotion) private var reduceMotion
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

    private var isPad: Bool { UIDevice.current.userInterfaceIdiom == .pad }

    private var unreadChatCount: Int { coordinator.unreadChatCount }
    private var unreadTeamCount: Int { coordinator.unreadTeamCount }

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

    private var iPhoneTabItems: [ShellTabItem] {
        let tags = tabTags
        var items: [ShellTabItem] = [
            .init(
                tag: tags.dashboard,
                title: L10n.TabDashboard,
                symbol: "house",
                selectedSymbol: "house.fill"
            )
        ]

        if canViewChats, let chatsTag = tags.chats {
            items.append(
                .init(
                    tag: chatsTag,
                    title: L10n.TabChats,
                    symbol: "bubble.left.and.bubble.right",
                    selectedSymbol: "bubble.left.and.bubble.right.fill",
                    badgeCount: unreadChatCount
                )
            )
        }

        if canViewChats, let teamTag = tags.team {
            items.append(
                .init(
                    tag: teamTag,
                    title: L10n.TabTeam,
                    symbol: "person.3",
                    selectedSymbol: "person.3.fill",
                    badgeCount: unreadTeamCount
                )
            )
        }

        items.append(
            .init(
                tag: tags.live,
                title: L10n.TabLive,
                symbol: "bell.and.waves.left.and.right",
                selectedSymbol: "bell.and.waves.left.and.right.fill",
                badgeCount: coordinator.liveCount
            )
        )
        items.append(
            .init(
                tag: tags.platform,
                title: L10n.TabPlatform,
                symbol: "square.grid.2x2",
                selectedSymbol: "square.grid.2x2.fill"
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
            if canReplyChats, let incoming = coordinator.incomingRequest, !coordinator.incomingBannerDismissed {
                LiveRequestTopBanner(request: incoming) {
                    coordinator.presentIncomingFullscreen()
                } onDismiss: {
                    coordinator.dismissIncomingBanner()
                }
                .transition(.move(edge: .top).combined(with: .opacity))
            }
        }
        .safeAreaInset(edge: .bottom, spacing: 0) {
            if shouldShowBottomTabBar {
                PAXBottomTabBar(
                    items: iPhoneTabItems,
                    selection: $selectedTab,
                    reduceMotion: reduceMotion
                )
                .transition(.move(edge: .bottom).combined(with: .opacity))
            }
        }
        .animation(reduceMotion ? nil : .spring(response: 0.32, dampingFraction: 0.9), value: shouldShowBottomTabBar)
        .environment(\.shellTabBarVisible, shouldShowBottomTabBar)
        .tint(PAXTheme.accent)
        .sheet(isPresented: $showGlobalSearch) {
            NavigationStack { GlobalSearchView() }
        }
        .onAppear {
            loadedTabs.insert(selectedTab)
            coordinator.updateUnreadCounts(readIds: settings.readSessionIds)
            schedulePlatformSync()
            PAXHaptics.prepare()
        }
        .onChange(of: settings.readSessionIds) { readIds in
            coordinator.updateUnreadCounts(readIds: readIds)
        }
        .onChange(of: coordinator.sessions.count) { _ in schedulePlatformSync() }
        .onChange(of: coordinator.liveCount) { count in
            schedulePlatformSync()
            if count > 0 { PAXHaptics.medium() }
        }
        .onChange(of: coordinator.activeSessionId) { sessionId in
            guard let sessionId else {
                routingSessionId = nil
                return
            }
            guard routingSessionId != sessionId else { return }
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
        return TabView(selection: $selectedTab) {
            lazyTab(tags.dashboard) {
                NavigationStack(path: $dashboardPath) {
                    DashboardView()
                        .navigationDestination(for: String.self, destination: sessionDestination)
                        .navigationDestination(for: PlatformModule.self) { module in
                            platformDestination(for: module)
                        }
                }
            }
            .tabItem { Label(L10n.TabDashboard, systemImage: "house.fill") }
            .tag(tags.dashboard)

            if canViewChats, let chatsTag = tags.chats {
                lazyTab(chatsTag) {
                    NavigationStack(path: $chatsPath) {
                        SessionListView(onOpenSession: { openSession($0, path: $chatsPath) })
                            .navigationDestination(for: String.self, destination: sessionDestination)
                    }
                }
                .tabItem { Label(L10n.TabChats, systemImage: "bubble.left.and.bubble.right") }
                .tag(chatsTag)
                .modifier(ShellTabBadge(count: unreadChatCount))
            }

            if canViewChats, let teamTag = tags.team {
                lazyTab(teamTag) {
                    NavigationStack(path: $teamPath) {
                        TeamMessagesHubView(onOpenSession: { openSession($0, path: $teamPath) })
                            .navigationDestination(for: String.self, destination: teamDestination)
                    }
                }
                .tabItem { Label(L10n.TabTeam, systemImage: "person.3.fill") }
                .tag(teamTag)
                .modifier(ShellTabBadge(count: unreadTeamCount))
            }

            lazyTab(tags.live) {
                NavigationStack(path: $livePath) {
                    LiveTabView(onOpenSession: { openSession($0, path: $livePath) })
                        .navigationDestination(for: String.self) { sessionId in
                            if canViewChats { ChatView(sessionId: sessionId) }
                        }
                }
            }
            .tabItem { Label(L10n.TabLive, systemImage: "bell.and.waves.left.and.right.fill") }
            .tag(tags.live)
            .modifier(ShellTabBadge(count: coordinator.liveCount))

            lazyTab(tags.platform) {
                NavigationStack(path: $platformPath) {
                    PlatformHubView()
                        .navigationDestination(for: String.self, destination: sessionDestination)
                        .navigationDestination(for: PlatformModule.self) { module in
                            platformDestination(for: module)
                        }
                }
            }
            .tabItem { Label(L10n.TabPlatform, systemImage: "square.grid.2x2.fill") }
            .tag(tags.platform)
        }
        .toolbar(.hidden, for: .tabBar)
    }

    @ViewBuilder
    private func lazyTab<Content: View>(_ tag: Int, @ViewBuilder content: () -> Content) -> some View {
        if loadedTabs.contains(tag) {
            content()
        } else {
            Color.clear
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
        guard routingSessionId != sessionId else { return }
        routingSessionId = sessionId
        appLock.recordActivity()
        coordinator.acknowledgeIncomingRequest(sessionId)
        coordinator.activeSessionId = sessionId
        AppRefreshPolicy.update(liveCount: coordinator.liveCount, openChat: true)

        if path.wrappedValue.isEmpty {
            path.wrappedValue.append(sessionId)
        } else {
            path.wrappedValue.removeLast(path.wrappedValue.count)
            path.wrappedValue.append(sessionId)
        }

        Task { @MainActor in
            settings.markSessionRead(sessionId)
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

private struct ShellTabBadge: ViewModifier {
    let count: Int
    func body(content: Content) -> some View {
        if count > 0 { content.badge(count) } else { content }
    }
}

private struct ShellTabItem: Identifiable {
    let tag: Int
    let title: String
    let symbol: String
    let selectedSymbol: String
    var badgeCount: Int = 0

    var id: Int { tag }
}

private struct PAXBottomTabBar: View {
    let items: [ShellTabItem]
    @Binding var selection: Int
    let reduceMotion: Bool

    private var bottomSafeInset: CGFloat {
        max(PAXShellLayout.bottomSafeArea, 6)
    }

    var body: some View {
        VStack(spacing: 0) {
            HStack(spacing: 6) {
                ForEach(items) { item in
                    button(for: item)
                }
            }
            .padding(.horizontal, 10)
            .padding(.top, 8)
            .padding(.bottom, 6)
            .background(tabBarPillBackground)
            .padding(.horizontal, 10)

            Color.clear
                .frame(height: bottomSafeInset)
        }
        .padding(.top, 4)
        .background(tabBarDockBackground)
        .accessibilityElement(children: .contain)
    }

    private var tabBarPillBackground: some View {
        RoundedRectangle(cornerRadius: 24, style: .continuous)
            .fill(.ultraThinMaterial)
            .overlay(
                RoundedRectangle(cornerRadius: 24, style: .continuous)
                    .fill(PAXTheme.surface.opacity(0.34))
            )
            .overlay(
                RoundedRectangle(cornerRadius: 24, style: .continuous)
                    .stroke(PAXTheme.border.opacity(0.46), lineWidth: 1)
            )
            .shadow(color: .black.opacity(0.22), radius: 18, x: 0, y: 10)
    }

    private var tabBarDockBackground: some View {
        LinearGradient(
            colors: [
                PAXTheme.background.opacity(0),
                PAXTheme.background.opacity(0.72),
                PAXTheme.background.opacity(0.94)
            ],
            startPoint: .top,
            endPoint: .bottom
        )
        .ignoresSafeArea(edges: .bottom)
    }

    private func button(for item: ShellTabItem) -> some View {
        let selected = selection == item.tag
        return Button {
            guard selection != item.tag else { return }
            if reduceMotion {
                selection = item.tag
            } else {
                withAnimation(.spring(response: 0.28, dampingFraction: 0.88)) {
                    selection = item.tag
                }
            }
            PAXHaptics.light()
        } label: {
            VStack(spacing: 3) {
                ZStack(alignment: .topTrailing) {
                    PAXAnimatedTabIcon(
                        symbol: item.symbol,
                        selectedSymbol: item.selectedSymbol,
                        isSelected: selected,
                        reduceMotion: reduceMotion
                    )

                    if item.badgeCount > 0 {
                        Text("\(min(item.badgeCount, 99))")
                            .font(.system(size: 9, weight: .bold))
                            .foregroundStyle(.white)
                            .padding(.horizontal, 4.5)
                            .padding(.vertical, 1.5)
                            .background(Capsule().fill(PAXTheme.danger))
                            .offset(x: 10, y: -6)
                            .transition(.scale.combined(with: .opacity))
                    }
                }

                Text(item.title)
                    .font(.system(size: 11, weight: selected ? .semibold : .regular))
                    .foregroundStyle(selected ? PAXTheme.textPrimary : PAXTheme.textSecondary)
                    .lineLimit(1)
                    .minimumScaleFactor(0.85)
            }
            .frame(maxWidth: .infinity)
            .padding(.horizontal, 4)
            .padding(.vertical, 8)
            .background(
                Capsule(style: .continuous)
                    .fill(selected ? PAXTheme.accent.opacity(0.18) : .clear)
            )
            .overlay(
                Capsule(style: .continuous)
                    .stroke(selected ? PAXTheme.accent.opacity(0.42) : .clear, lineWidth: 1)
            )
            .contentShape(Rectangle())
        }
        .buttonStyle(.plain)
        .accessibilityLabel(item.title)
        .accessibilityValue(selected ? "Ausgewählt" : "")
    }
}

private struct PAXAnimatedTabIcon: View {
    let symbol: String
    let selectedSymbol: String
    let isSelected: Bool
    let reduceMotion: Bool

    @State private var pulseScale: CGFloat = 1

    var body: some View {
        Image(systemName: isSelected ? selectedSymbol : symbol)
            .font(.system(size: 18, weight: .semibold))
            .symbolRenderingMode(.hierarchical)
            .foregroundStyle(isSelected ? PAXTheme.accent : PAXTheme.textSecondary)
            .scaleEffect(pulseScale * (isSelected ? 1.04 : 1))
            .rotationEffect(.degrees(isSelected && !reduceMotion ? -2 : 0))
            .animation(.easeOut(duration: 0.16), value: isSelected)
            .onAppear {
                pulseScale = 1
            }
            .onChange(of: isSelected) { selected in
                guard selected, !reduceMotion else {
                    pulseScale = 1
                    return
                }
                pulseScale = 0.9
                withAnimation(.spring(response: 0.24, dampingFraction: 0.5)) {
                    pulseScale = 1.16
                }
                withAnimation(.easeOut(duration: 0.16).delay(0.14)) {
                    pulseScale = 1
                }
            }
    }
}
