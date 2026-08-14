import Foundation
import SwiftUI
import UserNotifications

/// Persists read notification IDs per customer account so cleared badges stay cleared
/// across app updates and offline/server lag until genuinely new unread items arrive.
@MainActor
enum CustomerNotificationReadStore {
    private static let storageKey = "pax.customer.readNotificationIdsByUser"
    private static let maxIdsPerUser = 500

    static func isRead(userId: Int, notificationId: Int) -> Bool {
        guard userId > 0, notificationId > 0 else { return false }
        return load()[String(userId)]?.contains(notificationId) == true
    }

    static func markRead(userId: Int, ids: [Int]) {
        guard userId > 0 else { return }
        let cleaned = ids.filter { $0 > 0 }
        guard !cleaned.isEmpty else { return }

        var map = load()
        var set = Set(map[String(userId)] ?? [])
        cleaned.forEach { set.insert($0) }
        if set.count > maxIdsPerUser {
            set = Set(set.sorted().suffix(maxIdsPerUser))
        }
        map[String(userId)] = Array(set)
        save(map)
    }

    static func clearUser(_ userId: Int) {
        guard userId > 0 else { return }
        var map = load()
        map.removeValue(forKey: String(userId))
        save(map)
    }

    private static func load() -> [String: [Int]] {
        UserDefaults.standard.dictionary(forKey: storageKey) as? [String: [Int]] ?? [:]
    }

    private static func save(_ map: [String: [Int]]) {
        UserDefaults.standard.set(map, forKey: storageKey)
    }
}

@MainActor
final class CustomerNotificationsBadgeStore: ObservableObject {
    static let shared = CustomerNotificationsBadgeStore()

    @Published private(set) var unreadCount = 0

    private var refreshTask: Task<Void, Never>?
    private var activeUserId = 0

    private init() {}

    func bindUser(_ userId: Int) {
        guard userId > 0 else { return }
        if activeUserId != 0 && activeUserId != userId {
            applyUnreadCount(0)
        }
        activeUserId = userId
    }

    func resetForLogout() {
        refreshTask?.cancel()
        refreshTask = nil
        activeUserId = 0
        applyUnreadCount(0)
    }

    func refresh(api: CustomerAPIClient) async {
        let userId = AuthStore.shared.customerProfile?.id ?? activeUserId
        guard userId > 0 else {
            resetForLogout()
            return
        }
        activeUserId = userId

        do {
            let response = try await api.fetchNotifications(unreadOnly: true)
            let visibleUnread = response.items.filter { !CustomerNotificationReadStore.isRead(userId: userId, notificationId: $0.id) }
            applyUnreadCount(visibleUnread.count)
        } catch {
            #if DEBUG
            print("Customer notification badge refresh failed: \(error.localizedDescription)")
            #endif
        }
    }

    func scheduleRefresh(api: CustomerAPIClient) {
        refreshTask?.cancel()
        refreshTask = Task {
            await refresh(api: api)
        }
    }

    func markReadLocally(ids: [Int]) {
        let userId = AuthStore.shared.customerProfile?.id ?? activeUserId
        guard userId > 0 else { return }
        activeUserId = userId
        CustomerNotificationReadStore.markRead(userId: userId, ids: ids)
        let newlyRead = ids.filter { $0 > 0 }.count
        if newlyRead > 0 {
            applyUnreadCount(max(0, unreadCount - newlyRead))
        }
    }

    func clearAfterMarkAllRead(ids: [Int]) {
        markReadLocally(ids: ids)
        applyUnreadCount(0)
    }

    private func applyUnreadCount(_ count: Int) {
        unreadCount = max(0, count)
        PAXApplicationBadge.sync(total: unreadCount)
    }
}

struct CustomerNotificationBellButton: View {
    @EnvironmentObject private var api: CustomerAPIClient
    @EnvironmentObject private var navigation: CustomerNavigationCoordinator
    @ObservedObject private var badgeStore = CustomerNotificationsBadgeStore.shared

    var body: some View {
        Button {
            navigation.openNotifications()
            PAXHaptics.light()
        } label: {
            ZStack(alignment: .topTrailing) {
                PAXIcon(badgeStore.unreadCount > 0 ? "bell.badge.fill" : "bell", size: .card, emphasis: .primary)
                    .frame(width: 36, height: 36)
                    .background(.ultraThinMaterial, in: Circle())
                    .overlay(
                        Circle()
                            .stroke(PAXTheme.border.opacity(0.25), lineWidth: 0.5)
                    )
                if badgeStore.unreadCount > 0 {
                    Text(badgeStore.unreadCount > 99 ? "99+" : "\(badgeStore.unreadCount)")
                        .font(.system(size: 9, weight: .bold))
                        .foregroundStyle(.white)
                        .padding(.horizontal, badgeStore.unreadCount > 9 ? 4 : 3)
                        .padding(.vertical, 2)
                        .background(Color.red)
                        .clipShape(Capsule())
                        .offset(x: 6, y: -4)
                }
            }
        }
        .buttonStyle(.plain)
        .accessibilityLabel(
            badgeStore.unreadCount > 0
                ? String(localized: "\(badgeStore.unreadCount) unread notifications")
                : String(localized: "Notifications")
        )
        .task(id: AuthStore.shared.sessionEpoch) {
            guard AuthStore.shared.isLoggedIn, AuthStore.shared.isCustomerSession else { return }
            if let userId = AuthStore.shared.customerProfile?.id {
                badgeStore.bindUser(userId)
            }
            badgeStore.scheduleRefresh(api: api)
        }
    }
}
