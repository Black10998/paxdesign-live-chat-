import SwiftUI

struct CustomerLoginView: View {
    @EnvironmentObject private var auth: CustomerAuthStore
    @EnvironmentObject private var api: CustomerAPIClient
    @State private var isLoading = false

    var body: some View {
        NavigationStack {
            Form {
                Section(String(localized: "Website")) {
                    TextField(String(localized: "Site URL"), text: $auth.siteURL)
                        .textInputAutocapitalization(.never)
                        .keyboardType(.URL)
                }
                Section(String(localized: "Account")) {
                    TextField(String(localized: "Email or username"), text: $auth.username)
                        .textInputAutocapitalization(.never)
                        .keyboardType(.emailAddress)
                    SecureField(String(localized: "Application Password"), text: $auth.appPassword)
                }
                if let error = auth.errorMessage {
                    Section {
                        Text(error).foregroundStyle(.red)
                    }
                }
                Section {
                    Button(isLoading ? String(localized: "Signing in…") : String(localized: "Sign In")) {
                        Task {
                            isLoading = true
                            await auth.login(api: api)
                            isLoading = false
                        }
                    }
                    .disabled(isLoading)
                }
                Section {
                    Text(String(localized: "Use the same PAXDesign account as the website. Create an Application Password in WordPress under Users → Profile."))
                        .font(.footnote)
                        .foregroundStyle(.secondary)
                }
            }
            .navigationTitle("PAXDesign")
        }
    }
}

struct CustomerDashboardView: View {
    @EnvironmentObject private var api: CustomerAPIClient
    @State private var dashboard: CustomerDashboard?
    @State private var error: String?
    @State private var isLoading = true

    var body: some View {
        NavigationStack {
            Group {
                if isLoading {
                    ProgressView(String(localized: "Loading dashboard…"))
                } else if let error {
                    ContentUnavailableView(String(localized: "Unable to load"), systemImage: "wifi.exclamationmark", description: Text(error))
                } else if let dashboard {
                    List {
                        Section(String(localized: "Conversation")) {
                            if let preview = dashboard.chat?.last_preview, !preview.isEmpty {
                                Text(preview)
                            } else {
                                Text(String(localized: "No messages yet.")).foregroundStyle(.secondary)
                            }
                        }
                        Section(String(localized: "Active Projects")) {
                            if let projects = dashboard.projects_active, !projects.isEmpty {
                                ForEach(projects, id: \.id) { project in
                                    NavigationLink {
                                        CustomerProjectDetailView(projectId: project.id)
                                    } label: {
                                        HStack {
                                            Text(project.title)
                                            Spacer()
                                            Text("\(project.progress)%").foregroundStyle(.secondary)
                                        }
                                    }
                                }
                            } else {
                                Text(String(localized: "No active projects.")).foregroundStyle(.secondary)
                            }
                        }
                        Section(String(localized: "Recent Requests")) {
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
                                }
                            } else {
                                Text(String(localized: "No service requests yet.")).foregroundStyle(.secondary)
                            }
                        }
                        if let unread = dashboard.unread_count, unread > 0 {
                            Section(String(localized: "Notifications")) {
                                NavigationLink {
                                    CustomerNotificationsView()
                                } label: {
                                    Text(String(localized: "\(unread) unread notifications"))
                                }
                            }
                        }
                        if let news = dashboard.news, !news.isEmpty {
                            Section(String(localized: "News")) {
                                ForEach(news, id: \.slug) { item in
                                    NavigationLink {
                                        CustomerNewsDetailView(slug: item.slug)
                                    } label: {
                                        VStack(alignment: .leading, spacing: 4) {
                                            Text(item.title).font(.headline)
                                            if let excerpt = item.excerpt, !excerpt.isEmpty {
                                                Text(excerpt).font(.subheadline).foregroundStyle(.secondary)
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
            .navigationTitle(String(localized: "Dashboard"))
            .task { await load() }
            .refreshable { await load() }
        }
    }

    private func load() async {
        isLoading = dashboard == nil
        error = nil
        do {
            dashboard = try await api.fetchDashboard()
        } catch {
            self.error = error.localizedDescription
        }
        isLoading = false
    }
}

struct CustomerChatView: View {
    @EnvironmentObject private var api: CustomerAPIClient
    @State private var poll: CustomerChatPoll?
    @State private var draft = ""
    @State private var error: String?
    @State private var isSending = false
    @State private var streamingAssistant = ""

    private var isHumanQueue: Bool {
        guard let handler = poll?.handler else { return false }
        return handler == "admin" || handler == "live_request"
    }

    var body: some View {
        NavigationStack {
            VStack(spacing: 0) {
                ScrollViewReader { proxy in
                    ScrollView {
                        LazyVStack(alignment: .leading, spacing: 12) {
                            ForEach(displayMessages, id: \.id) { message in
                                VStack(alignment: message.role == "user" ? .trailing : .leading, spacing: 4) {
                                    if let name = message.sender_name, !name.isEmpty, message.role != "user" {
                                        Text(name).font(.caption).foregroundStyle(.secondary)
                                    }
                                    Text(message.content)
                                        .padding(10)
                                        .background(message.role == "user" ? Color.accentColor.opacity(0.15) : Color(.secondarySystemBackground))
                                        .clipShape(RoundedRectangle(cornerRadius: 12))
                                }
                                .frame(maxWidth: .infinity, alignment: message.role == "user" ? .trailing : .leading)
                                .id(message.id)
                            }
                            if !streamingAssistant.isEmpty {
                                Text(streamingAssistant)
                                    .padding(10)
                                    .background(Color(.secondarySystemBackground))
                                    .clipShape(RoundedRectangle(cornerRadius: 12))
                                    .frame(maxWidth: .infinity, alignment: .leading)
                                    .id("streaming")
                            }
                        }
                        .padding()
                    }
                    .onChange(of: displayMessages.count) { _, _ in
                        scrollToBottom(proxy: proxy)
                    }
                }
                Divider()
                HStack {
                    TextField(String(localized: "Message"), text: $draft, axis: .vertical)
                        .textFieldStyle(.roundedBorder)
                        .lineLimit(1...4)
                    Button {
                        Task { await send() }
                    } label: {
                        Image(systemName: isSending ? "hourglass" : "paperplane.fill")
                    }
                    .disabled(draft.trimmingCharacters(in: .whitespacesAndNewlines).isEmpty || isSending)
                }
                .padding()
            }
            .navigationTitle(String(localized: "Chat"))
            .overlay(alignment: .top) {
                if let error {
                    Text(error).font(.footnote).foregroundStyle(.red).padding(8)
                }
            }
            .task { await refresh() }
            .refreshable { await refresh() }
        }
    }

    private var displayMessages: [CustomerChatPoll.ChatMessage] {
        poll?.messages ?? []
    }

    private func scrollToBottom(proxy: ScrollViewProxy) {
        if !streamingAssistant.isEmpty {
            withAnimation { proxy.scrollTo("streaming", anchor: .bottom) }
        } else if let last = displayMessages.last?.id {
            withAnimation { proxy.scrollTo(last, anchor: .bottom) }
        }
    }

    private func refresh() async {
        do {
            poll = try await api.fetchChatMessages(sessionID: poll?.session_id, since: 0, full: true)
            error = nil
        } catch {
            self.error = error.localizedDescription
        }
    }

    private func send() async {
        let text = draft.trimmingCharacters(in: .whitespacesAndNewlines)
        guard !text.isEmpty else { return }
        isSending = true
        streamingAssistant = ""
        defer { isSending = false }
        do {
            if isHumanQueue {
                _ = try await api.sendChatMessage(text, sessionID: poll?.session_id)
            } else {
                try await api.streamChatMessage(text, sessionID: poll?.session_id) { event in
                    if event.type == "text", let chunk = event.text {
                        streamingAssistant += chunk
                    }
                    if event.type == "done", let message = event.message {
                        streamingAssistant = message.content
                    }
                }
                streamingAssistant = ""
            }
            draft = ""
            await refresh()
            error = nil
        } catch {
            self.error = error.localizedDescription
        }
    }
}

struct CustomerServicesView: View {
    @EnvironmentObject private var api: CustomerAPIClient
    @State private var response: CustomerServicesResponse?
    @State private var search = ""
    @State private var error: String?

    var body: some View {
        NavigationStack {
            Group {
                if let response {
                    List(response.services) { service in
                        NavigationLink {
                            CustomerServiceDetailView(slug: service.slug)
                        } label: {
                            VStack(alignment: .leading, spacing: 6) {
                                Text(service.name).font(.headline)
                                Text(service.description).font(.subheadline).foregroundStyle(.secondary).lineLimit(3)
                            }
                            .padding(.vertical, 4)
                        }
                    }
                } else if let error {
                    ContentUnavailableView(String(localized: "Services unavailable"), systemImage: "exclamationmark.triangle", description: Text(error))
                } else {
                    ProgressView()
                }
            }
            .navigationTitle(String(localized: "Services"))
            .searchable(text: $search)
            .task(id: search) { await load() }
        }
    }

    private func load() async {
        do {
            response = try await api.fetchServices(search: search)
        } catch {
            self.error = error.localizedDescription
        }
    }
}

struct CustomerServiceDetailView: View {
    @EnvironmentObject private var api: CustomerAPIClient
    let slug: String
    @State private var service: CustomerServiceDetail?

    var body: some View {
        Group {
            if let service {
                List {
                    Section {
                        Text(service.description)
                    }
                    if let features = service.features, !features.isEmpty {
                        Section(String(localized: "Features")) {
                            ForEach(features, id: \.self) { f in Text(f) }
                        }
                    }
                    Section {
                        NavigationLink(String(localized: "Request this service")) {
                            CustomerCreateOrderView(preselectedSlug: slug)
                        }
                    }
                }
            } else {
                ProgressView()
            }
        }
        .navigationTitle(service?.name ?? String(localized: "Service"))
        .task {
            service = try? await api.fetchService(slug: slug)
        }
    }
}

struct CustomerProfileView: View {
    @EnvironmentObject private var auth: CustomerAuthStore

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
                        auth.logout()
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
