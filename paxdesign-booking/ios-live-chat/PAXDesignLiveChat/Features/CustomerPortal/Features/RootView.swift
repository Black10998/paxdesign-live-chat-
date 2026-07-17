import SwiftUI

struct CustomerPortalShellView: View {
    @EnvironmentObject private var auth: AuthStore
    @ObservedObject private var customerSession = CustomerSessionController.shared
    @ObservedObject private var deepLinks = CustomerDeepLinkRouter.shared
    @ObservedObject private var navigation = CustomerNavigationCoordinator.shared

    var body: some View {
        CustomerTabView()
            .environmentObject(customerSession.auth)
            .environmentObject(customerSession.api)
            .environmentObject(navigation)
            .tint(PAXTheme.accent)
            .task {
                customerSession.syncFromAuthStore(auth)
                CustomerPushService.shared.configure(api: customerSession.api)
                await CustomerPushService.shared.requestAuthorizationAndRegister()
            }
            .onChange(of: deepLinks.pending) { link in
                guard let link else { return }
                navigation.handle(deepLink: link)
                deepLinks.pending = nil
            }
    }
}

struct CustomerTabView: View {
    @EnvironmentObject private var navigation: CustomerNavigationCoordinator
    @Environment(\.horizontalSizeClass) private var horizontalSizeClass

    var body: some View {
        TabView(selection: $navigation.selectedTab) {
            CustomerWebsiteTabView()
                .tabItem { Label(String(localized: "Website"), systemImage: "globe") }
                .tag(CustomerPortalTab.website)
            CustomerChatView(initialSessionID: navigation.chatSessionID)
                .tabItem { Label(String(localized: "Chat"), systemImage: "message.fill") }
                .tag(CustomerPortalTab.chat)
            CustomerWorkspaceTabView()
                .tabItem { Label(String(localized: "My work"), systemImage: "folder.fill") }
                .tag(CustomerPortalTab.workspace)
            CustomerMoreView()
                .tabItem { Label(String(localized: "Account"), systemImage: "person.crop.circle.fill") }
                .tag(CustomerPortalTab.account)
        }
        .onChange(of: navigation.selectedTab) { tab in
            switch tab {
            case .workspace, .account:
                navigation.refreshWorkspace()
            default:
                break
            }
        }
    }
}

/// Native workspace: dashboard, projects, requests (authenticated backend — not a website substitute).
struct CustomerWorkspaceTabView: View {
    var body: some View {
        NavigationStack {
            CustomerDashboardView()
        }
    }
}

struct CustomerMoreView: View {
    @EnvironmentObject private var navigation: CustomerNavigationCoordinator

    var body: some View {
        NavigationStack(path: $navigation.accountPath) {
            List {
                Section(String(localized: "Workspace")) {
                    NavigationLink(String(localized: "Portfolio")) { CustomerPortfolioListView() }
                    NavigationLink(String(localized: "Requests")) {
                        CustomerOrdersListView()
                    }
                    NavigationLink(String(localized: "Files & Invoices")) {
                        CustomerFilesView()
                    }
                    NavigationLink(String(localized: "Notifications")) {
                        CustomerNotificationsView()
                    }
                    NavigationLink(String(localized: "News")) {
                        CustomerNewsListView()
                    }
                    NavigationLink(String(localized: "Conversations")) {
                        CustomerConversationsView()
                    }
                }
                Section(String(localized: "Account")) {
                    NavigationLink(String(localized: "Profile")) {
                        CustomerProfileView()
                    }
                    NavigationLink(String(localized: "Settings")) {
                        CustomerSettingsView()
                    }
                }
                Section(String(localized: "About")) {
                    LabeledContent(String(localized: "Version"), value: PAXAppInfo.fullVersion)
                    NavigationLink(String(localized: "About this app")) { AboutView() }
                }
            }
            .navigationTitle(String(localized: "Account"))
            .navigationDestination(for: CustomerPortalDestination.self) { destination in
                switch destination.kind {
                case .project(let id):
                    CustomerProjectDetailView(projectId: id)
                case .order(let id):
                    CustomerOrderDetailView(orderId: id)
                case .news(let slug):
                    CustomerNewsDetailView(slug: slug)
                case .notifications:
                    CustomerNotificationsView()
                case .files:
                    CustomerFilesView()
                case .portfolio:
                    CustomerPortfolioListView()
                case .chat(let sessionID):
                    CustomerChatView(initialSessionID: sessionID)
                case .settings:
                    CustomerSettingsView()
                case .profile:
                    CustomerProfileView()
                }
            }
        }
    }
}
