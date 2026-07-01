import SwiftUI

struct MessageBubbleView: View {
    let message: LiveMessage
    let quotedMessage: LiveMessage?
    let canReply: Bool
    let onReply: () -> Void
    let onCopy: () -> Void
    let onImageTap: (URL) -> Void

    var body: some View {
        HStack(alignment: .bottom, spacing: 6) {
            if isOutgoing { Spacer(minLength: 56) }

            VStack(alignment: isOutgoing ? .trailing : .leading, spacing: 4) {
                bubbleContent
                    .contextMenu {
                        if !message.content.isEmpty {
                            Button {
                                onCopy()
                            } label: {
                                Label("Kopieren", systemImage: "doc.on.doc")
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

                if let reaction = message.reaction {
                    MessageReactionBadge(reaction: reaction)
                }
            }

            if !isOutgoing { Spacer(minLength: 56) }
        }
    }

    @ViewBuilder
    private var bubbleContent: some View {
        VStack(alignment: .leading, spacing: 6) {
            if let quotedMessage {
                QuotePreviewView(message: quotedMessage)
            }

            if let imageUrl = message.imageUrl, let url = URL(string: imageUrl) {
                Button {
                    onImageTap(url)
                } label: {
                    AsyncImage(url: url) { phase in
                        switch phase {
                        case .success(let image):
                            image
                                .resizable()
                                .scaledToFill()
                        case .failure:
                            Image(systemName: "photo")
                                .font(.title2)
                                .foregroundStyle(PAXTheme.textTertiary)
                                .frame(maxWidth: .infinity, maxHeight: .infinity)
                        default:
                            ProgressView()
                                .frame(maxWidth: .infinity, maxHeight: .infinity)
                        }
                    }
                    .frame(maxWidth: 220, maxHeight: PAXMessageStyle.imageMaxHeight)
                    .clipShape(RoundedRectangle(cornerRadius: PAXMessageStyle.imageCornerRadius, style: .continuous))
                }
                .buttonStyle(.plain)
            }

            if !message.content.isEmpty {
                Text(message.content)
                    .font(.subheadline)
                    .foregroundStyle(PAXTheme.textPrimary)
                    .multilineTextAlignment(.leading)
            } else if message.imageUrl != nil {
                Text("Bild")
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
        .frame(maxWidth: UIScreen.main.bounds.width * PAXMessageStyle.maxBubbleWidthRatio, alignment: isOutgoing ? .trailing : .leading)
    }

    private var isOutgoing: Bool { message.role == "admin" }
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
        .padding(8)
        .frame(maxWidth: .infinity, alignment: .leading)
        .background(Color.black.opacity(0.18))
        .clipShape(RoundedRectangle(cornerRadius: 10, style: .continuous))
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
                .frame(width: 3, height: 36)

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
        .padding(.horizontal, 14)
        .padding(.vertical, 8)
        .background(PAXTheme.surface.opacity(0.95))
    }
}
