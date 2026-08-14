import Foundation
import SwiftUI

/// Unread staff/assistant message count for the customer Chat tab badge.
@MainActor
final class CustomerChatBadgeStore: ObservableObject {
    static let shared = CustomerChatBadgeStore()

    @Published private(set) var unreadCount = 0

    /// When true, incoming staff messages do not increment the tab badge (customer is viewing chat).
    var isChatForeground = false

    private var userId = 0
    private var lastReadSeq = 0
    private var seenStaffSeqs = Set<Int>()
    private var refreshTask: Task<Void, Never>?

    private init() {
        restorePersistedState()
    }

    func configure(userId: Int) {
        guard userId > 0 else { return }
        if self.userId != userId {
            self.userId = userId
            restorePersistedState()
        }
    }

    func apply(unreadStaffCount: Int, lastReadSeq: Int? = nil) {
        let count = max(0, unreadStaffCount)
        if let lastReadSeq {
            self.lastReadSeq = max(self.lastReadSeq, lastReadSeq)
        }
        guard unreadCount != count else {
            persistState()
            return
        }
        unreadCount = count
        persistState()
        PAXApplicationBadge.syncCustomerPortal()
    }

    func clear() {
        lastReadSeq = max(lastReadSeq, seenStaffSeqs.max() ?? 0)
        guard unreadCount != 0 else {
            persistState()
            return
        }
        unreadCount = 0
        persistState()
        PAXApplicationBadge.syncCustomerPortal()
    }

    func update(from poll: CustomerChatPoll?) {
        guard let poll else { return }
        if let explicit = poll.unread_staff_count {
            if let readSeq = poll.last_read_seq {
                lastReadSeq = max(lastReadSeq, readSeq)
            }
            apply(unreadStaffCount: explicit, lastReadSeq: poll.last_read_seq)
            return
        }
        let readSeq = poll.last_read_seq ?? lastReadSeq
        lastReadSeq = max(lastReadSeq, readSeq)
        let staffMessages = (poll.messages ?? []).filter { $0.role == "admin" || $0.role == "assistant" }
        let unread = staffMessages.filter { $0.seq > readSeq }.count
        apply(unreadStaffCount: unread, lastReadSeq: readSeq)
    }

    func refresh(api: CustomerAPIClient) async {
        guard let poll = try? await api.fetchChatMessages(since: 0, full: false) else { return }
        update(from: poll)
    }

    func noteIncomingStaffMessage(seq: Int, role: String) {
        guard role == "admin" || role == "assistant" else { return }
        guard !isChatForeground else { return }
        guard seq > 0 else { return }
        guard seq > lastReadSeq else { return }
        guard !seenStaffSeqs.contains(seq) else { return }

        seenStaffSeqs.insert(seq)
        apply(unreadStaffCount: unreadCount + 1)
    }

    func scheduleRefresh(api: CustomerAPIClient) {
        refreshTask?.cancel()
        refreshTask = Task {
            await refresh(api: api)
        }
    }

    func resetForLogout() {
        userId = 0
        unreadCount = 0
        lastReadSeq = 0
        seenStaffSeqs = []
        isChatForeground = false
        refreshTask?.cancel()
        refreshTask = nil
        PAXApplicationBadge.syncCustomerPortal()
    }

    private var storageKeyPrefix: String {
        userId > 0 ? "pax.customer.chat.badge.\(userId)" : "pax.customer.chat.badge.anonymous"
    }

    private func persistState() {
        let defaults = UserDefaults.standard
        defaults.set(unreadCount, forKey: "\(storageKeyPrefix).count")
        defaults.set(lastReadSeq, forKey: "\(storageKeyPrefix).readSeq")
        defaults.set(Array(seenStaffSeqs.prefix(500)), forKey: "\(storageKeyPrefix).seen")
    }

    private func restorePersistedState() {
        let defaults = UserDefaults.standard
        unreadCount = max(0, defaults.integer(forKey: "\(storageKeyPrefix).count"))
        lastReadSeq = max(0, defaults.integer(forKey: "\(storageKeyPrefix).readSeq"))
        if let seen = defaults.array(forKey: "\(storageKeyPrefix).seen") as? [Int] {
            seenStaffSeqs = Set(seen)
        } else {
            seenStaffSeqs = []
        }
    }
}
