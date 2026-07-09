import AppIntents

@available(iOS 16.0, *)
struct OpenDashboardIntent: AppIntent {
    static var title: LocalizedStringResource = "Open Dashboard"
    static var openAppWhenRun = true

    func perform() async throws -> some IntentResult {
        NotificationCenter.default.post(name: .paxQuickAction, object: QuickActionsManager.openDashboard)
        return .result()
    }
}

@available(iOS 16.0, *)
struct OpenLiveRequestsIntent: AppIntent {
    static var title: LocalizedStringResource = "Open Live Requests"
    static var openAppWhenRun = true

    func perform() async throws -> some IntentResult {
        NotificationCenter.default.post(name: .paxQuickAction, object: QuickActionsManager.openLive)
        return .result()
    }
}

@available(iOS 16.0, *)
struct SearchLiveChatIntent: AppIntent {
    static var title: LocalizedStringResource = "Search Live Chat"
    static var openAppWhenRun = true

    @Parameter(title: "Query")
    var query: String?

    func perform() async throws -> some IntentResult {
        NotificationCenter.default.post(
            name: .paxQuickAction,
            object: QuickActionsManager.openSearch,
            userInfo: ["query": query ?? ""]
        )
        return .result()
    }
}

@available(iOS 16.0, *)
struct PAXLiveChatShortcuts: AppShortcutsProvider {
    static var appShortcuts: [AppShortcut] {
        AppShortcut(
            intent: OpenDashboardIntent(),
            phrases: ["Open \(.applicationName) dashboard", "Show business overview in \(.applicationName)"],
            shortTitle: "Dashboard",
            systemImageName: "house.fill"
        )
        AppShortcut(
            intent: OpenLiveRequestsIntent(),
            phrases: ["Open live requests in \(.applicationName)", "Show waiting customers in \(.applicationName)"],
            shortTitle: "Live Requests",
            systemImageName: "bell.and.waves.left.and.right.fill"
        )
        AppShortcut(
            intent: SearchLiveChatIntent(),
            phrases: ["Search \(.applicationName)", "Find chats in \(.applicationName)"],
            shortTitle: "Search",
            systemImageName: "magnifyingglass"
        )
    }
}
