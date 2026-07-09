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

    func handlePushForeground(
        type: String,
        sessionId: String,
        preview: String,
        customerName: String,
        activeSessionId: String?
    ) {
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
        content.sound = type == "ai_attention"
            ? UNNotificationSound(named: UNNotificationSoundName("pax-ai-alert.wav"))
            : UNNotificationSound(named: UNNotificationSoundName("pax-message.wav"))
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
