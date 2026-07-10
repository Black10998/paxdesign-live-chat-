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
            let status = PermissionCoordinator.shared.notificationStatus
            if status == .authorized || status == .provisional || status == .ephemeral {
                await MainActor.run {
                    UIApplication.shared.registerForRemoteNotifications()
                }
            }
            await DeviceSessionService.shared.registerWithPush(auth: auth)
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
        #if !SIDELOAD
        DeviceSessionService.shared.stop()
        #endif
        AppLockService.shared.resetOnLogout()
    }
}
