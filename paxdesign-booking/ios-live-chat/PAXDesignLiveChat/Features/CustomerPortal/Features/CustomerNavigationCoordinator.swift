import SwiftUI

enum CustomerPortalTab: Int, CaseIterable {
    case home = 0
    case services = 1
    case chat = 2
    case projects = 3
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
            selectedTab = .projects
            if parts.count > 1, let id = Int(parts[1]) {
                accountPath = [CustomerPortalDestination(kind: .project(id))]
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
        case "portfolio":
            selectedTab = .account
            accountPath = [CustomerPortalDestination(kind: .portfolio)]
        case "profile", "account":
            selectedTab = .account
            accountPath = [CustomerPortalDestination(kind: .profile)]
        case "settings":
            selectedTab = .account
            accountPath = [CustomerPortalDestination(kind: .settings)]
        case "services", "service":
            selectedTab = .services
        default:
            selectedTab = .home
        }
    }

    func openProject(_ id: Int) {
        selectedTab = .projects
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
