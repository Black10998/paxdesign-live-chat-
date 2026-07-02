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
    @Environment(\.scenePhase) private var scenePhase

    var body: some Scene {
        WindowGroup {
            RootView()
                .environmentObject(auth)
                .environmentObject(coordinator)
                .environmentObject(push)
                .environmentObject(settings)
                .environmentObject(permissions)
                .environmentObject(appLock)
                .environment(\.locale, settings.resolvedLocale)
                .environment(\.paxPalette, settings.palette)
                .modifier(PAXLayoutDirectionModifier())
                .preferredColorScheme(settings.appearanceMode.colorScheme)
                .animation(PAXTheme.fade, value: settings.languageMode)
                .animation(PAXTheme.fade, value: settings.visualTheme)
                .task {
                    await runStartupSequence()
                }
                .onChange(of: auth.isLoggedIn) { loggedIn in
                    if loggedIn {
                        coordinator.start(auth: auth)
                        appLock.prepareForLogin()
                        Task {
                            await permissions.refreshStatuses()
                            permissions.presentNotificationPromptIfNeeded(isLoggedIn: true)
                            await push.registerTokenWithBackend(auth: auth)
                        }
                    } else {
                        coordinator.stop()
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
        await withTaskGroup(of: Void.self) { group in
            group.addTask { await auth.bootstrapSession() }
            group.addTask { try? await Task.sleep(nanoseconds: 1_250_000_000) }
        }

        if auth.isLoggedIn {
            coordinator.start(auth: auth)
            permissions.presentNotificationPromptIfNeeded(isLoggedIn: true)
            await push.registerTokenWithBackend(auth: auth)
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
            await coordinator.handlePush(sessionId: sessionId, type: type, auth: auth, payload: payload)
        }
    }
}

struct RootView: View {
    @EnvironmentObject private var auth: AuthStore
    @EnvironmentObject private var coordinator: ChatCoordinator
    @EnvironmentObject private var permissions: PermissionCoordinator
    @EnvironmentObject private var appLock: AppLockService

    var body: some View {
        ZStack {
            Group {
                if auth.isBootstrapping {
                    PAXLaunchView()
                        .transition(.opacity)
                } else if auth.isLoggedIn {
                    MainShellView()
                        .transition(.opacity.combined(with: .move(edge: .trailing)))
                } else {
                    LoginView()
                        .transition(.opacity.combined(with: .move(edge: .leading)))
                }
            }
            .animation(PAXTheme.spring, value: auth.isBootstrapping)
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
    }
}

struct MainShellView: View {
    @EnvironmentObject private var auth: AuthStore
    @EnvironmentObject private var coordinator: ChatCoordinator
    @EnvironmentObject private var appLock: AppLockService
    @StateObject private var settings = AppSettingsStore.shared
    @State private var chatsPath = NavigationPath()
    @State private var livePath = NavigationPath()
    @State private var selectedTab = 0

    private var unreadChatCount: Int {
        coordinator.sessions.filter {
            $0.needsReply && !settings.readSessionIds.contains($0.sessionId)
        }.count
    }

    private var canViewChats: Bool { auth.canViewChats }
    private var canReplyChats: Bool { auth.canReplyChats }

    var body: some View {
        TabView(selection: $selectedTab) {
            if canViewChats {
                NavigationStack(path: $chatsPath) {
                    SessionListView(onOpenSession: { openSession($0, path: $chatsPath) })
                        .navigationDestination(for: String.self) { sessionId in
                            ChatView(sessionId: sessionId)
                        }
                }
                .tabItem { Label(L10n.TabChats, systemImage: "bubble.left.and.bubble.right") }
                .tag(0)
                .modifier(ChatsTabBadge(count: unreadChatCount))
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
            .tag(canViewChats ? 1 : 0)
            .modifier(LiveTabBadge(count: coordinator.liveCount))

            NavigationStack {
                AccountHubView()
            }
            .tabItem { Label(L10n.TabAccount, systemImage: "person.crop.circle") }
            .tag(canViewChats ? 2 : 1)
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
        .onChange(of: coordinator.activeSessionId) { sessionId in
            guard let sessionId, canViewChats else { return }
            selectedTab = 0
            openSession(sessionId, path: $chatsPath)
        }
        .onChange(of: coordinator.liveCount) { count in
            if count > 0 && selectedTab != 1 {
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
