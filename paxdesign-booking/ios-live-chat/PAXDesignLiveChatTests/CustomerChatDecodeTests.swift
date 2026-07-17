import XCTest
@testable import PAXDesignLiveChat

final class CustomerChatDecodeTests: XCTestCase {
    func testDecodesMessagesUsingIdFieldFromBackend() throws {
        let json = """
        {
          "session_id": "pax_u42_testsession",
          "handler": "ai",
          "message_count": 2,
          "messages": [
            {
              "id": 1,
              "role": "assistant",
              "content": "Hello! How can I help?"
            },
            {
              "id": 2,
              "role": "user",
              "content": "I need help with my order"
            }
          ]
        }
        """.data(using: .utf8)!

        let poll = try JSONDecoder().decode(CustomerChatPoll.self, from: json)

        XCTAssertEqual(poll.session_id, "pax_u42_testsession")
        XCTAssertEqual(poll.handler, "ai")
        XCTAssertEqual(poll.messages?.count, 2)
        XCTAssertEqual(poll.messages?.first?.seq, 1)
        XCTAssertEqual(poll.messages?.first?.content, "Hello! How can I help?")
        XCTAssertEqual(poll.messages?.last?.role, "user")
    }

    func testDecodesEmptyMessagesWithoutFailure() throws {
        let json = """
        {
          "session_id": "pax_u42_empty",
          "handler": "ai",
          "message_count": 0,
          "messages": []
        }
        """.data(using: .utf8)!

        let poll = try JSONDecoder().decode(CustomerChatPoll.self, from: json)

        XCTAssertEqual(poll.messages?.count, 0)
    }

    func testSkipsMalformedMessageWithoutFailingWholePayload() throws {
        let json = """
        {
          "session_id": "pax_u42_partial",
          "handler": "ai",
          "messages": [
            { "id": 3, "role": "assistant", "content": "Valid" },
            "broken-entry"
          ]
        }
        """.data(using: .utf8)!

        let poll = try JSONDecoder().decode(CustomerChatPoll.self, from: json)

        XCTAssertEqual(poll.messages?.count, 1)
        XCTAssertEqual(poll.messages?.first?.seq, 3)
    }
}
