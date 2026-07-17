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

    private struct ProfileResponse: Decodable {
        struct Profile: Decodable {
            let id: Int
            let display_name: String
            let email: String
            let verified: Bool
            let role: String
        }
        let profile: Profile
    }

    func login(api: CustomerAPIClient) async {
        errorMessage = nil
        guard !username.isEmpty, !appPassword.isEmpty else {
            errorMessage = String(localized: "Enter your email and application password.")
            return
        }
        api.configure(baseURL: siteURL, auth: self)
        do {
            let response: ProfileResponse = try await api.get("/customer/profile", as: ProfileResponse.self)
            profile = CustomerProfile(
                id: response.profile.id,
                display_name: response.profile.display_name,
                email: response.profile.email,
                verified: response.profile.verified,
                role: response.profile.role
            )
            isAuthenticated = true
        } catch {
            profile = nil
            isAuthenticated = false
            errorMessage = error.localizedDescription
        }
    }

    func logout() {
        username = ""
        appPassword = ""
        profile = nil
        isAuthenticated = false
        errorMessage = nil
    }

    var basicAuthHeader: String? {
        guard !username.isEmpty, !appPassword.isEmpty else { return nil }
        let raw = "\(username):\(appPassword)"
        return "Basic \(Data(raw.utf8).base64EncodedString())"
    }
}
