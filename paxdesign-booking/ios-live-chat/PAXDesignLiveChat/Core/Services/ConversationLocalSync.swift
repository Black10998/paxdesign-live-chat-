import Foundation

@MainActor
final class ConversationLocalSync {
    static let shared = ConversationLocalSync()

    private init() {}

    func apply(_ response: ConversationSyncResponse) {
        for (sessionId, thread) in response.threads {
            ConversationHistoryStore.shared.save(thread, sessionId: sessionId)
            ChatThreadRegistry.shared
                .bookingThread(sessionId: sessionId)
                .applySilentSync(thread)
        }
        for (sessionId, thread) in response.teamThreads {
            ConversationHistoryStore.shared.save(thread, sessionId: sessionId)
            ChatThreadRegistry.shared
                .teamThread(sessionId: sessionId)
                .applySilentSync(thread)
        }
    }

    func syncAll(api: LiveChatAPI) async {
        do {
            let response = try await api.fetchConversationSync()
            apply(response)
        } catch {
            // Background sync must never block or disturb the UI.
        }
    }
}
