import XCTest
@testable import PAXDesignLiveChat

@MainActor
final class CustomerNotificationBadgeTests: XCTestCase {
    private let storageKey = "pax.customer.readNotificationIdsByUser"
    private let maxIdKey = "pax.customer.markAllReadMaxIdByUser"
    private let allReadAtKey = "pax.customer.markAllReadAtByUser"

    override func setUp() {
        super.setUp()
        clearStore()
    }

    override func tearDown() {
        clearStore()
        super.tearDown()
    }

    func testReadStorePersistsPerUser() {
        CustomerNotificationReadStore.markRead(userId: 42, ids: [1, 2])
        CustomerNotificationReadStore.markRead(userId: 99, ids: [7])

        XCTAssertTrue(CustomerNotificationReadStore.isRead(userId: 42, notificationId: 1))
        XCTAssertTrue(CustomerNotificationReadStore.isRead(userId: 42, notificationId: 2))
        XCTAssertFalse(CustomerNotificationReadStore.isRead(userId: 42, notificationId: 7))
        XCTAssertTrue(CustomerNotificationReadStore.isRead(userId: 99, notificationId: 7))
    }

    func testClearUserRemovesOnlyThatAccount() {
        CustomerNotificationReadStore.markRead(userId: 10, ids: [5])
        CustomerNotificationReadStore.markRead(userId: 20, ids: [6])
        CustomerNotificationReadStore.clearUser(10)

        XCTAssertFalse(CustomerNotificationReadStore.isRead(userId: 10, notificationId: 5))
        XCTAssertTrue(CustomerNotificationReadStore.isRead(userId: 20, notificationId: 6))
    }

    func testBadgeStoreResetsOnLogout() {
        CustomerNotificationsBadgeStore.shared.bindUser(42)
        CustomerNotificationsBadgeStore.shared.resetForLogout()
        XCTAssertEqual(CustomerNotificationsBadgeStore.shared.unreadCount, 0)
    }

    func testReadStoreSurvivesReloadFromUserDefaults() {
        CustomerNotificationReadStore.markRead(userId: 5, ids: [88])
        XCTAssertTrue(CustomerNotificationReadStore.isRead(userId: 5, notificationId: 88))
    }

    func testReadStoreLoadsNSNumberArraysFromUserDefaults() {
        UserDefaults.standard.set(["42": [NSNumber(value: 11), NSNumber(value: 12)]], forKey: storageKey)
        XCTAssertTrue(CustomerNotificationReadStore.isRead(userId: 42, notificationId: 11))
        XCTAssertTrue(CustomerNotificationReadStore.isRead(userId: 42, notificationId: 12))
        XCTAssertFalse(CustomerNotificationReadStore.isRead(userId: 42, notificationId: 13))
    }

    func testMarkAllReadCoversOlderIdsOutsideVisiblePage() {
        CustomerNotificationReadStore.markAllRead(userId: 8, ids: [50, 49, 48])
        XCTAssertTrue(CustomerNotificationReadStore.isRead(userId: 8, notificationId: 12))
        XCTAssertTrue(CustomerNotificationReadStore.isRead(userId: 8, notificationId: 50))
        XCTAssertFalse(CustomerNotificationReadStore.isRead(userId: 8, notificationId: 51))
        XCTAssertTrue(
            CustomerNotificationReadStore.isRead(
                userId: 8,
                notificationId: 51,
                createdAt: "2020-01-01T00:00:00Z"
            )
        )
    }

    func testMarkAllReadZerosBadgeImmediately() {
        CustomerNotificationsBadgeStore.shared.bindUser(8)
        CustomerNotificationsBadgeStore.shared.clearAfterMarkAllRead(ids: [50, 49])
        XCTAssertEqual(CustomerNotificationsBadgeStore.shared.unreadCount, 0)
        XCTAssertEqual(CustomerNotificationReadStore.persistedUnreadCount(userId: 8), 0)
        CustomerNotificationsBadgeStore.shared.resetForLogout()
    }

    func testNotificationDecodesIntegerReadFlag() throws {
        let json = Data(#"{"id":11,"category":"news","title":"Hello","is_read":0,"created_at":"2026-01-01T00:00:00Z"}"#.utf8)
        let item = try JSONDecoder().decode(CustomerNotificationItem.self, from: json)
        XCTAssertEqual(item.id, 11)
        XCTAssertFalse(item.is_read)
    }

    func testLocalReadOverlayMarksServerUnreadAsRead() {
        CustomerNotificationReadStore.markRead(userId: 7, ids: [11])
        let json = Data(#"{"items":[{"id":11,"category":"news","title":"Hello","is_read":0,"created_at":"2026-01-01T00:00:00Z"},{"id":12,"category":"chat","title":"Ping","is_read":0,"created_at":"2026-01-01T00:00:00Z"}],"unread_count":2}"#.utf8)
        let payload = try! JSONDecoder().decode(CustomerNotificationsResponse.self, from: json)
        let overlaid = payload.overlayingLocalRead(userId: 7)
        XCTAssertTrue(overlaid.items.first { $0.id == 11 }?.is_read == true)
        XCTAssertTrue(overlaid.items.first { $0.id == 12 }?.is_read == false)
        XCTAssertEqual(overlaid.unread_count, 1)
    }

    func testMarkAllOverlayZerosUnreadCount() {
        CustomerNotificationReadStore.markAllRead(userId: 7, ids: [11, 12])
        let json = Data(#"{"items":[{"id":11,"category":"news","title":"Hello","is_read":0,"created_at":"2026-01-01T00:00:00Z"},{"id":12,"category":"chat","title":"Ping","is_read":0,"created_at":"2026-01-01T00:00:00Z"}],"unread_count":2}"#.utf8)
        let payload = try! JSONDecoder().decode(CustomerNotificationsResponse.self, from: json)
        let overlaid = payload.overlayingLocalRead(userId: 7)
        XCTAssertTrue(overlaid.items.allSatisfy(\.is_read))
        XCTAssertEqual(overlaid.unread_count, 0)
    }

    private func clearStore() {
        UserDefaults.standard.removeObject(forKey: storageKey)
        UserDefaults.standard.removeObject(forKey: maxIdKey)
        UserDefaults.standard.removeObject(forKey: allReadAtKey)
        UserDefaults.standard.removeObject(forKey: "pax.customer.unreadNotificationCount.8")
        UserDefaults.standard.removeObject(forKey: "pax.customer.unreadNotificationCount.42")
        UserDefaults.standard.removeObject(forKey: "pax.customer.unreadNotificationCount.5")
        UserDefaults.standard.removeObject(forKey: "pax.customer.unreadNotificationCount.7")
        UserDefaults.standard.removeObject(forKey: "pax.customer.unreadNotificationCount.10")
        UserDefaults.standard.removeObject(forKey: "pax.customer.unreadNotificationCount.20")
        UserDefaults.standard.removeObject(forKey: "pax.customer.unreadNotificationCount.99")
    }
}
