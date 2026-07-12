import XCTest
@testable import PAXDesignLiveChat

final class PushNotificationTests: XCTestCase {
    func testParseNotificationPayload() {
        let payload: [AnyHashable: Any] = [
            "pax": [
                "session_id": "pax_123",
                "type": "message",
                "event": "new_customer_message",
                "customer_name": "Jane Doe",
                "service": "Support",
                "preview": "Hello there",
            ],
        ]

        let parsed = PushService.shared.parseNotification(userInfo: payload)

        XCTAssertEqual(parsed?.sessionId, "pax_123")
        XCTAssertEqual(parsed?.type, "message")
        XCTAssertEqual(parsed?.event, "new_customer_message")
        XCTAssertEqual(parsed?.customerName, "Jane Doe")
        XCTAssertEqual(parsed?.preview, "Hello there")
    }

    func testParseFlattenedNotificationPayload() {
        let payload: [AnyHashable: Any] = [
            "session_id": "pax_456",
            "type": "team_message",
            "event": "team_message",
            "preview": "Hello team",
        ]

        let parsed = PushService.shared.parseFlattenedNotification(userInfo: payload)

        XCTAssertEqual(parsed?.sessionId, "pax_456")
        XCTAssertEqual(parsed?.type, "team_message")
        XCTAssertEqual(parsed?.event, "team_message")
        XCTAssertEqual(parsed?.preview, "Hello team")
    }

    func testParseNotificationRequiresSessionId() {
        let payload: [AnyHashable: Any] = [
            "pax": [
                "type": "message",
            ],
        ]

        XCTAssertNil(PushService.shared.parseNotification(userInfo: payload))
    }
}
