import Foundation
import UIKit
import UserNotifications

enum APNsRegistrationFailure: Error, Equatable {
    case notAuthorized(UNAuthorizationStatus)
    case iosError(domain: String, code: Int, message: String)
    case timeout(requestsSent: Int)
    case simulator

    var detailedMessage: String {
        switch self {
        case .notAuthorized(let status):
            return "Notification permission is \(authorizationLabel(status)); APNs registration requires authorized, provisional, or ephemeral."
        case .iosError(let domain, let code, let message):
            return "iOS APNs registration failed: \(domain) (\(code)) — \(message)"
        case .timeout(let count):
            return "APNs did not return a device token after \(count) registration request(s). Check Push capability, provisioning profile, and network."
        case .simulator:
            return "iOS Simulator cannot obtain a real APNs device token. Test on a physical device via TestFlight."
        }
    }

    private func authorizationLabel(_ status: UNAuthorizationStatus) -> String {
        switch status {
        case .authorized: return "authorized"
        case .denied: return "denied"
        case .notDetermined: return "not_determined"
        case .provisional: return "provisional"
        case .ephemeral: return "ephemeral"
        @unknown default: return "unknown"
        }
    }
}

@MainActor
final class PushService: NSObject, ObservableObject {
    static let shared = PushService()

    @Published private(set) var deviceToken: String?
    @Published private(set) var authorizationStatus: UNAuthorizationStatus = .notDetermined
    @Published private(set) var registrationRequestCount = 0
    @Published private(set) var lastRegistrationRequestAt: Date?
    @Published private(set) var tokenReceivedAt: Date?
    @Published private(set) var apnsDidRespond = false
    @Published private(set) var lastIOSRegistrationError: String?
    @Published private(set) var isRegisteredWithAPNs = false

    var apnsEnvironmentLabel: String {
        PAXAPNsEnvironment.label
    }

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

    func refreshAuthorizationStatus() async {
        let settings = await UNUserNotificationCenter.current().notificationSettings()
        authorizationStatus = settings.authorizationStatus
    }

    @discardableResult
    func requestAuthorization() async -> Bool {
        configureNotificationCategories()
        let center = UNUserNotificationCenter.current()
        let currentSettings = await center.notificationSettings()
        authorizationStatus = currentSettings.authorizationStatus
        switch currentSettings.authorizationStatus {
        case .authorized, .provisional, .ephemeral:
            _ = await ensureDeviceToken()
            return true
        case .denied:
            return false
        case .notDetermined:
            let granted = (try? await center.requestAuthorization(options: [.alert, .sound, .badge])) ?? false
            await refreshAuthorizationStatus()
            if granted {
                _ = await ensureDeviceToken()
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
        guard !token.isEmpty else { return }
        guard deviceToken != token else { return }

        deviceToken = token
        tokenReceivedAt = Date()
        apnsDidRespond = true
        isRegisteredWithAPNs = true
        lastIOSRegistrationError = nil

        DeviceSessionService.shared.resetRegistrationState()
        PushDiagnosticsStore.shared.recordTokenReceived(token: token)
        PushRegistrationDiagnostics.registerSucceeded(tokenPrefix: String(token.prefix(12)))
    }

    func recordRegistrationFailure(_ error: Error) {
        let nsError = error as NSError
        var parts = ["\(nsError.domain) (\(nsError.code)): \(nsError.localizedDescription)"]
        if let underlying = nsError.userInfo[NSUnderlyingErrorKey] as? NSError {
            parts.append("underlying \(underlying.domain) (\(underlying.code)): \(underlying.localizedDescription)")
        }
        if let reason = nsError.userInfo[NSLocalizedFailureReasonErrorKey] as? String, !reason.isEmpty {
            parts.append("reason: \(reason)")
        }
        let detail = parts.joined(separator: " | ")
        lastIOSRegistrationError = detail
        apnsDidRespond = true
        isRegisteredWithAPNs = false
        PushDiagnosticsStore.shared.recordIOSRegistrationFailure(detail)
        PushRegistrationDiagnostics.registerFailed("didFailToRegister: \(detail)")
    }

    func registerForRemoteNotificationsIfAuthorized() async {
        _ = await ensureDeviceToken(maxAttempts: 1, perAttemptTimeout: 20)
    }

    /// Full APNs registration lifecycle: permission check → registerForRemoteNotifications → wait for token or iOS error.
    @discardableResult
    func ensureDeviceToken(maxAttempts: Int = 3, perAttemptTimeout: TimeInterval = 20) async -> Result<String, APNsRegistrationFailure> {
        PushDiagnosticsStore.shared.recordRegistrationAttemptStarted()

        #if targetEnvironment(simulator)
        let failure = APNsRegistrationFailure.simulator
        PushDiagnosticsStore.shared.recordRegistrationAttemptFinished(success: false, error: failure.detailedMessage)
        return .failure(failure)
        #endif

        await refreshAuthorizationStatus()
        switch authorizationStatus {
        case .authorized, .provisional, .ephemeral:
            break
        default:
            let failure = APNsRegistrationFailure.notAuthorized(authorizationStatus)
            PushDiagnosticsStore.shared.recordRegistrationAttemptFinished(success: false, error: failure.detailedMessage)
            return .failure(failure)
        }

        if let token = deviceToken, !token.isEmpty {
            PushDiagnosticsStore.shared.recordRegistrationAttemptFinished(success: true, error: nil)
            return .success(token)
        }

        let attempts = max(1, maxAttempts)
        for attempt in 1...attempts {
            lastIOSRegistrationError = nil
            apnsDidRespond = false
            recordRegistrationRequest()

            UIApplication.shared.registerForRemoteNotifications()

            let deadline = Date().addingTimeInterval(perAttemptTimeout)
            while Date() < deadline {
                if let token = deviceToken, !token.isEmpty {
                    PushDiagnosticsStore.shared.recordRegistrationAttemptFinished(success: true, error: nil)
                    return .success(token)
                }
                if let iosError = lastIOSRegistrationError {
                    let nsFailure = parseNSErrorDetail(iosError)
                    if attempt >= attempts {
                        PushDiagnosticsStore.shared.recordRegistrationAttemptFinished(success: false, error: nsFailure.detailedMessage)
                        return .failure(nsFailure)
                    }
                    break
                }
                isRegisteredWithAPNs = UIApplication.shared.isRegisteredForRemoteNotifications
                try? await Task.sleep(nanoseconds: 250_000_000)
            }

            if let token = deviceToken, !token.isEmpty {
                PushDiagnosticsStore.shared.recordRegistrationAttemptFinished(success: true, error: nil)
                return .success(token)
            }
            if attempt < attempts {
                try? await Task.sleep(nanoseconds: 1_000_000_000)
            }
        }

        if let iosError = lastIOSRegistrationError {
            let failure = parseNSErrorDetail(iosError)
            PushDiagnosticsStore.shared.recordRegistrationAttemptFinished(success: false, error: failure.detailedMessage)
            return .failure(failure)
        }

        let failure = APNsRegistrationFailure.timeout(requestsSent: registrationRequestCount)
        PushDiagnosticsStore.shared.recordRegistrationAttemptFinished(success: false, error: failure.detailedMessage)
        return .failure(failure)
    }

    private func recordRegistrationRequest() {
        registrationRequestCount += 1
        lastRegistrationRequestAt = Date()
        PushDiagnosticsStore.shared.recordRegistrationRequested(count: registrationRequestCount, at: lastRegistrationRequestAt!)
    }

    private func parseNSErrorDetail(_ detail: String) -> APNsRegistrationFailure {
        if let open = detail.firstIndex(of: "("),
           let close = detail.firstIndex(of: ")"),
           open < close {
            let domain = String(detail[..<open]).trimmingCharacters(in: .whitespaces)
            let codeString = String(detail[detail.index(after: open)..<close]).trimmingCharacters(in: .whitespaces)
            let code = Int(codeString) ?? -1
            let messageStart = detail.index(after: close)
            let message = detail[messageStart...].trimmingCharacters(in: CharacterSet(charactersIn: ": "))
            return .iosError(domain: domain, code: code, message: message.isEmpty ? detail : message)
        }
        return .iosError(domain: "APNs", code: -1, message: detail)
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
        return parsePaxDictionary(pax)
    }

    func parseFlattenedNotification(userInfo: [AnyHashable: Any]) -> PushPayload? {
        guard userInfo["pax"] == nil else { return nil }
        let sessionId = (userInfo["session_id"] as? String) ?? ""
        guard !sessionId.isEmpty else { return nil }
        let type = (userInfo["type"] as? String) ?? "message"
        let event = (userInfo["event"] as? String) ?? type
        return PushPayload(
            sessionId: sessionId,
            type: type,
            event: event,
            customerName: (userInfo["customer_name"] as? String) ?? "",
            service: (userInfo["service"] as? String) ?? "",
            preview: (userInfo["preview"] as? String) ?? ""
        )
    }

    private func parsePaxDictionary(_ pax: [String: Any]) -> PushPayload? {
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
            await PushService.shared.ensureDeviceToken(maxAttempts: 2, perAttemptTimeout: 25)
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
            PushService.shared.recordRegistrationFailure(error)
            if AuthStore.shared.isLoggedIn {
                await DeviceSessionService.shared.registerWithPush(auth: AuthStore.shared)
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
