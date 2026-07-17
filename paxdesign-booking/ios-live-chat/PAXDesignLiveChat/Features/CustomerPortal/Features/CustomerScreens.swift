import SwiftUI

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
        .navigationTitle("PAXDesign")
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
                    ProgressView(String(localized: "Loading dashboard…"))
                        .padding(.top, 48)
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
                                    NavigationLink(String(localized: "Browse Services")) { CustomerServicesView() }
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
                                    Text(String(localized: "Request a service on our website to get started."))
                                        .foregroundStyle(PAXTheme.textSecondary)
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
                                    navigation.selectedTab = .account
                                    navigation.accountPath = [CustomerPortalDestination(kind: .files)]
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
            .task { await load() }
            .onChange(of: scenePhase) { phase in
                if phase == .active { Task { await load() } }
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

    private var isHumanQueue: Bool {
        guard let handler = poll?.handler else { return false }
        return handler == "admin" || handler == "live_request"
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
                    ScrollViewReader { proxy in
                        ScrollView {
                            if isLoading && displayMessages.isEmpty {
                                ProgressView(String(localized: "Loading chat…"))
                                    .frame(maxWidth: .infinity)
                                    .padding(.top, 48)
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
                    chatComposer
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
                    NavigationLink(String(localized: "History")) { CustomerConversationsView() }
                }
                ToolbarItemGroup(placement: .keyboard) {
                    Spacer()
                    Button(String(localized: "Done")) { isInputFocused = false }
                }
            }
            .overlay(alignment: .top) {
                if let error {
                    Text(error)
                        .font(.footnote)
                        .foregroundStyle(.red)
                        .padding(8)
                        .multilineTextAlignment(.center)
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
        !draft.trimmingCharacters(in: .whitespacesAndNewlines).isEmpty && !isSending && network.isConnected
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
        if full && poll == nil { isLoading = true }
        defer { isLoading = false }
        do {
            let since = full ? 0 : lastSeq
            let next = try await api.fetchChatMessages(sessionID: poll?.session_id, since: since, full: full)
            if full || poll == nil {
                poll = next
            } else if let incoming = next.messages, !incoming.isEmpty {
                var merged = poll?.messages ?? []
                let existing = Set(merged.map(\.seq))
                for msg in incoming where !existing.contains(msg.seq) { merged.append(msg) }
                poll = CustomerChatPoll(
                    session_id: next.session_id ?? poll?.session_id,
                    handler: next.handler ?? poll?.handler,
                    messages: merged.sorted { $0.seq < $1.seq },
                    message_count: next.message_count,
                    last_preview: next.last_preview,
                    admin_typing: next.admin_typing ?? poll?.admin_typing,
                    user_typing: next.user_typing ?? poll?.user_typing,
                    other_read_seq: next.other_read_seq ?? poll?.other_read_seq
                )
            } else if let current = poll {
                poll = CustomerChatPoll(
                    session_id: current.session_id,
                    handler: next.handler ?? current.handler,
                    messages: current.messages,
                    message_count: current.message_count,
                    last_preview: current.last_preview,
                    admin_typing: next.admin_typing ?? current.admin_typing,
                    user_typing: next.user_typing ?? current.user_typing,
                    other_read_seq: next.other_read_seq ?? current.other_read_seq
                )
            }
            if let maxSeq = poll?.messages?.map(\.seq).max() { lastSeq = max(lastSeq, maxSeq) }
            error = nil
        } catch {
            self.error = friendlyChatError(error)
        }
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
        pollTask = Task {
            while !Task.isCancelled {
                try? await Task.sleep(nanoseconds: 1_500_000_000)
                if network.isConnected { await refresh(full: false) }
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
        guard !text.isEmpty else { return }
        isSending = true
        error = nil
        notice = nil
        defer { isSending = false }
        do {
            let response = try await api.sendChatMessage(text, sessionID: poll?.session_id)
            draft = ""
            isInputFocused = false
            if let handler = response.handler {
                poll = CustomerChatPoll(
                    session_id: response.session_id,
                    handler: handler,
                    messages: poll?.messages,
                    message_count: poll?.message_count,
                    last_preview: poll?.last_preview,
                    admin_typing: poll?.admin_typing,
                    user_typing: poll?.user_typing,
                    other_read_seq: poll?.other_read_seq
                )
            }
            if let noticeText = response.notice, !noticeText.isEmpty {
                notice = noticeText
            }
            await refresh(full: true)
            PAXHaptics.light()
        } catch {
            self.error = friendlyChatError(error)
            PAXHaptics.warning()
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

struct CustomerServicesView: View {
    @EnvironmentObject private var api: CustomerAPIClient
    @State private var response: CustomerServicesResponse?
    @State private var search = ""
    @State private var selectedCategory = ""
    @State private var error: String?
    @State private var isLoading = true

    var body: some View {
        NavigationStack {
            Group {
                if isLoading && response == nil {
                    ProgressView(String(localized: "Loading services…"))
                } else if let error {
                    PAXContentUnavailableView(String(localized: "Services unavailable"), systemImage: "exclamationmark.triangle", description: Text(error))
                } else if let response {
                    ScrollView {
                        LazyVStack(alignment: .leading, spacing: CustomerPortalDesign.sectionSpacing) {
                            if !response.categories.isEmpty {
                                ScrollView(.horizontal, showsIndicators: false) {
                                    HStack(spacing: 10) {
                                        categoryChip("", title: String(localized: "All"))
                                        ForEach(response.categories) { category in
                                            categoryChip(category.slug, title: category.name)
                                        }
                                    }
                                    .padding(.horizontal)
                                }
                            }
                            ForEach(filteredServices(response.services)) { service in
                                NavigationLink {
                                    CustomerServiceDetailView(slug: service.slug)
                                } label: {
                                    CustomerServiceCard(service: service)
                                }
                                .buttonStyle(.plain)
                            }
                        }
                        .padding(.vertical)
                    }
                }
            }
            .background(PAXBackground())
            .navigationTitle(String(localized: "Services"))
            .searchable(text: $search, prompt: String(localized: "Search services"))
            .refreshable { await load(force: true) }
            .task(id: "\(search)|\(selectedCategory)") { await load(force: false) }
        }
    }

    private func categoryChip(_ slug: String, title: String) -> some View {
        Button(title) { selectedCategory = slug }
            .font(.subheadline.weight(.medium))
            .padding(.horizontal, 14)
            .padding(.vertical, 8)
            .background(selectedCategory == slug ? PAXTheme.accent : PAXTheme.accentSoft)
            .foregroundStyle(selectedCategory == slug ? Color.white : PAXTheme.textPrimary)
            .clipShape(Capsule())
    }

    private func filteredServices(_ services: [CustomerServicesResponse.Service]) -> [CustomerServicesResponse.Service] {
        var list = services
        if !selectedCategory.isEmpty {
            list = list.filter { $0.category == selectedCategory }
        }
        let query = search.trimmingCharacters(in: .whitespacesAndNewlines).lowercased()
        guard !query.isEmpty else { return list }
        return list.filter {
            $0.name.lowercased().contains(query) || $0.description.lowercased().contains(query)
        }
    }

    private func load(force: Bool) async {
        if response == nil || force { isLoading = true }
        error = nil
        do {
            response = try await api.fetchServices(
                search: search.isEmpty ? nil : search,
                category: selectedCategory.isEmpty ? nil : selectedCategory
            )
        } catch {
            self.error = (error as? CustomerAPIError)?.localizedDescription ?? error.localizedDescription
        }
        isLoading = false
    }
}

private struct CustomerServiceCard: View {
    let service: CustomerServicesResponse.Service

    var body: some View {
        CustomerPortalCard {
            HStack(alignment: .top, spacing: 14) {
                if let imageURL = service.image_url, let url = URL(string: imageURL) {
                    AsyncImage(url: url) { phase in
                        switch phase {
                        case .success(let image):
                            image.resizable().scaledToFill()
                        default:
                            CustomerServiceIconView(iconKey: service.icon_key ?? service.slug)
                        }
                    }
                    .frame(width: 56, height: 56)
                    .clipShape(RoundedRectangle(cornerRadius: 14, style: .continuous))
                } else {
                    CustomerServiceIconView(iconKey: service.icon_key ?? service.slug, size: 56)
                }
                VStack(alignment: .leading, spacing: 6) {
                    HStack {
                        Text(service.name)
                            .font(.headline)
                            .foregroundStyle(PAXTheme.textPrimary)
                        if service.featured {
                            Text(String(localized: "Popular"))
                                .font(.caption2.weight(.semibold))
                                .padding(.horizontal, 8)
                                .padding(.vertical, 3)
                                .background(PAXTheme.accentSoft)
                                .clipShape(Capsule())
                        }
                    }
                    Text(service.description)
                        .font(.subheadline)
                        .foregroundStyle(PAXTheme.textSecondary)
                        .lineLimit(3)
                        .multilineTextAlignment(.leading)
                }
            }
        }
        .padding(.horizontal)
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
                            Text(service.description)
                                .font(.body)
                                .foregroundStyle(PAXTheme.textPrimary)
                        }
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

                    if let orderURL = service.order_url.flatMap({ URL(string: $0) }) {
                        CustomerSafariLink(
                            title: String(localized: "Request on Website"),
                            url: orderURL
                        )
                    }
                }
                .padding()
            } else if let error {
                PAXContentUnavailableView(String(localized: "Service unavailable"), systemImage: "exclamationmark.triangle", description: Text(error))
                    .padding(.top, 40)
            } else {
                ProgressView().padding(.top, 40)
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

    var body: some View {
        NavigationStack {
            List {
                if let profile = auth.profile {
                    Section(String(localized: "Profile")) {
                        LabeledContent(String(localized: "Name"), value: profile.display_name)
                        LabeledContent(String(localized: "Email"), value: profile.email)
                        LabeledContent(String(localized: "Verified"), value: profile.verified ? String(localized: "Yes") : String(localized: "No"))
                    }
                }
                Section {
                    Button(String(localized: "Sign Out"), role: .destructive) {
                        appAuth.logout()
                    }
                }
                Section {
                    Link(String(localized: "Privacy Policy"), destination: URL(string: "https://paxdesign.at/datenschutz/")!)
                    Link(String(localized: "Terms"), destination: URL(string: "https://paxdesign.at/agb/")!)
                    Link(String(localized: "Contact Support"), destination: URL(string: "https://paxdesign.at/kontakt/")!)
                }
            }
            .navigationTitle(String(localized: "Account"))
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
