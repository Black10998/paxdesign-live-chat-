import Foundation
import UIKit
import UserNotifications

/// Foreground notification coordinator — uses system default sounds and deduplicates by session.
@MainActor
final class InAppNotificationCoordinator {
    static let shared = InAppNotificationCoordinator()

    private var lastMessageSoundAt: Date?
    private var lastAISoundAt: Date?
    private var lastNewChatSoundAt: Date?
    private var recentNotificationKeys: [String: Date] = [:]
    private let minInterval: TimeInterval = 0.8
    private let dedupWindow: TimeInterval = 30

    private init() {}

    func resetOnLogout() {
        lastMessageSoundAt = nil
        lastAISoundAt = nil
        lastNewChatSoundAt = nil
        recentNotificationKeys.removeAll()
    }

    func handleNewChatStarted(sessionId: String, customerName: String, preview: String) {
        guard AppSettingsStore.shared.notificationsEnabled else { return }
        guard shouldDeliver(sessionId: sessionId, type: "new_chat") else { return }
        guard shouldPlay(since: &lastNewChatSoundAt) else { return }

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
        guard shouldDeliver(sessionId: sessionId, type: "message") else { return }
        guard shouldPlay(since: &lastMessageSoundAt) else { return }

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
        guard shouldDeliver(sessionId: sessionId, type: "team_message") else { return }
        guard shouldPlay(since: &lastMessageSoundAt) else { return }

        PAXHaptics.light()
        postLocalNotification(
            title: senderName.isEmpty ? L10n.NotifyTeamMessageTitle : senderName,
            body: preview.isEmpty ? L10n.NotifyTeamMessageBody : preview,
            sessionId: sessionId,
            type: "team_message"
        )
    }

    func handleCustomerOrder(orderId: Int, customerName: String, preview: String) {
        guard AppSettingsStore.shared.notificationsEnabled else { return }
        let key = "order:\(orderId)"
        guard shouldDeliver(sessionId: key, type: "customer_order") else { return }
        guard shouldPlay(since: &lastMessageSoundAt) else { return }

        PAXHaptics.medium()
        let content = UNMutableNotificationContent()
        content.title = customerName.isEmpty ? String(localized: "New customer request") : customerName
        content.body = preview.isEmpty ? String(localized: "A customer submitted a new order.") : preview
        content.sound = UNNotificationSound(named: UNNotificationSoundName("pax-message.wav"))
        content.userInfo = [
            "type": "customer_order",
            "event": "new_customer_order",
            "order_id": String(orderId),
            "customer_name": customerName,
            "preview": preview,
        ]
        let request = UNNotificationRequest(identifier: "order-\(orderId)-\(UUID().uuidString)", content: content, trigger: nil)
        UNUserNotificationCenter.current().add(request)
    }

    func handleTeamRequest(sessionId: String, preview: String) {
        guard AppSettingsStore.shared.messageSoundEnabled else { return }
        guard shouldDeliver(sessionId: sessionId, type: "team_request") else { return }
        guard shouldPlay(since: &lastMessageSoundAt) else { return }

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
        guard shouldDeliver(sessionId: sessionId, type: "ai_attention") else { return }
        guard shouldPlay(since: &lastAISoundAt) else { return }

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
        guard shouldDeliver(sessionId: sessionId, type: event) else { return }
        guard shouldPlay(since: &lastAISoundAt) else { return }

        let title: String
        let body: String
        let type: String

        switch event {
        case "customer_waiting":
            title = L10n.NotifyCustomerWaitingTitle
            body = preview.isEmpty ? L10n.NotifyCustomerWaitingBody : preview
            type = "live_request"
        case "new_chat_started":
            title = L10n.NotifyNewChatTitle
            body = preview.isEmpty ? L10n.NotifyNewChatBody : preview
            type = "new_chat"
        case "missed_chat":
            title = L10n.NotifyMissedChatTitle
            body = preview.isEmpty ? L10n.NotifyMissedChatBody : preview
            type = "missed_chat"
        case "assigned_chat_updated":
            title = L10n.NotifyAssignedChatTitle
            body = preview.isEmpty ? L10n.NotifyAssignedChatBody : preview
            type = "session_sync"
        case "new_lead_contact":
            title = L10n.NotifyNewLeadTitle
            body = preview.isEmpty ? L10n.NotifyNewLeadBody : preview
            type = "new_lead_contact"
        case "link_scan_attention":
            title = L10n.NotifyAIAttentionTitle
            body = preview.isEmpty ? L10n.NotifyAIAttentionBody : preview
            type = "ai_attention"
        default:
            title = customerName.isEmpty ? L10n.NotifyNewMessageTitle : customerName
            body = preview.isEmpty ? L10n.NotifyNewMessageBody : preview
            type = "message"
        }

        PAXHaptics.medium()
        postLocalNotification(title: title, body: body, sessionId: sessionId, type: type)
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
            break
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

    private func shouldDeliver(sessionId: String, type: String) -> Bool {
        let key = "\(type):\(sessionId)"
        let now = Date()
        if let last = recentNotificationKeys[key], now.timeIntervalSince(last) < dedupWindow {
            return false
        }
        recentNotificationKeys[key] = now
        recentNotificationKeys = recentNotificationKeys.filter { now.timeIntervalSince($0.value) < dedupWindow * 2 }
        return true
    }

    private func shouldPlay(since lastPlayed: inout Date?) -> Bool {
        let now = Date()
        if let last = lastPlayed, now.timeIntervalSince(last) < minInterval { return false }
        lastPlayed = now
        return true
    }

    private func postLocalNotification(title: String, body: String, sessionId: String, type: String) {
        guard AppSettingsStore.shared.notificationsEnabled else { return }
        if UIApplication.shared.applicationState == .active {
            return
        }

        let content = UNMutableNotificationContent()
        content.title = title
        content.body = body
        content.sound = UNNotificationSound(named: UNNotificationSoundName(Self.soundName(for: type)))
        content.badge = NSNumber(value: UIApplication.shared.applicationIconBadgeNumber)
        content.userInfo = [
            "pax": [
                "session_id": sessionId,
                "type": type,
                "preview": body
            ]
        ]

        let identifier = "pax-foreground-\(type)-\(sessionId)"
        let request = UNNotificationRequest(
            identifier: identifier,
            content: content,
            trigger: nil
        )
        UNUserNotificationCenter.current().add(request)
    }

    private static func soundName(for type: String) -> String {
        switch type {
        case "live_request", "customer_waiting":
            return "pax-live-request.wav"
        case "ai_attention", "missed_chat", "new_lead_contact", "security_alert":
            return "pax-ai-alert.wav"
        default:
            return "pax-message.wav"
        }
    }
}
