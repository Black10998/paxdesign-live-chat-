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
    var isTeamDM: Bool { handler == "team_dm" || sessionId.hasPrefix("team_") }
    var needsReply: Bool { lastRole == "user" && !isClosed }
}

struct LiveMessage: Identifiable, Codable, Hashable {
    let id: Int
    let role: String
    let content: String
    let ts: Int?
    let imageUrl: String?
    let replyTo: Int?
    var reaction: String?

    enum CodingKeys: String, CodingKey {
        case id, role, content, ts, reaction
        case imageUrl = "image_url"
        case replyTo = "reply_to"
    }

    init(id: Int, role: String, content: String, ts: Int? = nil, imageUrl: String? = nil, replyTo: Int? = nil, reaction: String? = nil) {
        self.id = id
        self.role = role
        self.content = content
        self.ts = ts
        self.imageUrl = imageUrl
        self.replyTo = replyTo
        self.reaction = reaction.flatMap { MessageReaction.normalize($0) }
    }

    init(from decoder: Decoder) throws {
        let container = try decoder.container(keyedBy: CodingKeys.self)
        id = LiveChatDecode.int(container, CodingKeys.id)
        role = LiveChatDecode.string(container, CodingKeys.role)
        content = LiveChatDecode.string(container, CodingKeys.content)
        ts = try container.decodeIfPresent(Int.self, forKey: .ts)
        imageUrl = try container.decodeIfPresent(String.self, forKey: .imageUrl)
        replyTo = try container.decodeIfPresent(Int.self, forKey: .replyTo)
        if let raw = try container.decodeIfPresent(String.self, forKey: .reaction) {
            reaction = MessageReaction.normalize(raw)
        } else {
            reaction = nil
        }
    }
}

enum MessageReaction {
    static func normalize(_ value: String) -> String? {
        switch value {
        case "like", "pax-love": return "like"
        case "dislike", "pax-top", "pax-thanks", "pax-clear": return "dislike"
        default: return nil
        }
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
    let reactions: [String: String]

    enum CodingKeys: String, CodingKey {
        case handler
        case handlerLabel = "handler_label"
        case adminName = "admin_name"
        case customerName = "customer_name"
        case sessionRating = "session_rating"
        case detectedService = "detected_service"
        case updatedAt = "updated_at"
        case seq, messages, reactions
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
        reactions = (try? container.decode([String: String].self, forKey: .reactions)) ?? [:]
    }
}

struct QuickReply: Identifiable, Codable, Hashable {
    let label: String
    let text: String
    let lang: String

    var id: String { "\(lang)-\(label)" }
}

struct QuickRepliesResponse: Codable {
    let quickReplies: [QuickReply]

    enum CodingKeys: String, CodingKey {
        case quickReplies = "quick_replies"
    }

    init(from decoder: Decoder) throws {
        let container = try decoder.container(keyedBy: CodingKeys.self)
        quickReplies = (try? container.decode([QuickReply].self, forKey: .quickReplies)) ?? []
    }
}

struct SuggestionsResponse: Codable {
    let suggestions: [String]
    let messageId: Int

    enum CodingKeys: String, CodingKey {
        case suggestions
        case messageId = "message_id"
    }

    init(from decoder: Decoder) throws {
        let container = try decoder.container(keyedBy: CodingKeys.self)
        suggestions = (try? container.decode([String].self, forKey: .suggestions)) ?? []
        messageId = LiveChatDecode.int(container, CodingKeys.messageId)
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
    let isSuperAdmin: Bool
    let permissions: AdminPermissions

    enum CodingKeys: String, CodingKey {
        case userId = "user_id"
        case name, email, username
        case avatarUrl = "avatar_url"
        case siteUrl = "site_url"
        case restBase = "rest_base"
        case pluginVer = "plugin_ver"
        case isSuperAdmin = "is_super_admin"
        case permissions
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
        isSuperAdmin = (try? container.decode(Bool.self, forKey: .isSuperAdmin)) ?? false
        permissions = (try? container.decode(AdminPermissions.self, forKey: .permissions)) ?? .full
    }

    var displayEmail: String {
        PrivacyMask.email(email, revealFull: isSuperAdmin)
    }
}

extension AdminProfile {
    var perms: AdminPermissions { permissions }
}

struct StaffMember: Codable, Identifiable {
    var id: Int { userId }
    let userId: Int
    let name: String
    let email: String
    let username: String
    let enabled: Bool
    let permissions: AdminPermissions

    enum CodingKeys: String, CodingKey {
        case userId = "user_id"
        case name, email, username, enabled, permissions
    }
}

struct StaffListResponse: Codable {
    let staff: [StaffMember]
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
