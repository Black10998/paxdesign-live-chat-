import Foundation
import UIKit
import os

enum PAXDeviceInfo {
    static var deviceId: String {
        let key = "pax.device.id"
        if let existing = UserDefaults.standard.string(forKey: key), !existing.isEmpty {
            return existing
        }
        let id = UIDevice.current.identifierForVendor?.uuidString ?? UUID().uuidString
        UserDefaults.standard.set(id, forKey: key)
        return id
    }

    static var deviceName: String {
        UIDevice.current.name
    }

    static var deviceModel: String {
        PAXDeviceModelMapper.friendlyName(for: machineIdentifier)
    }

    static var machineIdentifier: String {
        var systemInfo = utsname()
        uname(&systemInfo)
        return withUnsafePointer(to: &systemInfo.machine) {
            $0.withMemoryRebound(to: CChar.self, capacity: 1) {
                String(cString: $0)
            }
        }
    }

    static var deviceKind: PAXDeviceKind {
        PAXDeviceModelMapper.deviceKind
    }

    static var osVersion: String {
        "\(UIDevice.current.systemName) \(UIDevice.current.systemVersion)"
    }

    static var appVersion: String {
        PAXAppInfo.fullVersion
    }

    static var registrationPayload: [String: Any] {
        [
            "device_id": deviceId,
            "device_name": deviceName,
            "device_model": deviceModel,
            "os_version": osVersion,
            "app_version": appVersion
        ]
    }
}

@MainActor
final class DeviceSessionService: ObservableObject {
    static let shared = DeviceSessionService()

    private var heartbeatTask: Task<Void, Never>?
    private var tokenObservationTask: Task<Void, Never>?
    private var lastRegisteredToken: String?
    private weak var observedAuth: AuthStore?

    func resetRegistrationState() {
        lastRegisteredToken = nil
    }

    func start(auth: AuthStore) {
        stop()
        observedAuth = auth
        heartbeatTask = Task { [weak self] in
            while !Task.isCancelled {
                await self?.sendHeartbeat(auth: auth)
                try? await Task.sleep(nanoseconds: 300_000_000_000) // 5 min
            }
        }
        tokenObservationTask = Task { [weak self] in
            await self?.observeTokenChanges(auth: auth)
        }
    }

    func stop() {
        heartbeatTask?.cancel()
        heartbeatTask = nil
        tokenObservationTask?.cancel()
        tokenObservationTask = nil
        observedAuth = nil
        lastRegisteredToken = nil
    }

    func registerWithPush(auth: AuthStore) async {
        guard PushService.shared.canAttemptRegistration || PushService.shared.deviceToken != nil else {
            await registerSessionWithoutToken(auth: auth)
            if let reason = PushService.shared.registrationBlockedReason {
                PushDiagnosticsStore.shared.recordServerRegistration(
                    success: false,
                    tokenPrefix: nil,
                    error: reason,
                    accepted: false
                )
            }
            return
        }
        await registerTokenWithServer(auth: auth)
        await sendHeartbeat(auth: auth)
    }

    private func registerTokenWithServer(auth: AuthStore) async {
        guard auth.isLoggedIn, let api = auth.api else { return }

        PushDiagnosticsStore.shared.recordServerRegistrationPending()

        if !PushService.shared.canAttemptRegistration, PushService.shared.deviceToken == nil {
            await registerSessionWithoutToken(auth: auth)
            return
        }

        let tokenResult = await PushService.shared.ensureDeviceToken(maxAttempts: 2, perAttemptTimeout: 20)
        let token: String
        switch tokenResult {
        case .success(let resolvedToken):
            token = resolvedToken
        case .failure(let failure):
            await registerSessionWithoutToken(auth: auth)
            let message = failure.detailedMessage
            PushRegistrationDiagnostics.registerFailed(message)
            PushDiagnosticsStore.shared.recordServerRegistration(success: false, tokenPrefix: nil, error: message)
            return
        }

        if token == lastRegisteredToken {
            let verified = await verifyServerPushState(auth: auth, token: token)
            PushDiagnosticsStore.shared.recordServerRegistration(
                success: true,
                tokenPrefix: String(token.prefix(12)),
                pushEnabled: verified,
                accepted: true
            )
            return
        }

        var lastError: Error?
        var accepted = false
        for attempt in 1...3 {
            do {
                let response = try await api.registerAPNs(
                    token: token,
                    sandbox: PAXAPNsEnvironment.isSandbox,
                    metadata: PAXDeviceInfo.registrationPayload
                )
                accepted = response.ok
                if !accepted {
                    throw LiveChatAPIError.rejected(response.error ?? "Server rejected APNs token.")
                }
                lastRegisteredToken = token
                PushRegistrationDiagnostics.registerSucceeded(tokenPrefix: String(token.prefix(12)))
                lastError = nil
                break
            } catch {
                lastError = error
                if attempt < 3 {
                    try? await Task.sleep(nanoseconds: UInt64(attempt) * 1_000_000_000)
                }
            }
        }

        let pushEnabled = await verifyServerPushState(auth: auth, token: token)
        if let lastError {
            PushRegistrationDiagnostics.registerFailed(lastError.localizedDescription)
            PushDiagnosticsStore.shared.recordServerRegistration(
                success: false,
                tokenPrefix: String(token.prefix(12)),
                error: lastError.localizedDescription,
                pushEnabled: pushEnabled,
                accepted: accepted
            )
            return
        }

        PushDiagnosticsStore.shared.recordServerRegistration(
            success: true,
            tokenPrefix: String(token.prefix(12)),
            pushEnabled: pushEnabled,
            accepted: accepted
        )
    }

    private func verifyServerPushState(auth: AuthStore, token: String) async -> Bool {
        guard let api = auth.api else { return false }
        do {
            let response = try await api.fetchEmployeeDevices(currentDeviceId: PAXDeviceInfo.deviceId)
            let current = response.devices.first { $0.deviceId == PAXDeviceInfo.deviceId }
            return current?.pushRegistered == true
        } catch {
            return false
        }
    }

    private func registerSessionWithoutToken(auth: AuthStore) async {
        guard auth.isLoggedIn, let api = auth.api else { return }
        _ = try? await api.sendDeviceHeartbeat(metadata: heartbeatPayload(includeToken: false))
    }

    private func heartbeatPayload(includeToken: Bool = true) -> [String: Any] {
        var payload = PAXDeviceInfo.registrationPayload
        if includeToken, let token = PushService.shared.deviceToken {
            payload["device_token"] = token
            payload["sandbox"] = PAXAPNsEnvironment.isSandbox
            payload["bundle_id"] = Bundle.main.bundleIdentifier ?? "at.paxdesign.livechat"
        }
        return payload
    }

    private func sendHeartbeat(auth: AuthStore) async {
        guard auth.isLoggedIn, let api = auth.api else { return }

        if PushService.shared.deviceToken == nil, PushService.shared.canAttemptRegistration {
            _ = await PushService.shared.ensureDeviceToken(maxAttempts: 1, perAttemptTimeout: 15)
        } else if PushService.shared.deviceToken != lastRegisteredToken, PushService.shared.deviceToken != nil {
            await registerTokenWithServer(auth: auth)
        }

        do {
            let response = try await api.sendDeviceHeartbeat(metadata: heartbeatPayload())
            if response.pushRegistered {
                PushDiagnosticsStore.shared.recordServerRegistration(
                    success: true,
                    tokenPrefix: PushService.shared.deviceToken.map { String($0.prefix(12)) },
                    pushEnabled: true,
                    accepted: true
                )
            }
        } catch {
            if case LiveChatAPIError.server(let message) = error {
                let lowered = message.lowercased()
                if lowered.contains("revoked")
                    || lowered.contains("awaiting administrator approval")
                    || lowered.contains("not approved") {
                    await PushService.shared.unregisterTokenFromBackend(auth: auth)
                    auth.logout()
                    return
                }
            }
            if case LiveChatAPIError.unauthorized = error {
                auth.handleUnauthorized()
            }
        }
    }

    private func observeTokenChanges(auth: AuthStore) async {
        var lastSeen = PushService.shared.deviceToken
        for await token in PushService.shared.$deviceToken.values {
            if token != lastSeen {
                lastSeen = token
                if token != nil {
                    await registerTokenWithServer(auth: auth)
                }
            }
        }
    }
}

enum PushRegistrationDiagnostics {
    private static let log = Logger(subsystem: "at.paxdesign.livechat", category: "Push")

    static func registerFailed(_ message: String) {
        log.error("APNs register failed: \(message, privacy: .public)")
    }

    static func registerSucceeded(tokenPrefix: String) {
        log.info("APNs register succeeded token_prefix=\(tokenPrefix, privacy: .public)")
    }
}
