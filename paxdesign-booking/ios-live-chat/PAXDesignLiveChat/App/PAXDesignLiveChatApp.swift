import SwiftUI

@main
struct PAXDesignLiveChatApp: App {
    @UIApplicationDelegateAdaptor(AppDelegate.self) private var appDelegate
    @StateObject private var auth = AuthStore.shared
    @StateObject private var coordinator = ChatCoordinator()
    @StateObject private var push = PushService.shared
    @StateObject private var settings = AppSettingsStore.shared
    @Environment(\.scenePhase) private var scenePhase

    var body: some Scene {
        WindowGroup {
            RootView()
                .environmentObject(auth)
                .environmentObject(coordinator)
                .environmentObject(push)
                .environmentObject(settings)
                .preferredColorScheme(.dark)
                .task {
                    await push.requestAuthorization()
                    await auth.bootstrapSession()
                    if auth.isLoggedIn {
                        coordinator.start(auth: auth)
                        await push.registerTokenWithBackend(auth: auth)
                    }
                }
                .onChange(of: auth.isLoggedIn) { loggedIn in
                    if loggedIn {
                        coordinator.start(auth: auth)
                        Task { await push.registerTokenWithBackend(auth: auth) }
                    } else {
                        coordinator.stop()
                    }
                }
                .onChange(of: scenePhase) { phase in
                    guard phase == .active, auth.isLoggedIn else { return }
                    Task { await coordinator.refreshSessions(auth: auth) }
                }
                .onReceive(NotificationCenter.default.publisher(for: .paxPushReceived)) { note in
                    handlePushNotification(note, opened: false)
                }
                .onReceive(NotificationCenter.default.publisher(for: .paxPushOpened)) { note in
                    handlePushNotification(note, opened: true)
                }
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

    var body: some View {
        ZStack {
            Group {
                if auth.isBootstrapping {
                    PAXSplashView()
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

            if let incoming = coordinator.incomingRequest {
                IncomingLiveRequestView(request: incoming)
                    .transition(.opacity.combined(with: .scale(scale: 0.96)))
                    .zIndex(10)
            }
        }
        .animation(PAXTheme.spring, value: coordinator.incomingRequest?.id)
    }
}

struct MainShellView: View {
    @EnvironmentObject private var auth: AuthStore
    @EnvironmentObject private var coordinator: ChatCoordinator
    @State private var navigationPath = NavigationPath()
    @State private var selectedTab = 0

    var body: some View {
        TabView(selection: $selectedTab) {
            NavigationStack(path: $navigationPath) {
                SessionListView(onOpenSession: openSession)
                    .navigationDestination(for: String.self) { sessionId in
                        ChatView(sessionId: sessionId)
                    }
            }
            .tabItem {
                Label("Chats", systemImage: "bubble.left.and.bubble.right")
            }
            .tag(0)

            NavigationStack {
                AccountHubView()
            }
            .tabItem {
                Label("Konto", systemImage: "person.crop.circle")
            }
            .tag(1)
        }
        .onChange(of: coordinator.activeSessionId) { sessionId in
            guard let sessionId else { return }
            selectedTab = 0
            openSession(sessionId)
        }
    }

    private func openSession(_ sessionId: String) {
        coordinator.acknowledgeIncomingRequest(sessionId)
        navigationPath = NavigationPath()
        navigationPath.append(sessionId)
    }
}
