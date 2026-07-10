import Foundation

@MainActor
final class TeamMessagingCoordinator: ObservableObject {
    static let shared = TeamMessagingCoordinator()

    @Published var teamSessions: [LiveSession] = []
    @Published var isLoading = false
    @Published var errorMessage: String?

    private var pollTask: Task<Void, Never>?
    private let inboxSubscriptionId = UUID()

    static func mergeTeamSessions(teamSessions: [LiveSession], coordinatorSessions: [LiveSession]) -> [LiveSession] {
        var merged: [String: LiveSession] = [:]
        for session in coordinatorSessions where session.isTeamDM {
            merged[session.sessionId] = session
        }
        for session in teamSessions {
            if let existing = merged[session.sessionId] {
                merged[session.sessionId] = existing.updatedAt >= session.updatedAt ? existing : session
            } else {
                merged[session.sessionId] = session
            }
        }
        return merged.values.sorted { $0.updatedAt > $1.updatedAt }
    }

    func unreadCount(settings: AppSettingsStore, coordinatorSessions: [LiveSession] = []) -> Int {
        Self.mergeTeamSessions(teamSessions: teamSessions, coordinatorSessions: coordinatorSessions)
            .filter { settings.isSessionUnread($0) }
            .count
    }

    func start(auth: AuthStore) {
        guard auth.isLoggedIn else { return }
        stop()
        pollTask = Task { [weak self] in
            while !Task.isCancelled {
                await self?.refresh(auth: auth)
                try? await Task.sleep(nanoseconds: AppRefreshPolicy.teamListInterval)
            }
        }
        if let api = auth.api {
            ChatEventStream.shared.subscribeInbox(id: inboxSubscriptionId, api: api) { [weak self] event in
                guard let self else { return }
                guard let sessionId = event.payload["session_id"] as? String, sessionId.hasPrefix("team_") else {
                    return
                }
                if event.type == "conversation_deleted" {
                    Task { await self.refresh(auth: auth) }
                    return
                }
                if event.type == "message" || event.type == "read" || event.type == "session_update" {
                    Task { await self.refresh(auth: auth) }
                }
            }
        }
    }

    func stop() {
        pollTask?.cancel()
        pollTask = nil
        ChatEventStream.shared.unsubscribeInbox(id: inboxSubscriptionId)
    }

    func deleteConversation(sessionId: String, mode: String, auth: AuthStore) async -> (success: Bool, message: String?) {
        guard let api = auth.api else { return (false, "Not signed in.") }
        do {
            let response = try await api.deleteTeamConversation(sessionId, mode: mode)
            teamSessions.removeAll { $0.sessionId == sessionId }
            await refresh(auth: auth)
            return (response.ok, response.message)
        } catch {
            return (false, error.localizedDescription)
        }
    }

    func refresh(auth: AuthStore) async {
        guard auth.isLoggedIn, let api = auth.api else {
            if !teamSessions.isEmpty { teamSessions = [] }
            return
        }
        let shouldShowLoading = teamSessions.isEmpty
        if shouldShowLoading { isLoading = true }
        defer { if shouldShowLoading { isLoading = false } }
        do {
            let response = try await api.fetchTeamSessions()
            if response.sessions != teamSessions {
                teamSessions = response.sessions
            }
            errorMessage = nil
        } catch {
            if case LiveChatAPIError.unauthorized = error {
                auth.handleUnauthorized()
            } else {
                errorMessage = error.localizedDescription
            }
        }
    }

    func openConversation(with userId: Int, auth: AuthStore) async -> String? {
        guard let api = auth.api else { return nil }
        do {
            let response = try await api.openTeamConversation(userId: userId)
            await refresh(auth: auth)
            return response.conversationId
        } catch {
            errorMessage = error.localizedDescription
            return nil
        }
    }
}

@MainActor
final class TeamChatThreadModel: ObservableObject {
    @Published var messages: [LiveMessage] = []
    @Published var isLoadingMessages = true
    @Published var participantName = ""
    @Published var draft = ""
    @Published var isSending = false
    @Published var errorMessage: String?
    @Published var currentSeq = 0

    let sessionId: String
    var onConversationRemoved: (() -> Void)?
    private var pollSeq = 0
    private var pollTask: Task<Void, Never>?
    private weak var auth: AuthStore?
    private var threadSubscriptionId: UUID?
    private var lastStreamEventAt = Date.distantPast
    private let recoveryPollIntervalNs: UInt64 = 5_000_000_000
    private let streamStaleThreshold: TimeInterval = 20

    init(sessionId: String) {
        self.sessionId = sessionId
    }

    func start(auth: AuthStore) {
        self.auth = auth
        stop()
        pollTask = Task { [weak self] in
            guard let self else { return }
            await self.poll(auth: auth)
            guard !Task.isCancelled else { return }
            self.beginEventStream(auth: auth)
            self.lastStreamEventAt = Date()

            while !Task.isCancelled {
                try? await Task.sleep(nanoseconds: self.recoveryPollIntervalNs)
                guard !Task.isCancelled else { break }
                if Date().timeIntervalSince(self.lastStreamEventAt) >= self.streamStaleThreshold {
                    await self.poll(auth: auth)
                }
            }
        }
    }

    func stop() {
        pollTask?.cancel()
        pollTask = nil
        if let threadSubscriptionId {
            ChatEventStream.shared.unsubscribeThread(id: threadSubscriptionId)
            self.threadSubscriptionId = nil
        }
    }

    private func beginEventStream(auth: AuthStore) {
        guard let api = auth.api else { return }
        if let threadSubscriptionId {
            ChatEventStream.shared.unsubscribeThread(id: threadSubscriptionId)
        }
        threadSubscriptionId = ChatEventStream.shared.subscribeThread(api: api, sessionId: sessionId, isTeam: true) { [weak self] event in
            guard let self else { return }
            self.lastStreamEventAt = Date()
            if event.type == "conversation_deleted" {
                self.onConversationRemoved?()
                return
            }
            if event.type == "read" {
                if let seq = event.payload["seq"] as? Int, seq > self.currentSeq {
                    self.currentSeq = seq
                }
                return
            }
            if event.type == "message" {
                let eventSeq = (event.payload["seq"] as? Int) ?? 0
                var insertedInline = false
                if let inline = LiveMessage.fromStreamPayload(event.payload["message"]) {
                    self.insertIncomingMessages([inline])
                    insertedInline = true
                }
                if eventSeq > self.currentSeq + 1 || !insertedInline {
                    Task { await self.poll(auth: auth) }
                }
            }
        }
    }

    func poll(auth: AuthStore) async {
        guard let api = auth.api else {
            isLoadingMessages = false
            return
        }
        do {
            let response = try await api.pollTeamSession(sessionId, since: pollSeq, full: pollSeq == 0)
            participantName = response.customerName
            if pollSeq == 0 {
                messages = normalizeTeamMessages(response.messages)
                isLoadingMessages = false
            } else if !response.messages.isEmpty {
                insertIncomingMessages(response.messages)
            }
            pollSeq = max(pollSeq, response.seq)
            currentSeq = max(currentSeq, response.seq)
            errorMessage = nil
        } catch {
            isLoadingMessages = false
            if case LiveChatAPIError.unauthorized = error {
                auth.handleUnauthorized()
            } else {
                errorMessage = error.localizedDescription
            }
        }
    }

    func markRead(auth: AuthStore) async {
        guard let api = auth.api, currentSeq > 0 else { return }
        _ = try? await api.markTeamSessionRead(sessionId, seq: currentSeq)
    }

    func send(auth: AuthStore, teamCoordinator: TeamMessagingCoordinator) async {
        let text = draft.trimmingCharacters(in: .whitespacesAndNewlines)
        guard !text.isEmpty, let api = auth.api else { return }

        let tempId = -(Int(Date().timeIntervalSince1970 * 1000) % 1_000_000_000)
        let optimistic = LiveMessage(
            id: tempId,
            role: "admin",
            content: text,
            ts: Int(Date().timeIntervalSince1970),
            senderId: auth.profile?.userId,
            senderName: auth.profile?.displayName
        )
        messages.append(optimistic)
        draft = ""
        isSending = true
        defer { isSending = false }

        do {
            let sent = try await api.sendTeamMessage(sessionId, content: text)
            if let index = messages.firstIndex(where: { $0.id == tempId }) {
                messages[index] = normalizeTeamMessage(sent.message)
            } else {
                insertIncomingMessages([sent.message])
            }
            pollSeq = max(pollSeq, sent.seq)
            currentSeq = max(currentSeq, sent.seq)
            MessageSendSound.shared.playIfEnabled()
            await teamCoordinator.refresh(auth: auth)
            await markRead(auth: auth)
            await poll(auth: auth)
            PAXHaptics.light()
        } catch {
            messages.removeAll { $0.id == tempId }
            draft = text
            errorMessage = error.localizedDescription
        }
    }

    private func insertIncomingMessages(_ incoming: [LiveMessage]) {
        let normalized = normalizeTeamMessages(incoming)
        let result = MessageMerge.mergeSorted(existing: messages, incoming: normalized)
        if result.changed {
            messages = result.messages
        }
        let maxId = normalized.map(\.id).filter { $0 > 0 }.max() ?? 0
        if maxId > 0 {
            pollSeq = max(pollSeq, maxId)
            currentSeq = max(currentSeq, maxId)
        }
    }

    private func normalizeTeamMessages(_ incoming: [LiveMessage]) -> [LiveMessage] {
        incoming.map { normalizeTeamMessage($0) }
    }

    private func normalizeTeamMessage(_ message: LiveMessage) -> LiveMessage {
        guard let myId = auth?.profile?.userId, let senderId = message.senderId else {
            return message
        }
        let expectedRole = senderId == myId ? "admin" : "user"
        guard message.role != expectedRole else { return message }
        return LiveMessage(
            id: message.id,
            role: expectedRole,
            content: message.content,
            ts: message.ts,
            imageUrl: message.imageUrl,
            replyTo: message.replyTo,
            reaction: message.reaction,
            senderId: message.senderId,
            senderName: message.senderName,
            senderAvatar: message.senderAvatar,
            senderRole: message.senderRole
        )
    }
}

struct TeamOpenResponse: Codable {
    let conversationId: String
    let session: LiveSession?

    enum CodingKeys: String, CodingKey {
        case conversationId = "conversation_id"
        case session
    }
}

struct TeamSendResponse: Codable {
    let ok: Bool
    let message: LiveMessage
    let seq: Int
}

struct TeamReadResponse: Codable {
    let ok: Bool
    let lastReadSeq: Int

    enum CodingKeys: String, CodingKey {
        case ok
        case lastReadSeq = "last_read_seq"
    }
}

struct TeamDeleteResponse: Codable {
    let ok: Bool
    let mode: String
    let message: String
}
