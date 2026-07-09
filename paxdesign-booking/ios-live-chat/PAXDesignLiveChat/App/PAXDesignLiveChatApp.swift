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
                            await PlatformSyncService.shared.sync(auth: auth)
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
        await permissions.refreshStatuses()
        await auth.bootstrapSession()

        if auth.isLoggedIn {
            coordinator.start(auth: auth)
            teamCoordinator.start(auth: auth)
            DeviceSessionService.shared.start(auth: auth)
            permissions.presentNotificationPromptIfNeeded(isLoggedIn: true)
            await DeviceSessionService.shared.registerWithPush(auth: auth)
            await PlatformSyncService.shared.sync(auth: auth)
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


struct MainShellView: View {
    var body: some View {
        AdaptiveShellView()
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
