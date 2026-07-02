import Foundation

enum SessionHandlerLocalization {
    static func label(handler: String) -> String {
        switch handler {
        case "live_request":
            return L10n.ChatHandlerLiveRequest
        case "admin":
            return L10n.ChatHandlerActive
        case "closed":
            return L10n.ChatHandlerClosed
        default:
            return L10n.ChatHandlerAi
        }
    }
}
