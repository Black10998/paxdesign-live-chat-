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
            title: L10n.PushActionAccept,
            options: [.foreground]
        )
        let decline = UNNotificationAction(
            identifier: "PAX_DECLINE",
            title: L10n.PushActionDecline,
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

    @discardableResult
    func requestAuthorization() async -> Bool {
        configureNotificationCategories()
        let center = UNUserNotificationCenter.current()
        let currentSettings = await center.notificationSettings()
        switch currentSettings.authorizationStatus {
        case .authorized, .provisional, .ephemeral:
            await MainActor.run {
                UIApplication.shared.registerForRemoteNotifications()
            }
            return true
        case .denied:
            return false
        case .notDetermined:
            let granted = (try? await center.requestAuthorization(options: [.alert, .sound, .badge])) ?? false
            if granted {
                await MainActor.run {
                    UIApplication.shared.registerForRemoteNotifications()
                }
            }
            return granted
        @unknown default:
            return false
        }
    }

    func registerTokenWithBackend(auth: AuthStore) async {
        await DeviceSessionService.shared.registerWithPush(auth: auth)
    }

    func unregisterTokenFromBackend(auth: AuthStore) async {
        guard let token = deviceToken, let api = auth.api else { return }
        try? await api.unregisterAPNs(token: token)
    }

    func updateDeviceToken(_ tokenData: Data) {
        let token = tokenData.map { String(format: "%02.2hhx", $0) }.joined()
        guard deviceToken != token else { return }
        deviceToken = token
        DeviceSessionService.shared.resetRegistrationState()
    }

    func registerForRemoteNotificationsIfAuthorized() async {
        let settings = await UNUserNotificationCenter.current().notificationSettings()
        switch settings.authorizationStatus {
        case .authorized, .provisional, .ephemeral:
            await MainActor.run {
                UIApplication.shared.registerForRemoteNotifications()
            }
        default:
            break
        }
    }

    struct PushPayload {
        let sessionId: String
        let type: String
        let event: String
        let customerName: String
        let service: String
        let preview: String
    }

    func parseNotification(userInfo: [AnyHashable: Any]) -> PushPayload? {
        guard let pax = userInfo["pax"] as? [String: Any] else { return nil }
        let sessionId = (pax["session_id"] as? String) ?? ""
        let type = (pax["type"] as? String) ?? "message"
        let event = (pax["event"] as? String) ?? type
        guard !sessionId.isEmpty else { return nil }
        return PushPayload(
            sessionId: sessionId,
            type: type,
            event: event,
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
        PushService.shared.configureNotificationCategories()
        QuickActionsManager.configure(
            isLoggedIn: AuthStore.shared.isLoggedIn,
            canViewChats: AuthStore.shared.canViewChats,
            canManageUsers: AuthStore.shared.canManageUsers
        )
        if let remoteNotification = launchOptions?[.remoteNotification] as? [AnyHashable: Any] {
            PushDeepLinkRouter.shared.store(userInfo: remoteNotification)
        }
        if let shortcut = launchOptions?[.shortcutItem] as? UIApplicationShortcutItem {
            DispatchQueue.main.async {
                NotificationCenter.default.post(name: .paxQuickAction, object: shortcut.type)
            }
        }
        Task { @MainActor in
            await PushService.shared.registerForRemoteNotificationsIfAuthorized()
        }
        return true
    }

    func application(
        _ application: UIApplication,
        performActionFor shortcutItem: UIApplicationShortcutItem,
        completionHandler: @escaping (Bool) -> Void
    ) {
        NotificationCenter.default.post(name: .paxQuickAction, object: shortcutItem.type)
        completionHandler(true)
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

    func application(_ application: UIApplication, didFailToRegisterForRemoteNotificationsWithError error: Error) {
        Task { @MainActor in
            PushRegistrationDiagnostics.registerFailed("didFailToRegister: \(error.localizedDescription)")
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

        PushDeepLinkRouter.shared.store(userInfo: info, action: response.actionIdentifier)
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
            "event": payload.event,
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
