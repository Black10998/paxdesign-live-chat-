import SwiftUI

struct MessageBubbleView: View {
    let message: LiveMessage
    let quotedMessage: LiveMessage?
    let canReply: Bool
    let showTimestamp: Bool
    let onReply: () -> Void
    let onCopy: () -> Void
    let onImageTap: (URL) -> Void

    var body: some View {
        HStack(alignment: .bottom, spacing: 4) {
            if isOutgoing { Spacer(minLength: 52) }

            VStack(alignment: isOutgoing ? .trailing : .leading, spacing: 3) {
                bubbleContent
                    .contextMenu {
                        if !message.content.isEmpty {
                            Button {
                                onCopy()
                            } label: {
                                Label("Kopieren", systemImage: "doc.on.doc")
                            }

                            ShareLink(item: message.content) {
                                Label("Teilen", systemImage: "square.and.arrow.up")
                            }
                        }
                        if canReply {
                            Button {
                                onReply()
                            } label: {
                                Label("Antworten", systemImage: "arrowshape.turn.up.left")
                            }
                        }
                    }

                HStack(spacing: 6) {
                    if let reaction = message.reaction {
                        MessageReactionBadge(reaction: reaction)
                    }
                    if showTimestamp, let time = MessageTimeFormatter.timeString(from: message.ts) {
                        Text(time)
                            .font(.caption2)
                            .foregroundStyle(PAXTheme.textTertiary)
                    }
                }
            }

            if !isOutgoing { Spacer(minLength: 52) }
        }
    }

    @ViewBuilder
    private var bubbleContent: some View {
        HStack(alignment: .bottom, spacing: 0) {
            if !isOutgoing {
                BubbleTail(isOutgoing: false)
            }

            VStack(alignment: .leading, spacing: 5) {
                if let quotedMessage {
                    QuotePreviewView(message: quotedMessage)
                }

                if let imageUrl = message.imageUrl, let url = URL(string: imageUrl) {
                    CachedChatImage(url: url) {
                        onImageTap(url)
                    }
                }

                if !message.content.isEmpty {
                    Text(message.content)
                        .font(.subheadline)
                        .foregroundStyle(PAXTheme.textPrimary)
                        .multilineTextAlignment(.leading)
                } else if message.imageUrl != nil {
                    Text(L10n.ChatImage)
                        .font(.caption)
                        .foregroundStyle(PAXTheme.textSecondary)
                }
            }
            .padding(.horizontal, PAXMessageStyle.bubblePaddingH)
            .padding(.vertical, PAXMessageStyle.bubblePaddingV)
            .background(
                RoundedRectangle(cornerRadius: PAXMessageStyle.bubbleRadius, style: .continuous)
                    .fill(PAXMessageStyle.bubbleColor(role: message.role, isOutgoing: isOutgoing))
            )
            .frame(
                maxWidth: min(300, UIScreen.main.bounds.width * PAXMessageStyle.maxBubbleWidthRatio),
                alignment: isOutgoing ? .trailing : .leading
            )

            if isOutgoing {
                BubbleTail(isOutgoing: true)
            }
        }
    }

    private var isOutgoing: Bool { message.role == "admin" }
}

private struct BubbleTail: View {
    let isOutgoing: Bool

    var body: some View {
        BubbleTailShape(isOutgoing: isOutgoing)
            .fill(PAXMessageStyle.bubbleColor(role: isOutgoing ? "admin" : "user", isOutgoing: isOutgoing))
            .frame(width: PAXMessageStyle.tailWidth, height: PAXMessageStyle.tailHeight)
            .offset(x: isOutgoing ? 1 : -1, y: 2)
    }
}

private struct BubbleTailShape: Shape {
    let isOutgoing: Bool

    func path(in rect: CGRect) -> Path {
        var path = Path()
        if isOutgoing {
            path.move(to: CGPoint(x: rect.minX, y: rect.maxY))
            path.addQuadCurve(
                to: CGPoint(x: rect.maxX, y: rect.minY),
                control: CGPoint(x: rect.minX, y: rect.minY)
            )
            path.addLine(to: CGPoint(x: rect.minX, y: rect.maxY))
        } else {
            path.move(to: CGPoint(x: rect.maxX, y: rect.maxY))
            path.addQuadCurve(
                to: CGPoint(x: rect.minX, y: rect.minY),
                control: CGPoint(x: rect.maxX, y: rect.minY)
            )
            path.addLine(to: CGPoint(x: rect.maxX, y: rect.maxY))
        }
        return path
    }
}

private struct QuotePreviewView: View {
    let message: LiveMessage

    var body: some View {
        HStack(spacing: 8) {
            RoundedRectangle(cornerRadius: 2, style: .continuous)
                .fill(PAXTheme.accent)
                .frame(width: 3)

            VStack(alignment: .leading, spacing: 2) {
                Text(message.role == "admin" ? "Sie" : "Kunde")
                    .font(.caption2.weight(.semibold))
                    .foregroundStyle(PAXTheme.accent)
                Text(previewText)
                    .font(.caption2)
                    .foregroundStyle(PAXTheme.textSecondary)
                    .lineLimit(2)
            }
        }
        .padding(7)
        .frame(maxWidth: .infinity, alignment: .leading)
        .background(Color.black.opacity(0.16))
        .clipShape(RoundedRectangle(cornerRadius: 9, style: .continuous))
    }

    private var previewText: String {
        if !message.content.isEmpty { return message.content }
        if message.imageUrl != nil { return "Bild" }
        return "—"
    }
}

struct ReplyBarView: View {
    let message: LiveMessage
    let onClear: () -> Void

    var body: some View {
        HStack(spacing: 10) {
            RoundedRectangle(cornerRadius: 2)
                .fill(PAXTheme.accent)
                .frame(width: 3, height: 34)

            VStack(alignment: .leading, spacing: 2) {
                Text("Antwort auf \(message.role == "admin" ? "Sie" : "Kunde")")
                    .font(.caption2.weight(.semibold))
                    .foregroundStyle(PAXTheme.accent)
                Text(message.content.isEmpty ? "Bild" : String(message.content.prefix(90)))
                    .font(.caption)
                    .foregroundStyle(PAXTheme.textSecondary)
                    .lineLimit(1)
            }

            Spacer()

            Button(action: onClear) {
                Image(systemName: "xmark.circle.fill")
                    .foregroundStyle(PAXTheme.textTertiary)
            }
            .buttonStyle(.plain)
        }
        .padding(.horizontal, 12)
        .padding(.vertical, 7)
        .background(PAXTheme.surface.opacity(0.95))
    }
}
