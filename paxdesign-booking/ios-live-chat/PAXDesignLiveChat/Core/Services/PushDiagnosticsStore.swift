import Foundation
import UserNotifications

@MainActor
final class PushDiagnosticsStore: ObservableObject {
    static let shared = PushDiagnosticsStore()

    @Published private(set) var authorizationStatus: String = "unknown"
    @Published private(set) var deviceTokenPrefix: String = "—"
    @Published private(set) var apnsEnvironment: String = "—"
    @Published private(set) var serverRegistrationStatus: String = "—"
    @Published private(set) var serverRegistrationAt: Date?
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
        if let token = push.deviceToken, !token.isEmpty {
            deviceTokenPrefix = String(token.prefix(12)) + "…"
        } else {
            deviceTokenPrefix = "—"
        }
        apnsEnvironment = push.apnsEnvironmentLabel
    }

    func recordServerRegistration(success: Bool, tokenPrefix: String?, error: String? = nil) {
        if success {
            serverRegistrationStatus = "registered"
            serverRegistrationAt = Date()
            lastServerError = nil
            if let tokenPrefix, !tokenPrefix.isEmpty {
                deviceTokenPrefix = tokenPrefix + "…"
            }
        } else {
            serverRegistrationStatus = "failed"
            lastServerError = error
        }
    }

    func recordServerRegistrationPending() {
        serverRegistrationStatus = "pending"
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
