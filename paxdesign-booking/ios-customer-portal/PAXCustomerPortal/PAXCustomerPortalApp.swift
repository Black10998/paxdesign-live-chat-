import SwiftUI

@main
struct PAXCustomerPortalApp: App {
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
