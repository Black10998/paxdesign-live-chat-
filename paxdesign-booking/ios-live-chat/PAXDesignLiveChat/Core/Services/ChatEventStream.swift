import Foundation

struct ChatStreamEvent {
    let id: Int
    let type: String
    let payload: [String: Any]
}

enum ChatEventStreamParser {
    static func parseLine(_ line: String, dataBuffer: inout String) -> ChatStreamEvent? {
        if line.hasPrefix("data:") {
            dataBuffer = String(line.dropFirst(5)).trimmingCharacters(in: .whitespaces)
            return nil
        }
        if line.isEmpty, !dataBuffer.isEmpty {
            defer { dataBuffer = "" }
            guard let jsonData = dataBuffer.data(using: .utf8),
                  let object = try? JSONSerialization.jsonObject(with: jsonData) as? [String: Any] else {
                return nil
            }
            let id = object["id"] as? Int ?? 0
            let type = object["type"] as? String ?? ""
            let payload = object["payload"] as? [String: Any] ?? [:]
            return ChatStreamEvent(id: id, type: type, payload: payload)
        }
        return nil
    }
}

@MainActor
final class ChatEventStream {
    static let shared = ChatEventStream()

    typealias InboxHandler = @MainActor (ChatStreamEvent) -> Void

    private var inboxHandlers: [UUID: InboxHandler] = [:]
    private var inboxTask: Task<Void, Never>?
    private weak var inboxApi: LiveChatAPI?
    private var inboxSince = 0

    private var threadTask: Task<Void, Never>?
    private var threadSince = 0
    private var threadSessionId = ""
    private var threadHandler: InboxHandler?

    func subscribeInbox(id: UUID, api: LiveChatAPI, handler: @escaping InboxHandler) {
        inboxHandlers[id] = handler
        inboxApi = api
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

    func startThread(api: LiveChatAPI, sessionId: String, isTeam: Bool, onEvent: @escaping InboxHandler) {
        threadTask?.cancel()
        threadSessionId = sessionId
        threadSince = 0
        threadHandler = onEvent
        let path = isTeam ? "team/sessions/\(sessionId)/stream" : "sessions/\(sessionId)/stream"
        threadTask = Task { @MainActor in
            while !Task.isCancelled {
                do {
                    let since = threadSince
                    try await api.consumeEventStream(path: path, since: since) { event in
                        Task { @MainActor in
                            if event.id > 0 {
                                self.threadSince = max(self.threadSince, event.id)
                            }
                            self.threadHandler?(event)
                        }
                    }
                } catch {
                    if Task.isCancelled { break }
                    try? await Task.sleep(nanoseconds: 400_000_000)
                }
            }
        }
    }

    func stopInbox() {
        inboxHandlers.removeAll()
        inboxTask?.cancel()
        inboxTask = nil
        inboxApi = nil
    }

    func stopThread() {
        threadTask?.cancel()
        threadTask = nil
        threadSessionId = ""
        threadHandler = nil
    }

    func stopAll() {
        stopInbox()
        stopThread()
    }

    private func ensureInboxStream() {
        guard inboxTask == nil, let api = inboxApi else { return }
        inboxTask = Task { @MainActor in
            while !Task.isCancelled {
                guard !inboxHandlers.isEmpty, let api = self.inboxApi else { break }
                do {
                    let since = inboxSince
                    try await api.consumeEventStream(path: "events/stream", since: since) { event in
                        Task { @MainActor in
                            if event.id > 0 {
                                self.inboxSince = max(self.inboxSince, event.id)
                            }
                            for handler in self.inboxHandlers.values {
                                handler(event)
                            }
                        }
                    }
                } catch {
                    if Task.isCancelled { break }
                    try? await Task.sleep(nanoseconds: 400_000_000)
                }
            }
        }
    }
}
