import Foundation
import UserNotifications

@MainActor
final class PushDiagnosticsStore: ObservableObject {
    static let shared = PushDiagnosticsStore()

    @Published private(set) var authorizationStatus: String = "unknown"
    @Published private(set) var deviceTokenPrefix: String = "—"
    @Published private(set) var deviceTokenSuffix: String = "—"
    @Published private(set) var apnsEnvironment: String = "—"
    @Published private(set) var apsEntitlement: String = "—"

    @Published private(set) var registrationRequested: Bool = false
    @Published private(set) var registrationRequestCount: Int = 0
    @Published private(set) var lastRegistrationRequestAt: Date?
    @Published private(set) var apnsResponded: Bool = false
    @Published private(set) var tokenReceived: Bool = false
    @Published private(set) var tokenReceivedAt: Date?
    @Published private(set) var iosRegistrationStatus: String = "—"
    @Published private(set) var iosRegistrationError: String?

    @Published private(set) var serverRegistrationStatus: String = "—"
    @Published private(set) var serverRegistrationAt: Date?
    @Published private(set) var serverPushEnabled: Bool = false
    @Published private(set) var serverUploadAttempted: Bool = false
    @Published private(set) var serverAccepted: Bool = false
    @Published private(set) var lastServerError: String?
    @Published private(set) var lastTestResult: PushTestResult?
    @Published private(set) var isRefreshing = false

    struct PushTestResult: Equatable {
        let sent: Bool
        let pushType: String
        let apnsHTTPStatus: Int
        let tokenPrefix: String
        let environment: String
        let appleResponse: String
        let failureReason: String?
        let testedAt: Date
    }

    private init() {}

    func refreshLocalState(push: PushService) async {
        let settings = await UNUserNotificationCenter.current().notificationSettings()
        authorizationStatus = label(for: settings.authorizationStatus)
        apnsEnvironment = push.apnsEnvironmentLabel
        apsEntitlement = PAXAPNsEnvironment.entitlementValue

        registrationRequested = push.registrationRequestCount > 0
        registrationRequestCount = push.registrationRequestCount
        lastRegistrationRequestAt = push.lastRegistrationRequestAt
        apnsResponded = push.apnsDidRespond
        tokenReceived = push.deviceToken != nil
        tokenReceivedAt = push.tokenReceivedAt

        if let token = push.deviceToken, !token.isEmpty {
            deviceTokenPrefix = String(token.prefix(8))
            deviceTokenSuffix = String(token.suffix(8))
        } else {
            deviceTokenPrefix = "—"
            deviceTokenSuffix = "—"
        }

        if let token = push.deviceToken, !token.isEmpty {
            iosRegistrationStatus = "registered"
            iosRegistrationError = nil
        } else if let error = push.lastIOSRegistrationError, !error.isEmpty {
            iosRegistrationStatus = "failed"
            iosRegistrationError = error
        } else if push.registrationRequestCount > 0 {
            iosRegistrationStatus = push.apnsDidRespond ? "failed" : "waiting"
            iosRegistrationError = push.apnsDidRespond ? "APNs responded without a device token." : nil
        } else {
            iosRegistrationStatus = "not_requested"
            iosRegistrationError = nil
        }
    }

    func recordRegistrationRequested(count: Int, at: Date) {
        registrationRequested = true
        registrationRequestCount = count
        lastRegistrationRequestAt = at
        iosRegistrationStatus = "waiting"
    }

    func recordRegistrationAttemptStarted() {
        iosRegistrationStatus = "waiting"
    }

    func recordRegistrationAttemptFinished(success: Bool, error: String?) {
        if success {
            iosRegistrationStatus = "registered"
            iosRegistrationError = nil
        } else {
            iosRegistrationStatus = "failed"
            iosRegistrationError = error
        }
    }

    func recordTokenReceived(token: String) {
        tokenReceived = true
        tokenReceivedAt = Date()
        apnsResponded = true
        deviceTokenPrefix = String(token.prefix(8))
        deviceTokenSuffix = String(token.suffix(8))
        iosRegistrationStatus = "registered"
        iosRegistrationError = nil
    }

    func recordIOSRegistrationFailure(_ detail: String) {
        apnsResponded = true
        iosRegistrationStatus = "failed"
        iosRegistrationError = detail
    }

    func recordServerRegistration(success: Bool, tokenPrefix: String?, error: String? = nil, pushEnabled: Bool = false, accepted: Bool = false) {
        serverUploadAttempted = true
        serverAccepted = accepted || success
        serverPushEnabled = pushEnabled || success
        if success {
            serverRegistrationStatus = pushEnabled ? "push_enabled" : "registered"
            serverRegistrationAt = Date()
            lastServerError = nil
            if let tokenPrefix, !tokenPrefix.isEmpty {
                deviceTokenPrefix = String(tokenPrefix.prefix(8))
            }
        } else {
            serverRegistrationStatus = "failed"
            lastServerError = error
        }
    }

    func recordServerRegistrationPending() {
        serverRegistrationStatus = "pending"
        serverUploadAttempted = true
    }

    func refreshWithRegistration(auth: AuthStore, push: PushService) async {
        isRefreshing = true
        defer { isRefreshing = false }
        await refreshLocalState(push: push)
    }

    func refreshLocalOnly(push: PushService) async {
        await refreshLocalState(push: push)
    }

    func repairRegistration(auth: AuthStore, push: PushService) async {
        isRefreshing = true
        defer { isRefreshing = false }
        push.resetRegistrationBackoff()
        _ = await push.ensureDeviceToken(maxAttempts: 2, perAttemptTimeout: 25)
        if auth.isLoggedIn {
            await push.registerTokenWithBackend(auth: auth)
        }
        await refreshLocalState(push: push)
    }

    func runTestPush(auth: AuthStore) async {
        guard let api = auth.api else {
            lastTestResult = PushTestResult(
                sent: false,
                pushType: "alert",
                apnsHTTPStatus: 0,
                tokenPrefix: deviceTokenPrefix,
                environment: apnsEnvironment,
                appleResponse: "",
                failureReason: "Not logged in",
                testedAt: Date()
            )
            return
        }

        isRefreshing = true
        defer { isRefreshing = false }

        do {
            let response = try await api.sendPushDiagnosticTest()
            lastTestResult = PushTestResult(
                sent: response.sent,
                pushType: response.pushType,
                apnsHTTPStatus: response.apnsHTTPStatus,
                tokenPrefix: response.tokenPrefix.isEmpty ? deviceTokenPrefix : response.tokenPrefix,
                environment: response.environment.isEmpty ? apnsEnvironment : response.environment,
                appleResponse: response.appleResponse,
                failureReason: response.failureReason,
                testedAt: Date()
            )
        } catch {
            lastTestResult = PushTestResult(
                sent: false,
                pushType: "alert",
                apnsHTTPStatus: 0,
                tokenPrefix: deviceTokenPrefix,
                environment: apnsEnvironment,
                appleResponse: "",
                failureReason: error.localizedDescription,
                testedAt: Date()
            )
        }
    }

    private func label(for status: UNAuthorizationStatus) -> String {
        switch status {
        case .authorized: return "authorized"
        case .denied: return "denied"
        case .notDetermined: return "not_determined"
        case .provisional: return "provisional"
        case .ephemeral: return "ephemeral"
        @unknown default: return "unknown"
        }
    }
}
