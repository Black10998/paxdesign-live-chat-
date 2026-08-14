import SwiftUI

// MARK: - Revolut-style screen chrome (search, hero, quick actions, grouped lists)

/// Filled search bar — Revolut `#16161F`, 44pt, pill radius.
struct PAXRevolutSearchBar: View {
    @Binding var text: String
    var placeholder: String
    @FocusState private var isFocused: Bool

    @Environment(\.colorScheme) private var colorScheme

    private var isDark: Bool { colorScheme == .dark }

    var body: some View {
        HStack(spacing: PAXSpacing.xs) {
            PAXIcon("magnifyingglass", size: .row, emphasis: .secondary)
            TextField(placeholder, text: $text)
                .font(PAXTypography.body)
                .foregroundStyle(PAXRevolutColors.textPrimary(isDark: isDark))
                .focused($isFocused)
                .textInputAutocapitalization(.never)
                .autocorrectionDisabled()
            if !text.isEmpty {
                Button {
                    text = ""
                    PAXHaptics.light()
                } label: {
                    PAXIcon("xmark.circle.fill", size: .inline, emphasis: .tertiary)
                }
                .buttonStyle(.plain)
            }
        }
        .padding(.horizontal, PAXSpacing.sm + 2)
        .frame(height: 44)
        .background(
            RoundedRectangle(cornerRadius: 22, style: .continuous)
                .fill(PAXRevolutColors.surface1(isDark: isDark))
                .overlay(
                    RoundedRectangle(cornerRadius: 22, style: .continuous)
                        .strokeBorder(
                            isFocused ? PAXTheme.accent.opacity(0.6) : PAXRevolutColors.divider(isDark: isDark),
                            lineWidth: isFocused ? 1.5 : 1
                        )
                )
        )
    }
}

/// Segmented filter chips — Revolut pill track.
struct PAXRevolutSegmentedFilter<T: Hashable>: View {
    let items: [T]
    @Binding var selection: T
    let title: (T) -> String

    @Environment(\.colorScheme) private var colorScheme
    @Namespace private var segmentNS

    var body: some View {
        ScrollView(.horizontal, showsIndicators: false) {
            HStack(spacing: PAXSpacing.xxs) {
                ForEach(items, id: \.self) { item in
                    let isSelected = selection == item
                    Button {
                        guard selection != item else { return }
                        withAnimation(.easeInOut(duration: 0.22)) { selection = item }
                        PAXHaptics.light()
                    } label: {
                        Text(title(item))
                            .font(PAXTypography.meta.weight(.semibold))
                            .foregroundStyle(isSelected ? PAXTheme.onAccent : PAXRevolutColors.textSecondary(isDark: colorScheme == .dark))
                            .padding(.horizontal, PAXSpacing.sm + 2)
                            .padding(.vertical, PAXSpacing.xs)
                            .background {
                                if isSelected {
                                    Capsule()
                                        .fill(PAXTheme.accent)
                                        .matchedGeometryEffect(id: "segment", in: segmentNS)
                                } else {
                                    Capsule()
                                        .fill(PAXRevolutColors.surface2(isDark: colorScheme == .dark))
                                }
                            }
                    }
                    .buttonStyle(PAXRevolutPressableStyle())
                }
            }
            .padding(.horizontal, 1)
        }
        .frame(height: 36)
    }
}

/// Circular quick action — Revolut 56pt action bar.
struct PAXRevolutQuickAction: View {
    let icon: String
    let title: String
    var isPrimary = false
    let action: () -> Void

    @Environment(\.colorScheme) private var colorScheme

    var body: some View {
        Button(action: {
            PAXHaptics.light()
            action()
        }) {
            VStack(spacing: PAXSpacing.xs) {
                ZStack {
                    if isPrimary {
                        Circle()
                            .fill(PAXTheme.accent)
                            .shadow(color: PAXTheme.accent.opacity(colorScheme == .dark ? 0.28 : 0.18), radius: 12, y: 6)
                    } else {
                        Circle()
                            .fill(PAXRevolutColors.surface2(isDark: colorScheme == .dark))
                    }
                    PAXIcon(icon, size: .action, tint: isPrimary ? PAXTheme.onAccent : PAXRevolutColors.textPrimary(isDark: colorScheme == .dark))
                }
                .frame(width: 56, height: 56)

                Text(title)
                    .font(PAXTypography.meta)
                    .foregroundStyle(PAXRevolutColors.textSecondary(isDark: colorScheme == .dark))
                    .lineLimit(1)
                    .minimumScaleFactor(0.8)
            }
            .frame(maxWidth: .infinity)
        }
        .buttonStyle(PAXRevolutPressableStyle())
    }
}

/// Dashboard hero — Revolut balance-style large headline.
struct PAXRevolutDashboardHero: View {
    let greeting: String
    let headline: String
    let subtitle: String

    @Environment(\.colorScheme) private var colorScheme

    var body: some View {
        VStack(alignment: .leading, spacing: PAXSpacing.sm) {
            Text(greeting.uppercased())
                .font(PAXTypography.labelUpper)
                .foregroundStyle(PAXRevolutColors.textSecondary(isDark: colorScheme == .dark))
                .tracking(0.6)

            Text(headline)
                .font(PAXTypography.balance)
                .foregroundStyle(PAXRevolutColors.textPrimary(isDark: colorScheme == .dark))
                .lineLimit(2)
                .minimumScaleFactor(0.75)

            Text(subtitle)
                .font(PAXTypography.meta)
                .foregroundStyle(PAXRevolutColors.textSecondary(isDark: colorScheme == .dark))
        }
        .frame(maxWidth: .infinity, alignment: .leading)
        .padding(.horizontal, PAXSpacing.screenHorizontal)
        .padding(.vertical, PAXSpacing.md)
    }
}

/// Grouped surface container for list rows — Revolut floating slab.
struct PAXRevolutGroupedList<Content: View>: View {
    @ViewBuilder var content: () -> Content

    @Environment(\.colorScheme) private var colorScheme

    var body: some View {
        VStack(spacing: 0) {
            content()
        }
        .clipShape(RoundedRectangle(cornerRadius: 16, style: .continuous))
        .overlay(
            RoundedRectangle(cornerRadius: 16, style: .continuous)
                .strokeBorder(PAXRevolutColors.divider(isDark: colorScheme == .dark), lineWidth: 1)
        )
        .background(
            RoundedRectangle(cornerRadius: 16, style: .continuous)
                .fill(PAXRevolutColors.surface1(isDark: colorScheme == .dark))
        )
    }
}

/// Transaction-style row with divider.
struct PAXRevolutTransactionRow: View {
    let title: String
    let subtitle: String
    let trailing: String
    var trailingColor: Color?
    var leading: AnyView?

    @Environment(\.colorScheme) private var colorScheme

    init(
        title: String,
        subtitle: String,
        trailing: String,
        trailingColor: Color? = nil,
        @ViewBuilder leading: () -> some View = { EmptyView() }
    ) {
        self.title = title
        self.subtitle = subtitle
        self.trailing = trailing
        self.trailingColor = trailingColor
        self.leading = AnyView(leading())
    }

    var body: some View {
        HStack(spacing: PAXSpacing.sm) {
            leading
            VStack(alignment: .leading, spacing: 2) {
                Text(title)
                    .font(PAXTypography.rowTitle)
                    .foregroundStyle(PAXRevolutColors.textPrimary(isDark: colorScheme == .dark))
                    .lineLimit(1)
                Text(subtitle)
                    .font(PAXTypography.meta)
                    .foregroundStyle(PAXRevolutColors.textSecondary(isDark: colorScheme == .dark))
                    .lineLimit(1)
            }
            Spacer(minLength: 0)
            Text(trailing)
                .font(PAXTypography.rowTitle)
                .monospacedDigit()
                .foregroundStyle(trailingColor ?? PAXRevolutColors.textPrimary(isDark: colorScheme == .dark))
                .lineLimit(1)
        }
        .frame(minHeight: PAXSpacing.listRowHeight)
        .padding(.horizontal, PAXSpacing.md)
        .contentShape(Rectangle())
    }
}

/// Settings row — Revolut 64pt list item.
struct PAXRevolutSettingsRow: View {
    let title: String
    let subtitle: String?
    let icon: String

    @Environment(\.colorScheme) private var colorScheme

    var body: some View {
        HStack(spacing: PAXSpacing.sm) {
            ZStack {
                RoundedRectangle(cornerRadius: 10, style: .continuous)
                    .fill(PAXRevolutColors.surface2(isDark: colorScheme == .dark))
                    .frame(width: 40, height: 40)
                PAXIcon(icon, size: .row, tint: PAXTheme.accent)
            }
            VStack(alignment: .leading, spacing: 2) {
                Text(title)
                    .font(PAXTypography.rowTitle)
                    .foregroundStyle(PAXRevolutColors.textPrimary(isDark: colorScheme == .dark))
                if let subtitle, !subtitle.isEmpty {
                    Text(subtitle)
                        .font(PAXTypography.meta)
                        .foregroundStyle(PAXRevolutColors.textSecondary(isDark: colorScheme == .dark))
                        .lineLimit(1)
                }
            }
            Spacer(minLength: 0)
            PAXIcon("chevron.right", size: .inline, emphasis: .tertiary)
        }
        .frame(minHeight: PAXSpacing.listRowHeight)
        .padding(.horizontal, PAXSpacing.md)
        .contentShape(Rectangle())
    }
}

/// Revolut-style chat composer container.
struct PAXRevolutComposerBar<Content: View>: View {
    @ViewBuilder var content: () -> Content

    @Environment(\.colorScheme) private var colorScheme

    var body: some View {
        VStack(spacing: 0) {
            Divider()
                .background(PAXRevolutColors.divider(isDark: colorScheme == .dark))
            content()
                .padding(.horizontal, PAXSpacing.sm)
                .padding(.vertical, PAXSpacing.xs)
        }
        .background {
            ZStack {
                if colorScheme == .dark {
                    Rectangle().fill(.regularMaterial)
                } else {
                    Rectangle().fill(.thinMaterial)
                }
                PAXRevolutColors.canvas(isDark: colorScheme == .dark).opacity(0.88)
            }
            .ignoresSafeArea(edges: .bottom)
        }
    }
}
