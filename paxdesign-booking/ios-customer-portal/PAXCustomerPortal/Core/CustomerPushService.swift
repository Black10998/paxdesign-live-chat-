import Foundation
import UIKit
import UserNotifications

@MainActor
final class CustomerPushService: NSObject, ObservableObject {
    static let shared = CustomerPushService()

    @Published private(set) var deviceToken: String?
    @Published private(set) var authorizationStatus: UNAuthorizationStatus = .notDetermined

    private weak var api: CustomerAPIClient?

    func configure(api: CustomerAPIClient) {
        self.api = api
    }

    func requestAuthorizationAndRegister() async {
        let center = UNUserNotificationCenter.current()
        center.delegate = self
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
        Task {
            try? await api?.registerPush(token: token, deviceID: UIDevice.current.identifierForVendor?.uuidString ?? UUID().uuidString)
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

extension CustomerPushService: UNUserNotificationCenterDelegate {
    nonisolated func userNotificationCenter(
        _ center: UNUserNotificationCenter,
        willPresent notification: UNNotification
    ) async -> UNNotificationPresentationOptions {
        [.banner, .sound, .badge]
    }

    nonisolated func userNotificationCenter(
        _ center: UNUserNotificationCenter,
        didReceive response: UNNotificationResponse
    ) async {
        let info = response.notification.request.content.userInfo
        if let link = await CustomerPushService.shared.handleNotification(userInfo: info) {
            await MainActor.run {
                CustomerDeepLinkRouter.shared.pending = link
            }
        }
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
