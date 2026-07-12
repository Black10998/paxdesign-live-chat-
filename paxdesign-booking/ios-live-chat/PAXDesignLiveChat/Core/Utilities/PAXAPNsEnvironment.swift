import Foundation
import Security

/// Runtime APNs environment detection per Apple entitlements (`aps-environment`).
enum PAXAPNsEnvironment {
    static var label: String {
        isSandbox ? "sandbox" : "production"
    }

    static var isSandbox: Bool {
        #if targetEnvironment(simulator)
        return true
        #else
        if let entitlement = readEntitlement("aps-environment") {
            return entitlement == "development"
        }
        #if DEBUG
        return true
        #else
        return false
        #endif
        #endif
    }

    static var entitlementValue: String {
        readEntitlement("aps-environment") ?? "unknown"
    }

    private static func readEntitlement(_ key: String) -> String? {
        guard let task = SecTaskCreateFromSelf(nil) else { return nil }
        guard let value = SecTaskCopyValueForEntitlement(task, key as CFString) else { return nil }
        return value as? String
    }
}
