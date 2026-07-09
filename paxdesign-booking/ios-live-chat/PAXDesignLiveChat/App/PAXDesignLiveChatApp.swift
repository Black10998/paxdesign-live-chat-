import SwiftUI

@main
struct PAXDesignLiveChatApp: App {
    @UIApplicationDelegateAdaptor(AppDelegate.self) private var appDelegate
    @StateObject private var auth = AuthStore.shared
    @StateObject private var coordinator = ChatCoordinator()
    @StateObject private var push = PushService.shared
    @StateObject private var settings = AppSettingsStore.shared
    @StateObject private var permissions = PermissionCoordinator.shared
    @StateObject private var appLock = AppLockService.shared
    @StateObject private var teamCoordinator = TeamMessagingCoordinator.shared
    @State private var showLaunchSplash = true
    @Environment(\.scenePhase) private var scenePhase

    var body: some Scene {
        WindowGroup {
            RootView(showLaunchSplash: $showLaunchSplash)
                .environmentObject(auth)
                .environmentObject(coordinator)
                .environmentObject(push)
                .environmentObject(settings)
                .environmentObject(permissions)
                .environmentObject(appLock)
                .environmentObject(teamCoordinator)
                .environment(\.locale, settings.resolvedLocale)
                .environment(\.paxPalette, settings.palette)
                .modifier(PAXLayoutDirectionModifier())
                .preferredColorScheme(settings.appearanceMode.colorScheme)
                .task {
                    await runStartupSequence()
                }
                .onChange(of: auth.isLoggedIn) { loggedIn in
                    if loggedIn {
                        coordinator.start(auth: auth)
                        teamCoordinator.start(auth: auth)
                        appLock.prepareForLogin()
                        DeviceSessionService.shared.start(auth: auth)
                        Task {
                            await permissions.refreshStatuses()
                            permissions.presentNotificationPromptIfNeeded(isLoggedIn: true)
                            await DeviceSessionService.shared.registerWithPush(auth: auth)
                        }
                    } else {
                        coordinator.stop()
                        teamCoordinator.stop()
                        DeviceSessionService.shared.stop()
                        appLock.resetOnLogout()
                    }
                }
                .onChange(of: scenePhase) { phase in
                    appLock.handleScenePhase(phase, isLoggedIn: auth.isLoggedIn)
                    guard phase == .active, auth.isLoggedIn else { return }
                    Task {
                        await auth.refreshProfile()
                        await coordinator.refreshSessions(auth: auth)
                        await permissions.refreshStatuses()
                    }
                }
                .onReceive(NotificationCenter.default.publisher(for: .paxPushReceived)) { note in
                    handlePushNotification(note, opened: false)
                }
                .onReceive(NotificationCenter.default.publisher(for: .paxPushOpened)) { note in
                    handlePushNotification(note, opened: true)
                }
        }
    }

    private func runStartupSequence() async {
        await permissions.refreshStatuses()
        await auth.bootstrapSession()

        if auth.isLoggedIn {
            coordinator.start(auth: auth)
            teamCoordinator.start(auth: auth)
            DeviceSessionService.shared.start(auth: auth)
            permissions.presentNotificationPromptIfNeeded(isLoggedIn: true)
            await DeviceSessionService.shared.registerWithPush(auth: auth)
        }
    }

    private func handlePushNotification(_ note: Notification, opened: Bool) {
        guard auth.isLoggedIn,
              let sessionId = note.userInfo?["session_id"] as? String,
              let type = note.userInfo?["type"] as? String else { return }

        let payload = PushService.PushPayload(
            sessionId: sessionId,
            type: type,
            customerName: note.userInfo?["customer_name"] as? String ?? "",
            service: note.userInfo?["service"] as? String ?? "",
            preview: note.userInfo?["preview"] as? String ?? ""
        )

        Task { @MainActor in
            if opened, let action = note.userInfo?["action"] as? String, action.hasPrefix("PAX_") {
                await coordinator.handlePushAction(action, sessionId: sessionId, auth: auth)
                return
            }
            if !opened {
                InAppNotificationCoordinator.shared.handlePushForeground(
                    type: type,
                    sessionId: sessionId,
                    preview: payload.preview,
                    customerName: payload.customerName,
                    activeSessionId: coordinator.activeSessionId
                )
            }
            await coordinator.handlePush(sessionId: sessionId, type: type, auth: auth, payload: payload)
        }
    }
}

struct RootView: View {
    @Binding var showLaunchSplash: Bool
    @EnvironmentObject private var auth: AuthStore
    @EnvironmentObject private var coordinator: ChatCoordinator
    @EnvironmentObject private var permissions: PermissionCoordinator
    @EnvironmentObject private var appLock: AppLockService
    @StateObject private var settings = AppSettingsStore.shared
    @State private var showOnboarding = false

    private var needsOnboarding: Bool {
        auth.isLoggedIn
            && !settings.onboardingCompleted
            && !(auth.profile?.onboardingCompleted ?? false)
    }

    var body: some View {
        ZStack {
            Group {
                if showLaunchSplash {
                    PAXLaunchView {
                        withAnimation(PAXTheme.fade) {
                            showLaunchSplash = false
                        }
                    }
                    .transition(.opacity)
                } else if auth.isLoggedIn {
                    MainShellView()
                        .transition(.opacity.combined(with: .move(edge: .trailing)))
                } else {
                    LoginView()
                        .transition(.opacity.combined(with: .move(edge: .leading)))
                }
            }
            .animation(PAXTheme.spring, value: showLaunchSplash)
            .animation(PAXTheme.spring, value: auth.isLoggedIn)

            if coordinator.showIncomingFullscreen, let incoming = coordinator.incomingRequest, auth.canReplyChats {
                IncomingLiveRequestView(request: incoming)
                    .transition(.opacity.combined(with: .scale(scale: 0.96)))
                    .zIndex(10)
            }

            if auth.isLoggedIn, appLock.isActive, appLock.isLocked, !appLock.isUnlocked {
                AppLockView()
                    .transition(.opacity)
                    .zIndex(100)
            }
        }
        .animation(PAXTheme.spring, value: coordinator.incomingRequest?.id)
        .animation(PAXTheme.spring, value: coordinator.showIncomingFullscreen)
        .animation(PAXTheme.fade, value: appLock.isLocked)
        .sheet(isPresented: $permissions.showNotificationPrompt) {
            NotificationPermissionPromptView()
                .presentationDetents([.medium])
                .presentationDragIndicator(.visible)
        }
        .fullScreenCover(isPresented: $showOnboarding) {
            OnboardingFlowView {
                showOnboarding = false
            }
        }
        .onChange(of: showLaunchSplash) { splash in
            guard !splash, needsOnboarding else { return }
            showOnboarding = true
        }
        .onChange(of: auth.isLoggedIn) { loggedIn in
            if loggedIn, !showLaunchSplash, needsOnboarding {
                showOnboarding = true
            }
            if loggedIn, auth.profile?.onboardingCompleted == true {
                settings.onboardingCompleted = true
            }
        }
    }
}


struct MainShellView: View {
    @EnvironmentObject private var auth: AuthStore
    @EnvironmentObject private var coordinator: ChatCoordinator
    @EnvironmentObject private var teamCoordinator: TeamMessagingCoordinator
    @EnvironmentObject private var appLock: AppLockService
    @StateObject private var settings = AppSettingsStore.shared
    @State private var chatsPath = NavigationPath()
    @State private var teamPath = NavigationPath()
    @State private var livePath = NavigationPath()
    @State private var selectedTab = 0

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
    private var showTeamTab: Bool { canViewChats }

    private var tabTags: (chats: Int?, team: Int?, live: Int, platform: Int) {
        var tag = 0
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
        return (chats, team, live, platform)
    }

    var body: some View {
        let tags = tabTags
        TabView(selection: $selectedTab) {
            if canViewChats, let chatsTag = tags.chats {
                NavigationStack(path: $chatsPath) {
                    SessionListView(onOpenSession: { openSession($0, path: $chatsPath) })
                        .navigationDestination(for: String.self) { sessionId in
                            ChatView(sessionId: sessionId)
                        }
                }
                .tabItem { Label(L10n.TabChats, systemImage: "bubble.left.and.bubble.right") }
                .tag(chatsTag)
                .modifier(ChatsTabBadge(count: unreadChatCount))
            }

            if showTeamTab, let teamTag = tags.team {
                NavigationStack(path: $teamPath) {
                    TeamMessagesHubView(onOpenSession: { openSession($0, path: $teamPath) })
                        .navigationDestination(for: String.self) { sessionId in
                            TeamChatView(sessionId: sessionId)
                        }
                }
                .tabItem { Label(L10n.TabTeam, systemImage: "person.3.fill") }
                .tag(teamTag)
                .modifier(ChatsTabBadge(count: unreadTeamCount))
            }

            NavigationStack(path: $livePath) {
                LiveTabView(onOpenSession: { openSession($0, path: $livePath) })
                    .navigationDestination(for: String.self) { sessionId in
                        if canViewChats {
                            ChatView(sessionId: sessionId)
                        }
                    }
            }
            .tabItem { Label(L10n.TabLive, systemImage: "bell.and.waves.left.and.right.fill") }
            .tag(tags.live)
            .modifier(LiveTabBadge(count: coordinator.liveCount))

            NavigationStack {
                PlatformHubView()
                    .navigationDestination(for: String.self) { sessionId in
                        if sessionId.hasPrefix("team_") {
                            TeamChatView(sessionId: sessionId)
                        } else if canViewChats {
                            ChatView(sessionId: sessionId)
                        }
                    }
            }
            .tabItem { Label(L10n.TabPlatform, systemImage: "square.grid.2x2.fill") }
            .tag(tags.platform)
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
        .onChange(of: coordinator.activeSessionId) { sessionId in
            guard let sessionId, canViewChats else { return }
            let tags = tabTags
            if sessionId.hasPrefix("team_"), let teamTag = tags.team {
                selectedTab = teamTag
                openSession(sessionId, path: $teamPath)
            } else if let chatsTag = tags.chats {
                selectedTab = chatsTag
                openSession(sessionId, path: $chatsPath)
            }
        }
        .onChange(of: coordinator.liveCount) { count in
            if count > 0 && selectedTab != tabTags.live {
                PAXHaptics.medium()
            }
        }
        .onChange(of: selectedTab) { _ in
            appLock.recordActivity()
            PAXKeyboard.dismiss()
        }
    }

    private func openSession(_ sessionId: String, path: Binding<NavigationPath>) {
        appLock.recordActivity()
        coordinator.acknowledgeIncomingRequest(sessionId)
        settings.readSessionIds.insert(sessionId)
        path.wrappedValue = NavigationPath()
        path.wrappedValue.append(sessionId)
    }
}

private struct LiveTabBadge: ViewModifier {
    let count: Int

    func body(content: Content) -> some View {
        if count > 0 {
            content.badge(count)
        } else {
            content
        }
    }
}

private struct ChatsTabBadge: ViewModifier {
    let count: Int

    func body(content: Content) -> some View {
        if count > 0 {
            content.badge(count)
        } else {
            content
        }
    }
}
