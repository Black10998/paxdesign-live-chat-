import Foundation

@MainActor
final class TeamMessagingCoordinator: ObservableObject {
    static let shared = TeamMessagingCoordinator()

    @Published var teamSessions: [LiveSession] = []
    @Published var isLoading = false
    @Published var errorMessage: String?

    private var pollTask: Task<Void, Never>?

    func start(auth: AuthStore) {
        guard auth.isLoggedIn else { return }
        stop()
        pollTask = Task { [weak self] in
            while !Task.isCancelled {
                await self?.refresh(auth: auth)
                try? await Task.sleep(nanoseconds: 2_000_000_000)
            }
        }
    }

    func stop() {
        pollTask?.cancel()
        pollTask = nil
    }

    func refresh(auth: AuthStore) async {
        guard auth.isLoggedIn, let api = auth.api else {
            teamSessions = []
            return
        }
        isLoading = true
        defer { isLoading = false }
        do {
            let response = try await api.fetchTeamSessions()
            teamSessions = response.sessions
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
    @Published var participantName = ""
    @Published var draft = ""
    @Published var isSending = false
    @Published var errorMessage: String?

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
                try? await Task.sleep(nanoseconds: 800_000_000)
            }
        }
    }

    func stop() {
        pollTask?.cancel()
        pollTask = nil
    }

    func poll(auth: AuthStore) async {
        guard let api = auth.api else { return }
        do {
            let response = try await api.pollTeamSession(sessionId, since: pollSeq, full: pollSeq == 0)
            participantName = response.customerName
            if pollSeq == 0 {
                messages = response.messages
            } else if !response.messages.isEmpty {
                mergeMessages(response.messages)
            }
            pollSeq = max(pollSeq, response.seq)
            errorMessage = nil
        } catch {
            errorMessage = error.localizedDescription
        }
    }

    func send(auth: AuthStore) async {
        let text = draft.trimmingCharacters(in: .whitespacesAndNewlines)
        guard !text.isEmpty, let api = auth.api else { return }
        isSending = true
        defer { isSending = false }
        do {
            _ = try await api.sendTeamMessage(sessionId, content: text)
            draft = ""
            MessageSendSound.shared.playIfEnabled()
            await poll(auth: auth)
            PAXHaptics.light()
        } catch {
            errorMessage = error.localizedDescription
        }
    }

    private func mergeMessages(_ incoming: [LiveMessage]) {
        var map = Dictionary(uniqueKeysWithValues: messages.map { ($0.id, $0) })
        for msg in incoming {
            map[msg.id] = msg
        }
        messages = map.values.sorted { $0.id < $1.id }
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
}
