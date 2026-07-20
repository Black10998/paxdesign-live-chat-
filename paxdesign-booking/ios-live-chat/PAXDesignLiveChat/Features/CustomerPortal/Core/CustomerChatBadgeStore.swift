import Foundation
import SwiftUI

/// Unread staff/assistant message count for the customer Chat tab badge.
@MainActor
final class CustomerChatBadgeStore: ObservableObject {
    static let shared = CustomerChatBadgeStore()

    @Published private(set) var unreadCount = 0

    private var refreshTask: Task<Void, Never>?

    private init() {}

    func apply(unreadStaffCount: Int) {
        let count = max(0, unreadStaffCount)
        guard unreadCount != count else { return }
        unreadCount = count
    }

    func clear() {
        apply(unreadStaffCount: 0)
    }

    func update(from poll: CustomerChatPoll?) {
        guard let poll else { return }
        if let explicit = poll.unread_staff_count {
            apply(unreadStaffCount: explicit)
            return
        }
        let readSeq = poll.last_read_seq ?? 0
        let staffMessages = (poll.messages ?? []).filter { $0.role == "admin" || $0.role == "assistant" }
        let unread = staffMessages.filter { $0.seq > readSeq }.count
        if unread > 0 {
            apply(unreadStaffCount: unread)
        }
    }

    func refresh(api: CustomerAPIClient) async {
        guard let poll = try? await api.fetchChatMessages(since: 0, full: false) else { return }
        update(from: poll)
    }

    func noteIncomingStaffMessage() {
        apply(unreadStaffCount: unreadCount + 1)
    }

    func scheduleRefresh(api: CustomerAPIClient) {
        refreshTask?.cancel()
        refreshTask = Task {
            await refresh(api: api)
        }
    }
}
