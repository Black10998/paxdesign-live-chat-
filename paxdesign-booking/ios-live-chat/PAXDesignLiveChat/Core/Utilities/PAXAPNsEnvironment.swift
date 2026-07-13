import Foundation

/// APNs environment detection for TestFlight/App Store (production) vs debug/simulator (sandbox).
enum PAXAPNsEnvironment {
    private static let signedEntitlementPlistKey = "PAXSignedAPSEnvironment"

    static var label: String {
        isSandbox ? "sandbox" : "production"
    }

    static var isSandbox: Bool {
        #if targetEnvironment(simulator)
        return true
        #else
        switch entitlementValue {
        case "development":
            return true
        case "production":
            return false
        default:
            #if DEBUG
            return true
            #else
            return false
            #endif
        }
        #endif
    }

    /// Build-time signed entitlement mirrored into Info.plist, or "missing" after iOS reports error 3000.
    static var entitlementValue: String {
        if PAXPushEntitlementState.runtimeMissing {
            return "missing"
        }
        if let fromPlist = Bundle.main.infoDictionary?[signedEntitlementPlistKey] as? String,
           !fromPlist.isEmpty {
            return fromPlist
        }
        #if DEBUG
        return "development"
        #else
        return "production"
        #endif
    }

    static var hasPushEntitlement: Bool {
        let value = entitlementValue
        return value == "production" || value == "development"
    }
}
