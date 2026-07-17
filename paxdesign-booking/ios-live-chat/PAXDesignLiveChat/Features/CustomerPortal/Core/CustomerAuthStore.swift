import Foundation

@MainActor
final class CustomerAuthStore: ObservableObject {
    @Published var siteURL: String = "https://paxdesign.at"
    @Published var username: String = ""
    @Published var appPassword: String = ""
    @Published var profile: CustomerProfileResponse.Profile?
    @Published var isAuthenticated = false
    @Published var errorMessage: String?

    func restoreSession(api: CustomerAPIClient?) {
        guard let saved = CustomerKeychain.load() else { return }
        siteURL = saved.siteURL
        username = saved.username
        appPassword = saved.appPassword
        if let api {
            Task { await login(api: api, silent: true) }
        }
    }

    func login(api: CustomerAPIClient, silent: Bool = false) async {
        if !silent { errorMessage = nil }
        guard !username.isEmpty, !appPassword.isEmpty else {
            if !silent { errorMessage = String(localized: "Enter your email and password.") }
            return
        }
        api.configure(baseURL: siteURL, auth: self)
        do {
            let response = try await api.fetchProfile()
            profile = response.profile
            isAuthenticated = true
            CustomerKeychain.save(siteURL: siteURL, username: username, appPassword: appPassword)
        } catch {
            profile = nil
            isAuthenticated = false
            if !silent { errorMessage = error.localizedDescription }
        }
    }

    func logout() {
        username = ""
        appPassword = ""
        profile = nil
        isAuthenticated = false
        errorMessage = nil
        CustomerKeychain.clear()
    }

    var basicAuthHeader: String? {
        guard !username.isEmpty, !appPassword.isEmpty else { return nil }
        let raw = "\(username):\(appPassword)"
        return "Basic \(Data(raw.utf8).base64EncodedString())"
    }
}
