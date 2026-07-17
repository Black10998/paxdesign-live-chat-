import Foundation

enum CustomerPortalDecode {
    static func string<C: CodingKey>(_ container: KeyedDecodingContainer<C>, _ key: C) -> String {
        if let value = try? container.decode(String.self, forKey: key) {
            return value
        }
        if let value = try? container.decode(Int.self, forKey: key) {
            return String(value)
        }
        if let value = try? container.decode(Double.self, forKey: key) {
            return String(value)
        }
        return ""
    }

    static func int<C: CodingKey>(_ container: KeyedDecodingContainer<C>, _ key: C) -> Int {
        if let value = try? container.decode(Int.self, forKey: key) {
            return value
        }
        if let value = try? container.decode(String.self, forKey: key), let parsed = Int(value) {
            return parsed
        }
        return 0
    }

    static func optionalInt<C: CodingKey>(_ container: KeyedDecodingContainer<C>, _ key: C) -> Int? {
        if let value = try? container.decodeIfPresent(Int.self, forKey: key) {
            return value
        }
        if let value = try? container.decodeIfPresent(String.self, forKey: key), let parsed = Int(value) {
            return parsed
        }
        return nil
    }

    static func optionalBool<C: CodingKey>(_ container: KeyedDecodingContainer<C>, _ key: C) -> Bool? {
        if let value = try? container.decodeIfPresent(Bool.self, forKey: key) {
            return value
        }
        if let value = try? container.decodeIfPresent(String.self, forKey: key) {
            switch value.lowercased() {
            case "1", "true", "yes": return true
            case "0", "false", "no": return false
            default: return nil
            }
        }
        if let value = try? container.decodeIfPresent(Int.self, forKey: key) {
            return value != 0
        }
        return nil
    }

    static func decodeChatMessages<C: CodingKey>(
        from container: KeyedDecodingContainer<C>,
        key: C
    ) -> [CustomerChatPoll.ChatMessage] {
        guard var array = try? container.nestedUnkeyedContainer(forKey: key) else {
            return []
        }
        var messages: [CustomerChatPoll.ChatMessage] = []
        while !array.isAtEnd {
            if let message = try? array.decode(CustomerChatPoll.ChatMessage.self) {
                messages.append(message)
            } else {
                _ = try? array.decode(CustomerLossyJSONSkip.self)
            }
        }
        return messages
    }
}

private struct CustomerLossyJSONSkip: Decodable {
    init(from decoder: Decoder) throws {
        if let container = try? decoder.singleValueContainer() {
            if container.decodeNil() { return }
            if (try? container.decode(Bool.self)) != nil { return }
            if (try? container.decode(Int.self)) != nil { return }
            if (try? container.decode(Double.self)) != nil { return }
            if (try? container.decode(String.self)) != nil { return }
        }
        if var unkeyed = try? decoder.unkeyedContainer() {
            while !unkeyed.isAtEnd {
                _ = try? unkeyed.decode(CustomerLossyJSONSkip.self)
            }
            return
        }
        if let keyed = try? decoder.container(keyedBy: CustomerLossyJSONKey.self) {
            for key in keyed.allKeys {
                _ = try? keyed.decode(CustomerLossyJSONSkip.self, forKey: key)
            }
        }
    }
}

private struct CustomerLossyJSONKey: CodingKey {
    var stringValue: String
    var intValue: Int?
    init?(stringValue: String) { self.stringValue = stringValue }
    init?(intValue: Int) {
        self.intValue = intValue
        self.stringValue = "\(intValue)"
    }
}
