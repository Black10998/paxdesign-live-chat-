import Foundation

struct LiveSession: Identifiable, Codable, Hashable {
    let id: Int
    let sessionId: String
    let handler: String
    let handlerLabel: String
    let adminName: String
    let customerName: String
    let sessionRating: Int
    let detectedService: String
    let updatedAt: String
    let messageCount: Int
    let seq: Int
    let lastPreview: String
    let lastRole: String

    enum CodingKeys: String, CodingKey {
        case id
        case sessionId = "session_id"
        case handler
        case handlerLabel = "handler_label"
        case adminName = "admin_name"
        case customerName = "customer_name"
        case sessionRating = "session_rating"
        case detectedService = "detected_service"
        case updatedAt = "updated_at"
        case messageCount = "message_count"
        case seq
        case lastPreview = "last_preview"
        case lastRole = "last_role"
    }

    var displayName: String {
        if !customerName.isEmpty { return customerName }
        return "Kunde · \(sessionId.prefix(10))"
    }

    var isLiveRequest: Bool { handler == "live_request" }
    var isAdmin: Bool { handler == "admin" }
    var isClosed: Bool { handler == "closed" }
}

struct LiveMessage: Identifiable, Codable, Hashable {
    let id: Int
    let role: String
    let content: String
    let ts: Int?
    let imageUrl: String?
    let replyTo: Int?

    enum CodingKeys: String, CodingKey {
        case id, role, content, ts
        case imageUrl = "image_url"
        case replyTo = "reply_to"
    }
}

struct SessionListResponse: Codable {
    let sessions: [LiveSession]
    let liveCount: Int

    enum CodingKeys: String, CodingKey {
        case sessions
        case liveCount = "live_count"
    }
}

struct PollResponse: Codable {
    let handler: String
    let handlerLabel: String
    let adminName: String
    let customerName: String
    let sessionRating: Int
    let detectedService: String
    let updatedAt: String
    let seq: Int
    let messages: [LiveMessage]
    let adminTyping: Bool
    let userTyping: Bool

    enum CodingKeys: String, CodingKey {
        case handler
        case handlerLabel = "handler_label"
        case adminName = "admin_name"
        case customerName = "customer_name"
        case sessionRating = "session_rating"
        case detectedService = "detected_service"
        case updatedAt = "updated_at"
        case seq, messages
        case adminTyping = "admin_typing"
        case userTyping = "user_typing"
    }
}

struct AdminProfile: Codable {
    let userId: Int
    let name: String
    let email: String
    let username: String?
    let avatarUrl: String?
    let siteUrl: String
    let restBase: String
    let pluginVer: String

    enum CodingKeys: String, CodingKey {
        case userId = "user_id"
        case name, email, username
        case avatarUrl = "avatar_url"
        case siteUrl = "site_url"
        case restBase = "rest_base"
        case pluginVer = "plugin_ver"
    }
}

extension LiveSession {
    static func fromPushPayload(sessionId: String, payload: PushService.PushPayload) -> LiveSession {
        LiveSession(
            id: 0,
            sessionId: sessionId,
            handler: "live_request",
            handlerLabel: "Live-Anfrage",
            adminName: "",
            customerName: payload.customerName.isEmpty ? "Kunde" : payload.customerName,
            sessionRating: 0,
            detectedService: payload.service,
            updatedAt: "",
            messageCount: 0,
            seq: 0,
            lastPreview: payload.preview,
            lastRole: "user"
        )
    }
}

struct IncomingLiveRequest: Identifiable, Equatable {
    let id: String
    let session: LiveSession
}
