import Foundation

/// Debounces expensive foreground refresh work to keep navigation responsive.
@MainActor
enum ForegroundRefreshCoordinator {
    private static var lastRefresh: Date?
    private static var task: Task<Void, Never>?
    private static let minimumInterval: TimeInterval = 4

    static func schedule(
        auth: AuthStore,
        coordinator: ChatCoordinator,
        permissions: PermissionCoordinator
    ) {
        guard auth.isLoggedIn else { return }
        task?.cancel()
        task = Task {
            if let last = lastRefresh, Date().timeIntervalSince(last) < minimumInterval {
                let wait = minimumInterval - Date().timeIntervalSince(last)
                try? await Task.sleep(nanoseconds: UInt64(wait * 1_000_000_000))
            }
            guard !Task.isCancelled else { return }
            lastRefresh = Date()
            await auth.refreshProfile()
            await coordinator.refreshSessions(auth: auth)
            await permissions.refreshStatuses()
            await PlatformSyncService.shared.sync(auth: auth)
        }
    }
}
