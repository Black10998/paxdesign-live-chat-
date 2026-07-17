import SwiftUI

enum CustomerPortalTab: Int, CaseIterable {
    case home = 0
    case services = 1
    case portfolio = 2
    case chat = 3
    case account = 4
}

struct CustomerPortalDestination: Equatable, Hashable {
    enum Kind: Hashable {
        case project(Int)
        case order(Int)
        case news(String)
        case notifications
        case files
        case chat(sessionID: String?)
        case portfolio
        case settings
        case profile
        case about
        case contact
        case dashboard
        case projectsList
        case ordersList
        case newsList
        case conversations
    }

    let kind: Kind
}

@MainActor
final class CustomerNavigationCoordinator: ObservableObject {
    static let shared = CustomerNavigationCoordinator()

    @Published var selectedTab: CustomerPortalTab = .home
    @Published var accountPath: [CustomerPortalDestination] = []
    @Published var chatSessionID: String?
    @Published var pendingChatFocus = false
    @Published var pendingServiceCardID: String?
    @Published var pendingOrderSlug: String?
    @Published private(set) var workspaceRefreshToken = UUID()

    func refreshWorkspace() {
        workspaceRefreshToken = UUID()
    }

    func openAccountDestination(_ destination: CustomerPortalDestination) {
        selectedTab = .account
        Task { @MainActor in
            try? await Task.sleep(nanoseconds: 50_000_000)
            accountPath = [destination]
        }
    }

    func openFiles() {
        openAccountDestination(CustomerPortalDestination(kind: .files))
    }

    func handle(deepLink: CustomerDeepLink) {
        let normalized = deepLink.path.trimmingCharacters(in: CharacterSet(charactersIn: "/"))
        let parts = normalized.split(separator: "/").map(String.init)

        guard let head = parts.first?.lowercased() else {
            selectedTab = .home
            return
        }

        switch head {
        case "chat":
            selectedTab = .chat
            let session = parts.count > 1 ? parts[1] : nil
            chatSessionID = session
            pendingChatFocus = true
        case "projects", "project":
            selectedTab = .account
            if parts.count > 1, let id = Int(parts[1]) {
                accountPath = [CustomerPortalDestination(kind: .project(id))]
            } else {
                accountPath = [CustomerPortalDestination(kind: .dashboard)]
            }
        case "orders", "order", "requests", "request":
            selectedTab = .account
            if parts.count > 1, let id = Int(parts[1]) {
                accountPath = [CustomerPortalDestination(kind: .order(id))]
            } else {
                accountPath = []
            }
        case "news":
            selectedTab = .account
            if parts.count > 1 {
                accountPath = [CustomerPortalDestination(kind: .news(parts[1]))]
            }
        case "notifications":
            selectedTab = .account
            accountPath = [CustomerPortalDestination(kind: .notifications)]
        case "files", "documents", "invoices":
            selectedTab = .account
            accountPath = [CustomerPortalDestination(kind: .files)]
        case "portfolio", "referenzen":
            selectedTab = .portfolio
        case "about", "ueber-uns":
            selectedTab = .account
            accountPath = [CustomerPortalDestination(kind: .about)]
        case "contact", "kontakt":
            selectedTab = .account
            accountPath = [CustomerPortalDestination(kind: .contact)]
        case "portal", "dashboard":
            selectedTab = .account
            accountPath = [CustomerPortalDestination(kind: .dashboard)]
        case "profile", "account":
            selectedTab = .account
            accountPath = [CustomerPortalDestination(kind: .profile)]
        case "settings":
            selectedTab = .account
            accountPath = [CustomerPortalDestination(kind: .settings)]
        case "services", "service":
            selectedTab = .services
            if parts.count > 1 {
                pendingServiceCardID = parts[1]
            }
        default:
            selectedTab = .home
        }
    }

    func openServiceRequest(slug: String) {
        pendingOrderSlug = slug
        selectedTab = .services
    }

    func openProject(_ id: Int) {
        selectedTab = .account
        accountPath = [CustomerPortalDestination(kind: .project(id))]
    }

    func openOrder(_ id: Int) {
        selectedTab = .account
        accountPath = [CustomerPortalDestination(kind: .order(id))]
    }

    func openNotifications() {
        selectedTab = .account
        accountPath = [CustomerPortalDestination(kind: .notifications)]
    }

    func openChat(sessionID: String? = nil) {
        selectedTab = .chat
        chatSessionID = sessionID
        pendingChatFocus = true
    }

    func openProjectsList() {
        openAccountDestination(CustomerPortalDestination(kind: .projectsList))
    }

    func openOrdersList() {
        openAccountDestination(CustomerPortalDestination(kind: .ordersList))
    }

    func openNewsList() {
        openAccountDestination(CustomerPortalDestination(kind: .newsList))
    }

    func openConversationsList() {
        openAccountDestination(CustomerPortalDestination(kind: .conversations))
    }
}

extension CustomerDeepLink {
    init?(notificationItem: CustomerNotificationItem) {
        if let deep = notificationItem.deep_link, !deep.isEmpty {
            self.init(path: deep.hasPrefix("/") ? deep : "/\(deep)")
            return
        }
        switch notificationItem.category.lowercased() {
        case "chat":
            self.init(path: "/chat")
        case "project":
            self.init(path: "/projects")
        case "order":
            self.init(path: "/orders")
        case "news":
            self.init(path: "/news")
        case "account", "security":
            self.init(path: "/profile")
        default:
            self.init(path: "/notifications")
        }
    }
}
