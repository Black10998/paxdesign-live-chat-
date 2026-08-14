import SwiftUI

// MARK: - Revolut-inspired design system adapted for PAXDesign brand

/// Spacing scale (Revolut 4pt grid).
enum PAXSpacing {
    static let xxs: CGFloat = 4
    static let xs: CGFloat = 8
    static let sm: CGFloat = 12
    static let md: CGFloat = 16
    static let lg: CGFloat = 20
    static let xl: CGFloat = 24
    static let xxl: CGFloat = 32
    static let screenHorizontal: CGFloat = 16
    static let sectionGap: CGFloat = 24
    static let listRowHeight: CGFloat = 64
    static let listRowHeightLarge: CGFloat = 72
    static let primaryButtonHeight: CGFloat = 52
    static let iconTileSize: CGFloat = 40
}

/// Typography hierarchy (SF Pro / system — Revolut structure, PAXDesign content).
enum PAXTypography {
    static let balance = Font.system(size: 40, weight: .bold, design: .rounded)
    static let titleLarge = Font.system(size: 28, weight: .bold)
    static let section = Font.system(size: 22, weight: .bold)
    static let subsection = Font.system(size: 18, weight: .semibold)
    static let rowTitle = Font.system(size: 16, weight: .semibold)
    static let body = Font.system(size: 15, weight: .regular)
    static let button = Font.system(size: 16, weight: .semibold)
    static let meta = Font.system(size: 13, weight: .regular)
    static let caption = Font.system(size: 11, weight: .regular)
    static let tabLabel = Font.system(size: 10, weight: .semibold)
    static let labelUpper = Font.system(size: 11, weight: .bold)
}

/// Revolut-style canvas and surface tokens with guaranteed light/dark contrast.
enum PAXRevolutColors {
    static func canvas(isDark: Bool) -> Color {
        isDark
            ? Color(red: 0.039, green: 0.039, blue: 0.059)
            : Color(red: 0.949, green: 0.949, blue: 0.969)
    }

    static func surface1(isDark: Bool) -> Color {
        isDark
            ? Color(red: 0.086, green: 0.086, blue: 0.122)
            : Color.white
    }

    static func surface2(isDark: Bool) -> Color {
        isDark
            ? Color(red: 0.118, green: 0.118, blue: 0.165)
            : Color(red: 0.96, green: 0.96, blue: 0.98)
    }

    static func surface3(isDark: Bool) -> Color {
        isDark
            ? Color(red: 0.157, green: 0.157, blue: 0.227)
            : Color(red: 0.92, green: 0.92, blue: 0.95)
    }

    static func divider(isDark: Bool) -> Color {
        isDark
            ? Color(red: 0.165, green: 0.165, blue: 0.220)
            : Color(red: 0.88, green: 0.88, blue: 0.90)
    }

    static func textPrimary(isDark: Bool) -> Color {
        isDark ? .white : Color(red: 0.08, green: 0.08, blue: 0.10)
    }

    static func textSecondary(isDark: Bool) -> Color {
        isDark
            ? Color(red: 0.604, green: 0.604, blue: 0.667)
            : Color(red: 0.38, green: 0.38, blue: 0.42)
    }

    static func textTertiary(isDark: Bool) -> Color {
        isDark
            ? Color(red: 0.416, green: 0.416, blue: 0.494)
            : Color(red: 0.52, green: 0.52, blue: 0.56)
    }

    /// Primary CTA label — always readable on brand fill.
    static func onPrimaryFill(isDark: Bool, accentIsLight: Bool = false) -> Color {
        if accentIsLight || isDark {
            return .black
        }
        return .white
    }
}

/// Revolut-style press interaction.
struct PAXRevolutPressableStyle: ButtonStyle {
    @Environment(\.accessibilityReduceMotion) private var reduceMotion

    func makeBody(configuration: Configuration) -> some View {
        configuration.label
            .opacity(configuration.isPressed ? 0.88 : 1)
            .scaleEffect(configuration.isPressed && !reduceMotion ? 0.98 : 1)
            .animation(reduceMotion ? nil : .easeOut(duration: 0.15), value: configuration.isPressed)
    }
}

/// Solid elevated surface card (Revolut tile pattern).
private struct PAXRevolutSurfaceModifier: ViewModifier {
    let cornerRadius: CGFloat
    let elevation: Int
    @Environment(\.colorScheme) private var colorScheme

    private var isDark: Bool { colorScheme == .dark }

    func body(content: Content) -> some View {
        let fill: Color = {
            switch elevation {
            case 0: return PAXRevolutColors.surface1(isDark: isDark)
            case 1: return PAXRevolutColors.surface2(isDark: isDark)
            default: return PAXRevolutColors.surface3(isDark: isDark)
            }
        }()

        content
            .background(
                RoundedRectangle(cornerRadius: cornerRadius, style: .continuous)
                    .fill(fill)
                    .overlay(
                        RoundedRectangle(cornerRadius: cornerRadius, style: .continuous)
                            .strokeBorder(PAXRevolutColors.divider(isDark: isDark), lineWidth: 1)
                    )
            )
            .shadow(
                color: .black.opacity(isDark ? 0.35 : 0.06),
                radius: isDark ? 0 : 8,
                x: 0,
                y: isDark ? 0 : 4
            )
    }
}

extension View {
    func paxRevolutSurface(cornerRadius: CGFloat = 16, elevation: Int = 0) -> some View {
        modifier(PAXRevolutSurfaceModifier(cornerRadius: cornerRadius, elevation: elevation))
    }

    func paxRevolutScreenPadding() -> some View {
        padding(.horizontal, PAXSpacing.screenHorizontal)
    }

    func paxRevolutSectionSpacing() -> some View {
        padding(.bottom, PAXSpacing.sectionGap)
    }
}

/// Standard list row (Revolut transaction row pattern).
struct PAXRevolutListRow<Leading: View, Trailing: View>: View {
    let title: String
    let subtitle: String?
    @ViewBuilder var leading: () -> Leading
    @ViewBuilder var trailing: () -> Trailing

    init(
        title: String,
        subtitle: String? = nil,
        @ViewBuilder leading: @escaping () -> Leading = { EmptyView() },
        @ViewBuilder trailing: @escaping () -> Trailing = { EmptyView() }
    ) {
        self.title = title
        self.subtitle = subtitle
        self.leading = leading
        self.trailing = trailing
    }

    var body: some View {
        HStack(spacing: PAXSpacing.sm) {
            leading()
            VStack(alignment: .leading, spacing: 2) {
                Text(title)
                    .font(PAXTypography.rowTitle)
                    .foregroundStyle(PAXTheme.textPrimary)
                    .lineLimit(1)
                if let subtitle, !subtitle.isEmpty {
                    Text(subtitle)
                        .font(PAXTypography.meta)
                        .foregroundStyle(PAXTheme.textSecondary)
                        .lineLimit(1)
                }
            }
            Spacer(minLength: 0)
            trailing()
        }
        .frame(minHeight: PAXSpacing.listRowHeight)
        .padding(.horizontal, PAXSpacing.md)
        .contentShape(Rectangle())
    }
}

/// Primary CTA — Revolut gradient structure with PAXDesign brand accent.
struct PAXRevolutPrimaryButton: View {
    let title: String
    var isLoading = false
    let action: () -> Void

    @Environment(\.colorScheme) private var colorScheme

    private var isDark: Bool { colorScheme == .dark }

    var body: some View {
        Button(action: action) {
            HStack(spacing: PAXSpacing.xs) {
                if isLoading {
                    ProgressView()
                        .tint(PAXTheme.onAccent)
                }
                Text(title)
                    .font(PAXTypography.button)
            }
            .foregroundStyle(PAXTheme.onAccent)
            .frame(maxWidth: .infinity, minHeight: PAXSpacing.primaryButtonHeight)
            .background(
                RoundedRectangle(cornerRadius: 16, style: .continuous)
                    .fill(PAXTheme.accent)
            )
            .shadow(color: PAXTheme.accent.opacity(isDark ? 0.25 : 0.18), radius: 14, y: 8)
        }
        .buttonStyle(PAXRevolutPressableStyle())
        .disabled(isLoading)
    }
}

/// Secondary CTA — Revolut surface2 button.
struct PAXRevolutSecondaryButton: View {
    let title: String
    let action: () -> Void

    @Environment(\.colorScheme) private var colorScheme

    var body: some View {
        Button(action: action) {
            Text(title)
                .font(PAXTypography.button)
                .foregroundStyle(PAXRevolutColors.textPrimary(isDark: colorScheme == .dark))
                .frame(maxWidth: .infinity, minHeight: PAXSpacing.primaryButtonHeight)
                .paxRevolutSurface(cornerRadius: 16, elevation: 1)
        }
        .buttonStyle(PAXRevolutPressableStyle())
    }
}

/// Section header — Revolut subsection typography.
struct PAXRevolutSectionHeader: View {
    let title: String
    var actionTitle: String? = nil
    var action: (() -> Void)? = nil

    var body: some View {
        HStack(alignment: .firstTextBaseline) {
            Text(title)
                .font(PAXTypography.section)
                .foregroundStyle(PAXTheme.textPrimary)
            Spacer(minLength: 0)
            if let actionTitle, let action {
                Button(actionTitle, action: action)
                    .font(PAXTypography.meta.weight(.semibold))
                    .foregroundStyle(PAXTheme.link)
            }
        }
        .padding(.horizontal, PAXSpacing.screenHorizontal)
    }
}
