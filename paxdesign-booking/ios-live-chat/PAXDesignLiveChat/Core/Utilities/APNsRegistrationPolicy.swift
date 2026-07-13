import Foundation

/// Backoff and permanent-failure policy for iOS APNs registration retries.
@MainActor
enum APNsRegistrationPolicy {
    static let permanentFailureCooldown: TimeInterval = 86_400
    static let maxRetryCooldown: TimeInterval = 900
    static let baseRetryCooldown: TimeInterval = 60

    static func isPermanentFailure(_ error: Error) -> Bool {
        let nsError = error as NSError
        if nsError.domain == NSCocoaErrorDomain && nsError.code == 3000 {
            return true
        }
        let text = nsError.localizedDescription.lowercased()
        return text.contains("aps-environment") || text.contains("entitlement")
    }

    static func isPermanentFailureMessage(_ message: String) -> Bool {
        let text = message.lowercased()
        if text.contains("nscocoaerrordomain (3000)") || text.contains("(3000)") {
            return text.contains("aps-environment") || text.contains("entitlement")
        }
        return text.contains("no valid 'aps-environment' entitlement")
    }

    static func isPermanentFailure(_ failure: APNsRegistrationFailure) -> Bool {
        switch failure {
        case .simulator:
            return true
        case .iosError(_, let code, let message):
            if code == 3000 { return true }
            return isPermanentFailureMessage(message)
        case .notAuthorized, .timeout:
            return false
        }
    }

    static func retryCooldown(afterFailures failures: Int) -> TimeInterval {
        guard failures > 0 else { return 0 }
        let scaled = baseRetryCooldown * pow(2.0, Double(min(failures - 1, 4)))
        return min(scaled, maxRetryCooldown)
    }
}
