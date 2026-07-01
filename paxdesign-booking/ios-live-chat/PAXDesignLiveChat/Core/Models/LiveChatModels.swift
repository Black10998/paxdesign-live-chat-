import Foundation

private enum LiveChatDecode {
    static func string<C: CodingKey>(_ container: KeyedDecodingContainer<C>, _ key: C) -> String {
        if let value = try? container.decode(String.self, forKey: key) {
            return value
        }
        if let value = try? container.decode(Int.self, forKey: key) {
            return String(value)
        }
        if let value = try? container.decode(Double.self, forKey: key) {
            return String(value)
        }
        return ""
    }

    static func int<C: CodingKey>(_ container: KeyedDecodingContainer<C>, _ key: C) -> Int {
        if let value = try? container.decode(Int.self, forKey: key) {
            return value
        }
        if let value = try? container.decode(String.self, forKey: key), let parsed = Int(value) {
            return parsed
        }
        return 0
    }

    static func bool<C: CodingKey>(_ container: KeyedDecodingContainer<C>, _ key: C) -> Bool {
        if let value = try? container.decode(Bool.self, forKey: key) {
            return value
        }
        if let value = try? container.decode(Int.self, forKey: key) {
            return value != 0
        }
        if let value = try? container.decode(String.self, forKey: key) {
            return value == "1" || value.lowercased() == "true"
        }
        return false
    }
}

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

    init(
        id: Int,
        sessionId: String,
        handler: String,
        handlerLabel: String,
        adminName: String,
        customerName: String,
        sessionRating: Int,
        detectedService: String,
        updatedAt: String,
        messageCount: Int,
        seq: Int,
        lastPreview: String,
        lastRole: String
    ) {
        self.id = id
        self.sessionId = sessionId
        self.handler = handler
        self.handlerLabel = handlerLabel
        self.adminName = adminName
        self.customerName = customerName
        self.sessionRating = sessionRating
        self.detectedService = detectedService
        self.updatedAt = updatedAt
        self.messageCount = messageCount
        self.seq = seq
        self.lastPreview = lastPreview
        self.lastRole = lastRole
    }

    init(from decoder: Decoder) throws {
        let container = try decoder.container(keyedBy: CodingKeys.self)
        id = LiveChatDecode.int(container, CodingKeys.id)
        sessionId = LiveChatDecode.string(container, CodingKeys.sessionId)
        handler = LiveChatDecode.string(container, CodingKeys.handler)
        handlerLabel = LiveChatDecode.string(container, CodingKeys.handlerLabel)
        adminName = LiveChatDecode.string(container, CodingKeys.adminName)
        customerName = LiveChatDecode.string(container, CodingKeys.customerName)
        sessionRating = LiveChatDecode.int(container, CodingKeys.sessionRating)
        detectedService = LiveChatDecode.string(container, CodingKeys.detectedService)
        updatedAt = LiveChatDecode.string(container, CodingKeys.updatedAt)
        messageCount = LiveChatDecode.int(container, CodingKeys.messageCount)
        seq = LiveChatDecode.int(container, CodingKeys.seq)
        lastPreview = LiveChatDecode.string(container, CodingKeys.lastPreview)
        lastRole = LiveChatDecode.string(container, CodingKeys.lastRole)
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

    init(id: Int, role: String, content: String, ts: Int? = nil, imageUrl: String? = nil, replyTo: Int? = nil) {
        self.id = id
        self.role = role
        self.content = content
        self.ts = ts
        self.imageUrl = imageUrl
        self.replyTo = replyTo
    }

    init(from decoder: Decoder) throws {
        let container = try decoder.container(keyedBy: CodingKeys.self)
        id = LiveChatDecode.int(container, CodingKeys.id)
        role = LiveChatDecode.string(container, CodingKeys.role)
        content = LiveChatDecode.string(container, CodingKeys.content)
        ts = try container.decodeIfPresent(Int.self, forKey: .ts)
        imageUrl = try container.decodeIfPresent(String.self, forKey: .imageUrl)
        replyTo = try container.decodeIfPresent(Int.self, forKey: .replyTo)
    }
}

struct SessionListResponse: Codable {
    let sessions: [LiveSession]
    let liveCount: Int

    enum CodingKeys: String, CodingKey {
        case sessions
        case liveCount = "live_count"
    }

    init(from decoder: Decoder) throws {
        let container = try decoder.container(keyedBy: CodingKeys.self)
        sessions = (try? container.decode([LiveSession].self, forKey: .sessions)) ?? []
        liveCount = LiveChatDecode.int(container, CodingKeys.liveCount)
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

    init(from decoder: Decoder) throws {
        let container = try decoder.container(keyedBy: CodingKeys.self)
        handler = LiveChatDecode.string(container, CodingKeys.handler)
        handlerLabel = LiveChatDecode.string(container, CodingKeys.handlerLabel)
        adminName = LiveChatDecode.string(container, CodingKeys.adminName)
        customerName = LiveChatDecode.string(container, CodingKeys.customerName)
        sessionRating = LiveChatDecode.int(container, CodingKeys.sessionRating)
        detectedService = LiveChatDecode.string(container, CodingKeys.detectedService)
        updatedAt = LiveChatDecode.string(container, CodingKeys.updatedAt)
        seq = LiveChatDecode.int(container, CodingKeys.seq)
        messages = (try? container.decode([LiveMessage].self, forKey: .messages)) ?? []
        adminTyping = LiveChatDecode.bool(container, CodingKeys.adminTyping)
        userTyping = LiveChatDecode.bool(container, CodingKeys.userTyping)
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

    init(from decoder: Decoder) throws {
        let container = try decoder.container(keyedBy: CodingKeys.self)
        userId = LiveChatDecode.int(container, CodingKeys.userId)
        name = LiveChatDecode.string(container, CodingKeys.name)
        email = LiveChatDecode.string(container, CodingKeys.email)
        username = try container.decodeIfPresent(String.self, forKey: .username)
        avatarUrl = try container.decodeIfPresent(String.self, forKey: .avatarUrl)
        siteUrl = LiveChatDecode.string(container, CodingKeys.siteUrl)
        restBase = LiveChatDecode.string(container, CodingKeys.restBase)
        pluginVer = LiveChatDecode.string(container, CodingKeys.pluginVer)
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
