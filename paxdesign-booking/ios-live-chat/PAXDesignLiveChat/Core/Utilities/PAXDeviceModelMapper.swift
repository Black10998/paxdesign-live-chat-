import Foundation
import UIKit

enum PAXDeviceModelMapper {
    static func friendlyName(for machine: String) -> String {
        let id = machine.trimmingCharacters(in: .whitespacesAndNewlines)
        if id.isEmpty { return UIDevice.current.model }
        if let mapped = identifiers[id] { return mapped }
        if id.hasPrefix("iPhone") { return "iPhone" }
        if id.hasPrefix("iPad") { return "iPad" }
        if id.hasPrefix("Mac") || id == "arm64" { return "Mac" }
        return id
    }

    static var deviceKind: PAXDeviceKind {
        switch UIDevice.current.userInterfaceIdiom {
        case .pad: return .iPad
        case .mac: return .mac
        default: return .iPhone
        }
    }

    private static let identifiers: [String: String] = [
        "iPhone8,1": "iPhone 6s",
        "iPhone8,2": "iPhone 6s Plus",
        "iPhone9,1": "iPhone 7",
        "iPhone9,2": "iPhone 7 Plus",
        "iPhone9,3": "iPhone 7",
        "iPhone9,4": "iPhone 7 Plus",
        "iPhone10,1": "iPhone 8",
        "iPhone10,2": "iPhone 8 Plus",
        "iPhone10,3": "iPhone X",
        "iPhone10,4": "iPhone 8",
        "iPhone10,5": "iPhone 8 Plus",
        "iPhone10,6": "iPhone X",
        "iPhone11,2": "iPhone XS",
        "iPhone11,4": "iPhone XS Max",
        "iPhone11,6": "iPhone XS Max",
        "iPhone11,8": "iPhone XR",
        "iPhone12,1": "iPhone 11",
        "iPhone12,3": "iPhone 11 Pro",
        "iPhone12,5": "iPhone 11 Pro Max",
        "iPhone12,8": "iPhone SE (2nd gen)",
        "iPhone13,1": "iPhone 12 mini",
        "iPhone13,2": "iPhone 12",
        "iPhone13,3": "iPhone 12 Pro",
        "iPhone13,4": "iPhone 12 Pro Max",
        "iPhone14,2": "iPhone 13 Pro",
        "iPhone14,3": "iPhone 13 Pro Max",
        "iPhone14,4": "iPhone 13 mini",
        "iPhone14,5": "iPhone 13",
        "iPhone14,6": "iPhone SE (3rd gen)",
        "iPhone14,7": "iPhone 14",
        "iPhone14,8": "iPhone 14 Plus",
        "iPhone15,2": "iPhone 14 Pro",
        "iPhone15,3": "iPhone 14 Pro Max",
        "iPhone15,4": "iPhone 15",
        "iPhone15,5": "iPhone 15 Plus",
        "iPhone16,1": "iPhone 15 Pro",
        "iPhone16,2": "iPhone 15 Pro Max",
        "iPhone17,1": "iPhone 16 Pro",
        "iPhone17,2": "iPhone 16 Pro Max",
        "iPhone17,3": "iPhone 16",
        "iPhone17,4": "iPhone 16 Plus",
        "iPad13,1": "iPad Air (4th gen)",
        "iPad13,2": "iPad Air (4th gen)",
        "iPad13,4": "iPad Pro 11-inch (3rd gen)",
        "iPad13,5": "iPad Pro 11-inch (3rd gen)",
        "iPad13,6": "iPad Pro 11-inch (3rd gen)",
        "iPad13,7": "iPad Pro 11-inch (3rd gen)",
        "iPad13,8": "iPad Pro 12.9-inch (5th gen)",
        "iPad13,9": "iPad Pro 12.9-inch (5th gen)",
        "iPad13,10": "iPad Pro 12.9-inch (5th gen)",
        "iPad13,11": "iPad Pro 12.9-inch (5th gen)",
        "iPad14,1": "iPad mini (6th gen)",
        "iPad14,2": "iPad mini (6th gen)",
        "iPad14,3": "iPad Pro 11-inch (4th gen)",
        "iPad14,4": "iPad Pro 11-inch (4th gen)",
        "iPad14,5": "iPad Pro 12.9-inch (6th gen)",
        "iPad14,6": "iPad Pro 12.9-inch (6th gen)",
    ]
}

enum PAXDeviceKind {
    case iPhone
    case iPad
    case mac
}
