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

    func enqueue(_ message: PendingOutboundMessage) {
        entries[message.id] = message
        persist()
    }

    func acknowledge(clientMsgId: String) {
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
        entries.removeAll()
        try? FileManager.default.removeItem(at: url)
    }

    private func persist() {
        guard let data = try? encoder.encode(entries) else { return }
        try? data.write(to: url, options: .atomic)
    }
}
