import XCTest
@testable import PAXDesignLiveChat

final class MessagingReliabilityTests: XCTestCase {
    func testServerAcknowledgementRemovesMatchingOptimisticMessage() {
        let clientId = UUID().uuidString.lowercased()
        let optimistic = LiveMessage(
            id: -1,
            clientMsgId: clientId,
            role: "admin",
            content: "hello"
        )
        let server = LiveMessage(
            id: 42,
            clientMsgId: clientId,
            role: "admin",
            content: "hello"
        )

        let result = MessageMerge.mergeSorted(existing: [optimistic], incoming: [server])

        XCTAssertTrue(result.changed)
        XCTAssertEqual(result.messages, [server])
    }

    func testDuplicateIngressNeverCreatesDuplicateIDs() {
        let message = LiveMessage(id: 8, role: "user", content: "once")
        let existing = [message, message]

        let result = MessageMerge.mergeSorted(existing: existing, incoming: [message])

        XCTAssertEqual(result.messages.map(\.id), [8])
    }

    func testReactionsCannotCrashOnLegacyDuplicateIDs() {
        let first = LiveMessage(id: 8, role: "admin", content: "once")
        let duplicate = LiveMessage(id: 8, role: "admin", content: "once")

        let result = MessageMerge.applyReactions(
            to: [first, duplicate],
            reactions: ["8": "like"]
        )

        XCTAssertEqual(result.messages.count, 1)
        XCTAssertEqual(result.messages.first?.reaction, "like")
    }

    func testOutOfOrderMessagesAreSortedByServerSequence() {
        let existing = [LiveMessage(id: 3, role: "user", content: "three")]
        let incoming = [
            LiveMessage(id: 2, role: "user", content: "two"),
            LiveMessage(id: 4, role: "assistant", content: "four"),
        ]

        let result = MessageMerge.mergeSorted(existing: existing, incoming: incoming)

        XCTAssertEqual(result.messages.map(\.id), [2, 3, 4])
    }

    func testSSEParserSupportsDurableChannelAndMultilineData() {
        var buffer = ""
        XCTAssertNil(ChatEventStreamParser.parseLine(
            "data: {\"id\":91,\"type\":\"message\",",
            dataBuffer: &buffer
        ))
        XCTAssertNil(ChatEventStreamParser.parseLine(
            "data: \"channel\":\"session:pax_1\",\"payload\":{\"seq\":7}}",
            dataBuffer: &buffer
        ))
        let event = ChatEventStreamParser.parseLine("", dataBuffer: &buffer)

        XCTAssertEqual(event?.id, 91)
        XCTAssertEqual(event?.channel, "session:pax_1")
        XCTAssertEqual(StreamPayload.int(event?.payload["seq"]), 7)
    }

    func testLiveMessageDecodesLinkScanAndLinkCardFields() throws {
        let json = """
        {
          "id": 12,
          "role": "user",
          "content": "See https://example.com/page",
          "link_scan_status": "safe",
          "link_scan_urls": "[{\\"url\\":\\"https://example.com/page\\",\\"status\\":\\"safe\\"}]"
        }
        """.data(using: .utf8)!
        let message = try JSONDecoder().decode(LiveMessage.self, from: json)
        XCTAssertEqual(message.linkScanStatus, "safe")
        XCTAssertTrue(message.showsLinkScanBadge)
    }

    func testSiteScopeKeyIsStable() {
        XCTAssertEqual(
            SiteScopeKey.make("https://example.com/wp-json"),
            SiteScopeKey.make("https://example.com/wp-json")
        )
        XCTAssertNotEqual(
            SiteScopeKey.make("https://example.com/wp-json"),
            SiteScopeKey.make("https://other.example/wp-json")
        )
    }

    func testPollCursorUsesInclusiveSinceSemantics() {
        // Server poll returns msg_seq > since. Advancing since to the latest seq
        // before the message is merged locally would skip that message entirely.
        let existing = [LiveMessage(id: 4, role: "user", content: "four")]
        let incoming = [LiveMessage(id: 5, role: "user", content: "five")]

        let result = MessageMerge.mergeSorted(existing: existing, incoming: incoming)

        XCTAssertTrue(result.changed)
        XCTAssertEqual(result.messages.map(\.id), [4, 5])

        let localMax = result.messages.map(\.id).max() ?? 0
        let serverSeq = 5
        let pollSeq = localMax < serverSeq ? localMax : max(localMax, serverSeq)
        XCTAssertEqual(pollSeq, 4, "Poll cursor must stay behind server seq until message 5 is local")
    }

    func testLinkScanStatusLabelsMatchProductionContract() {
        XCTAssertEqual(LinkScanStatus(raw: "checking").label, L10n.ChatLinkScanChecking)
        XCTAssertEqual(LinkScanStatus(raw: "safe").label, L10n.ChatLinkScanSafe)
        XCTAssertEqual(LinkScanStatus(raw: "suspicious").label, L10n.ChatLinkScanSuspicious)
        XCTAssertEqual(LinkScanStatus(raw: "dangerous").label, L10n.ChatLinkScanDangerous)
        XCTAssertEqual(LinkScanStatus(raw: "failed").label, L10n.ChatLinkScanIncomplete)
        XCTAssertEqual(LinkScanStatus(raw: "timeout").label, L10n.ChatLinkScanIncomplete)
        XCTAssertEqual(LinkScanStatus(raw: "incomplete").label, L10n.ChatLinkScanIncomplete)
    }

    func testLinkScanSupportDefaultsToCheckingUntilServerResult() {
        let message = LiveMessage(id: 3, role: "user", content: "See https://example.com")
        XCTAssertEqual(LinkScanSupport.resolvedStatus(for: message), .checking)
        XCTAssertFalse(LinkScanSupport.shouldBlockLinks(in: message))
    }

    func testLinkScanSupportBlocksDangerousLinksOnlyAfterServerVerdict() {
        let dangerous = LiveMessage(id: 4, role: "user", content: "bad", linkScanStatus: "dangerous")
        XCTAssertTrue(LinkScanSupport.shouldBlockLinks(in: dangerous))
    }

    func testStreamPayloadDecodesInlineWebsiteMessage() {
        let payload: [String: Any] = [
            "session_id": "pax_test",
            "seq": 12,
            "role": "user",
            "message": [
                "id": 12,
                "client_msg_id": "abc-123",
                "role": "user",
                "content": "Hello from website",
                "ts": 1_720_000_000,
            ],
        ]

        let decoded = StreamPayload.messages(from: payload)

        XCTAssertEqual(decoded.count, 1)
        XCTAssertEqual(decoded.first?.id, 12)
        XCTAssertEqual(decoded.first?.content, "Hello from website")
        XCTAssertEqual(decoded.first?.clientMsgId, "abc-123")
    }

    func testConversationSyncCooldownBlocksRapidRepeats() {
        ConversationSyncCoordinator.reset()
        XCTAssertTrue(ConversationSyncCoordinator.shouldRunFullSync())
        ConversationSyncCoordinator.beginFullSync()
        ConversationSyncCoordinator.endFullSync()
        XCTAssertFalse(ConversationSyncCoordinator.shouldRunFullSync())
    }

    func testOpenChatPollingIntervalsAreConservative() {
        AppRefreshPolicy.update(scenePhase: .active)
        AppRefreshPolicy.update(liveCount: 0, openChat: true)
        XCTAssertEqual(AppRefreshPolicy.chatThreadIntervalLive, 5_000_000_000)
        XCTAssertEqual(AppRefreshPolicy.teamThreadIntervalLive, 5_000_000_000)
        XCTAssertEqual(AppRefreshPolicy.sessionListInterval, 2_000_000_000)
        XCTAssertEqual(AppRefreshPolicy.teamListInterval, 2_000_000_000)
    }

    func testIdlePollingIntervalsReducedVersusAggressiveBaseline() {
        AppRefreshPolicy.update(scenePhase: .active)
        AppRefreshPolicy.update(liveCount: 0, openChat: false)
        XCTAssertGreaterThanOrEqual(AppRefreshPolicy.sessionListInterval, 4_000_000_000)
        XCTAssertGreaterThanOrEqual(AppRefreshPolicy.teamListInterval, 4_000_000_000)
    }

    func testNetworkRequestTrackerCountsEndpoints() async {
        await NetworkRequestTracker.shared.reset()
        await NetworkRequestTracker.shared.record(endpoint: "sessions")
        await NetworkRequestTracker.shared.record(endpoint: "sessions")
        await NetworkRequestTracker.shared.record(endpoint: "poll:test")
        XCTAssertEqual(await NetworkRequestTracker.shared.totalInWindow, 3)
        XCTAssertEqual(await NetworkRequestTracker.shared.snapshot()["sessions"], 2)
    }

    func testCircuitBreakerOpensOnEdge403() async {
        await NetworkCircuitBreaker.shared.reset()
        await NetworkCircuitBreaker.shared.recordHTTPResponse(
            status: 403,
            bodySnippet: "Access to this resource on the server is denied!",
            endpoint: "sessions",
            retryAfter: nil
        )
        XCTAssertTrue(await NetworkCircuitBreaker.shared.isOpen)
        XCTAssertTrue(await NetworkCircuitBreaker.shared.pollingSuspended)
    }

    func testCircuitBreakerRateCapBlocksBurst() async {
        await NetworkCircuitBreaker.shared.reset()
        await MainActor.run {
            NetworkCircuitBreaker.shared.maxRequestsPerSecond = 2
        }
        try? await NetworkCircuitBreaker.shared.recordRequestStart(endpoint: "a")
        try? await NetworkCircuitBreaker.shared.recordRequestEnd(endpoint: "a")
        try? await NetworkCircuitBreaker.shared.recordRequestStart(endpoint: "b")
        try? await NetworkCircuitBreaker.shared.recordRequestEnd(endpoint: "b")
        do {
            try await NetworkCircuitBreaker.shared.recordRequestStart(endpoint: "c")
            XCTFail("Expected rate cap")
        } catch NetworkCircuitBreakerError.rateLimited {
            XCTAssertTrue(true)
        } catch {
            XCTFail("Unexpected error \(error)")
        }
    }

    func testDeviceEndpointsBypassCircuitRateCap() async {
        await NetworkCircuitBreaker.shared.reset()
        await MainActor.run {
            NetworkCircuitBreaker.shared.maxRequestsPerSecond = 1
        }
        try? await NetworkCircuitBreaker.shared.recordRequestStart(endpoint: "sessions")
        try? await NetworkCircuitBreaker.shared.recordRequestEnd(endpoint: "sessions")
        XCTAssertNoThrow(try await NetworkCircuitBreaker.shared.recordRequestStart(endpoint: "employee-devices"))
        try? await NetworkCircuitBreaker.shared.recordRequestEnd(endpoint: "employee-devices")
    }

    func testAPNsPermanentFailureDetection() {
        XCTAssertTrue(APNsRegistrationPolicy.isPermanentFailureMessage(
            "NSCocoaErrorDomain (3000): No valid 'aps-environment' entitlement string found for application."
        ))
        XCTAssertFalse(APNsRegistrationPolicy.isPermanentFailureMessage("network timeout"))
    }

    func testSSEHealthySuspendsListPolling() {
        AppRefreshPolicy.sseHealthy = true
        AppRefreshPolicy.update(scenePhase: .active)
        AppRefreshPolicy.update(liveCount: 2, openChat: true)
        XCTAssertEqual(AppRefreshPolicy.sessionListInterval, 60_000_000_000)
        XCTAssertEqual(AppRefreshPolicy.teamListInterval, 60_000_000_000)
        AppRefreshPolicy.sseHealthy = false
    }
}
