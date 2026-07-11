import Foundation

@MainActor
final class ChatThreadRegistry {
    static let shared = ChatThreadRegistry()

    private var bookingThreads: [String: ChatThreadModel] = [:]
    private var teamThreads: [String: TeamChatThreadModel] = [:]
    private let maxThreads = 300

    private init() {}

    func bookingThread(sessionId: String) -> ChatThreadModel {
        if let existing = bookingThreads[sessionId] {
            touch(sessionId: sessionId, isTeam: false)
            return existing
        }
        let thread = ChatThreadModel(sessionId: sessionId)
        thread.hydrateFromLocalStore()
        bookingThreads[sessionId] = thread
        touch(sessionId: sessionId, isTeam: false)
        evictIfNeeded()
        return thread
    }

    func teamThread(sessionId: String) -> TeamChatThreadModel {
        if let existing = teamThreads[sessionId] {
            touch(sessionId: sessionId, isTeam: true)
            return existing
        }
        let thread = TeamChatThreadModel(sessionId: sessionId)
        thread.hydrateFromLocalStore()
        teamThreads[sessionId] = thread
        touch(sessionId: sessionId, isTeam: true)
        evictIfNeeded()
        return thread
    }

    func clearAll() {
        for thread in bookingThreads.values {
            thread.suspend()
        }
        for thread in teamThreads.values {
            thread.suspend()
        }
        bookingThreads.removeAll()
        teamThreads.removeAll()
    }

    func updateQuickLinks(_ links: [QuickLink]) {
        for thread in bookingThreads.values {
            thread.quickLinks = links
        }
    }

    private var recentOrder: [String] = []

    private func touch(sessionId: String, isTeam: Bool) {
        let key = (isTeam ? "t:" : "b:") + sessionId
        recentOrder.removeAll { $0 == key }
        recentOrder.append(key)
    }

    private func evictIfNeeded() {
        while bookingThreads.count + teamThreads.count > maxThreads, let oldest = recentOrder.first {
            recentOrder.removeFirst()
            if oldest.hasPrefix("t:") {
                let id = String(oldest.dropFirst(2))
                teamThreads[id]?.suspend()
                teamThreads.removeValue(forKey: id)
            } else if oldest.hasPrefix("b:") {
                let id = String(oldest.dropFirst(2))
                bookingThreads[id]?.suspend()
                bookingThreads.removeValue(forKey: id)
            }
        }
    }
}
