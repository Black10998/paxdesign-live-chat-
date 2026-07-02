import Foundation
import UIKit
import UserNotifications

@MainActor
final class PushService: NSObject, ObservableObject {
    static let shared = PushService()

    @Published private(set) var deviceToken: String?

    private let liveRequestCategory = "PAX_LIVE_REQUEST"
    private let messageCategory = "PAX_MESSAGE"

    func configureNotificationCategories() {
        let accept = UNNotificationAction(
            identifier: "PAX_ACCEPT",
            title: "Annehmen",
            options: [.foreground]
        )
        let decline = UNNotificationAction(
            identifier: "PAX_DECLINE",
            title: "Ablehnen",
            options: [.destructive, .foreground]
        )
        let liveCategory = UNNotificationCategory(
            identifier: liveRequestCategory,
            actions: [accept, decline],
            intentIdentifiers: [],
            options: [.customDismissAction]
        )
        let messageCategoryObj = UNNotificationCategory(
            identifier: messageCategory,
            actions: [],
            intentIdentifiers: [],
            options: []
        )
        UNUserNotificationCenter.current().setNotificationCategories([liveCategory, messageCategoryObj])
    }

    func requestAuthorization() async {
        configureNotificationCategories()
        let center = UNUserNotificationCenter.current()
        _ = try? await center.requestAuthorization(options: [.alert, .sound, .badge])
        await MainActor.run {
            UIApplication.shared.registerForRemoteNotifications()
        }
    }

    func registerTokenWithBackend(auth: AuthStore) async {
        guard let token = deviceToken, let api = auth.api else { return }
        #if DEBUG
        let sandbox = true
        #else
        let sandbox = false
        #endif
        try? await api.registerAPNs(token: token, sandbox: sandbox)
    }

    func unregisterTokenFromBackend(auth: AuthStore) async {
        guard let token = deviceToken, let api = auth.api else { return }
        try? await api.unregisterAPNs(token: token)
    }

    func updateDeviceToken(_ tokenData: Data) {
        deviceToken = tokenData.map { String(format: "%02.2hhx", $0) }.joined()
    }

    struct PushPayload {
        let sessionId: String
        let type: String
        let customerName: String
        let service: String
        let preview: String
    }

    func parseNotification(userInfo: [AnyHashable: Any]) -> PushPayload? {
        guard let pax = userInfo["pax"] as? [String: Any] else { return nil }
        let sessionId = (pax["session_id"] as? String) ?? ""
        let type = (pax["type"] as? String) ?? "message"
        guard !sessionId.isEmpty else { return nil }
        return PushPayload(
            sessionId: sessionId,
            type: type,
            customerName: (pax["customer_name"] as? String) ?? "",
            service: (pax["service"] as? String) ?? "",
            preview: (pax["preview"] as? String) ?? ""
        )
    }
}

final class AppDelegate: NSObject, UIApplicationDelegate, UNUserNotificationCenterDelegate {
    func application(_ application: UIApplication, didFinishLaunchingWithOptions launchOptions: [UIApplication.LaunchOptionsKey: Any]? = nil) -> Bool {
        configureNativeChrome()
        UNUserNotificationCenter.current().delegate = self
        return true
    }

    private func configureNativeChrome() {
        let tabAppearance = UITabBarAppearance()
        tabAppearance.configureWithDefaultBackground()
        UITabBar.appearance().standardAppearance = tabAppearance
        UITabBar.appearance().scrollEdgeAppearance = tabAppearance

        let navAppearance = UINavigationBarAppearance()
        navAppearance.configureWithDefaultBackground()
        UINavigationBar.appearance().standardAppearance = navAppearance
        UINavigationBar.appearance().scrollEdgeAppearance = navAppearance
        UINavigationBar.appearance().compactAppearance = navAppearance
    }

    func application(_ application: UIApplication, didRegisterForRemoteNotificationsWithDeviceToken deviceToken: Data) {
        Task { @MainActor in
            PushService.shared.updateDeviceToken(deviceToken)
            if AuthStore.shared.isLoggedIn {
                await PushService.shared.registerTokenWithBackend(auth: AuthStore.shared)
            }
        }
    }

    func application(
        _ application: UIApplication,
        didReceiveRemoteNotification userInfo: [AnyHashable: Any],
        fetchCompletionHandler completionHandler: @escaping (UIBackgroundFetchResult) -> Void
    ) {
        Task { @MainActor in
            if let payload = PushService.shared.parseNotification(userInfo: userInfo) {
                NotificationCenter.default.post(
                    name: .paxPushReceived,
                    object: nil,
                    userInfo: pushUserInfo(from: payload)
                )
            }
            completionHandler(.newData)
        }
    }

    func userNotificationCenter(
        _ center: UNUserNotificationCenter,
        willPresent notification: UNNotification
    ) async -> UNNotificationPresentationOptions {
        if let payload = PushService.shared.parseNotification(userInfo: notification.request.content.userInfo) {
            NotificationCenter.default.post(
                name: .paxPushReceived,
                object: nil,
                userInfo: pushUserInfo(from: payload)
            )
        }

        guard AppSettingsStore.shared.notificationsEnabled else { return [] }
        var options: UNNotificationPresentationOptions = [.banner, .badge]
        if AppSettingsStore.shared.messageSoundEnabled || payloadIsLiveRequest(notification) {
            options.insert(.sound)
        }
        return options
    }

    func userNotificationCenter(
        _ center: UNUserNotificationCenter,
        didReceive response: UNNotificationResponse
    ) async {
        let info = response.notification.request.content.userInfo
        guard let payload = PushService.shared.parseNotification(userInfo: info) else { return }

        var userInfo = pushUserInfo(from: payload)
        userInfo["action"] = response.actionIdentifier

        NotificationCenter.default.post(name: .paxPushOpened, object: nil, userInfo: userInfo)
    }

    private func payloadIsLiveRequest(_ notification: UNNotification) -> Bool {
        guard let pax = notification.request.content.userInfo["pax"] as? [String: Any] else { return false }
        return (pax["type"] as? String) == "live_request"
    }

    private func pushUserInfo(from payload: PushService.PushPayload) -> [String: Any] {
        [
            "session_id": payload.sessionId,
            "type": payload.type,
            "customer_name": payload.customerName,
            "service": payload.service,
            "preview": payload.preview
        ]
    }
}

extension Notification.Name {
    static let paxPushOpened = Notification.Name("paxPushOpened")
    static let paxPushReceived = Notification.Name("paxPushReceived")
    static let paxSessionSync = Notification.Name("paxSessionSync")
}
