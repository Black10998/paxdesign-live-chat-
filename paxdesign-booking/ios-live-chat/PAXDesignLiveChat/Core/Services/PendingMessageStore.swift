import Foundation

struct PendingOutboundMessage: Codable, Identifiable, Hashable {
    enum Channel: String, Codable {
        case booking
        case team
    }

    let id: String
    let sessionId: String
    let channel: Channel
    let content: String
    let replyTo: Int?
    let createdAt: TimeInterval
    var attempts: Int
    var attachmentPath: String? = nil
    var filename: String? = nil
}

@MainActor
final class PendingMessageStore {
    static let shared = PendingMessageStore()

    private var entries: [String: PendingOutboundMessage] = [:]
    private let url: URL
    private let encoder = JSONEncoder()
    private let decoder = JSONDecoder()

    private init() {
        let base = FileManager.default.urls(for: .applicationSupportDirectory, in: .userDomainMask).first
            ?? FileManager.default.temporaryDirectory
        let directory = base.appendingPathComponent("ConversationHistory", isDirectory: true)
        try? FileManager.default.createDirectory(at: directory, withIntermediateDirectories: true)
        url = directory.appendingPathComponent("pending-outbox.json")
        if let data = try? Data(contentsOf: url),
           let decoded = try? decoder.decode([String: PendingOutboundMessage].self, from: data) {
            entries = decoded
        }
    }

    @discardableResult
    func enqueue(_ message: PendingOutboundMessage) -> Bool {
        entries[message.id] = message
        return persist()
    }

    @discardableResult
    func enqueueImage(_ message: PendingOutboundMessage, data: Data, filename: String) -> Bool {
        let fileURL = attachmentURL(clientMsgId: message.id, filename: filename)
        do {
            try data.write(to: fileURL, options: .atomic)
        } catch {
            return false
        }
        var stored = message
        stored.attachmentPath = fileURL.path
        stored.filename = filename
        if enqueue(stored) {
            return true
        }
        try? FileManager.default.removeItem(at: fileURL)
        return false
    }

    func acknowledge(clientMsgId: String) {
        if let path = entries[clientMsgId]?.attachmentPath {
            try? FileManager.default.removeItem(atPath: path)
        }
        entries.removeValue(forKey: clientMsgId)
        persist()
    }

    func pending(sessionId: String, channel: PendingOutboundMessage.Channel) -> [PendingOutboundMessage] {
        entries.values
            .filter { $0.sessionId == sessionId && $0.channel == channel }
            .sorted { $0.createdAt < $1.createdAt }
    }

    func noteAttempt(clientMsgId: String) {
        guard var entry = entries[clientMsgId] else { return }
        entry.attempts += 1
        entries[clientMsgId] = entry
        persist()
    }

    func clearAll() {
        for entry in entries.values {
            if let path = entry.attachmentPath {
                try? FileManager.default.removeItem(atPath: path)
            }
        }
        entries.removeAll()
        try? FileManager.default.removeItem(at: url)
    }

    @discardableResult
    private func persist() -> Bool {
        guard let data = try? encoder.encode(entries) else { return false }
        do {
            try data.write(to: url, options: .atomic)
            return true
        } catch {
            return false
        }
    }

    private func attachmentURL(clientMsgId: String, filename: String) -> URL {
        url.deletingLastPathComponent()
            .appendingPathComponent("pending-\(clientMsgId)-\(filename)")
    }
}
