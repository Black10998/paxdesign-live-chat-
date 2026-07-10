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

    /// Decode one or many inline messages from an SSE payload.
    static func messages(from payload: [String: Any]) -> [LiveMessage] {
        if let single = LiveMessage.fromStreamPayload(payload["message"]) {
            return [single]
        }
        guard let raw = payload["messages"] else { return [] }
        guard let array = raw as? [Any] else { return [] }

        var decoded: [LiveMessage] = []
        for item in array {
            guard let dict = item as? [String: Any],
                  JSONSerialization.isValidJSONObject(dict),
                  let data = try? JSONSerialization.data(withJSONObject: dict),
                  let message = try? JSONDecoder().decode(LiveMessage.self, from: data) else {
                continue
            }
            decoded.append(message)
        }
        return decoded.sorted { $0.id < $1.id }
    }
}
