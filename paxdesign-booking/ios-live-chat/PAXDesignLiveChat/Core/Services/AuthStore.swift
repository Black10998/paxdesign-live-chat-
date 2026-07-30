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
    @Published var username = ""
    @Published var accountPassword = ""

    private(set) var api: LiveChatAPI?
    private var appPassword = ""
    private var appPasswordUUID = ""
    private var unauthorizedRecoveryTask: Task<Void, Never>?

    private let service = "at.paxdesign.livechat.credentials"

    var siteURLString: String { AppServerConfig.siteURL }
    var isCustomerSession: Bool { sessionMode == .customer }
    var isStaffSession: Bool { sessionMode == .staff }

    /// Internal session credentials minted by the server (not shown in UI).
    func storedAPICredentials() -> (username: String, appPassword: String)? {
        guard !username.isEmpty, !appPassword.isEmpty else { return nil }
        return (username, appPassword)
    }

    private init() {
        loadStoredCredentials()
    }

    func loadStoredCredentials() {
        guard let data = KeychainHelper.read(service: service),
              let dict = try? JSONDecoder().decode(StoredCredentials.self, from: data) else {
            clearFormFields()
            return
        }
        username = dict.username
        appPassword = dict.appPassword
        appPasswordUUID = dict.appPasswordUUID ?? ""
        sessionMode = dict.sessionMode
    }

    func bootstrapSession() async {
        loadStoredCredentials()
        defer { finishBootstrap() }

        guard !isLoggedIn else { return }
        guard !username.isEmpty, !appPassword.isEmpty else { return }

        do {
            if sessionMode == .customer {
                try await loginCustomer()
            } else {
                try await loginStaff()
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
        if isLoggedIn, isCustomerSession {
            CustomerSessionController.shared.syncFromAuthStore(self)
        }
    }

    /// Unified sign-in: email/username + account password. Server decides staff vs customer.
    func login() async throws {
        let login = LiveChatAPI.normalizeUsername(username)
        let password = accountPassword.trimmingCharacters(in: .whitespacesAndNewlines)

        guard !login.isEmpty, !password.isEmpty else {
            throw LiveChatAPIError.server(String(localized: "Please enter your email and password."))
        }

        username = login

        let client = CustomerAPIClient()
        client.useDefaultServer()
        let response = try await client.authMobileLogin(login: login, password: password)

        try await completeMobileLogin(response: response)

        if !isBootstrapping {
            NotificationCenter.default.post(name: .paxInteractiveLoginSucceeded, object: nil)
        }
    }

    func loginWithApple(
        identityToken: String,
        authorizationCode: String?,
        fullName: PersonNameComponents?,
        email: String?
    ) async throws {
        let client = CustomerAPIClient()
        client.useDefaultServer()
        var payload: [String: String] = [
            "identity_token": identityToken,
            "device_label": "PAXDesign iOS",
        ]
        if let authorizationCode, !authorizationCode.isEmpty {
            payload["authorization_code"] = authorizationCode
        }
        if let email, !email.isEmpty {
            payload["email"] = email
        }
        if let given = fullName?.givenName, !given.isEmpty {
            payload["given_name"] = given
        }
        if let family = fullName?.familyName, !family.isEmpty {
            payload["family_name"] = family
        }
        if let given = fullName?.givenName, let family = fullName?.familyName {
            payload["name"] = "\(given) \(family)".trimmingCharacters(in: .whitespaces)
        }

        let response = try await client.authAppleLogin(payload: payload)
        try await completeMobileLogin(response: response)

        if !isBootstrapping {
            NotificationCenter.default.post(name: .paxInteractiveLoginSucceeded, object: nil)
        }
    }

    private func completeMobileLogin(response: MobileLoginResponse) async throws {
        guard response.success == true,
              let mode = response.session_mode,
              let userLogin = response.username,
              let appPass = response.app_password else {
            throw LiveChatAPIError.server(response.message ?? String(localized: "Sign in failed."))
        }

        username = userLogin
        appPassword = LiveChatAPI.normalizeAppPassword(appPass)
        appPasswordUUID = response.app_password_uuid ?? ""

        if mode == "staff" {
            try await loginStaff()
            persistCredentials(mode: .staff)
            Task(priority: .utility) {
                await PlatformSyncService.shared.sync(auth: self)
            }
        } else {
            try await loginCustomer()
            persistCredentials(mode: .customer)
        }
    }

    func loginStaff() async throws {
        let user = LiveChatAPI.normalizeUsername(username)
        let password = LiveChatAPI.normalizeAppPassword(appPassword)

        guard !user.isEmpty, !password.isEmpty else {
            throw LiveChatAPIError.server(String(localized: "Please enter your email and password."))
        }

        let site = try SecureURLValidator.validateHTTPS(AppServerConfig.siteURL)
        let normalizedSite = LiveChatAPI.normalizeSiteURL(site)
        let client = LiveChatAPI(siteURL: normalizedSite, username: user, appPassword: password)
        let me = try await client.validateLogin()
        applyStaffSession(client: client, profile: me)
    }

    func loginCustomer() async throws {
        let user = LiveChatAPI.normalizeUsername(username)
        let password = LiveChatAPI.normalizeAppPassword(appPassword)

        guard !user.isEmpty, !password.isEmpty else {
            throw CustomerAPIError.unauthorized
        }

        let bridgeAuth = CustomerAuthStore()
        bridgeAuth.siteURL = siteURLString
        bridgeAuth.username = user
        bridgeAuth.appPassword = password

        let client = CustomerAPIClient()
        client.configure(baseURL: siteURLString, auth: bridgeAuth)

        do {
            let response = try await client.fetchProfile()
            applyCustomerSession(profile: response.profile)
            CustomerSessionController.shared.activate(
                siteURL: siteURLString,
                username: user,
                appPassword: password,
                profile: response.profile
            )
        } catch CustomerAPIError.unauthorized {
            invalidateStoredSession(keepFormFields: true)
            throw CustomerAPIError.unauthorized
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
            appPasswordUUID: appPasswordUUID.isEmpty ? nil : appPasswordUUID,
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
            guard !username.isEmpty, !appPassword.isEmpty else {
                invalidateStoredSession(keepFormFields: true)
                return
            }
            do {
                if sessionMode == .customer {
                    try await loginCustomer()
                } else {
                    try await loginStaff()
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
        appPassword = ""
        appPasswordUUID = ""
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
        let customerToken = CustomerPushService.shared.deviceToken
        let tokenToUnregister = PushService.shared.deviceToken
        let logoutUUID = appPasswordUUID
        let logoutUser = username
        let logoutPass = appPassword
        let wasCustomer = isCustomerSession

        DeviceSessionService.shared.stop()
        CustomerNotificationsBadgeStore.shared.resetForLogout()
        PlatformSyncService.shared.reset()

        if wasCustomer, let token = customerToken, !logoutUser.isEmpty, !logoutPass.isEmpty {
            let bridgeAuth = CustomerAuthStore()
            bridgeAuth.siteURL = AppServerConfig.siteURL
            bridgeAuth.username = logoutUser
            bridgeAuth.appPassword = logoutPass
            let client = CustomerAPIClient()
            client.configure(baseURL: AppServerConfig.siteURL, auth: bridgeAuth)
            Task {
                try? await client.unregisterPush(token: token, deviceID: PAXDeviceInfo.deviceId)
            }
        }

        invalidateStoredSession(keepFormFields: false)
        PushDiagnosticsStore.shared.recordServerRegistration(success: false, tokenPrefix: nil, error: "logged out")

        if let apiClient, let token = tokenToUnregister {
            Task {
                try? await apiClient.unregisterAPNs(token: token)
            }
        }

        if !logoutUUID.isEmpty, !logoutUser.isEmpty, !logoutPass.isEmpty {
            Task {
                let bridgeAuth = CustomerAuthStore()
                bridgeAuth.siteURL = AppServerConfig.siteURL
                bridgeAuth.username = logoutUser
                bridgeAuth.appPassword = logoutPass
                let client = CustomerAPIClient()
                client.configure(baseURL: AppServerConfig.siteURL, auth: bridgeAuth)
                try? await client.authMobileLogout(appPasswordUUID: logoutUUID)
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
        guard !username.isEmpty, !appPassword.isEmpty else { return }
        guard let site = try? SecureURLValidator.validateHTTPS(siteURLString) else { return }
        let normalizedSite = LiveChatAPI.normalizeSiteURL(site)
        let user = LiveChatAPI.normalizeUsername(username)
        let password = LiveChatAPI.normalizeAppPassword(appPassword)
        guard !user.isEmpty, !password.isEmpty else { return }
        if sessionMode == .customer {
            isLoggedIn = true
            CustomerSessionController.shared.syncFromAuthStore(self)
            return
        }
        api = LiveChatAPI(siteURL: normalizedSite, username: user, appPassword: password)
        sessionMode = .staff
        isLoggedIn = true
    }

    private func clearFormFields() {
        username = ""
        accountPassword = ""
    }

    #if DEBUG
    func configureLayoutVerification(mode: PAXLayoutVerification.Mode) {
        isBootstrapping = false
        isLoggedIn = true
        sessionEpoch = UUID()
        switch mode {
        case .customer:
            sessionMode = .customer
            profile = nil
            customerProfile = CustomerProfileResponse.Profile(
                id: 1,
                display_name: "Layout Verify",
                email: "verify@paxdesign.test",
                verified: true,
                role: "customer",
                avatar_url: nil
            )
            username = "layout-verify@paxdesign.test"
            appPassword = "layout-verify"
            CustomerSessionController.shared.activate(
                siteURL: siteURLString,
                username: username,
                appPassword: appPassword,
                profile: customerProfile!
            )
        case .staff:
            sessionMode = .staff
            customerProfile = nil
            profile = AdminProfile.layoutVerificationStub
            username = "layout-verify"
            appPassword = "layout-verify"
            if let site = URL(string: AppServerConfig.siteURL) {
                api = LiveChatAPI(siteURL: site, username: "layout-verify", appPassword: "layout-verify")
            }
        }
    }
    #endif
}

private struct StoredCredentials: Codable {
    let siteURL: String
    let username: String
    let appPassword: String
    let appPasswordUUID: String?
    let sessionMode: SessionMode?

    init(siteURL: String, username: String, appPassword: String, appPasswordUUID: String? = nil, sessionMode: SessionMode? = nil) {
        self.siteURL = siteURL
        self.username = username
        self.appPassword = appPassword
        self.appPasswordUUID = appPasswordUUID
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
