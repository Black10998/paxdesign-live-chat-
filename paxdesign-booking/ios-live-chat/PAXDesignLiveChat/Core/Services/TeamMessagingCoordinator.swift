import Foundation

@MainActor
final class TeamMessagingCoordinator: ObservableObject {
    static let shared = TeamMessagingCoordinator()

    @Published var teamSessions: [LiveSession] = []
    @Published var pendingRequests: [LiveSession] = []
    @Published var isLoading = false
    @Published var errorMessage: String?

    private var pollTask: Task<Void, Never>?
    private let inboxSubscriptionId = UUID()
    private var inboxRefreshTask: Task<Void, Never>?
    private let teamRefreshGuard = RequestInFlightGuard()
    private static let inboxDebounceNs: UInt64 = 1_500_000_000

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
        let sorted = merged.values.sorted { $0.updatedAt > $1.updatedAt }
        var seenOtherUserIds = Set<Int>()
        return sorted.filter { session in
            guard session.isTeamDM, session.otherUserId > 0 else { return true }
            if seenOtherUserIds.contains(session.otherUserId) { return false }
            seenOtherUserIds.insert(session.otherUserId)
            return true
        }
    }

    func unreadCount(settings: AppSettingsStore, coordinatorSessions: [LiveSession] = []) -> Int {
        Self.mergeTeamSessions(teamSessions: teamSessions, coordinatorSessions: coordinatorSessions)
            .reduce(0) { partial, session in
                partial + settings.unreadMessageCount(for: session)
            }
    }

    func bumpTeamSession(sessionId: String, message: LiveMessage, seq: Int) {
        guard let index = teamSessions.firstIndex(where: { $0.sessionId == sessionId }) else { return }
        let updated = LiveSessionPatch.bumped(teamSessions[index], message: message, seq: seq)
        teamSessions.remove(at: index)
        teamSessions.insert(updated, at: 0)
        persistTeamListCache()
    }

    private func persistTeamListCache() {
        let existing = SessionListCache.shared.load()
        SessionListCache.shared.save(
            sessions: existing?.sessions ?? [],
            teamSessions: teamSessions,
            liveCount: existing?.liveCount ?? 0
        )
    }

    func start(auth: AuthStore) {
        guard auth.isLoggedIn else { return }
        stop()
        if let api = auth.api {
            SessionListCache.shared.setSiteScope(api.publicApiBaseURL)
            if teamSessions.isEmpty, let cached = SessionListCache.shared.load() {
                applyTeamSessions(cached.teamSessions)
            }
        }
        pollTask = Task { [weak self] in
            while !Task.isCancelled {
                guard let self else { return }
                if NetworkCircuitBreaker.shared.isOpen {
                    try? await Task.sleep(nanoseconds: 60_000_000_000)
                    continue
                }
                await self.refresh(auth: auth, mode: .lightweight)
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
                    self.scheduleInboxListRefresh(auth: auth)
                    return
                }
                if event.type == "conversation_request" || event.type == "request_update" {
                    Task { await self.refreshPendingRequests(auth: auth) }
                    self.scheduleInboxListRefresh(auth: auth)
                    if event.type == "conversation_request" {
                        InAppNotificationCoordinator.shared.handleTeamRequest(
                            sessionId: sessionId,
                            preview: (event.payload["request_note"] as? String) ?? "New conversation request"
                        )
                    }
                    return
                }
                if event.type == "typing" {
                    let typing = StreamPayload.bool(event.payload["typing"])
                    if typing {
                        ChatThreadRegistry.shared.teamThread(sessionId: sessionId).setRemoteTyping(true)
                    }
                    return
                }
                if event.type == "message" {
                    let incoming = StreamPayload.messages(from: event.payload)
                    for inline in incoming {
                        let seq = max(StreamPayload.int(event.payload["seq"]), inline.id)
                        ConversationHistoryStore.shared.mergeMessage(
                            sessionId: sessionId,
                            message: inline,
                            seq: seq
                        )
                        ChatThreadRegistry.shared.teamThread(sessionId: sessionId).applyLiveMessage(
                            inline,
                            seq: seq
                        )
                        self.bumpTeamSession(sessionId: sessionId, message: inline, seq: seq)
                    }
                    if !incoming.isEmpty {
                        var userInfo: [String: Any] = ["session_id": sessionId]
                        if incoming.count == 1 {
                            userInfo["inline_message"] = event.payload["message"]
                        }
                        NotificationCenter.default.post(
                            name: .paxSessionSync,
                            object: nil,
                            userInfo: userInfo
                        )
                    }
                }
                if event.type == "message" || event.type == "read" || event.type == "session_update" {
                    self.scheduleInboxListRefresh(auth: auth)
                }
            }
        }
        Task {
            await refreshPendingRequests(auth: auth)
            await touchPresence(auth: auth)
        }
    }

    func refreshPendingRequests(auth: AuthStore) async {
        guard auth.isLoggedIn, let api = auth.api else {
            pendingRequests = []
            return
        }
        if let response = try? await api.fetchPendingTeamRequests() {
            pendingRequests = response.sessions
        }
    }

    func touchPresence(auth: AuthStore) async {
        guard let api = auth.api else { return }
        _ = try? await api.touchTeamPresence()
    }

    func respondToRequest(sessionId: String, accept: Bool, auth: AuthStore) async -> Bool {
        guard let api = auth.api else { return false }
        do {
            _ = try await api.respondToTeamRequest(sessionId, accept: accept)
            await refresh(auth: auth)
            await refreshPendingRequests(auth: auth)
            NotificationCenter.default.post(name: .paxSessionSync, object: nil, userInfo: ["session_id": sessionId])
            return true
        } catch {
            errorMessage = error.localizedDescription
            return false
        }
    }

    func pinConversation(sessionId: String, pinned: Bool, auth: AuthStore) async {
        guard let api = auth.api else { return }
        _ = try? await api.pinTeamConversation(sessionId, pinned: pinned)
        await refresh(auth: auth, mode: .lightweight)
    }

    private func scheduleInboxListRefresh(auth: AuthStore) {
        inboxRefreshTask?.cancel()
        inboxRefreshTask = Task { [weak self] in
            try? await Task.sleep(nanoseconds: Self.inboxDebounceNs)
            guard !Task.isCancelled, let self else { return }
            await self.refresh(auth: auth, mode: .lightweight)
        }
    }

    func stop() {
        pollTask?.cancel()
        pollTask = nil
        inboxRefreshTask?.cancel()
        inboxRefreshTask = nil
        ChatEventStream.shared.unsubscribeInbox(id: inboxSubscriptionId)
    }

    func resetLoggedOutState() {
        stop()
        teamSessions = []
        pendingRequests = []
        isLoading = false
        errorMessage = nil
    }

    func deleteConversation(sessionId: String, mode: String, auth: AuthStore) async -> (success: Bool, message: String?) {
        guard let api = auth.api else { return (false, "Not signed in.") }
        let previous = teamSessions
        teamSessions.removeAll { $0.sessionId == sessionId }
        persistTeamListCache()
        ConversationHistoryStore.shared.purge(sessionId: sessionId)
        ChatThreadRegistry.shared.teamThread(sessionId: sessionId).resetForLogout()
        do {
            let response = try await api.deleteTeamConversation(sessionId, mode: mode)
            if !response.ok {
                teamSessions = previous
                persistTeamListCache()
            }
            return (response.ok, response.message)
        } catch {
            teamSessions = previous
            persistTeamListCache()
            return (false, error.localizedDescription)
        }
    }

    func applyTeamSessions(_ sessions: [LiveSession]) {
        if sessions != teamSessions {
            teamSessions = sessions
        }
        errorMessage = nil
    }

    func refresh(auth: AuthStore, mode: SessionRefreshMode = .lightweight) async {
        switch mode {
        case .full:
            await fullConversationSync(auth: auth)
        case .lightweight:
            await refreshLightweight(auth: auth)
        }
    }

    func fullConversationSync(auth: AuthStore) async {
        guard ConversationSyncCoordinator.shouldRunFullSync() else { return }
        guard auth.isLoggedIn, let api = auth.api else {
            if !teamSessions.isEmpty { teamSessions = [] }
            return
        }
        ConversationSyncCoordinator.beginFullSync()
        defer { ConversationSyncCoordinator.endFullSync() }
        let shouldShowLoading = teamSessions.isEmpty
        if shouldShowLoading { isLoading = true }
        defer { if shouldShowLoading { isLoading = false } }
        do {
            let response = try await api.fetchConversationSync()
            ConversationLocalSync.shared.apply(response)
            applyTeamSessions(response.teamSessions)
            SessionListCache.shared.save(
                sessions: response.sessions,
                teamSessions: response.teamSessions,
                liveCount: response.liveCount
            )
        } catch {
            if case LiveChatAPIError.unauthorized = error {
                auth.handleUnauthorized()
            } else if teamSessions.isEmpty {
                errorMessage = error.localizedDescription
            }
        }
    }

    private func refreshLightweight(auth: AuthStore) async {
        guard auth.isLoggedIn, let api = auth.api else {
            if !teamSessions.isEmpty { teamSessions = [] }
            return
        }
        guard teamRefreshGuard.tryEnter("team-sessions-lightweight") else { return }
        defer { teamRefreshGuard.leave("team-sessions-lightweight") }
        do {
            let response = try await api.fetchTeamSessions()
            applyTeamSessions(response.sessions)
            persistTeamListCache()
        } catch {
            if case LiveChatAPIError.unauthorized = error {
                auth.handleUnauthorized()
            } else if teamSessions.isEmpty {
                errorMessage = error.localizedDescription
            }
        }
    }

    func openConversation(with userId: Int, auth: AuthStore, requestNote: String = "") async -> String? {
        guard let api = auth.api, let myId = auth.profile?.userId else { return nil }

        let expectedSessionId = TeamVoiceRecorderService.teamConversationId(
            currentUserId: myId,
            otherUserId: userId
        )
        if let existing = teamSessions.first(where: {
            $0.sessionId == expectedSessionId || $0.otherUserId == userId
        }) {
            do {
                _ = try await api.openTeamConversation(userId: userId, requestNote: requestNote)
                await refresh(auth: auth, mode: .lightweight)
                await refreshPendingRequests(auth: auth)
                return existing.sessionId
            } catch {
                errorMessage = error.localizedDescription
                return existing.sessionId
            }
        }

        do {
            let response = try await api.openTeamConversation(userId: userId, requestNote: requestNote)
            await refresh(auth: auth)
            await refreshPendingRequests(auth: auth)
            return response.conversationId
        } catch {
            errorMessage = error.localizedDescription
            return nil
        }
    }

    func existingConversationId(for userId: Int, auth: AuthStore) -> String? {
        guard let myId = auth.profile?.userId else { return nil }
        let expectedSessionId = TeamVoiceRecorderService.teamConversationId(
            currentUserId: myId,
            otherUserId: userId
        )
        return teamSessions.first(where: {
            $0.sessionId == expectedSessionId || $0.otherUserId == userId
        })?.sessionId
    }
}

@MainActor
final class TeamChatThreadModel: ObservableObject {
    @Published var messages: [LiveMessage] = []
    @Published var messagesRevision = 0
    @Published var isLoadingMessages = false
    @Published var participantName = ""
    @Published var draft = ""
    @Published var isSending = false
    @Published var errorMessage: String?
    @Published var currentSeq = 0
    @Published var otherReadSeq = 0
    @Published var requestStatus = "accepted"
    @Published var requestStatusLabel = "Accepted"
    @Published var canSend = true
    @Published var canRespond = false
    @Published var remoteTyping = false
    @Published var otherPresence = "offline"
    @Published var otherLastSeen = 0
    @Published var failedClientMsgIds: Set<String> = []
    @Published var deletingMessageIds: Set<Int> = []

    let sessionId: String
    var onConversationRemoved: (() -> Void)?
    private var pollSeq = 0
    private var pollTask: Task<Void, Never>?
    private weak var auth: AuthStore?
    private var threadSubscriptionId: UUID?
    private var lastStreamEventAt = Date.distantPast
    private var streamSnapshotReady = false
    private var pendingStreamEvents: [ChatStreamEvent] = []
    private var pendingInlineMessages: [LiveMessage] = []
    private var historyBaselined = false
    private var serverMessageCount = 0
    private var lifecycleGeneration = 0
    private var typingTask: Task<Void, Never>?
    private var lastTypingSent = false
    private let streamStaleThreshold: TimeInterval = 12

    init(sessionId: String) {
        self.sessionId = sessionId
    }

    func start(auth: AuthStore) {
        self.auth = auth
        hydrateFromLocalStore()

        if let pollTask, !pollTask.isCancelled {
            if let auth = self.auth {
                Task { await self.poll(auth: auth) }
            }
            return
        }

        lifecycleGeneration += 1
        let generation = lifecycleGeneration
        stopBackgroundWork()
        streamSnapshotReady = true
        isLoadingMessages = false

        pollTask = Task { [weak self] in
            guard let self else { return }
            self.beginEventStream(auth: auth, generation: generation)
            async let history: Void = self.loadFullHistory(auth: auth, showLoading: false)
            async let pending: Void = self.retryPendingMessages(auth: auth)
            _ = await (history, pending)
            guard !Task.isCancelled, self.lifecycleGeneration == generation else { return }
            self.lastStreamEventAt = Date()

            while !Task.isCancelled, self.lifecycleGeneration == generation {
                if NetworkCircuitBreaker.shared.pollingSuspended {
                    try? await Task.sleep(nanoseconds: 5_000_000_000)
                    continue
                }
                let streamFresh = Date().timeIntervalSince(self.lastStreamEventAt) < self.streamStaleThreshold
                if !streamFresh, !AppRefreshPolicy.sseHealthy {
                    await self.poll(auth: auth)
                }
                if self.historyBaselined,
                   self.serverMessageCount > 0,
                   self.persistedMessageCount() < self.serverMessageCount {
                    await self.reloadFullHistory(auth: auth)
                }
                let interval = streamFresh
                    ? AppRefreshPolicy.teamThreadIntervalLive
                    : AppRefreshPolicy.teamThreadIntervalStale
                try? await Task.sleep(nanoseconds: interval)
            }
        }
    }

    func hydrateFromLocalStore(force: Bool = false) {
        if !force && !messages.isEmpty { return }
        guard let snapshot = ConversationHistoryStore.shared.snapshot(for: sessionId) else { return }
        applyBaselineSnapshot(snapshot.toPollResponse(), persistCache: false)
        isLoadingMessages = false
    }

    func applySilentSync(_ data: PollResponse) {
        applyBaselineSnapshot(data, persistCache: false)
        isLoadingMessages = false
    }

    func setRemoteTyping(_ typing: Bool) {
        remoteTyping = typing
        if typing {
            typingTask?.cancel()
            typingTask = Task { [weak self] in
                try? await Task.sleep(nanoseconds: 5_000_000_000)
                guard !Task.isCancelled else { return }
                await MainActor.run { self?.remoteTyping = false }
            }
        }
    }

    func handleDraftChange(auth: AuthStore) {
        let typing = !draft.trimmingCharacters(in: .whitespacesAndNewlines).isEmpty
        guard typing != lastTypingSent, let api = auth.api else { return }
        lastTypingSent = typing
        Task { try? await api.setTeamTyping(sessionId, typing: typing) }
    }

    func respondToRequest(accept: Bool, auth: AuthStore, teamCoordinator: TeamMessagingCoordinator) async {
        _ = await teamCoordinator.respondToRequest(sessionId: sessionId, accept: accept, auth: auth)
        await refreshNow(auth: auth)
    }

    func applyLiveMessage(_ message: LiveMessage, seq: Int) {
        insertIncomingMessages([message])
        lastStreamEventAt = Date()
        if seq > 0 {
            pollSeq = max(pollSeq, seq)
            currentSeq = max(currentSeq, seq)
        }
    }

    func refreshNow(auth: AuthStore, inlineMessage: Any? = nil) async {
        var appliedInline = false
        if let inline = LiveMessage.fromStreamPayload(inlineMessage) {
            insertIncomingMessages([inline])
            lastStreamEventAt = Date()
            appliedInline = true
            if historyBaselined {
                let targetSeq = max(currentSeq, inline.id)
                if needsHistoryRecovery(throughSeq: targetSeq) {
                    await recoverMissingHistory(auth: auth, throughSeq: targetSeq)
                }
            }
        }
        if !streamIsFresh() || !appliedInline {
            await poll(auth: auth)
        }
    }

    private func streamIsFresh() -> Bool {
        Date().timeIntervalSince(lastStreamEventAt) < streamStaleThreshold
    }

    func suspend() {
        lifecycleGeneration += 1
        stopBackgroundWork()
    }

    func stop() {
        suspend()
    }

    private func restoreFromCache() -> Bool {
        guard let snapshot = ConversationHistoryStore.shared.snapshot(for: sessionId) else {
            return false
        }
        applyBaselineSnapshot(snapshot.toPollResponse(), persistCache: false)
        isLoadingMessages = false
        return !messages.isEmpty || snapshot.messageCount > 0
    }

    private func stopBackgroundWork() {
        pollTask?.cancel()
        pollTask = nil
        if let threadSubscriptionId {
            ChatEventStream.shared.unsubscribeThread(id: threadSubscriptionId)
            self.threadSubscriptionId = nil
        }
    }

    private func resetThreadState() {
        messages = []
        pollSeq = 0
        currentSeq = 0
        historyBaselined = false
        serverMessageCount = 0
        streamSnapshotReady = false
        pendingStreamEvents = []
        pendingInlineMessages = []
        isLoadingMessages = true
    }

    func resetForLogout() {
        suspend()
        resetThreadState()
        draft = ""
        isSending = false
        errorMessage = nil
        remoteTyping = false
        participantName = ""
        currentSeq = 0
        otherReadSeq = 0
        requestStatus = "accepted"
        requestStatusLabel = "Accepted"
        canSend = true
        canRespond = false
        otherPresence = "offline"
        otherLastSeen = 0
        failedClientMsgIds = []
        deletingMessageIds = []
        messagesRevision = 0
        auth = nil
    }

    private func loadFullHistory(auth: AuthStore, showLoading: Bool = false) async {
        guard let api = auth.api else { return }
        defer { isLoadingMessages = false }
        do {
            let response = try await api.pollTeamSession(sessionId, since: 0, full: true)
            applyBaselineSnapshot(response)
            await verifyHistoryIntegrity(auth: auth)
            errorMessage = nil
        } catch {
            if case LiveChatAPIError.unauthorized = error {
                auth.handleUnauthorized()
            } else {
                errorMessage = error.localizedDescription
            }
        }
    }

    private func reloadFullHistory(auth: AuthStore) async {
        guard let api = auth.api else { return }
        do {
            let response = try await api.pollTeamSession(sessionId, since: 0, full: true)
            applyBaselineSnapshot(response)
            await verifyHistoryIntegrity(auth: auth)
            errorMessage = nil
        } catch {
            if case LiveChatAPIError.unauthorized = error {
                auth.handleUnauthorized()
            } else {
                errorMessage = error.localizedDescription
            }
        }
    }

    private func applyBaselineSnapshot(_ response: PollResponse, persistCache: Bool = true) {
        participantName = response.customerName
        applyTeamMeta(response)
        let optimistic = messages.filter { $0.id < 0 }
        let serverMax = response.messages.map(\.id).filter { $0 > 0 }.max() ?? 0
        let liveAhead = messages.filter { $0.id > serverMax }
        var merged = MessageMerge.baseline(server: response.messages, preservingOptimistic: optimistic)
        if !liveAhead.isEmpty {
            merged = MessageMerge.mergeSorted(existing: merged, incoming: liveAhead).messages
        }
        messages = normalizeTeamMessages(merged)
        pollSeq = response.seq
        currentSeq = response.seq
        serverMessageCount = max(response.messageCount, response.messages.count, response.seq)
        historyBaselined = true
        noteHistoryIntegrityIssue()
        if persistCache {
            ConversationHistoryStore.shared.save(response, sessionId: sessionId)
        }
    }

    private func noteHistoryIntegrityIssue() {
        guard serverMessageCount > 0, persistedMessageCount() < serverMessageCount else {
            return
        }
    }

    private func persistedMessageCount() -> Int {
        messages.filter { $0.id > 0 }.count
    }

    private func verifyHistoryIntegrity(auth: AuthStore) async {
        guard serverMessageCount > 0 else { return }
        guard persistedMessageCount() < serverMessageCount else { return }
        await reloadFullHistory(auth: auth)
    }

    private func recoverMissingHistory(auth: AuthStore, throughSeq: Int) async {
        if serverMessageCount > 0 && persistedMessageCount() < serverMessageCount {
            await reloadFullHistory(auth: auth)
            return
        }
        await fillMessageGap(auth: auth, throughSeq: throughSeq)
    }

    private func loadInitialSnapshot(auth: AuthStore) async {
        await loadFullHistory(auth: auth)
    }

    private func beginEventStream(auth: AuthStore, generation: Int) {
        guard let api = auth.api else { return }
        if let threadSubscriptionId {
            ChatEventStream.shared.unsubscribeThread(id: threadSubscriptionId)
        }
        threadSubscriptionId = ChatEventStream.shared.subscribeThread(api: api, sessionId: sessionId, isTeam: true) { [weak self] event in
            guard let self, self.lifecycleGeneration == generation else { return }
            await self.handleThreadStreamEvent(event, auth: auth)
        }
    }

    private func handleThreadStreamEvent(_ event: ChatStreamEvent, auth: AuthStore) async {
        lastStreamEventAt = Date()
        if event.type == "conversation_deleted" {
            onConversationRemoved?()
            return
        }
        if event.type == "read" {
            let seq = StreamPayload.int(event.payload["seq"])
            if seq > currentSeq {
                currentSeq = seq
            }
            if let readerId = event.payload["user_id"] as? Int, readerId != auth.profile?.userId {
                otherReadSeq = max(otherReadSeq, seq)
            }
            return
        }
        if event.type == "request_update" {
            let status = StreamPayload.string(event.payload["request_status"])
            if !status.isEmpty {
                requestStatus = status
            }
            return
        }
        if event.type == "typing" {
            setRemoteTyping(StreamPayload.bool(event.payload["typing"]))
            return
        }
        if event.type == "message" {
            let eventSeq = StreamPayload.int(event.payload["seq"])
            let incoming = StreamPayload.messages(from: event.payload)
            if !incoming.isEmpty {
                insertIncomingMessages(incoming)
                let maxId = incoming.map(\.id).filter { $0 > 0 }.max() ?? 0
                let targetSeq = max(eventSeq, maxId)
                if needsHistoryRecovery(throughSeq: targetSeq) {
                    await recoverMissingHistory(auth: auth, throughSeq: targetSeq)
                }
            } else if eventSeq > 0 {
                await recoverMissingHistory(auth: auth, throughSeq: eventSeq)
            } else {
                await poll(auth: auth)
            }
        }
        if event.type == "message_deleted" {
            let messageId = StreamPayload.int(event.payload["message_id"])
            if messageId > 0 {
                applyPermanentMessageDeletion(messageId: messageId)
            }
        }
    }

    private func needsHistoryRecovery(throughSeq: Int) -> Bool {
        if serverMessageCount > 0 && persistedMessageCount() < serverMessageCount {
            return true
        }
        guard throughSeq > 0 else { return false }
        let localMax = messages.map(\.id).filter { $0 > 0 }.max() ?? 0
        return throughSeq > localMax || pollSeq > localMax
    }

    private func hasMessageGap(throughSeq: Int) -> Bool {
        needsHistoryRecovery(throughSeq: throughSeq)
    }

    private func fillMessageGap(auth: AuthStore, throughSeq: Int) async {
        guard needsHistoryRecovery(throughSeq: throughSeq) else { return }
        guard let api = auth.api else { return }

        var attempts = 0
        while needsHistoryRecovery(throughSeq: throughSeq) && attempts < 6 {
            attempts += 1
            do {
                let response = try await api.pollTeamSession(sessionId, since: pollSeq, full: false)
                participantName = response.customerName
                if !response.messages.isEmpty {
                    insertIncomingMessages(response.messages)
                }
                pollSeq = max(pollSeq, response.seq)
                currentSeq = max(currentSeq, response.seq)
                if !needsHistoryRecovery(throughSeq: throughSeq) {
                    break
                }
                if response.messages.isEmpty {
                    await reloadFullHistory(auth: auth)
                    break
                }
            } catch {
                if case LiveChatAPIError.unauthorized = error {
                    auth.handleUnauthorized()
                } else {
                    errorMessage = error.localizedDescription
                }
                break
            }
        }
    }

    func poll(auth: AuthStore) async {
        guard let api = auth.api else {
            isLoadingMessages = false
            return
        }
        do {
            let response = try await api.pollTeamSession(sessionId, since: pollSeq, full: false)
            participantName = response.customerName
            if !response.messages.isEmpty {
                insertIncomingMessages(response.messages)
            }
            pollSeq = max(pollSeq, response.seq)
            currentSeq = max(currentSeq, response.seq)
            applyTeamMeta(response)
            if response.messageCount > serverMessageCount {
                serverMessageCount = response.messageCount
            }
            isLoadingMessages = false
            errorMessage = nil
            if serverMessageCount > 0 && persistedMessageCount() < serverMessageCount {
                await reloadFullHistory(auth: auth)
            }
            noteHistoryIntegrityIssue()
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

        let clientMsgId = UUID().uuidString.lowercased()
        let tempId = -(Int(Date().timeIntervalSince1970 * 1000) % 1_000_000_000)
        let optimistic = LiveMessage(
            id: tempId,
            clientMsgId: clientMsgId,
            role: "admin",
            content: text,
            ts: Int(Date().timeIntervalSince1970),
            senderId: auth.profile?.userId,
            senderName: auth.profile?.displayName
        )
        messages.append(optimistic)
        messagesRevision &+= 1
        let queued = PendingMessageStore.shared.enqueue(PendingOutboundMessage(
            id: clientMsgId,
            sessionId: sessionId,
            channel: .team,
            content: text,
            replyTo: nil,
            createdAt: Date().timeIntervalSince1970,
            attempts: 0
        ))
        guard queued else {
            messages.removeAll { $0.id == tempId }
            draft = text
            errorMessage = "Nachricht konnte nicht sicher lokal gespeichert werden."
            return
        }
        draft = ""
        isSending = true
        defer { isSending = false }

        do {
            let sent = try await api.sendTeamMessage(
                sessionId,
                content: text,
                clientMsgId: clientMsgId
            )
            messages.removeAll { $0.id == tempId }
            insertIncomingMessages([sent.message])
            pollSeq = max(pollSeq, sent.seq)
            currentSeq = max(currentSeq, sent.seq)
            PendingMessageStore.shared.acknowledge(clientMsgId: clientMsgId)
            MessageSendSound.shared.playIfEnabled()
            schedulePostSendTasks(auth: auth, teamCoordinator: teamCoordinator)
            await poll(auth: auth)
            PAXHaptics.light()
        } catch {
            switch error {
            case LiveChatAPIError.unauthorized, LiveChatAPIError.rejected(_):
                PendingMessageStore.shared.acknowledge(clientMsgId: clientMsgId)
                messages.removeAll { $0.id == tempId }
                draft = text
                errorMessage = error.localizedDescription
            default:
                errorMessage = "Nachricht wird automatisch erneut gesendet."
                if let clientMsgId = optimistic.clientMsgId {
                    failedClientMsgIds.insert(clientMsgId)
                }
            }
        }
    }

    func retryFailedMessage(_ clientMsgId: String, auth: AuthStore, teamCoordinator: TeamMessagingCoordinator) async {
        guard let item = PendingMessageStore.shared.pending(sessionId: sessionId, channel: .team)
            .first(where: { $0.id == clientMsgId }) else { return }
        failedClientMsgIds.remove(clientMsgId)
        guard let api = auth.api else { return }
        do {
            let sent = try await api.sendTeamMessage(sessionId, content: item.content, clientMsgId: clientMsgId)
            insertIncomingMessages([sent.message])
            pollSeq = max(pollSeq, sent.seq)
            currentSeq = max(currentSeq, sent.seq)
            PendingMessageStore.shared.acknowledge(clientMsgId: clientMsgId)
            await teamCoordinator.refresh(auth: auth)
        } catch {
            failedClientMsgIds.insert(clientMsgId)
            errorMessage = error.localizedDescription
        }
    }

    func sendImage(
        auth: AuthStore,
        teamCoordinator: TeamMessagingCoordinator,
        imageData: Data,
        filename: String,
        caption: String = ""
    ) async {
        guard let api = auth.api else { return }
        let clientMsgId = UUID().uuidString.lowercased()
        let tempId = -(Int(Date().timeIntervalSince1970 * 1000) % 1_000_000_000)
        let optimistic = LiveMessage(
            id: tempId,
            clientMsgId: clientMsgId,
            role: "admin",
            content: caption,
            ts: Int(Date().timeIntervalSince1970),
            imageUrl: "pending://\(clientMsgId)",
            senderId: auth.profile?.userId,
            senderName: auth.profile?.displayName,
            attachmentType: "image"
        )
        messages.append(optimistic)
        messagesRevision &+= 1

        Task {
            do {
                let sent = try await api.sendTeamImage(
                    sessionId,
                    imageData: imageData,
                    filename: filename,
                    caption: caption,
                    clientMsgId: clientMsgId
                )
                insertIncomingMessages([sent.message])
                messages.removeAll { $0.id < 0 && $0.clientMsgId == clientMsgId }
                pollSeq = max(pollSeq, sent.seq)
                currentSeq = max(currentSeq, sent.seq)
                MessageSendSound.shared.playIfEnabled()
                schedulePostSendTasks(auth: auth, teamCoordinator: teamCoordinator)
                PAXHaptics.light()
            } catch {
                messages.removeAll { $0.id < 0 && $0.clientMsgId == clientMsgId }
                errorMessage = error.localizedDescription
            }
        }
    }

    func sendAudio(
        auth: AuthStore,
        teamCoordinator: TeamMessagingCoordinator,
        audioData: Data,
        filename: String,
        duration: TimeInterval
    ) async {
        guard let api = auth.api else { return }
        guard audioData.count >= 256 else {
            errorMessage = L10n.TeamVoiceEmpty
            return
        }
        let clientMsgId = UUID().uuidString.lowercased()
        let tempId = -(Int(Date().timeIntervalSince1970 * 1000) % 1_000_000_000)
        let optimistic = LiveMessage(
            id: tempId,
            clientMsgId: clientMsgId,
            role: "admin",
            content: "",
            ts: Int(Date().timeIntervalSince1970),
            senderId: auth.profile?.userId,
            senderName: auth.profile?.displayName,
            attachmentType: "voice",
            audioUrl: "pending://\(clientMsgId)",
            audioDuration: duration
        )
        messages.append(optimistic)
        messagesRevision &+= 1

        Task {
            do {
                let sent = try await api.sendTeamAudio(
                    sessionId,
                    audioData: audioData,
                    filename: filename,
                    duration: duration,
                    clientMsgId: clientMsgId
                )
                insertIncomingMessages([sent.message])
                messages.removeAll { $0.id < 0 && $0.clientMsgId == clientMsgId }
                pollSeq = max(pollSeq, sent.seq)
                currentSeq = max(currentSeq, sent.seq)
                failedClientMsgIds.remove(clientMsgId)
                MessageSendSound.shared.playIfEnabled()
                schedulePostSendTasks(auth: auth, teamCoordinator: teamCoordinator)
                PAXHaptics.light()
            } catch {
                failedClientMsgIds.insert(clientMsgId)
                errorMessage = error.localizedDescription
            }
        }
    }

    func sendLocation(
        auth: AuthStore,
        teamCoordinator: TeamMessagingCoordinator,
        latitude: Double,
        longitude: Double,
        label: String = ""
    ) async {
        guard let api = auth.api else { return }
        let clientMsgId = UUID().uuidString.lowercased()
        let tempId = -(Int(Date().timeIntervalSince1970 * 1000) % 1_000_000_000)
        let optimistic = LiveMessage(
            id: tempId,
            clientMsgId: clientMsgId,
            role: "admin",
            content: label,
            ts: Int(Date().timeIntervalSince1970),
            senderId: auth.profile?.userId,
            senderName: auth.profile?.displayName,
            attachmentType: "location",
            locationLat: latitude,
            locationLng: longitude,
            locationLabel: label
        )
        messages.append(optimistic)
        messagesRevision &+= 1

        Task {
            do {
                let sent = try await api.sendTeamLocation(
                    sessionId,
                    latitude: latitude,
                    longitude: longitude,
                    label: label,
                    clientMsgId: clientMsgId
                )
                insertIncomingMessages([sent.message])
                messages.removeAll { $0.id < 0 && $0.clientMsgId == clientMsgId }
                pollSeq = max(pollSeq, sent.seq)
                currentSeq = max(currentSeq, sent.seq)
                MessageSendSound.shared.playIfEnabled()
                schedulePostSendTasks(auth: auth, teamCoordinator: teamCoordinator)
                PAXHaptics.light()
            } catch {
                messages.removeAll { $0.id < 0 && $0.clientMsgId == clientMsgId }
                errorMessage = error.localizedDescription
            }
        }
    }

    func sendFile(
        auth: AuthStore,
        teamCoordinator: TeamMessagingCoordinator,
        fileData: Data,
        filename: String,
        caption: String = ""
    ) async {
        guard let api = auth.api else { return }
        let clientMsgId = UUID().uuidString.lowercased()
        let tempId = -(Int(Date().timeIntervalSince1970 * 1000) % 1_000_000_000)
        let optimistic = LiveMessage(
            id: tempId,
            clientMsgId: clientMsgId,
            role: "admin",
            content: caption,
            ts: Int(Date().timeIntervalSince1970),
            senderId: auth.profile?.userId,
            senderName: auth.profile?.displayName,
            attachmentType: "file",
            fileUrl: "pending://\(clientMsgId)",
            fileName: filename
        )
        messages.append(optimistic)
        messagesRevision &+= 1

        Task {
            do {
                let sent = try await api.sendTeamFile(
                    sessionId,
                    fileData: fileData,
                    filename: filename,
                    caption: caption,
                    clientMsgId: clientMsgId
                )
                insertIncomingMessages([sent.message])
                messages.removeAll { $0.id < 0 && $0.clientMsgId == clientMsgId }
                pollSeq = max(pollSeq, sent.seq)
                currentSeq = max(currentSeq, sent.seq)
                MessageSendSound.shared.playIfEnabled()
                schedulePostSendTasks(auth: auth, teamCoordinator: teamCoordinator)
                PAXHaptics.light()
            } catch {
                messages.removeAll { $0.id < 0 && $0.clientMsgId == clientMsgId }
                errorMessage = error.localizedDescription
            }
        }
    }

    func deleteMessage(_ messageId: Int, auth: AuthStore) async {
        guard messageId > 0, let api = auth.api else { return }
        applyPermanentMessageDeletion(messageId: messageId)
        do {
            try await api.deleteTeamMessage(sessionId, messageId: messageId)
        } catch {
            errorMessage = error.localizedDescription
            await poll(auth: auth)
        }
    }

    private func applyPermanentMessageDeletion(messageId: Int) {
        guard messageId > 0 else { return }
        deletingMessageIds.insert(messageId)
        messagesRevision &+= 1
        messages.removeAll { $0.id == messageId }
        deletingMessageIds.remove(messageId)
        messagesRevision &+= 1
        let payload = CachedPollPayload(
            handler: "team_dm",
            handlerLabel: "Team",
            adminName: auth?.profile?.displayName ?? "",
            customerName: participantName,
            assignedAgent: nil,
            sessionRating: 0,
            detectedService: "Team-Nachricht",
            updatedAt: "",
            seq: pollSeq,
            messageCount: max(serverMessageCount, messages.count, pollSeq),
            messages: messages
        )
        ConversationHistoryStore.shared.save(payload.asPollResponse(), sessionId: sessionId)
    }

    private func schedulePostSendTasks(auth: AuthStore, teamCoordinator: TeamMessagingCoordinator) {
        Task { await teamCoordinator.refresh(auth: auth, mode: .lightweight) }
        Task { await markRead(auth: auth) }
    }

    private func applyTeamMeta(_ response: PollResponse) {
        otherReadSeq = max(otherReadSeq, response.otherReadSeq)
        requestStatus = response.requestStatus.isEmpty ? "accepted" : response.requestStatus
        requestStatusLabel = response.requestStatusLabel.isEmpty ? "Accepted" : response.requestStatusLabel
        canSend = response.canSend
        canRespond = response.canRespond
        otherPresence = response.otherPresence.isEmpty ? "offline" : response.otherPresence
        otherLastSeen = response.otherLastSeen
        if response.userTyping {
            setRemoteTyping(true)
        }
    }

    private func retryPendingMessages(auth: AuthStore) async {
        guard let api = auth.api else { return }
        let pending = PendingMessageStore.shared.pending(sessionId: sessionId, channel: .team)
        for item in pending {
            guard !Task.isCancelled else { return }
            PendingMessageStore.shared.noteAttempt(clientMsgId: item.id)
            do {
                let sent = try await api.sendTeamMessage(
                    sessionId,
                    content: item.content,
                    clientMsgId: item.id
                )
                insertIncomingMessages([sent.message])
                pollSeq = max(pollSeq, sent.seq)
                currentSeq = max(currentSeq, sent.seq)
                PendingMessageStore.shared.acknowledge(clientMsgId: item.id)
            } catch {
                if case LiveChatAPIError.unauthorized = error {
                    auth.handleUnauthorized()
                }
                return
            }
        }
    }

    private func insertIncomingMessages(_ incoming: [LiveMessage], source: String = "merge") {
        let normalized = normalizeTeamMessages(incoming)
        let beforeCount = messages.count
        let existingIds = Set(messages.map(\.id))
        let result = MessageMerge.mergeSorted(existing: messages, incoming: normalized)
        var published = false
        if result.changed {
            messages = result.messages
            messagesRevision &+= 1
            published = true
        } else if normalized.contains(where: { $0.id > 0 && !existingIds.contains($0.id) }) {
            messagesRevision &+= 1
            published = true
        }

        ChatLiveDiagnostics.mergeResult(
            sessionId: sessionId,
            incomingIds: normalized.map(\.id),
            changed: result.changed,
            before: beforeCount,
            after: messages.count,
            published: published
        )

        if result.changed {
            let payload = CachedPollPayload(
                handler: "team_dm",
                handlerLabel: "Team",
                adminName: auth?.profile?.displayName ?? "",
                customerName: participantName,
                assignedAgent: nil,
                sessionRating: 0,
                detectedService: "Team-Nachricht",
                updatedAt: "",
                seq: pollSeq,
                messageCount: max(serverMessageCount, messages.count, pollSeq),
                messages: messages
            )
            ConversationHistoryStore.shared.save(payload.asPollResponse(), sessionId: sessionId)
        }
        let maxId = messages.map(\.id).filter { $0 > 0 }.max() ?? 0
        if maxId > 0 {
            pollSeq = max(pollSeq, maxId)
            currentSeq = max(currentSeq, maxId)
        }

        let presentIds = Set(messages.map(\.id))
        if !published, normalized.contains(where: { $0.id > 0 && !presentIds.contains($0.id) }) {
            ChatLiveDiagnostics.cursorAdjusted(sessionId: sessionId, reason: "\(source)-unpublished", pollSeq: pollSeq)
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
            clientMsgId: message.clientMsgId,
            role: expectedRole,
            content: message.content,
            ts: message.ts,
            imageUrl: message.imageUrl,
            replyTo: message.replyTo,
            reaction: message.reaction,
            senderId: message.senderId,
            senderName: message.senderName,
            senderAvatar: message.senderAvatar,
            senderRole: message.senderRole,
            attachmentType: message.attachmentType,
            audioUrl: message.audioUrl,
            audioDuration: message.audioDuration,
            locationLat: message.locationLat,
            locationLng: message.locationLng,
            locationLabel: message.locationLabel
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

struct TeamBroadcastResponse: Codable {
    let ok: Bool
    let sent: Int
    let skipped: [Int]?
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

struct TeamRespondResponse: Codable {
    let ok: Bool
    let requestStatus: String
    let session: LiveSession?

    enum CodingKeys: String, CodingKey {
        case ok
        case requestStatus = "request_status"
        case session
    }
}

struct TeamSessionActionResponse: Codable {
    let ok: Bool
    let session: LiveSession?
}
