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
                IncomingCallRingtone.shared.startRinging()
                scheduleFullscreenPresentation()
            } else {
                showIncomingFullscreen = false
                IncomingCallRingtone.shared.stopRinging()
            }
        }
    }
    @Published var activeSessionId: String?
    @Published var showIncomingFullscreen = false
    @Published var incomingBannerDismissed = false

    private var listTask: Task<Void, Never>?
    private var knownLiveRequests = Set<String>()
    private var lastKnownPreviews: [String: String] = [:]
    private var expiryTasks: [String: Task<Void, Never>] = [:]
    private var fullscreenTask: Task<Void, Never>?
    private(set) var lastSessionRefreshAt: Date?

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

    func start(auth: AuthStore) {
        stop()
        listTask = Task { [weak self] in
            while !Task.isCancelled {
                await self?.refreshSessions(auth: auth)
                let interval = AppRefreshPolicy.sessionListInterval
                try? await Task.sleep(nanoseconds: interval)
            }
        }
        if let api = auth.api {
            ChatEventStream.shared.startInbox(api: api) { [weak self] event in
                Task { @MainActor in
                    await self?.refreshSessions(auth: auth)
                    if event.type == "message", let sid = event.payload["session_id"] as? String {
                        self?.postSessionSync(sessionId: sid)
                    }
                }
            }
        }
    }

    func stop() {
        listTask?.cancel()
        listTask = nil
        ChatEventStream.shared.stopInbox()
        expiryTasks.values.forEach { $0.cancel() }
        expiryTasks.removeAll()
        IncomingCallRingtone.shared.stopRinging()
    }

    func refreshSessions(auth: AuthStore) async {
        guard let api = auth.api else { return }
        let shouldShowSync = sessions.isEmpty
        if shouldShowSync { isSyncing = true }
        defer { if shouldShowSync { isSyncing = false } }
        do {
            let response = try await api.fetchSessions()
            let newSessions = response.sessions
            let newLiveCount = response.liveCount
            let changed = newSessions != sessions || newLiveCount != liveCount
            guard changed else {
                errorMessage = nil
                return
            }
            sessions = newSessions
            liveCount = newLiveCount
            lastSyncAt = Date()
            lastSessionRefreshAt = lastSyncAt
            updateUnreadCounts()
            AppRefreshPolicy.update(liveCount: newLiveCount, openChat: activeSessionId != nil)
            errorMessage = nil
            detectIncomingLiveRequests(newSessions)
        } catch {
            if case LiveChatAPIError.unauthorized = error {
                auth.handleUnauthorized()
            }
            errorMessage = error.localizedDescription
        }
    }

    func updateUnreadCounts() {
        let settings = AppSettingsStore.shared
        unreadChatCount = sessions.filter { !$0.isTeamDM && settings.isSessionUnread($0) }.count
        unreadTeamCount = sessions.filter { $0.isTeamDM && settings.isSessionUnread($0) }.count
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

    func handlePush(sessionId: String, type: String, auth: AuthStore, payload: PushService.PushPayload? = nil) async {
        let event = payload?.event ?? type
        switch event {
        case "customer_waiting", "live_request":
            await presentLiveRequest(sessionId: sessionId, auth: auth, payload: payload)
        case "new_chat_started", "new_chat", "new_customer_message", "message":
            await refreshSessions(auth: auth)
            if event == "new_customer_message" || type == "message" {
                activeSessionId = sessionId
                postSessionSync(sessionId: sessionId)
            } else {
                postSessionSync(sessionId: sessionId)
            }
        case "assigned_chat_updated", "new_lead_contact", "missed_chat":
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
                if type == "message" {
                    activeSessionId = sessionId
                    postSessionSync(sessionId: sessionId)
                } else {
                    postSessionSync(sessionId: sessionId)
                }
            default:
                await refreshSessions(auth: auth)
                activeSessionId = sessionId
                postSessionSync(sessionId: sessionId)
            }
        }
    }

    private func postSessionSync(sessionId: String) {
        NotificationCenter.default.post(
            name: .paxSessionSync,
            object: nil,
            userInfo: ["session_id": sessionId]
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

        if let payload {
            presentIncoming(session: LiveSession.fromPushPayload(sessionId: sessionId, payload: payload))
        }
    }
}

@MainActor
final class ChatThreadModel: ObservableObject {
    @Published var messages: [LiveMessage] = []
    @Published var isLoadingMessages = true
    @Published var handler = "ai"
    @Published var customerName = "Kunde"
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
    @Published var aiSuggestions: [String] = []
    @Published var suggestionsLoading = false
    @Published var suggestionsError: String?

    let sessionId: String
    private weak var auth: AuthStore?
    private var pollSeq = 0
    private var pollTask: Task<Void, Never>?
    private var typingStopTask: Task<Void, Never>?
    private var typingNotifyTask: Task<Void, Never>?
    private var suggestionsTask: Task<Void, Never>?
    private var suggestionsForMessageId = 0
    private var knownMessageIds = Set<Int>()
    private var lastTypingNotifyAt = Date.distantPast

    init(sessionId: String) {
        self.sessionId = sessionId
    }

    func start(auth: AuthStore) {
        self.auth = auth
        pollTask?.cancel()
        pollTask = Task { [weak self] in
            guard let self else { return }
            await self.loadQuickReplies(auth: auth)
            await self.loadFull(auth: auth)
            while !Task.isCancelled {
                await self.poll(auth: auth)
                try? await Task.sleep(nanoseconds: AppRefreshPolicy.chatThreadInterval)
            }
        }
        if let api = auth.api {
            ChatEventStream.shared.startThread(api: api, sessionId: sessionId, isTeam: false) { [weak self] event in
                Task { @MainActor in
                    guard let self, let auth = self.auth else { return }
                    if event.type == "message" || event.type == "typing" || event.type == "handler" {
                        await self.poll(auth: auth)
                    }
                }
            }
        }
    }

    func refreshNow(auth: AuthStore) async {
        await poll(auth: auth)
    }

    func stop() {
        pollTask?.cancel()
        pollTask = nil
        ChatEventStream.shared.stopThread()
        typingStopTask?.cancel()
        typingStopTask = nil
        typingNotifyTask?.cancel()
        typingNotifyTask = nil
        suggestionsTask?.cancel()
        suggestionsTask = nil
        AdminTypingSound.shared.stop()
    }

    private func loadQuickReplies(auth: AuthStore) async {
        guard let api = auth.api, quickReplies.isEmpty else { return }
        if let response = try? await api.fetchQuickReplies() {
            quickReplies = response.quickReplies
        }
    }

    private func loadFull(auth: AuthStore) async {
        guard let api = auth.api else {
            isLoadingMessages = false
            return
        }
        isLoadingMessages = messages.isEmpty
        defer { isLoadingMessages = false }
        do {
            let data = try await api.fetchSession(sessionId)
            applyPoll(data)
            knownMessageIds = Set(messages.map(\.id))
            if handler == "admin" {
                maybeFetchSuggestionsForLatestUserMessage()
            }
        } catch {
            if case LiveChatAPIError.unauthorized = error, let auth = self.auth {
                auth.handleUnauthorized()
            }
            errorMessage = error.localizedDescription
        }
    }

    private func poll(auth: AuthStore) async {
        guard let api = auth.api else { return }
        do {
            let data = try await api.pollSession(sessionId, since: pollSeq)
            applyPoll(data)
        } catch {
            if case LiveChatAPIError.unauthorized = error {
                auth.handleUnauthorized()
            }
            errorMessage = error.localizedDescription
        }
    }

    private func applyPoll(_ data: PollResponse) {
        if handler != data.handler { handler = data.handler }
        let resolvedName = data.customerName.isEmpty ? "Kunde" : data.customerName
        if customerName != resolvedName { customerName = resolvedName }
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
        pollSeq = max(pollSeq, data.seq)

        if !data.reactions.isEmpty {
            let reactionResult = MessageMerge.applyReactions(to: messages, reactions: data.reactions)
            if reactionResult.changed {
                messages = reactionResult.messages
            }
        }

        guard !data.messages.isEmpty else {
            return
        }

        var newUserMessageId = 0
        for msg in data.messages {
            if msg.role == "user" && !knownMessageIds.contains(msg.id) {
                newUserMessageId = msg.id
            }
            knownMessageIds.insert(msg.id)
        }

        let mergeResult = MessageMerge.mergeSorted(existing: messages, incoming: data.messages)
        if mergeResult.changed {
            messages = mergeResult.messages
        }

        if newUserMessageId > 0 {
            userTyping = false
        }

        if handler == "admin", newUserMessageId > 0 {
            fetchSuggestions(messageId: newUserMessageId)
        }
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
                self.suggestionsError = response.suggestions.isEmpty ? "Keine Vorschläge verfügbar." : nil
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

        let replyId = replyToMessage?.id
        let tempId = -(Int(Date().timeIntervalSince1970 * 1000) % 1_000_000_000)
        let optimistic = LiveMessage(
            id: tempId,
            role: "admin",
            content: text,
            ts: Int(Date().timeIntervalSince1970),
            replyTo: replyId,
            senderId: auth.profile?.userId,
            senderName: auth.profile?.displayName
        )
        messages.append(optimistic)
        knownMessageIds.insert(tempId)
        draft = ""
        clearReply()
        clearSuggestions()
        isSending = true
        defer { isSending = false }

        do {
            let msg = try await api.sendMessage(sessionId, text: text, replyTo: replyId)
            if let index = messages.firstIndex(where: { $0.id == tempId }) {
                messages[index] = msg
            } else {
                messages.append(msg)
            }
            knownMessageIds.remove(tempId)
            knownMessageIds.insert(msg.id)
            pollSeq = max(pollSeq, msg.id)
            MessageSendSound.shared.playIfEnabled()
            await poll(auth: auth)
        } catch {
            messages.removeAll { $0.id == tempId }
            knownMessageIds.remove(tempId)
            draft = text
            errorMessage = error.localizedDescription
        }
    }

    func sendImage(auth: AuthStore, imageData: Data, filename: String) async {
        guard let api = auth.api else { return }
        typingNotifyTask?.cancel()
        let caption = draft.trimmingCharacters(in: .whitespacesAndNewlines)
        let replyId = replyToMessage?.id
        let tempId = -(Int(Date().timeIntervalSince1970 * 1000) % 1_000_000_000) - 1
        let optimistic = LiveMessage(
            id: tempId,
            role: "admin",
            content: caption.isEmpty ? "📷 Foto" : caption,
            ts: Int(Date().timeIntervalSince1970),
            replyTo: replyId,
            senderId: auth.profile?.userId,
            senderName: auth.profile?.displayName
        )
        messages.append(optimistic)
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
                replyTo: replyId
            )
            if let index = messages.firstIndex(where: { $0.id == tempId }) {
                messages[index] = msg
            } else {
                messages.append(msg)
            }
            knownMessageIds.remove(tempId)
            knownMessageIds.insert(msg.id)
            pollSeq = max(pollSeq, msg.id)
            MessageSendSound.shared.playIfEnabled()
            await poll(auth: auth)
        } catch {
            messages.removeAll { $0.id == tempId }
            knownMessageIds.remove(tempId)
            errorMessage = error.localizedDescription
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
        guard let api = auth.api else { return }
        if let data = try? await api.fetchSession(sessionId) {
            handler = data.handler
            customerName = data.customerName.isEmpty ? "Kunde" : data.customerName
            adminName = data.adminName
            detectedService = data.detectedService
            updatedAt = data.updatedAt
            sessionRating = data.sessionRating
            messages = data.messages
            knownMessageIds = Set(messages.map(\.id))
            pollSeq = data.seq
            if !data.reactions.isEmpty {
                applyReactions(data.reactions)
            }
            if handler == "admin" {
                await loadQuickReplies(auth: auth)
                maybeFetchSuggestionsForLatestUserMessage()
            } else {
                clearSuggestions()
            }
        }
    }
}
