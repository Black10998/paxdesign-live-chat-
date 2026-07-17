import SwiftUI
import PhotosUI

struct CustomerLoginView: View {
    @EnvironmentObject private var auth: CustomerAuthStore
    @EnvironmentObject private var api: CustomerAPIClient
    @StateObject private var network = CustomerNetworkMonitor.shared
    var onRegister: (() -> Void)? = nil
    var onForgot: (() -> Void)? = nil
    @State private var isLoading = false

    var body: some View {
        Form {
            if !network.isConnected {
                Section {
                    Label(String(localized: "You are offline. Connect to sign in."), systemImage: "wifi.slash")
                        .foregroundStyle(.orange)
                }
            }
            Section(String(localized: "Account")) {
                TextField(String(localized: "Email or username"), text: $auth.username)
                    .textInputAutocapitalization(.never)
                    .keyboardType(.emailAddress)
                    .accessibilityLabel(String(localized: "Email or username"))
                SecureField(String(localized: "Password"), text: $auth.appPassword)
                    .accessibilityLabel(String(localized: "Password"))
            }
            if let error = auth.errorMessage {
                Section { Text(error).foregroundStyle(.red).accessibilityLabel(error) }
            }
            Section {
                Button(isLoading ? String(localized: "Signing in…") : String(localized: "Sign In")) {
                    Task {
                        isLoading = true
                        await auth.login(api: api)
                        isLoading = false
                    }
                }
                .disabled(isLoading || !network.isConnected)
                if let onRegister {
                    Button(String(localized: "Create account")) { onRegister() }
                }
                if let onForgot {
                    Button(String(localized: "Forgot password?")) { onForgot() }
                }
            }
        }
        .navigationTitle(String(localized: "PAXDesign"))
    }
}

struct CustomerDashboardView: View {
    @EnvironmentObject private var api: CustomerAPIClient
    @EnvironmentObject private var navigation: CustomerNavigationCoordinator
    @Environment(\.scenePhase) private var scenePhase
    @State private var dashboard: CustomerDashboard?
    @State private var profileName = ""
    @State private var error: String?
    @State private var isLoading = true

    var body: some View {
        NavigationStack {
            ScrollView {
                if isLoading && dashboard == nil {
                    CustomerDashboardSkeleton()
                        .padding(.top, 8)
                } else if let error {
                    PAXContentUnavailableView(String(localized: "Unable to load"), systemImage: "wifi.exclamationmark", description: Text(error))
                        .padding(.top, 32)
                } else if let dashboard {
                    LazyVStack(alignment: .leading, spacing: CustomerPortalDesign.sectionSpacing) {
                        CustomerPortalCard {
                            VStack(alignment: .leading, spacing: 8) {
                                Text(greeting)
                                    .font(.title2.weight(.semibold))
                                Text(String(localized: "Everything about your projects, requests, and conversations in one place."))
                                    .font(.subheadline)
                                    .foregroundStyle(PAXTheme.textSecondary)
                            }
                        }

                        if let unread = dashboard.unread_count, unread > 0 {
                            CustomerPortalCard {
                                Button {
                                    navigation.openNotifications()
                                } label: {
                                    HStack {
                                        Label(String(localized: "\(unread) unread notifications"), systemImage: "bell.badge.fill")
                                            .font(.headline)
                                        Spacer()
                                        Image(systemName: "chevron.right")
                                            .foregroundStyle(.secondary)
                                    }
                                }
                                .buttonStyle(.plain)
                            }
                        }

                        CustomerPortalCard {
                            VStack(alignment: .leading, spacing: 12) {
                                CustomerPortalSectionHeader(title: String(localized: "Conversation"))
                                if let preview = dashboard.chat?.last_preview, !preview.isEmpty {
                                    Text(preview).font(.body)
                                } else {
                                    Text(String(localized: "No messages yet. Open Chat to start talking with our team."))
                                        .foregroundStyle(PAXTheme.textSecondary)
                                }
                                NavigationLink(String(localized: "Open Chat")) {
                                    CustomerChatView(initialSessionID: dashboard.chat?.session_id)
                                }
                                .font(.subheadline.weight(.semibold))
                            }
                        }

                        CustomerPortalCard {
                            VStack(alignment: .leading, spacing: 12) {
                                CustomerPortalSectionHeader(title: String(localized: "Active Projects"))
                                if let projects = dashboard.projects_active, !projects.isEmpty {
                                    ForEach(projects, id: \.id) { project in
                                        NavigationLink {
                                            CustomerProjectDetailView(projectId: project.id)
                                        } label: {
                                            HStack {
                                                VStack(alignment: .leading, spacing: 4) {
                                                    Text(project.title).font(.headline)
                                                    Text(project.status).font(.caption).foregroundStyle(.secondary)
                                                }
                                                Spacer()
                                                Text("\(project.progress)%").font(.subheadline.weight(.semibold))
                                            }
                                        }
                                        .buttonStyle(.plain)
                                        if project.id != projects.last?.id { Divider() }
                                    }
                                } else {
                                    Text(String(localized: "No active projects yet.")).foregroundStyle(PAXTheme.textSecondary)
                                    Button(String(localized: "Browse Services")) {
                                        navigation.selectedTab = .services
                                    }
                                    .font(.subheadline.weight(.semibold))
                                }
                            }
                        }

                        CustomerPortalCard {
                            VStack(alignment: .leading, spacing: 12) {
                                CustomerPortalSectionHeader(title: String(localized: "Recent Requests"))
                                if let orders = dashboard.orders_recent, !orders.isEmpty {
                                    ForEach(orders, id: \.id) { order in
                                        NavigationLink {
                                            CustomerOrderDetailView(orderId: order.id)
                                        } label: {
                                            HStack {
                                                Text(order.service_label)
                                                Spacer()
                                                Text(order.status).foregroundStyle(.secondary)
                                            }
                                        }
                                        .buttonStyle(.plain)
                                    }
                                } else {
                                    NavigationLink(String(localized: "Start a request")) { CustomerCreateOrderView() }
                                        .font(.subheadline.weight(.semibold))
                                }
                            }
                        }

                        CustomerPortalCard {
                            VStack(alignment: .leading, spacing: 12) {
                                CustomerPortalSectionHeader(title: String(localized: "Portfolio"))
                                if let portfolio = dashboard.portfolio, !portfolio.isEmpty {
                                    ForEach(portfolio) { item in
                                        NavigationLink {
                                            CustomerPortfolioDetailView(slug: item.slug)
                                        } label: {
                                            HStack(spacing: 12) {
                                                if let imageURL = item.image_url, let url = URL(string: imageURL) {
                                                    AsyncImage(url: url) { phase in
                                                        if case .success(let image) = phase {
                                                            image.resizable().scaledToFill()
                                                        } else {
                                                            Color.gray.opacity(0.15)
                                                        }
                                                    }
                                                    .frame(width: 56, height: 56)
                                                    .clipShape(RoundedRectangle(cornerRadius: 10, style: .continuous))
                                                }
                                                VStack(alignment: .leading, spacing: 4) {
                                                    Text(item.title).font(.headline)
                                                    if let excerpt = item.excerpt, !excerpt.isEmpty {
                                                        Text(excerpt).font(.caption).foregroundStyle(.secondary).lineLimit(2)
                                                    }
                                                }
                                            }
                                        }
                                        .buttonStyle(.plain)
                                    }
                                } else {
                                    Text(String(localized: "Explore our latest work.")).foregroundStyle(PAXTheme.textSecondary)
                                }
                                NavigationLink(String(localized: "View Portfolio")) { CustomerPortfolioListView() }
                                    .font(.subheadline.weight(.semibold))
                            }
                        }

                        CustomerPortalCard {
                            VStack(alignment: .leading, spacing: 12) {
                                CustomerPortalSectionHeader(title: String(localized: "Files & Invoices"))
                                if let count = dashboard.files_count, count > 0 {
                                    Text(String(localized: "\(count) files available"))
                                        .font(.subheadline)
                                        .foregroundStyle(PAXTheme.textSecondary)
                                } else {
                                    Text(String(localized: "Download shared documents, quotes, and invoices."))
                                        .foregroundStyle(PAXTheme.textSecondary)
                                }
                                Button(String(localized: "Open Files")) {
                                    navigation.openFiles()
                                }
                                .font(.subheadline.weight(.semibold))
                            }
                        }

                        if let news = dashboard.news, !news.isEmpty {
                            CustomerPortalCard {
                                VStack(alignment: .leading, spacing: 12) {
                                    CustomerPortalSectionHeader(title: String(localized: "News"))
                                    ForEach(news, id: \.slug) { item in
                                        NavigationLink {
                                            CustomerNewsDetailView(slug: item.slug)
                                        } label: {
                                            VStack(alignment: .leading, spacing: 4) {
                                                Text(item.title).font(.headline)
                                                if let excerpt = item.excerpt, !excerpt.isEmpty {
                                                    Text(excerpt).font(.subheadline).foregroundStyle(.secondary).lineLimit(2)
                                                }
                                            }
                                        }
                                        .buttonStyle(.plain)
                                    }
                                }
                            }
                        }
                    }
                    .padding()
                }
            }
            .background(PAXBackground())
            .navigationTitle(String(localized: "Home"))
            .task(id: navigation.workspaceRefreshToken) { await load() }
            .onChange(of: scenePhase) { phase in
                if phase == .active {
                    navigation.refreshWorkspace()
                }
            }
            .refreshable { await load() }
        }
    }

    private func load() async {
        if dashboard == nil { isLoading = true }
        error = nil
        do {
            async let dashboardTask = api.fetchDashboard()
            async let profileTask = api.fetchProfile()
            dashboard = try await dashboardTask
            if let profile = try? await profileTask {
                profileName = profile.profile.display_name
            }
        } catch {
            self.error = (error as? CustomerAPIError)?.localizedDescription ?? error.localizedDescription
        }
        isLoading = false
    }

    private var greeting: String {
        let name = profileName.trimmingCharacters(in: .whitespacesAndNewlines)
        if name.isEmpty {
            return String(localized: "Welcome back")
        }
        return String(localized: "Welcome back, \(name)")
    }
}

struct CustomerChatView: View {
    @EnvironmentObject private var api: CustomerAPIClient
    @Environment(\.scenePhase) private var scenePhase
    @StateObject private var network = CustomerNetworkMonitor.shared
    @FocusState private var isInputFocused: Bool
    var initialSessionID: String? = nil
    @State private var poll: CustomerChatPoll?
    @State private var draft = ""
    @State private var error: String?
    @State private var notice: String?
    @State private var isLoading = true
    @State private var isSending = false
    @State private var lastSeq = 0
    @State private var pollTask: Task<Void, Never>?
    @State private var showImagePicker = false
    @State private var isRecordingVoice = false
    @State private var voiceRecorder = CustomerVoiceRecorder()
    @State private var showLocationSheet = false
    @State private var typingTask: Task<Void, Never>?
    @State private var lastTypingSent = false
    @State private var recovery: CustomerChatSessionRecovery.Action?
    @State private var isRecovering = false
    @State private var pollingSuspended = false

    private var isHumanQueue: Bool {
        guard let handler = poll?.handler else { return false }
        return handler == "admin" || handler == "live_request"
    }

    private var isConversationClosed: Bool {
        poll?.handler == "closed"
    }

    var body: some View {
        NavigationStack {
            ZStack {
                PAXBackground()
                VStack(spacing: 0) {
                    if !network.isConnected {
                        Text(String(localized: "Offline — messages will send when you reconnect."))
                            .font(.caption).foregroundStyle(.orange).padding(8)
                            .frame(maxWidth: .infinity).background(Color.orange.opacity(0.12))
                    }
                    if let notice, !notice.isEmpty {
                        Text(notice)
                            .font(.caption)
                            .foregroundStyle(.secondary)
                            .padding(.horizontal, 12)
                            .padding(.vertical, 8)
                            .frame(maxWidth: .infinity)
                            .background(PAXTheme.accentSoft)
                    }
                    if let recovery, !isConversationClosed {
                        CustomerChatRecoveryBanner(
                            action: recovery,
                            isRecovering: isRecovering,
                            onRenew: { Task { await startNewConversation() } },
                            onRetry: { Task { await retryAfterRecovery() } }
                        )
                    } else if isConversationClosed {
                        closedConversationBanner
                    }
                    ScrollViewReader { proxy in
                        ScrollView {
                            if isLoading && displayMessages.isEmpty {
                                CustomerChatSkeleton()
                                    .frame(maxWidth: .infinity, maxHeight: .infinity)
                            } else if displayMessages.isEmpty && error == nil {
                                CustomerChatEmptyState(isAI: !isHumanQueue)
                                    .padding(.top, 32)
                            } else {
                                LazyVStack(alignment: .leading, spacing: 12) {
                                    ForEach(displayMessages, id: \.id) { message in
                                        CustomerChatBubble(
                                            message: message,
                                            otherReadSeq: poll?.other_read_seq ?? 0,
                                            showReadReceipts: isHumanQueue
                                        ).id(message.id)
                                    }
                                    if poll?.admin_typing == true, isHumanQueue {
                                        CustomerChatTypingIndicator(label: String(localized: "Support is typing…"))
                                            .id("typing")
                                    }
                                }
                                .padding()
                            }
                        }
                        .scrollDismissesKeyboard(.interactively)
                        .onChange(of: displayMessages.count) { _ in scrollToBottom(proxy: proxy) }
                        .onChange(of: poll?.admin_typing) { _ in scrollToBottom(proxy: proxy) }
                    }
                }
                .safeAreaInset(edge: .bottom, spacing: 0) {
                    if !isConversationClosed {
                        chatComposer
                    }
                }
            }
            .navigationTitle(String(localized: "Chat"))
            .toolbar {
                ToolbarItem(placement: .topBarLeading) {
                    if isHumanQueue, poll?.handler != "closed" {
                        Button(String(localized: "End chat")) {
                            Task { await closeConversation() }
                        }
                    }
                }
                ToolbarItem(placement: .topBarTrailing) {
                    HStack(spacing: 10) {
                        CustomerNavAvatarButton()
                        NavigationLink(String(localized: "History")) { CustomerConversationsView() }
                    }
                }
                ToolbarItemGroup(placement: .keyboard) {
                    Spacer()
                    Button(String(localized: "Done")) { isInputFocused = false }
                }
            }
            .overlay(alignment: .top) {
                if let error, recovery == nil {
                    Text(error)
                        .font(.footnote)
                        .foregroundStyle(.red)
                        .padding(8)
                        .multilineTextAlignment(.center)
                        .fixedSize(horizontal: false, vertical: true)
                        .frame(maxWidth: .infinity)
                }
            }
            .sheet(isPresented: $showImagePicker) {
                CustomerPhotoPicker { data in Task { await sendPhoto(data) } }
            }
            .sheet(isPresented: $showLocationSheet) {
                CustomerLocationShareSheet { lat, lng, label in
                    Task { await sendLocation(lat: lat, lng: lng, label: label) }
                }
            }
            .task {
                if let initialSessionID {
                    poll = CustomerChatPoll(session_id: initialSessionID, handler: nil, messages: [], message_count: nil, last_preview: nil)
                }
                await refresh(full: true)
                startPolling()
            }
            .onDisappear {
                pollTask?.cancel()
                typingTask?.cancel()
                Task { try? await api.sendChatTyping(sessionID: poll?.session_id, stop: true) }
            }
            .onChange(of: scenePhase) { phase in
                if phase == .active {
                    Task { await refresh(full: false) }
                }
            }
            .refreshable { await refresh(full: true) }
        }
    }

    private var closedConversationBanner: some View {
        VStack(spacing: 10) {
            Text(String(localized: "This conversation has ended."))
                .font(.subheadline.weight(.semibold))
                .foregroundStyle(PAXTheme.textPrimary)
            Text(String(localized: "Start a new conversation to continue chatting with our team."))
                .font(.caption)
                .foregroundStyle(PAXTheme.textSecondary)
                .multilineTextAlignment(.center)
            Button(String(localized: "Start new conversation")) {
                Task { await startNewConversation() }
            }
            .buttonStyle(CustomerPrimaryButtonStyleModifier(style: .filled))
        }
        .padding(16)
        .frame(maxWidth: .infinity)
        .background(PAXTheme.surfaceElevated)
    }

    private var chatComposer: some View {
        VStack(spacing: 0) {
            Divider()
            HStack(alignment: .bottom, spacing: 10) {
                if isHumanQueue {
                    Menu {
                        Button(String(localized: "Photo"), systemImage: "photo") { showImagePicker = true }
                        Button(isRecordingVoice ? String(localized: "Stop recording") : String(localized: "Voice message"), systemImage: "mic") {
                            Task { await toggleVoice() }
                        }
                        Button(String(localized: "Share location"), systemImage: "location") { showLocationSheet = true }
                    } label: {
                        Image(systemName: "plus.circle.fill")
                            .font(.title2)
                            .foregroundStyle(PAXTheme.accent)
                    }
                }
                TextField(String(localized: "Message"), text: $draft, axis: .vertical)
                    .lineLimit(1...5)
                    .padding(.horizontal, 12)
                    .padding(.vertical, 10)
                    .background(PAXTheme.surfaceElevated)
                    .clipShape(RoundedRectangle(cornerRadius: 18, style: .continuous))
                    .focused($isInputFocused)
                    .onChange(of: draft) { _ in scheduleTypingPing() }
                Button { Task { await send() } } label: {
                    Image(systemName: isSending ? "hourglass" : "arrow.up.circle.fill")
                        .font(.system(size: 30))
                        .symbolRenderingMode(.hierarchical)
                        .foregroundStyle(canSend ? PAXTheme.accent : .secondary)
                }
                .disabled(!canSend)
            }
            .padding(.horizontal, 12)
            .padding(.vertical, 10)
            .background(.ultraThinMaterial)
        }
    }

    private var canSend: Bool {
        !isConversationClosed
            && !draft.trimmingCharacters(in: .whitespacesAndNewlines).isEmpty
            && !isSending
            && network.isConnected
    }

    private var displayMessages: [CustomerChatPoll.ChatMessage] {
        poll?.messages ?? []
    }

    private func scrollToBottom(proxy: ScrollViewProxy) {
        if poll?.admin_typing == true {
            withAnimation { proxy.scrollTo("typing", anchor: .bottom) }
        } else if let last = displayMessages.last?.id {
            withAnimation { proxy.scrollTo(last, anchor: .bottom) }
        }
    }

    private func refresh(full: Bool = false) async {
        if isSending && !full { return }
        if pollingSuspended && !full { return }
        if full && poll == nil { isLoading = true }
        defer { isLoading = false }
        do {
            let since = full ? 0 : lastSeq
            let next = try await api.fetchChatMessages(sessionID: poll?.session_id, since: since, full: full)
            applyPollUpdate(next, full: full)
            let pollNotice = next.notice
            if let pollNotice, !pollNotice.isEmpty {
                notice = pollNotice
            }
            if next.handler == "closed" {
                recovery = CustomerChatSessionRecovery.analyze(
                    error: CustomerAPIError.serverCode("chat_closed", pollNotice ?? ""),
                    handler: "closed",
                    isConnected: network.isConnected
                )
                pollingSuspended = true
                pollTask?.cancel()
            } else {
                recovery = nil
                pollingSuspended = false
                error = nil
            }
        } catch {
            await handleChatError(error, savedDraft: nil, duringSend: false)
        }
    }

    private func applyPollUpdate(_ next: CustomerChatPoll, full: Bool) {
        if full || poll == nil {
            poll = next
        } else if let incoming = next.messages, !incoming.isEmpty {
            var merged = poll?.messages ?? []
            var existing = Set(merged.map(\.seq))
            for msg in incoming where !existing.contains(msg.seq) {
                merged.append(msg)
                existing.insert(msg.seq)
            }
            poll = CustomerChatPoll(
                session_id: next.session_id ?? poll?.session_id,
                handler: next.handler ?? poll?.handler,
                messages: merged.sorted { $0.seq < $1.seq },
                message_count: next.message_count,
                last_preview: next.last_preview,
                notice: next.notice ?? poll?.notice,
                admin_typing: next.admin_typing ?? poll?.admin_typing,
                user_typing: next.user_typing ?? poll?.user_typing,
                other_read_seq: next.other_read_seq ?? poll?.other_read_seq
            )
        } else if let current = poll {
            poll = CustomerChatPoll(
                session_id: next.session_id ?? current.session_id,
                handler: next.handler ?? current.handler,
                messages: current.messages,
                message_count: current.message_count,
                last_preview: current.last_preview,
                notice: next.notice ?? current.notice,
                admin_typing: next.admin_typing ?? current.admin_typing,
                user_typing: next.user_typing ?? current.user_typing,
                other_read_seq: next.other_read_seq ?? current.other_read_seq
            )
        }
        if let maxSeq = poll?.messages?.map(\.seq).max() {
            lastSeq = max(lastSeq, maxSeq)
        }
    }

    private func handleChatError(_ error: Error, savedDraft: String?, duringSend: Bool) async {
        if let action = CustomerChatSessionRecovery.analyze(
            error: error,
            handler: poll?.handler,
            isConnected: network.isConnected
        ) {
            recovery = action
            self.error = nil
            if action.shouldStopPolling {
                pollingSuspended = true
                pollTask?.cancel()
            }
            if action.shouldRenew, !isRecovering {
                await autoRenewSession(preserveDraft: action.preserveDraft ? savedDraft : nil)
                return
            }
            if action.preserveDraft, let savedDraft, duringSend {
                draft = savedDraft
            }
            return
        }
        recovery = nil
        if duringSend, let savedDraft {
            draft = savedDraft
        }
        self.error = friendlyChatError(error)
    }

    private func autoRenewSession(preserveDraft: String?) async {
        guard !isRecovering else { return }
        isRecovering = true
        notice = CustomerChatSessionRecovery.reconnectingNotice()
        defer { isRecovering = false }
        do {
            let renewed = try await api.renewChatSession(closedSessionID: poll?.session_id)
            poll = CustomerChatPoll(
                session_id: renewed.session_id,
                handler: renewed.handler,
                messages: [],
                message_count: nil,
                last_preview: nil
            )
            lastSeq = 0
            recovery = nil
            pollingSuspended = false
            error = nil
            startPolling()
            await refresh(full: true)
            notice = CustomerChatSessionRecovery.newConversationNotice()
            if let preserveDraft {
                draft = preserveDraft
            }
        } catch {
            await handleChatError(error, savedDraft: preserveDraft, duringSend: preserveDraft != nil)
        }
    }

    private func retryAfterRecovery() async {
        error = nil
        recovery = nil
        pollingSuspended = false
        startPolling()
        await refresh(full: true)
    }

    private func friendlyChatError(_ error: Error) -> String {
        if error is DecodingError {
            return String(localized: "We couldn't load your messages. Pull down to refresh.")
        }
        if let apiError = error as? CustomerAPIError {
            return apiError.localizedDescription ?? String(localized: "Something went wrong. Please try again.")
        }
        return CustomerAPIError.friendlyMessage(forServerText: error.localizedDescription)
    }

    private func startPolling() {
        pollTask?.cancel()
        pollingSuspended = false
        pollTask = Task {
            while !Task.isCancelled {
                try? await Task.sleep(nanoseconds: 1_500_000_000)
                if network.isConnected, !isSending, !pollingSuspended, !isConversationClosed {
                    await refresh(full: false)
                }
            }
        }
    }

    private func sendPhoto(_ data: Data) async {
        guard let session = poll?.session_id else { return }
        isSending = true
        defer { isSending = false }
        do {
            _ = try await api.uploadChatImage(sessionID: session, imageData: data, filename: "photo.jpg")
            await refresh(full: true)
        } catch { self.error = friendlyChatError(error) }
    }

    private func toggleVoice() async {
        if isRecordingVoice {
            isRecordingVoice = false
            if let result = voiceRecorder.stop(), let session = poll?.session_id {
                isSending = true
                defer { isSending = false }
                do {
                    _ = try await api.uploadChatVoice(sessionID: session, audioData: result.data, duration: result.duration)
                    await refresh(full: true)
                } catch { self.error = friendlyChatError(error) }
            }
        } else {
            do { try await voiceRecorder.start(); isRecordingVoice = true }
            catch { self.error = friendlyChatError(error) }
        }
    }

    private func sendLocation(lat: Double, lng: Double, label: String) async {
        guard let session = poll?.session_id else { return }
        isSending = true
        defer { isSending = false }
        do {
            _ = try await api.sendChatLocation(sessionID: session, lat: lat, lng: lng, label: label)
            await refresh(full: true)
        } catch { self.error = friendlyChatError(error) }
    }

    private func send() async {
        let text = draft.trimmingCharacters(in: .whitespacesAndNewlines)
        guard !text.isEmpty, !isConversationClosed else { return }
        let savedDraft = text
        let clientMsgID = UUID().uuidString
        let assistantClientMsgID = UUID().uuidString
        draft = ""
        isSending = true
        error = nil
        notice = nil
        defer {
            isSending = false
        }
        do {
            let response = try await sendMessageWithRenewFallback(
                text,
                clientMsgID: clientMsgID,
                assistantClientMsgID: assistantClientMsgID
            )
            isInputFocused = false
            applySendResponse(response)
            await refresh(full: false)
            PAXHaptics.light()
        } catch {
            await handleChatError(error, savedDraft: savedDraft, duringSend: true)
            PAXHaptics.warning()
        }
    }

    private func sendMessageWithRenewFallback(
        _ text: String,
        clientMsgID: String,
        assistantClientMsgID: String
    ) async throws -> CustomerSendResponse {
        do {
            return try await api.sendChatMessage(
                text,
                sessionID: poll?.session_id,
                clientMsgID: clientMsgID,
                assistantClientMsgID: assistantClientMsgID
            )
        } catch let apiError as CustomerAPIError {
            if shouldRenewSession(for: apiError) {
                let renewed = try await api.renewChatSession(closedSessionID: poll?.session_id)
                poll = CustomerChatPoll(
                    session_id: renewed.session_id,
                    handler: renewed.handler,
                    messages: poll?.messages,
                    message_count: poll?.message_count,
                    last_preview: poll?.last_preview
                )
                lastSeq = 0
                pollingSuspended = false
                recovery = nil
                startPolling()
                return try await api.sendChatMessage(
                    text,
                    sessionID: renewed.session_id,
                    clientMsgID: clientMsgID,
                    assistantClientMsgID: assistantClientMsgID
                )
            }
            throw apiError
        }
    }

    private func shouldRenewSession(for error: CustomerAPIError) -> Bool {
        switch error {
        case .serverCode(let code, _):
            return code == "chat_closed" || code == "invalid_session" || code == "not_found" || code == "forbidden"
        case .http(let code):
            return code == 409 || code == 404
        default:
            return false
        }
    }

    private func applySendResponse(_ response: CustomerSendResponse) {
        var merged = poll?.messages ?? []
        var existing = Set(merged.map(\.seq))

        func appendIfNew(_ message: CustomerChatPoll.ChatMessage?) {
            guard let message, !existing.contains(message.seq) else { return }
            merged.append(message)
            existing.insert(message.seq)
        }

        appendIfNew(response.message)
        appendIfNew(response.assistant)

        poll = CustomerChatPoll(
            session_id: response.session_id.isEmpty ? poll?.session_id : response.session_id,
            handler: response.handler ?? poll?.handler,
            messages: merged.isEmpty ? poll?.messages : merged.sorted { $0.seq < $1.seq },
            message_count: poll?.message_count,
            last_preview: poll?.last_preview,
            admin_typing: poll?.admin_typing,
            user_typing: poll?.user_typing,
            other_read_seq: poll?.other_read_seq
        )
        if let maxSeq = merged.map(\.seq).max() {
            lastSeq = max(lastSeq, maxSeq)
        }
        if let noticeText = response.notice, !noticeText.isEmpty {
            notice = noticeText
        } else if response.renewed == true {
            notice = String(localized: "This conversation was closed. We started a new one for your message.")
        }
        if response.handler == "live_request" || response.handler == "admin" {
            notice = response.notice ?? notice
        }
    }

    private func startNewConversation() async {
        guard !isRecovering else { return }
        isRecovering = true
        error = nil
        notice = CustomerChatSessionRecovery.reconnectingNotice()
        defer { isRecovering = false }
        do {
            let renewed = try await api.renewChatSession(closedSessionID: poll?.session_id)
            poll = CustomerChatPoll(
                session_id: renewed.session_id,
                handler: renewed.handler,
                messages: [],
                message_count: nil,
                last_preview: nil
            )
            lastSeq = 0
            recovery = nil
            pollingSuspended = false
            startPolling()
            await refresh(full: true)
            notice = CustomerChatSessionRecovery.newConversationNotice()
        } catch {
            await handleChatError(error, savedDraft: nil, duringSend: false)
        }
    }

    private func scheduleTypingPing() {
        guard isHumanQueue, poll?.handler != "closed" else { return }
        let typing = !draft.trimmingCharacters(in: .whitespacesAndNewlines).isEmpty
        guard typing != lastTypingSent else { return }
        lastTypingSent = typing
        typingTask?.cancel()
        typingTask = Task {
            try? await api.sendChatTyping(sessionID: poll?.session_id, stop: !typing)
            if typing {
                try? await Task.sleep(nanoseconds: 2_500_000_000)
                if !Task.isCancelled, draft.trimmingCharacters(in: .whitespacesAndNewlines).isEmpty {
                    lastTypingSent = false
                    try? await api.sendChatTyping(sessionID: poll?.session_id, stop: true)
                }
            }
        }
    }

    private func closeConversation() async {
        do {
            _ = try await api.closeChatSession(sessionID: poll?.session_id)
            notice = String(localized: "This conversation has ended. You can start a new one anytime.")
            await refresh(full: true)
        } catch {
            self.error = friendlyChatError(error)
        }
    }
}

struct CustomerServiceDetailView: View {
    @EnvironmentObject private var api: CustomerAPIClient
    let slug: String
    @State private var service: CustomerServiceDetail?
    @State private var error: String?

    var body: some View {
        ScrollView {
            if let service {
                VStack(alignment: .leading, spacing: CustomerPortalDesign.sectionSpacing) {
                    if let imageURL = service.image_url, let url = URL(string: imageURL) {
                        AsyncImage(url: url) { phase in
                            switch phase {
                            case .success(let image):
                                image.resizable().scaledToFill()
                            default:
                                CustomerServiceIconView(iconKey: service.icon_key ?? service.slug, size: 72)
                                    .frame(maxWidth: .infinity)
                            }
                        }
                        .frame(height: 180)
                        .frame(maxWidth: .infinity)
                        .clipShape(RoundedRectangle(cornerRadius: CustomerPortalDesign.cardRadius, style: .continuous))
                    } else {
                        CustomerServiceIconView(iconKey: service.icon_key ?? service.slug, size: 72)
                            .frame(maxWidth: .infinity)
                    }

                    CustomerPortalCard {
                        VStack(alignment: .leading, spacing: 10) {
                            Text(service.name).font(.title2.weight(.bold))
                            Text(service.category.capitalized)
                                .font(.subheadline)
                                .foregroundStyle(PAXTheme.textSecondary)
                            if service.body_text?.isEmpty == false || service.description.isEmpty == false {
                                Text(service.body_text ?? service.description)
                                    .font(.body)
                                    .foregroundStyle(PAXTheme.textPrimary)
                            }
                        }
                    }

                    if let blocks = service.blocks, !blocks.isEmpty {
                        CustomerNativeContentBlocksView(blocks: blocks)
                    }

                    if let features = service.features, !features.isEmpty {
                        CustomerPortalCard {
                            VStack(alignment: .leading, spacing: 10) {
                                CustomerPortalSectionHeader(title: String(localized: "Features"))
                                ForEach(features, id: \.self) { feature in
                                    Label(feature, systemImage: "checkmark.circle.fill")
                                        .font(.subheadline)
                                        .foregroundStyle(PAXTheme.textPrimary)
                                }
                            }
                        }
                    }

                    NavigationLink {
                        CustomerCreateOrderView(preselectedSlug: service.slug)
                    } label: {
                        Label(String(localized: "Start order"), systemImage: "plus.circle.fill")
                            .frame(maxWidth: .infinity)
                    }
                    .buttonStyle(CustomerPrimaryButtonStyleModifier(style: .filled))
                }
                .padding()
            } else if let error {
                PAXContentUnavailableView(String(localized: "Service unavailable"), systemImage: "exclamationmark.triangle", description: Text(error))
                    .padding(.top, 40)
            } else {
                CustomerDetailScrollSkeleton()
                    .padding(.top, 8)
            }
        }
        .background(PAXBackground())
        .navigationTitle(service?.name ?? String(localized: "Service"))
        .navigationBarTitleDisplayMode(.inline)
        .task { await load() }
    }

    private func load() async {
        do {
            service = try await api.fetchService(slug: slug)
        } catch {
            self.error = (error as? CustomerAPIError)?.localizedDescription ?? error.localizedDescription
        }
    }
}

struct CustomerProfileView: View {
    @EnvironmentObject private var auth: CustomerAuthStore
    @EnvironmentObject private var appAuth: AuthStore
    @EnvironmentObject private var api: CustomerAPIClient
    @EnvironmentObject private var navigation: CustomerNavigationCoordinator
    @State private var avatarUploadError: String?
    @State private var isUploadingAvatar = false
    @State private var selectedAvatarItem: PhotosPickerItem?

    var body: some View {
        List {
                if let profile = auth.profile {
                    Section(String(localized: "Profile")) {
                        HStack(spacing: 16) {
                            CustomerProfileAvatarView(urlString: profile.avatar_url, size: 64)
                            VStack(alignment: .leading, spacing: 4) {
                                Text(profile.display_name).font(.headline)
                                Text(profile.email).font(.subheadline).foregroundStyle(.secondary)
                            }
                        }
                        PhotosPicker(selection: $selectedAvatarItem, matching: .images) {
                            Label(
                                isUploadingAvatar ? String(localized: "Uploading…") : String(localized: "Change profile photo"),
                                systemImage: "camera.fill"
                            )
                        }
                        .disabled(isUploadingAvatar)
                        LabeledContent(String(localized: "Verified"), value: profile.verified ? String(localized: "Yes") : String(localized: "No"))
                    }
                }
                if let avatarUploadError {
                    Section { Text(avatarUploadError).foregroundStyle(.red) }
                }
                Section {
                    Button(String(localized: "Sign Out"), role: .destructive) {
                        appAuth.logout()
                    }
                }
                Section(String(localized: "Legal & Support")) {
                    NavigationLink(String(localized: "Privacy Policy")) {
                        CustomerLegalPageView(slug: "datenschutz", title: String(localized: "Privacy Policy"))
                    }
                    NavigationLink(String(localized: "Terms")) {
                        CustomerLegalPageView(slug: "agb", title: String(localized: "Terms"))
                    }
                    NavigationLink(String(localized: "About us")) {
                        CustomerLegalPageView(slug: "ueber-uns", title: String(localized: "About us"))
                    }
                    Button(String(localized: "Contact Support")) {
                        navigation.openChat()
                    }
                }
            }
            .navigationTitle(String(localized: "Account"))
            .onChange(of: selectedAvatarItem) { item in
                guard let item else { return }
                Task { await uploadAvatar(from: item) }
            }
            .task { await auth.refreshProfile(api: api) }
    }

    private func uploadAvatar(from item: PhotosPickerItem) async {
        isUploadingAvatar = true
        avatarUploadError = nil
        defer {
            isUploadingAvatar = false
            selectedAvatarItem = nil
        }
        do {
            guard let data = try await item.loadTransferable(type: Data.self) else {
                avatarUploadError = String(localized: "Could not load the selected photo.")
                return
            }
            _ = try await api.uploadProfileAvatar(imageData: data)
            await auth.refreshProfile(api: api)
        } catch {
            avatarUploadError = (error as? CustomerAPIError)?.localizedDescription ?? error.localizedDescription
        }
    }
}

private struct CustomerChatEmptyState: View {
    let isAI: Bool

    var body: some View {
        PAXContentUnavailableView(
            String(localized: "Start a conversation"),
            systemImage: "message.fill",
            description: Text(
                isAI
                    ? String(localized: "Ask a question about your project, order, or service. Our assistant is here to help.")
                    : String(localized: "Send a message to continue your conversation with our team.")
            )
        )
        .padding(.horizontal, 24)
    }
}

private struct CustomerChatTypingIndicator: View {
    let label: String

    var body: some View {
        HStack(spacing: 8) {
            ProgressView()
                .controlSize(.small)
            Text(label)
                .font(.caption)
                .foregroundStyle(.secondary)
            Spacer(minLength: 24)
        }
        .padding(.horizontal, 4)
        .accessibilityLabel(label)
    }
}
