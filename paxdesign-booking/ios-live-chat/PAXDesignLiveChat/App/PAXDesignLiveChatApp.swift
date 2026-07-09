import SwiftUI

@main
struct PAXDesignLiveChatApp: App {
    #if !SIDELOAD
    @UIApplicationDelegateAdaptor(AppDelegate.self) private var appDelegate
    #endif
    @StateObject private var coordinator = ChatCoordinator()
    @State private var showLaunchSplash = true
    @Environment(\.scenePhase) private var scenePhase

    init() {
        LaunchDiagnostics.mark("App.init")
    }

    var body: some Scene {
        WindowGroup {
            RootView(showLaunchSplash: $showLaunchSplash)
                .environmentObject(AuthStore.shared)
                .environmentObject(coordinator)
                .environmentObject(PushService.shared)
                .environmentObject(AppSettingsStore.shared)
                .environmentObject(PermissionCoordinator.shared)
                .environmentObject(AppLockService.shared)
                .environmentObject(TeamMessagingCoordinator.shared)
                .environment(\.locale, AppSettingsStore.shared.resolvedLocale)
                .environment(\.paxPalette, AppSettingsStore.shared.palette)
                .modifier(PAXLayoutDirectionModifier())
                .preferredColorScheme(AppSettingsStore.shared.appearanceMode.colorScheme)
                .onAppear {
                    LaunchDiagnostics.mark("RootView.onAppear")
                }
                .task {
                    await runStartupSequence()
                }
                .onChange(of: AuthStore.shared.isLoggedIn) { loggedIn in
                    let auth = AuthStore.shared
                    if loggedIn {
                        coordinator.start(auth: auth)
                        TeamMessagingCoordinator.shared.start(auth: auth)
                        AppLockService.shared.prepareForLogin()
                        #if !SIDELOAD
                        DeviceSessionService.shared.start(auth: auth)
                        #endif
                        Task {
                            await PermissionCoordinator.shared.refreshStatuses()
                            PermissionCoordinator.shared.presentNotificationPromptIfNeeded(isLoggedIn: true)
                            #if !SIDELOAD
                            await DeviceSessionService.shared.registerWithPush(auth: auth)
                            #endif
                            await PlatformSyncService.shared.sync(auth: auth)
                        }
                    } else {
                        coordinator.stop()
                        TeamMessagingCoordinator.shared.stop()
                        #if !SIDELOAD
                        DeviceSessionService.shared.stop()
                        #endif
                        AppLockService.shared.resetOnLogout()
                    }
                }
                .onChange(of: scenePhase) { phase in
                    let auth = AuthStore.shared
                    AppLockService.shared.handleScenePhase(phase, isLoggedIn: auth.isLoggedIn)
                    guard phase == .active, auth.isLoggedIn else { return }
                    Task {
                        await auth.refreshProfile()
                        await coordinator.refreshSessions(auth: auth)
                        await PermissionCoordinator.shared.refreshStatuses()
                        await PlatformSyncService.shared.sync(auth: auth)
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
        LaunchDiagnostics.mark("startup.begin")
        #if SIDELOAD
        // Avoid launch-time side effects in sideload builds; wait for explicit user login.
        await PermissionCoordinator.shared.refreshStatuses()
        AuthStore.shared.finishBootstrap()
        LaunchDiagnostics.mark("startup.sideload.complete")
        return
        #else
        let auth = AuthStore.shared
        let permissions = PermissionCoordinator.shared
        await permissions.refreshStatuses()
        await auth.bootstrapSession()

        if auth.isLoggedIn {
            coordinator.start(auth: auth)
            TeamMessagingCoordinator.shared.start(auth: auth)
            DeviceSessionService.shared.start(auth: auth)
            permissions.presentNotificationPromptIfNeeded(isLoggedIn: true)
            await DeviceSessionService.shared.registerWithPush(auth: auth)
            await PlatformSyncService.shared.sync(auth: auth)
        }
        LaunchDiagnostics.mark("startup.complete")
        #endif
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
    @Binding var showLaunchSplash: Bool
    @EnvironmentObject private var auth: AuthStore
    @EnvironmentObject private var coordinator: ChatCoordinator
    @EnvironmentObject private var permissions: PermissionCoordinator
    @EnvironmentObject private var appLock: AppLockService
    @EnvironmentObject private var settings: AppSettingsStore
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
                    AdaptiveShellView()
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
