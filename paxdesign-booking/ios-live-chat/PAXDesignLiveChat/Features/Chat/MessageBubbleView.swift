import SwiftUI

struct MessageBubbleView: View {
    @Environment(\.paxPalette) private var palette

    let message: LiveMessage
    let quotedMessage: LiveMessage?
    let canReply: Bool
    let canDelete: Bool
    var canAnalyze = false
    let showTimestamp: Bool
    var senderLabel: String?
    var agentDisplayName = L10n.ChatAgent
    var customerDisplayName = L10n.ChatCustomer
    var siteBaseURL: String?
    let onReply: () -> Void
    let onCopy: () -> Void
    let onDelete: () -> Void
    var onAnalyze: (() -> Void)?
    var onLinkReview: ((String) -> Void)?
    var isLinkReviewSubmitting = false
    var isTeamChat = false
    let onImageTap: (URL) -> Void

    var body: some View {
        HStack(alignment: .bottom, spacing: 4) {
            if isOutgoing { Spacer(minLength: 52) }

            VStack(alignment: isOutgoing ? .trailing : .leading, spacing: 3) {
                if let senderLabel {
                    Text(senderLabel)
                        .font(.caption2.weight(.semibold))
                        .foregroundStyle(isOutgoing ? PAXTheme.accent : PAXTheme.textSecondary)
                }

                bubbleContent
                    .contextMenu {
                        if !message.content.isEmpty {
                            Button {
                                onCopy()
                            } label: {
                                Label { Text(L10n.CommonCopy) } icon: { PAXIcon("doc.on.doc") }
                            }

                            ShareLink(item: message.content) {
                                Label { Text(L10n.CommonShare) } icon: { PAXIcon("square.and.arrow.up") }
                            }
                        }
                        if canReply {
                            Button {
                                onReply()
                            } label: {
                                Label { Text(L10n.CommonReply) } icon: { PAXIcon("arrowshape.turn.up.left") }
                            }
                        }
                        if canAnalyze, let onAnalyze {
                            Button {
                                onAnalyze()
                            } label: {
                                Label { Text(L10n.ChatAnalyzeMessage) } icon: { PAXIcon("sparkles") }
                            }
                        }
                        if canDelete {
                            Button(role: .destructive) {
                                onDelete()
                            } label: {
                                Label { Text(L10n.ChatDeleteMessage) } icon: { PAXIcon("trash") }
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
        if isTeamChat, message.isVoiceMessage {
            VoiceMessageBubbleView(message: message, isOutgoing: isOutgoing)
                .frame(
                    maxWidth: min(280, UIScreen.main.bounds.width * PAXMessageStyle.maxBubbleWidthRatio),
                    alignment: isOutgoing ? .trailing : .leading
                )
        } else if isTeamChat, message.isLocationMessage {
            LocationMessageBubbleView(message: message)
                .frame(
                    maxWidth: min(280, UIScreen.main.bounds.width * PAXMessageStyle.maxBubbleWidthRatio),
                    alignment: isOutgoing ? .trailing : .leading
                )
        } else if isTeamChat, hasStandaloneImage {
            TeamImageBubbleView(message: message, onImageTap: onImageTap)
                .frame(
                    maxWidth: min(280, UIScreen.main.bounds.width * PAXMessageStyle.maxBubbleWidthRatio),
                    alignment: isOutgoing ? .trailing : .leading
                )
        } else if isTeamChat, message.isFileMessage {
            TeamFileBubbleView(message: message, isOutgoing: isOutgoing)
                .frame(
                    maxWidth: min(280, UIScreen.main.bounds.width * PAXMessageStyle.maxBubbleWidthRatio),
                    alignment: isOutgoing ? .trailing : .leading
                )
        } else if message.isVoiceMessage {
            VoiceMessageBubbleView(message: message, isOutgoing: isOutgoing)
                .frame(
                    maxWidth: min(280, UIScreen.main.bounds.width * PAXMessageStyle.maxBubbleWidthRatio),
                    alignment: isOutgoing ? .trailing : .leading
                )
        } else if message.isFileMessage,
                  message.imageUrl == nil || (message.imageUrl ?? "").hasPrefix("pending://") {
            TeamFileBubbleView(message: message, isOutgoing: isOutgoing)
                .frame(
                    maxWidth: min(280, UIScreen.main.bounds.width * PAXMessageStyle.maxBubbleWidthRatio),
                    alignment: isOutgoing ? .trailing : .leading
                )
        } else {
            standardBubbleContent
        }
    }

    private var hasStandaloneImage: Bool {
        message.imageUrl != nil || message.attachmentType == "image"
    }

    @ViewBuilder
    private var standardBubbleContent: some View {
        HStack(alignment: .bottom, spacing: 0) {
            if !isOutgoing {
                BubbleTail(isOutgoing: false, fill: incomingBubbleFill)
            }

            VStack(alignment: .leading, spacing: 5) {
                if let quotedMessage {
                    QuotePreviewView(
                        message: quotedMessage,
                        agentDisplayName: agentDisplayName,
                        customerDisplayName: customerDisplayName
                    )
                }

                if let imageUrl = message.imageUrl,
                   !imageUrl.hasPrefix("pending://"),
                   let url = URL(string: imageUrl) {
                    CachedChatImage(url: url) {
                        onImageTap(url)
                    }
                }

                if message.isLocationMessage {
                    LocationMessageBubbleView(message: message)
                }

                if message.isLinkCard {
                    LinkCardBubbleView(message: message, siteBaseURL: siteBaseURL)
                } else if message.isInPlaceWarning {
                    Text(message.content)
                        .font(.system(size: PAXMessageStyle.bubbleFontSize))
                        .foregroundStyle(message.isInPlaceWarnStyle ? Color(red: 0.6, green: 0.2, blue: 0.07) : PAXTheme.textSecondary)
                        .multilineTextAlignment(.leading)
                } else if !message.content.isEmpty && !message.isVoiceMessage && !message.isLocationMessage {
                    Text(message.content)
                        .font(.system(size: PAXMessageStyle.bubbleFontSize))
                        .lineSpacing(PAXMessageStyle.bubbleLineSpacing)
                        .foregroundStyle(PAXMessageStyle.bubbleTextColor(isOutgoing: isOutgoing))
                        .multilineTextAlignment(PAXTextAlignment.natural(for: message.content))
                        .environment(\.layoutDirection, PAXTextAlignment.layoutDirection(for: message.content))
                } else if message.imageUrl != nil {
                    Text(L10n.ChatImage)
                        .font(.caption)
                        .foregroundStyle(PAXMessageStyle.bubbleTextColor(isOutgoing: isOutgoing).opacity(0.8))
                }

                if message.showsLinkScanBadge && !message.isInPlaceWarning {
                    LinkScanBadgeView(message: message, useStaffDisplay: canDelete)
                }

                if canDelete && message.needsLinkScanReview && !message.isInPlaceWarning, let onLinkReview {
                    LinkScanReviewActionsView(
                        message: message,
                        isSubmitting: isLinkReviewSubmitting,
                        onAction: onLinkReview
                    )
                }
            }
            .padding(.horizontal, PAXMessageStyle.bubblePaddingH)
            .padding(.top, PAXMessageStyle.bubblePaddingV)
            .padding(.bottom, PAXMessageStyle.bubblePaddingBottom)
            .background { bubbleBackground }
            .frame(
                maxWidth: min(300, UIScreen.main.bounds.width * PAXMessageStyle.maxBubbleWidthRatio),
                alignment: isOutgoing ? .trailing : .leading
            )

            if isOutgoing {
                BubbleTail(isOutgoing: true, fill: outgoingTailFill)
            }
        }
    }

    @ViewBuilder
    private var bubbleBackground: some View {
        if isOutgoing && !message.isInPlaceWarning && !message.isInPlaceWarnStyle {
            UnevenRoundedRectangle(
                topLeadingRadius: PAXMessageStyle.bubbleRadius,
                bottomLeadingRadius: PAXMessageStyle.bubbleRadius,
                bottomTrailingRadius: 0,
                topTrailingRadius: PAXMessageStyle.bubbleRadius,
                style: .continuous
            )
            .fill(PAXMessageStyle.outgoingGradient)
        } else {
            UnevenRoundedRectangle(
                topLeadingRadius: PAXMessageStyle.bubbleRadius,
                bottomLeadingRadius: 0,
                bottomTrailingRadius: PAXMessageStyle.bubbleRadius,
                topTrailingRadius: PAXMessageStyle.bubbleRadius,
                style: .continuous
            )
            .fill(bubbleFill)
        }
    }

    private var incomingBubbleFill: Color {
        if message.isInPlaceWarnStyle {
            return Color(red: 1.0, green: 0.97, blue: 0.94)
        }
        if message.isInPlaceWarning {
            return Color(red: 0.97, green: 0.98, blue: 0.99)
        }
        return PAXMessageStyle.incomingFill
    }

    private var outgoingTailFill: Color {
        Color(red: 37 / 255, green: 114 / 255, blue: 135 / 255)
    }

    private var isOutgoing: Bool { message.role == "admin" || message.role == "assistant" }

    private var bubbleFill: Color {
        if message.isInPlaceWarnStyle {
            return Color(red: 1.0, green: 0.97, blue: 0.94)
        }
        if message.isInPlaceWarning {
            return Color(red: 0.97, green: 0.98, blue: 0.99)
        }
        if isOutgoing {
            return Color(red: 37 / 255, green: 114 / 255, blue: 135 / 255)
        }
        return PAXMessageStyle.bubbleColor(role: message.role, isOutgoing: isOutgoing, palette: palette)
    }
}

private struct BubbleTail: View {
    let isOutgoing: Bool
    let fill: Color

    var body: some View {
        BubbleTailShape(isOutgoing: isOutgoing)
            .fill(fill)
            .frame(
                width: isOutgoing ? PAXMessageStyle.outgoingTailWidth : PAXMessageStyle.tailWidth,
                height: isOutgoing ? PAXMessageStyle.outgoingTailHeight : PAXMessageStyle.tailHeight
            )
            .offset(x: isOutgoing ? 0 : -1, y: isOutgoing ? 1 : 2)
    }
}

private struct BubbleTailShape: Shape {
    let isOutgoing: Bool

    func path(in rect: CGRect) -> Path {
        var path = Path()
        if isOutgoing {
            path.move(to: CGPoint(x: rect.maxX, y: rect.maxY))
            path.addLine(to: CGPoint(x: rect.minX, y: rect.minY))
            path.addLine(to: CGPoint(x: rect.maxX, y: rect.minY))
            path.closeSubpath()
        } else {
            path.move(to: CGPoint(x: rect.minX, y: rect.maxY))
            path.addLine(to: CGPoint(x: rect.maxX, y: rect.minY))
            path.addLine(to: CGPoint(x: rect.minX, y: rect.minY))
            path.closeSubpath()
        }
        return path
    }
}

private struct QuotePreviewView: View {
    let message: LiveMessage
    let agentDisplayName: String
    let customerDisplayName: String

    var body: some View {
        HStack(spacing: 8) {
            RoundedRectangle(cornerRadius: 2, style: .continuous)
                .fill(PAXTheme.accent)
                .frame(width: 3)

            VStack(alignment: .leading, spacing: 2) {
                Text(message.role == "admin" ? agentDisplayName : customerDisplayName)
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
        .background(
            RoundedRectangle(cornerRadius: 9, style: .continuous)
                .fill(.ultraThinMaterial)
                .overlay(
                    RoundedRectangle(cornerRadius: 9, style: .continuous)
                        .fill(PAXTheme.surface.opacity(0.62))
                )
                .overlay(
                    RoundedRectangle(cornerRadius: 9, style: .continuous)
                        .stroke(PAXTheme.border.opacity(0.36), lineWidth: 1)
                )
        )
    }

    private var previewText: String {
        if !message.content.isEmpty { return message.content }
        if message.imageUrl != nil { return L10n.ChatImage }
        return "—"
    }
}

struct ReplyBarView: View {
    let message: LiveMessage
    var agentDisplayName = L10n.ChatAgent
    var customerDisplayName = L10n.ChatCustomer
    let onClear: () -> Void

    var body: some View {
        HStack(spacing: 10) {
            RoundedRectangle(cornerRadius: 2)
                .fill(PAXTheme.accent)
                .frame(width: 3, height: 34)

            VStack(alignment: .leading, spacing: 2) {
                Text("\(L10n.ChatReplyTo) \(message.role == "admin" ? agentDisplayName : customerDisplayName)")
                    .font(.caption2.weight(.semibold))
                    .foregroundStyle(PAXTheme.accent)
                Text(message.content.isEmpty ? L10n.ChatImage : String(message.content.prefix(90)))
                    .font(.caption)
                    .foregroundStyle(PAXTheme.textSecondary)
                    .lineLimit(1)
            }

            Spacer()

            Button(action: onClear) {
                PAXIcon("xmark.circle.fill", emphasis: .tertiary)
            }
            .buttonStyle(.plain)
        }
        .padding(.horizontal, 12)
        .padding(.vertical, 7)
        .background(
            RoundedRectangle(cornerRadius: 12, style: .continuous)
                .fill(.ultraThinMaterial)
                .overlay(
                    RoundedRectangle(cornerRadius: 12, style: .continuous)
                        .fill(PAXTheme.surface.opacity(0.82))
                )
                .overlay(
                    RoundedRectangle(cornerRadius: 12, style: .continuous)
                        .stroke(PAXTheme.border.opacity(0.42), lineWidth: 1)
                )
        )
    }
}
