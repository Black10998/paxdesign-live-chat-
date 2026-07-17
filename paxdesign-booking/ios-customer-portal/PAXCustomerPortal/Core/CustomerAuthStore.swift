import Foundation

@MainActor
final class CustomerAuthStore: ObservableObject {
    @Published var siteURL: String = "https://paxdesign.at"
    @Published var username: String = ""
    @Published var appPassword: String = ""
    @Published var profile: CustomerProfile?
    @Published var isAuthenticated = false
    @Published var errorMessage: String?

    struct CustomerProfile: Codable {
        let id: Int
        let display_name: String
        let email: String
        let verified: Bool
        let role: String
    }

    func login() async {
        errorMessage = nil
        guard !username.isEmpty, !appPassword.isEmpty else {
            errorMessage = String(localized: "Enter your email and application password.")
            return
        }
        isAuthenticated = true
    }

    func logout() {
        username = ""
        appPassword = ""
        profile = nil
        isAuthenticated = false
    }

    var basicAuthHeader: String? {
        guard !username.isEmpty, !appPassword.isEmpty else { return nil }
        let raw = "\(username):\(appPassword)"
        return "Basic \(Data(raw.utf8).base64EncodedString())"
    }
}
