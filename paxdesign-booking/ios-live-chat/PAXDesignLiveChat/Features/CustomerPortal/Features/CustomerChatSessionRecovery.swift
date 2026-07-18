import Foundation
import SwiftUI

/// Session lifecycle analysis and localized recovery messaging for customer chat.
enum CustomerChatSessionRecovery {

    enum Issue: Equatable {
        case closed
        case expired
        case unavailable
        case forbidden
        case offline
    }

    struct Action: Equatable {
        let issue: Issue
        let message: String
        let shouldRenew: Bool
        let shouldStopPolling: Bool
        let preserveDraft: Bool
        let showManualRenew: Bool
    }

    /// Friendly waiting copy when human support is pending (never "session closed").
    static func waitingForTeamNotice() -> String {
        String(localized: "Our team will reply here when available. Feel free to send another message anytime.")
    }

    /// Hide session lifecycle system messages from the customer transcript.
    static func visibleMessages(_ messages: [CustomerChatPoll.ChatMessage]) -> [CustomerChatPoll.ChatMessage] {
        messages.filter { message in
            guard message.role == "system" else { return true }
            let lower = message.content.lowercased()
            let blocked = [
                "closed", "geschlossen", "beendet", "ended", "conversation ended",
                "session closed", "neues gespräch", "new chat", "new conversation",
                "start a new", "inactivity", "inaktivität", "مغلق", "انتهت",
            ]
            return !blocked.contains(where: { lower.contains($0) })
        }
    }

    static func analyze(error: Error, handler: String?, isConnected: Bool) -> Action? {
        if !isConnected {
            return Action(
                issue: .offline,
                message: String(localized: "You are offline. Your message will send when you reconnect."),
                shouldRenew: false,
                shouldStopPolling: false,
                preserveDraft: true,
                showManualRenew: false
            )
        }

        if handler == "closed" {
            return Action(
                issue: .closed,
                message: waitingForTeamNotice(),
                shouldRenew: true,
                shouldStopPolling: false,
                preserveDraft: true,
                showManualRenew: false
            )
        }

        if let apiError = error as? CustomerAPIError {
            switch apiError {
            case .serverCode(let code, let message):
                return actionForCode(code, message: message)
            case .http(let code):
                return actionForHTTP(code)
            case .server(let message):
                return actionForMessage(message)
            case .unauthorized:
                return Action(
                    issue: .forbidden,
                    message: String(localized: "Please sign in again to continue chatting."),
                    shouldRenew: false,
                    shouldStopPolling: true,
                    preserveDraft: true,
                    showManualRenew: false
                )
            default:
                return nil
            }
        }

        return nil
    }

    static func renewedNotice() -> String {
        waitingForTeamNotice()
    }

    static func reconnectingNotice() -> String {
        String(localized: "Reconnecting to your conversation…")
    }

    private static func actionForCode(_ code: String, message: String) -> Action? {
        switch code {
        case "chat_closed":
            return Action(
                issue: .closed,
                message: waitingForTeamNotice(),
                shouldRenew: true,
                shouldStopPolling: false,
                preserveDraft: true,
                showManualRenew: false
            )
        case "invalid_session", "not_found":
            return Action(
                issue: .expired,
                message: String(localized: "Your conversation session expired. Reconnecting…"),
                shouldRenew: true,
                shouldStopPolling: false,
                preserveDraft: true,
                showManualRenew: false
            )
        case "disabled", "worker_only_stream", "not_configured", "openai_failed":
            return Action(
                issue: .unavailable,
                message: String(localized: "The assistant is temporarily unavailable. Please try again."),
                shouldRenew: false,
                shouldStopPolling: false,
                preserveDraft: true,
                showManualRenew: false
            )
        case "forbidden":
            return Action(
                issue: .forbidden,
                message: String(localized: "You no longer have access to this conversation. Reconnecting…"),
                shouldRenew: true,
                shouldStopPolling: false,
                preserveDraft: true,
                showManualRenew: false
            )
        default:
            return nil
        }
    }

    private static func actionForHTTP(_ code: Int) -> Action? {
        switch code {
        case 409:
            return Action(
                issue: .closed,
                message: waitingForTeamNotice(),
                shouldRenew: true,
                shouldStopPolling: false,
                preserveDraft: true,
                showManualRenew: false
            )
        case 404:
            return Action(
                issue: .expired,
                message: String(localized: "Your conversation session expired. Reconnecting…"),
                shouldRenew: true,
                shouldStopPolling: false,
                preserveDraft: true,
                showManualRenew: false
            )
        case 503, 502:
            return Action(
                issue: .unavailable,
                message: String(localized: "The assistant is temporarily unavailable. Please try again."),
                shouldRenew: false,
                shouldStopPolling: false,
                preserveDraft: true,
                showManualRenew: false
            )
        default:
            return nil
        }
    }

    private static func actionForMessage(_ message: String) -> Action? {
        let lower = message.lowercased()
        if lower.contains("closed") || lower.contains("geschlossen") || lower.contains("مغلق") {
            return Action(
                issue: .closed,
                message: waitingForTeamNotice(),
                shouldRenew: true,
                shouldStopPolling: false,
                preserveDraft: true,
                showManualRenew: false
            )
        }
        if lower.contains("unavailable") || lower.contains("nicht verfügbar") || lower.contains("غير متاح") || lower.contains("temporarily") {
            return Action(
                issue: .unavailable,
                message: String(localized: "The assistant is temporarily unavailable. Please try again."),
                shouldRenew: false,
                shouldStopPolling: false,
                preserveDraft: true,
                showManualRenew: false
            )
        }
        return nil
    }
}

// MARK: - Recovery UI

struct CustomerChatRecoveryBanner: View {
    let action: CustomerChatSessionRecovery.Action
    var isRecovering: Bool
    var onRetry: () -> Void

    private var icon: String {
        switch action.issue {
        case .offline: return "wifi.slash"
        case .closed, .expired: return "bubble.left.and.bubble.right"
        case .unavailable: return "exclamationmark.triangle"
        case .forbidden: return "lock.fill"
        }
    }

    var body: some View {
        VStack(alignment: .leading, spacing: 12) {
            HStack(alignment: .top, spacing: 10) {
                Image(systemName: icon)
                    .font(.body.weight(.semibold))
                    .foregroundStyle(bannerTint)
                    .accessibilityHidden(true)
                Text(action.message)
                    .font(.subheadline)
                    .foregroundStyle(PAXTheme.textPrimary)
                    .fixedSize(horizontal: false, vertical: true)
                    .frame(maxWidth: .infinity, alignment: .leading)
            }

            if action.issue == .unavailable || action.issue == .offline {
                Button(String(localized: "Retry")) { onRetry() }
                    .buttonStyle(CustomerPrimaryButtonStyleModifier(style: .tinted))
                    .disabled(isRecovering)
            }
        }
        .padding(16)
        .frame(maxWidth: .infinity, alignment: .leading)
        .background(bannerTint.opacity(0.12))
        .accessibilityElement(children: .contain)
    }

    private var bannerTint: Color {
        switch action.issue {
        case .offline: return .orange
        case .unavailable: return .orange
        case .forbidden: return .red
        default: return PAXTheme.accent
        }
    }
}
