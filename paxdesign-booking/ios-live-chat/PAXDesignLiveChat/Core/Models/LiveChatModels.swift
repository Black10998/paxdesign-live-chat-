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
        customerLanguage: String = ""
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
    }

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

    enum CodingKeys: String, CodingKey {
        case id, role, content, ts, reaction
        case clientMsgId = "client_msg_id"
        case imageUrl = "image_url"
        case replyTo = "reply_to"
        case senderId = "sender_id"
        case senderName = "sender_name"
        case senderAvatar = "sender_avatar"
        case senderRole = "sender_role"
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
        senderRole: String? = nil
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
    let messages: [LiveMessage]
    let adminTyping: Bool
    let userTyping: Bool
    let reactions: [String: String]

    enum CodingKeys: String, CodingKey {
        case handler
        case handlerLabel = "handler_label"
        case adminName = "admin_name"
        case adminUserId = "admin_user_id"
        case assignedAgent = "assigned_agent"
        case customerName = "customer_name"
        case sessionRating = "session_rating"
        case detectedService = "detected_service"
        case updatedAt = "updated_at"
        case seq
        case messageCount = "message_count"
        case messages, reactions
        case adminTyping = "admin_typing"
        case userTyping = "user_typing"
        case lastReadSeq = "last_read_seq"
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
        sessionRating = LiveChatDecode.int(container, CodingKeys.sessionRating)
        detectedService = LiveChatDecode.string(container, CodingKeys.detectedService)
        updatedAt = LiveChatDecode.string(container, CodingKeys.updatedAt)
        seq = LiveChatDecode.int(container, CodingKeys.seq)
        messageCount = LiveChatDecode.int(container, CodingKeys.messageCount)
        lastReadSeq = LiveChatDecode.int(container, CodingKeys.lastReadSeq)
        messages = LiveChatDecode.decodeLiveMessages(from: container, key: CodingKeys.messages)
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
        PrivacyMask.email(email, revealFull: isSuperAdmin)
    }

    var displayName: String {
        let cleaned = name.trimmingCharacters(in: .whitespacesAndNewlines)
        if !cleaned.isEmpty { return cleaned }
        if let username = normalizedUsername, !username.isEmpty { return username }
        let cleanedEmail = email.trimmingCharacters(in: .whitespacesAndNewlines)
        if !cleanedEmail.isEmpty { return cleanedEmail }
        return L10n.CommonAdministrator
    }

    var displayUsernameIfDistinct: String? {
        guard let username = normalizedUsername, !username.isEmpty else { return nil }
        let cleanedEmail = email.trimmingCharacters(in: .whitespacesAndNewlines)
        guard !cleanedEmail.isEmpty else {
            return PrivacyMask.email(username, revealFull: isSuperAdmin)
        }
        guard username.caseInsensitiveCompare(cleanedEmail) != .orderedSame else { return nil }
        return PrivacyMask.email(username, revealFull: isSuperAdmin)
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
    }

    var displayRoleLabel: String {
        if let roleLabel, !roleLabel.isEmpty { return roleLabel }
        if isExecutive { return "Executive Manager" }
        if isAdministrator { return "Administrator" }
        if permissions.manageUsers { return "Manager" }
        return "Team Member"
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
