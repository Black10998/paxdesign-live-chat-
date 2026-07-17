import SwiftUI

final class CustomerAppDelegate: NSObject, UIApplicationDelegate {
    func application(_ application: UIApplication, didRegisterForRemoteNotificationsWithDeviceToken deviceToken: Data) {
        Task { @MainActor in
            CustomerPushService.shared.didRegister(tokenData: deviceToken)
        }
    }

    func application(_ application: UIApplication, didFailToRegisterForRemoteNotificationsWithError error: Error) {
        Task { @MainActor in
            CustomerPushService.shared.didFailRegistration(error)
        }
    }
}

@main
struct PAXCustomerPortalApp: App {
    @UIApplicationDelegateAdaptor(CustomerAppDelegate.self) private var appDelegate
    @StateObject private var auth = CustomerAuthStore()
    @StateObject private var api = CustomerAPIClient()

    var body: some Scene {
        WindowGroup {
            RootView()
                .environmentObject(auth)
                .environmentObject(api)
                .onAppear {
                    api.configure(baseURL: auth.siteURL, auth: auth)
                }
        }
    }
}
