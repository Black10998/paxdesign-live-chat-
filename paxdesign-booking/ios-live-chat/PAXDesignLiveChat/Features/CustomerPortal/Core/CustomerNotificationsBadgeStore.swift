import Foundation
import SwiftUI
import UserNotifications

/// Persists read notification IDs per customer account so cleared badges stay cleared
/// across app updates and offline/server lag until genuinely new unread items arrive.
@MainActor
enum CustomerNotificationReadStore {
    private static let storageKey = "pax.customer.readNotificationIdsByUser"
    private static let maxIdKey = "pax.customer.markAllReadMaxIdByUser"
    private static let allReadAtKey = "pax.customer.markAllReadAtByUser"
    private static let maxIdsPerUser = 500

    static func isRead(userId: Int, notificationId: Int, createdAt: String? = nil) -> Bool {
        guard userId > 0, notificationId > 0 else { return false }
        if notificationId <= allReadMaxId(userId: userId) {
            return true
        }
        if loadIds()[String(userId)]?.contains(notificationId) == true {
            return true
        }
        if let createdAt, let date = parseTimestamp(createdAt), date.timeIntervalSince1970 <= allReadAt(userId: userId) {
            return true
        }
        return false
    }

    static func markRead(userId: Int, ids: [Int]) {
        guard userId > 0 else { return }
        let cleaned = ids.filter { $0 > 0 }
        guard !cleaned.isEmpty else { return }

        var map = loadIds()
        var set = Set(map[String(userId)] ?? [])
        cleaned.forEach { set.insert($0) }
        if set.count > maxIdsPerUser {
            set = Set(set.sorted().suffix(maxIdsPerUser))
        }
        map[String(userId)] = Array(set)
        saveIds(map)
    }

    /// Marks every currently known notification as read, including older rows not in the visible page.
    static func markAllRead(userId: Int, ids: [Int]) {
        guard userId > 0 else { return }
        markRead(userId: userId, ids: ids)
        let visibleMax = ids.filter { $0 > 0 }.max() ?? 0
        var maxMap = intMap(forKey: maxIdKey)
        maxMap[String(userId)] = max(maxMap[String(userId)] ?? 0, visibleMax)
        UserDefaults.standard.set(maxMap, forKey: maxIdKey)

        var times = doubleMap(forKey: allReadAtKey)
        times[String(userId)] = Date().timeIntervalSince1970 + 5
        UserDefaults.standard.set(times, forKey: allReadAtKey)
    }

    static func clearUser(_ userId: Int) {
        guard userId > 0 else { return }
        let key = String(userId)
        var map = loadIds()
        map.removeValue(forKey: key)
        saveIds(map)

        var maxMap = intMap(forKey: maxIdKey)
        maxMap.removeValue(forKey: key)
        UserDefaults.standard.set(maxMap, forKey: maxIdKey)

        var times = doubleMap(forKey: allReadAtKey)
        times.removeValue(forKey: key)
        UserDefaults.standard.set(times, forKey: allReadAtKey)
    }

    static func persistedUnreadCount(userId: Int) -> Int {
        guard userId > 0 else { return 0 }
        return max(0, UserDefaults.standard.integer(forKey: unreadCountKey(userId)))
    }

    static func persistUnreadCount(userId: Int, count: Int) {
        guard userId > 0 else { return }
        UserDefaults.standard.set(max(0, count), forKey: unreadCountKey(userId))
    }

    private static func unreadCountKey(_ userId: Int) -> String {
        "pax.customer.unreadNotificationCount.\(userId)"
    }

    private static func allReadMaxId(userId: Int) -> Int {
        intMap(forKey: maxIdKey)[String(userId)] ?? 0
    }

    private static func allReadAt(userId: Int) -> TimeInterval {
        doubleMap(forKey: allReadAtKey)[String(userId)] ?? 0
    }

    private static func loadIds() -> [String: [Int]] {
        guard let raw = UserDefaults.standard.dictionary(forKey: storageKey) else { return [:] }
        var result: [String: [Int]] = [:]
        for (key, value) in raw {
            result[key] = intArray(value)
        }
        return result
    }

    private static func saveIds(_ map: [String: [Int]]) {
        UserDefaults.standard.set(map, forKey: storageKey)
    }

    private static func intArray(_ value: Any) -> [Int] {
        if let ints = value as? [Int] {
            return ints
        }
        if let numbers = value as? [NSNumber] {
            return numbers.map(\.intValue)
        }
        if let anyArray = value as? [Any] {
            return anyArray.compactMap { item in
                if let int = item as? Int { return int }
                if let number = item as? NSNumber { return number.intValue }
                return nil
            }
        }
        return []
    }

    private static func intMap(forKey key: String) -> [String: Int] {
        guard let raw = UserDefaults.standard.dictionary(forKey: key) else { return [:] }
        var result: [String: Int] = [:]
        for (mapKey, value) in raw {
            if let int = value as? Int {
                result[mapKey] = int
            } else if let number = value as? NSNumber {
                result[mapKey] = number.intValue
            }
        }
        return result
    }

    private static func doubleMap(forKey key: String) -> [String: Double] {
        guard let raw = UserDefaults.standard.dictionary(forKey: key) else { return [:] }
        var result: [String: Double] = [:]
        for (mapKey, value) in raw {
            if let double = value as? Double {
                result[mapKey] = double
            } else if let number = value as? NSNumber {
                result[mapKey] = number.doubleValue
            }
        }
        return result
    }

    private static func parseTimestamp(_ raw: String) -> Date? {
        let trimmed = raw.trimmingCharacters(in: .whitespacesAndNewlines)
        guard !trimmed.isEmpty else { return nil }

        let iso = ISO8601DateFormatter()
        iso.formatOptions = [.withInternetDateTime, .withFractionalSeconds]
        if let date = iso.date(from: trimmed) { return date }
        iso.formatOptions = [.withInternetDateTime]
        if let date = iso.date(from: trimmed) { return date }

        let mysql = DateFormatter()
        mysql.locale = Locale(identifier: "en_US_POSIX")
        mysql.timeZone = TimeZone(secondsFromGMT: 0)
        mysql.dateFormat = "yyyy-MM-dd HH:mm:ss"
        if let date = mysql.date(from: trimmed) { return date }
        mysql.dateFormat = "yyyy-MM-dd'T'HH:mm:ss"
        return mysql.date(from: trimmed)
    }
}

@MainActor
final class CustomerNotificationsBadgeStore: ObservableObject {
    static let shared = CustomerNotificationsBadgeStore()

    @Published private(set) var unreadCount = 0

    private var refreshTask: Task<Void, Never>?
    private var activeUserId = 0
    private var refreshGeneration = 0
    private var suppressStaleUnreadUntilGeneration = 0

    private init() {}

    func bindUser(_ userId: Int) {
        guard userId > 0 else { return }
        if activeUserId != 0 && activeUserId != userId {
            applyUnreadCount(0, persistFor: activeUserId)
        }
        activeUserId = userId
        applyUnreadCount(CustomerNotificationReadStore.persistedUnreadCount(userId: userId), persistFor: userId)
    }

    func resetForLogout() {
        refreshTask?.cancel()
        refreshTask = nil
        refreshGeneration += 1
        suppressStaleUnreadUntilGeneration = refreshGeneration
        let previousUser = activeUserId
        activeUserId = 0
        applyUnreadCount(0, persistFor: previousUser)
    }

    func refresh(api: CustomerAPIClient) async {
        refreshGeneration += 1
        let generation = refreshGeneration
        let userId = AuthStore.shared.customerProfile?.id ?? activeUserId
        guard userId > 0 else {
            resetForLogout()
            return
        }
        activeUserId = userId

        do {
            let response = try await api.fetchNotifications(unreadOnly: true)
            guard !Task.isCancelled, generation == refreshGeneration else { return }
            let overlaid = response.overlayingLocalRead(userId: userId)
            let visibleUnread = overlaid.items.filter { !$0.is_read }.count
            if visibleUnread == 0 {
                suppressStaleUnreadUntilGeneration = 0
            }
            applyUnreadCount(visibleUnread, persistFor: userId)
        } catch {
            guard generation == refreshGeneration else { return }
            #if DEBUG
            print("Customer notification badge refresh failed: \(error.localizedDescription)")
            #endif
        }
    }

    func scheduleRefresh(api: CustomerAPIClient) {
        refreshTask?.cancel()
        let scheduledGeneration = refreshGeneration
        refreshTask = Task {
            guard !Task.isCancelled else { return }
            guard scheduledGeneration == refreshGeneration else { return }
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
            applyUnreadCount(max(0, unreadCount - newlyRead), persistFor: userId)
        }
    }

    func clearAfterMarkAllRead(ids: [Int]) {
        let userId = AuthStore.shared.customerProfile?.id ?? activeUserId
        guard userId > 0 else { return }
        activeUserId = userId
        refreshTask?.cancel()
        refreshTask = nil
        refreshGeneration += 1
        suppressStaleUnreadUntilGeneration = refreshGeneration
        CustomerNotificationReadStore.markAllRead(userId: userId, ids: ids)
        applyUnreadCount(0, persistFor: userId)
    }

    func applyServerUnreadCount(_ count: Int) {
        let userId = AuthStore.shared.customerProfile?.id ?? activeUserId
        guard userId > 0 else { return }
        activeUserId = userId
        if count == 0 {
            suppressStaleUnreadUntilGeneration = 0
        }
        applyUnreadCount(count, persistFor: userId)
    }

    private func applyUnreadCount(_ count: Int, persistFor userId: Int) {
        let next = max(0, count)
        unreadCount = next
        if userId > 0 {
            CustomerNotificationReadStore.persistUnreadCount(userId: userId, count: next)
        }
        PAXApplicationBadge.syncCustomerPortal()
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
