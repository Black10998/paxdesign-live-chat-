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

        var map = Dictionary(uniqueKeysWithValues: existing.map { ($0.id, $0) })
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
