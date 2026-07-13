import Foundation

enum MessageMerge {
    /// Stable merge key for system notices — aligns with server `sys:*` client_msg_id values.
    static func systemMergeKey(for message: LiveMessage) -> String? {
        guard message.role == "system" else { return nil }
        if let clientId = message.clientMsgId?.trimmingCharacters(in: .whitespacesAndNewlines),
           !clientId.isEmpty {
            return clientId
        }
        switch message.content {
        case "Chat-Session gestartet.":
            return "sys:session_started"
        case "Dieser Chat wurde geschlossen. Sie können jederzeit ein neues Gespräch starten.":
            return "sys:chat_closed"
        case "Der Kunde hat das Gespräch beendet.":
            return "sys:customer_closed"
        case "Der KI-Assistent übernimmt den Chat wieder.":
            return "sys:ai_reclaimed"
        case "Ein PAXDesign-Mitarbeiter wurde informiert. Bitte bleiben Sie kurz im Chat.":
            return "sys:live_agent_notified"
        case "Danke. Ich leite Sie jetzt an einen PAXDesign-Mitarbeiter weiter.":
            return "sys:live_transfer_thanks"
        default:
            if message.content.hasPrefix("Der Chat wurde wieder geöffnet.") {
                return "sys:chat_reopened:\(message.content.hashValue)"
            }
            if message.content.hasSuffix(" ist dem Chat beigetreten.") {
                return "sys:admin_joined:\(message.content.hashValue)"
            }
            return "sys:content:\(message.content.hashValue)"
        }
    }

    private static func mergeKey(for message: LiveMessage) -> String {
        if let systemKey = systemMergeKey(for: message) {
            return "system:\(systemKey)"
        }
        if let clientId = message.clientMsgId?.trimmingCharacters(in: .whitespacesAndNewlines),
           !clientId.isEmpty {
            return "client:\(clientId)"
        }
        return "id:\(message.id)"
    }

    /// Append-only merge for sorted message lists — avoids full re-sort on every poll tick.
    static func mergeSorted(
        existing: [LiveMessage],
        incoming: [LiveMessage],
        preserveReaction: (LiveMessage, LiveMessage) -> LiveMessage = { merged, existing in
            var result = merged
            if result.reaction == nil { result.reaction = existing.reaction }
            return result
        }
    ) -> (messages: [LiveMessage], changed: Bool) {
        guard !incoming.isEmpty else { return (existing, false) }

        if existing.isEmpty {
            let sorted = dedupeByMergeKey(incoming.sorted { $0.id < $1.id })
            return (sorted, true)
        }

        let incomingClientIds = Set(incoming.compactMap(\.clientMsgId))
        let reconciledExisting = existing.filter { message in
            guard message.id < 0, let clientId = message.clientMsgId else { return true }
            return !incomingClientIds.contains(clientId)
        }
        var keyToId: [String: Int] = [:]
        for message in reconciledExisting where message.id > 0 {
            keyToId[mergeKey(for: message)] = message.id
        }

        var map: [Int: LiveMessage] = [:]
        for message in reconciledExisting {
            map[message.id] = message
        }
        var changed = reconciledExisting.count != existing.count
            || map.count != reconciledExisting.count

        for msg in incoming {
            let resolvedId: Int
            if msg.id > 0, let existingId = keyToId[mergeKey(for: msg)] {
                resolvedId = existingId
            } else {
                resolvedId = msg.id
            }

            var normalized = msg
            if resolvedId != msg.id, resolvedId > 0 {
                normalized = msg.replacingId(resolvedId)
            }

            if let prior = map[resolvedId] {
                var merged = normalized
                merged = preserveReaction(merged, prior)
                if merged != prior {
                    map[resolvedId] = merged
                    changed = true
                }
            } else {
                map[resolvedId] = normalized
                changed = true
            }
            if resolvedId > 0 {
                keyToId[mergeKey(for: normalized)] = resolvedId
            }
        }

        guard changed else { return (existing, false) }

        return (dedupeByMergeKey(map.values.sorted { $0.id < $1.id }), true)
    }

    private static func dedupeByMergeKey(_ messages: [LiveMessage]) -> [LiveMessage] {
        var seenKeys = Set<String>()
        var result: [LiveMessage] = []
        for message in messages {
            let key = mergeKey(for: message)
            guard !seenKeys.contains(key) else { continue }
            seenKeys.insert(key)
            result.append(message)
        }
        return result
    }

    /// Establish authoritative server history while preserving in-flight optimistic sends.
    static func baseline(server: [LiveMessage], preservingOptimistic optimistic: [LiveMessage]) -> [LiveMessage] {
        let sorted = dedupeByMergeKey(server.sorted { $0.id < $1.id })
        let acknowledgedClientIds = Set(sorted.compactMap(\.clientMsgId))
        let pending = optimistic.filter {
            $0.id < 0 && ($0.clientMsgId.map { !acknowledgedClientIds.contains($0) } ?? true)
        }
        guard !pending.isEmpty else { return sorted }
        return mergeSorted(existing: sorted, incoming: pending).messages
    }

    static func applyReactions(
        to messages: [LiveMessage],
        reactions: [String: String]
    ) -> (messages: [LiveMessage], changed: Bool) {
        guard !reactions.isEmpty else { return (messages, false) }

        var map: [Int: LiveMessage] = [:]
        for message in messages {
            map[message.id] = message
        }
        var changed = map.count != messages.count

        for (key, raw) in reactions {
            guard let messageId = Int(key),
                  let reaction = MessageReaction.normalize(raw),
                  var msg = map[messageId],
                  msg.role == "admin" else { continue }
            if msg.reaction != reaction {
                msg.reaction = reaction
                map[messageId] = msg
                changed = true
            }
        }

        guard changed else { return (messages, false) }
        return (map.values.sorted { $0.id < $1.id }, true)
    }
}
