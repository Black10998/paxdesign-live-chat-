import Foundation

@MainActor
final class TeamMessagingCoordinator: ObservableObject {
    static let shared = TeamMessagingCoordinator()

    @Published var teamSessions: [LiveSession] = []
    @Published var isLoading = false
    @Published var errorMessage: String?

    private var pollTask: Task<Void, Never>?

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
    }

    func stop() {
        pollTask?.cancel()
        pollTask = nil
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
    private var pollSeq = 0
    private var pollTask: Task<Void, Never>?
    private weak var auth: AuthStore?

    init(sessionId: String) {
        self.sessionId = sessionId
    }

    func start(auth: AuthStore) {
        self.auth = auth
        stop()
        pollTask = Task { [weak self] in
            while !Task.isCancelled {
                await self?.poll(auth: auth)
                try? await Task.sleep(nanoseconds: AppRefreshPolicy.teamThreadInterval)
            }
        }
    }

    func stop() {
        pollTask?.cancel()
        pollTask = nil
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
                messages = response.messages
                isLoadingMessages = false
            } else if !response.messages.isEmpty {
                mergeMessages(response.messages)
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
            senderName: auth.profile?.displayName,
            senderId: auth.profile?.userId
        )
        messages.append(optimistic)
        draft = ""
        isSending = true
        defer { isSending = false }

        do {
            let sent = try await api.sendTeamMessage(sessionId, content: text)
            if let index = messages.firstIndex(where: { $0.id == tempId }) {
                messages[index] = sent.message
            } else {
                mergeMessages([sent.message])
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

    private func mergeMessages(_ incoming: [LiveMessage]) {
        let result = MessageMerge.mergeSorted(existing: messages, incoming: incoming)
        if result.changed {
            messages = result.messages
        }
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
