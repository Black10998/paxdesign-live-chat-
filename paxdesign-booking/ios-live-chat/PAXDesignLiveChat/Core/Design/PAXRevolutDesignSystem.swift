import SwiftUI
import UIKit

// MARK: - Dynamic color (always tracks the live trait collection)

enum PAXDynamic {
    static func color(_ light: UIColor, _ dark: UIColor) -> Color {
        Color(uiColor: UIColor { traits in
            traits.userInterfaceStyle == .dark ? dark : light
        })
    }

    static func uiColor(_ light: UIColor, _ dark: UIColor) -> UIColor {
        UIColor { traits in
            traits.userInterfaceStyle == .dark ? dark : light
        }
    }

    static let lime = UIColor(red: 194 / 255, green: 1, blue: 0, alpha: 1)
    static let limeEnd = UIColor(red: 154 / 255, green: 1, blue: 80 / 255, alpha: 1)
    static let indigo = UIColor(red: 0.357, green: 0.420, blue: 1, alpha: 1)
    static let indigoEnd = UIColor(red: 0.420, green: 0.357, blue: 1, alpha: 1)

    static let canvasLight = UIColor(red: 0.957, green: 0.957, blue: 0.973, alpha: 1) // #F4F4F8
    static let canvasDark = UIColor(red: 0.039, green: 0.039, blue: 0.059, alpha: 1) // #0A0A0F
    static let surface1Light = UIColor.white
    static let surface1Dark = UIColor(red: 0.086, green: 0.086, blue: 0.122, alpha: 1) // #16161F
    static let surface2Light = UIColor(red: 0.957, green: 0.957, blue: 0.973, alpha: 1)
    static let surface2Dark = UIColor(red: 0.118, green: 0.118, blue: 0.165, alpha: 1) // #1E1E2A
    static let surface3Light = UIColor(red: 0.910, green: 0.910, blue: 0.933, alpha: 1)
    static let surface3Dark = UIColor(red: 0.157, green: 0.157, blue: 0.227, alpha: 1) // #28283A
    static let dividerLight = UIColor(red: 0.890, green: 0.890, blue: 0.910, alpha: 1)
    static let dividerDark = UIColor(red: 0.165, green: 0.165, blue: 0.220, alpha: 1)
    static let borderLight = UIColor(red: 0.820, green: 0.820, blue: 0.850, alpha: 1)
    static let borderDark = UIColor(red: 0.200, green: 0.200, blue: 0.290, alpha: 1)
    static let textPrimaryLight = UIColor(red: 0.039, green: 0.039, blue: 0.059, alpha: 1)
    static let textPrimaryDark = UIColor.white
    static let textSecondaryLight = UIColor(red: 0.380, green: 0.380, blue: 0.420, alpha: 1)
    static let textSecondaryDark = UIColor(red: 0.604, green: 0.604, blue: 0.667, alpha: 1)
    static let textTertiaryLight = UIColor(red: 0.520, green: 0.520, blue: 0.560, alpha: 1)
    static let textTertiaryDark = UIColor(red: 0.416, green: 0.416, blue: 0.494, alpha: 1)
    static let income = UIColor(red: 0.122, green: 0.820, blue: 0.482, alpha: 1)
    static let spend = UIColor(red: 1, green: 0.353, blue: 0.416, alpha: 1)
    static let warn = UIColor(red: 1, green: 0.698, blue: 0.247, alpha: 1)
}

// MARK: - Spacing (Revolut 4pt grid)

enum PAXSpacing {
    static let xxs: CGFloat = 4
    static let xs: CGFloat = 8
    static let sm: CGFloat = 12
    static let md: CGFloat = 16
    static let lg: CGFloat = 20
    static let xl: CGFloat = 24
    static let xxl: CGFloat = 32
    static let hero: CGFloat = 40
    static let screenHorizontal: CGFloat = 16
    static let sectionGap: CGFloat = 24
    static let listRowHeight: CGFloat = 64
    static let listRowHeightLarge: CGFloat = 72
    static let primaryButtonHeight: CGFloat = 52
    static let inputHeight: CGFloat = 56
    static let quickActionSize: CGFloat = 56
    static let searchHeight: CGFloat = 44
    static let tabBarHeight: CGFloat = 56
    static let avatarRow: CGFloat = 40
    static let iconButtonHit: CGFloat = 44
}

// MARK: - Typography (Revolut hierarchy, SF Pro)

enum PAXTypography {
    static let balance = Font.system(size: 40, weight: .bold).leading(.tight)
    static let titleLarge = Font.system(size: 28, weight: .bold)
    static let section = Font.system(size: 22, weight: .bold)
    static let subsection = Font.system(size: 18, weight: .semibold)
    static let rowTitle = Font.system(size: 16, weight: .semibold)
    static let amount = Font.system(size: 16, weight: .semibold).monospacedDigit()
    static let body = Font.system(size: 15, weight: .regular)
    static let button = Font.system(size: 16, weight: .semibold)
    static let meta = Font.system(size: 13, weight: .regular)
    static let caption = Font.system(size: 11, weight: .regular)
    static let tabLabel = Font.system(size: 10, weight: .semibold)
    static let labelUpper = Font.system(size: 11, weight: .bold)
}

// MARK: - Brand gradient (PAX lime in dark, indigo-blue in light)

enum PAXBrandGradient {
    static var colors: [Color] {
        [
            PAXDynamic.color(PAXDynamic.indigo, PAXDynamic.lime),
            PAXDynamic.color(PAXDynamic.indigoEnd, PAXDynamic.limeEnd),
        ]
    }

    static var linear: LinearGradient {
        LinearGradient(colors: colors, startPoint: .topLeading, endPoint: .bottomTrailing)
    }

    static var glow: Color {
        PAXDynamic.color(
            PAXDynamic.indigo.withAlphaComponent(0.28),
            PAXDynamic.lime.withAlphaComponent(0.28)
        )
    }
}

// MARK: - Pressable

struct PAXRevolutPressableStyle: ButtonStyle {
    @Environment(\.accessibilityReduceMotion) private var reduceMotion

    func makeBody(configuration: Configuration) -> some View {
        configuration.label
            .opacity(configuration.isPressed ? 0.85 : 1)
            .scaleEffect(configuration.isPressed && !reduceMotion ? 0.98 : 1)
            .animation(reduceMotion ? nil : .easeOut(duration: 0.15), value: configuration.isPressed)
    }
}

// MARK: - Surfaces

private struct PAXRevolutSurfaceModifier: ViewModifier {
    let cornerRadius: CGFloat
    let elevation: Int

    func body(content: Content) -> some View {
        let fill: Color = {
            switch elevation {
            case 0: return PAXTheme.surface
            case 1: return PAXTheme.surfaceElevated
            default: return PAXTheme.surface3
            }
        }()
        content
            .background(
                RoundedRectangle(cornerRadius: cornerRadius, style: .continuous)
                    .fill(fill)
                    .overlay(
                        RoundedRectangle(cornerRadius: cornerRadius, style: .continuous)
                            .strokeBorder(PAXTheme.divider, lineWidth: 1)
                    )
            )
            .shadow(color: PAXTheme.cardShadow, radius: elevation >= 2 ? 16 : 8, x: 0, y: elevation >= 2 ? 8 : 2)
    }
}

extension View {
    func paxRevolutSurface(cornerRadius: CGFloat = 16, elevation: Int = 0) -> some View {
        modifier(PAXRevolutSurfaceModifier(cornerRadius: cornerRadius, elevation: elevation))
    }

    func paxRevolutScreenPadding() -> some View {
        padding(.horizontal, PAXSpacing.screenHorizontal)
    }
}

// MARK: - Buttons

struct PAXRevolutPrimaryButton: View {
    let title: String
    var isLoading = false
    let action: () -> Void

    var body: some View {
        Button(action: action) {
            HStack(spacing: PAXSpacing.xs) {
                if isLoading {
                    ProgressView().tint(PAXTheme.onAccent)
                }
                Text(title)
                    .font(PAXTypography.button)
            }
            .foregroundStyle(PAXTheme.onAccent)
            .frame(maxWidth: .infinity, minHeight: PAXSpacing.primaryButtonHeight)
            .background(
                RoundedRectangle(cornerRadius: 16, style: .continuous)
                    .fill(PAXBrandGradient.linear)
            )
            .shadow(color: PAXBrandGradient.glow, radius: 14, y: 8)
        }
        .buttonStyle(PAXRevolutPressableStyle())
        .disabled(isLoading)
        .accessibilityAddTraits(.isButton)
    }
}

struct PAXRevolutSecondaryButton: View {
    let title: String
    let action: () -> Void

    var body: some View {
        Button(action: action) {
            Text(title)
                .font(PAXTypography.button)
                .foregroundStyle(PAXTheme.textPrimary)
                .frame(maxWidth: .infinity, minHeight: PAXSpacing.primaryButtonHeight)
                .paxRevolutSurface(cornerRadius: 16, elevation: 1)
        }
        .buttonStyle(PAXRevolutPressableStyle())
    }
}

struct PAXRevolutGhostButton: View {
    let title: String
    let action: () -> Void

    var body: some View {
        Button(action: action) {
            Text(title)
                .font(PAXTypography.button)
                .foregroundStyle(PAXTheme.textSecondary)
                .frame(maxWidth: .infinity, minHeight: 44)
        }
        .buttonStyle(PAXRevolutPressableStyle())
    }
}

// MARK: - Circular quick action (signature Revolut home control)

struct PAXQuickActionVisual: View {
    let title: String
    let systemImage: String
    var emphasized = false

    var body: some View {
        VStack(spacing: 8) {
            ZStack {
                Circle()
                    .fill(emphasized ? AnyShapeStyle(PAXBrandGradient.linear) : AnyShapeStyle(PAXTheme.surfaceElevated))
                    .overlay {
                        if !emphasized {
                            Circle().strokeBorder(PAXTheme.divider, lineWidth: 1)
                        }
                    }
                    .shadow(color: emphasized ? PAXBrandGradient.glow : .clear, radius: 10, y: 4)
                PAXIcon(
                    systemImage,
                    size: .action,
                    tint: emphasized ? PAXTheme.onAccent : PAXTheme.textPrimary
                )
            }
            .frame(width: PAXSpacing.quickActionSize, height: PAXSpacing.quickActionSize)

            Text(title)
                .font(PAXTypography.meta)
                .foregroundStyle(PAXTheme.textSecondary)
                .lineLimit(1)
                .minimumScaleFactor(0.75)
        }
        .frame(maxWidth: .infinity)
    }
}

struct PAXQuickActionButton: View {
    let title: String
    let systemImage: String
    var emphasized = false
    let action: () -> Void

    var body: some View {
        Button(action: action) {
            PAXQuickActionVisual(title: title, systemImage: systemImage, emphasized: emphasized)
        }
        .buttonStyle(PAXRevolutPressableStyle())
        .accessibilityLabel(title)
    }
}

struct PAXQuickActionBar: View {
    let items: [Item]

    struct Item: Identifiable {
        let id = UUID()
        let title: String
        let systemImage: String
        var emphasized = false
        let action: () -> Void
    }

    var body: some View {
        HStack(alignment: .top, spacing: 8) {
            ForEach(items) { item in
                PAXQuickActionButton(
                    title: item.title,
                    systemImage: item.systemImage,
                    emphasized: item.emphasized,
                    action: item.action
                )
            }
        }
        .padding(.horizontal, PAXSpacing.screenHorizontal)
    }
}

// MARK: - Fields

struct PAXRevolutField: View {
    let title: String
    var systemImage: String? = nil
    @Binding var text: String
    var isSecure = false
    var keyboardType: UIKeyboardType = .default
    var submitLabel: SubmitLabel = .next
    var textContentType: UITextContentType? = nil

    @FocusState private var focused: Bool

    var body: some View {
        HStack(spacing: 12) {
            if let systemImage {
                PAXIcon(systemImage, size: .row, emphasis: .secondary)
            }
            Group {
                if isSecure {
                    SecureField(title, text: $text)
                        .textContentType(textContentType)
                } else {
                    TextField(title, text: $text)
                        .textInputAutocapitalization(.never)
                        .autocorrectionDisabled()
                        .keyboardType(keyboardType)
                        .textContentType(textContentType)
                }
            }
            .font(PAXTypography.body)
            .foregroundStyle(PAXTheme.textPrimary)
            .submitLabel(submitLabel)
            .focused($focused)
        }
        .padding(.horizontal, 16)
        .frame(minHeight: PAXSpacing.inputHeight)
        .background(
            RoundedRectangle(cornerRadius: 12, style: .continuous)
                .fill(PAXTheme.surface)
        )
        .overlay(
            RoundedRectangle(cornerRadius: 12, style: .continuous)
                .strokeBorder(focused ? PAXTheme.accent : PAXTheme.borderSubtle, lineWidth: focused ? 1.5 : 1)
        )
    }
}

struct PAXRevolutSearchBar: View {
    @Binding var text: String
    var prompt: String
    @FocusState.Binding var isFocused: Bool

    var body: some View {
        HStack(spacing: 10) {
            PAXIcon("magnifyingglass", size: .row, emphasis: .secondary)
            TextField(prompt, text: $text)
                .font(PAXTypography.body)
                .foregroundStyle(PAXTheme.textPrimary)
                .focused($isFocused)
            if !text.isEmpty {
                Button {
                    text = ""
                } label: {
                    PAXIcon("xmark.circle.fill", size: .inline, emphasis: .tertiary)
                }
                .buttonStyle(.plain)
            }
        }
        .padding(.horizontal, 16)
        .frame(height: PAXSpacing.searchHeight)
        .background(PAXTheme.surface, in: Capsule())
        .overlay(Capsule().strokeBorder(PAXTheme.divider, lineWidth: 1))
    }
}

// MARK: - Segmented chips

struct PAXRevolutSegmentedControl<Value: Hashable>: View {
    let items: [(Value, String)]
    @Binding var selection: Value

    var body: some View {
        HStack(spacing: 0) {
            ForEach(items, id: \.0) { item in
                let selected = selection == item.0
                Button {
                    withAnimation(.easeInOut(duration: 0.22)) {
                        selection = item.0
                    }
                    PAXHaptics.light()
                } label: {
                    Text(item.1)
                        .font(.system(size: 13, weight: .semibold))
                        .foregroundStyle(selected ? PAXTheme.onAccent : PAXTheme.textSecondary)
                        .padding(.horizontal, 14)
                        .padding(.vertical, 8)
                        .background {
                            if selected {
                                Capsule().fill(PAXBrandGradient.linear)
                            }
                        }
                }
                .buttonStyle(.plain)
            }
        }
        .padding(3)
        .background(PAXTheme.surfaceElevated, in: Capsule())
    }
}

// MARK: - List row (transaction pattern)

struct PAXRevolutListRow<Leading: View, Trailing: View>: View {
    let title: String
    let subtitle: String?
    var highlighted = false
    @ViewBuilder var leading: () -> Leading
    @ViewBuilder var trailing: () -> Trailing

    var body: some View {
        HStack(spacing: 12) {
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
        .padding(.horizontal, PAXSpacing.md)
        .frame(minHeight: PAXSpacing.listRowHeight)
        .background(highlighted ? PAXTheme.surfaceElevated : Color.clear)
        .contentShape(Rectangle())
    }
}

struct PAXRevolutGlyphAvatar: View {
    let systemImage: String
    var size: CGFloat = 40
    var tint: Color = PAXTheme.accent

    var body: some View {
        ZStack {
            Circle().fill(PAXTheme.surfaceElevated)
            PAXIcon(systemImage, size: size >= 48 ? .hero : .card, tint: tint)
        }
        .frame(width: size, height: size)
        .overlay(Circle().strokeBorder(PAXTheme.divider, lineWidth: 1))
    }
}

// MARK: - Metric / currency tile

struct PAXRevolutMetricTile: View {
    let title: String
    let value: String
    let systemImage: String
    var tint: Color = PAXTheme.accent
    var trend: Double? = nil

    var body: some View {
        HStack(spacing: 12) {
            PAXRevolutGlyphAvatar(systemImage: systemImage, size: 40, tint: tint)
            VStack(alignment: .leading, spacing: 2) {
                Text(title)
                    .font(PAXTypography.meta)
                    .foregroundStyle(PAXTheme.textSecondary)
                    .lineLimit(1)
                Text(value)
                    .font(PAXTypography.section)
                    .monospacedDigit()
                    .foregroundStyle(PAXTheme.textPrimary)
                    .lineLimit(1)
                    .minimumScaleFactor(0.7)
            }
            Spacer(minLength: 0)
            if let trend {
                Text(String(format: "%+.0f%%", trend))
                    .font(PAXTypography.caption.weight(.bold))
                    .foregroundStyle(trend >= 0 ? PAXTheme.success : PAXTheme.danger)
            }
        }
        .padding(.horizontal, 16)
        .frame(minHeight: 72)
        .paxRevolutSurface(cornerRadius: 16, elevation: 0)
    }
}

// MARK: - Settings icon chip

struct PAXSettingsGlyph: View {
    let systemImage: String
    var tint: Color = PAXTheme.accent

    var body: some View {
        ZStack {
            RoundedRectangle(cornerRadius: 10, style: .continuous)
                .fill(tint.opacity(0.16))
            PAXIcon(systemImage, size: .card, tint: tint)
        }
        .frame(width: 36, height: 36)
    }
}

// MARK: - Section header

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
    }
}

// MARK: - Analytics donut

struct PAXRevolutDonutCard: View {
    let title: String
    let total: String
    let caption: String
    let progress: Double
    var slices: [(Color, Double)] = []

    @State private var animated: Double = 0
    @Environment(\.accessibilityReduceMotion) private var reduceMotion

    var body: some View {
        VStack(spacing: 16) {
            ZStack {
                Circle().stroke(PAXTheme.surface3, lineWidth: 14)
                Circle()
                    .trim(from: 0, to: min(max(animated, 0), 1))
                    .stroke(
                        PAXBrandGradient.linear,
                        style: StrokeStyle(lineWidth: 14, lineCap: .round)
                    )
                    .rotationEffect(.degrees(-90))
                VStack(spacing: 2) {
                    Text(total)
                        .font(PAXTypography.section)
                        .monospacedDigit()
                        .foregroundStyle(PAXTheme.textPrimary)
                    Text(caption)
                        .font(PAXTypography.meta)
                        .foregroundStyle(PAXTheme.textSecondary)
                }
            }
            .frame(width: 168, height: 168)

            if !slices.isEmpty {
                VStack(spacing: 8) {
                    ForEach(Array(slices.enumerated()), id: \.offset) { _, slice in
                        HStack {
                            Circle().fill(slice.0).frame(width: 8, height: 8)
                            Spacer()
                        }
                    }
                }
            }
        }
        .padding(20)
        .frame(maxWidth: .infinity)
        .paxRevolutSurface(cornerRadius: 20, elevation: 0)
        .onAppear {
            if reduceMotion {
                animated = progress
            } else {
                withAnimation(.easeOut(duration: 0.7)) { animated = progress }
            }
        }
    }
}

// MARK: - Sheet chrome

struct PAXRevolutSheetHandle: View {
    var body: some View {
        Capsule()
            .fill(PAXTheme.textTertiary.opacity(0.45))
            .frame(width: 36, height: 5)
            .padding(.top, 10)
            .frame(maxWidth: .infinity)
    }
}
