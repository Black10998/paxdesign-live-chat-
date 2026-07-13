import SwiftUI
import UIKit

/// Shared lazy message list — renders directly from `messages` so live inserts always appear.
struct ChatMessageListView: View {
    let messages: [LiveMessage]
    let messagesRevision: Int
    let sessionId: String
    let userTyping: Bool
    let canReply: Bool
    let handler: String
    var isLoading = false
    var agentDisplayName = L10n.ChatAgent
    var customerDisplayName = L10n.ChatCustomer
    let onReply: (LiveMessage) -> Void
    let onCopy: (LiveMessage) -> Void
    let onDelete: (LiveMessage) -> Void
    var onAnalyze: ((LiveMessage) -> Void)?
    var onLinkReview: ((LiveMessage, String) -> Void)?
    var linkReviewSubmittingIds: Set<Int> = []
    let onImageTap: (URL) -> Void
    var teamOtherReadSeq: Int = 0
    var teamFailedClientMsgIds: Set<String> = []
    var onRetryTeamMessage: ((String) -> Void)? = nil
    var siteBaseURL: String?
    var deletingMessageIds: Set<Int> = []

    @State private var lastTrackedMessageId: Int?
    @State private var animatedRowIds = Set<String>()

    private var displayRows: [MessageDisplayRow] {
        var messageLookup: [Int: LiveMessage] = [:]
        for message in messages {
            messageLookup[message.id] = message
        }
        return messages.enumerated().map { index, message in
            MessageDisplayRow(
                id: messageRowId(message),
                message: message,
                previous: index > 0 ? messages[index - 1] : nil,
                next: index + 1 < messages.count ? messages[index + 1] : nil,
                quotedMessage: message.replyTo.flatMap { messageLookup[$0] }
            )
        }
    }

    var body: some View {
        Group {
            if isLoading, messages.isEmpty {
                PAXChatThreadLoadingStack()
            } else {
                messageScrollView
            }
        }
    }

    private var messageScrollView: some View {
        ScrollViewReader { proxy in
            ScrollView {
                LazyVStack(alignment: .leading, spacing: PAXMessageStyle.threadSpacing) {
                    ForEach(displayRows) { row in
                        AnimatedChatMessageRow(
                            row: row,
                            canReply: canReply,
                            handler: handler,
                            agentDisplayName: agentDisplayName,
                            customerDisplayName: customerDisplayName,
                            isOutgoing: isOutgoingMessage(row.message),
                            animatedRowIds: $animatedRowIds,
                            onReply: { onReply(row.message) },
                            onCopy: { onCopy(row.message) },
                            onDelete: { onDelete(row.message) },
                            onAnalyze: onAnalyze.map { handler in
                                { handler(row.message) }
                            },
                            onLinkReview: onLinkReview.map { handler in
                                { action in handler(row.message, action) }
                            },
                            linkReviewSubmittingIds: linkReviewSubmittingIds,
                            onImageTap: onImageTap,
                            teamOtherReadSeq: teamOtherReadSeq,
                            teamFailedClientMsgIds: teamFailedClientMsgIds,
                            onRetryTeamMessage: onRetryTeamMessage,
                            siteBaseURL: siteBaseURL,
                            deletingMessageIds: deletingMessageIds
                        )
                        .id(row.id)
                    }
                    if userTyping {
                        TypingIndicator(customerName: customerDisplayName)
                            .id("typing-indicator")
                    }
                }
                .padding(.horizontal, 12)
                .padding(.vertical, 10)
                .padding(.bottom, 8)
            }
            .scrollDismissesKeyboard(.interactively)
            .onChange(of: messagesRevision) { _ in
                ChatLiveDiagnostics.uiRows(
                    sessionId: sessionId,
                    messageCount: messages.count,
                    rowCount: displayRows.count,
                    revision: messagesRevision
                )
                let latestId = messages.last?.id
                let appended = latestId != nil && latestId != lastTrackedMessageId
                lastTrackedMessageId = latestId
                if appended || userTyping || messages.count > 0 {
                    scrollToBottom(proxy: proxy, animated: true)
                }
            }
            .onChange(of: userTyping) { typing in
                if typing {
                    scrollToBottom(proxy: proxy, animated: true)
                }
            }
            .onReceive(NotificationCenter.default.publisher(for: UIResponder.keyboardWillShowNotification)) { _ in
                DispatchQueue.main.asyncAfter(deadline: .now() + 0.05) {
                    scrollToBottom(proxy: proxy, animated: true)
                }
            }
            .onReceive(NotificationCenter.default.publisher(for: UIResponder.keyboardWillChangeFrameNotification)) { _ in
                DispatchQueue.main.asyncAfter(deadline: .now() + 0.05) {
                    scrollToBottom(proxy: proxy, animated: true)
                }
            }
            .onAppear {
                lastTrackedMessageId = messages.last?.id
                animatedRowIds = Set(displayRows.map(\.id))
                scrollToBottom(proxy: proxy, animated: false)
            }
        }
        .onAppear {
            ChatLiveDiagnostics.uiRows(
                sessionId: sessionId,
                messageCount: messages.count,
                rowCount: displayRows.count,
                revision: messagesRevision
            )
        }
    }

    private func messageRowId(_ message: LiveMessage) -> String {
        if let clientId = message.clientMsgId, !clientId.isEmpty {
            return "c:\(clientId)"
        }
        return "m:\(message.id)"
    }

    private func scrollToBottom(proxy: ScrollViewProxy, animated: Bool) {
        let action = {
            if userTyping {
                proxy.scrollTo("typing-indicator", anchor: .bottom)
            } else if let last = displayRows.last {
                proxy.scrollTo(last.id, anchor: .bottom)
            }
        }
        if animated {
            withAnimation(.spring(response: 0.34, dampingFraction: 0.88)) {
                action()
            }
        } else {
            action()
        }
    }

    private func isOutgoingMessage(_ message: LiveMessage) -> Bool {
        message.role == "admin" || message.role == "assistant"
    }
}

private struct AnimatedChatMessageRow: View {
    let row: MessageDisplayRow
    let canReply: Bool
    let handler: String
    let agentDisplayName: String
    let customerDisplayName: String
    let isOutgoing: Bool
    @Binding var animatedRowIds: Set<String>
    let onReply: () -> Void
    let onCopy: () -> Void
    let onDelete: () -> Void
    var onAnalyze: (() -> Void)?
    var onLinkReview: ((String) -> Void)?
    var linkReviewSubmittingIds: Set<Int> = []
    let onImageTap: (URL) -> Void
    var teamOtherReadSeq: Int = 0
    var teamFailedClientMsgIds: Set<String> = []
    var onRetryTeamMessage: ((String) -> Void)? = nil
    var siteBaseURL: String?
    var deletingMessageIds: Set<Int> = []

    @State private var isVisible = false

    var body: some View {
        ChatMessageRow(
            row: row,
            canReply: canReply,
            handler: handler,
            agentDisplayName: agentDisplayName,
            customerDisplayName: customerDisplayName,
            onReply: onReply,
            onCopy: onCopy,
            onDelete: onDelete,
            onAnalyze: onAnalyze,
            onLinkReview: onLinkReview,
            linkReviewSubmittingIds: linkReviewSubmittingIds,
            onImageTap: onImageTap,
            teamOtherReadSeq: teamOtherReadSeq,
            teamFailedClientMsgIds: teamFailedClientMsgIds,
            onRetryTeamMessage: onRetryTeamMessage,
            siteBaseURL: siteBaseURL,
            deletingMessageIds: deletingMessageIds
        )
        .opacity(isVisible ? 1 : 0)
        .scaleEffect(isVisible ? 1 : 0.92, anchor: isOutgoing ? .bottomTrailing : .bottomLeading)
        .offset(y: isVisible ? 0 : 6)
        .onAppear {
            if animatedRowIds.contains(row.id) {
                isVisible = true
            } else {
                animatedRowIds.insert(row.id)
                withAnimation(.spring(response: 0.4, dampingFraction: 0.84)) {
                    isVisible = true
                }
            }
        }
    }
}

private struct MessageDisplayRow: Identifiable {
    let id: String
    let message: LiveMessage
    let previous: LiveMessage?
    let next: LiveMessage?
    let quotedMessage: LiveMessage?
}

private struct ChatMessageRow: View {
    let row: MessageDisplayRow
    let canReply: Bool
    let handler: String
    let agentDisplayName: String
    let customerDisplayName: String
    let onReply: () -> Void
    let onCopy: () -> Void
    let onDelete: () -> Void
    var onAnalyze: (() -> Void)?
    var onLinkReview: ((String) -> Void)?
    var linkReviewSubmittingIds: Set<Int> = []
    let onImageTap: (URL) -> Void
    var teamOtherReadSeq: Int = 0
    var teamFailedClientMsgIds: Set<String> = []
    var onRetryTeamMessage: ((String) -> Void)? = nil
    var siteBaseURL: String?
    var deletingMessageIds: Set<Int> = []

    private var showSenderLabel: Bool {
        row.message.role != "system" && (
            row.previous == nil
                || row.previous?.role != row.message.role
                || MessageTimeFormatter.shouldShowDayHeader(current: row.message, previous: row.previous)
        )
    }

    private func senderLabel(for message: LiveMessage) -> String {
        if handler == "team", let sender = message.senderName, !sender.isEmpty {
            return message.role == "admin" ? sender : (sender.isEmpty ? customerDisplayName : sender)
        }
        switch message.role {
        case "admin":
            if let sender = message.senderName, !sender.isEmpty { return sender }
            return agentDisplayName
        case "assistant":
            return agentDisplayName
        case "user":
            if let sender = message.senderName, !sender.isEmpty { return sender }
            return customerDisplayName
        default:
            return L10n.ChatAgent
        }
    }

    var body: some View {
        Group {
            if MessageTimeFormatter.shouldShowDayHeader(current: row.message, previous: row.previous),
               let header = MessageTimeFormatter.dayHeader(from: row.message.ts) {
                Text(header)
                    .font(.caption2.weight(.medium))
                    .foregroundStyle(PAXTheme.textTertiary)
                    .frame(maxWidth: .infinity)
                    .padding(.vertical, 8)
            }

            if row.message.role == "system" {
                SystemMessageView(text: row.message.content)
            } else {
                MessageBubbleView(
                    message: row.message,
                    quotedMessage: row.quotedMessage,
                    canReply: handler == "admin" && canReply && row.message.role != "system",
                    canDelete: handler == "admin" && canReply && row.message.role != "system" && row.message.id > 0,
                    canAnalyze: handler == "admin" && row.message.role == "user" && onAnalyze != nil,
                    showTimestamp: MessageTimeFormatter.shouldShowTimestamp(current: row.message, next: row.next),
                    senderLabel: showSenderLabel ? senderLabel(for: row.message) : nil,
                    agentDisplayName: agentDisplayName,
                    customerDisplayName: customerDisplayName,
                    siteBaseURL: siteBaseURL,
                    onReply: onReply,
                    onCopy: onCopy,
                    onDelete: onDelete,
                    onAnalyze: onAnalyze,
                    onLinkReview: onLinkReview,
                    isLinkReviewSubmitting: linkReviewSubmittingIds.contains(row.message.id),
                    onImageTap: { onImageTap($0) }
                )
                .opacity(deletingMessageIds.contains(row.message.id) ? 0 : 1)
                .scaleEffect(deletingMessageIds.contains(row.message.id) ? 0.9 : 1, anchor: .center)
                .blur(radius: deletingMessageIds.contains(row.message.id) ? 4 : 0)
                .animation(.easeOut(duration: 0.22), value: deletingMessageIds.contains(row.message.id))
                if handler == "team", row.message.role == "admin" {
                    TeamMessageDeliveryStatus(
                        message: row.message,
                        otherReadSeq: teamOtherReadSeq,
                        failedClientMsgIds: teamFailedClientMsgIds,
                        onRetry: onRetryTeamMessage
                    )
                    .padding(.top, 2)
                }
            }
        }
    }
}

private struct TeamMessageDeliveryStatus: View {
    let message: LiveMessage
    let otherReadSeq: Int
    let failedClientMsgIds: Set<String>
    let onRetry: ((String) -> Void)?

    private var label: String {
        if let clientId = message.clientMsgId, failedClientMsgIds.contains(clientId) {
            return "Failed"
        }
        if message.id < 0 { return "Sending" }
        if otherReadSeq >= message.id && message.id > 0 { return "Read" }
        if message.id > 0 { return "Delivered" }
        return "Sent"
    }

    private var tint: Color {
        label == "Failed" ? PAXTheme.danger : PAXTheme.textTertiary
    }

    var body: some View {
        HStack(spacing: 6) {
            Text(label)
                .font(.caption2)
                .foregroundStyle(tint)
            if label == "Failed", let clientId = message.clientMsgId, let onRetry {
                Button("Retry") { onRetry(clientId) }
                    .font(.caption2.weight(.semibold))
            }
        }
        .frame(maxWidth: .infinity, alignment: .trailing)
        .padding(.trailing, 8)
    }
}

private struct SystemMessageView: View {
    let text: String

    var body: some View {
        Text(text)
            .font(.caption)
            .foregroundStyle(PAXTheme.textSecondary)
            .multilineTextAlignment(.center)
            .padding(.horizontal, 14)
            .padding(.vertical, 6)
            .frame(maxWidth: .infinity)
            .background(
                Capsule()
                    .fill(PAXTheme.systemBubble)
            )
            .padding(.vertical, 4)
    }
}

private struct TypingIndicator: View {
    let customerName: String
    @State private var animate = false

    var body: some View {
        HStack(spacing: 6) {
            HStack(spacing: 4) {
                ForEach(0..<3, id: \.self) { i in
                    Circle().fill(PAXTheme.textSecondary).frame(width: 5, height: 5)
                        .opacity(animate ? 1 : 0.3)
                        .animation(.easeInOut(duration: 0.45).repeatForever().delay(Double(i) * 0.12), value: animate)
                }
            }
            .padding(.horizontal, 12)
            .padding(.vertical, 8)
            .background(Capsule().fill(PAXTheme.userBubble))
            Text(L10n.ChatCustomerTyping)
                .font(.caption2)
                .foregroundStyle(PAXTheme.textSecondary)
        }
        .accessibilityLabel("\(customerName) \(L10n.ChatCustomerTyping)")
        .onAppear { animate = true }
    }
}
