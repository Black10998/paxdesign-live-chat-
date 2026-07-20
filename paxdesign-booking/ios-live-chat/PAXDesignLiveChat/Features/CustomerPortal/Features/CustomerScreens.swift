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
        ScrollView {
            VStack(spacing: 20) {
                PAXAuthHeroView(
                    style: .animatedLogo,
                    title: String(localized: "PAXDesign"),
                    subtitle: String(localized: "Sign in to your customer account."),
                    markWidth: 120
                )
                .padding(.top, 12)

                if !network.isConnected {
                    PAXLabel(String(localized: "You are offline. Connect to sign in."), icon: "wifi.slash")
                        .foregroundStyle(.orange)
                        .frame(maxWidth: .infinity, alignment: .leading)
                }

                VStack(spacing: 14) {
                    PAXField(
                        title: String(localized: "Email or username"),
                        icon: "person",
                        text: $auth.username,
                        keyboardType: .emailAddress
                    )
                    PAXField(
                        title: String(localized: "Password"),
                        icon: "lock",
                        text: $auth.appPassword,
                        isSecure: true
                    )
                }

                if let error = auth.errorMessage {
                    Text(error)
                        .font(.footnote)
                        .foregroundStyle(.red)
                        .multilineTextAlignment(.center)
                        .frame(maxWidth: .infinity)
                        .accessibilityLabel(error)
                }

                PAXPrimaryButton(
                    title: isLoading ? String(localized: "Signing in…") : String(localized: "Sign In"),
                    isLoading: isLoading
                ) {
                    Task {
                        isLoading = true
                        await auth.login(api: api)
                        isLoading = false
                    }
                }
                .disabled(isLoading || !network.isConnected)

                VStack(spacing: 10) {
                    if let onRegister {
                        Button(String(localized: "Create account")) { onRegister() }
                            .font(.subheadline)
                    }
                    if let onForgot {
                        Button(String(localized: "Forgot password?")) { onForgot() }
                            .font(.subheadline)
                            .foregroundStyle(.secondary)
                    }
                }
            }
            .padding(.horizontal, 20)
            .padding(.bottom, 24)
        }
        .scrollDismissesKeyboard(.interactively)
        .paxScreenBackground()
        .navigationTitle(String(localized: "Sign in"))
        .navigationBarTitleDisplayMode(.inline)
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
                                        PAXLabel(String(localized: "\(unread) unread notifications"), icon: "bell.badge.fill")
                                            .font(.headline)
                                        Spacer()
                                        PAXIcon("chevron.right", size: .inline, emphasis: .secondary)
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
                                                    Text(project.status).font(.caption).foregroundStyle(PAXTheme.textSecondary)
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
                                                Text(order.status).foregroundStyle(PAXTheme.textSecondary)
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
                                                        Text(excerpt).font(.caption).foregroundStyle(PAXTheme.textSecondary).lineLimit(2)
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
                                                    Text(excerpt).font(.subheadline).foregroundStyle(PAXTheme.textSecondary).lineLimit(2)
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
                    Task { await CustomerPushService.shared.prepareNotificationRegistration() }
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
    @EnvironmentObject private var auth: CustomerAuthStore
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
    @State private var streamTask: Task<Void, Never>?
    @State private var streamSince = 0
    @State private var showImagePicker = false
    @State private var isRecordingVoice = false
    @State private var voiceRecorder = CustomerVoiceRecorder()
    @State private var showLocationSheet = false
    @State private var typingTask: Task<Void, Never>?
    @State private var lastTypingSent = false
    @State private var recovery: CustomerChatSessionRecovery.Action?
    @State private var isRecovering = false
    @State private var pollingSuspended = false
    @State private var pollIntervalNs: UInt64 = 700_000_000
    @State private var eventStreamActive = false
    @State private var showDocumentPicker = false
    @State private var showCameraPicker = false
    @State private var showAuth = false
    @State private var authMode: CustomerChatAuthMode = .login

    private enum CustomerChatAuthMode {
        case login, register
    }

    private let minPollIntervalNs: UInt64 = 700_000_000
    private let maxPollIntervalNs: UInt64 = 8_000_000_000

    private var isHumanQueue: Bool {
        guard let handler = poll?.handler else { return false }
        return handler == "admin" || handler == "live_request"
    }

    var body: some View {
        NavigationStack {
            ZStack {
                PAXBackground()
                if auth.isAuthenticated {
                    chatContent
                } else {
                    CustomerChatGuestAuthPanel(
                        onSignIn: {
                            authMode = .login
                            showAuth = true
                        },
                        onRegister: {
                            authMode = .register
                            showAuth = true
                        }
                    )
                }
            }
            .navigationTitle(String(localized: "Chat"))
            .toolbar {
                ToolbarItem(placement: .topBarTrailing) {
                    if auth.isAuthenticated {
                        HStack(spacing: 10) {
                            CustomerNavAvatarButton()
                            NavigationLink(String(localized: "History")) { CustomerConversationsView() }
                        }
                    }
                }
            }
            .sheet(isPresented: $showAuth) {
                NavigationStack {
                    Group {
                        switch authMode {
                        case .login:
                            CustomerLoginView(
                                onRegister: { authMode = .register },
                                onForgot: { }
                            )
                        case .register:
                            CustomerRegisterView(onDone: { _ in authMode = .login })
                        }
                    }
                    .environmentObject(auth)
                    .environmentObject(api)
                }
                .presentationDetents([.large])
                .presentationDragIndicator(.visible)
            }
            .onChange(of: auth.isAuthenticated) { signedIn in
                guard signedIn else { return }
                Task {
                    if let session = try? await api.fetchChatSession() {
                        poll = CustomerChatPoll(
                            session_id: session.session_id,
                            handler: session.handler,
                            messages: [],
                            message_count: nil,
                            last_preview: nil
                        )
                    }
                    await refresh(full: true)
                    startPolling()
                    startEventStream()
                }
            }
        }
        // Chat owns its bottom composer; do not add shell tab-bar clearance here.
        // Nested bottom safeAreaInset would fight the customer tab bar and hide the composer.
    }

    private var chatContent: some View {
        VStack(spacing: 0) {
            if !network.isConnected {
                Text(String(localized: "Offline — messages will send when you reconnect."))
                    .font(.caption).foregroundStyle(.orange).padding(8)
                    .frame(maxWidth: .infinity).background(Color.orange.opacity(0.12))
            }
            if let notice, !notice.isEmpty {
                Text(notice)
                    .font(.caption)
                    .foregroundStyle(PAXTheme.textSecondary)
                    .padding(.horizontal, 12)
                    .padding(.vertical, 8)
                    .frame(maxWidth: .infinity)
                    .background(PAXTheme.accentSoft)
            }
            if let recovery, recovery.issue != .closed {
                CustomerChatRecoveryBanner(
                    action: recovery,
                    isRecovering: isRecovering,
                    onRetry: { Task { await retryAfterRecovery() } }
                )
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
                            Color.clear.frame(height: 1).id("chat-bottom")
                        }
                        .padding()
                    }
                }
                .scrollDismissesKeyboard(.interactively)
                .onChange(of: displayMessages.count) { _ in scrollToBottom(proxy: proxy, animated: true) }
                .onChange(of: lastSeq) { _ in scrollToBottom(proxy: proxy, animated: true) }
                .onChange(of: poll?.admin_typing) { _ in scrollToBottom(proxy: proxy, animated: true) }
                .onChange(of: isInputFocused) { focused in
                    if focused { scrollToBottom(proxy: proxy, animated: true) }
                }
            }
            .frame(maxWidth: .infinity, maxHeight: .infinity)

            // Sit in the layout above the shell tab-bar safe area (not a nested bottom inset).
            chatComposer
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
            .sheet(isPresented: $showCameraPicker) {
                CustomerCameraPicker { data in Task { await sendPhoto(data) } }
            }
            .sheet(isPresented: $showDocumentPicker) {
                CustomerDocumentPicker { url in Task { await sendDocument(url) } }
            }
            .sheet(isPresented: $showLocationSheet) {
                CustomerLocationShareSheet { lat, lng, label in
                    Task { await sendLocation(lat: lat, lng: lng, label: label) }
                }
            }
            .task {
                guard auth.isAuthenticated else { return }
                if let session = try? await api.fetchChatSession() {
                    poll = CustomerChatPoll(
                        session_id: session.session_id,
                        handler: session.handler,
                        messages: [],
                        message_count: nil,
                        last_preview: nil
                    )
                } else if let initialSessionID, !initialSessionID.isEmpty {
                    poll = CustomerChatPoll(session_id: initialSessionID, handler: nil, messages: [], message_count: nil, last_preview: nil)
                }
                await refresh(full: true)
                startPolling()
                startEventStream()
            }
            .onDisappear {
                pollTask?.cancel()
                streamTask?.cancel()
                typingTask?.cancel()
                if poll?.session_id != nil {
                    AppRefreshPolicy.setActiveSession(nil)
                }
                Task { try? await api.sendChatTyping(sessionID: poll?.session_id, stop: true) }
            }
            .onAppear {
                AppRefreshPolicy.setActiveSession(poll?.session_id)
            }
            .onChange(of: scenePhase) { phase in
                if phase == .active, auth.isAuthenticated {
                    Task { await refresh(full: false) }
                }
            }
            .refreshable { await refresh(full: true) }
    }

    private var chatComposer: some View {
        VStack(spacing: 0) {
            Divider().opacity(0.35)
            HStack(alignment: .bottom, spacing: 8) {
                Menu {
                    Button {
                        guard isHumanQueue else { notice = String(localized: "Attachments are available during human support."); return }
                        showCameraPicker = true
                    } label: {
                        PAXLabel(String(localized: "Camera"), icon: "camera")
                    }
                    Button {
                        guard isHumanQueue else { notice = String(localized: "Attachments are available during human support."); return }
                        showImagePicker = true
                    } label: {
                        PAXLabel(String(localized: "Photo Library"), icon: "photo.on.rectangle")
                    }
                    Button {
                        guard isHumanQueue else { notice = String(localized: "Attachments are available during human support."); return }
                        showDocumentPicker = true
                    } label: {
                        PAXLabel(String(localized: "Files"), icon: "doc")
                    }
                    Button {
                        guard isHumanQueue else { notice = String(localized: "Attachments are available during human support."); return }
                        Task { await toggleVoice() }
                    } label: {
                        PAXLabel(isRecordingVoice ? String(localized: "Stop recording") : String(localized: "Voice message"), icon: "mic")
                    }
                    Button {
                        guard isHumanQueue else { notice = String(localized: "Attachments are available during human support."); return }
                        showLocationSheet = true
                    } label: {
                        PAXLabel(String(localized: "Location"), icon: "location")
                    }
                } label: {
                    PAXIcon("plus.circle.fill", size: .hero, tint: PAXTheme.accent)
                }
                TextField(String(localized: "Message"), text: $draft, axis: .vertical)
                    .lineLimit(1...5)
                    .padding(.horizontal, 12)
                    .padding(.vertical, 10)
                    .background(PAXTheme.surfaceElevated)
                    .clipShape(RoundedRectangle(cornerRadius: 18, style: .continuous))
                    .focused($isInputFocused)
                    .submitLabel(.send)
                    .onSubmit { if canSend { Task { await send() } } }
                    .onChange(of: draft) { _ in scheduleTypingPing() }
                    .layoutPriority(1)
                Button { Task { await send() } } label: {
                    PAXIcon(isSending ? "hourglass" : "arrow.up.circle.fill", size: .display, tint: canSend ? PAXTheme.accent : PAXTheme.textTertiary)
                        .frame(width: 44, height: 44)
                }
                .disabled(!canSend)
                .accessibilityLabel(String(localized: "Send"))
            }
            .padding(.leading, 12)
            .padding(.trailing, 8)
            .padding(.vertical, 10)
            .background(.ultraThinMaterial)
        }
    }

    private var canSend: Bool {
        !draft.trimmingCharacters(in: .whitespacesAndNewlines).isEmpty
            && !isSending
            && network.isConnected
    }

    private var displayMessages: [CustomerChatPoll.ChatMessage] {
        CustomerChatSessionRecovery.visibleMessages(poll?.messages ?? [])
    }

    private func scrollToBottom(proxy: ScrollViewProxy, animated: Bool = true) {
        let scroll = {
            if poll?.admin_typing == true {
                proxy.scrollTo("typing", anchor: .bottom)
            } else {
                proxy.scrollTo("chat-bottom", anchor: .bottom)
            }
        }
        if animated {
            withAnimation(.easeOut(duration: 0.25), scroll)
        } else {
            scroll()
        }
    }

    private func refresh(full: Bool = false) async -> Bool {
        if isSending && !full { return false }
        if pollingSuspended && !full { return false }
        if full && poll == nil { isLoading = true }
        defer { isLoading = false }
        let previousCount = poll?.messages?.count ?? 0
        do {
            if poll?.handler == "closed" {
                await autoRenewSession(preserveDraft: nil)
            }
            let since = full ? 0 : lastSeq
            let next = try await api.fetchChatMessages(sessionID: poll?.session_id, since: since, full: full)
            applyPollUpdate(next, full: full)
            if next.handler == "closed" {
                await autoRenewSession(preserveDraft: nil)
                if let reopened = try? await api.fetchChatMessages(sessionID: poll?.session_id, since: 0, full: full) {
                    applyPollUpdate(reopened, full: full)
                }
            } else {
                recovery = nil
                pollingSuspended = false
                error = nil
            }
            if let pollNotice = next.notice, !pollNotice.isEmpty, next.handler != "closed" {
                notice = pollNotice
            } else if next.handler != "closed" {
                notice = nil
            }
            AppRefreshPolicy.setActiveSession(poll?.session_id)
            let newCount = poll?.messages?.count ?? 0
            return newCount > previousCount
        } catch {
            await handleChatError(error, savedDraft: nil, duringSend: false)
            return false
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
            let renewed = try await api.renewChatSession(closedSessionID: poll?.session_id, newConversation: false)
            poll = CustomerChatPoll(
                session_id: renewed.session_id,
                handler: renewed.handler,
                messages: poll?.messages,
                message_count: poll?.message_count,
                last_preview: poll?.last_preview
            )
            lastSeq = poll?.messages?.map(\.seq).max() ?? 0
            streamSince = 0
            recovery = nil
            pollingSuspended = false
            error = nil
            startPolling()
            startEventStream()
            await refresh(full: true)
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
        startEventStream()
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
        pollIntervalNs = minPollIntervalNs
        pollTask = Task {
            while !Task.isCancelled {
                let interval = eventStreamActive ? max(pollIntervalNs, 5_000_000_000) : pollIntervalNs
                try? await Task.sleep(nanoseconds: interval)
                guard network.isConnected, !pollingSuspended else { continue }
                let hadNew = await refresh(full: false)
                if hadNew {
                    pollIntervalNs = minPollIntervalNs
                } else {
                    let step: UInt64 = isHumanQueue ? 500_000_000 : 800_000_000
                    pollIntervalNs = min(pollIntervalNs + step, maxPollIntervalNs)
                }
            }
        }
    }

    private func startEventStream() {
        streamTask?.cancel()
        guard poll?.session_id != nil else { return }
        streamTask = Task { @MainActor in
            eventStreamActive = true
            defer { eventStreamActive = false }
            while !Task.isCancelled {
                guard network.isConnected, !pollingSuspended else {
                    try? await Task.sleep(nanoseconds: 1_000_000_000)
                    continue
                }
                let sessionID = poll?.session_id
                let since = streamSince
                do {
                    try await api.consumeCustomerChatEventStream(sessionID: sessionID, since: since) { event in
                        if event.id > 0 {
                            streamSince = max(streamSince, event.id)
                        }
                        switch event.type {
                        case "message", "message_deleted", "link_scan_updated", "handler", "typing":
                            pollIntervalNs = minPollIntervalNs
                            await refresh(full: event.type == "message" || event.type == "handler")
                        default:
                            break
                        }
                    }
                } catch {
                    if Task.isCancelled { break }
                    try? await Task.sleep(nanoseconds: 1_000_000_000)
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

    private func sendDocument(_ url: URL) async {
        guard let session = poll?.session_id else { return }
        isSending = true
        defer { isSending = false }
        do {
            let data = try Data(contentsOf: url)
            let filename = url.lastPathComponent.isEmpty ? "document" : url.lastPathComponent
            _ = try await api.uploadChatFile(sessionID: session, fileData: data, filename: filename)
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
        guard !text.isEmpty else { return }

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
                let renewed = try await api.renewChatSession(closedSessionID: poll?.session_id, newConversation: false)
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
                startEventStream()
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
        if let noticeText = response.notice, !noticeText.isEmpty, response.handler != "closed" {
            notice = noticeText
        } else if response.renewed != true {
            notice = nil
        }
        if response.renewed == true {
            recovery = nil
            pollingSuspended = false
        }
        if response.handler == "live_request" || response.handler == "admin" {
            notice = response.notice ?? notice
        }
        NotificationCenter.default.post(name: .paxChatScrollToBottom, object: nil)
    }

    private func scheduleTypingPing() {
        guard isHumanQueue else { return }
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
                                    PAXLabel(feature, icon: "checkmark.circle.fill")
                                        .font(.subheadline)
                                        .foregroundStyle(PAXTheme.textPrimary)
                                }
                            }
                        }
                    }

                    NavigationLink {
                        CustomerCreateOrderView(preselectedSlug: service.slug)
                    } label: {
                        PAXLabel(String(localized: "Start order"), icon: "plus.circle.fill")
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
    @State private var showSignOutConfirm = false

    var body: some View {
        List {
                if let profile = auth.profile {
                    Section(String(localized: "Account")) {
                        HStack(spacing: 16) {
                            CustomerProfileAvatarView(urlString: profile.avatar_url, size: 64)
                            VStack(alignment: .leading, spacing: 4) {
                                Text(profile.display_name).font(.headline)
                                Text(profile.email).font(.subheadline).foregroundStyle(PAXTheme.textSecondary)
                                Text(String(localized: "Signed in"))
                                    .font(.caption.weight(.semibold))
                                    .foregroundStyle(.green)
                            }
                        }
                        PhotosPicker(selection: $selectedAvatarItem, matching: .images) {
                            PAXLabel(
                                isUploadingAvatar ? String(localized: "Uploading…") : String(localized: "Change profile photo"),
                                icon: "camera.fill"
                            )
                        }
                        .disabled(isUploadingAvatar)
                        LabeledContent(String(localized: "Verified"), value: profile.verified ? String(localized: "Yes") : String(localized: "No"))
                    }
                }
                if let avatarUploadError {
                    Section { Text(avatarUploadError).foregroundStyle(.red) }
                }
                Section(String(localized: "Security & settings")) {
                    NavigationLink {
                        CustomerSettingsView()
                    } label: {
                        PAXLabel(String(localized: "Settings"), icon: "gearshape.fill")
                    }
                    NavigationLink {
                        AppLockSettingsView()
                    } label: {
                        PAXLabel(String(localized: "App lock"), icon: "lock.shield")
                    }
                    NavigationLink {
                        CustomerDeviceManagementView()
                    } label: {
                        PAXLabel(String(localized: "Devices"), icon: "iphone.and.arrow.forward")
                    }
                }
                Section {
                    Button(String(localized: "Sign Out"), role: .destructive) {
                        showSignOutConfirm = true
                    }
                }
                Section(String(localized: "Legal & Support")) {
                    Link(destination: PAXLegalLinks.impressum) {
                        PAXLabel(String(localized: "Impressum"), icon: "safari")
                    }
                    Link(destination: PAXLegalLinks.privacyPolicy) {
                        PAXLabel(String(localized: "Privacy Policy"), icon: "safari")
                    }
                    Link(destination: PAXLegalLinks.terms) {
                        PAXLabel(String(localized: "Terms"), icon: "safari")
                    }
                    Link(destination: PAXLegalLinks.serviceDocumentation) {
                        PAXLabel(String(localized: "Service documentation"), icon: "safari")
                    }
                    Link(destination: PAXLegalLinks.contact) {
                        PAXLabel(String(localized: "Contact"), icon: "safari")
                    }
                    Button(String(localized: "Chat with support")) {
                        navigation.openChat()
                    }
                }
            }
            .navigationTitle(String(localized: "Account"))
            .confirmationDialog(
                String(localized: "Sign out?"),
                isPresented: $showSignOutConfirm,
                titleVisibility: .visible
            ) {
                Button(String(localized: "Sign Out"), role: .destructive) {
                    appAuth.logout()
                }
                Button(String(localized: "Cancel"), role: .cancel) {}
            } message: {
                Text(String(localized: "You will need to sign in again to access your projects, orders, and messages."))
            }
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

private struct CustomerChatGuestAuthPanel: View {
    let onSignIn: () -> Void
    let onRegister: () -> Void

    var body: some View {
        VStack(spacing: 24) {
            Spacer(minLength: 0)
            PAXIcon("chats.fill", size: .display, tint: PAXTheme.accent)
            VStack(spacing: 8) {
                Text(String(localized: "Sign in to chat"))
                    .font(.title2.weight(.bold))
                    .foregroundStyle(PAXTheme.textPrimary)
                Text(String(localized: "Sign in or create a free account to message our team and keep your conversations synced."))
                    .font(.subheadline)
                    .foregroundStyle(PAXTheme.textSecondary)
                    .multilineTextAlignment(.center)
                    .padding(.horizontal, 8)
            }
            HStack(spacing: 12) {
                Button(String(localized: "Sign In"), action: onSignIn)
                    .buttonStyle(CustomerPrimaryButtonStyleModifier(style: .filled))
                    .frame(maxWidth: .infinity)
                Button(String(localized: "Create account"), action: onRegister)
                    .buttonStyle(CustomerPrimaryButtonStyleModifier(style: .tinted))
                    .frame(maxWidth: .infinity)
            }
            .padding(.horizontal, 4)
            Spacer(minLength: 0)
        }
        .padding(.horizontal, 24)
        .frame(maxWidth: .infinity, maxHeight: .infinity)
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
                .foregroundStyle(PAXTheme.textSecondary)
            Spacer(minLength: 24)
        }
        .padding(.horizontal, 4)
        .accessibilityLabel(label)
    }
}
