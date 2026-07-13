import Foundation
#if !SIDELOAD
import UIKit
#endif

/// Ensures login side-effects (polling, push, sync) start exactly once per session.
@MainActor
enum AppServicesController {
    private static var didStart = false

    static func startLoggedInServices(
        auth: AuthStore,
        coordinator: ChatCoordinator,
        teamCoordinator: TeamMessagingCoordinator
    ) {
        guard auth.isLoggedIn, !didStart else { return }
        didStart = true

        if let api = auth.api {
            ConversationHistoryStore.shared.setSiteScope(api.publicApiBaseURL)
            SessionListCache.shared.setSiteScope(api.publicApiBaseURL)
            ConversationHistoryStore.shared.warmAllFromDisk()
        }

        coordinator.start(auth: auth)
        teamCoordinator.start(auth: auth)
        AppLockService.shared.prepareForLogin()

        #if !SIDELOAD
        DeviceSessionService.shared.start(auth: auth)
        #endif

        Task(priority: .utility) {
            await PermissionCoordinator.shared.refreshStatuses()
            #if !SIDELOAD
            await DeviceSessionService.shared.registerWithPush(auth: auth, reason: .login)
            #endif
            await PlatformSyncService.shared.sync(auth: auth)
            #if !SIDELOAD
            SpotlightIndexer.indexAppContent(auth: auth, coordinator: coordinator)
            #endif
        }
    }

    static func stopLoggedInServices(
        coordinator: ChatCoordinator,
        teamCoordinator: TeamMessagingCoordinator
    ) {
        didStart = false
        coordinator.stop()
        teamCoordinator.stop()
        ChatThreadRegistry.shared.clearAll()
        ConversationHistoryStore.shared.clearAll()
        SessionListCache.shared.clear()
        PendingMessageStore.shared.clearAll()
        ConversationSyncCoordinator.reset()
        NetworkRequestTracker.shared.reset()
        NetworkCircuitBreaker.shared.reset()
        HTTPResponseForensics.shared.reset()
        AppRefreshPolicy.sseHealthy = false
        #if !SIDELOAD
        DeviceSessionService.shared.stop()
        PushRegistrationCoordinator.reset()
        #endif
        AppLockService.shared.resetOnLogout()
    }
}
