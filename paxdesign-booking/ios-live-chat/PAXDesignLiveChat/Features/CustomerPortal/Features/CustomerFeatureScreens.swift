import SwiftUI

// MARK: - Projects

struct CustomerProjectsListView: View {
    @EnvironmentObject private var api: CustomerAPIClient
    @EnvironmentObject private var navigation: CustomerNavigationCoordinator
    var useSplitLayout: Bool = false
    @State private var projects: [CustomerProjectSummary] = []
    @State private var error: String?
    @State private var isLoading = true
    @State private var selectedProjectId: Int?

    var body: some View {
        Group {
            if useSplitLayout {
                NavigationSplitView {
                    projectList
                        .navigationTitle(String(localized: "Projects"))
                } detail: {
                    if let selectedProjectId {
                        CustomerProjectDetailView(projectId: selectedProjectId)
                    } else {
                        PAXContentUnavailableView(
                            String(localized: "Select a project"),
                            systemImage: "folder",
                            description: Text(String(localized: "Choose a project to view milestones, files, and activity."))
                        )
                    }
                }
            } else {
                NavigationStack {
                    projectList
                        .navigationTitle(String(localized: "Projects"))
                }
            }
        }
        .task(id: navigation.workspaceRefreshToken) { await load() }
    }

    private var projectList: some View {
        Group {
            if isLoading {
                CustomerListRowsSkeleton(rowCount: 6)
            } else if let error {
                PAXContentUnavailableView(String(localized: "Projects unavailable"), systemImage: "exclamationmark.triangle", description: Text(error))
            } else if projects.isEmpty {
                ScrollView {
                    CustomerProjectsEmptyState()
                        .padding()
                }
                .refreshable { await load() }
            } else {
                List(projects, selection: useSplitLayout ? $selectedProjectId : .constant(nil)) { project in
                    if useSplitLayout {
                        projectRow(project)
                            .tag(project.id)
                    } else {
                        NavigationLink {
                            CustomerProjectDetailView(projectId: project.id)
                        } label: {
                            projectRow(project)
                        }
                    }
                }
                .refreshable { await load() }
            }
        }
    }

    private func projectRow(_ project: CustomerProjectSummary) -> some View {
        VStack(alignment: .leading, spacing: 6) {
            Text(project.title).font(.headline)
            HStack {
                Text(project.status).foregroundStyle(.secondary)
                Spacer()
                ProgressView(value: Double(project.progress), total: 100)
                    .frame(width: 72)
                Text("\(project.progress)%").font(.subheadline.weight(.semibold))
            }.font(.subheadline)
        }
        .padding(.vertical, 4)
    }

    private func load() async {
        isLoading = projects.isEmpty
        error = nil
        do {
            let response = try await api.fetchProjects()
            projects = response.projects
            if useSplitLayout, selectedProjectId == nil {
                selectedProjectId = projects.first?.id
            }
        } catch {
            self.error = (error as? CustomerAPIError)?.localizedDescription ?? error.localizedDescription
        }
        isLoading = false
    }
}

struct CustomerProjectDetailView: View {
    @EnvironmentObject private var api: CustomerAPIClient
    let projectId: Int
    @State private var project: CustomerProjectDetail?
    @State private var error: String?
    @State private var downloadingFileId: Int?
    @State private var shareURL: URL?

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
                            ForEach(files) { file in
                                CustomerFileRow(
                                    name: file.file_name,
                                    subtitle: CustomerPortalFormatting.fileSize(file.file_size),
                                    size: file.file_size,
                                    isLoading: downloadingFileId == file.id
                                ) {
                                    Task { await downloadProjectFile(file) }
                                }
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
                CustomerDetailScrollSkeleton()
            }
        }
        .navigationTitle(project?.title ?? String(localized: "Project"))
        .task { await load() }
        .sheet(item: $shareURL) { url in
            CustomerFileShareSheet(url: url)
        }
    }

    private func load() async {
        do {
            project = try await api.fetchProject(id: projectId)
        } catch {
            self.error = (error as? CustomerAPIError)?.localizedDescription ?? error.localizedDescription
        }
    }

    private func downloadProjectFile(_ file: CustomerProjectDetail.FileItem) async {
        downloadingFileId = file.id
        defer { downloadingFileId = nil }
        do {
            shareURL = try await api.downloadProjectFile(projectId: projectId, fileId: file.id)
        } catch {
            self.error = (error as? CustomerAPIError)?.localizedDescription ?? error.localizedDescription
        }
    }
}

private struct CustomerProjectsEmptyState: View {
    var body: some View {
        CustomerPortalCard {
            VStack(spacing: 20) {
                Image(systemName: "folder.badge.plus")
                    .font(.system(size: 56))
                    .foregroundStyle(PAXTheme.accent)
                    .padding(.top, 8)

                Text(String(localized: "No projects yet"))
                    .font(.title2.weight(.bold))

                Text(String(localized: "Your active projects will appear here once our team assigns work to your account. Explore our portfolio or start a service request."))
                    .font(.body)
                    .foregroundStyle(PAXTheme.textSecondary)
                    .multilineTextAlignment(.center)

                NavigationLink {
                    CustomerPortfolioListView()
                } label: {
                    Label(String(localized: "Browse portfolio"), systemImage: "photo.on.rectangle.angled")
                        .frame(maxWidth: .infinity)
                }
                .buttonStyle(CustomerPrimaryButtonStyleModifier(style: .filled))

                NavigationLink {
                    CustomerServicesCatalogView()
                } label: {
                    Label(String(localized: "Explore services"), systemImage: "square.grid.2x2")
                        .frame(maxWidth: .infinity)
                }
                .buttonStyle(.bordered)
            }
            .padding(8)
        }
    }
}

// MARK: - Orders

struct CustomerOrdersListView: View {
    @EnvironmentObject private var api: CustomerAPIClient
    @EnvironmentObject private var navigation: CustomerNavigationCoordinator
    @State private var orders: [CustomerOrderSummary] = []
    @State private var error: String?
    @State private var isLoading = true

    var body: some View {
        Group {
            if isLoading {
                CustomerListRowsSkeleton(rowCount: 6)
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
        .task(id: navigation.workspaceRefreshToken) { await load() }
        .refreshable { await load() }
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
    @State private var downloadingFileId: Int?
    @State private var shareURL: URL?

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
                    if let files = order.files, !files.isEmpty {
                        Section(String(localized: "Files & Invoices")) {
                            ForEach(files) { file in
                                CustomerFileRow(
                                    name: file.file_name,
                                    subtitle: file.kind.capitalized + " · " + CustomerPortalFormatting.fileSize(file.file_size),
                                    size: file.file_size,
                                    isLoading: downloadingFileId == file.id
                                ) {
                                    Task { await downloadOrderFile(file) }
                                }
                            }
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
                CustomerDetailScrollSkeleton()
            }
        }
        .navigationTitle(order?.service_label ?? String(localized: "Request"))
        .task { await load() }
        .sheet(item: $shareURL) { url in
            CustomerFileShareSheet(url: url)
        }
    }

    private func load() async {
        do { order = try await api.fetchOrder(id: orderId) }
        catch { self.error = (error as? CustomerAPIError)?.localizedDescription ?? error.localizedDescription }
    }

    private func downloadOrderFile(_ file: CustomerOrderDetail.FileItem) async {
        downloadingFileId = file.id
        defer { downloadingFileId = nil }
        do {
            shareURL = try await api.downloadOrderFile(orderId: orderId, fileId: file.id)
        } catch {
            self.error = (error as? CustomerAPIError)?.localizedDescription ?? error.localizedDescription
        }
    }
}

struct CustomerCreateOrderView: View {
    @EnvironmentObject private var api: CustomerAPIClient
    @Environment(\.dismiss) private var dismiss
    var preselectedSlug: String = ""
    var prefilledTitle: String = ""
    var prefilledDescription: String = ""
    @State private var services: [CustomerServicesResponse.Service] = []
    @State private var selectedSlug = ""
    @State private var description = ""
    @State private var error: String?
    @State private var isSubmitting = false
    @State private var isLoadingServices = true

    static func templateDescription(title: String, features: [String]) -> String {
        let header = String(localized: "Service request: \(title)")
        if features.isEmpty {
            return header + "\n\n" + String(localized: "Please describe your requirements below.")
        }
        let bullets = features.map { "• \($0)" }.joined(separator: "\n")
        return header + "\n\n" + bullets + "\n\n" + String(localized: "Please add any additional details.")
    }

    var body: some View {
        Group {
            if isLoadingServices && services.isEmpty {
                CustomerFormSkeleton()
            } else {
                orderForm
            }
        }
        .navigationTitle(String(localized: "New request"))
        .task { await loadServices() }
    }

    private var orderForm: some View {
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
    }

    private func loadServices() async {
        isLoadingServices = services.isEmpty
        if let response = try? await api.fetchServices() {
            services = response.services
            if !preselectedSlug.isEmpty {
                selectedSlug = preselectedSlug
            } else if selectedSlug.isEmpty {
                selectedSlug = services.first?.slug ?? ""
            }
            if description.trimmingCharacters(in: .whitespacesAndNewlines).isEmpty {
                if !prefilledDescription.isEmpty {
                    description = prefilledDescription
                } else if !prefilledTitle.isEmpty {
                    description = Self.templateDescription(title: prefilledTitle, features: [])
                }
            }
        }
        isLoadingServices = false
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
    @State private var isLoading = true

    var body: some View {
        Group {
                if isLoading && items.isEmpty {
                    CustomerNewsListSkeleton()
                } else if let error {
                    PAXContentUnavailableView(String(localized: "News unavailable"), systemImage: "newspaper", description: Text(error))
                } else if items.isEmpty {
                    PAXContentUnavailableView(
                        String(localized: "No announcements yet"),
                        systemImage: "newspaper",
                        description: Text(String(localized: "Updates from PAXDesign will appear here."))
                    )
                } else {
                    ScrollView {
                        LazyVStack(spacing: CustomerPortalDesign.sectionSpacing) {
                            ForEach(items) { item in
                                NavigationLink {
                                    CustomerNewsDetailView(slug: item.slug)
                                } label: {
                                    CustomerNewsCard(item: item)
                                }
                                .buttonStyle(.plain)
                            }
                        }
                        .padding()
                    }
                }
            }
            .background(PAXBackground())
            .navigationTitle(String(localized: "News"))
            .task { await load() }
            .refreshable { await load() }
    }

    private func load() async {
        isLoading = items.isEmpty
        error = nil
        do {
            items = try await api.fetchNews().items
        } catch {
            self.error = (error as? CustomerAPIError)?.localizedDescription ?? error.localizedDescription
        }
        isLoading = false
    }
}

private struct CustomerNewsCard: View {
    let item: CustomerNewsItem

    var body: some View {
        CustomerPortalCard {
            VStack(alignment: .leading, spacing: 10) {
                if let imageURL = item.image_url, let url = URL(string: imageURL) {
                    AsyncImage(url: url) { phase in
                        if case .success(let image) = phase {
                            image.resizable().scaledToFill()
                        } else {
                            Rectangle().fill(PAXTheme.accentSoft)
                        }
                    }
                    .frame(height: 140)
                    .clipShape(RoundedRectangle(cornerRadius: 12, style: .continuous))
                }
                Text(item.title)
                    .font(.headline)
                    .foregroundStyle(PAXTheme.textPrimary)
                    .multilineTextAlignment(.leading)
                if let excerpt = item.excerpt, !excerpt.isEmpty {
                    Text(excerpt)
                        .font(.subheadline)
                        .foregroundStyle(.secondary)
                        .lineLimit(3)
                }
                if let date = item.published_at, !date.isEmpty {
                    Text(CustomerPortalFormatting.relativeDate(date))
                        .font(.caption)
                        .foregroundStyle(.tertiary)
                }
            }
        }
    }
}

struct CustomerNewsDetailView: View {
    @EnvironmentObject private var api: CustomerAPIClient
    let slug: String
    @State private var item: CustomerNewsItem?
    @State private var error: String?

    var body: some View {
        ScrollView {
            if let item {
                VStack(alignment: .leading, spacing: 16) {
                    if let imageURL = item.image_url, let url = URL(string: imageURL) {
                        AsyncImage(url: url) { phase in
                            if case .success(let image) = phase {
                                image.resizable().scaledToFill()
                            } else {
                                SkeletonBlock(height: 200, cornerRadius: 16)
                            }
                        }
                        .frame(maxWidth: .infinity)
                        .frame(height: 200)
                        .clipShape(RoundedRectangle(cornerRadius: 16, style: .continuous))
                    }
                    Text(item.title).font(.title.bold())
                    if let date = item.published_at, !date.isEmpty {
                        Text(CustomerPortalFormatting.relativeDate(date))
                            .font(.subheadline)
                            .foregroundStyle(.secondary)
                    }
                    if let body = item.body, !body.isEmpty {
                        Text(body.replacingOccurrences(of: "<[^>]+>", with: "", options: .regularExpression))
                            .font(.body)
                    }
                }
                .padding()
            } else if let error {
                PAXContentUnavailableView(String(localized: "Unable to load"), systemImage: "exclamationmark.triangle", description: Text(error))
            } else {
                CustomerDetailScrollSkeleton()
                    .padding(.top, 8)
            }
        }
        .background(PAXBackground())
        .navigationTitle(String(localized: "Announcement"))
        .task { await load() }
    }

    private func load() async {
        do {
            item = try await api.fetchNewsItem(slug: slug)
        } catch {
            self.error = (error as? CustomerAPIError)?.localizedDescription ?? error.localizedDescription
        }
    }
}

struct CustomerNotificationsView: View {
    @EnvironmentObject private var api: CustomerAPIClient
    @EnvironmentObject private var navigation: CustomerNavigationCoordinator
    @State private var response: CustomerNotificationsResponse?
    @State private var error: String?
    @State private var filter: String = "all"

    private var filteredItems: [CustomerNotificationItem] {
        guard let items = response?.items else { return [] }
        if filter == "all" { return items }
        return items.filter { $0.category.lowercased() == filter }
    }

    var body: some View {
        Group {
            if let response {
                VStack(spacing: 0) {
                    ScrollView(.horizontal, showsIndicators: false) {
                        HStack(spacing: 8) {
                            filterChip("all", title: String(localized: "All"))
                            filterChip("chat", title: String(localized: "Chat"))
                            filterChip("project", title: String(localized: "Projects"))
                            filterChip("order", title: String(localized: "Requests"))
                            filterChip("news", title: String(localized: "News"))
                        }
                        .padding(.horizontal)
                        .padding(.vertical, 8)
                    }
                    if filteredItems.isEmpty {
                        PAXContentUnavailableView(
                            String(localized: "No notifications"),
                            systemImage: "bell",
                            description: Text(String(localized: "Updates about your projects, requests, and chat will appear here."))
                        )
                    } else {
                        List(filteredItems) { item in
                            Button {
                                Task { await openNotification(item) }
                            } label: {
                                VStack(alignment: .leading, spacing: 8) {
                                    HStack {
                                        CustomerNotificationCategoryBadge(category: item.category)
                                        Spacer()
                                        if !item.is_read {
                                            Circle().fill(PAXTheme.accent).frame(width: 8, height: 8)
                                        }
                                    }
                                    Text(item.title).font(.headline).foregroundStyle(PAXTheme.textPrimary)
                                    if let body = item.body, !body.isEmpty {
                                        Text(body).font(.subheadline).foregroundStyle(.secondary)
                                    }
                                    Text(item.created_at).font(.caption2).foregroundStyle(.tertiary)
                                }
                                .padding(.vertical, 4)
                                .opacity(item.is_read ? 0.72 : 1)
                            }
                            .buttonStyle(.plain)
                        }
                        .listStyle(.plain)
                    }
                }
                .navigationTitle(String(localized: "Notifications"))
                .toolbar {
                    if response.unread_count > 0 {
                        ToolbarItem(placement: .topBarTrailing) {
                            Button(String(localized: "Mark all read")) {
                                Task { await markAllRead() }
                            }
                        }
                    }
                }
            } else if let error {
                PAXContentUnavailableView(String(localized: "Notifications unavailable"), systemImage: "bell.slash", description: Text(error))
            } else {
                CustomerNotificationsSkeleton()
            }
        }
        .task { await load() }
        .refreshable { await load() }
    }

    private func filterChip(_ value: String, title: String) -> some View {
        Button(title) { filter = value }
            .font(.subheadline.weight(.medium))
            .padding(.horizontal, 12)
            .padding(.vertical, 8)
            .background(filter == value ? PAXTheme.accent : PAXTheme.surfaceElevated)
            .foregroundStyle(filter == value ? Color.white : PAXTheme.textPrimary)
            .clipShape(Capsule())
    }

    private func load() async {
        do { response = try await api.fetchNotifications() }
        catch { self.error = (error as? CustomerAPIError)?.localizedDescription ?? error.localizedDescription }
    }

    private func markAllRead() async {
        guard let ids = response?.items.filter({ !$0.is_read }).map(\.id), !ids.isEmpty else { return }
        try? await api.markNotificationsRead(ids: ids)
        await load()
    }

    private func openNotification(_ item: CustomerNotificationItem) async {
        if !item.is_read {
            try? await api.markNotificationsRead(ids: [item.id])
            await load()
        }
        if let link = CustomerDeepLink(notificationItem: item) {
            navigation.handle(deepLink: link)
        }
    }
}

struct CustomerFilesView: View {
    @EnvironmentObject private var api: CustomerAPIClient
    @State private var files: [CustomerFileLibraryItem] = []
    @State private var error: String?
    @State private var isLoading = true
    @State private var downloadingId: String?
    @State private var shareURL: URL?

    var body: some View {
        Group {
            if isLoading && files.isEmpty {
                CustomerFilesSkeleton()
            } else if let error {
                PAXContentUnavailableView(String(localized: "Files unavailable"), systemImage: "doc", description: Text(error))
            } else if files.isEmpty {
                PAXContentUnavailableView(
                    String(localized: "No files yet"),
                    systemImage: "doc.text",
                    description: Text(String(localized: "Project documents, invoices, and shared files will appear here."))
                )
            } else {
                List(files) { file in
                    CustomerFileRow(
                        name: file.file_name,
                        subtitle: "\(file.parent_title) · \(CustomerPortalFormatting.fileSize(file.file_size))",
                        size: file.file_size,
                        isLoading: downloadingId == file.id
                    ) {
                        Task { await download(file) }
                    }
                }
                .listStyle(.insetGrouped)
            }
        }
        .navigationTitle(String(localized: "Files & Invoices"))
        .task { await load() }
        .refreshable { await load() }
        .sheet(item: $shareURL) { url in
            CustomerFileShareSheet(url: url)
        }
    }

    private func load() async {
        isLoading = files.isEmpty
        error = nil
        do {
            files = try await api.fetchFilesLibrary().files
        } catch {
            self.error = (error as? CustomerAPIError)?.localizedDescription ?? error.localizedDescription
        }
        isLoading = false
    }

    private func download(_ file: CustomerFileLibraryItem) async {
        downloadingId = file.id
        defer { downloadingId = nil }
        do {
            switch file.source {
            case "project":
                shareURL = try await api.downloadProjectFile(projectId: file.parent_id, fileId: file.recordId)
            default:
                shareURL = try await api.downloadOrderFile(orderId: file.parent_id, fileId: file.recordId)
            }
        } catch {
            self.error = (error as? CustomerAPIError)?.localizedDescription ?? error.localizedDescription
        }
    }
}

struct CustomerFileShareSheet: View {
    let url: URL
    @Environment(\.dismiss) private var dismiss

    var body: some View {
        NavigationStack {
            VStack(spacing: 16) {
                Image(systemName: "doc.fill")
                    .font(.system(size: 44))
                    .foregroundStyle(PAXTheme.accent)
                Text(url.lastPathComponent)
                    .font(.headline)
                    .multilineTextAlignment(.center)
                ShareLink(item: url) {
                    Text(String(localized: "Share or Save"))
                        .frame(maxWidth: .infinity)
                }
                .buttonStyle(CustomerPrimaryButtonStyleModifier(style: .filled))
                .padding(.horizontal)
            }
            .padding()
            .navigationTitle(String(localized: "File ready"))
            .toolbar {
                ToolbarItem(placement: .topBarTrailing) {
                    Button(String(localized: "Done")) { dismiss() }
                }
            }
        }
        .presentationDetents([.medium])
    }
}

extension URL: @retroactive Identifiable {
    public var id: String { absoluteString }
}

// MARK: - Settings

struct CustomerSettingsView: View {
    @EnvironmentObject private var api: CustomerAPIClient
    @EnvironmentObject private var auth: CustomerAuthStore
    @EnvironmentObject private var appAuth: AuthStore
    @EnvironmentObject private var navigation: CustomerNavigationCoordinator
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
                        await auth.refreshProfile(api: api)
                        message = String(localized: "Profile updated.")
                    }
                }
            }
            Section(String(localized: "Notifications")) {
                Toggle(String(localized: "Chat"), isOn: $chatPref)
                Toggle(String(localized: "Projects"), isOn: $projectPref)
                Toggle(String(localized: "Requests"), isOn: $orderPref)
                Toggle(String(localized: "News"), isOn: $newsPref)
                Toggle(String(localized: "Security"), isOn: $securityPref)
                Toggle(String(localized: "Push notifications"), isOn: $pushPref)
                Button(String(localized: "Save settings")) {
                    Task {
                        let prefs = CustomerSettingsResponse.NotificationPrefs(
                            chat: chatPref, project: projectPref, order: orderPref,
                            news: newsPref, security: securityPref, push_enabled: pushPref
                        )
                        _ = try? await api.updateSettings(prefs)
                        message = String(localized: "Settings saved.")
                        navigation.refreshWorkspace()
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
            await auth.refreshProfile(api: api)
            if let profile = auth.profile {
                displayName = profile.display_name
            } else if let profile = try? await api.fetchProfile() {
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
