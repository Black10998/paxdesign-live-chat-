import SwiftUI

// MARK: - Projects

struct CustomerProjectsListView: View {
    @EnvironmentObject private var api: CustomerAPIClient
    @State private var projects: [CustomerProjectSummary] = []
    @State private var error: String?
    @State private var isLoading = true

    var body: some View {
        NavigationStack {
            Group {
                if isLoading {
                    ProgressView(String(localized: "Loading projects…"))
                } else if let error {
                    PAXContentUnavailableView(String(localized: "Projects unavailable"), systemImage: "exclamationmark.triangle", description: Text(error))
                } else if projects.isEmpty {
                    PAXContentUnavailableView(String(localized: "No projects yet"), systemImage: "folder", description: Text(String(localized: "Your active work will appear here.")))
                } else {
                    List(projects) { project in
                        NavigationLink {
                            CustomerProjectDetailView(projectId: project.id)
                        } label: {
                            VStack(alignment: .leading, spacing: 4) {
                                Text(project.title).font(.headline)
                                HStack {
                                    Text(project.status).foregroundStyle(.secondary)
                                    Spacer()
                                    Text("\(project.progress)%")
                                }.font(.subheadline)
                            }
                        }
                    }
                }
            }
            .navigationTitle(String(localized: "Projects"))
            .task { await load() }
            .refreshable { await load() }
        }
    }

    private func load() async {
        isLoading = projects.isEmpty
        error = nil
        do {
            let response = try await api.fetchProjects()
            projects = response.projects
        } catch {
            self.error = error.localizedDescription
        }
        isLoading = false
    }
}

struct CustomerProjectDetailView: View {
    @EnvironmentObject private var api: CustomerAPIClient
    let projectId: Int
    @State private var project: CustomerProjectDetail?
    @State private var error: String?

    var body: some View {
        Group {
            if let project {
                List {
                    Section {
                        Text(project.description ?? String(localized: "No description."))
                        ProgressView(value: Double(project.progress), total: 100)
                        LabeledContent(String(localized: "Status"), value: project.status)
                        LabeledContent(String(localized: "Reference"), value: project.ref)
                    }
                    if let milestones = project.milestones, !milestones.isEmpty {
                        Section(String(localized: "Milestones")) {
                            ForEach(milestones) { m in
                                VStack(alignment: .leading) {
                                    Text(m.title).font(.headline)
                                    Text(m.status).font(.caption).foregroundStyle(.secondary)
                                }
                            }
                        }
                    }
                    if let assignees = project.assignees, !assignees.isEmpty {
                        Section(String(localized: "Team")) {
                            ForEach(assignees) { a in
                                Text(a.display_name ?? a.role_label)
                            }
                        }
                    }
                    if let notes = project.notes, !notes.isEmpty {
                        Section(String(localized: "Notes")) {
                            ForEach(notes) { n in Text(n.body) }
                        }
                    }
                    if let files = project.files, !files.isEmpty {
                        Section(String(localized: "Files")) {
                            ForEach(files) { f in
                                Text(f.file_name).font(.subheadline)
                            }
                        }
                    }
                    if let activity = project.activity, !activity.isEmpty {
                        Section(String(localized: "Activity")) {
                            ForEach(activity) { a in
                                VStack(alignment: .leading) {
                                    Text(a.summary)
                                    Text(a.created_at).font(.caption).foregroundStyle(.secondary)
                                }
                            }
                        }
                    }
                }
            } else if let error {
                PAXContentUnavailableView(String(localized: "Unable to load project"), systemImage: "exclamationmark.triangle", description: Text(error))
            } else {
                ProgressView()
            }
        }
        .navigationTitle(project?.title ?? String(localized: "Project"))
        .task { await load() }
    }

    private func load() async {
        do {
            project = try await api.fetchProject(id: projectId)
        } catch {
            self.error = error.localizedDescription
        }
    }
}

// MARK: - Orders

struct CustomerOrdersListView: View {
    @EnvironmentObject private var api: CustomerAPIClient
    @State private var orders: [CustomerOrderSummary] = []
    @State private var error: String?
    @State private var isLoading = true

    var body: some View {
        NavigationStack {
            Group {
                if isLoading {
                    ProgressView()
                } else if let error {
                    PAXContentUnavailableView(String(localized: "Requests unavailable"), systemImage: "exclamationmark.triangle", description: Text(error))
                } else if orders.isEmpty {
                    PAXContentUnavailableView(String(localized: "No requests yet"), systemImage: "tray", description: Text(String(localized: "Submit a service request from the Services tab.")))
                } else {
                    List(orders) { order in
                        NavigationLink {
                            CustomerOrderDetailView(orderId: order.id)
                        } label: {
                            VStack(alignment: .leading) {
                                Text(order.service_label).font(.headline)
                                Text(order.status).foregroundStyle(.secondary)
                            }
                        }
                    }
                }
            }
            .navigationTitle(String(localized: "Requests"))
            .toolbar {
                NavigationLink(String(localized: "New request")) {
                    CustomerCreateOrderView()
                }
            }
            .task { await load() }
            .refreshable { await load() }
        }
    }

    private func load() async {
        isLoading = orders.isEmpty
        do {
            orders = try await api.fetchOrders().orders
        } catch {
            self.error = error.localizedDescription
        }
        isLoading = false
    }
}

struct CustomerOrderDetailView: View {
    @EnvironmentObject private var api: CustomerAPIClient
    let orderId: Int
    @State private var order: CustomerOrderDetail?
    @State private var error: String?

    var body: some View {
        Group {
            if let order {
                List {
                    Section {
                        LabeledContent(String(localized: "Reference"), value: order.ref)
                        LabeledContent(String(localized: "Status"), value: order.status)
                        Text(order.description ?? "")
                    }
                    if let assigned = order.assigned {
                        Section(String(localized: "Assigned")) {
                            Text(assigned.display_name)
                        }
                    }
                    if let notes = order.notes, !notes.isEmpty {
                        Section(String(localized: "Notes")) {
                            ForEach(notes) { n in Text(n.body) }
                        }
                    }
                    if let activity = order.activity, !activity.isEmpty {
                        Section(String(localized: "Activity")) {
                            ForEach(activity) { a in Text(a.summary) }
                        }
                    }
                }
            } else if let error {
                PAXContentUnavailableView(String(localized: "Unable to load request"), systemImage: "exclamationmark.triangle", description: Text(error))
            } else {
                ProgressView()
            }
        }
        .navigationTitle(order?.service_label ?? String(localized: "Request"))
        .task { await load() }
    }

    private func load() async {
        do { order = try await api.fetchOrder(id: orderId) }
        catch { self.error = error.localizedDescription }
    }
}

struct CustomerCreateOrderView: View {
    @EnvironmentObject private var api: CustomerAPIClient
    @Environment(\.dismiss) private var dismiss
    var preselectedSlug: String = ""
    @State private var services: [CustomerServicesResponse.Service] = []
    @State private var selectedSlug = ""
    @State private var description = ""
    @State private var error: String?
    @State private var isSubmitting = false

    var body: some View {
        Form {
            Picker(String(localized: "Service"), selection: $selectedSlug) {
                Text(String(localized: "Select a service")).tag("")
                ForEach(services) { s in
                    Text(s.name).tag(s.slug)
                }
            }
            Section(String(localized: "Details")) {
                TextField(String(localized: "Describe your request"), text: $description, axis: .vertical)
                    .lineLimit(3...8)
            }
            if let error {
                Section { Text(error).foregroundStyle(.red) }
            }
            Section {
                Button(isSubmitting ? String(localized: "Submitting…") : String(localized: "Submit request")) {
                    Task { await submit() }
                }.disabled(isSubmitting || selectedSlug.isEmpty || description.trimmingCharacters(in: .whitespacesAndNewlines).isEmpty)
            }
        }
        .navigationTitle(String(localized: "New request"))
        .task {
            if let response = try? await api.fetchServices() {
                services = response.services
                if !preselectedSlug.isEmpty {
                    selectedSlug = preselectedSlug
                } else if selectedSlug.isEmpty {
                    selectedSlug = services.first?.slug ?? ""
                }
            }
        }
    }

    private func submit() async {
        isSubmitting = true
        defer { isSubmitting = false }
        do {
            _ = try await api.createOrder(serviceSlug: selectedSlug, description: description)
            dismiss()
        } catch {
            self.error = error.localizedDescription
        }
    }
}

// MARK: - News & Notifications

struct CustomerNewsListView: View {
    @EnvironmentObject private var api: CustomerAPIClient
    @State private var items: [CustomerNewsItem] = []
    @State private var error: String?

    var body: some View {
        NavigationStack {
            Group {
                if items.isEmpty && error == nil {
                    ProgressView()
                } else if let error {
                    PAXContentUnavailableView(String(localized: "News unavailable"), systemImage: "newspaper", description: Text(error))
                } else {
                    List(items) { item in
                        NavigationLink(item.title) {
                            CustomerNewsDetailView(slug: item.slug)
                        }
                    }
                }
            }
            .navigationTitle(String(localized: "News"))
            .task { await load() }
            .refreshable { await load() }
        }
    }

    private func load() async {
        do { items = try await api.fetchNews().items }
        catch { self.error = error.localizedDescription }
    }
}

struct CustomerNewsDetailView: View {
    @EnvironmentObject private var api: CustomerAPIClient
    let slug: String
    @State private var item: CustomerNewsItem?

    var body: some View {
        ScrollView {
            VStack(alignment: .leading, spacing: 12) {
                if let item {
                    Text(item.title).font(.title2.bold())
                    if let body = item.body { Text(body) }
                } else {
                    ProgressView()
                }
            }.padding()
        }
        .navigationTitle(String(localized: "Announcement"))
        .task {
            item = try? await api.fetchNewsItem(slug: slug)
        }
    }
}

struct CustomerNotificationsView: View {
    @EnvironmentObject private var api: CustomerAPIClient
    @State private var response: CustomerNotificationsResponse?
    @State private var error: String?

    var body: some View {
        NavigationStack {
            Group {
                if let response {
                    List(response.items) { item in
                        VStack(alignment: .leading, spacing: 4) {
                            Text(item.title).font(.headline)
                            if let body = item.body, !body.isEmpty {
                                Text(body).font(.subheadline).foregroundStyle(.secondary)
                            }
                        }
                        .opacity(item.is_read ? 0.6 : 1)
                    }
                } else if let error {
                    PAXContentUnavailableView(String(localized: "Notifications unavailable"), systemImage: "bell.slash", description: Text(error))
                } else {
                    ProgressView()
                }
            }
            .navigationTitle(String(localized: "Notifications"))
            .task { await load() }
            .refreshable { await load() }
        }
    }

    private func load() async {
        do { response = try await api.fetchNotifications() }
        catch { self.error = error.localizedDescription }
    }
}

// MARK: - Settings

struct CustomerSettingsView: View {
    @EnvironmentObject private var api: CustomerAPIClient
    @EnvironmentObject private var auth: CustomerAuthStore
    @EnvironmentObject private var appAuth: AuthStore
    @State private var chatPref = true
    @State private var projectPref = true
    @State private var orderPref = true
    @State private var newsPref = true
    @State private var securityPref = true
    @State private var pushPref = true
    @State private var displayName = ""
    @State private var deletePassword = ""
    @State private var message: String?

    var body: some View {
        Form {
            Section(String(localized: "Profile")) {
                TextField(String(localized: "Display name"), text: $displayName)
                Button(String(localized: "Save profile")) {
                    Task {
                        _ = try? await api.updateProfile(displayName: displayName)
                        message = String(localized: "Profile updated.")
                    }
                }
            }
            Section(String(localized: "Notifications")) {
                Toggle(String(localized: "Chat"), isOn: $chatPref)
                Toggle(String(localized: "Projects"), isOn: $projectPref)
                Toggle(String(localized: "Requests"), isOn: $orderPref)
                Toggle(String(localized: "News"), isOn: $newsPref)
                Toggle(String(localized: "Push notifications"), isOn: $pushPref)
                Button(String(localized: "Save settings")) {
                    Task {
                        let prefs = CustomerSettingsResponse.NotificationPrefs(
                            chat: chatPref, project: projectPref, order: orderPref,
                            news: newsPref, security: securityPref, push_enabled: pushPref
                        )
                        _ = try? await api.updateSettings(prefs)
                        message = String(localized: "Settings saved.")
                    }
                }
            }
            Section(String(localized: "Delete account")) {
                SecureField(String(localized: "Confirm password"), text: $deletePassword)
                Button(String(localized: "Delete my account"), role: .destructive) {
                    Task {
                        do {
                            try await api.deleteAccount(password: deletePassword)
                            appAuth.logout()
                        } catch {
                            message = error.localizedDescription
                        }
                    }
                }
            }
            if let message {
                Section { Text(message) }
            }
        }
        .navigationTitle(String(localized: "Settings"))
        .task {
            if let profile = try? await api.fetchProfile() {
                displayName = profile.profile.display_name
            }
            if let settings = try? await api.fetchSettings() {
                chatPref = settings.notifications.chat
                projectPref = settings.notifications.project
                orderPref = settings.notifications.order
                newsPref = settings.notifications.news
                securityPref = settings.notifications.security
                pushPref = settings.notifications.push_enabled
            }
        }
    }
}
