import SwiftUI

@main
struct PAXDesignLiveChatApp: App {
    #if !SIDELOAD
    @UIApplicationDelegateAdaptor(AppDelegate.self) private var appDelegate
    #endif
    @StateObject private var coordinator = ChatCoordinator()
    @StateObject private var launchSplash = LaunchSplashController()
    @Environment(\.scenePhase) private var scenePhase

    init() {
        LaunchDiagnostics.mark("App.init")
    }

    var body: some Scene {
        WindowGroup {
            RootView()
                .environmentObject(AuthStore.shared)
                .environmentObject(coordinator)
                .environmentObject(PushService.shared)
                .environmentObject(AppSettingsStore.shared)
                .environmentObject(PermissionCoordinator.shared)
                .environmentObject(AppLockService.shared)
                .environmentObject(TeamMessagingCoordinator.shared)
                .environmentObject(launchSplash)
                .environment(\.locale, AppSettingsStore.shared.resolvedLocale)
                .environment(\.paxPalette, AppSettingsStore.shared.palette)
                .modifier(PAXLayoutDirectionModifier())
                .preferredColorScheme(AppSettingsStore.shared.appearanceMode.colorScheme)
                .task {
                    await runStartupSequence()
                }
                .onChange(of: AuthStore.shared.isLoggedIn) { loggedIn in
                    if loggedIn {
                        AppServicesController.startLoggedInServices(
                            auth: AuthStore.shared,
                            coordinator: coordinator,
                            teamCoordinator: TeamMessagingCoordinator.shared
                        )
                    } else {
                        AppServicesController.stopLoggedInServices(
                            coordinator: coordinator,
                            teamCoordinator: TeamMessagingCoordinator.shared
                        )
                    }
                }
                .onChange(of: scenePhase) { phase in
                    let auth = AuthStore.shared
                    switch phase {
                    case .active:
                        AppRefreshPolicy.update(scenePhase: .active)
                    case .inactive:
                        AppRefreshPolicy.update(scenePhase: .inactive)
                    case .background:
                        AppRefreshPolicy.update(scenePhase: .background)
                    @unknown default:
                        break
                    }
                    AppLockService.shared.handleScenePhase(phase, isLoggedIn: auth.isLoggedIn)
                    guard phase == .active, auth.isLoggedIn else { return }
                    ForegroundRefreshCoordinator.schedule(
                        auth: auth,
                        coordinator: coordinator,
                        permissions: PermissionCoordinator.shared
                    )
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
        LaunchDiagnostics.mark("startup.begin")
        let auth = AuthStore.shared
        let permissions = PermissionCoordinator.shared
        await permissions.refreshStatuses()
        launchSplash.markBootstrapFinished()

        await auth.bootstrapSession()

        if auth.isLoggedIn {
            AppServicesController.startLoggedInServices(
                auth: auth,
                coordinator: coordinator,
                teamCoordinator: TeamMessagingCoordinator.shared
            )
        }

        LaunchDiagnostics.mark("startup.complete")
    }

    private func handlePushNotification(_ note: Notification, opened: Bool) {
        let auth = AuthStore.shared
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
    @EnvironmentObject private var auth: AuthStore
    @EnvironmentObject private var coordinator: ChatCoordinator
    @EnvironmentObject private var permissions: PermissionCoordinator
    @EnvironmentObject private var appLock: AppLockService
    @EnvironmentObject private var settings: AppSettingsStore
    @EnvironmentObject private var launchSplash: LaunchSplashController
    @State private var showFirstRunOnboarding = false

    private enum AppPhase {
        case splash
        case onboarding
        case login
        case main
    }

    private var phase: AppPhase {
        if launchSplash.isVisible || auth.isBootstrapping { return .splash }
        if showFirstRunOnboarding { return .onboarding }
        if auth.isLoggedIn { return .main }
        return .login
    }

    var body: some View {
        ZStack {
            switch phase {
            case .splash:
                PAXLaunchView {
                    launchSplash.markAnimationFinished()
                }
                .transition(.opacity)

            case .onboarding:
                OnboardingFlowView(mode: .firstLaunch) {
                    settings.firstLaunchOnboardingCompleted = true
                    withAnimation(.easeInOut(duration: 0.3)) {
                        showFirstRunOnboarding = false
                    }
                }
                .transition(.opacity)

            case .main:
                AdaptiveShellView()
                    .transition(.opacity)

            case .login:
                LoginView()
                    .transition(.opacity)
            }

            if coordinator.showIncomingFullscreen, let incoming = coordinator.incomingRequest, auth.canReplyChats {
                IncomingLiveRequestView(request: incoming)
                    .zIndex(10)
            }

            if auth.isLoggedIn, appLock.isActive, appLock.isLocked, !appLock.isUnlocked {
                AppLockView()
                    .zIndex(100)
            }
        }
        .animation(.easeInOut(duration: 0.28), value: phaseIdentifier)
        .sheet(isPresented: $permissions.showNotificationPrompt) {
            NotificationPermissionPromptView()
                .presentationDetents([.medium])
                .presentationDragIndicator(.visible)
        }
        .onChange(of: launchSplash.isVisible) { visible in
            guard !visible, !settings.firstLaunchOnboardingCompleted else { return }
            showFirstRunOnboarding = true
        }
        .onChange(of: auth.isLoggedIn) { loggedIn in
            if loggedIn, auth.profile?.onboardingCompleted == true {
                settings.onboardingCompleted = true
            }
        }
    }

    private var phaseIdentifier: String {
        switch phase {
        case .splash: return "splash"
        case .onboarding: return "onboarding"
        case .main: return "main"
        case .login: return "login"
        }
    }
}
