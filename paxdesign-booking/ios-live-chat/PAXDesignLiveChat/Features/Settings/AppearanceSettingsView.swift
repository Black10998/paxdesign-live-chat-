import SwiftUI

struct AppearanceSettingsView: View {
    @EnvironmentObject private var settings: AppSettingsStore
    @Environment(\.colorScheme) private var colorScheme

    private let themeColumns = [
        GridItem(.flexible(), spacing: 12),
        GridItem(.flexible(), spacing: 12)
    ]

    var body: some View {
        ScrollView {
            VStack(alignment: .leading, spacing: 22) {
                appearanceModeSection
                themeSection
                accentSection
            }
            .padding(.horizontal, 16)
            .padding(.vertical, 12)
        }
        .paxScreenBackground()
        .navigationTitle(L10n.SettingsSectionAppearance)
        .navigationBarTitleDisplayMode(.inline)
    }

    private var appearanceModeSection: some View {
        VStack(alignment: .leading, spacing: 10) {
            Text(L10n.AppearanceColorScheme)
                .font(.headline)

            HStack(spacing: 8) {
                ForEach(AppSettingsStore.AppearanceMode.allCases) { mode in
                    Button {
                        withAnimation(PAXTheme.spring) {
                            settings.appearanceMode = mode
                            PAXHaptics.light()
                        }
                    } label: {
                        Text(mode.title)
                            .font(.subheadline.weight(.semibold))
                            .foregroundStyle(settings.appearanceMode == mode ? Color.white : PAXTheme.textPrimary)
                            .frame(maxWidth: .infinity)
                            .padding(.vertical, 11)
                            .background(
                                RoundedRectangle(cornerRadius: 12, style: .continuous)
                                    .fill(settings.appearanceMode == mode ? settings.palette.accent : Color(.tertiarySystemFill))
                            )
                    }
                    .buttonStyle(.plain)
                }
            }
        }
        .padding(16)
        .paxPremiumGlass(tier: .standard, cornerRadius: 18)
    }

    private var themeSection: some View {
        VStack(alignment: .leading, spacing: 12) {
            Text(L10n.ThemeTitle)
                .font(.headline)
            Text(L10n.ThemeFooter)
                .font(.caption)
                .foregroundStyle(PAXTheme.textSecondary)

            LazyVGrid(columns: themeColumns, spacing: 12) {
                ForEach(AppSettingsStore.VisualTheme.allCases) { theme in
                    Button {
                        withAnimation(PAXTheme.spring) {
                            settings.visualTheme = theme
                            PAXHaptics.light()
                        }
                    } label: {
                        ThemePreviewCard(
                            theme: theme,
                            isSelected: settings.visualTheme == theme,
                            isDark: settings.resolvedIsDark(for: colorScheme)
                        )
                    }
                    .buttonStyle(.plain)
                }
            }
        }
        .padding(16)
        .paxPremiumGlass(tier: .standard, cornerRadius: 18)
    }

    private var accentSection: some View {
        NavigationLink {
            AccentColorSettingsView()
        } label: {
            HStack(spacing: 14) {
                ZStack {
                    Circle()
                        .fill(
                            AngularGradient(
                                colors: [settings.palette.accent, settings.palette.accentSecondary, settings.palette.accent],
                                center: .center
                            )
                        )
                        .frame(width: 42, height: 42)
                    PAXIcon("paintpalette", size: .row, emphasis: .onFill)
                }
                VStack(alignment: .leading, spacing: 3) {
                    Text(L10n.AccentColorTitle)
                        .font(.body.weight(.semibold))
                        .foregroundStyle(PAXTheme.textPrimary)
                    Text(settings.accentColorPreset.title)
                        .font(.caption)
                        .foregroundStyle(PAXTheme.textSecondary)
                }
                Spacer(minLength: 0)
                PAXIcon("chevron.right", size: .inline, emphasis: .tertiary)
            }
            .padding(16)
            .paxPremiumGlass(tier: .standard, cornerRadius: 18)
        }
        .buttonStyle(.plain)
    }
}

private struct ThemePreviewCard: View {
    let theme: AppSettingsStore.VisualTheme
    let isSelected: Bool
    let isDark: Bool

    var body: some View {
        let palette = PAXThemePalette.palette(for: theme)

        VStack(alignment: .leading, spacing: 10) {
            ZStack {
                Circle()
                    .stroke(
                        AngularGradient(
                            colors: [palette.accent, palette.accentSecondary, palette.glowTertiary, palette.accent],
                            center: .center
                        ),
                        lineWidth: isSelected ? 4 : 2.5
                    )
                    .frame(width: 54, height: 54)

                Circle()
                    .fill(palette.accent)
                    .frame(width: 22, height: 22)

                if isSelected {
                    PAXIcon("checkmark", size: .micro, emphasis: .onFill)
                        .offset(x: 18, y: -18)
                        .background(
                            Circle()
                                .fill(palette.accent)
                                .frame(width: 16, height: 16)
                                .offset(x: 18, y: -18)
                        )
                }
            }
            .frame(maxWidth: .infinity)

            VStack(alignment: .leading, spacing: 2) {
                Text(theme.title)
                    .font(.subheadline.weight(.semibold))
                    .foregroundStyle(PAXTheme.textPrimary)
                Text(theme.subtitle)
                    .font(.caption2)
                    .foregroundStyle(PAXTheme.textSecondary)
                    .lineLimit(2)
                    .minimumScaleFactor(0.85)
            }

            HStack(spacing: 6) {
                Circle().fill(palette.background(isDark: isDark)).frame(width: 12, height: 12)
                Circle().fill(palette.surface(isDark: isDark)).frame(width: 12, height: 12)
                Circle().fill(palette.accent).frame(width: 12, height: 12)
            }
        }
        .padding(12)
        .frame(maxWidth: .infinity, minHeight: 148, alignment: .topLeading)
        .background(
            RoundedRectangle(cornerRadius: 16, style: .continuous)
                .fill(palette.surface(isDark: isDark).opacity(0.55))
                .overlay(
                    RoundedRectangle(cornerRadius: 16, style: .continuous)
                        .stroke(isSelected ? palette.accent.opacity(0.65) : PAXTheme.border.opacity(0.28), lineWidth: isSelected ? 1.5 : 0.5)
                )
        )
        .accessibilityAddTraits(isSelected ? .isSelected : [])
    }
}
