import Foundation

/// Coalesces overlapping identical refresh operations.
@MainActor
final class RequestInFlightGuard {
    private var keys = Set<String>()

    func tryEnter(_ key: String) -> Bool {
        guard !keys.contains(key) else { return false }
        keys.insert(key)
        return true
    }

    func leave(_ key: String) {
        keys.remove(key)
    }
}
