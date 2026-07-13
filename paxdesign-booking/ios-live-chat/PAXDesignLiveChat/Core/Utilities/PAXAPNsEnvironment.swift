import Foundation
import Security

/// Runtime APNs environment detection from the signed `aps-environment` entitlement.
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

    /// Actual signed entitlement value, or "missing" when absent from the app binary.
    static var entitlementValue: String {
        readEntitlement("aps-environment") ?? "missing"
    }

    static var hasPushEntitlement: Bool {
        guard let value = readEntitlement("aps-environment"), !value.isEmpty else {
            return false
        }
        return value == "production" || value == "development"
    }

    private static func readEntitlement(_ key: String) -> String? {
        PAXCopyEntitlement(key)
    }
}
