import Foundation

enum SiteScopeKey {
    static func make(_ value: String) -> String {
        var hash: UInt64 = 14_695_981_039_346_656_037
        for byte in value.utf8 {
            hash ^= UInt64(byte)
            hash &*= 1_099_511_628_211
        }
        return String(hash, radix: 16)
    }
}

final class ChatCursorStore {
    static let shared = ChatCursorStore()

    private let defaults = UserDefaults.standard
    private let lock = NSLock()
    private let consumerKey = "pax.chat.consumer-id"

    private init() {}

    var consumerId: String {
        lock.lock()
        defer { lock.unlock() }
        if let existing = defaults.string(forKey: consumerKey), !existing.isEmpty {
            return existing
        }
        let created = UUID().uuidString.lowercased()
        defaults.set(created, forKey: consumerKey)
        return created
    }

    func eventCursor(site: String, channel: String) -> Int {
        lock.lock()
        defer { lock.unlock() }
        return defaults.integer(forKey: key(site: site, channel: channel))
    }

    func advance(site: String, channel: String, eventId: Int) {
        guard eventId > 0 else { return }
        lock.lock()
        defer { lock.unlock() }
        let storageKey = key(site: site, channel: channel)
        if eventId > defaults.integer(forKey: storageKey) {
            defaults.set(eventId, forKey: storageKey)
        }
    }

    private func key(site: String, channel: String) -> String {
        "pax.chat.cursor.\(SiteScopeKey.make(site)).\(channel)"
    }
}
