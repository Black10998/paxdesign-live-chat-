import Foundation

struct ConversationSnapshot: Codable {
    let sessionId: String
    let handler: String
    let handlerLabel: String
    let adminName: String
    let customerName: String
    let assignedAgent: EmployeeIdentity?
    let detectedService: String
    let updatedAt: String
    let sessionRating: Int
    let seq: Int
    let messageCount: Int
    let messages: [LiveMessage]
    let cachedAt: TimeInterval

    init(sessionId: String, response: PollResponse, cachedAt: TimeInterval = Date().timeIntervalSince1970) {
        self.sessionId = sessionId
        handler = response.handler
        handlerLabel = response.handlerLabel
        adminName = response.adminName
        customerName = response.customerName
        assignedAgent = response.assignedAgent
        detectedService = response.detectedService
        updatedAt = response.updatedAt
        sessionRating = response.sessionRating
        seq = response.seq
        messageCount = max(response.messageCount, response.messages.count, response.seq)
        messages = response.messages
        self.cachedAt = cachedAt
    }

    var cachedDate: Date { Date(timeIntervalSince1970: cachedAt) }

    func toPollResponse() -> PollResponse {
        CachedPollPayload(
            handler: handler,
            handlerLabel: handlerLabel,
            adminName: adminName,
            customerName: customerName,
            assignedAgent: assignedAgent,
            sessionRating: sessionRating,
            detectedService: detectedService,
            updatedAt: updatedAt,
            seq: seq,
            messageCount: messageCount,
            messages: messages
        ).asPollResponse()
    }
}

@MainActor
final class ConversationHistoryStore {
    static let shared = ConversationHistoryStore()

    private let memoryLimit = 300
    private var memory: [String: ConversationSnapshot] = [:]
    private var memoryOrder: [String] = []
    private let directoryURL: URL
    private let encoder = JSONEncoder()
    private let decoder = JSONDecoder()
    private var siteScope = ""

    private init() {
        let base = FileManager.default.urls(for: .applicationSupportDirectory, in: .userDomainMask).first
            ?? FileManager.default.temporaryDirectory
        directoryURL = base.appendingPathComponent("ConversationHistory", isDirectory: true)
        try? FileManager.default.createDirectory(at: directoryURL, withIntermediateDirectories: true)
    }

    func setSiteScope(_ siteURL: String) {
        let normalized = siteURL.trimmingCharacters(in: .whitespacesAndNewlines).lowercased()
        guard normalized != siteScope else { return }
        siteScope = normalized
        memory.removeAll()
        memoryOrder.removeAll()
        warmAllFromDisk()
    }

    func warmAllFromDisk() {
        guard let items = try? FileManager.default.contentsOfDirectory(at: directoryURL, includingPropertiesForKeys: nil) else {
            return
        }
        let scopePrefix = (siteScope.isEmpty ? "default" : SiteScopeKey.make(siteScope)) + "-"
        for url in items where url.pathExtension == "json" {
            let name = url.lastPathComponent
            guard name.hasPrefix(scopePrefix) else { continue }
            guard let data = try? Data(contentsOf: url),
                  let snapshot = try? decoder.decode(ConversationSnapshot.self, from: data) else {
                continue
            }
            storeInMemory(snapshot)
        }
    }

    func snapshot(for sessionId: String) -> ConversationSnapshot? {
        if let cached = memory[sessionId] {
            return cached
        }
        guard let loaded = loadFromDisk(sessionId: sessionId) else { return nil }
        storeInMemory(loaded)
        return loaded
    }

    func save(_ response: PollResponse, sessionId: String) {
        let snapshot = ConversationSnapshot(sessionId: sessionId, response: response)
        storeInMemory(snapshot)
        writeToDisk(snapshot)
    }

    func mergeMessage(sessionId: String, message: LiveMessage, seq: Int) {
        let base = snapshot(for: sessionId)
        var messages = base?.messages ?? []
        let mergeResult = MessageMerge.mergeSorted(existing: messages, incoming: [message])
        guard mergeResult.changed else { return }
        messages = mergeResult.messages
        let resolvedSeq = max(seq, message.id, base?.seq ?? 0)
        let payload = CachedPollPayload(
            handler: base?.handler ?? "ai",
            handlerLabel: base?.handlerLabel ?? "",
            adminName: base?.adminName ?? "",
            customerName: base?.customerName ?? "",
            assignedAgent: base?.assignedAgent,
            sessionRating: base?.sessionRating ?? 0,
            detectedService: base?.detectedService ?? "",
            updatedAt: base?.updatedAt ?? "",
            seq: resolvedSeq,
            messageCount: max(base?.messageCount ?? 0, messages.count, resolvedSeq),
            messages: messages
        )
        save(payload.asPollResponse(), sessionId: sessionId)
    }

    func isFresh(_ sessionId: String, maxAge: TimeInterval = 45) -> Bool {
        guard let snapshot = snapshot(for: sessionId) else { return false }
        return Date().timeIntervalSince(snapshot.cachedDate) <= maxAge
    }

    func clearAll() {
        memory.removeAll()
        memoryOrder.removeAll()
        siteScope = ""
        if let items = try? FileManager.default.contentsOfDirectory(at: directoryURL, includingPropertiesForKeys: nil) {
            for url in items { try? FileManager.default.removeItem(at: url) }
        }
    }

    func purge(sessionId: String) {
        memory.removeValue(forKey: sessionId)
        memoryOrder.removeAll { $0 == sessionId }
        try? FileManager.default.removeItem(at: fileURL(for: sessionId))
    }

    private func storeInMemory(_ snapshot: ConversationSnapshot) {
        memory[snapshot.sessionId] = snapshot
        memoryOrder.removeAll { $0 == snapshot.sessionId }
        memoryOrder.append(snapshot.sessionId)
        while memoryOrder.count > memoryLimit {
            let evicted = memoryOrder.removeFirst()
            memory.removeValue(forKey: evicted)
        }
    }

    private func fileURL(for sessionId: String) -> URL {
        let safe = sessionId
            .replacingOccurrences(of: "/", with: "_")
            .replacingOccurrences(of: ":", with: "_")
        let scope = siteScope.isEmpty ? "default" : SiteScopeKey.make(siteScope)
        return directoryURL.appendingPathComponent("\(scope)-\(safe).json")
    }

    private func loadFromDisk(sessionId: String) -> ConversationSnapshot? {
        let url = fileURL(for: sessionId)
        guard let data = try? Data(contentsOf: url),
              let snapshot = try? decoder.decode(ConversationSnapshot.self, from: data),
              snapshot.sessionId == sessionId else {
            return nil
        }
        return snapshot
    }

    private func writeToDisk(_ snapshot: ConversationSnapshot) {
        let url = fileURL(for: snapshot.sessionId)
        guard let data = try? encoder.encode(snapshot) else { return }
        try? data.write(to: url, options: .atomic)
    }
}

struct CachedPollPayload {
    let handler: String
    let handlerLabel: String
    let adminName: String
    let customerName: String
    let assignedAgent: EmployeeIdentity?
    let sessionRating: Int
    let detectedService: String
    let updatedAt: String
    let seq: Int
    let messageCount: Int
    let messages: [LiveMessage]

    func asPollResponse() -> PollResponse {
        var payload: [String: Any] = [
            "handler": handler,
            "handler_label": handlerLabel,
            "admin_name": adminName,
            "admin_user_id": 0,
            "customer_name": customerName,
            "session_rating": sessionRating,
            "detected_service": detectedService,
            "updated_at": updatedAt,
            "seq": seq,
            "message_count": messageCount,
            "last_read_seq": 0,
            "messages": messages.map(messageDictionary),
            "admin_typing": false,
            "user_typing": false,
            "reactions": [:],
        ]
        if let assignedAgent,
           let agentData = try? JSONEncoder().encode(assignedAgent),
           let agentObject = try? JSONSerialization.jsonObject(with: agentData) {
            payload["assigned_agent"] = agentObject
        } else {
            payload["assigned_agent"] = NSNull()
        }

        guard JSONSerialization.isValidJSONObject(payload),
              let data = try? JSONSerialization.data(withJSONObject: payload),
              let decoded = try? JSONDecoder().decode(PollResponse.self, from: data) else {
            return PollResponse.emptySnapshot
        }
        return decoded
    }

    private func messageDictionary(_ message: LiveMessage) -> [String: Any] {
        var dict: [String: Any] = [
            "id": message.id,
            "role": message.role,
            "content": message.content,
        ]
        if let clientMsgId = message.clientMsgId { dict["client_msg_id"] = clientMsgId }
        if let ts = message.ts { dict["ts"] = ts }
        if let imageUrl = message.imageUrl { dict["image_url"] = imageUrl }
        if let replyTo = message.replyTo { dict["reply_to"] = replyTo }
        if let reaction = message.reaction { dict["reaction"] = reaction }
        if let senderId = message.senderId { dict["sender_id"] = senderId }
        if let senderName = message.senderName { dict["sender_name"] = senderName }
        if let senderAvatar = message.senderAvatar { dict["sender_avatar"] = senderAvatar }
        if let senderRole = message.senderRole { dict["sender_role"] = senderRole }
        return dict
    }
}
