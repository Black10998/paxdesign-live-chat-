import Foundation
import UIKit
import UserNotifications

struct CustomerPushPayload: Equatable {
    let category: String
    let type: String
    let event: String
    let sessionId: String
    let deepLink: String
    let title: String
    let body: String
    let notificationId: Int

    var soundTone: PAXNotificationSound.Tone {
        switch category {
        case "security":
            return .aiAlert
        case "chat":
            return .message
        default:
            return .message
        }
    }
}

@MainActor
final class CustomerPushService: NSObject, ObservableObject {
    static let shared = CustomerPushService()

    private static let educationSeenKey = "customerNotificationEducationSeen"
    private static let messageCategory = "PAX_CUSTOMER_MESSAGE"
    private static let alertCategory = "PAX_CUSTOMER_ALERT"

    @Published private(set) var deviceToken: String?
    @Published private(set) var authorizationStatus: UNAuthorizationStatus = .notDetermined
    @Published var shouldShowNotificationEducation = false

    private weak var api: CustomerAPIClient?
    private var lastRegisteredToken: String?
    private var lastSoundPlayedAt: Date?

    func configure(api: CustomerAPIClient) {
        self.api = api
    }

    func configureNotificationCategories() {
        let messageCategory = UNNotificationCategory(
            identifier: Self.messageCategory,
            actions: [],
            intentIdentifiers: [],
            options: []
        )
        let alertCategory = UNNotificationCategory(
            identifier: Self.alertCategory,
            actions: [],
            intentIdentifiers: [],
            options: []
        )
        UNUserNotificationCenter.current().setNotificationCategories([messageCategory, alertCategory])
    }

    func prepareNotificationRegistration() async {
        configureNotificationCategories()
        let settings = await UNUserNotificationCenter.current().notificationSettings()
        authorizationStatus = settings.authorizationStatus
        if settings.authorizationStatus == .notDetermined,
           !UserDefaults.standard.bool(forKey: Self.educationSeenKey) {
            shouldShowNotificationEducation = true
            return
        }
        await requestAuthorizationAndRegister()
    }

    func markNotificationEducationSeen() {
        UserDefaults.standard.set(true, forKey: Self.educationSeenKey)
        shouldShowNotificationEducation = false
    }

    func requestAuthorizationAndRegister() async {
        configureNotificationCategories()
        let center = UNUserNotificationCenter.current()
        do {
            let granted = try await center.requestAuthorization(options: [.alert, .badge, .sound])
            authorizationStatus = granted ? .authorized : .denied
            if granted {
                UIApplication.shared.registerForRemoteNotifications()
            }
        } catch {
            authorizationStatus = .denied
        }
    }

    func didRegister(tokenData: Data) {
        let token = tokenData.map { String(format: "%02x", $0) }.joined()
        deviceToken = token
        guard token != lastRegisteredToken else { return }
        lastRegisteredToken = token
        Task {
            await CustomerDeviceSessionService.shared.registerIfNeeded(force: true)
        }
    }

    func didFailRegistration(_ error: Error) {
        #if DEBUG
        print("APNs registration failed: \(error.localizedDescription)")
        #endif
    }

    func parseNotification(userInfo: [AnyHashable: Any]) -> CustomerPushPayload? {
        let pax = (userInfo["pax"] as? [String: Any]) ?? userInfo as? [String: Any] ?? [:]
        guard !pax.isEmpty else { return nil }

        let category = (pax["category"] as? String) ?? "news"
        let type = (pax["type"] as? String) ?? category
        let event = (pax["event"] as? String) ?? type
        let sessionId = (pax["session_id"] as? String) ?? ""
        let deepLink = (pax["deep_link"] as? String) ?? ""
        let notificationId = (pax["notification_id"] as? Int) ?? Int(pax["notification_id"] as? String ?? "") ?? 0

        let aps = userInfo["aps"] as? [String: Any]
        let alert = aps?["alert"]
        let title: String
        let body: String
        switch alert {
        case let dictionary as [String: Any]:
            title = (dictionary["title"] as? String) ?? ""
            body = (dictionary["body"] as? String) ?? ""
        case let text as String:
            title = ""
            body = text
        default:
            title = ""
            body = ""
        }

        return CustomerPushPayload(
            category: category,
            type: type,
            event: event,
            sessionId: sessionId,
            deepLink: deepLink,
            title: title,
            body: body,
            notificationId: notificationId
        )
    }

    func handleNotification(userInfo: [AnyHashable: Any]) -> CustomerDeepLink? {
        if let payload = parseNotification(userInfo: userInfo) {
            return deepLink(from: payload)
        }
        if let deep = userInfo["deep_link"] as? String {
            return CustomerDeepLink(path: deep)
        }
        if let link = userInfo["deepLink"] as? String {
            return CustomerDeepLink(path: link)
        }
        if let category = userInfo["category"] as? String {
            return deepLink(forCategory: category)
        }
        return nil
    }

    func willPresentOptions(for notification: UNNotification) -> UNNotificationPresentationOptions {
        guard let payload = parseNotification(userInfo: notification.request.content.userInfo) else {
            return [.banner, .badge, .sound]
        }

        let activeSessionId = AppRefreshPolicy.activeSessionId
        if !payload.sessionId.isEmpty,
           payload.sessionId == activeSessionId,
           payload.category == "chat" {
            playForegroundSound(for: payload)
            return [.badge]
        }

        playForegroundSound(for: payload)
        return [.banner, .badge, .sound]
    }

    func handleForegroundNotification(_ notification: UNNotification) {
        guard let payload = parseNotification(userInfo: notification.request.content.userInfo) else { return }
        playForegroundSound(for: payload)
        CustomerNotificationsBadgeStore.shared.incrementUnread()
        if let link = deepLink(from: payload) {
            CustomerDeepLinkRouter.shared.pending = link
        }
    }

    func apnsSoundName(for payload: CustomerPushPayload) -> String {
        switch payload.soundTone {
        case .aiAlert:
            return "pax-ai-alert.wav"
        case .message:
            return "pax-message.wav"
        default:
            return "pax-message.wav"
        }
    }

    private func deepLink(from payload: CustomerPushPayload) -> CustomerDeepLink? {
        if !payload.deepLink.isEmpty {
            return CustomerDeepLink(path: payload.deepLink)
        }
        return deepLink(forCategory: payload.category)
    }

    private func deepLink(forCategory category: String) -> CustomerDeepLink? {
        switch category {
        case "chat": return CustomerDeepLink(path: "/chat")
        case "project": return CustomerDeepLink(path: "/projects")
        case "order": return CustomerDeepLink(path: "/requests")
        case "news": return CustomerDeepLink(path: "/news")
        default: return CustomerDeepLink(path: "/notifications")
        }
    }

    private func playForegroundSound(for payload: CustomerPushPayload) {
        let now = Date()
        if let lastSoundPlayedAt, now.timeIntervalSince(lastSoundPlayedAt) < 0.8 {
            return
        }
        lastSoundPlayedAt = now
        PAXNotificationSound.shared.play(payload.soundTone, respectSettings: false)
        PAXHaptics.light()
    }
}

struct CustomerDeepLink: Equatable {
    let path: String
}

@MainActor
final class CustomerDeepLinkRouter: ObservableObject {
    static let shared = CustomerDeepLinkRouter()
    @Published var pending: CustomerDeepLink?
}
