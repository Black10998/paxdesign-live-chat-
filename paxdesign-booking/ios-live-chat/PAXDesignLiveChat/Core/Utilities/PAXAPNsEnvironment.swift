import Foundation

/// APNs environment detection for TestFlight/App Store (production) vs debug/simulator (sandbox).
enum PAXAPNsEnvironment {
    static var label: String {
        isSandbox ? "sandbox" : "production"
    }

    static var isSandbox: Bool {
        #if targetEnvironment(simulator)
        return true
        #else
        #if DEBUG
        return true
        #else
        return false
        #endif
        #endif
    }

    /// Mirrors the signed `aps-environment` entitlement value for diagnostics.
    static var entitlementValue: String {
        isSandbox ? "development" : "production"
    }
}
