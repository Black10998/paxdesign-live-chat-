import Foundation
import UserNotifications

@MainActor
final class ChatCoordinator: ObservableObject {
    @Published var sessions: [LiveSession] = []
    @Published var liveCount = 0
    @Published var isLoading = false
    @Published var errorMessage: String?
    @Published var incomingRequest: IncomingLiveRequest? {
        didSet {
            if incomingRequest != nil {
                IncomingCallRingtone.shared.startRinging()
            } else {
                IncomingCallRingtone.shared.stopRinging()
            }
        }
    }
    @Published var activeSessionId: String?

    private var listTask: Task<Void, Never>?
    private var knownLiveRequests = Set<String>()
    private var expiryTasks: [String: Task<Void, Never>] = [:]

    func start(auth: AuthStore) {
        stop()
        listTask = Task { [weak self] in
            while !Task.isCancelled {
                await self?.refreshSessions(auth: auth)
                try? await Task.sleep(nanoseconds: 800_000_000)
            }
        }
    }

    func stop() {
        listTask?.cancel()
        listTask = nil
        expiryTasks.values.forEach { $0.cancel() }
        expiryTasks.removeAll()
        IncomingCallRingtone.shared.stopRinging()
    }

    func refreshSessions(auth: AuthStore) async {
        guard let api = auth.api else { return }
        do {
            let response = try await api.fetchSessions()
            sessions = response.sessions
            liveCount = response.liveCount
            errorMessage = nil
            detectIncomingLiveRequests(response.sessions)
        } catch {
            errorMessage = error.localizedDescription
        }
    }

    private func detectIncomingLiveRequests(_ items: [LiveSession]) {
        let liveOnes = items.filter { $0.isLiveRequest }
        for session in liveOnes {
            guard !knownLiveRequests.contains(session.sessionId) else { continue }
            presentIncoming(session: session)
        }
    }

    func presentIncoming(session: LiveSession) {
        if incomingRequest?.id == session.sessionId { return }
        knownLiveRequests.insert(session.sessionId)
        incomingRequest = IncomingLiveRequest(id: session.sessionId, session: session)
        scheduleExpiry(for: session.sessionId)
    }

    private func scheduleExpiry(for sessionId: String) {
        expiryTasks[sessionId]?.cancel()
        expiryTasks[sessionId] = Task { [weak self] in
            try? await Task.sleep(nanoseconds: 120_000_000_000)
            await MainActor.run {
                guard self?.incomingRequest?.id == sessionId else { return }
                self?.acknowledgeIncomingRequest(sessionId)
            }
        }
    }

    func acknowledgeIncomingRequest(_ sessionId: String) {
        knownLiveRequests.insert(sessionId)
        expiryTasks[sessionId]?.cancel()
        expiryTasks.removeValue(forKey: sessionId)
        if incomingRequest?.id == sessionId {
            incomingRequest = nil
        }
    }

    func acceptLiveRequest(auth: AuthStore, session: LiveSession) async {
        guard let api = auth.api else { return }
        isLoading = true
        defer { isLoading = false }
        do {
            try await api.takeover(session.sessionId)
            acknowledgeIncomingRequest(session.sessionId)
            activeSessionId = session.sessionId
            await refreshSessions(auth: auth)
            PAXHaptics.success()
        } catch {
            errorMessage = error.localizedDescription
        }
    }

    func declineLiveRequest(auth: AuthStore, session: LiveSession) async {
        guard let api = auth.api else { return }
        isLoading = true
        defer { isLoading = false }
        do {
            try await api.decline(session.sessionId)
            acknowledgeIncomingRequest(session.sessionId)
            await refreshSessions(auth: auth)
            PAXHaptics.warning()
        } catch {
            errorMessage = error.localizedDescription
        }
    }

    func archiveSession(auth: AuthStore, session: LiveSession) async {
        guard let api = auth.api else { return }
        let sessionId = session.sessionId
        do {
            try await api.archive(sessionId)
            await refreshSessions(auth: auth)
            if activeSessionId == sessionId { activeSessionId = nil }
            PAXHaptics.light()
        } catch {
            errorMessage = error.localizedDescription
            await refreshSessions(auth: auth)
        }
    }

    func deleteSession(auth: AuthStore, session: LiveSession) async {
        guard let api = auth.api else { return }
        let sessionId = session.sessionId
        sessions.removeAll { $0.sessionId == sessionId }
        if activeSessionId == sessionId { activeSessionId = nil }
        do {
            try await api.deleteSession(sessionId)
            await refreshSessions(auth: auth)
            PAXHaptics.warning()
        } catch {
            errorMessage = error.localizedDescription
            await refreshSessions(auth: auth)
        }
    }

    func handlePush(sessionId: String, type: String, auth: AuthStore, payload: PushService.PushPayload? = nil) async {
        switch type {
        case "live_request":
            await presentLiveRequest(sessionId: sessionId, auth: auth, payload: payload)
        case "new_chat", "message":
            await refreshSessions(auth: auth)
            if type == "message" {
                activeSessionId = sessionId
            }
        default:
            activeSessionId = sessionId
        }
    }

    func handlePushAction(_ action: String, sessionId: String, auth: AuthStore) async {
        await refreshSessions(auth: auth)
        guard let session = sessions.first(where: { $0.sessionId == sessionId }) else { return }
        switch action {
        case "PAX_ACCEPT":
            await acceptLiveRequest(auth: auth, session: session)
        case "PAX_DECLINE":
            await declineLiveRequest(auth: auth, session: session)
        default:
            await presentLiveRequest(sessionId: sessionId, auth: auth, payload: nil)
        }
    }

    private func presentLiveRequest(sessionId: String, auth: AuthStore, payload: PushService.PushPayload?) async {
        if let existing = sessions.first(where: { $0.sessionId == sessionId && $0.isLiveRequest }) {
            presentIncoming(session: existing)
            return
        }

        await refreshSessions(auth: auth)
        if let session = sessions.first(where: { $0.sessionId == sessionId && $0.isLiveRequest }) {
            presentIncoming(session: session)
            return
        }

        if let payload {
            presentIncoming(session: LiveSession.fromPushPayload(sessionId: sessionId, payload: payload))
        }
    }
}

@MainActor
final class ChatThreadModel: ObservableObject {
    @Published var messages: [LiveMessage] = []
    @Published var handler = "ai"
    @Published var customerName = "Kunde"
    @Published var userTyping = false
    @Published var draft = ""
    @Published var isSending = false
    @Published var errorMessage: String?

    let sessionId: String
    private var pollSeq = 0
    private var pollTask: Task<Void, Never>?
    private var typingStopTask: Task<Void, Never>?

    init(sessionId: String) {
        self.sessionId = sessionId
    }

    func start(auth: AuthStore) {
        pollTask?.cancel()
        pollTask = Task { [weak self] in
            guard let self else { return }
            await self.loadFull(auth: auth)
            while !Task.isCancelled {
                await self.poll(auth: auth)
                try? await Task.sleep(nanoseconds: 650_000_000)
            }
        }
    }

    func stop() {
        pollTask?.cancel()
        pollTask = nil
        typingStopTask?.cancel()
        typingStopTask = nil
    }

    private func loadFull(auth: AuthStore) async {
        guard let api = auth.api else { return }
        do {
            let data = try await api.fetchSession(sessionId)
            applyPoll(data)
        } catch {
            errorMessage = error.localizedDescription
        }
    }

    private func poll(auth: AuthStore) async {
        guard let api = auth.api else { return }
        do {
            let data = try await api.pollSession(sessionId, since: pollSeq)
            applyPoll(data)
        } catch {
            errorMessage = error.localizedDescription
        }
    }

    private func applyPoll(_ data: PollResponse) {
        handler = data.handler
        customerName = data.customerName.isEmpty ? "Kunde" : data.customerName
        userTyping = data.userTyping
        pollSeq = max(pollSeq, data.seq)
        if !data.messages.isEmpty {
            var map = Dictionary(uniqueKeysWithValues: messages.map { ($0.id, $0) })
            for msg in data.messages {
                map[msg.id] = msg
            }
            messages = map.values.sorted { $0.id < $1.id }
        }
    }

    func send(auth: AuthStore) async {
        let text = draft.trimmingCharacters(in: .whitespacesAndNewlines)
        guard !text.isEmpty, let api = auth.api else { return }
        await notifyTypingStop(auth: auth)
        isSending = true
        defer { isSending = false }
        do {
            let msg = try await api.sendMessage(sessionId, text: text)
            draft = ""
            messages.append(msg)
            pollSeq = max(pollSeq, msg.id)
        } catch {
            errorMessage = error.localizedDescription
        }
    }

    func notifyTyping(auth: AuthStore) async {
        typingStopTask?.cancel()
        try? await auth.api?.setTyping(sessionId)
        typingStopTask = Task { [weak self] in
            try? await Task.sleep(nanoseconds: 1_500_000_000)
            guard !Task.isCancelled, let self else { return }
            try? await auth.api?.setTyping(self.sessionId, stop: true)
        }
    }

    func notifyTypingStop(auth: AuthStore) async {
        typingStopTask?.cancel()
        typingStopTask = nil
        try? await auth.api?.setTyping(sessionId, stop: true)
    }

    func reloadAfterTakeover(auth: AuthStore) async {
        guard let api = auth.api else { return }
        if let data = try? await api.fetchSession(sessionId) {
            handler = data.handler
            customerName = data.customerName.isEmpty ? "Kunde" : data.customerName
            messages = data.messages
            pollSeq = data.seq
        }
    }
}
