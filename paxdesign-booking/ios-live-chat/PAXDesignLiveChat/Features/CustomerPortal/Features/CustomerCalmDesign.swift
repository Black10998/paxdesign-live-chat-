import SwiftUI

/// Shared calm Apple-style spacing and surfaces for coordinated marketing screens.
enum CustomerCalmDesign {
    static let sectionSpacing: CGFloat = 32
    static let cardSpacing: CGFloat = 24
    static let cardRadius: CGFloat = 22
    static let shellRadius: CGFloat = 24
    static let contentPadding: CGFloat = 20
}

struct CustomerCalmTagRow: View {
    @Environment(\.marketingTheme) private var theme
    let tags: [String]

    var body: some View {
        HStack(spacing: 8) {
            ForEach(tags, id: \.self) { tag in
                Text(tag.uppercased())
                    .font(.system(size: 11, weight: .semibold))
                    .tracking(0.8)
                    .foregroundStyle(theme.textSecondary)
                    .padding(.horizontal, 10)
                    .padding(.vertical, 5)
                    .background(theme.panel)
                    .clipShape(Capsule())
                    .overlay(Capsule().stroke(theme.border, lineWidth: 0.5))
            }
        }
        .accessibilityElement(children: .combine)
    }
}

struct CustomerCalmSectionIntro: View {
    @Environment(\.marketingTheme) private var theme
    let tags: [String]
    let title: String
    let intro: String

    var body: some View {
        VStack(alignment: .leading, spacing: 14) {
            if !tags.isEmpty {
                CustomerCalmTagRow(tags: tags)
            }
            Text(title)
                .font(.system(size: 34, weight: .bold, design: .default))
                .tracking(-0.6)
                .foregroundStyle(theme.textPrimary)
                .fixedSize(horizontal: false, vertical: true)
            Text(intro)
                .font(.title3)
                .foregroundStyle(theme.textSecondary)
                .lineSpacing(4)
                .fixedSize(horizontal: false, vertical: true)
        }
        .frame(maxWidth: .infinity, alignment: .leading)
        .accessibilityElement(children: .combine)
    }
}

struct CustomerCalmShellCard<Content: View>: View {
    @Environment(\.marketingTheme) private var theme
    @ViewBuilder var content: () -> Content

    private var shellGradient: LinearGradient {
        LinearGradient(
            colors: [
                Color(red: 0.83, green: 1, blue: 0.2),
                theme.accent,
                Color(red: 0.66, green: 0.9, blue: 0)
            ],
            startPoint: .topLeading,
            endPoint: .bottomTrailing
        )
    }

    var body: some View {
        content()
            .background(theme.panel)
            .clipShape(RoundedRectangle(cornerRadius: CustomerCalmDesign.cardRadius, style: .continuous))
            .padding(1.5)
            .background(shellGradient)
            .clipShape(RoundedRectangle(cornerRadius: CustomerCalmDesign.shellRadius, style: .continuous))
            .shadow(color: theme.shadowDark.opacity(0.35), radius: 18, y: 10)
    }
}

struct CustomerCalmCTABlock: View {
    @Environment(\.marketingTheme) private var theme
    let tags: [String]
    let title: String
    let text: String
    let button: String
    let url: URL
    var action: (() -> Void)? = nil

    var body: some View {
        CustomerCalmShellCard {
            VStack(alignment: .leading, spacing: 16) {
                if !tags.isEmpty {
                    CustomerCalmTagRow(tags: tags)
                }
                Text(title)
                    .font(.title2.weight(.bold))
                    .foregroundStyle(theme.textPrimary)
                Text(text)
                    .font(.body)
                    .foregroundStyle(theme.textSecondary)
                    .lineSpacing(5)
                    .fixedSize(horizontal: false, vertical: true)
                if let action {
                    Button(button, action: action)
                        .buttonStyle(CustomerCalmAccentButtonStyle())
                } else {
                    Link(destination: url) {
                        Text(button)
                            .frame(maxWidth: .infinity)
                    }
                    .buttonStyle(CustomerCalmAccentButtonStyle())
                }
            }
            .padding(24)
            .frame(maxWidth: .infinity, alignment: .leading)
        }
        .accessibilityElement(children: .contain)
    }
}

struct CustomerCalmAccentButtonStyle: ButtonStyle {
    @Environment(\.marketingTheme) private var theme

    func makeBody(configuration: Configuration) -> some View {
        configuration.label
            .font(.headline)
            .padding(.vertical, 14)
            .padding(.horizontal, 18)
            .background(theme.accent.opacity(configuration.isPressed ? 0.85 : 1))
            .foregroundStyle(theme.accentOnAccent)
            .clipShape(RoundedRectangle(cornerRadius: 14, style: .continuous))
            .scaleEffect(configuration.isPressed ? 0.98 : 1)
            .animation(PAXTheme.quickSpring, value: configuration.isPressed)
    }
}

struct CustomerCalmCategoryChip: View {
    @Environment(\.marketingTheme) private var theme
    let title: String
    let isSelected: Bool
    let action: () -> Void

    var body: some View {
        Button(action: action) {
            Text(title)
                .font(.subheadline.weight(.semibold))
                .padding(.horizontal, 16)
                .padding(.vertical, 9)
                .background(isSelected ? theme.accent : theme.panel)
                .foregroundStyle(isSelected ? theme.accentOnAccent : theme.textPrimary)
                .clipShape(Capsule())
                .overlay(Capsule().stroke(theme.border, lineWidth: isSelected ? 0 : 0.5))
        }
        .buttonStyle(.plain)
        .accessibilityAddTraits(isSelected ? .isSelected : [])
    }
}

struct CustomerCalmStatGrid: View {
    @Environment(\.marketingTheme) private var theme
    let stats: [CustomerPortfolioStat]

    private let columns = [GridItem(.flexible(), spacing: 12), GridItem(.flexible(), spacing: 12)]

    var body: some View {
        LazyVGrid(columns: columns, spacing: 12) {
            ForEach(stats) { stat in
                VStack(alignment: .leading, spacing: 4) {
                    Text(stat.value)
                        .font(.system(size: 28, weight: .bold, design: .rounded))
                        .foregroundStyle(theme.accent)
                    Text(stat.label)
                        .font(.footnote)
                        .foregroundStyle(theme.textSecondary)
                        .fixedSize(horizontal: false, vertical: true)
                }
                .frame(maxWidth: .infinity, alignment: .leading)
                .padding(14)
                .background(theme.cardBackground)
                .clipShape(RoundedRectangle(cornerRadius: 14, style: .continuous))
                .overlay(
                    RoundedRectangle(cornerRadius: 14, style: .continuous)
                        .stroke(theme.border, lineWidth: 0.5)
                )
            }
        }
    }
}
