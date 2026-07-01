import Foundation
import Security

@MainActor
final class AuthStore: ObservableObject {
    static let shared = AuthStore()

    @Published private(set) var isLoggedIn = false
    @Published private(set) var profile: AdminProfile?
    @Published private(set) var isBootstrapping = true
    @Published var siteURLString = ""
    @Published var username = ""
    @Published var appPassword = ""

    private(set) var api: LiveChatAPI?

    private let service = "at.paxdesign.livechat.credentials"

    func loadStoredCredentials() {
        guard let data = KeychainHelper.read(service: service),
              let dict = try? JSONDecoder().decode(StoredCredentials.self, from: data) else {
            clearFormFields()
            return
        }
        siteURLString = dict.siteURL
        username = dict.username
        appPassword = dict.appPassword
    }

    func bootstrapSession() async {
        loadStoredCredentials()
        defer { isBootstrapping = false }

        guard !isLoggedIn else { return }
        guard !username.isEmpty, !appPassword.isEmpty, !siteURLString.isEmpty else { return }

        do {
            try await login()
        } catch {
            invalidateStoredSession(keepFormFields: true)
        }
    }

    func login() async throws {
        let site = try SecureURLValidator.validateHTTPS(siteURLString)
        let normalizedSite = LiveChatAPI.normalizeSiteURL(site)
        let user = LiveChatAPI.normalizeUsername(username)
        let password = LiveChatAPI.normalizeAppPassword(appPassword)

        guard !user.isEmpty, !password.isEmpty else {
            throw LiveChatAPIError.server("Bitte alle Felder ausfüllen.")
        }

        siteURLString = normalizedSite.absoluteString
        username = user
        appPassword = password

        let client = LiveChatAPI(siteURL: normalizedSite, username: user, appPassword: password)
        do {
            let me = try await client.validateLogin()
            api = client
            profile = me
            isLoggedIn = true

            let stored = StoredCredentials(siteURL: siteURLString, username: username, appPassword: appPassword)
            if let data = try? JSONEncoder().encode(stored) {
                KeychainHelper.save(data, service: service)
            }
        } catch {
            invalidateStoredSession(keepFormFields: true)
            throw error
        }
    }

    /// Called when the server rejects stored credentials (HTTP 401/403).
    func handleUnauthorized() {
        invalidateStoredSession(keepFormFields: true)
    }

    func invalidateStoredSession(keepFormFields: Bool = false) {
        api = nil
        profile = nil
        isLoggedIn = false
        KeychainHelper.delete(service: service)
        if !keepFormFields {
            clearFormFields()
        }
    }

    func logout() {
        invalidateStoredSession(keepFormFields: false)
    }

    private func clearFormFields() {
        siteURLString = ""
        username = ""
        appPassword = ""
    }
}

private struct StoredCredentials: Codable {
    let siteURL: String
    let username: String
    let appPassword: String
}

enum KeychainHelper {
    static func save(_ data: Data, service: String) {
        delete(service: service)
        let query: [String: Any] = [
            kSecClass as String: kSecClassGenericPassword,
            kSecAttrService as String: service,
            kSecValueData as String: data,
            kSecAttrAccessible as String: kSecAttrAccessibleAfterFirstUnlockThisDeviceOnly
        ]
        SecItemAdd(query as CFDictionary, nil)
    }

    static func read(service: String) -> Data? {
        let query: [String: Any] = [
            kSecClass as String: kSecClassGenericPassword,
            kSecAttrService as String: service,
            kSecReturnData as String: true,
            kSecMatchLimit as String: kSecMatchLimitOne
        ]
        var item: CFTypeRef?
        let status = SecItemCopyMatching(query as CFDictionary, &item)
        guard status == errSecSuccess else { return nil }
        return item as? Data
    }

    static func delete(service: String) {
        let query: [String: Any] = [
            kSecClass as String: kSecClassGenericPassword,
            kSecAttrService as String: service
        ]
        SecItemDelete(query as CFDictionary)
    }
}
