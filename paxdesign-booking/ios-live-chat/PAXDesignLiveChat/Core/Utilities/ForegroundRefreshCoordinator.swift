import Foundation

/// Debounces expensive foreground refresh work to keep navigation responsive.
@MainActor
enum ForegroundRefreshCoordinator {
    private static var lastRefresh: Date?
    private static var task: Task<Void, Never>?
    private static let minimumInterval: TimeInterval = 8

    static func schedule(
        auth: AuthStore,
        coordinator: ChatCoordinator,
        teamCoordinator: TeamMessagingCoordinator,
        permissions: PermissionCoordinator
    ) {
        guard auth.isLoggedIn, AppRefreshPolicy.isForeground else { return }
        task?.cancel()
        task = Task {
            if let last = lastRefresh, Date().timeIntervalSince(last) < minimumInterval {
                let wait = minimumInterval - Date().timeIntervalSince(last)
                try? await Task.sleep(nanoseconds: UInt64(wait * 1_000_000_000))
            }
            guard !Task.isCancelled else { return }
            lastRefresh = Date()

            let recentPoll = coordinator.lastSessionRefreshAt.map {
                Date().timeIntervalSince($0) < 2.5
            } ?? false

            if !recentPoll {
                await coordinator.refreshSessions(auth: auth, mode: .lightweight)
            }

            await teamCoordinator.refresh(auth: auth, mode: .lightweight)
            await permissions.refreshStatuses()

            Task(priority: .utility) {
                await PlatformSyncService.shared.sync(auth: auth)
            }
        }
    }
}
