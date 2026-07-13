import Foundation

/// Prevents duplicate heavy `conversations/sync` calls from parallel coordinators.
@MainActor
enum ConversationSyncCoordinator {
    private static var lastFullSyncAt: Date?
    private static var fullSyncInFlight = false
    private static let minInterval: TimeInterval = 60

    static func shouldRunFullSync() -> Bool {
        guard !fullSyncInFlight else { return false }
        guard let last = lastFullSyncAt else { return true }
        return Date().timeIntervalSince(last) >= minInterval
    }

    static func beginFullSync() {
        fullSyncInFlight = true
    }

    static func endFullSync() {
        fullSyncInFlight = false
        lastFullSyncAt = Date()
    }

    static func reset() {
        lastFullSyncAt = nil
        fullSyncInFlight = false
    }
}
