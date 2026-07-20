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
            customerLanguage: session.customerLanguage,
            otherUserId: session.otherUserId,
            requestStatus: session.requestStatus,
            requestStatusLabel: session.requestStatusLabel,
            canSend: session.canSend,
            canRespond: session.canRespond,
            requestedBy: session.requestedBy,
            isPinned: session.isPinned,
            isMuted: session.isMuted,
            assignedTo: session.assignedTo,
            otherRoleRank: session.otherRoleRank,
            otherRoleLabel: session.otherRoleLabel,
            otherPresence: session.otherPresence,
            otherLastSeen: session.otherLastSeen
        )
    }

    static func patched(
        _ session: LiveSession,
        handler: String? = nil,
        handlerLabel: String? = nil,
        adminName: String? = nil,
        message: LiveMessage? = nil,
        seq: Int = 0
    ) -> LiveSession {
        let base = message.map { bumped(session, message: $0, seq: seq) } ?? session
        return LiveSession(
            id: base.id,
            sessionId: base.sessionId,
            handler: handler ?? base.handler,
            handlerLabel: handlerLabel ?? base.handlerLabel,
            adminName: adminName ?? base.adminName,
            customerName: base.customerName,
            sessionRating: base.sessionRating,
            detectedService: base.detectedService,
            updatedAt: base.updatedAt,
            messageCount: base.messageCount,
            seq: base.seq,
            lastPreview: base.lastPreview,
            lastRole: base.lastRole,
            customerLanguage: base.customerLanguage,
            otherUserId: base.otherUserId,
            requestStatus: base.requestStatus,
            requestStatusLabel: base.requestStatusLabel,
            canSend: base.canSend,
            canRespond: base.canRespond,
            requestedBy: base.requestedBy,
            isPinned: base.isPinned,
            isMuted: base.isMuted,
            assignedTo: base.assignedTo,
            otherRoleRank: base.otherRoleRank,
            otherRoleLabel: base.otherRoleLabel,
            otherPresence: base.otherPresence,
            otherLastSeen: base.otherLastSeen
        )
    }
}
