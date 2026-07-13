import Foundation

struct ChatStreamEvent {
    let id: Int
    let type: String
    let payload: [String: Any]
    let channel: String
}

enum ChatEventStreamParser {
    static func parseLine(_ line: String, dataBuffer: inout String) -> ChatStreamEvent? {
        if line.hasPrefix("data:") {
            let chunk = String(line.dropFirst(5)).trimmingCharacters(in: .whitespaces)
            if !dataBuffer.isEmpty {
                dataBuffer.append("\n")
            }
            dataBuffer.append(chunk)
            return nil
        }
        if line.hasPrefix("event:") || line.hasPrefix("id:") || line.hasPrefix("retry:") || line.hasPrefix(":") {
            return nil
        }
        if line.isEmpty, !dataBuffer.isEmpty {
            defer { dataBuffer = "" }
            guard let jsonData = dataBuffer.data(using: .utf8),
                  let object = try? JSONSerialization.jsonObject(with: jsonData) as? [String: Any] else {
                return nil
            }
            let id = StreamPayload.int(object["id"])
            let type = StreamPayload.string(object["type"])
            let payload = object["payload"] as? [String: Any] ?? [:]
            let channel = StreamPayload.string(object["channel"])
            return ChatStreamEvent(id: id, type: type, payload: payload, channel: channel)
        }
        return nil
    }
}

@MainActor
final class ChatEventStream {
    static let shared = ChatEventStream()

    typealias InboxHandler = @MainActor (ChatStreamEvent) async -> Void

    @MainActor
    private final class ThreadSubscription {
        let api: LiveChatAPI
        let path: String
        let channel: String
        let handler: InboxHandler
        var since = 0
        var task: Task<Void, Never>?

        init(api: LiveChatAPI, path: String, channel: String, handler: @escaping InboxHandler) {
            self.api = api
            self.path = path
            self.channel = channel
            self.handler = handler
            since = ChatCursorStore.shared.eventCursor(
                site: api.cursorScope,
                channel: channel
            )
        }
    }

    private struct PendingAck {
        let api: LiveChatAPI
        let channel: String
        let eventId: Int
        let seq: Int
    }

    private var inboxHandlers: [UUID: InboxHandler] = [:]
    private var inboxTask: Task<Void, Never>?
    private weak var inboxApi: LiveChatAPI?
    private var inboxSince = 0

    private var threadSubscriptions: [UUID: ThreadSubscription] = [:]
    private var pendingAcks: [String: PendingAck] = [:]
    private var ackFlushTask: Task<Void, Never>?
    private static let ackDebounceNs: UInt64 = 1_500_000_000
    private static let reconnectDelayNs: UInt64 = 1_000_000_000

    func subscribeInbox(id: UUID, api: LiveChatAPI, handler: @escaping InboxHandler) {
        inboxHandlers[id] = handler
        inboxApi = api
        inboxSince = ChatCursorStore.shared.eventCursor(
            site: api.cursorScope,
            channel: "inbox"
        )
        ensureInboxStream()
    }

    func unsubscribeInbox(id: UUID) {
        inboxHandlers.removeValue(forKey: id)
        if inboxHandlers.isEmpty {
            inboxTask?.cancel()
            inboxTask = nil
            inboxApi = nil
        }
    }

    @discardableResult
    func subscribeThread(api: LiveChatAPI, sessionId: String, isTeam: Bool, onEvent: @escaping InboxHandler) -> UUID {
        let id = UUID()
        let path = isTeam ? "team/sessions/\(sessionId)/stream" : "sessions/\(sessionId)/stream"
        let channel = isTeam ? "team:\(sessionId)" : "session:\(sessionId)"
        let subscription = ThreadSubscription(api: api, path: path, channel: channel, handler: onEvent)
        threadSubscriptions[id] = subscription
        subscription.task = Task { @MainActor in
            while !Task.isCancelled {
                guard let current = self.threadSubscriptions[id] else { break }
                do {
                    let since = current.since
                    try await current.api.consumeEventStream(path: current.path, since: since) { event in
                        guard let current = self.threadSubscriptions[id] else { return }
                        await current.handler(event)
                        if event.id > 0 {
                            current.since = max(current.since, event.id)
                            ChatCursorStore.shared.advance(
                                site: current.api.cursorScope,
                                channel: current.channel,
                                eventId: event.id
                            )
                            self.scheduleAck(
                                api: current.api,
                                channel: current.channel,
                                eventId: event.id,
                                seq: StreamPayload.int(event.payload["seq"])
                            )
                        }
                    }
                } catch {
                    if Task.isCancelled { break }
                    try? await Task.sleep(nanoseconds: Self.reconnectDelayNs)
                }
            }
        }
        return id
    }

    func unsubscribeThread(id: UUID) {
        guard let subscription = threadSubscriptions.removeValue(forKey: id) else { return }
        subscription.task?.cancel()
    }

    func stopInbox() {
        inboxHandlers.removeAll()
        inboxTask?.cancel()
        inboxTask = nil
        inboxApi = nil
        inboxSince = 0
        flushAcksNow()
    }

    func stopThreads() {
        for (_, subscription) in threadSubscriptions {
            subscription.task?.cancel()
        }
        threadSubscriptions.removeAll()
        flushAcksNow()
    }

    func stopAll() {
        stopInbox()
        stopThreads()
    }

    private func ensureInboxStream() {
        guard inboxTask == nil, let api = inboxApi else { return }
        inboxTask = Task { @MainActor in
            while !Task.isCancelled {
                guard !inboxHandlers.isEmpty, let api = self.inboxApi else { break }
                do {
                    let since = inboxSince
                    try await api.consumeEventStream(path: "events/stream", since: since) { event in
                        for handler in self.inboxHandlers.values {
                            await handler(event)
                        }
                        if event.id > 0 {
                            self.inboxSince = max(self.inboxSince, event.id)
                            ChatCursorStore.shared.advance(
                                site: api.cursorScope,
                                channel: "inbox",
                                eventId: event.id
                            )
                            self.scheduleAck(
                                api: api,
                                channel: event.channel.isEmpty ? "inbox:admins" : event.channel,
                                eventId: event.id,
                                seq: StreamPayload.int(event.payload["seq"])
                            )
                        }
                    }
                } catch {
                    if Task.isCancelled { break }
                    try? await Task.sleep(nanoseconds: Self.reconnectDelayNs)
                }
            }
        }
    }

    private func scheduleAck(api: LiveChatAPI, channel: String, eventId: Int, seq: Int) {
        guard eventId > 0 else { return }
        let existing = pendingAcks[channel]
        if let existing, existing.eventId >= eventId {
            return
        }
        pendingAcks[channel] = PendingAck(api: api, channel: channel, eventId: eventId, seq: seq)
        ackFlushTask?.cancel()
        ackFlushTask = Task { @MainActor in
            try? await Task.sleep(nanoseconds: Self.ackDebounceNs)
            guard !Task.isCancelled else { return }
            await self.flushAcks()
        }
    }

    private func flushAcks() async {
        let batch = pendingAcks.values
        pendingAcks.removeAll()
        ackFlushTask = nil
        for ack in batch {
            try? await ack.api.acknowledgeEvent(channel: ack.channel, eventId: ack.eventId, seq: ack.seq)
        }
    }

    private func flushAcksNow() {
        ackFlushTask?.cancel()
        ackFlushTask = nil
        let batch = pendingAcks.values
        pendingAcks.removeAll()
        for ack in batch {
            Task {
                try? await ack.api.acknowledgeEvent(channel: ack.channel, eventId: ack.eventId, seq: ack.seq)
            }
        }
    }
}
