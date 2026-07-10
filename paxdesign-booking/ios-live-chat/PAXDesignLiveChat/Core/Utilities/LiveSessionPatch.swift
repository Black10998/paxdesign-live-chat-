import Foundation

enum LiveSessionPatch {
    static func mysqlTimestamp(from unixTs: Int? = nil) -> String {
        let date: Date
        if let unixTs, unixTs > 0 {
            date = Date(timeIntervalSince1970: TimeInterval(unixTs))
        } else {
            date = Date()
        }
        let formatter = DateFormatter()
        formatter.locale = Locale(identifier: "en_US_POSIX")
        formatter.timeZone = TimeZone.current
        formatter.dateFormat = "yyyy-MM-dd HH:mm:ss"
        return formatter.string(from: date)
    }

    static func preview(from message: LiveMessage) -> String {
        let trimmed = message.content.trimmingCharacters(in: .whitespacesAndNewlines)
        if !trimmed.isEmpty { return trimmed }
        if message.imageUrl != nil { return "📷 Foto" }
        return "—"
    }

    static func bumped(_ session: LiveSession, message: LiveMessage, seq: Int) -> LiveSession {
        let resolvedSeq = max(seq, message.id, session.seq)
        return LiveSession(
            id: session.id,
            sessionId: session.sessionId,
            handler: session.handler,
            handlerLabel: session.handlerLabel,
            adminName: session.adminName,
            customerName: session.customerName,
            sessionRating: session.sessionRating,
            detectedService: session.detectedService,
            updatedAt: mysqlTimestamp(from: message.ts),
            messageCount: max(session.messageCount, resolvedSeq),
            seq: resolvedSeq,
            lastPreview: preview(from: message),
            lastRole: message.role,
            customerLanguage: session.customerLanguage
        )
    }
}
