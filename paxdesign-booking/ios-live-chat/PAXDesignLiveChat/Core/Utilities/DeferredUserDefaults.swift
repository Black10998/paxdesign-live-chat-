import Foundation

/// Batches UserDefaults writes off the hot path so taps and navigation stay instant.
enum DeferredUserDefaults {
    private static var tasks: [String: Task<Void, Never>] = [:]

    static func set(_ value: Any?, forKey key: String, delayNanoseconds: UInt64 = 400_000_000) {
        tasks[key]?.cancel()
        tasks[key] = Task {
            try? await Task.sleep(nanoseconds: delayNanoseconds)
            guard !Task.isCancelled else { return }
            await Task.detached(priority: .utility) {
                UserDefaults.standard.set(value, forKey: key)
            }.value
        }
    }

    static func setData(_ data: Data?, forKey key: String, delayNanoseconds: UInt64 = 400_000_000) {
        set(data, forKey: key, delayNanoseconds: delayNanoseconds)
    }
}
