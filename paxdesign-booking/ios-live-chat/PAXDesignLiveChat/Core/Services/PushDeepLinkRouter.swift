import Foundation

/// Queues notification deep links until the shell is ready (cold start / splash).
@MainActor
final class PushDeepLinkRouter: ObservableObject {
    static let shared = PushDeepLinkRouter()

    private struct PendingRoute {
        let sessionId: String
        let type: String
        let event: String
        let action: String?
        let customerName: String
        let service: String
        let preview: String
    }

    private var pending: PendingRoute?

    private init() {}

    func store(userInfo: [AnyHashable: Any], action: String? = nil) {
        guard let payload = PushService.shared.parseNotification(userInfo: userInfo) else { return }
        pending = PendingRoute(
            sessionId: payload.sessionId,
            type: payload.type,
            event: payload.event,
            action: action,
            customerName: payload.customerName,
            service: payload.service,
            preview: payload.preview
        )
    }

    func consumeIfReady(
        auth: AuthStore,
        coordinator: ChatCoordinator,
        teamCoordinator: TeamMessagingCoordinator,
        isShellReady: Bool
    ) async {
        guard isShellReady, auth.isLoggedIn, let route = pending else { return }
        pending = nil

        let payload = PushService.PushPayload(
            sessionId: route.sessionId,
            type: route.type,
            event: route.event,
            customerName: route.customerName,
            service: route.service,
            preview: route.preview
        )

        if let action = route.action, action.hasPrefix("PAX_") {
            await coordinator.handlePushAction(action, sessionId: route.sessionId, auth: auth)
            return
        }

        await coordinator.handlePush(
            sessionId: route.sessionId,
            type: route.type,
            auth: auth,
            payload: payload
        )

        if route.type == "team_message" || route.sessionId.hasPrefix("team_") {
            await teamCoordinator.refresh(auth: auth)
        }
    }
}
