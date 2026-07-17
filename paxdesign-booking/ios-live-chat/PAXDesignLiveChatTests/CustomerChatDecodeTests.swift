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
        XCTAssertEqual(poll.messages?.first?.content, "Valid")
    }

    func testDecodesConversationsWithStringCounts() throws {
        let json = """
        {
          "conversations": [
            {
              "session_id": "pax_u42_abc",
              "last_preview": "Hello there",
              "handler": "admin",
              "message_count": "12",
              "updated_at": "2026-07-17 12:00:00"
            }
          ]
        }
        """.data(using: .utf8)!

        let response = try JSONDecoder().decode(CustomerConversationsResponse.self, from: json)

        XCTAssertEqual(response.conversations.count, 1)
        XCTAssertEqual(response.conversations.first?.session_id, "pax_u42_abc")
        XCTAssertEqual(response.conversations.first?.message_count, 12)
    }

    func testDecodesParticipantIdentityFields() throws {
        let json = """
        {
          "session_id": "pax_u42_testsession",
          "handler": "admin",
          "admin_typing": true,
          "messages": [
            {
              "id": 1,
              "role": "assistant",
              "content": "Hello!",
              "sender_name": "PAXDesign AI",
              "sender_role": "AI Assistant",
              "sender_avatar": "https://example.com/ai.png"
            },
            {
              "id": 2,
              "role": "user",
              "content": "Thanks",
              "sender_name": "Jane Customer",
              "sender_role": "Customer"
            }
          ]
        }
        """.data(using: .utf8)!

        let poll = try JSONDecoder().decode(CustomerChatPoll.self, from: json)

        XCTAssertEqual(poll.admin_typing, true)
        XCTAssertEqual(poll.messages?.first?.sender_name, "PAXDesign AI")
        XCTAssertEqual(poll.messages?.first?.sender_role, "AI Assistant")
        XCTAssertEqual(poll.messages?.last?.sender_name, "Jane Customer")
    }
}
