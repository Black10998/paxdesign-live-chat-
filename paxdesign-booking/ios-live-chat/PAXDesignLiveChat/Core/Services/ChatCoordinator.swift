import Foundation
import UserNotifications

@MainActor
final class ChatCoordinator: ObservableObject {
    @Published var sessions: [LiveSession] = []
    @Published var liveCount = 0
    @Published var unreadChatCount = 0
    @Published var unreadTeamCount = 0
    @Published var isLoading = false
    @Published var isSyncing = false
    @Published var lastSyncAt: Date?
    @Published var errorMessage: String?
    @Published var incomingRequest: IncomingLiveRequest? {
        didSet {
            if incomingRequest != nil {
                incomingBannerDismissed = false
                showIncomingFullscreen = false
            } else {
                showIncomingFullscreen = false
            }
        }
    }
    @Published var pendingCustomerMessage: PendingCustomerMessageBanner?
    @Published var activeSessionId: String?
    @Published var showIncomingFullscreen = false
    @Published var incomingBannerDismissed = false

    private var knownSessionIds = Set<String>()
    private var listTask: Task<Void, Never>?
    private var knownLiveRequests = Set<String>()
    private var lastKnownPreviews: [String: String] = [:]
    private var expiryTasks: [String: Task<Void, Never>] = [:]
    private var fullscreenTask: Task<Void, Never>?
    private(set) var lastSessionRefreshAt: Date?
    private var sessionServerSeq: [String: Int] = [:]
    private let inboxSubscriptionId = UUID()
    private var inboxRefreshTask: Task<Void, Never>?
    private var fullSyncTask: Task<Void, Never>?
    private var listLoopCounter = 0
    private let sessionRefreshGuard = RequestInFlightGuard()
    private static let inboxDebounceNs: UInt64 = 1_500_000_000
    private static let fullSyncEveryListCycles = 24

    func serverSeq(for sessionId: String) -> Int {
        if let hinted = sessionServerSeq[sessionId], hinted > 0 {
            return hinted
        }
        return sessions.first(where: { $0.sessionId == sessionId })?.seq ?? 0
    }

    func noteSessionSeq(_ sessionId: String, seq: Int) {
        guard !sessionId.isEmpty, seq > 0 else { return }
        sessionServerSeq[sessionId] = max(sessionServerSeq[sessionId] ?? 0, seq)
    }

    private func noteSessionSeqs(from sessions: [LiveSession]) {
        for session in sessions where session.seq > 0 {
            noteSessionSeq(session.sessionId, seq: session.seq)
        }
    }

    private func scheduleFullscreenPresentation() {
        fullscreenTask?.cancel()
        fullscreenTask = Task { [weak self] in
            try? await Task.sleep(nanoseconds: 2_500_000_000)
            guard !Task.isCancelled, self?.incomingRequest != nil else { return }
            await MainActor.run { self?.showIncomingFullscreen = true }
        }
    }

    func presentIncomingFullscreen() {
        fullscreenTask?.cancel()
        showIncomingFullscreen = true
    }

    func dismissIncomingBanner() {
        incomingBannerDismissed = true
    }

    func dismissCustomerMessageBanner() {
        pendingCustomerMessage = nil
    }

    func openSessionFromBanner(_ sessionId: String, auth: AuthStore) async {
        pendingCustomerMessage = nil
        incomingBannerDismissed = true
        acknowledgeIncomingRequest(sessionId)
        if let session = sessions.first(where: { $0.sessionId == sessionId }) {
            if session.isLiveRequest {
                await acceptLiveRequest(auth: auth, session: session)
            }
        }
        activeSessionId = sessionId
        AppRefreshPolicy.setActiveSession(sessionId)
        NotificationCenter.default.post(name: .paxPushOpened, object: nil, userInfo: ["session_id": sessionId])
    }

    func hydrateFromCache() {
        guard sessions.isEmpty, let cached = SessionListCache.shared.load() else { return }
        sessions = cached.sessions
        liveCount = cached.liveCount
        noteSessionSeqs(from: cached.sessions)
        lastSyncAt = cached.cachedDate
        lastSessionRefreshAt = cached.cachedDate
        updateUnreadCounts()
        TeamMessagingCoordinator.shared.applyTeamSessions(cached.teamSessions)
    }

    func start(auth: AuthStore) {
        stop()
        if let api = auth.api {
            ConversationHistoryStore.shared.setSiteScope(api.publicApiBaseURL)
            SessionListCache.shared.setSiteScope(api.publicApiBaseURL)
            hydrateFromCache()
            knownSessionIds = Set(sessions.map(\.sessionId))
        }
        listTask = Task { [weak self] in
            while !Task.isCancelled {
                guard let self else { return }
                if NetworkCircuitBreaker.shared.isOpen {
                    try? await Task.sleep(nanoseconds: 60_000_000_000)
                    continue
                }
                self.listLoopCounter += 1
                if self.listLoopCounter.isMultiple(of: Self.fullSyncEveryListCycles) {
                    await self.refreshSessions(auth: auth, mode: .full)
                } else {
                    await self.refreshSessions(auth: auth, mode: .lightweight)
                }
                let interval = AppRefreshPolicy.sessionListInterval
                try? await Task.sleep(nanoseconds: interval)
            }
        }
        if let api = auth.api {
            ChatEventStream.shared.subscribeInbox(id: inboxSubscriptionId, api: api) { [weak self] event in
                guard let self else { return }
                if let sid = event.payload["session_id"] as? String, !sid.isEmpty {
                    let seq = StreamPayload.int(event.payload["seq"])
                    if seq > 0 {
                        self.noteSessionSeq(sid, seq: seq)
                    }
                    if event.type == "message" {
                        let incoming = StreamPayload.messages(from: event.payload)
                        ChatLiveDiagnostics.sseReceived(
                            channel: event.channel.isEmpty ? "inbox:admins" : event.channel,
                            type: event.type,
                            sessionId: sid,
                            eventId: event.id,
                            seq: seq,
                            inlineCount: incoming.count
                        )
                        for inline in incoming {
                            let messageSeq = max(seq, inline.id)
                            if sid.hasPrefix("team_") {
                                // Team data merge is owned by TeamMessagingCoordinator.
                                if self.activeSessionId != sid, inline.role != "admin" {
                                    InAppNotificationCoordinator.shared.handleTeamMessage(
                                        sessionId: sid,
                                        preview: inline.content,
                                        senderName: inline.senderName ?? "",
                                        isActiveSession: false
                                    )
                                }
                            } else {
                                ConversationHistoryStore.shared.mergeMessage(sessionId: sid, message: inline, seq: messageSeq)
                                ChatThreadRegistry.shared.bookingThread(sessionId: sid).applyLiveMessage(inline, seq: messageSeq)
                                self.bumpBookingSession(sessionId: sid, message: inline, seq: messageSeq)
                                if self.activeSessionId != sid, inline.role == "user" {
                                    let sessionName = self.sessions.first(where: { $0.sessionId == sid })?.displayName ?? ""
                                    InAppNotificationCoordinator.shared.handleNewCustomerMessage(
                                        sessionId: sid,
                                        preview: inline.content,
                                        customerName: sessionName,
                                        isActiveSession: false
                                    )
                                }
                            }
                        }
                    }
                    if event.type == "message" || event.type == "handler" {
                        if self.activeSessionId != sid || event.type == "handler" {
                            self.postSessionSync(sessionId: sid, inlineMessage: event.payload["message"])
                        }
                    }
                }
                if self.inboxEventNeedsListRefresh(event) {
                    self.scheduleInboxListRefresh(auth: auth)
                }
            }
        }
    }

    private func inboxEventNeedsListRefresh(_ event: ChatStreamEvent) -> Bool {
        switch event.type {
        case "handler", "session_update", "conversation_deleted", "conversation_request", "request_update":
            return true
        case "message":
            guard let sessionId = event.payload["session_id"] as? String else { return true }
            return sessionId.hasPrefix("team_")
        default:
            return false
        }
    }

    private func scheduleInboxListRefresh(auth: AuthStore) {
        inboxRefreshTask?.cancel()
        inboxRefreshTask = Task { [weak self] in
            try? await Task.sleep(nanoseconds: Self.inboxDebounceNs)
            guard !Task.isCancelled, let self else { return }
            await self.refreshSessions(auth: auth, mode: .lightweight)
        }
    }

    private func bumpBookingSession(sessionId: String, message: LiveMessage, seq: Int) {
        guard let index = sessions.firstIndex(where: { $0.sessionId == sessionId }) else { return }
        let updated = LiveSessionPatch.bumped(sessions[index], message: message, seq: seq)
        sessions.remove(at: index)
        sessions.insert(updated, at: 0)
        updateUnreadCounts()
        persistSessionListCache()
        detectIncomingLiveRequests([updated])
    }

    private func persistSessionListCache() {
        let existing = SessionListCache.shared.load()
        SessionListCache.shared.save(
            sessions: sessions,
            teamSessions: existing?.teamSessions ?? TeamMessagingCoordinator.shared.teamSessions,
            liveCount: liveCount
        )
    }

    func stop() {
        listTask?.cancel()
        listTask = nil
        inboxRefreshTask?.cancel()
        inboxRefreshTask = nil
        fullSyncTask?.cancel()
        fullSyncTask = nil
        listLoopCounter = 0
        ChatEventStream.shared.unsubscribeInbox(id: inboxSubscriptionId)
        expiryTasks.values.forEach { $0.cancel() }
        expiryTasks.removeAll()
        IncomingCallRingtone.shared.stopRinging()
    }

    func resetLoggedOutState() {
        stop()
        sessions = []
        liveCount = 0
        unreadChatCount = 0
        unreadTeamCount = 0
        isLoading = false
        isSyncing = false
        lastSyncAt = nil
        errorMessage = nil
        incomingRequest = nil
        pendingCustomerMessage = nil
        activeSessionId = nil
        showIncomingFullscreen = false
        incomingBannerDismissed = false
        knownSessionIds.removeAll()
        knownLiveRequests.removeAll()
        lastKnownPreviews.removeAll()
        sessionServerSeq.removeAll()
        fullscreenTask?.cancel()
        fullscreenTask = nil
        lastSessionRefreshAt = nil
    }

    func refreshSessions(auth: AuthStore, mode: SessionRefreshMode = .lightweight) async {
        switch mode {
        case .full:
            await fullConversationSync(auth: auth)
        case .lightweight:
            await refreshSessionsLightweight(auth: auth)
        }
    }

    func fullConversationSync(auth: AuthStore) async {
        await ConversationSyncCoordinator.performUnifiedFullSync(
            auth: auth,
            chatCoordinator: self,
            teamCoordinator: TeamMessagingCoordinator.shared
        )
    }

    func performUnifiedConversationSync(
        auth: AuthStore,
        teamCoordinator: TeamMessagingCoordinator
    ) async {
        guard let api = auth.api else { return }
        let shouldShowSync = sessions.isEmpty
        if shouldShowSync { isSyncing = true }
        defer { if shouldShowSync { isSyncing = false } }
        do {
            let response = try await api.fetchConversationSync()
            ConversationLocalSync.shared.apply(response)
            teamCoordinator.applyTeamSessions(response.teamSessions)

            sessions = response.sessions
            liveCount = response.liveCount
            noteSessionSeqs(from: response.sessions)
            detectNewSessions(response.sessions)
            lastSyncAt = Date()
            lastSessionRefreshAt = lastSyncAt
            updateUnreadCounts()
            AppRefreshPolicy.update(liveCount: response.liveCount, openChat: activeSessionId != nil)
            errorMessage = nil
            detectIncomingLiveRequests(response.sessions)
            persistSessionListCache()
        } catch {
            if case LiveChatAPIError.unauthorized = error {
                auth.handleUnauthorized()
            }
            if sessions.isEmpty {
                errorMessage = error.localizedDescription
            }
        }
    }

    private func refreshSessionsLightweight(auth: AuthStore) async {
        guard let api = auth.api else { return }
        guard sessionRefreshGuard.tryEnter("sessions-lightweight") else { return }
        defer { sessionRefreshGuard.leave("sessions-lightweight") }
        do {
            let response = try await api.fetchSessions()
            sessions = response.sessions
            liveCount = response.liveCount
            noteSessionSeqs(from: response.sessions)
            detectNewSessions(response.sessions)
            lastSessionRefreshAt = Date()
            updateUnreadCounts()
            AppRefreshPolicy.update(liveCount: response.liveCount, openChat: activeSessionId != nil)
            errorMessage = nil
            detectIncomingLiveRequests(response.sessions)
            persistSessionListCache()
        } catch {
            if case LiveChatAPIError.unauthorized = error {
                auth.handleUnauthorized()
            } else if sessions.isEmpty {
                errorMessage = error.localizedDescription
            }
        }
    }

    func updateUnreadCounts() {
        let settings = AppSettingsStore.shared
        unreadChatCount = sessions
            .filter { !$0.isTeamDM }
            .reduce(0) { $0 + settings.unreadMessageCount(for: $1) }
        unreadTeamCount = TeamMessagingCoordinator.shared.unreadCount(
            settings: settings,
            coordinatorSessions: sessions
        )
        PAXApplicationBadge.sync(
            total: unreadChatCount + unreadTeamCount + liveCount + StaffOrdersCoordinator.shared.unreadCount
        )
    }

    private func detectNewSessions(_ items: [LiveSession]) {
        for session in items where !session.isTeamDM {
            guard !knownSessionIds.contains(session.sessionId) else { continue }
            knownSessionIds.insert(session.sessionId)
            guard activeSessionId != session.sessionId else { continue }
            InAppNotificationCoordinator.shared.handleNewChatStarted(
                sessionId: session.sessionId,
                customerName: session.displayName,
                preview: session.lastPreview
            )
        }
        let currentIds = Set(items.map(\.sessionId))
        knownSessionIds = knownSessionIds.intersection(currentIds)
    }

    private func detectIncomingLiveRequests(_ items: [LiveSession]) {
        let liveOnes = items.filter { $0.isLiveRequest }
        for session in liveOnes {
            guard !knownLiveRequests.contains(session.sessionId) else { continue }
            presentIncoming(session: session)
        }

        for session in items where !session.isLiveRequest && session.needsReply {
            let preview = session.lastPreview
            let previous = lastKnownPreviews[session.sessionId]
            lastKnownPreviews[session.sessionId] = preview

            guard previous != preview, !preview.isEmpty else { continue }
            guard activeSessionId != session.sessionId else { continue }

            pendingCustomerMessage = PendingCustomerMessageBanner(
                id: session.sessionId,
                sessionId: session.sessionId,
                customerName: session.displayName,
                preview: preview
            )
            InAppNotificationCoordinator.shared.handleNewCustomerMessage(
                sessionId: session.sessionId,
                preview: preview,
                customerName: session.displayName,
                isActiveSession: false
            )
        }

        let currentIds = Set(items.map(\.sessionId))
        lastKnownPreviews = lastKnownPreviews.filter { currentIds.contains($0.key) }
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
        let sessionId = session.sessionId
        AppSettingsStore.shared.archiveSession(sessionId)
        if activeSessionId == sessionId { activeSessionId = nil }
        PAXHaptics.light()
    }

    func deleteSession(auth: AuthStore, session: LiveSession) async {
        guard let api = auth.api else { return }
        let sessionId = session.sessionId
        do {
            try await api.deleteSession(sessionId)
            if activeSessionId == sessionId { activeSessionId = nil }
            await refreshSessions(auth: auth)
            PAXHaptics.warning()
        } catch {
            errorMessage = error.localizedDescription
            await refreshSessions(auth: auth)
        }
    }

    func handlePush(sessionId: String, type: String, auth: AuthStore, payload: PushService.PushPayload? = nil, shouldNavigate: Bool = false) async {
        let event = payload?.event ?? type
        switch event {
        case "customer_waiting", "live_request":
            await presentLiveRequest(sessionId: sessionId, auth: auth, payload: payload)
        case "new_chat_started", "new_chat", "new_customer_message", "message":
            await refreshSessions(auth: auth)
            if shouldNavigate, event == "new_customer_message" || type == "message" {
                activeSessionId = sessionId
            }
            postSessionSync(sessionId: sessionId)
        case "assigned_chat_updated", "new_lead_contact", "missed_chat", "link_scan_attention":
            await refreshSessions(auth: auth)
            postSessionSync(sessionId: sessionId)
        case "session_sync":
            await refreshSessions(auth: auth)
            postSessionSync(sessionId: sessionId)
        default:
            switch type {
            case "live_request":
                await presentLiveRequest(sessionId: sessionId, auth: auth, payload: payload)
            case "new_chat", "message":
                await refreshSessions(auth: auth)
                if shouldNavigate, type == "message" {
                    activeSessionId = sessionId
                }
                postSessionSync(sessionId: sessionId)
            default:
                await refreshSessions(auth: auth)
                if shouldNavigate {
                    activeSessionId = sessionId
                }
                postSessionSync(sessionId: sessionId)
            }
        }
    }

    private func postSessionSync(sessionId: String, inlineMessage: Any? = nil) {
        var userInfo: [String: Any] = ["session_id": sessionId]
        if let inlineMessage {
            userInfo["inline_message"] = inlineMessage
        }
        NotificationCenter.default.post(
            name: .paxSessionSync,
            object: nil,
            userInfo: userInfo
        )
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

        // Avoid synthetic placeholder sessions — only present verified server rows.
        incomingRequest = nil
    }
}

@MainActor
final class ChatThreadModel: ObservableObject {
    @Published var messages: [LiveMessage] = []
    @Published var messagesRevision = 0
    @Published var isLoadingMessages = false
    @Published var handler = "ai"
    @Published var customerName = "Kunde"
    @Published var customerLanguage = ""
    @Published var adminName = ""
    @Published var assignedAgent: EmployeeIdentity?
    @Published var detectedService = ""
    @Published var updatedAt = ""
    @Published var sessionRating = 0
    @Published var userTyping = false
    @Published var draft = ""
    @Published var isSending = false
    @Published var errorMessage: String?
    @Published var replyToMessage: LiveMessage?
    @Published var quickReplies: [QuickReply] = []
    @Published var quickLinks: [QuickLink] = []
    @Published var aiSuggestions: [String] = []
    @Published var suggestionsLoading = false
    @Published var suggestionsError: String?
    @Published var deletingMessageIds: Set<Int> = []
    @Published var linkReviewSubmittingIds: Set<Int> = []

    let sessionId: String
    private weak var auth: AuthStore?
    private var permanentlyDeletedMessageIds = Set<Int>()
    private var pollSeq = 0
    private var pollTask: Task<Void, Never>?
    private var typingStopTask: Task<Void, Never>?
    private var typingNotifyTask: Task<Void, Never>?
    private var suggestionsTask: Task<Void, Never>?
    private var readAckTask: Task<Void, Never>?
    private var deletionTasks: [Int: Task<Void, Never>] = [:]
    private var suggestionsForMessageId = 0
    private var knownMessageIds = Set<Int>()
    private var lastTypingNotifyAt = Date.distantPast
    private var lifecycleGeneration = 0
    private var threadSubscriptionId: UUID?
    private var lastStreamEventAt = Date.distantPast
    private var streamSnapshotReady = false
    private var pendingStreamEvents: [ChatStreamEvent] = []
    private var pendingInlineMessages: [LiveMessage] = []
    private var historyBaselined = false
    private var serverMessageCount = 0
    private let streamStaleThreshold: TimeInterval = 12

    init(sessionId: String) {
        self.sessionId = sessionId
    }

    func start(auth: AuthStore, expectedServerSeq: Int = 0) {
        self.auth = auth
        hydrateFromLocalStore()

        if let pollTask, !pollTask.isCancelled {
            if expectedServerSeq > pollSeq {
                pollSeq = max(pollSeq, expectedServerSeq)
            }
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

        pollTask = Task { @MainActor [weak self] in
            guard let self else { return }
            self.beginEventStream(auth: auth, generation: generation)
            async let quickReplies: Void = self.loadQuickReplies(auth: auth)
            async let quickLinks: Void = self.loadQuickLinks(auth: auth)
            async let history: Void = self.loadFullHistory(
                auth: auth,
                expectedServerSeq: expectedServerSeq,
                showLoading: false
            )
            async let pending: Void = self.retryPendingMessages(auth: auth)
            _ = await (quickReplies, quickLinks, history, pending)
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
                    ? AppRefreshPolicy.chatThreadIntervalLive
                    : AppRefreshPolicy.chatThreadIntervalStale
                try? await Task.sleep(nanoseconds: interval)
            }
        }
    }

    func applySilentSync(_ data: PollResponse) {
        applyBaselineSnapshot(data, persistCache: false)
        isLoadingMessages = false
    }

    func hydrateFromLocalStore(force: Bool = false) {
        if !force && !messages.isEmpty { return }
        guard let snapshot = ConversationHistoryStore.shared.snapshot(for: sessionId) else { return }
        applyBaselineSnapshot(snapshot.toPollResponse(), persistCache: false)
        isLoadingMessages = false
    }

    func applyLiveMessage(_ message: LiveMessage, seq: Int) {
        insertIncomingMessages([message], source: "inbox-live")
        lastStreamEventAt = Date()
    }

    func refreshNow(auth: AuthStore, expectedServerSeq: Int = 0, inlineMessage: Any? = nil) async {
        var appliedInline = false
        if let inline = LiveMessage.fromStreamPayload(inlineMessage) {
            insertIncomingMessages([inline], source: "refresh-inline")
            lastStreamEventAt = Date()
            appliedInline = true
            if historyBaselined {
                let targetSeq = max(expectedServerSeq, inline.id)
                if needsHistoryRecovery(expectedServerSeq: targetSeq) {
                    await recoverMissingHistory(auth: auth, throughSeq: targetSeq)
                }
            }
        } else if expectedServerSeq > localMessageMaxId() {
            await recoverMissingHistory(auth: auth, throughSeq: expectedServerSeq)
        }

        let shouldPoll = !streamIsFresh() || !appliedInline || needsHistoryRecovery(expectedServerSeq: max(expectedServerSeq, pollSeq))
        if shouldPoll {
            await poll(auth: auth)
        }

        if historyBaselined,
           needsHistoryRecovery(expectedServerSeq: max(expectedServerSeq, pollSeq)) {
            await recoverMissingHistory(auth: auth, throughSeq: max(expectedServerSeq, pollSeq))
        }
    }

    private func streamIsFresh() -> Bool {
        Date().timeIntervalSince(lastStreamEventAt) < streamStaleThreshold
    }

    func suspend() {
        lifecycleGeneration += 1
        stopBackgroundWork()
        AdminTypingSound.shared.stop()
    }

    func stop() {
        suspend()
    }

    private func stopBackgroundWork() {
        pollTask?.cancel()
        pollTask = nil
        if let threadSubscriptionId {
            ChatEventStream.shared.unsubscribeThread(id: threadSubscriptionId)
            self.threadSubscriptionId = nil
        }
        typingStopTask?.cancel()
        typingStopTask = nil
        typingNotifyTask?.cancel()
        typingNotifyTask = nil
        suggestionsTask?.cancel()
        suggestionsTask = nil
        readAckTask?.cancel()
        readAckTask = nil
    }

    private func restoreFromCache() -> Bool {
        hydrateFromLocalStore()
        return historyBaselined
    }

    private func resetThreadState() {
        messages = []
        knownMessageIds = []
        pollSeq = 0
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
        replyToMessage = nil
        quickReplies = []
        aiSuggestions = []
        suggestionsLoading = false
        suggestionsError = nil
        deletingMessageIds = []
        linkReviewSubmittingIds = []
        userTyping = false
        handler = "ai"
        customerName = "Kunde"
        customerLanguage = ""
        adminName = ""
        assignedAgent = nil
        detectedService = ""
        updatedAt = ""
        sessionRating = 0
        messagesRevision = 0
        suggestionsForMessageId = 0
        auth = nil
    }

    private func beginEventStream(auth: AuthStore, generation: Int) {
        guard let api = auth.api else { return }
        if let threadSubscriptionId {
            ChatEventStream.shared.unsubscribeThread(id: threadSubscriptionId)
        }
        threadSubscriptionId = ChatEventStream.shared.subscribeThread(api: api, sessionId: sessionId, isTeam: false) { [weak self] event in
            guard let self, let auth = self.auth, self.lifecycleGeneration == generation else { return }
            await self.handleThreadStreamEvent(event, auth: auth)
        }
    }

    private func handleThreadStreamEvent(_ event: ChatStreamEvent, auth: AuthStore) async {
        lastStreamEventAt = Date()
        switch event.type {
        case "message":
            let eventSeq = StreamPayload.int(event.payload["seq"])
            let incoming = StreamPayload.messages(from: event.payload)
            ChatLiveDiagnostics.sseReceived(
                channel: event.channel.isEmpty ? "session:\(sessionId)" : event.channel,
                type: event.type,
                sessionId: sessionId,
                eventId: event.id,
                seq: eventSeq,
                inlineCount: incoming.count
            )
            if !incoming.isEmpty {
                insertIncomingMessages(incoming, source: "thread-sse")
                let maxId = incoming.map(\.id).filter { $0 > 0 }.max() ?? 0
                let targetSeq = max(eventSeq, maxId)
                if needsHistoryRecovery(expectedServerSeq: targetSeq) {
                    await recoverMissingHistory(auth: auth, throughSeq: targetSeq)
                }
            } else if eventSeq > 0 {
                await recoverMissingHistory(auth: auth, throughSeq: eventSeq)
            } else {
                await poll(auth: auth)
            }
        case "typing":
            let who = StreamPayload.string(event.payload["who"])
            if who == "user" {
                userTyping = StreamPayload.bool(event.payload["active"])
            }
        case "handler":
            let incomingHandler = StreamPayload.string(event.payload["handler"])
            if !incomingHandler.isEmpty {
                handler = incomingHandler
            }
            let incomingAdmin = StreamPayload.string(event.payload["admin_name"])
            if !incomingAdmin.isEmpty {
                adminName = incomingAdmin
            }
        case "link_scan_updated":
            if let updated = StreamPayload.messages(from: event.payload).first {
                applyLinkScanUpdate(updated)
            } else if let raw = event.payload["message"] as? [String: Any],
                      let data = try? JSONSerialization.data(withJSONObject: raw),
                      let updated = try? JSONDecoder().decode(LiveMessage.self, from: data) {
                applyLinkScanUpdate(updated)
            }
        case "link_scan_review_ready":
            if let updated = StreamPayload.messages(from: event.payload).first {
                applyLinkScanUpdate(updated)
            } else if let raw = event.payload["message"] as? [String: Any],
                      let data = try? JSONSerialization.data(withJSONObject: raw),
                      let updated = try? JSONDecoder().decode(LiveMessage.self, from: data) {
                applyLinkScanUpdate(updated)
            }
        case "message_deleted":
            let messageId = StreamPayload.int(event.payload["message_id"])
            let tombstone = StreamPayload.string(event.payload["tombstone"])
            let warn = StreamPayload.bool(event.payload["warn"])
            if messageId > 0 {
                applyMessageDeleted(
                    messageId: messageId,
                    tombstone: tombstone.isEmpty ? L10n.ChatMessageDeletedByEmployee : tombstone,
                    warn: warn
                )
            }
        default:
            break
        }
    }

    private func applyLinkScanUpdate(_ updated: LiveMessage) {
        guard updated.id > 0, !permanentlyDeletedMessageIds.contains(updated.id) else { return }
        if let index = messages.firstIndex(where: { $0.id == updated.id }) {
            messages[index] = updated
            messagesRevision &+= 1
        }
    }

    private func applyMessageDeleted(messageId: Int, tombstone: String, warn: Bool = false) {
        guard messageId > 0 else { return }
        guard !permanentlyDeletedMessageIds.contains(messageId) else { return }
        permanentlyDeletedMessageIds.insert(messageId)
        guard let index = messages.firstIndex(where: { $0.id == messageId }) else { return }

        deletingMessageIds.insert(messageId)
        messagesRevision &+= 1

        deletionTasks[messageId]?.cancel()
        deletionTasks[messageId] = Task { @MainActor in
            try? await Task.sleep(nanoseconds: 200_000_000)
            guard !Task.isCancelled else { return }
            finishMessageDeletion(at: index, messageId: messageId, tombstone: tombstone, warn: warn)
        }
    }

    private func finishMessageDeletion(at index: Int, messageId: Int, tombstone: String, warn: Bool) {
        deletionTasks.removeValue(forKey: messageId)
        deletingMessageIds.remove(messageId)
        guard messages.indices.contains(index), messages[index].id == messageId else { return }

        let existing = messages[index]
        messages[index] = LiveMessage(
            id: messageId,
            clientMsgId: existing.clientMsgId,
            role: existing.role,
            content: tombstone,
            ts: existing.ts ?? Int(Date().timeIntervalSince1970),
            imageUrl: nil,
            replyTo: nil,
            attachmentType: warn ? "in_place_warning" : "in_place_deleted",
            linkScanStatus: nil,
            linkScanSystemStatus: nil,
            linkScanReviewPending: nil,
            linkScanUrls: nil
        )
        knownMessageIds.remove(messageId)
        messagesRevision &+= 1
    }

    func deleteMessage(_ messageId: Int, auth: AuthStore) async {
        guard messageId > 0, let api = auth.api else { return }
        applyMessageDeleted(messageId: messageId, tombstone: L10n.ChatMessageDeletedByEmployee, warn: false)
        do {
            try await api.deleteMessage(sessionId, messageId: messageId)
        } catch {
            errorMessage = error.localizedDescription
            await refreshNow(auth: auth)
        }
    }

    func submitLinkScanReview(messageId: Int, action: String, auth: AuthStore) async {
        guard messageId > 0, let api = auth.api else { return }

        if action == "mark_safe", let index = messages.firstIndex(where: { $0.id == messageId }) {
            let existing = messages[index]
            messages[index] = LiveMessage(
                id: existing.id,
                clientMsgId: existing.clientMsgId,
                role: existing.role,
                content: existing.content,
                ts: existing.ts,
                imageUrl: existing.imageUrl,
                replyTo: existing.replyTo,
                reaction: existing.reaction,
                senderId: existing.senderId,
                senderName: existing.senderName,
                senderAvatar: existing.senderAvatar,
                senderRole: existing.senderRole,
                attachmentType: existing.attachmentType,
                linkUrl: existing.linkUrl,
                linkLabel: existing.linkLabel,
                linkIcon: existing.linkIcon,
                linkScanStatus: "safe",
                linkScanSystemStatus: existing.linkScanSystemStatus,
                linkScanReviewPending: nil,
                linkScanUrls: existing.linkScanUrls
            )
            messagesRevision &+= 1
        } else if action == "mark_unsafe", let index = messages.firstIndex(where: { $0.id == messageId }) {
            let existing = messages[index]
            messages[index] = LiveMessage(
                id: existing.id,
                clientMsgId: existing.clientMsgId,
                role: existing.role,
                content: existing.content,
                ts: existing.ts,
                imageUrl: existing.imageUrl,
                replyTo: existing.replyTo,
                reaction: existing.reaction,
                senderId: existing.senderId,
                senderName: existing.senderName,
                senderAvatar: existing.senderAvatar,
                senderRole: existing.senderRole,
                attachmentType: existing.attachmentType,
                linkUrl: existing.linkUrl,
                linkLabel: existing.linkLabel,
                linkIcon: existing.linkIcon,
                linkScanStatus: "dangerous",
                linkScanSystemStatus: existing.linkScanSystemStatus,
                linkScanReviewPending: nil,
                linkScanUrls: existing.linkScanUrls
            )
            messagesRevision &+= 1
        } else if action == "delete_warn" {
            applyMessageDeleted(
                messageId: messageId,
                tombstone: L10n.ChatLinkScanDeleteWarnTombstone,
                warn: true
            )
        }

        linkReviewSubmittingIds.insert(messageId)
        defer { linkReviewSubmittingIds.remove(messageId) }
        do {
            let response = try await api.submitLinkScanReview(sessionId, messageId: messageId, action: action)
            if action == "delete_warn" {
                let tombstone = response.tombstone ?? L10n.ChatLinkScanDeleteWarnTombstone
                if let index = messages.firstIndex(where: { $0.id == messageId }) {
                    finishMessageDeletion(at: index, messageId: messageId, tombstone: tombstone, warn: true)
                }
            } else if let updated = response.message {
                applyLinkScanUpdate(updated)
            }
            PAXHaptics.success()
        } catch {
            errorMessage = error.localizedDescription
            PAXHaptics.warning()
            await refreshNow(auth: auth)
        }
    }

    private func insertIncomingMessages(_ incoming: [LiveMessage], source: String = "merge") {
        let incoming = incoming.filter { $0.id <= 0 || !permanentlyDeletedMessageIds.contains($0.id) }
        guard !incoming.isEmpty else { return }

        let beforeCount = messages.count
        let existingIds = Set(messages.map(\.id))
        var newUserMessageId = 0
        for msg in incoming where msg.id > 0 {
            if permanentlyDeletedMessageIds.contains(msg.id) { continue }
            if msg.role == "user" && !knownMessageIds.contains(msg.id) {
                newUserMessageId = msg.id
            }
            knownMessageIds.insert(msg.id)
        }

        let mergeResult = MessageMerge.mergeSorted(existing: messages, incoming: incoming)
        var published = false
        if mergeResult.changed {
            messages = mergeResult.messages
            messagesRevision &+= 1
            published = true
        } else if incoming.contains(where: { $0.id > 0 && !existingIds.contains($0.id) }) {
            messagesRevision &+= 1
            published = true
        }

        ChatLiveDiagnostics.mergeResult(
            sessionId: sessionId,
            incomingIds: incoming.map(\.id),
            changed: mergeResult.changed,
            before: beforeCount,
            after: messages.count,
            published: published
        )

        if !incoming.isEmpty {
            let payload = CachedPollPayload(
                handler: handler,
                handlerLabel: "",
                adminName: adminName,
                customerName: customerName,
                assignedAgent: assignedAgent,
                sessionRating: sessionRating,
                detectedService: detectedService,
                updatedAt: updatedAt,
                seq: pollSeq,
                messageCount: max(serverMessageCount, messages.count, pollSeq),
                messages: messages
            )
            ConversationHistoryStore.shared.save(payload.asPollResponse(), sessionId: sessionId)
        }

        let maxId = messages.map(\.id).filter { $0 > 0 }.max() ?? 0
        if maxId > 0 {
            pollSeq = max(pollSeq, maxId)
            scheduleReadAcknowledgement()
        }

        if newUserMessageId > 0 {
            userTyping = false
            if handler == "admin" {
                fetchSuggestions(messageId: newUserMessageId)
            }
        }

        let presentIds = Set(messages.map(\.id))
        if !published, incoming.contains(where: { $0.id > 0 && !presentIds.contains($0.id) }) {
            ChatLiveDiagnostics.cursorAdjusted(sessionId: sessionId, reason: "\(source)-unpublished", pollSeq: pollSeq)
        }
    }

    private func persistedMessageCount() -> Int {
        messages.filter { $0.id > 0 }.count
    }

    private func scheduleReadAcknowledgement() {
        guard historyBaselined, pollSeq > 0, let api = auth?.api else { return }
        let seq = pollSeq
        readAckTask?.cancel()
        readAckTask = Task {
            try? await Task.sleep(nanoseconds: 350_000_000)
            guard !Task.isCancelled else { return }
            try? await api.markSessionRead(sessionId, seq: seq)
        }
    }

    private func needsHistoryRecovery(expectedServerSeq: Int = 0) -> Bool {
        if serverMessageCount > 0 && persistedMessageCount() < serverMessageCount {
            return true
        }
        let localMax = localMessageMaxId()
        if expectedServerSeq > localMax {
            return true
        }
        return pollSeq > localMax
    }

    private func localMessageMaxId() -> Int {
        messages.map(\.id).filter { $0 > 0 }.max() ?? 0
    }

    private func loadFullHistory(auth: AuthStore, expectedServerSeq: Int = 0, showLoading: Bool = false) async {
        guard let api = auth.api else { return }
        defer { isLoadingMessages = false }
        do {
            let data = try await api.fetchSession(sessionId)
            applyBaselineSnapshot(data)
            await verifyHistoryIntegrity(auth: auth)
            let targetSeq = max(expectedServerSeq, data.seq)
            if needsHistoryRecovery(expectedServerSeq: targetSeq) {
                await recoverMissingHistory(auth: auth, throughSeq: targetSeq)
            }
            errorMessage = nil
            if handler == "admin" {
                maybeFetchSuggestionsForLatestUserMessage()
            }
        } catch {
            if case LiveChatAPIError.unauthorized = error, let auth = self.auth {
                auth.handleUnauthorized()
            }
            if messages.isEmpty {
                errorMessage = error.localizedDescription
            } else {
                noteHistoryIntegrityIssue()
            }
        }
    }

    private func reloadFullHistory(auth: AuthStore) async {
        guard let api = auth.api else { return }
        do {
            let data = try await api.fetchSession(sessionId)
            applyBaselineSnapshot(data)
            await verifyHistoryIntegrity(auth: auth)
            errorMessage = nil
        } catch {
            if case LiveChatAPIError.unauthorized = error, let auth = self.auth {
                auth.handleUnauthorized()
            }
            errorMessage = error.localizedDescription
        }
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

    private func fillMessageGap(auth: AuthStore, throughSeq: Int) async {
        guard throughSeq > 0, needsHistoryRecovery(expectedServerSeq: throughSeq) else { return }
        guard let api = auth.api else { return }

        var attempts = 0
        while needsHistoryRecovery(expectedServerSeq: throughSeq) && attempts < 3 {
            attempts += 1
            do {
                let since = pollSeq
                let data = try await api.pollSession(sessionId, since: since)
                applyIncrementalSnapshot(data, polledSince: since)
                if !needsHistoryRecovery(expectedServerSeq: throughSeq) {
                    break
                }
                if data.messages.isEmpty {
                    await reloadFullHistory(auth: auth)
                    break
                }
            } catch {
                if case LiveChatAPIError.unauthorized = error, let auth = self.auth {
                    auth.handleUnauthorized()
                }
                errorMessage = error.localizedDescription
                break
            }
        }
    }

    private func applyBaselineSnapshot(_ data: PollResponse, persistCache: Bool = true) {
        applySessionMeta(data)
        let optimistic = messages.filter { $0.id < 0 }
        let serverMax = data.messages.map(\.id).filter { $0 > 0 }.max() ?? 0
        let liveAhead = messages.filter { $0.id > serverMax }
        var merged = MessageMerge.baseline(server: data.messages, preservingOptimistic: optimistic)
        if !liveAhead.isEmpty {
            merged = MessageMerge.mergeSorted(existing: merged, incoming: liveAhead).messages
        }
        messages = merged
        knownMessageIds = Set(messages.map(\.id))
        let localMax = localMessageMaxId()
        if localMax < data.seq {
            pollSeq = localMax
            ChatLiveDiagnostics.cursorAdjusted(sessionId: sessionId, reason: "baseline-gap", pollSeq: pollSeq)
        } else {
            pollSeq = max(localMax, data.seq)
        }
        messagesRevision &+= 1
        serverMessageCount = max(data.messageCount, data.messages.count, data.seq)
        historyBaselined = true
        scheduleReadAcknowledgement()

        if !data.reactions.isEmpty {
            let reactionResult = MessageMerge.applyReactions(to: messages, reactions: data.reactions)
            if reactionResult.changed {
                messages = reactionResult.messages
            }
        }
        noteHistoryIntegrityIssue()
        if persistCache {
            ConversationHistoryStore.shared.save(data, sessionId: sessionId)
        }
    }

    private func applyIncrementalSnapshot(_ data: PollResponse, polledSince: Int) {
        applySessionMeta(data)
        if !data.messages.isEmpty {
            insertIncomingMessages(data.messages, source: "poll")
        }
        reconcilePollCursor(serverSeq: data.seq)
        ChatLiveDiagnostics.pollApplied(
            sessionId: sessionId,
            since: polledSince,
            serverSeq: data.seq,
            newCount: data.messages.count,
            localMax: localMessageMaxId(),
            pollSeq: pollSeq
        )
        if data.messageCount > serverMessageCount {
            serverMessageCount = data.messageCount
        }

        if !data.reactions.isEmpty {
            let reactionResult = MessageMerge.applyReactions(to: messages, reactions: data.reactions)
            if reactionResult.changed {
                messages = reactionResult.messages
            }
        }
        noteHistoryIntegrityIssue()
    }

    private func applySessionMeta(_ data: PollResponse) {
        if handler != data.handler { handler = data.handler }
        let resolvedName = data.customerName.isEmpty ? "Kunde" : data.customerName
        if customerName != resolvedName { customerName = resolvedName }
        if customerLanguage != data.customerLanguage { customerLanguage = data.customerLanguage }
        if adminName != data.adminName { adminName = data.adminName }
        if assignedAgent?.id != data.assignedAgent?.id || assignedAgent?.name != data.assignedAgent?.name {
            assignedAgent = data.assignedAgent
        }
        if detectedService != data.detectedService { detectedService = data.detectedService }
        if updatedAt != data.updatedAt { updatedAt = data.updatedAt }
        if data.sessionRating > 0, sessionRating != data.sessionRating {
            sessionRating = data.sessionRating
        }
        if userTyping != data.userTyping { userTyping = data.userTyping }
    }

    private func reconcilePollCursor(serverSeq: Int) {
        let localMax = localMessageMaxId()
        if serverSeq > localMax {
            pollSeq = localMax
            return
        }
        pollSeq = max(pollSeq, serverSeq)
    }

    private func noteHistoryIntegrityIssue() {
        guard serverMessageCount > 0, persistedMessageCount() < serverMessageCount else {
            return
        }
        // Silent background recovery — never disturb the chat UI.
    }

    private func loadQuickReplies(auth: AuthStore) async {
        guard let api = auth.api, quickReplies.isEmpty else { return }
        if let response = try? await api.fetchQuickReplies() {
            quickReplies = filteredQuickReplies(from: response.quickReplies)
        }
    }

    private func loadQuickLinks(auth: AuthStore) async {
        guard let api = auth.api else { return }
        if let response = try? await api.fetchQuickLinks() {
            quickLinks = response.quickLinks
        }
    }

    func sendQuickLink(_ link: QuickLink, auth: AuthStore) async {
        guard handler == "admin", let api = auth.api else { return }
        let clientMsgId = UUID().uuidString.lowercased()
        let tempId = -(Int(Date().timeIntervalSince1970 * 1000) % 1_000_000_000)
        let optimistic = LiveMessage(
            id: tempId,
            clientMsgId: clientMsgId,
            role: "admin",
            content: link.label,
            ts: Int(Date().timeIntervalSince1970),
            senderId: auth.profile?.userId,
            senderName: auth.profile?.displayName,
            attachmentType: "link_card",
            linkUrl: link.url,
            linkLabel: link.label,
            linkIcon: link.icon
        )
        messages.append(optimistic)
        knownMessageIds.insert(tempId)
        isSending = true
        defer { isSending = false }

        do {
            let msg = try await api.sendLinkCard(sessionId, linkId: link.id, clientMsgId: clientMsgId)
            messages.removeAll { $0.id == tempId }
            knownMessageIds.remove(tempId)
            insertIncomingMessages([msg])
        } catch {
            messages.removeAll { $0.id == tempId }
            knownMessageIds.remove(tempId)
            if case LiveChatAPIError.unauthorized = error {
                auth.handleUnauthorized()
            }
            errorMessage = error.localizedDescription
        }
    }

    func filteredQuickReplies() -> [QuickReply] {
        filteredQuickReplies(from: quickReplies)
    }

    private func filteredQuickReplies(from items: [QuickReply]) -> [QuickReply] {
        let lang = customerLanguage.trimmingCharacters(in: .whitespacesAndNewlines).lowercased()
        guard !lang.isEmpty else { return items }
        let localized = items.filter { $0.lang.lowercased() == lang }
        return localized.isEmpty ? items : localized
    }

    private func poll(auth: AuthStore) async {
        guard let api = auth.api else { return }
        do {
            let since = pollSeq
            let data = try await api.pollSession(sessionId, since: since)
            applyIncrementalSnapshot(data, polledSince: since)
            if serverMessageCount > 0 && persistedMessageCount() < serverMessageCount {
                await reloadFullHistory(auth: auth)
            }
        } catch {
            if case LiveChatAPIError.unauthorized = error {
                auth.handleUnauthorized()
            }
            errorMessage = error.localizedDescription
        }
    }

    private func applyPoll(_ data: PollResponse) {
        applyIncrementalSnapshot(data, polledSince: pollSeq)
    }

    private func applyReactions(_ reactions: [String: String]) {
        let result = MessageMerge.applyReactions(to: messages, reactions: reactions)
        if result.changed {
            messages = result.messages
        }
    }

    func applyQuickReply(_ text: String) {
        draft = text
    }

    func applySuggestion(_ text: String) {
        draft = text
    }

    func setReply(to message: LiveMessage) {
        replyToMessage = message
    }

    func clearReply() {
        replyToMessage = nil
    }

    func fetchSuggestions(messageId: Int) {
        guard handler == "admin", messageId > 0 else { return }
        if suggestionsForMessageId == messageId && !aiSuggestions.isEmpty { return }

        suggestionsTask?.cancel()
        suggestionsForMessageId = messageId
        suggestionsLoading = true
        suggestionsError = nil
        aiSuggestions = []

        suggestionsTask = Task { [weak self] in
            guard let self else { return }
            defer { self.suggestionsLoading = false }
            guard !Task.isCancelled else { return }
            guard let api = self.auth?.api else { return }
            do {
                let response = try await api.fetchSuggestions(sessionId: self.sessionId, messageId: messageId)
                guard !Task.isCancelled, response.messageId == messageId else { return }
                self.aiSuggestions = response.suggestions
                self.suggestionsError = response.suggestions.isEmpty ? L10n.ChatSuggestionsEmpty : nil
            } catch {
                guard !Task.isCancelled else { return }
                self.suggestionsError = error.localizedDescription
            }
        }
    }

    func maybeFetchSuggestionsForLatestUserMessage() {
        guard handler == "admin" else {
            clearSuggestions()
            return
        }
        guard let lastUser = messages.last(where: { $0.role == "user" }) else {
            clearSuggestions()
            return
        }
        fetchSuggestions(messageId: lastUser.id)
    }

    func clearSuggestions() {
        suggestionsTask?.cancel()
        suggestionsTask = nil
        aiSuggestions = []
        suggestionsLoading = false
        suggestionsError = nil
        suggestionsForMessageId = 0
    }

    func handleDraftChange(auth: AuthStore) {
        let trimmed = draft.trimmingCharacters(in: .whitespacesAndNewlines)
        if trimmed.isEmpty {
            AdminTypingSound.shared.stop()
            Task { await notifyTypingStop(auth: auth) }
            return
        }
        AdminTypingSound.shared.typingActivity()
        scheduleTypingNotify(auth: auth)
    }

    func send(auth: AuthStore) async {
        let text = draft.trimmingCharacters(in: .whitespacesAndNewlines)
        guard !text.isEmpty, let api = auth.api else { return }
        AdminTypingSound.shared.stop()
        typingNotifyTask?.cancel()
        await notifyTypingStop(auth: auth)

        if handler == "closed" {
            errorMessage = L10n.ChatHandlerClosed
            draft = text
            return
        }
        if handler != "admin", auth.canTakeOverChats {
            do {
                try await api.takeover(sessionId)
                handler = "admin"
                if adminName.isEmpty {
                    adminName = auth.profile?.displayName ?? L10n.ChatAgent
                }
            } catch {
                errorMessage = error.localizedDescription
                draft = text
                return
            }
        }

        let replyId = replyToMessage?.id
        let clientMsgId = UUID().uuidString.lowercased()
        let tempId = -(Int(Date().timeIntervalSince1970 * 1000) % 1_000_000_000)
        let optimistic = LiveMessage(
            id: tempId,
            clientMsgId: clientMsgId,
            role: "admin",
            content: text,
            ts: Int(Date().timeIntervalSince1970),
            replyTo: replyId,
            senderId: auth.profile?.userId,
            senderName: auth.profile?.displayName
        )
        messages.append(optimistic)
        let queued = PendingMessageStore.shared.enqueue(PendingOutboundMessage(
            id: clientMsgId,
            sessionId: sessionId,
            channel: .booking,
            content: text,
            replyTo: replyId,
            createdAt: Date().timeIntervalSince1970,
            attempts: 0
        ))
        guard queued else {
            messages.removeAll { $0.id == tempId }
            knownMessageIds.remove(tempId)
            draft = text
            errorMessage = "Nachricht konnte nicht sicher lokal gespeichert werden."
            return
        }
        knownMessageIds.insert(tempId)
        draft = ""
        clearReply()
        clearSuggestions()
        isSending = true
        defer { isSending = false }

        do {
            let msg = try await api.sendMessage(
                sessionId,
                text: text,
                replyTo: replyId,
                clientMsgId: clientMsgId
            )
            messages.removeAll { $0.id == tempId }
            knownMessageIds.remove(tempId)
            insertIncomingMessages([msg])
            pollSeq = max(pollSeq, msg.id)
            PendingMessageStore.shared.acknowledge(clientMsgId: clientMsgId)
            MessageSendSound.shared.playIfEnabled()
            await poll(auth: auth)
        } catch {
            switch error {
            case LiveChatAPIError.unauthorized, LiveChatAPIError.rejected(_):
                PendingMessageStore.shared.acknowledge(clientMsgId: clientMsgId)
                messages.removeAll { $0.id == tempId }
                knownMessageIds.remove(tempId)
                draft = text
            default:
                // The request may have reached the server before the connection
                // failed. Keep one durable outbox item and retry with the same ID.
                errorMessage = "Nachricht wird automatisch erneut gesendet."
                return
            }
            errorMessage = error.localizedDescription
        }
    }

    private func retryPendingMessages(auth: AuthStore) async {
        guard let api = auth.api else { return }
        let pending = PendingMessageStore.shared.pending(sessionId: sessionId, channel: .booking)
        for item in pending {
            guard !Task.isCancelled else { return }
            PendingMessageStore.shared.noteAttempt(clientMsgId: item.id)
            do {
                let message: LiveMessage
                if let path = item.attachmentPath {
                    guard let imageData = try? Data(contentsOf: URL(fileURLWithPath: path)) else {
                        PendingMessageStore.shared.acknowledge(clientMsgId: item.id)
                        continue
                    }
                    message = try await api.sendImage(
                        sessionId,
                        imageData: imageData,
                        filename: item.filename ?? "image.jpg",
                        caption: item.content,
                        replyTo: item.replyTo,
                        clientMsgId: item.id
                    )
                } else {
                    message = try await api.sendMessage(
                        sessionId,
                        text: item.content,
                        replyTo: item.replyTo,
                        clientMsgId: item.id
                    )
                }
                insertIncomingMessages([message])
                PendingMessageStore.shared.acknowledge(clientMsgId: item.id)
            } catch {
                if case LiveChatAPIError.unauthorized = error {
                    auth.handleUnauthorized()
                }
                return
            }
        }
    }

    func sendImage(auth: AuthStore, imageData: Data, filename: String) async {
        guard let api = auth.api else { return }
        typingNotifyTask?.cancel()
        let caption = draft.trimmingCharacters(in: .whitespacesAndNewlines)
        let replyId = replyToMessage?.id
        let clientMsgId = UUID().uuidString.lowercased()
        let tempId = -(Int(Date().timeIntervalSince1970 * 1000) % 1_000_000_000) - 1
        let optimistic = LiveMessage(
            id: tempId,
            clientMsgId: clientMsgId,
            role: "admin",
            content: caption.isEmpty ? "📷 Foto" : caption,
            ts: Int(Date().timeIntervalSince1970),
            replyTo: replyId,
            senderId: auth.profile?.userId,
            senderName: auth.profile?.displayName
        )
        messages.append(optimistic)
        let queued = PendingMessageStore.shared.enqueueImage(
            PendingOutboundMessage(
                id: clientMsgId,
                sessionId: sessionId,
                channel: .booking,
                content: caption,
                replyTo: replyId,
                createdAt: Date().timeIntervalSince1970,
                attempts: 0
            ),
            data: imageData,
            filename: filename
        )
        guard queued else {
            messages.removeAll { $0.id == tempId }
            knownMessageIds.remove(tempId)
            draft = caption
            errorMessage = "Bild konnte nicht sicher lokal gespeichert werden."
            return
        }
        knownMessageIds.insert(tempId)
        draft = ""
        clearReply()
        isSending = true
        defer { isSending = false }

        do {
            let msg = try await api.sendImage(
                sessionId,
                imageData: imageData,
                filename: filename,
                caption: caption,
                replyTo: replyId,
                clientMsgId: clientMsgId
            )
            messages.removeAll { $0.id == tempId }
            knownMessageIds.remove(tempId)
            insertIncomingMessages([msg])
            pollSeq = max(pollSeq, msg.id)
            PendingMessageStore.shared.acknowledge(clientMsgId: clientMsgId)
            MessageSendSound.shared.playIfEnabled()
            await poll(auth: auth)
        } catch {
            switch error {
            case LiveChatAPIError.unauthorized, LiveChatAPIError.rejected(_):
                PendingMessageStore.shared.acknowledge(clientMsgId: clientMsgId)
                messages.removeAll { $0.id == tempId }
                knownMessageIds.remove(tempId)
                errorMessage = error.localizedDescription
            default:
                errorMessage = "Bild wird automatisch erneut gesendet."
            }
        }
    }

    func notifyTyping(auth: AuthStore) async {
        typingStopTask?.cancel()
        try? await auth.api?.setTyping(sessionId)
        typingStopTask = Task { [weak self] in
            try? await Task.sleep(nanoseconds: 1_800_000_000)
            guard !Task.isCancelled, let self else { return }
            try? await auth.api?.setTyping(self.sessionId, stop: true)
        }
    }

    func notifyTypingStop(auth: AuthStore) async {
        typingStopTask?.cancel()
        typingStopTask = nil
        typingNotifyTask?.cancel()
        typingNotifyTask = nil
        lastTypingNotifyAt = .distantPast
        try? await auth.api?.setTyping(sessionId, stop: true)
    }

    private func scheduleTypingNotify(auth: AuthStore) {
        let minInterval: TimeInterval = 1.1
        let now = Date()
        let elapsed = now.timeIntervalSince(lastTypingNotifyAt)

        if elapsed >= minInterval {
            lastTypingNotifyAt = now
            Task { await notifyTyping(auth: auth) }
            return
        }

        typingNotifyTask?.cancel()
        let remaining = max(minInterval - elapsed, 0)
        let delay = UInt64(remaining * 1_000_000_000)
        typingNotifyTask = Task { [weak self] in
            try? await Task.sleep(nanoseconds: delay)
            guard !Task.isCancelled, let self else { return }
            self.lastTypingNotifyAt = Date()
            await self.notifyTyping(auth: auth)
        }
    }

    func reloadAfterTakeover(auth: AuthStore) async {
        await reloadFullHistory(auth: auth)
        if handler == "admin" {
            await loadQuickReplies(auth: auth)
        } else {
            clearSuggestions()
        }
    }
}
