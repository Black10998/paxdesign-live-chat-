import Foundation

@MainActor
final class CustomerSessionController: ObservableObject {
    static let shared = CustomerSessionController()

    let auth = CustomerAuthStore()
    let api = CustomerAPIClient()

    private init() {}

    func activate(
        siteURL: String,
        username: String,
        appPassword: String,
        profile: CustomerProfileResponse.Profile
    ) {
        auth.siteURL = siteURL
        auth.username = username
        auth.appPassword = appPassword
        auth.profile = profile
        auth.isAuthenticated = true
        auth.errorMessage = nil
        api.configure(baseURL: siteURL, auth: auth)
        CustomerKeychain.save(siteURL: siteURL, username: username, appPassword: appPassword)
    }

    func syncFromAuthStore(_ store: AuthStore) {
        guard let profile = store.customerProfile,
              let creds = store.storedAPICredentials() else { return }
        activate(
            siteURL: store.siteURLString,
            username: creds.username,
            appPassword: creds.appPassword,
            profile: profile
        )
    }

    func deactivate() {
        auth.logout()
    }
}
