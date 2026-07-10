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
}
