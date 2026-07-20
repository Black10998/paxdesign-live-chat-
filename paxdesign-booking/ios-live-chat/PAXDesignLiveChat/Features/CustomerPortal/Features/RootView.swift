import SwiftUI

struct CustomerPortalShellView: View {
    @EnvironmentObject private var auth: AuthStore
    @ObservedObject private var customerSession = CustomerSessionController.shared
    @ObservedObject private var customerPush = CustomerPushService.shared
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
            .task {
                CustomerPushService.shared.configure(api: customerSession.api)
            }
            .sheet(
                isPresented: $customerPush.shouldShowNotificationEducation,
                onDismiss: { CustomerPushService.shared.markNotificationEducationSeen() }
            ) {
                CustomerNotificationPermissionSheet(
                    onEnable: { CustomerPushService.shared.enableNotificationsAfterEducation() },
                    onSkip: { CustomerPushService.shared.skipNotificationEducation() }
                )
                .presentationDetents([.medium, .large])
                .presentationDragIndicator(.visible)
            }
            .onChange(of: deepLinks.pending) { link in
                guard let link else { return }
                navigation.handle(deepLink: link)
                deepLinks.pending = nil
            }
            .onReceive(NotificationCenter.default.publisher(for: UIApplication.willEnterForegroundNotification)) { _ in
                guard auth.isLoggedIn, auth.isCustomerSession, !auth.isBootstrapping else { return }
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
    @Environment(\.accessibilityReduceMotion) private var reduceMotion
    @StateObject private var menuScrollState = UiverseMenuScrollState()
    @State private var loadedTabs: Set<Int> = CustomerTabView.initialLoadedTabs()

    private static func initialLoadedTabs() -> Set<Int> {
        #if DEBUG
        if PAXLayoutVerification.isActive, PAXLayoutVerification.mode == .customer {
            return [CustomerPortalTab.chat.rawValue]
        }
        #endif
        return [CustomerPortalTab.home.rawValue]
    }

    private var menuItems: [UiverseMenuBarItem] {
        [
            UiverseMenuBarItem(tag: CustomerPortalTab.home.rawValue, icon: "dashboard.fill", title: String(localized: "Home")),
            UiverseMenuBarItem(tag: CustomerPortalTab.services.rawValue, icon: "platform.fill", title: String(localized: "Services")),
            UiverseMenuBarItem(tag: CustomerPortalTab.portfolio.rawValue, icon: "photo", title: String(localized: "Portfolio")),
            UiverseMenuBarItem(tag: CustomerPortalTab.chat.rawValue, icon: "chats.fill", title: String(localized: "Chat")),
            UiverseMenuBarItem(tag: CustomerPortalTab.account.rawValue, icon: "profile.user", title: String(localized: "Account")),
        ]
    }

    var body: some View {
        ZStack {
            customerTabPane(.home) {
                CustomerHomepageView()
            }
            customerTabPane(.services) {
                CustomerServicesCatalogScreen()
            }
            customerTabPane(.portfolio) {
                NavigationStack {
                    CustomerPortfolioListView()
                }
            }
            customerTabPane(.chat) {
                CustomerChatView(initialSessionID: navigation.chatSessionID)
            }
            customerTabPane(.account) {
                CustomerMoreView()
            }
        }
        .paxShellBottomTabBar(
            isVisible: true,
            items: menuItems,
            selection: Binding(
                get: { navigation.selectedTab.rawValue },
                set: { newValue in
                    navigation.selectedTab = CustomerPortalTab(rawValue: newValue) ?? .home
                }
            ),
            reduceMotion: reduceMotion,
            scrollState: menuScrollState
        )
        .environment(\.shellTabBarVisible, true)
        .onAppear {
            loadedTabs.insert(navigation.selectedTab.rawValue)
        }
        .onChange(of: navigation.selectedTab) { tab in
            loadedTabs.insert(tab.rawValue)
            PAXHaptics.light()
            switch tab {
            case .account:
                navigation.refreshWorkspace()
            default:
                break
            }
        }
    }

    @ViewBuilder
    private func customerTabPane<Content: View>(_ tab: CustomerPortalTab, @ViewBuilder content: () -> Content) -> some View {
        if loadedTabs.contains(tab.rawValue) {
            content()
                .opacity(navigation.selectedTab == tab ? 1 : 0)
                .allowsHitTesting(navigation.selectedTab == tab)
                .accessibilityHidden(navigation.selectedTab != tab)
                .zIndex(navigation.selectedTab == tab ? 1 : 0)
        }
    }
}

struct CustomerMoreView: View {
    @EnvironmentObject private var navigation: CustomerNavigationCoordinator
    @EnvironmentObject private var auth: CustomerAuthStore
    @EnvironmentObject private var api: CustomerAPIClient
    @State private var showAuth = false
    @State private var authMode: CustomerAuthSheetMode = .login

    private enum CustomerAuthSheetMode {
        case login, register
    }

    var body: some View {
        NavigationStack(path: $navigation.accountPath) {
            List {
                if !auth.isAuthenticated {
                    Section {
                        VStack(alignment: .leading, spacing: 12) {
                            Text(String(localized: "Sign in to access your workspace, chat, and files."))
                                .font(.subheadline)
                                .foregroundStyle(PAXTheme.textSecondary)
                            HStack(spacing: 10) {
                                Button(String(localized: "Sign In")) {
                                    authMode = .login
                                    showAuth = true
                                }
                                .buttonStyle(CustomerPrimaryButtonStyleModifier(style: .filled))
                                .frame(maxWidth: .infinity)

                                Button(String(localized: "Create account")) {
                                    authMode = .register
                                    showAuth = true
                                }
                                .buttonStyle(CustomerPrimaryButtonStyleModifier(style: .tinted))
                                .frame(maxWidth: .infinity)
                            }
                        }
                        .padding(.vertical, 4)
                    }
                }
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
                    .toolbar {
                        ToolbarItem(placement: .cancellationAction) {
                            Button(String(localized: "Close")) { showAuth = false }
                        }
                    }
                }
                .environmentObject(auth)
                .environmentObject(api)
            }
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
                case .devices:
                    CustomerDeviceManagementView()
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
