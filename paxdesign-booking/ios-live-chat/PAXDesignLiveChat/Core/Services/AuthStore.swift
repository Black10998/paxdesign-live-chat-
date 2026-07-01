import Foundation
import Security

@MainActor
final class AuthStore: ObservableObject {
    static let shared = AuthStore()

    static let defaultSiteURL = "https://paxdesign.at"
    static let defaultUsername = "sarah.gta1995@gmail.com"

    @Published private(set) var isLoggedIn = false
    @Published private(set) var profile: AdminProfile?
    @Published private(set) var isBootstrapping = true
    @Published var siteURLString = AuthStore.defaultSiteURL
    @Published var username = AuthStore.defaultUsername
    @Published var appPassword = ""

    private(set) var api: LiveChatAPI?

    private let service = "at.paxdesign.livechat.credentials"

    func loadStoredCredentials() {
        guard let data = KeychainHelper.read(service: service),
              let dict = try? JSONDecoder().decode(StoredCredentials.self, from: data) else {
            applyDefaults()
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
        guard !username.isEmpty, !appPassword.isEmpty else { return }

        do {
            try await login()
        } catch {
            clearStoredCredentials()
        }
    }

    func login() async throws {
        guard let rawURL = URL(string: siteURLString.trimmingCharacters(in: .whitespacesAndNewlines)) else {
            throw LiveChatAPIError.server("Ungültige Server-URL.")
        }

        let site = LiveChatAPI.normalizeSiteURL(rawURL)
        let user = LiveChatAPI.normalizeUsername(username)
        let password = LiveChatAPI.normalizeAppPassword(appPassword)

        guard !user.isEmpty, !password.isEmpty else {
            throw LiveChatAPIError.server("Bitte alle Felder ausfüllen.")
        }

        siteURLString = site.absoluteString
        username = user
        appPassword = password

        let client = LiveChatAPI(siteURL: site, username: user, appPassword: password)
        do {
            let me = try await client.validateLogin()
            api = client
            profile = me
            isLoggedIn = true
            SyncDebugStore.shared.apiBaseURL = client.publicApiBaseURL
            SyncDebugStore.shared.loggedInUser = me.name
            SyncDebugStore.shared.pluginVersion = me.pluginVer

            let stored = StoredCredentials(siteURL: siteURLString, username: username, appPassword: appPassword)
            if let data = try? JSONEncoder().encode(stored) {
                KeychainHelper.save(data, service: service)
            }
        } catch {
            invalidateStoredSession()
            throw error
        }
    }

    func invalidateStoredSession() {
        api = nil
        profile = nil
        isLoggedIn = false
        clearStoredCredentials()
    }

    func logout() {
        invalidateStoredSession()
        applyDefaults()
    }

    private func applyDefaults() {
        siteURLString = Self.defaultSiteURL
        username = Self.defaultUsername
        appPassword = ""
    }

    private func clearStoredCredentials() {
        KeychainHelper.delete(service: service)
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
