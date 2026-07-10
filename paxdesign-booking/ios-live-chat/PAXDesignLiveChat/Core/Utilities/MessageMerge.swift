import Foundation

enum MessageMerge {
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
            let sorted = incoming.sorted { $0.id < $1.id }
            return (sorted, true)
        }

        let incomingClientIds = Set(incoming.compactMap(\.clientMsgId))
        let reconciledExisting = existing.filter { message in
            guard message.id < 0, let clientId = message.clientMsgId else { return true }
            return !incomingClientIds.contains(clientId)
        }
        var map = Dictionary(uniqueKeysWithValues: reconciledExisting.map { ($0.id, $0) })
        var changed = false
        var maxIncomingId = existing.last?.id ?? 0
        var appendOnly = true

        for msg in incoming {
            if msg.id <= maxIncomingId { appendOnly = false }
            maxIncomingId = max(maxIncomingId, msg.id)

            if let prior = map[msg.id] {
                var merged = msg
                merged = preserveReaction(merged, prior)
                if merged != prior {
                    map[msg.id] = merged
                    changed = true
                }
            } else {
                map[msg.id] = msg
                changed = true
            }
        }

        guard changed else { return (existing, false) }

        if appendOnly, let lastExisting = existing.last {
            let newOnes = incoming.filter { $0.id > lastExisting.id }.sorted { $0.id < $1.id }
            if !newOnes.isEmpty {
                return (existing + newOnes, true)
            }
        }

        return (map.values.sorted { $0.id < $1.id }, true)
    }

    /// Establish authoritative server history while preserving in-flight optimistic sends.
    static func baseline(server: [LiveMessage], preservingOptimistic optimistic: [LiveMessage]) -> [LiveMessage] {
        let sorted = server.sorted { $0.id < $1.id }
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

        var map = Dictionary(uniqueKeysWithValues: messages.map { ($0.id, $0) })
        var changed = false

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
