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
        .overlay(alignment: .bottom) {
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
        .environment(\.shellTabBarScrollInset, shouldShowBottomTabBar ? PAXShellLayout.tabBarScrollInset : 0)
        .tint(PAXTheme.accent)
        .sheet(isPresented: $showGlobalSearch) {
            NavigationStack { GlobalSearchView() }
        }
        .onAppear {
            loadedTabs.insert(selectedTab)
            coordinator.updateUnreadCounts()
            schedulePlatformSync()
            PAXHaptics.prepare()
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
            if let session = coordinator.sessions.first(where: { $0.sessionId == sessionId })
                ?? teamCoordinator.teamSessions.first(where: { $0.sessionId == sessionId }) {
                settings.markSessionRead(sessionId, seq: session.seq)
            } else {
                settings.markSessionRead(sessionId)
            }
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
    let symbol: String
    let selectedSymbol: String
    var badgeCount: Int = 0

    var id: Int { tag }
}

private struct PAXBottomTabBar: View {
    let items: [ShellTabItem]
    @Binding var selection: Int
    let reduceMotion: Bool

    var body: some View {
        VStack(spacing: 0) {
            Rectangle()
                .fill(PAXTheme.border.opacity(0.35))
                .frame(height: 0.33)

            HStack(spacing: 0) {
                ForEach(items) { item in
                    button(for: item)
                }
            }
            .frame(maxWidth: .infinity)
            .frame(height: PAXShellLayout.tabBarContentHeight)
            .padding(.horizontal, 4)
        }
        .background {
            PAXTabBarGlassBackground()
                .ignoresSafeArea(edges: .bottom)
        }
        .accessibilityElement(children: .contain)
    }

    private func button(for item: ShellTabItem) -> some View {
        let selected = selection == item.tag
        return Button {
            guard selection != item.tag else { return }
            if reduceMotion {
                selection = item.tag
            } else {
                withAnimation(.spring(response: 0.42, dampingFraction: 0.84)) {
                    selection = item.tag
                }
            }
            PAXHaptics.light()
        } label: {
            VStack(spacing: 4) {
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
                    .font(.system(size: 10, weight: selected ? .semibold : .regular))
                    .foregroundStyle(selected ? PAXTheme.textPrimary : PAXTheme.textSecondary)
                    .lineLimit(1)
                    .minimumScaleFactor(0.85)
                    .offset(y: selected ? -1 : 0)
                    .animation(.spring(response: 0.36, dampingFraction: 0.82), value: selected)
            }
            .frame(maxWidth: .infinity)
            .padding(.top, 6)
            .padding(.bottom, 2)
            .contentShape(Rectangle())
        }
        .buttonStyle(.plain)
        .accessibilityLabel(item.title)
        .accessibilityValue(selected ? "Ausgewählt" : "")
    }
}

private struct PAXTabBarGlassBackground: View {
    @Environment(\.colorScheme) private var colorScheme
    @Environment(\.accessibilityReduceMotion) private var reduceMotion
    @State private var shimmerPhase: CGFloat = -1

    var body: some View {
        ZStack {
            Rectangle()
                .fill(.ultraThinMaterial)

            Rectangle()
                .fill(.bar.opacity(colorScheme == .dark ? 0.55 : 0.42))

            LinearGradient(
                colors: [
                    Color.white.opacity(colorScheme == .dark ? 0.16 : 0.34),
                    Color.white.opacity(colorScheme == .dark ? 0.05 : 0.1),
                    .clear
                ],
                startPoint: .top,
                endPoint: .center
            )
            .blendMode(.overlay)

            if !reduceMotion {
                LinearGradient(
                    colors: [
                        .clear,
                        Color.white.opacity(colorScheme == .dark ? 0.12 : 0.22),
                        .clear
                    ],
                    startPoint: .leading,
                    endPoint: .trailing
                )
                .scaleEffect(x: 2.2, y: 1)
                .offset(x: shimmerPhase * 220)
                .blendMode(.plusLighter)
                .allowsHitTesting(false)
            }

            VStack(spacing: 0) {
                Rectangle()
                    .fill(
                        LinearGradient(
                            colors: [
                                Color.white.opacity(colorScheme == .dark ? 0.28 : 0.55),
                                Color.white.opacity(0.08),
                                .clear
                            ],
                            startPoint: .top,
                            endPoint: .bottom
                        )
                    )
                    .frame(height: 0.66)
                Spacer(minLength: 0)
            }
        }
        .onAppear {
            guard !reduceMotion else { return }
            shimmerPhase = -1
            withAnimation(.linear(duration: 4.8).repeatForever(autoreverses: false)) {
                shimmerPhase = 1
            }
        }
    }
}

private struct PAXAnimatedTabIcon: View {
    let symbol: String
    let selectedSymbol: String
    let isSelected: Bool
    let reduceMotion: Bool

    @State private var pulseScale: CGFloat = 1
    @State private var slideOffset: CGFloat = 0

    var body: some View {
        ZStack {
            Image(systemName: symbol)
                .font(.system(size: 18, weight: .semibold))
                .symbolRenderingMode(.hierarchical)
                .foregroundStyle(PAXTheme.textSecondary)
                .opacity(isSelected ? 0 : 1)
                .offset(x: isSelected ? -10 : 0)
                .scaleEffect(isSelected ? 0.82 : 1)

            Image(systemName: selectedSymbol)
                .font(.system(size: 18, weight: .semibold))
                .symbolRenderingMode(.hierarchical)
                .foregroundStyle(PAXTheme.accent)
                .opacity(isSelected ? 1 : 0)
                .offset(x: isSelected ? 0 : 10)
                .scaleEffect(isSelected ? 1.06 : 0.82)
        }
        .scaleEffect(pulseScale)
        .offset(x: slideOffset)
        .animation(.spring(response: 0.38, dampingFraction: 0.78), value: isSelected)
        .onChange(of: isSelected) { selected in
            guard selected, !reduceMotion else {
                pulseScale = 1
                slideOffset = 0
                return
            }
            slideOffset = -6
            pulseScale = 0.88
            withAnimation(.spring(response: 0.34, dampingFraction: 0.62)) {
                slideOffset = 0
                pulseScale = 1.12
            }
            withAnimation(.easeOut(duration: 0.18).delay(0.12)) {
                pulseScale = 1
            }
        }
    }
}
