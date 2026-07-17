import Foundation
import UIKit
import UserNotifications

@MainActor
final class CustomerPushService: NSObject, ObservableObject {
    static let shared = CustomerPushService()

    private static let educationSeenKey = "customerNotificationEducationSeen"

    @Published private(set) var deviceToken: String?
    @Published private(set) var authorizationStatus: UNAuthorizationStatus = .notDetermined
    @Published var shouldShowNotificationEducation = false

    private weak var api: CustomerAPIClient?
    private var lastRegisteredToken: String?

    func configure(api: CustomerAPIClient) {
        self.api = api
    }

    func prepareNotificationRegistration() async {
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
            do {
                try await api?.registerPush(
                    token: token,
                    deviceID: UIDevice.current.identifierForVendor?.uuidString ?? UUID().uuidString
                )
            } catch {
                lastRegisteredToken = nil
                #if DEBUG
                print("Customer push registration failed: \(error.localizedDescription)")
                #endif
            }
        }
    }

    func didFailRegistration(_ error: Error) {
        #if DEBUG
        print("APNs registration failed: \(error.localizedDescription)")
        #endif
    }

    func handleNotification(userInfo: [AnyHashable: Any]) -> CustomerDeepLink? {
        if let deep = userInfo["deep_link"] as? String {
            return CustomerDeepLink(path: deep)
        }
        if let link = userInfo["deepLink"] as? String {
            return CustomerDeepLink(path: link)
        }
        if let category = userInfo["category"] as? String {
            switch category {
            case "chat": return CustomerDeepLink(path: "/chat")
            case "project": return CustomerDeepLink(path: "/projects")
            case "order": return CustomerDeepLink(path: "/requests")
            case "news": return CustomerDeepLink(path: "/news")
            default: return CustomerDeepLink(path: "/notifications")
            }
        }
        return nil
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
