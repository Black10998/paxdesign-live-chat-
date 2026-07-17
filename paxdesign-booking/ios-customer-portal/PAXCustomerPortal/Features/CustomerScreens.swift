import SwiftUI

struct CustomerLoginView: View {
    @EnvironmentObject private var auth: CustomerAuthStore
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
                            await auth.login()
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
                                    HStack {
                                        Text(project.title)
                                        Spacer()
                                        Text("\(project.progress)%").foregroundStyle(.secondary)
                                    }
                                }
                            } else {
                                Text(String(localized: "No active projects.")).foregroundStyle(.secondary)
                            }
                        }
                        Section(String(localized: "Recent Requests")) {
                            if let orders = dashboard.orders_recent, !orders.isEmpty {
                                ForEach(orders, id: \.id) { order in
                                    HStack {
                                        Text(order.service_label)
                                        Spacer()
                                        Text(order.status).foregroundStyle(.secondary)
                                    }
                                }
                            } else {
                                Text(String(localized: "No service requests yet.")).foregroundStyle(.secondary)
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
                        VStack(alignment: .leading, spacing: 6) {
                            Text(service.name).font(.headline)
                            Text(service.description).font(.subheadline).foregroundStyle(.secondary).lineLimit(3)
                        }
                        .padding(.vertical, 4)
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

struct CustomerProfileView: View {
    @EnvironmentObject private var auth: CustomerAuthStore

    var body: some View {
        NavigationStack {
            List {
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
