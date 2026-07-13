import Foundation

/// Reads push entitlements from the app bundle's embedded provisioning profile.
enum PAXEmbeddedProvisionReader {
    static func apsEnvironmentValue() -> String? {
        guard let url = Bundle.main.url(forResource: "embedded", withExtension: "mobileprovision"),
              let data = try? Data(contentsOf: url),
              let ascii = String(data: data, encoding: .ascii),
              let xmlStart = ascii.range(of: "<?xml"),
              let xmlEnd = ascii.range(of: "</plist>", range: xmlStart.lowerBound..<ascii.endIndex) else {
            return nil
        }

        let xml = String(ascii[xmlStart.lowerBound...xmlEnd.upperBound])
        guard let plistData = xml.data(using: .utf8),
              let plist = try? PropertyListSerialization.propertyList(from: plistData, options: [], format: nil) as? [String: Any],
              let entitlements = plist["Entitlements"] as? [String: Any],
              let aps = entitlements["aps-environment"] as? String,
              !aps.isEmpty else {
            return nil
        }
        return aps
    }
}
