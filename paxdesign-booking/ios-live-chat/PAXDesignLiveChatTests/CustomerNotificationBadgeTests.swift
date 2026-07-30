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
}
