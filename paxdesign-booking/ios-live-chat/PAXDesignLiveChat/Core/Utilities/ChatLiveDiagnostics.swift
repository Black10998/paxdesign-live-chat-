import Foundation
import os

/// Structured logging for live message receipt, merge, and UI refresh paths.
enum ChatLiveDiagnostics {
    private static let log = Logger(subsystem: "at.paxdesign.livechat", category: "Messaging")

    static func sseReceived(channel: String, type: String, sessionId: String, eventId: Int, seq: Int, inlineCount: Int) {
        log.info("sse channel=\(channel, privacy: .public) type=\(type, privacy: .public) session=\(sessionId, privacy: .public) event=\(eventId) seq=\(seq) inline=\(inlineCount)")
    }

    static func mergeResult(sessionId: String, incomingIds: [Int], changed: Bool, before: Int, after: Int, published: Bool) {
        let ids = incomingIds.map(String.init).joined(separator: ",")
        log.info("merge session=\(sessionId, privacy: .public) incoming=[\(ids, privacy: .public)] changed=\(changed) count \(before)->\(after) published=\(published)")
    }

    static func pollApplied(sessionId: String, since: Int, serverSeq: Int, newCount: Int, localMax: Int, pollSeq: Int) {
        log.info("poll session=\(sessionId, privacy: .public) since=\(since) serverSeq=\(serverSeq) new=\(newCount) localMax=\(localMax) pollSeq=\(pollSeq)")
    }

    static func uiRows(sessionId: String, messageCount: Int, rowCount: Int, revision: Int) {
        #if DEBUG
        log.debug("ui session=\(sessionId, privacy: .public) messages=\(messageCount) rows=\(rowCount) revision=\(revision)")
        #endif
    }

    static func cursorAdjusted(sessionId: String, reason: String, pollSeq: Int) {
        log.notice("cursor session=\(sessionId, privacy: .public) reason=\(reason, privacy: .public) pollSeq=\(pollSeq)")
    }
}
