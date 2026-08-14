import XCTest
@testable import PAXDesignLiveChat

@MainActor
final class CustomerNotificationBadgeTests: XCTestCase {
    private let storageKey = "pax.customer.readNotificationIdsByUser"

    override func setUp() {
        super.setUp()
        UserDefaults.standard.removeObject(forKey: storageKey)
    }

    override func tearDown() {
        UserDefaults.standard.removeObject(forKey: storageKey)
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
        let stored = UserDefaults.standard.dictionary(forKey: storageKey) as? [String: [Int]]
        XCTAssertEqual(stored?["5"], [88])
        XCTAssertTrue(CustomerNotificationReadStore.isRead(userId: 5, notificationId: 88))
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
}
