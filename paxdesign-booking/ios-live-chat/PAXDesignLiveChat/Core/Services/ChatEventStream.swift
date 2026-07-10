import Foundation

struct ChatStreamEvent: Sendable {
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

    private var inboxTask: Task<Void, Never>?
    private var threadTask: Task<Void, Never>?
    private var inboxSince = 0
    private var threadSince = 0
    private var threadSessionId = ""

    func startInbox(api: LiveChatAPI, onEvent: @escaping (ChatStreamEvent) -> Void) {
        inboxTask?.cancel()
        inboxTask = Task {
            while !Task.isCancelled {
                do {
                    try await api.consumeEventStream(path: "events/stream", since: inboxSince) { event in
                        if event.id > 0 { self.inboxSince = max(self.inboxSince, event.id) }
                        onEvent(event)
                    }
                } catch {
                    if Task.isCancelled { break }
                    try? await Task.sleep(nanoseconds: 800_000_000)
                }
            }
        }
    }

    func startThread(api: LiveChatAPI, sessionId: String, isTeam: Bool, onEvent: @escaping (ChatStreamEvent) -> Void) {
        threadTask?.cancel()
        threadSessionId = sessionId
        threadSince = 0
        let path = isTeam ? "team/sessions/\(sessionId)/stream" : "sessions/\(sessionId)/stream"
        threadTask = Task {
            while !Task.isCancelled {
                do {
                    try await api.consumeEventStream(path: path, since: threadSince) { event in
                        if event.id > 0 { self.threadSince = max(self.threadSince, event.id) }
                        onEvent(event)
                    }
                } catch {
                    if Task.isCancelled { break }
                    try? await Task.sleep(nanoseconds: 800_000_000)
                }
            }
        }
    }

    func stopInbox() {
        inboxTask?.cancel()
        inboxTask = nil
    }

    func stopThread() {
        threadTask?.cancel()
        threadTask = nil
        threadSessionId = ""
    }

    func stopAll() {
        stopInbox()
        stopThread()
    }
}
