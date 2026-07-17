import SwiftUI

struct RootView: View {
    @EnvironmentObject private var auth: CustomerAuthStore
    @EnvironmentObject private var api: CustomerAPIClient
    @ObservedObject private var deepLinks = CustomerDeepLinkRouter.shared

    var body: some View {
        Group {
            if auth.isAuthenticated {
                CustomerTabView()
            } else {
                CustomerAuthContainerView()
            }
        }
        .tint(Color(red: 0.21, green: 0.21, blue: 0.21))
        .task {
            auth.restoreSession(api: api)
            CustomerPushService.shared.configure(api: api)
            await CustomerPushService.shared.requestAuthorizationAndRegister()
        }
        .onChange(of: deepLinks.pending) { _, link in
            guard let link else { return }
            deepLinks.pending = nil
            // Deep link routing handled by tab selection in future; chat/projects paths open relevant tabs.
            _ = link.path
        }
    }
}

struct CustomerTabView: View {
    @State private var selectedTab = 0

    var body: some View {
        TabView(selection: $selectedTab) {
            CustomerDashboardView()
                .tabItem { Label(String(localized: "Home"), systemImage: "house.fill") }
                .tag(0)
            CustomerProjectsListView()
                .tabItem { Label(String(localized: "Projects"), systemImage: "folder.fill") }
                .tag(1)
            CustomerOrdersListView()
                .tabItem { Label(String(localized: "Requests"), systemImage: "doc.text.fill") }
                .tag(2)
            CustomerChatView()
                .tabItem { Label(String(localized: "Chat"), systemImage: "message.fill") }
                .tag(3)
            CustomerMoreView()
                .tabItem { Label(String(localized: "More"), systemImage: "ellipsis.circle.fill") }
                .tag(4)
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
                NavigationLink(String(localized: "Conversations")) { CustomerConversationsView() }
                NavigationLink(String(localized: "Account")) { CustomerProfileView() }
                NavigationLink(String(localized: "Settings")) { CustomerSettingsView() }
            }
            .navigationTitle(String(localized: "More"))
        }
    }
}
