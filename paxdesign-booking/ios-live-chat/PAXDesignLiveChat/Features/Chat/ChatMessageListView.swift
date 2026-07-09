import SwiftUI

/// Shared lazy message list — avoids per-body `enumerated()` allocations.
struct ChatMessageListView: View {
    let messages: [LiveMessage]
    let userTyping: Bool
    let canReply: Bool
    let handler: String
    let quotedMessage: (LiveMessage) -> LiveMessage?
    let onReply: (LiveMessage) -> Void
    let onCopy: (LiveMessage) -> Void
    let onImageTap: (URL) -> Void

    var body: some View {
        let rows = Self.displayRows(for: messages)
        ScrollViewReader { proxy in
            ScrollView {
                LazyVStack(alignment: .leading, spacing: PAXMessageStyle.threadSpacing) {
                    ForEach(rows) { row in
                        ChatMessageRow(
                            row: row,
                            canReply: canReply,
                            handler: handler,
                            quotedMessage: quotedMessage(row.message),
                            onReply: { onReply(row.message) },
                            onCopy: { onCopy(row.message) },
                            onImageTap: onImageTap
                        )
                        .id(row.id)
                    }
                    if userTyping {
                        TypingIndicator()
                            .id("typing-indicator")
                    }
                }
                .padding(.horizontal, 12)
                .padding(.vertical, 10)
            }
            .onChange(of: messages.count) { _ in scrollToBottom(proxy: proxy) }
            .onChange(of: userTyping) { _ in scrollToBottom(proxy: proxy) }
        }
    }

    private static func displayRows(for messages: [LiveMessage]) -> [MessageDisplayRow] {
        messages.enumerated().map { index, message in
            MessageDisplayRow(
                id: message.id,
                message: message,
                previous: index > 0 ? messages[index - 1] : nil,
                next: index + 1 < messages.count ? messages[index + 1] : nil
            )
        }
    }

    private func scrollToBottom(proxy: ScrollViewProxy) {
        if userTyping {
            proxy.scrollTo("typing-indicator", anchor: .bottom)
        } else if let last = messages.last {
            proxy.scrollTo(last.id, anchor: .bottom)
        }
    }
}

private struct MessageDisplayRow: Identifiable {
    let id: Int
    let message: LiveMessage
    let previous: LiveMessage?
    let next: LiveMessage?
}

private struct ChatMessageRow: View {
    let row: MessageDisplayRow
    let canReply: Bool
    let handler: String
    let quotedMessage: LiveMessage?
    let onReply: () -> Void
    let onCopy: () -> Void
    let onImageTap: (URL) -> Void

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

            MessageBubbleView(
                message: row.message,
                quotedMessage: quotedMessage,
                canReply: handler == "admin" && canReply && row.message.role != "system",
                showTimestamp: MessageTimeFormatter.shouldShowTimestamp(current: row.message, next: row.next),
                onReply: onReply,
                onCopy: onCopy,
                onImageTap: { onImageTap($0) }
            )
        }
    }
}

private struct TypingIndicator: View {
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
        .onAppear { animate = true }
    }
}
