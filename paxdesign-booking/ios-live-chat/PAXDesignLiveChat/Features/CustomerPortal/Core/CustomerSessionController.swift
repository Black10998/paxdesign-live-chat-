import Foundation

@MainActor
final class CustomerSessionController: ObservableObject {
    static let shared = CustomerSessionController()

    let auth = CustomerAuthStore()
    let api = CustomerAPIClient()

    private var activeSessionKey: String?

    private init() {}

    func activate(
        siteURL: String,
        username: String,
        appPassword: String,
        profile: CustomerProfileResponse.Profile
    ) {
        let sessionKey = Self.sessionKey(siteURL: siteURL, username: username, profileId: profile.id)
        if activeSessionKey == sessionKey {
            return
        }
        activeSessionKey = sessionKey

        auth.siteURL = siteURL
        auth.username = username
        auth.appPassword = appPassword
        auth.profile = profile
        auth.isAuthenticated = true
        auth.errorMessage = nil
        api.configure(baseURL: siteURL, auth: auth)
        CustomerKeychain.save(siteURL: siteURL, username: username, appPassword: appPassword)
        AppLockService.shared.bindAccount(scope: "customer-\(profile.id)")
        AppLockService.shared.prepareForLogin()
        CustomerPushService.shared.configure(api: api)
        CustomerChatBadgeStore.shared.configure(userId: profile.id)
        CustomerNotificationsBadgeStore.shared.bindUser(profile.id)
        CustomerChatBadgeSyncService.shared.start(api: api, userId: profile.id)
        Task {
            await CustomerPushService.shared.prepareNotificationRegistration()
            CustomerDeviceSessionService.shared.start(api: api)
        }
    }

    func syncFromAuthStore(_ store: AuthStore) {
        guard store.isLoggedIn, store.isCustomerSession,
              let creds = store.storedAPICredentials() else { return }

        auth.siteURL = store.siteURLString
        auth.username = creds.username
        auth.appPassword = creds.appPassword
        api.configure(baseURL: store.siteURLString, auth: auth)
        auth.isAuthenticated = true

        if let profile = store.customerProfile {
            activate(
                siteURL: store.siteURLString,
                username: creds.username,
                appPassword: creds.appPassword,
                profile: profile
            )
            return
        }

        Task {
            guard let response = try? await api.fetchProfile() else { return }
            activate(
                siteURL: store.siteURLString,
                username: creds.username,
                appPassword: creds.appPassword,
                profile: response.profile
            )
        }
    }

    func deactivate() {
        activeSessionKey = nil
        CustomerDeviceSessionService.shared.stop()
        CustomerChatBadgeSyncService.shared.stop()
        CustomerChatBadgeStore.shared.resetForLogout()
        CustomerNotificationsBadgeStore.shared.resetForLogout()
        auth.logout()
    }

    private static func sessionKey(siteURL: String, username: String, profileId: Int) -> String {
        "\(siteURL.lowercased())|\(username.lowercased())|\(profileId)"
    }
}
