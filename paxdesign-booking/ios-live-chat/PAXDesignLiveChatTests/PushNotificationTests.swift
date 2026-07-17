import XCTest
@testable import PAXDesignLiveChat

@MainActor
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
    func testCustomerParseNotificationPayload() {
        let payload: [AnyHashable: Any] = [
            "aps": [
                "alert": [
                    "title": "Support replied",
                    "body": "We received your request.",
                ],
                "sound": "pax-message.wav",
            ],
            "pax": [
                "notification_id": 42,
                "category": "chat",
                "type": "message",
                "event": "customer_chat",
                "entity_type": "chat",
                "entity_id": "pax_customer_123",
                "session_id": "pax_customer_123",
                "deep_link": "/chat/pax_customer_123",
            ],
        ]

        let parsed = CustomerPushService.shared.parseNotification(userInfo: payload)

        XCTAssertEqual(parsed?.category, "chat")
        XCTAssertEqual(parsed?.type, "message")
        XCTAssertEqual(parsed?.sessionId, "pax_customer_123")
        XCTAssertEqual(parsed?.deepLink, "/chat/pax_customer_123")
        XCTAssertEqual(parsed?.title, "Support replied")
        XCTAssertEqual(parsed?.body, "We received your request.")
        XCTAssertEqual(parsed?.soundTone, .message)
    }

    @MainActor
    func testCustomerDeepLinkFromCategory() {
        let payload: [AnyHashable: Any] = [
            "pax": [
                "category": "order",
                "type": "order_update",
                "event": "customer_order",
                "deep_link": "/orders/7",
            ],
        ]

        let link = CustomerPushService.shared.handleNotification(userInfo: payload)
        XCTAssertEqual(link?.path, "/orders/7")
    }

    @MainActor
    func testCustomerSecurityUsesAIAlertTone() {
        let payload: [AnyHashable: Any] = [
            "pax": [
                "category": "security",
                "type": "security_alert",
                "event": "customer_security",
            ],
        ]

        let parsed = CustomerPushService.shared.parseNotification(userInfo: payload)
        XCTAssertEqual(parsed?.soundTone, .aiAlert)
        XCTAssertEqual(CustomerPushService.shared.apnsSoundName(for: parsed!), "pax-ai-alert.wav")
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
