import SwiftUI

struct RootView: View {
    @EnvironmentObject private var auth: CustomerAuthStore
    @EnvironmentObject private var api: CustomerAPIClient

    var body: some View {
        Group {
            if auth.isAuthenticated {
                CustomerTabView()
            } else {
                CustomerLoginView()
            }
        }
        .tint(Color(red: 0.21, green: 0.21, blue: 0.21))
        .task {
            auth.restoreSession(api: api)
        }
    }
}

struct CustomerTabView: View {
    var body: some View {
        TabView {
            CustomerDashboardView()
                .tabItem { Label(String(localized: "Home"), systemImage: "house.fill") }
            CustomerProjectsListView()
                .tabItem { Label(String(localized: "Projects"), systemImage: "folder.fill") }
            CustomerOrdersListView()
                .tabItem { Label(String(localized: "Requests"), systemImage: "doc.text.fill") }
            CustomerChatView()
                .tabItem { Label(String(localized: "Chat"), systemImage: "message.fill") }
            CustomerMoreView()
                .tabItem { Label(String(localized: "More"), systemImage: "ellipsis.circle.fill") }
        }
    }
}

struct CustomerMoreView: View {
    var body: some View {
        NavigationStack {
            List {
                NavigationLink(String(localized: "Services")) { CustomerServicesView() }
                NavigationLink(String(localized: "News")) { CustomerNewsListView() }
                NavigationLink(String(localized: "Notifications")) { CustomerNotificationsView() }
                NavigationLink(String(localized: "Account")) { CustomerProfileView() }
                NavigationLink(String(localized: "Settings")) { CustomerSettingsView() }
            }
            .navigationTitle(String(localized: "More"))
        }
    }
}
