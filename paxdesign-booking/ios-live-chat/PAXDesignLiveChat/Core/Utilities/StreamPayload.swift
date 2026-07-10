import Foundation

/// JSON values from SSE payloads arrive as NSNumber/NSString — never assume `as? Int` works.
enum StreamPayload {
    static func int(_ value: Any?) -> Int {
        switch value {
        case let number as NSNumber:
            return number.intValue
        case let int as Int:
            return int
        case let string as String:
            return Int(string) ?? 0
        default:
            return 0
        }
    }

    static func string(_ value: Any?) -> String {
        switch value {
        case let string as String:
            return string
        case let number as NSNumber:
            return number.stringValue
        case let int as Int:
            return String(int)
        default:
            return ""
        }
    }

    static func bool(_ value: Any?) -> Bool {
        switch value {
        case let bool as Bool:
            return bool
        case let number as NSNumber:
            return number.intValue != 0
        case let string as String:
            return string == "1" || string.lowercased() == "true"
        default:
            return false
        }
    }
}
