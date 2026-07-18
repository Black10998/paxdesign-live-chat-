import SwiftUI

struct CustomerPortalShellView: View {
    @EnvironmentObject private var auth: AuthStore
    @ObservedObject private var customerSession = CustomerSessionController.shared
    @ObservedObject private var deepLinks = CustomerDeepLinkRouter.shared
    @ObservedObject private var navigation = CustomerNavigationCoordinator.shared
    @ObservedObject private var settings = AppSettingsStore.shared

    var body: some View {
        CustomerTabView()
            .environmentObject(customerSession.auth)
            .environmentObject(customerSession.api)
            .environmentObject(navigation)
            .environmentObject(settings)
            .tint(PAXTheme.accent)
            .preferredColorScheme(settings.appearanceMode.colorScheme)
            .id(settings.themeRevision)
            .task {
                customerSession.syncFromAuthStore(auth)
                CustomerPushService.shared.configure(api: customerSession.api)
                await CustomerPushService.shared.prepareNotificationRegistration()
            }
            .sheet(isPresented: Binding(
                get: { CustomerPushService.shared.shouldShowNotificationEducation },
                set: { CustomerPushService.shared.shouldShowNotificationEducation = $0 }
            )) {
                CustomerNotificationPermissionSheet {
                    CustomerPushService.shared.markNotificationEducationSeen()
                    Task { await CustomerPushService.shared.requestAuthorizationAndRegister() }
                }
            }
            .onChange(of: deepLinks.pending) { link in
                guard let link else { return }
                navigation.handle(deepLink: link)
                deepLinks.pending = nil
            }
            .onReceive(NotificationCenter.default.publisher(for: UIApplication.willEnterForegroundNotification)) { _ in
                Task {
                    await customerSession.auth.refreshProfile(api: customerSession.api)
                    navigation.refreshWorkspace()
                    CustomerNotificationsBadgeStore.shared.scheduleRefresh(api: customerSession.api)
                }
            }
    }
}

struct CustomerTabView: View {
    @EnvironmentObject private var navigation: CustomerNavigationCoordinator
    @Environment(\.horizontalSizeClass) private var horizontalSizeClass

    var body: some View {
        TabView(selection: $navigation.selectedTab) {
            CustomerHomepageView()
                .tabItem { Label(String(localized: "Home"), systemImage: "house.fill") }
                .tag(CustomerPortalTab.home)
            CustomerServicesCatalogScreen()
                .tabItem { Label(String(localized: "Services"), systemImage: "square.grid.2x2.fill") }
                .tag(CustomerPortalTab.services)
            NavigationStack {
                CustomerPortfolioListView()
            }
            .tabItem { Label(String(localized: "Portfolio"), systemImage: "photo.on.rectangle.angled") }
            .tag(CustomerPortalTab.portfolio)
            CustomerChatView(initialSessionID: navigation.chatSessionID)
                .tabItem { Label(String(localized: "Chat"), systemImage: "message.fill") }
                .tag(CustomerPortalTab.chat)
            CustomerMoreView()
                .tabItem { Label(String(localized: "Account"), systemImage: "person.crop.circle.fill") }
                .tag(CustomerPortalTab.account)
        }
        .onChange(of: navigation.selectedTab) { tab in
            PAXHaptics.light()
            switch tab {
            case .account:
                navigation.refreshWorkspace()
            default:
                break
            }
        }
    }
}

struct CustomerMoreView: View {
    @EnvironmentObject private var navigation: CustomerNavigationCoordinator

    var body: some View {
        NavigationStack(path: $navigation.accountPath) {
            List {
                Section(String(localized: "Company")) {
                    NavigationLink(String(localized: "About")) { CustomerAboutView() }
                    NavigationLink(String(localized: "Contact")) { CustomerContactView() }
                }
                Section(String(localized: "Customer Portal")) {
                    NavigationLink(String(localized: "My workspace")) {
                        CustomerDashboardView()
                    }
                    NavigationLink(String(localized: "Projects")) {
                        CustomerProjectsListView(useSplitLayout: false)
                    }
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
                Section(String(localized: "About this app")) {
                    LabeledContent(String(localized: "Version"), value: PAXAppInfo.fullVersion)
                    NavigationLink(String(localized: "About this app")) { AboutView() }
                }
                Section {
                    CustomerAccountFooterSection()
                        .listRowInsets(EdgeInsets(top: 0, leading: 0, bottom: 0, trailing: 0))
                        .listRowBackground(Color.clear)
                }
                .listRowSeparator(.hidden)
            }
            .navigationTitle(String(localized: "Account"))
            .customerPortalToolbar(showsAvatar: false)
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
                case .about:
                    CustomerAboutView()
                case .contact:
                    CustomerContactView()
                case .dashboard:
                    CustomerDashboardView()
                case .projectsList:
                    CustomerProjectsListView(useSplitLayout: false)
                case .ordersList:
                    CustomerOrdersListView()
                case .newsList:
                    CustomerNewsListView()
                case .conversations:
                    CustomerConversationsView()
                }
            }
        }
    }
}
