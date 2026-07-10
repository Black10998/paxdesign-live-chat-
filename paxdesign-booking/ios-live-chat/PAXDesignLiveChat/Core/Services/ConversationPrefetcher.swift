import Foundation

@MainActor
final class ConversationPrefetcher {
    static let shared = ConversationPrefetcher()

    private var inflight = Set<String>()
    private var queued = Set<String>()

    private init() {}

    func schedulePrefetch(sessionId: String, api: LiveChatAPI, isTeam: Bool, priority: Bool = false) {
        guard !sessionId.isEmpty else { return }
        if ConversationHistoryStore.shared.isFresh(sessionId) { return }
        if inflight.contains(sessionId) { return }
        if !priority && queued.contains(sessionId) { return }
        queued.insert(sessionId)

        Task(priority: priority ? .userInitiated : .utility) { [weak self] in
            await self?.prefetch(sessionId: sessionId, api: api, isTeam: isTeam)
        }
    }

    func prefetchFromSessions(_ sessions: [LiveSession], api: LiveChatAPI) {
        let sorted = sessions.sorted { $0.updatedAt > $1.updatedAt }
        for session in sorted.prefix(24) {
            schedulePrefetch(
                sessionId: session.sessionId,
                api: api,
                isTeam: session.isTeamDM,
                priority: false
            )
        }
    }

    func prefetch(sessionId: String, api: LiveChatAPI, isTeam: Bool) async {
        guard !sessionId.isEmpty else { return }
        if ConversationHistoryStore.shared.isFresh(sessionId) {
            queued.remove(sessionId)
            return
        }
        guard !inflight.contains(sessionId) else { return }
        inflight.insert(sessionId)
        defer {
            inflight.remove(sessionId)
            queued.remove(sessionId)
        }

        do {
            let response: PollResponse
            if isTeam {
                response = try await api.pollTeamSession(sessionId, since: 0, full: true)
            } else {
                response = try await api.fetchSession(sessionId)
            }
            ConversationHistoryStore.shared.save(response, sessionId: sessionId)
        } catch {
            // Prefetch is best-effort; open thread will fetch on demand.
        }
    }
}
