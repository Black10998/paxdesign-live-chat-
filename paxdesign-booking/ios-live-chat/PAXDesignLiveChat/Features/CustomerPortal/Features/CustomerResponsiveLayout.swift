import SwiftUI

/// Shared responsive layout primitives for the customer portal.
enum CustomerResponsiveLayout {
    static let screenPadding: CGFloat = 20
    static let readableMaxWidth: CGFloat = 680

    static func metadataColumns(for sizeClass: UserInterfaceSizeClass?) -> [GridItem] {
        if sizeClass == .regular {
            return [GridItem(.flexible(), spacing: 12), GridItem(.flexible(), spacing: 12)]
        }
        return [GridItem(.flexible(), spacing: 12)]
    }

    static func statColumns(for sizeClass: UserInterfaceSizeClass?) -> [GridItem] {
        if sizeClass == .regular {
            return [GridItem(.flexible(), spacing: 12), GridItem(.flexible(), spacing: 12)]
        }
        return [GridItem(.flexible(), spacing: 12)]
    }
}

// MARK: - Modifiers

struct CustomerConstrainedContentModifier: ViewModifier {
    var alignment: Alignment = .leading

    func body(content: Content) -> some View {
        content
            .frame(maxWidth: .infinity, alignment: alignment)
    }
}

struct CustomerScreenPaddingModifier: ViewModifier {
    var edges: Edge.Set = .horizontal

    func body(content: Content) -> some View {
        content.padding(edges, CustomerResponsiveLayout.screenPadding)
    }
}

struct CustomerReadableWidthModifier: ViewModifier {
    func body(content: Content) -> some View {
        content
            .frame(maxWidth: CustomerResponsiveLayout.readableMaxWidth)
            .frame(maxWidth: .infinity)
    }
}

struct CustomerOverflowSafeModifier: ViewModifier {
    func body(content: Content) -> some View {
        content
            .frame(maxWidth: .infinity, alignment: .leading)
            .fixedSize(horizontal: false, vertical: true)
    }
}

extension View {
    func customerConstrainedContent(alignment: Alignment = .leading) -> some View {
        modifier(CustomerConstrainedContentModifier(alignment: alignment))
    }

    func customerScreenPadding(_ edges: Edge.Set = .horizontal) -> some View {
        modifier(CustomerScreenPaddingModifier(edges: edges))
    }

    func customerReadableWidth() -> some View {
        modifier(CustomerReadableWidthModifier())
    }

    func customerOverflowSafe() -> some View {
        modifier(CustomerOverflowSafeModifier())
    }
}

// MARK: - Text

struct CustomerResponsiveTitle: View {
    let text: String
    var color: Color = PAXTheme.textPrimary

    var body: some View {
        Text(text)
            .font(.title2.weight(.bold))
            .foregroundStyle(color)
            .multilineTextAlignment(.leading)
            .lineLimit(nil)
            .fixedSize(horizontal: false, vertical: true)
            .frame(maxWidth: .infinity, alignment: .leading)
            .minimumScaleFactor(0.85)
            .layoutPriority(1)
    }
}

struct CustomerResponsiveHeadline: View {
    let text: String
    var color: Color = PAXTheme.textPrimary

    var body: some View {
        Text(text)
            .font(.headline)
            .foregroundStyle(color)
            .multilineTextAlignment(.leading)
            .fixedSize(horizontal: false, vertical: true)
            .frame(maxWidth: .infinity, alignment: .leading)
    }
}

struct CustomerResponsiveBody: View {
    let text: String
    var color: Color = PAXTheme.textSecondary
    var lineSpacing: CGFloat = 6

    var body: some View {
        Text(text)
            .font(.body)
            .foregroundStyle(color)
            .lineSpacing(lineSpacing)
            .multilineTextAlignment(.leading)
            .fixedSize(horizontal: false, vertical: true)
            .frame(maxWidth: .infinity, alignment: .leading)
            .textSelection(.enabled)
    }
}

struct CustomerResponsiveCaption: View {
    let text: String
    var color: Color = PAXTheme.textSecondary

    var body: some View {
        Text(text)
            .font(.caption)
            .foregroundStyle(color)
            .multilineTextAlignment(.leading)
            .fixedSize(horizontal: false, vertical: true)
            .frame(maxWidth: .infinity, alignment: .leading)
    }
}
