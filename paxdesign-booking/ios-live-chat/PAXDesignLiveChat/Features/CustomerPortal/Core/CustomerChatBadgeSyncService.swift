import Foundation

/// Background SSE listener so the Chat tab badge updates instantly even when the chat screen is not loaded.
@MainActor
final class CustomerChatBadgeSyncService {
    static let shared = CustomerChatBadgeSyncService()

    private var streamTask: Task<Void, Never>?
    private var streamSince = 0
    private var activeUserId = 0

    private init() {}

    func start(api: CustomerAPIClient, userId: Int) {
        guard userId > 0 else {
            stop()
            return
        }
        if activeUserId == userId, streamTask != nil, !(streamTask?.isCancelled ?? true) {
            return
        }
        stop()
        activeUserId = userId
        CustomerChatBadgeStore.shared.configure(userId: userId)
        streamTask = Task {
            while !Task.isCancelled {
                guard api.hasConfiguredSession else {
                    try? await Task.sleep(nanoseconds: 2_000_000_000)
                    continue
                }
                do {
                    try await api.consumeCustomerChatEventStream(sessionID: nil, since: streamSince) { event in
                        if event.id > 0 {
                            self.streamSince = max(self.streamSince, event.id)
                        }
                        switch event.type {
                        case "message":
                            if let message = CustomerChatPoll.ChatMessage.fromStreamPayload(event.payload["message"]) {
                                CustomerChatBadgeStore.shared.noteIncomingStaffMessage(seq: message.seq, role: message.role)
                            } else {
                                CustomerChatBadgeStore.shared.scheduleRefresh(api: api)
                            }
                        case "handler":
                            CustomerChatBadgeStore.shared.scheduleRefresh(api: api)
                        default:
                            break
                        }
                    }
                } catch {
                    if Task.isCancelled { break }
                    try? await Task.sleep(nanoseconds: 1_500_000_000)
                }
            }
        }
    }

    func stop() {
        streamTask?.cancel()
        streamTask = nil
        activeUserId = 0
        streamSince = 0
    }
}
