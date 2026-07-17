import Foundation
import Security

enum SessionMode: String, Codable {
    case staff
    case customer
}

@MainActor
final class AuthStore: ObservableObject {
    static let shared = AuthStore()

    @Published private(set) var isLoggedIn = false
    @Published private(set) var profile: AdminProfile?
    @Published private(set) var customerProfile: CustomerProfileResponse.Profile?
    @Published private(set) var sessionMode: SessionMode?
    @Published private(set) var isBootstrapping = true
    /// Bumped on every login/logout so SwiftUI rebuilds the shell and drops stale UI state.
    @Published private(set) var sessionEpoch = UUID()
    @Published var siteURLString = ""
    @Published var username = ""
    @Published var appPassword = ""

    private(set) var api: LiveChatAPI?
    private var unauthorizedRecoveryTask: Task<Void, Never>?

    private let service = "at.paxdesign.livechat.credentials"

    var isCustomerSession: Bool { sessionMode == .customer }
    var isStaffSession: Bool { sessionMode == .staff }

    private init() {
        loadStoredCredentials()
    }

    func loadStoredCredentials() {
        guard let data = KeychainHelper.read(service: service),
              let dict = try? JSONDecoder().decode(StoredCredentials.self, from: data) else {
            clearFormFields()
            return
        }
        siteURLString = dict.siteURL
        username = dict.username
        appPassword = dict.appPassword
        sessionMode = dict.sessionMode
    }

    func bootstrapSession() async {
        loadStoredCredentials()
        defer { finishBootstrap() }

        guard !isLoggedIn else { return }
        guard !username.isEmpty, !appPassword.isEmpty, !siteURLString.isEmpty else { return }

        do {
            if sessionMode == .customer {
                try await loginCustomer()
            } else {
                try await login()
            }
        } catch {
            if case LiveChatAPIError.unauthorized = error {
                invalidateStoredSession(keepFormFields: true)
            } else if case CustomerAPIError.unauthorized = error {
                invalidateStoredSession(keepFormFields: true)
            } else {
                restoreOfflineSession()
            }
        }
    }

    func finishBootstrap() {
        isBootstrapping = false
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
            applyStaffSession(client: client, profile: me)
            persistCredentials(mode: .staff)
            Task(priority: .utility) {
                await PlatformSyncService.shared.sync(auth: self)
            }
        } catch LiveChatAPIError.unauthorized {
            try await loginCustomer()
        } catch {
            if case LiveChatAPIError.unauthorized = error {
                invalidateStoredSession(keepFormFields: true)
            }
            throw error
        }
    }

    func loginCustomer() async throws {
        let site = try SecureURLValidator.validateHTTPS(siteURLString)
        let normalizedSite = LiveChatAPI.normalizeSiteURL(site)
        let user = LiveChatAPI.normalizeUsername(username)
        let password = LiveChatAPI.normalizeAppPassword(appPassword)

        guard !user.isEmpty, !password.isEmpty else {
            throw CustomerAPIError.unauthorized
        }

        siteURLString = normalizedSite.absoluteString
        username = user
        appPassword = password

        let bridgeAuth = CustomerAuthStore()
        bridgeAuth.siteURL = siteURLString
        bridgeAuth.username = username
        bridgeAuth.appPassword = appPassword

        let client = CustomerAPIClient()
        client.configure(baseURL: siteURLString, auth: bridgeAuth)

        do {
            let response = try await client.fetchProfile()
            applyCustomerSession(profile: response.profile)
            CustomerSessionController.shared.activate(
                siteURL: siteURLString,
                username: username,
                appPassword: appPassword,
                profile: response.profile
            )
            persistCredentials(mode: .customer)
        } catch CustomerAPIError.unauthorized {
            invalidateStoredSession(keepFormFields: true)
            throw error
        } catch CustomerAPIError.http(401), CustomerAPIError.http(403) {
            invalidateStoredSession(keepFormFields: true)
            throw CustomerAPIError.unauthorized
        } catch {
            throw error
        }
    }

    private func applyStaffSession(client: LiveChatAPI, profile me: AdminProfile) {
        api = client
        profile = me
        customerProfile = nil
        sessionMode = .staff
        isLoggedIn = true
        sessionEpoch = UUID()
        AppSettingsStore.shared.onboardingCompleted = me.onboardingCompleted
        CustomerSessionController.shared.deactivate()
    }

    private func applyCustomerSession(profile: CustomerProfileResponse.Profile) {
        api = nil
        self.profile = nil
        customerProfile = profile
        sessionMode = .customer
        isLoggedIn = true
        sessionEpoch = UUID()
    }

    private func persistCredentials(mode: SessionMode) {
        let stored = StoredCredentials(
            siteURL: siteURLString,
            username: username,
            appPassword: appPassword,
            sessionMode: mode
        )
        if let data = try? JSONEncoder().encode(stored) {
            let service = self.service
            Task.detached(priority: .utility) {
                KeychainHelper.save(data, service: service)
            }
        }
    }

    /// Called when the server rejects stored credentials (HTTP 401/403).
    func handleUnauthorized() {
        guard unauthorizedRecoveryTask == nil else { return }
        unauthorizedRecoveryTask = Task { @MainActor [weak self] in
            defer { self?.unauthorizedRecoveryTask = nil }
            guard let self else { return }
            guard !username.isEmpty, !appPassword.isEmpty, !siteURLString.isEmpty else {
                invalidateStoredSession(keepFormFields: true)
                return
            }
            do {
                if sessionMode == .customer {
                    try await loginCustomer()
                } else {
                    try await login()
                }
            } catch {
                if case LiveChatAPIError.unauthorized = error {
                    invalidateStoredSession(keepFormFields: true)
                } else {
                    restoreOfflineSession()
                }
            }
        }
    }

    func invalidateStoredSession(keepFormFields: Bool = false) {
        unauthorizedRecoveryTask?.cancel()
        unauthorizedRecoveryTask = nil
        api = nil
        profile = nil
        customerProfile = nil
        sessionMode = nil
        CustomerSessionController.shared.deactivate()
        isLoggedIn = false
        sessionEpoch = UUID()
        let service = self.service
        Task.detached(priority: .utility) {
            KeychainHelper.delete(service: service)
        }
        if !keepFormFields {
            clearFormFields()
        }
        if !isLoggedIn {
            PlatformSyncService.shared.reset()
        }
    }

    func logout() {
        unauthorizedRecoveryTask?.cancel()
        unauthorizedRecoveryTask = nil
        let apiClient = api
        let tokenToUnregister = PushService.shared.deviceToken
        DeviceSessionService.shared.stop()
        PlatformSyncService.shared.reset()
        invalidateStoredSession(keepFormFields: false)
        PushDiagnosticsStore.shared.recordServerRegistration(success: false, tokenPrefix: nil, error: "logged out")
        if let apiClient, let token = tokenToUnregister {
            Task {
                try? await apiClient.unregisterAPNs(token: token)
            }
        }
    }

    func refreshProfile() async {
        guard let api else { return }
        if let me = try? await api.validateLogin() {
            profile = me
            AppSettingsStore.shared.onboardingCompleted = me.onboardingCompleted
        }
    }

    func applyProfileUpdate(_ updated: AdminProfile) {
        profile = updated
    }

    private func restoreOfflineSession() {
        guard !username.isEmpty, !appPassword.isEmpty, !siteURLString.isEmpty else { return }
        guard let site = try? SecureURLValidator.validateHTTPS(siteURLString) else { return }
        let normalizedSite = LiveChatAPI.normalizeSiteURL(site)
        let user = LiveChatAPI.normalizeUsername(username)
        let password = LiveChatAPI.normalizeAppPassword(appPassword)
        guard !user.isEmpty, !password.isEmpty else { return }
        if sessionMode == .customer {
            isLoggedIn = true
            return
        }
        api = LiveChatAPI(siteURL: normalizedSite, username: user, appPassword: password)
        sessionMode = .staff
        isLoggedIn = true
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
    let sessionMode: SessionMode?

    init(siteURL: String, username: String, appPassword: String, sessionMode: SessionMode? = nil) {
        self.siteURL = siteURL
        self.username = username
        self.appPassword = appPassword
        self.sessionMode = sessionMode
    }
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
