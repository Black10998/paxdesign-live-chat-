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

    @MainActor
    func testActiveSessionTrackingForPushSuppression() {
        AppRefreshPolicy.setActiveSession("pax_open_chat")
        XCTAssertEqual(AppRefreshPolicy.activeSessionId, "pax_open_chat")
        AppRefreshPolicy.setActiveSession(nil)
        XCTAssertNil(AppRefreshPolicy.activeSessionId)
        AppRefreshPolicy.setActiveSession("")
        XCTAssertNil(AppRefreshPolicy.activeSessionId)
    }

    @MainActor
    func testPushHandlerDoesNotNavigateByDefault() async {
        let auth = AuthStore.shared
        let coordinator = ChatCoordinator()
        coordinator.activeSessionId = nil
        let sessionId = "pax_test_no_nav"

        await coordinator.handlePush(
            sessionId: sessionId,
            type: "message",
            auth: auth,
            payload: PushService.PushPayload(
                sessionId: sessionId,
                type: "message",
                event: "new_customer_message",
                customerName: "Jane",
                service: "Support",
                preview: "Hello"
            ),
            shouldNavigate: false
        )

        XCTAssertNil(coordinator.activeSessionId)
    }
}
