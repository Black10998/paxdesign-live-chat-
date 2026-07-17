import SwiftUI

struct CustomerHTMLTextView: View {
    let html: String
    let plainFallback: String?

    var body: some View {
        if let attributed = Self.attributedString(from: html) {
            Text(attributed)
                .font(.body)
                .foregroundStyle(PAXTheme.textPrimary)
                .frame(maxWidth: .infinity, alignment: .leading)
        } else if let plainFallback, !plainFallback.isEmpty {
            Text(plainFallback)
                .font(.body)
                .foregroundStyle(PAXTheme.textPrimary)
                .frame(maxWidth: .infinity, alignment: .leading)
        }
    }

    private static func attributedString(from html: String) -> AttributedString? {
        guard let data = html.data(using: .utf8) else { return nil }
        let options: [NSAttributedString.DocumentReadingOptionKey: Any] = [
            .documentType: NSAttributedString.DocumentType.html,
            .characterEncoding: String.Encoding.utf8.rawValue,
        ]
        guard let ns = try? NSAttributedString(data: data, options: options, documentAttributes: nil),
              !ns.string.trimmingCharacters(in: .whitespacesAndNewlines).isEmpty else {
            return nil
        }
        return AttributedString(ns)
    }
}

struct CustomerNativeContentBlocksView: View {
    let blocks: [CustomerContentBlock]

    var body: some View {
        VStack(alignment: .leading, spacing: CustomerPortalDesign.sectionSpacing) {
            ForEach(Array(blocks.enumerated()), id: \.offset) { _, block in
                blockView(block)
            }
        }
    }

    @ViewBuilder
    private func blockView(_ block: CustomerContentBlock) -> some View {
        switch block.type {
        case "heading":
            Text(block.text ?? "")
                .font(headingFont(level: block.level ?? 2))
                .foregroundStyle(PAXTheme.textPrimary)
                .frame(maxWidth: .infinity, alignment: .leading)

        case "text":
            if let html = block.html, !html.isEmpty {
                CustomerHTMLTextView(html: html, plainFallback: block.text)
            } else if let text = block.text, !text.isEmpty {
                Text(text)
                    .font(.body)
                    .foregroundStyle(PAXTheme.textPrimary)
            }

        case "image":
            if let urlString = block.url, let url = URL(string: urlString) {
                CustomerPortalCard {
                    VStack(alignment: .leading, spacing: 8) {
                        AsyncImage(url: url) { phase in
                            if case .success(let image) = phase {
                                image.resizable().scaledToFill()
                            } else {
                                Rectangle().fill(PAXTheme.accentSoft)
                            }
                        }
                        .frame(maxWidth: .infinity)
                        .frame(height: 200)
                        .clipShape(RoundedRectangle(cornerRadius: 12, style: .continuous))
                        if let caption = block.caption, !caption.isEmpty {
                            Text(caption)
                                .font(.caption)
                                .foregroundStyle(PAXTheme.textSecondary)
                        }
                    }
                }
            }

        case "gallery":
            if let images = block.images, !images.isEmpty {
                CustomerPortalCard {
                    VStack(alignment: .leading, spacing: 12) {
                        CustomerPortalSectionHeader(title: String(localized: "Gallery"))
                        ScrollView(.horizontal, showsIndicators: false) {
                            HStack(spacing: 12) {
                                ForEach(images, id: \.self) { urlString in
                                    if let url = URL(string: urlString) {
                                        AsyncImage(url: url) { phase in
                                            if case .success(let image) = phase {
                                                image.resizable().scaledToFill()
                                            } else {
                                                Color.gray.opacity(0.15)
                                            }
                                        }
                                        .frame(width: 200, height: 140)
                                        .clipShape(RoundedRectangle(cornerRadius: 12, style: .continuous))
                                    }
                                }
                            }
                        }
                    }
                }
            }

        case "feature":
            CustomerPortalCard {
                VStack(alignment: .leading, spacing: 8) {
                    if let urlString = block.url, let url = URL(string: urlString) {
                        AsyncImage(url: url) { phase in
                            if case .success(let image) = phase {
                                image.resizable().scaledToFill()
                            } else {
                                Rectangle().fill(PAXTheme.accentSoft)
                            }
                        }
                        .frame(maxWidth: .infinity)
                        .frame(height: 160)
                        .clipShape(RoundedRectangle(cornerRadius: 12, style: .continuous))
                    }
                    if let title = block.title, !title.isEmpty {
                        Label(title, systemImage: "sparkles")
                            .font(.headline)
                    }
                    if let text = block.text, !text.isEmpty {
                        Text(text)
                            .font(.subheadline)
                            .foregroundStyle(PAXTheme.textSecondary)
                    }
                }
            }

        case "list":
            if let items = block.listItems, !items.isEmpty {
                CustomerPortalCard {
                    VStack(alignment: .leading, spacing: 8) {
                        ForEach(items, id: \.self) { item in
                            Label(item, systemImage: "checkmark.circle.fill")
                                .font(.subheadline)
                                .foregroundStyle(PAXTheme.textPrimary)
                        }
                    }
                }
            }

        case "accordion":
            if let items = block.accordionItems, !items.isEmpty {
                CustomerPortalCard {
                    VStack(alignment: .leading, spacing: 12) {
                        ForEach(Array(items.enumerated()), id: \.offset) { _, item in
                            VStack(alignment: .leading, spacing: 4) {
                                if !item.title.isEmpty {
                                    Text(item.title).font(.headline)
                                }
                                if !item.text.isEmpty {
                                    Text(item.text)
                                        .font(.subheadline)
                                        .foregroundStyle(PAXTheme.textSecondary)
                                }
                            }
                            if item.title != items.last?.title { Divider() }
                        }
                    }
                }
            }

        case "button":
            if block.action == "page", let slug = block.slug, !slug.isEmpty {
                NavigationLink {
                    CustomerNativePageView(slug: slug, title: block.text ?? slug)
                } label: {
                    Text(block.text ?? slug)
                        .frame(maxWidth: .infinity)
                }
                .buttonStyle(CustomerPrimaryButtonStyleModifier(style: .tinted))
            } else if block.action == "external", let urlString = block.url, let url = URL(string: urlString) {
                Link(destination: url) {
                    Text(block.text ?? urlString)
                        .frame(maxWidth: .infinity)
                }
                .buttonStyle(CustomerPrimaryButtonStyleModifier(style: .tinted))
            }

        case "video":
            if let urlString = block.url, let url = URL(string: urlString) {
                CustomerPortalCard {
                    VStack(alignment: .leading, spacing: 10) {
                        if let title = block.title, !title.isEmpty {
                            Text(title).font(.headline)
                        }
                        Link(destination: url) {
                            Label(String(localized: "Watch video"), systemImage: "play.circle.fill")
                                .frame(maxWidth: .infinity)
                        }
                        .buttonStyle(CustomerPrimaryButtonStyleModifier(style: .tinted))
                    }
                }
            }

        default:
            if let text = block.text, !text.isEmpty {
                Text(text)
                    .font(.body)
                    .foregroundStyle(PAXTheme.textSecondary)
            } else {
                EmptyView()
            }
        }
    }

    private func headingFont(level: Int) -> Font {
        switch level {
        case 1: return .largeTitle.weight(.bold)
        case 3: return .title3.weight(.semibold)
        case 4: return .headline
        case 5: return .subheadline.weight(.semibold)
        default: return .title2.weight(.bold)
        }
    }
}
