import Foundation

/// Coalesces server-side push registration and enforces minimum retry spacing.
@MainActor
enum PushRegistrationCoordinator {
    private static var serverRegistrationTask: Task<Void, Never>?
    private static var lastServerRegistrationAttempt: Date?
    private static var lastAutomaticApnsAttempt: Date?

    static let minimumServerRegistrationInterval: TimeInterval = 120
    static let minimumAutomaticApnsInterval: TimeInterval = 300
    static let heartbeatApnsInterval: TimeInterval = 3600

    static func registerWithServer(auth: AuthStore, reason: RegistrationReason) async {
        if let inFlight = serverRegistrationTask {
            await inFlight.value
            return
        }

        if reason != .manualRepair,
           let last = lastServerRegistrationAttempt,
           Date().timeIntervalSince(last) < minimumServerRegistrationInterval {
            return
        }

        let task = Task { @MainActor in
            lastServerRegistrationAttempt = Date()
            await DeviceSessionService.shared.runRegisterTokenWithServer(auth: auth, reason: reason)
        }
        serverRegistrationTask = task
        await task.value
        serverRegistrationTask = nil
    }

    static func shouldAttemptAutomaticApns(reason: RegistrationReason) -> Bool {
        switch reason {
        case .manualRepair, .userAction, .tokenReceived:
            return true
        case .login, .foreground, .heartbeat, .settings:
            guard PushService.shared.canAttemptRegistration else { return false }
            guard let last = lastAutomaticApnsAttempt else { return true }
            let interval = reason == .heartbeat ? heartbeatApnsInterval : minimumAutomaticApnsInterval
            return Date().timeIntervalSince(last) >= interval
        }
    }

    static func noteAutomaticApnsAttempt() {
        lastAutomaticApnsAttempt = Date()
    }

    static func reset() {
        serverRegistrationTask?.cancel()
        serverRegistrationTask = nil
        lastServerRegistrationAttempt = nil
        lastAutomaticApnsAttempt = nil
    }

    enum RegistrationReason: Equatable {
        case login
        case foreground
        case heartbeat
        case settings
        case userAction
        case tokenReceived
        case manualRepair
    }
}
