import Foundation
import UserNotifications

/// Plays in-app tones and posts local notifications when the app is foregrounded.
@MainActor
final class InAppNotificationCoordinator {
    static let shared = InAppNotificationCoordinator()

    private var lastMessageSoundAt: Date?
    private var lastAISoundAt: Date?
    private let minInterval: TimeInterval = 1.2

    private init() {}

    func handleNewCustomerMessage(sessionId: String, preview: String, customerName: String, isActiveSession: Bool) {
        guard AppSettingsStore.shared.messageSoundEnabled else { return }
        guard !isActiveSession else { return }
        guard shouldPlay(since: &lastMessageSoundAt) else { return }

        PAXNotificationSound.shared.play(.message)
        PAXHaptics.light()
        postLocalNotification(
            title: customerName.isEmpty ? "Neue Kundennachricht" : customerName,
            body: preview.isEmpty ? "Neue Nachricht im Live Chat" : preview,
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
            title: senderName.isEmpty ? "Team-Nachricht" : senderName,
            body: preview.isEmpty ? "Neue Team-Nachricht" : preview,
            sessionId: sessionId,
            type: "team_message"
        )
    }

    func handleAIAttention(sessionId: String, preview: String) {
        guard AppSettingsStore.shared.messageSoundEnabled else { return }
        guard shouldPlay(since: &lastAISoundAt) else { return }

        PAXNotificationSound.shared.play(.aiAlert)
        PAXHaptics.medium()
        postLocalNotification(
            title: "KI-Assistent",
            body: preview.isEmpty ? "Neue KI-Aktivität — bitte prüfen" : preview,
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
            title = "Customer waiting"
            body = preview.isEmpty ? "Ein Kunde wartet auf schnelle Rückmeldung." : preview
            tone = .liveRequest
            type = "live_request"
        case "new_chat_started":
            title = "New chat started"
            body = preview.isEmpty ? "Neuer Live-Chat wurde gestartet." : preview
            tone = .message
            type = "new_chat"
        case "missed_chat":
            title = "Missed chat"
            body = preview.isEmpty ? "Eine Live-Anfrage wurde verpasst." : preview
            tone = .aiAlert
            type = "missed_chat"
        case "assigned_chat_updated":
            title = "Assigned chat updated"
            body = preview.isEmpty ? "Zugewiesener Chat hat neue Aktivität." : preview
            tone = .aiAlert
            type = "session_sync"
        case "new_lead_contact":
            title = "New lead/contact from chat"
            body = preview.isEmpty ? "Neuer Lead oder Kontakt aus Live-Chat." : preview
            tone = .aiAlert
            type = "new_lead_contact"
        default:
            title = customerName.isEmpty ? "Live Chat" : customerName
            body = preview.isEmpty ? "Neue Aktivität im Live Chat." : preview
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
        case "customer_waiting", "new_chat_started", "missed_chat", "assigned_chat_updated", "new_lead_contact":
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
