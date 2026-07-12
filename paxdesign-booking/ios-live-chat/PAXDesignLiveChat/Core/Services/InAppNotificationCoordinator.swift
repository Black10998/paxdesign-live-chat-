import Foundation
import UserNotifications

/// Plays in-app tones and posts local notifications when the app is foregrounded.
@MainActor
final class InAppNotificationCoordinator {
    static let shared = InAppNotificationCoordinator()

    private var lastMessageSoundAt: Date?
    private var lastAISoundAt: Date?
    private var lastNewChatSoundAt: Date?
    private let minInterval: TimeInterval = 0.8

    private init() {}

    func handleNewChatStarted(sessionId: String, customerName: String, preview: String) {
        guard AppSettingsStore.shared.notificationsEnabled else { return }
        guard shouldPlay(since: &lastNewChatSoundAt) else { return }

        PAXNotificationSound.shared.play(.message)
        PAXHaptics.medium()
        postLocalNotification(
            title: customerName.isEmpty ? L10n.NotifyNewChatTitle : customerName,
            body: preview.isEmpty ? L10n.NotifyNewChatBody : preview,
            sessionId: sessionId,
            type: "new_chat"
        )
    }

    func handleNewCustomerMessage(sessionId: String, preview: String, customerName: String, isActiveSession: Bool) {
        guard AppSettingsStore.shared.messageSoundEnabled else { return }
        guard !isActiveSession else { return }
        guard shouldPlay(since: &lastMessageSoundAt) else { return }

        PAXNotificationSound.shared.play(.message)
        PAXHaptics.light()
        postLocalNotification(
            title: customerName.isEmpty ? L10n.NotifyNewMessageTitle : customerName,
            body: preview.isEmpty ? L10n.NotifyNewMessageBody : preview,
            sessionId: sessionId,
            type: "message"
        )
    }

    func handleTeamMessage(sessionId: String, preview: String, senderName: String, isActiveSession: Bool) {
        guard AppSettingsStore.shared.messageSoundEnabled else { return }
        guard !isActiveSession else { return }
        guard shouldPlay(since: &lastMessageSoundAt) else { return }

        PAXNotificationSound.shared.play(.message)
        PAXHaptics.light()
        postLocalNotification(
            title: senderName.isEmpty ? L10n.NotifyTeamMessageTitle : senderName,
            body: preview.isEmpty ? L10n.NotifyTeamMessageBody : preview,
            sessionId: sessionId,
            type: "team_message"
        )
    }

    func handleTeamRequest(sessionId: String, preview: String) {
        guard AppSettingsStore.shared.messageSoundEnabled else { return }
        guard shouldPlay(since: &lastMessageSoundAt) else { return }

        PAXNotificationSound.shared.play(.liveRequest)
        PAXHaptics.medium()
        postLocalNotification(
            title: L10n.NotifyTeamRequestTitle,
            body: preview.isEmpty ? L10n.NotifyTeamRequestBody : preview,
            sessionId: sessionId,
            type: "team_request"
        )
    }

    func handleAIAttention(sessionId: String, preview: String) {
        guard AppSettingsStore.shared.messageSoundEnabled else { return }
        guard shouldPlay(since: &lastAISoundAt) else { return }

        PAXNotificationSound.shared.play(.aiAlert)
        PAXHaptics.medium()
        postLocalNotification(
            title: L10n.NotifyAIAttentionTitle,
            body: preview.isEmpty ? L10n.NotifyAIAttentionBody : preview,
            sessionId: sessionId,
            type: "ai_attention"
        )
    }

    func handleOperationalEvent(event: String, sessionId: String, preview: String, customerName: String) {
        guard AppSettingsStore.shared.messageSoundEnabled else { return }
        guard shouldPlay(since: &lastAISoundAt) else { return }

        let title: String
        let body: String
        let tone: PAXNotificationSound.Tone
        let type: String

        switch event {
        case "customer_waiting":
            title = L10n.NotifyCustomerWaitingTitle
            body = preview.isEmpty ? L10n.NotifyCustomerWaitingBody : preview
            tone = .liveRequest
            type = "live_request"
        case "new_chat_started":
            title = L10n.NotifyNewChatTitle
            body = preview.isEmpty ? L10n.NotifyNewChatBody : preview
            tone = .message
            type = "new_chat"
        case "missed_chat":
            title = L10n.NotifyMissedChatTitle
            body = preview.isEmpty ? L10n.NotifyMissedChatBody : preview
            tone = .aiAlert
            type = "missed_chat"
        case "assigned_chat_updated":
            title = L10n.NotifyAssignedChatTitle
            body = preview.isEmpty ? L10n.NotifyAssignedChatBody : preview
            tone = .aiAlert
            type = "session_sync"
        case "new_lead_contact":
            title = L10n.NotifyNewLeadTitle
            body = preview.isEmpty ? L10n.NotifyNewLeadBody : preview
            tone = .aiAlert
            type = "new_lead_contact"
        case "link_scan_attention":
            title = L10n.NotifyAIAttentionTitle
            body = preview.isEmpty ? L10n.NotifyAIAttentionBody : preview
            tone = .aiAlert
            type = "ai_attention"
        default:
            title = customerName.isEmpty ? L10n.NotifyNewMessageTitle : customerName
            body = preview.isEmpty ? L10n.NotifyNewMessageBody : preview
            tone = .message
            type = "message"
        }

        PAXNotificationSound.shared.play(tone)
        PAXHaptics.medium()
        postLocalNotification(
            title: title,
            body: body,
            sessionId: sessionId,
            type: type
        )
    }

    func handlePushForeground(
        type: String,
        event: String,
        sessionId: String,
        preview: String,
        customerName: String,
        activeSessionId: String?
    ) {
        switch event {
        case "customer_waiting", "new_chat_started", "missed_chat", "assigned_chat_updated", "new_lead_contact", "link_scan_attention":
            handleOperationalEvent(
                event: event,
                sessionId: sessionId,
                preview: preview,
                customerName: customerName
            )
            return
        default:
            break
        }

        switch type {
        case "live_request":
            break // ringtone handled by ChatCoordinator
        case "message", "new_chat":
            handleNewCustomerMessage(
                sessionId: sessionId,
                preview: preview,
                customerName: customerName,
                isActiveSession: activeSessionId == sessionId
            )
        case "team_message":
            handleTeamMessage(
                sessionId: sessionId,
                preview: preview,
                senderName: customerName,
                isActiveSession: activeSessionId == sessionId
            )
        case "session_sync", "ai_attention":
            handleAIAttention(sessionId: sessionId, preview: preview)
        default:
            break
        }
    }

    private func shouldPlay(since lastPlayed: inout Date?) -> Bool {
        let now = Date()
        if let last = lastPlayed, now.timeIntervalSince(last) < minInterval { return false }
        lastPlayed = now
        return true
    }

    private func postLocalNotification(title: String, body: String, sessionId: String, type: String) {
        guard AppSettingsStore.shared.notificationsEnabled else { return }

        let content = UNMutableNotificationContent()
        content.title = title
        content.body = body
        content.sound = .default
        content.userInfo = [
            "pax": [
                "session_id": sessionId,
                "type": type,
                "preview": body
            ]
        ]

        let request = UNNotificationRequest(
            identifier: "pax-foreground-\(sessionId)-\(UUID().uuidString)",
            content: content,
            trigger: nil
        )
        UNUserNotificationCenter.current().add(request)
    }
}
