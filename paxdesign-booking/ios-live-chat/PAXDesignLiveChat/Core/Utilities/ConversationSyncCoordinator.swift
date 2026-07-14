import Foundation

/// Prevents duplicate heavy `conversations/sync` calls from parallel coordinators.
@MainActor
enum ConversationSyncCoordinator {
    private static var lastFullSyncAt: Date?
    private static var fullSyncInFlight: Task<Void, Never>?
    private static let minInterval: TimeInterval = 60

    static func shouldRunFullSync() -> Bool {
        guard fullSyncInFlight == nil else { return false }
        guard let last = lastFullSyncAt else { return true }
        return Date().timeIntervalSince(last) >= minInterval
    }

    static func beginFullSync() {
        // Retained for tests; unified sync owns lifecycle via fullSyncInFlight.
    }

    static func endFullSync() {
        lastFullSyncAt = Date()
    }

    static func reset() {
        lastFullSyncAt = nil
        fullSyncInFlight?.cancel()
        fullSyncInFlight = nil
    }

    /// Single network round-trip shared by customer + team coordinators.
    static func performUnifiedFullSync(
        auth: AuthStore,
        chatCoordinator: ChatCoordinator,
        teamCoordinator: TeamMessagingCoordinator
    ) async {
        if let task = fullSyncInFlight {
            await task.value
            return
        }
        guard shouldRunFullSync() else { return }
        guard auth.isLoggedIn, auth.api != nil else { return }

        let task = Task {
            await chatCoordinator.performUnifiedConversationSync(auth: auth, teamCoordinator: teamCoordinator)
            endFullSync()
        }
        fullSyncInFlight = task
        defer { fullSyncInFlight = nil }
        await task.value
    }
}
