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

    static func optionalInt<C: CodingKey>(_ container: KeyedDecodingContainer<C>, _ key: C) -> Int? {
        if let value = try? container.decodeIfPresent(Int.self, forKey: key) {
            return value
        }
        if let value = try? container.decodeIfPresent(String.self, forKey: key), let parsed = Int(value) {
            return parsed
        }
        return nil
    }

    static func decodeLiveMessages<C: CodingKey>(from container: KeyedDecodingContainer<C>, key: C) -> [LiveMessage] {
        guard var array = try? container.nestedUnkeyedContainer(forKey: key) else {
            return []
        }
        var messages: [LiveMessage] = []
        while !array.isAtEnd {
            if let message = try? array.decode(LiveMessage.self) {
                messages.append(message)
            } else {
                _ = try? array.decode(LossyJSONSkip.self)
            }
        }
        return messages
    }
}

/// Skips one JSON value when decoding a partially invalid message array.
private struct LossyJSONSkip: Decodable {
    init(from decoder: Decoder) throws {
        if let container = try? decoder.singleValueContainer() {
            if container.decodeNil() { return }
            if (try? container.decode(Bool.self)) != nil { return }
            if (try? container.decode(Int.self)) != nil { return }
            if (try? container.decode(Double.self)) != nil { return }
            if (try? container.decode(String.self)) != nil { return }
        }
        if var unkeyed = try? decoder.unkeyedContainer() {
            while !unkeyed.isAtEnd {
                _ = try? unkeyed.decode(LossyJSONSkip.self)
            }
            return
        }
        if let keyed = try? decoder.container(keyedBy: LossyJSONKey.self) {
            for key in keyed.allKeys {
                _ = try? keyed.decode(LossyJSONSkip.self, forKey: key)
            }
        }
    }
}

private struct LossyJSONKey: CodingKey {
    var stringValue: String
    var intValue: Int?
    init?(stringValue: String) { self.stringValue = stringValue }
    init?(intValue: Int) {
        self.intValue = intValue
        self.stringValue = "\(intValue)"
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
    let customerLanguage: String
    // Team-only metadata (defaults for customer sessions)
    let otherUserId: Int
    let requestStatus: String
    let requestStatusLabel: String
    let canSend: Bool
    let canRespond: Bool
    let requestedBy: Int
    let isPinned: Bool
    let isMuted: Bool
    let assignedTo: Int
    let otherRoleRank: Int
    let otherRoleLabel: String
    let otherPresence: String
    let otherLastSeen: Int

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
        case customerLanguage = "customer_language"
        case otherUserId = "other_user_id"
        case requestStatus = "request_status"
        case requestStatusLabel = "request_status_label"
        case canSend = "can_send"
        case canRespond = "can_respond"
        case requestedBy = "requested_by"
        case isPinned = "is_pinned"
        case isMuted = "is_muted"
        case assignedTo = "assigned_to"
        case otherRoleRank = "other_role_rank"
        case otherRoleLabel = "other_role_label"
        case otherPresence = "other_presence"
        case otherLastSeen = "other_last_seen"
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
        lastRole: String,
        customerLanguage: String = "",
        otherUserId: Int = 0,
        requestStatus: String = "accepted",
        requestStatusLabel: String = "Accepted",
        canSend: Bool = true,
        canRespond: Bool = false,
        requestedBy: Int = 0,
        isPinned: Bool = false,
        isMuted: Bool = false,
        assignedTo: Int = 0,
        otherRoleRank: Int = 99,
        otherRoleLabel: String = "",
        otherPresence: String = "offline",
        otherLastSeen: Int = 0
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
        self.customerLanguage = customerLanguage
        self.otherUserId = otherUserId
        self.requestStatus = requestStatus
        self.requestStatusLabel = requestStatusLabel
        self.canSend = canSend
        self.canRespond = canRespond
        self.requestedBy = requestedBy
        self.isPinned = isPinned
        self.isMuted = isMuted
        self.assignedTo = assignedTo
        self.otherRoleRank = otherRoleRank
        self.otherRoleLabel = otherRoleLabel
        self.otherPresence = otherPresence
        self.otherLastSeen = otherLastSeen
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
        customerLanguage = LiveChatDecode.string(container, CodingKeys.customerLanguage)
        otherUserId = LiveChatDecode.int(container, CodingKeys.otherUserId)
        requestStatus = LiveChatDecode.string(container, CodingKeys.requestStatus).isEmpty
            ? "accepted" : LiveChatDecode.string(container, CodingKeys.requestStatus)
        requestStatusLabel = LiveChatDecode.string(container, CodingKeys.requestStatusLabel).isEmpty
            ? "Accepted" : LiveChatDecode.string(container, CodingKeys.requestStatusLabel)
        canSend = container.contains(.canSend) ? LiveChatDecode.bool(container, CodingKeys.canSend) : true
        canRespond = LiveChatDecode.bool(container, CodingKeys.canRespond)
        requestedBy = LiveChatDecode.int(container, CodingKeys.requestedBy)
        isPinned = LiveChatDecode.bool(container, CodingKeys.isPinned)
        isMuted = LiveChatDecode.bool(container, CodingKeys.isMuted)
        assignedTo = LiveChatDecode.int(container, CodingKeys.assignedTo)
        otherRoleRank = container.contains(.otherRoleRank)
            ? LiveChatDecode.int(container, CodingKeys.otherRoleRank)
            : 99
        otherRoleLabel = LiveChatDecode.string(container, CodingKeys.otherRoleLabel)
        otherPresence = LiveChatDecode.string(container, CodingKeys.otherPresence).isEmpty
            ? "offline" : LiveChatDecode.string(container, CodingKeys.otherPresence)
        otherLastSeen = LiveChatDecode.int(container, CodingKeys.otherLastSeen)
    }

    var isRequestPending: Bool { requestStatus == "pending" }
    var isRequestDeclined: Bool { requestStatus == "declined" || requestStatus == "locked" }
    var isExecutiveConversation: Bool { otherRoleRank == 1 }

    var displayName: String {
        if isTeamDM {
            if !customerName.isEmpty { return customerName }
            return "Team · \(sessionId.replacingOccurrences(of: "team_", with: ""))"
        }
        if !customerName.isEmpty { return customerName }
        return "Kunde · \(sessionId.prefix(10))"
    }

    var isLiveRequest: Bool { handler == "live_request" }
    var isAdmin: Bool { handler == "admin" }
    var isClosed: Bool { handler == "closed" }
    var isTeamDM: Bool { handler == "team_dm" || sessionId.hasPrefix("team_") }
    var needsReply: Bool { lastRole == "user" && !isClosed }
}

struct EmployeeIdentity: Codable, Hashable {
    let id: Int
    let name: String
    let email: String
    let avatar: String
    let role: String

    enum CodingKeys: String, CodingKey {
        case id, name, email, avatar, role
    }

    init(id: Int, name: String, email: String = "", avatar: String = "", role: String = "") {
        self.id = id
        self.name = name
        self.email = email
        self.avatar = avatar
        self.role = role
    }

    init(from decoder: Decoder) throws {
        let container = try decoder.container(keyedBy: CodingKeys.self)
        id = LiveChatDecode.int(container, CodingKeys.id)
        name = LiveChatDecode.string(container, CodingKeys.name)
        email = LiveChatDecode.string(container, CodingKeys.email)
        avatar = LiveChatDecode.string(container, CodingKeys.avatar)
        role = LiveChatDecode.string(container, CodingKeys.role)
    }
}

struct LiveMessage: Identifiable, Codable, Hashable {
    let id: Int
    let clientMsgId: String?
    let role: String
    let content: String
    let ts: Int?
    let imageUrl: String?
    let replyTo: Int?
    var reaction: String?
    let senderId: Int?
    let senderName: String?
    let senderAvatar: String?
    let senderRole: String?
    let attachmentType: String?
    let linkUrl: String?
    let linkLabel: String?
    let linkIcon: String?
    let linkScanStatus: String?
    let linkScanSystemStatus: String?
    let linkScanReviewPending: String?
    let linkScanUrls: String?

    enum CodingKeys: String, CodingKey {
        case id, role, content, ts, reaction
        case clientMsgId = "client_msg_id"
        case imageUrl = "image_url"
        case replyTo = "reply_to"
        case senderId = "sender_id"
        case senderName = "sender_name"
        case senderAvatar = "sender_avatar"
        case senderRole = "sender_role"
        case attachmentType = "attachment_type"
        case linkUrl = "link_url"
        case linkLabel = "link_label"
        case linkIcon = "link_icon"
        case linkScanStatus = "link_scan_status"
        case linkScanSystemStatus = "link_scan_system_status"
        case linkScanReviewPending = "link_scan_review_pending"
        case linkScanUrls = "link_scan_urls"
    }

    private enum LegacyCodingKeys: String, CodingKey {
        case sender
    }

    init(
        id: Int,
        clientMsgId: String? = nil,
        role: String,
        content: String,
        ts: Int? = nil,
        imageUrl: String? = nil,
        replyTo: Int? = nil,
        reaction: String? = nil,
        senderId: Int? = nil,
        senderName: String? = nil,
        senderAvatar: String? = nil,
        senderRole: String? = nil,
        attachmentType: String? = nil,
        linkUrl: String? = nil,
        linkLabel: String? = nil,
        linkIcon: String? = nil,
        linkScanStatus: String? = nil,
        linkScanSystemStatus: String? = nil,
        linkScanReviewPending: String? = nil,
        linkScanUrls: String? = nil
    ) {
        self.id = id
        self.clientMsgId = clientMsgId
        self.role = role
        self.content = content
        self.ts = ts
        self.imageUrl = imageUrl
        self.replyTo = replyTo
        self.reaction = reaction.flatMap { MessageReaction.normalize($0) }
        self.senderId = senderId
        self.senderName = senderName
        self.senderAvatar = senderAvatar
        self.senderRole = senderRole
        self.attachmentType = attachmentType
        self.linkUrl = linkUrl
        self.linkLabel = linkLabel
        self.linkIcon = linkIcon
        self.linkScanStatus = linkScanStatus
        self.linkScanSystemStatus = linkScanSystemStatus
        self.linkScanReviewPending = linkScanReviewPending
        self.linkScanUrls = linkScanUrls
    }

    var needsLinkScanReview: Bool {
        guard role == "user" else { return false }
        guard let pending = linkScanReviewPending, !pending.isEmpty else { return false }
        return pending == "1" || pending.lowercased() == "true"
    }

    var isLinkCard: Bool {
        attachmentType == "link_card" && !(linkUrl ?? "").isEmpty
    }

    var isInPlaceWarning: Bool {
        attachmentType == "in_place_warning" || attachmentType == "in_place_deleted"
    }

    var isInPlaceWarnStyle: Bool {
        attachmentType == "in_place_warning"
    }

    var showsLinkScanBadge: Bool {
        guard role == "user" else { return false }
        if let status = linkScanStatus, !status.isEmpty, status != "none" { return true }
        return LinkScanSupport.urls(in: content).isEmpty == false
    }

    init(from decoder: Decoder) throws {
        let container = try decoder.container(keyedBy: CodingKeys.self)
        id = LiveChatDecode.int(container, CodingKeys.id)
        let decodedClientId = LiveChatDecode.string(container, CodingKeys.clientMsgId)
        clientMsgId = decodedClientId.isEmpty ? nil : decodedClientId
        role = LiveChatDecode.string(container, CodingKeys.role)
        content = LiveChatDecode.string(container, CodingKeys.content)
        ts = LiveChatDecode.optionalInt(container, CodingKeys.ts)
        imageUrl = try container.decodeIfPresent(String.self, forKey: .imageUrl)
        replyTo = LiveChatDecode.optionalInt(container, CodingKeys.replyTo)
        if let raw = try container.decodeIfPresent(String.self, forKey: .reaction) {
            reaction = MessageReaction.normalize(raw)
        } else {
            reaction = nil
        }
        senderId = LiveChatDecode.optionalInt(container, CodingKeys.senderId)
        let decodedSenderName = LiveChatDecode.string(container, CodingKeys.senderName)
        if decodedSenderName.isEmpty {
            let legacy = try decoder.container(keyedBy: LegacyCodingKeys.self)
            let legacySender = LiveChatDecode.string(legacy, LegacyCodingKeys.sender)
            senderName = legacySender.isEmpty ? nil : legacySender
        } else {
            senderName = decodedSenderName
        }
        let avatar = LiveChatDecode.string(container, CodingKeys.senderAvatar)
        senderAvatar = avatar.isEmpty ? nil : avatar
        let roleLabel = LiveChatDecode.string(container, CodingKeys.senderRole)
        senderRole = roleLabel.isEmpty ? nil : roleLabel
        attachmentType = Self.decodeOptionalString(container, .attachmentType)
        linkUrl = Self.decodeOptionalString(container, .linkUrl)
        linkLabel = Self.decodeOptionalString(container, .linkLabel)
        linkIcon = Self.decodeOptionalString(container, .linkIcon)
        linkScanStatus = Self.decodeOptionalString(container, .linkScanStatus)
        linkScanSystemStatus = Self.decodeOptionalString(container, .linkScanSystemStatus)
        linkScanReviewPending = Self.decodeOptionalString(container, .linkScanReviewPending)
        linkScanUrls = Self.decodeOptionalString(container, .linkScanUrls)
    }

    private static func decodeOptionalString<C: CodingKey>(
        _ container: KeyedDecodingContainer<C>,
        _ key: C
    ) -> String? {
        let value = LiveChatDecode.string(container, key)
        return value.isEmpty ? nil : value
    }

    /// Decode a message embedded in an SSE event payload for instant UI insertion.
    static func fromStreamPayload(_ value: Any?) -> LiveMessage? {
        guard let dict = value as? [String: Any],
              JSONSerialization.isValidJSONObject(dict),
              let data = try? JSONSerialization.data(withJSONObject: dict) else {
            return nil
        }
        return try? JSONDecoder().decode(LiveMessage.self, from: data)
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

struct ConversationSyncResponse: Codable {
    let sessions: [LiveSession]
    let liveCount: Int
    let teamSessions: [LiveSession]
    let threads: [String: PollResponse]
    let teamThreads: [String: PollResponse]

    enum CodingKeys: String, CodingKey {
        case sessions
        case liveCount = "live_count"
        case teamSessions = "team_sessions"
        case threads
        case teamThreads = "team_threads"
    }

    init(from decoder: Decoder) throws {
        let container = try decoder.container(keyedBy: CodingKeys.self)
        sessions = (try? container.decode([LiveSession].self, forKey: .sessions)) ?? []
        liveCount = LiveChatDecode.int(container, CodingKeys.liveCount)
        teamSessions = (try? container.decode([LiveSession].self, forKey: .teamSessions)) ?? []
        threads = (try? container.decode([String: PollResponse].self, forKey: .threads)) ?? [:]
        teamThreads = (try? container.decode([String: PollResponse].self, forKey: .teamThreads)) ?? [:]
    }
}

struct PollResponse: Codable {
    let handler: String
    let handlerLabel: String
    let adminName: String
    let adminUserId: Int
    let assignedAgent: EmployeeIdentity?
    let customerName: String
    let sessionRating: Int
    let detectedService: String
    let updatedAt: String
    let seq: Int
    let messageCount: Int
    let lastReadSeq: Int
    let otherReadSeq: Int
    let messages: [LiveMessage]
    let adminTyping: Bool
    let userTyping: Bool
    let reactions: [String: String]
    let customerLanguage: String
    let otherUserId: Int
    let requestStatus: String
    let requestStatusLabel: String
    let canSend: Bool
    let canRespond: Bool
    let requestedBy: Int
    let isPinned: Bool
    let isMuted: Bool
    let assignedTo: Int
    let otherRoleRank: Int
    let otherRoleLabel: String
    let otherPresence: String
    let otherLastSeen: Int

    enum CodingKeys: String, CodingKey {
        case handler
        case handlerLabel = "handler_label"
        case adminName = "admin_name"
        case adminUserId = "admin_user_id"
        case assignedAgent = "assigned_agent"
        case customerName = "customer_name"
        case customerLanguage = "customer_language"
        case sessionRating = "session_rating"
        case detectedService = "detected_service"
        case updatedAt = "updated_at"
        case seq
        case messageCount = "message_count"
        case messages, reactions
        case adminTyping = "admin_typing"
        case userTyping = "user_typing"
        case lastReadSeq = "last_read_seq"
        case otherReadSeq = "other_read_seq"
        case otherUserId = "other_user_id"
        case requestStatus = "request_status"
        case requestStatusLabel = "request_status_label"
        case canSend = "can_send"
        case canRespond = "can_respond"
        case requestedBy = "requested_by"
        case isPinned = "is_pinned"
        case isMuted = "is_muted"
        case assignedTo = "assigned_to"
        case otherRoleRank = "other_role_rank"
        case otherRoleLabel = "other_role_label"
        case otherPresence = "other_presence"
        case otherLastSeen = "other_last_seen"
    }

    init(from decoder: Decoder) throws {
        let container = try decoder.container(keyedBy: CodingKeys.self)
        handler = LiveChatDecode.string(container, CodingKeys.handler)
        handlerLabel = LiveChatDecode.string(container, CodingKeys.handlerLabel)
        adminName = LiveChatDecode.string(container, CodingKeys.adminName)
        adminUserId = LiveChatDecode.int(container, CodingKeys.adminUserId)
        assignedAgent = container.contains(.assignedAgent)
            ? (try? container.decode(EmployeeIdentity.self, forKey: .assignedAgent))
            : nil
        customerName = LiveChatDecode.string(container, CodingKeys.customerName)
        customerLanguage = LiveChatDecode.string(container, CodingKeys.customerLanguage)
        sessionRating = LiveChatDecode.int(container, CodingKeys.sessionRating)
        detectedService = LiveChatDecode.string(container, CodingKeys.detectedService)
        updatedAt = LiveChatDecode.string(container, CodingKeys.updatedAt)
        seq = LiveChatDecode.int(container, CodingKeys.seq)
        messageCount = LiveChatDecode.int(container, CodingKeys.messageCount)
        lastReadSeq = LiveChatDecode.int(container, CodingKeys.lastReadSeq)
        otherReadSeq = LiveChatDecode.int(container, CodingKeys.otherReadSeq)
        messages = LiveChatDecode.decodeLiveMessages(from: container, key: CodingKeys.messages)
        adminTyping = LiveChatDecode.bool(container, CodingKeys.adminTyping)
        userTyping = LiveChatDecode.bool(container, CodingKeys.userTyping)
        reactions = (try? container.decode([String: String].self, forKey: .reactions)) ?? [:]
        otherUserId = LiveChatDecode.int(container, CodingKeys.otherUserId)
        requestStatus = LiveChatDecode.string(container, CodingKeys.requestStatus).isEmpty
            ? "accepted" : LiveChatDecode.string(container, CodingKeys.requestStatus)
        requestStatusLabel = LiveChatDecode.string(container, CodingKeys.requestStatusLabel)
        canSend = container.contains(.canSend) ? LiveChatDecode.bool(container, CodingKeys.canSend) : true
        canRespond = LiveChatDecode.bool(container, CodingKeys.canRespond)
        requestedBy = LiveChatDecode.int(container, CodingKeys.requestedBy)
        isPinned = LiveChatDecode.bool(container, CodingKeys.isPinned)
        isMuted = LiveChatDecode.bool(container, CodingKeys.isMuted)
        assignedTo = LiveChatDecode.int(container, CodingKeys.assignedTo)
        otherRoleRank = LiveChatDecode.int(container, CodingKeys.otherRoleRank)
        otherRoleLabel = LiveChatDecode.string(container, CodingKeys.otherRoleLabel)
        otherPresence = LiveChatDecode.string(container, CodingKeys.otherPresence)
        otherLastSeen = LiveChatDecode.int(container, CodingKeys.otherLastSeen)
    }
}

struct QuickReply: Identifiable, Codable, Hashable {
    let label: String
    let text: String
    let lang: String

    var id: String { "\(lang)-\(label)" }
}

struct QuickLink: Identifiable, Codable, Hashable {
    let id: String
    let label: String
    let url: String
    let icon: String

    init(id: String, label: String, url: String, icon: String = "🔗") {
        self.id = id
        self.label = label
        self.url = url
        self.icon = icon
    }

    init(from decoder: Decoder) throws {
        let container = try decoder.container(keyedBy: CodingKeys.self)
        id = LiveChatDecode.string(container, CodingKeys.id)
        label = LiveChatDecode.string(container, CodingKeys.label)
        url = LiveChatDecode.string(container, CodingKeys.url)
        let decodedIcon = LiveChatDecode.string(container, CodingKeys.icon)
        icon = decodedIcon.isEmpty ? "🔗" : decodedIcon
    }

    enum CodingKeys: String, CodingKey {
        case id, label, url, icon
    }
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

struct QuickLinksResponse: Codable {
    let quickLinks: [QuickLink]

    enum CodingKeys: String, CodingKey {
        case quickLinks = "quick_links"
    }

    init(from decoder: Decoder) throws {
        let container = try decoder.container(keyedBy: CodingKeys.self)
        quickLinks = (try? container.decode([QuickLink].self, forKey: .quickLinks)) ?? []
    }
}

struct LinkScanReviewResponse: Codable {
    let ok: Bool?
    let action: String?
    let message: LiveMessage?
    let tombstone: String?
    let messageId: Int?
    let warn: Bool?

    enum CodingKeys: String, CodingKey {
        case ok, action, message, tombstone, warn
        case messageId = "message_id"
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
    let modulePermissions: ModulePermissions?
    let onboardingCompleted: Bool
    let termsAccepted: Bool
    let termsAcceptedAt: Int
    let permissionStatus: OnboardingPermissionStatus?
    let spokenLanguages: [String]

    enum CodingKeys: String, CodingKey {
        case userId = "user_id"
        case name, email, username
        case avatarUrl = "avatar_url"
        case siteUrl = "site_url"
        case restBase = "rest_base"
        case pluginVer = "plugin_ver"
        case isSuperAdmin = "is_super_admin"
        case permissions
        case modulePermissions = "module_permissions"
        case onboardingCompleted = "onboarding_completed"
        case termsAccepted = "terms_accepted"
        case termsAcceptedAt = "terms_accepted_at"
        case permissionStatus = "permission_status"
        case spokenLanguages = "spoken_languages"
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
        modulePermissions = try container.decodeIfPresent(ModulePermissions.self, forKey: .modulePermissions)
        onboardingCompleted = (try? container.decode(Bool.self, forKey: .onboardingCompleted)) ?? false
        termsAccepted = (try? container.decode(Bool.self, forKey: .termsAccepted)) ?? onboardingCompleted
        termsAcceptedAt = LiveChatDecode.int(container, CodingKeys.termsAcceptedAt)
        permissionStatus = try container.decodeIfPresent(OnboardingPermissionStatus.self, forKey: .permissionStatus)
        spokenLanguages = (try? container.decode([String].self, forKey: .spokenLanguages)) ?? ["de", "en"]
    }

    func updating(modulePermissions: ModulePermissions) -> AdminProfile {
        AdminProfile(
            userId: userId,
            name: name,
            email: email,
            username: username,
            avatarUrl: avatarUrl,
            siteUrl: siteUrl,
            restBase: restBase,
            pluginVer: pluginVer,
            isSuperAdmin: isSuperAdmin,
            permissions: permissions,
            modulePermissions: modulePermissions,
            onboardingCompleted: onboardingCompleted,
            termsAccepted: termsAccepted,
            termsAcceptedAt: termsAcceptedAt,
            permissionStatus: permissionStatus,
            spokenLanguages: spokenLanguages
        )
    }

    private init(
        userId: Int,
        name: String,
        email: String,
        username: String?,
        avatarUrl: String?,
        siteUrl: String,
        restBase: String,
        pluginVer: String,
        isSuperAdmin: Bool,
        permissions: AdminPermissions,
        modulePermissions: ModulePermissions?,
        onboardingCompleted: Bool,
        termsAccepted: Bool,
        termsAcceptedAt: Int,
        permissionStatus: OnboardingPermissionStatus?,
        spokenLanguages: [String]
    ) {
        self.userId = userId
        self.name = name
        self.email = email
        self.username = username
        self.avatarUrl = avatarUrl
        self.siteUrl = siteUrl
        self.restBase = restBase
        self.pluginVer = pluginVer
        self.isSuperAdmin = isSuperAdmin
        self.permissions = permissions
        self.modulePermissions = modulePermissions
        self.onboardingCompleted = onboardingCompleted
        self.termsAccepted = termsAccepted
        self.termsAcceptedAt = termsAcceptedAt
        self.permissionStatus = permissionStatus
        self.spokenLanguages = spokenLanguages
    }

    var displayEmail: String {
        ""
    }

    var displayName: String {
        let cleaned = name.trimmingCharacters(in: .whitespacesAndNewlines)
        if !cleaned.isEmpty { return cleaned }
        if let username = normalizedUsername, !username.isEmpty, !username.contains("@") {
            return username
        }
        return L10n.CommonAdministrator
    }

    var displayUsernameIfDistinct: String? {
        nil
    }

    private var normalizedUsername: String? {
        guard let username else { return nil }
        let cleaned = username.trimmingCharacters(in: .whitespacesAndNewlines)
        return cleaned.isEmpty ? nil : cleaned
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
    let avatarUrl: String?
    let profileTitle: String?
    let profilePhone: String?
    let profileNotes: String?
    let onboardingCompleted: Bool
    let enabled: Bool
    let permissions: AdminPermissions
    let roleLabel: String?
    let isExecutive: Bool
    let isAdministrator: Bool
    let roleRank: Int
    let presenceStatus: String
    let lastSeen: Int
    let teamRole: String?
    let isProtected: Bool
    let requiresEdRequest: Bool
    let requiresContactRequest: Bool
    let canMessageEdDirectly: Bool

    enum CodingKeys: String, CodingKey {
        case userId = "user_id"
        case name, email, username
        case avatarUrl = "avatar_url"
        case profileTitle = "profile_title"
        case profilePhone = "profile_phone"
        case profileNotes = "profile_notes"
        case onboardingCompleted = "onboarding_completed"
        case enabled, permissions
        case roleLabel = "role_label"
        case isExecutive = "is_executive"
        case isAdministrator = "is_administrator"
        case roleRank = "role_rank"
        case presenceStatus = "presence_status"
        case lastSeen = "last_seen"
        case teamRole = "team_role"
        case isProtected = "protected"
        case requiresEdRequest = "requires_ed_request"
        case requiresContactRequest = "requires_contact_request"
        case canMessageEdDirectly = "can_message_ed_directly"
    }

    init(from decoder: Decoder) throws {
        let c = try decoder.container(keyedBy: CodingKeys.self)
        userId = LiveChatDecode.int(c, CodingKeys.userId)
        name = LiveChatDecode.string(c, CodingKeys.name)
        email = LiveChatDecode.string(c, CodingKeys.email)
        username = LiveChatDecode.string(c, CodingKeys.username)
        avatarUrl = try c.decodeIfPresent(String.self, forKey: .avatarUrl)
        profileTitle = try c.decodeIfPresent(String.self, forKey: .profileTitle)
        profilePhone = try c.decodeIfPresent(String.self, forKey: .profilePhone)
        profileNotes = try c.decodeIfPresent(String.self, forKey: .profileNotes)
        onboardingCompleted = (try? c.decode(Bool.self, forKey: .onboardingCompleted)) ?? false
        enabled = (try? c.decode(Bool.self, forKey: .enabled)) ?? false
        permissions = (try? c.decode(AdminPermissions.self, forKey: .permissions)) ?? AdminPermissions()
        roleLabel = try c.decodeIfPresent(String.self, forKey: .roleLabel)
        isExecutive = (try? c.decode(Bool.self, forKey: .isExecutive)) ?? false
        isAdministrator = (try? c.decode(Bool.self, forKey: .isAdministrator)) ?? false
        roleRank = LiveChatDecode.int(c, CodingKeys.roleRank)
        presenceStatus = LiveChatDecode.string(c, CodingKeys.presenceStatus).isEmpty
            ? "offline" : LiveChatDecode.string(c, CodingKeys.presenceStatus)
        lastSeen = LiveChatDecode.int(c, CodingKeys.lastSeen)
        teamRole = try c.decodeIfPresent(String.self, forKey: .teamRole)
        isProtected = (try? c.decode(Bool.self, forKey: .isProtected)) ?? false
        requiresEdRequest = (try? c.decode(Bool.self, forKey: .requiresEdRequest)) ?? false
        requiresContactRequest = (try? c.decode(Bool.self, forKey: .requiresContactRequest)) ?? false
        canMessageEdDirectly = (try? c.decode(Bool.self, forKey: .canMessageEdDirectly)) ?? false
    }

    var displayRoleLabel: String {
        if let roleLabel, !roleLabel.isEmpty {
            return Self.localizedRoleLabel(roleLabel)
        }
        if isExecutive { return L10n.RoleExecutiveDirector }
        if isAdministrator { return L10n.RoleAdministrator }
        if permissions.manageUsers { return L10n.RoleSeniorStaff }
        return L10n.RoleStaffMember
    }

    var isOnline: Bool { presenceStatus == "online" }

    var publicDisplaySubtitle: String {
        if isExecutive { return L10n.RoleExecutiveDirector }
        if let profileTitle, !profileTitle.isEmpty {
            let trimmed = profileTitle.trimmingCharacters(in: .whitespacesAndNewlines)
            if !trimmed.isEmpty, !Self.isPlaceholderProfileTitle(trimmed) {
                return Self.localizedRoleLabel(trimmed)
            }
        }
        if let roleLabel, !roleLabel.isEmpty, !Self.isPlaceholderProfileTitle(roleLabel) {
            return Self.localizedRoleLabel(roleLabel)
        }
        return displayRoleLabel
    }

    private static func localizedRoleLabel(_ value: String) -> String {
        switch value.trimmingCharacters(in: .whitespacesAndNewlines) {
        case "Executive Director":
            return L10n.RoleExecutiveDirector
        case "Administrator":
            return L10n.RoleAdministrator
        case "Senior Staff":
            return L10n.RoleSeniorStaff
        case "Staff Member", "Team Member":
            return L10n.RoleStaffMember
        default:
            return value
        }
    }

    private static func isPlaceholderProfileTitle(_ value: String) -> Bool {
        let normalized = value.lowercased()
        return normalized.contains("management system")
            || normalized == "system"
            || normalized == "management"
    }
}

struct OnboardingPermissionStatus: Codable, Equatable {
    let notifications: String
    let location: String

    init(notifications: String = "", location: String = "") {
        self.notifications = notifications
        self.location = location
    }

    init(from decoder: Decoder) throws {
        let c = try decoder.container(keyedBy: CodingKeys.self)
        notifications = (try? c.decode(String.self, forKey: .notifications)) ?? ""
        location = (try? c.decode(String.self, forKey: .location)) ?? ""
    }

    enum CodingKeys: String, CodingKey {
        case notifications
        case location
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

enum TeamRoleKey: String, CaseIterable, Identifiable {
    case executiveDirector = "executive_director"
    case administrator = "administrator"
    case seniorStaff = "senior_staff"
    case staffMember = "staff_member"
    case teamMember = "team_member"

    var id: String { rawValue }

    var label: String {
        switch self {
        case .executiveDirector: return L10n.RoleExecutiveDirector
        case .administrator: return L10n.RoleAdministrator
        case .seniorStaff: return L10n.RoleSeniorStaff
        case .staffMember: return L10n.RoleStaffMember
        case .teamMember: return L10n.RoleTeamMember
        }
    }

    static var assignable: [TeamRoleKey] {
        [.administrator, .seniorStaff, .staffMember, .teamMember]
    }
}

struct TeamManagementOverview: Codable {
    let executiveDirectorEmail: String
    let totalMembers: Int
    let enabledMembers: Int
    let pendingRequestCount: Int
    let membersByRole: [String: Int]
    let policy: TeamContactPolicy
    let hierarchy: [TeamHierarchyLevel]

    enum CodingKeys: String, CodingKey {
        case executiveDirectorEmail = "executive_director_email"
        case totalMembers = "total_members"
        case enabledMembers = "enabled_members"
        case pendingRequestCount = "pending_request_count"
        case membersByRole = "members_by_role"
        case policy, hierarchy
    }
}

struct TeamHierarchyLevel: Codable, Identifiable {
    var id: String { role }
    let role: String
    let roleLabel: String
    let members: [TeamHierarchyMember]

    enum CodingKeys: String, CodingKey {
        case role
        case roleLabel = "role_label"
        case members
    }
}

struct TeamHierarchyMember: Codable, Identifiable {
    var id: Int { userId }
    let userId: Int
    let name: String
    let email: String
    let enabled: Bool
    let roleLabel: String

    enum CodingKeys: String, CodingKey {
        case userId = "user_id"
        case name, email, enabled
        case roleLabel = "role_label"
    }
}

struct TeamContactPolicy: Codable, Equatable {
    let edRequestRequiredForAll: Bool
    let requireEdApproval: Bool
    let requireAdminApproval: Bool
    let requireManagerApproval: Bool
    let edEmail: String

    enum CodingKeys: String, CodingKey {
        case edRequestRequiredForAll = "ed_request_required_for_all"
        case requireEdApproval = "require_ed_approval"
        case requireAdminApproval = "require_admin_approval"
        case requireManagerApproval = "require_manager_approval"
        case edEmail = "ed_email"
    }
}

struct TeamManagementMembersResponse: Codable {
    let members: [StaffMember]
}

struct TeamManagementMemberResponse: Codable {
    let ok: Bool
    let member: StaffMember?
}

struct TeamManagementPolicyResponse: Codable {
    let ok: Bool?
    let policy: TeamContactPolicy
}

extension Array where Element == StaffMember {
    func deduplicatedByUserId() -> [StaffMember] {
        var seen = Set<Int>()
        return filter { seen.insert($0.userId).inserted }
    }
}
