import SwiftUI

struct AppearanceSettingsView: View {
    @StateObject private var settings = AppSettingsStore.shared
    @Environment(\.colorScheme) private var colorScheme

    var body: some View {
        List {
            Section {
                Picker(L10n.AppearanceTitle, selection: $settings.appearanceMode) {
                    ForEach(AppSettingsStore.AppearanceMode.allCases) { mode in
                        Text(mode.title).tag(mode)
                    }
                }
                .pickerStyle(.inline)
            } header: {
                Text(L10n.AppearanceColorScheme)
            } footer: {
                Text(L10n.AppearanceFooter)
            }

            Section {
                ForEach(AppSettingsStore.VisualTheme.allCases) { theme in
                    Button {
                        withAnimation(PAXTheme.spring) {
                            settings.visualTheme = theme
                            PAXHaptics.light()
                        }
                    } label: {
                        ThemePreviewRow(
                            theme: theme,
                            isSelected: settings.visualTheme == theme,
                            isDark: resolvedIsDark
                        )
                    }
                    .buttonStyle(.plain)
                }
            } header: {
                Text(L10n.ThemeTitle)
            } footer: {
                Text(L10n.ThemeFooter)
            }
        }
        .listStyle(.insetGrouped)
        .scrollContentBackground(.hidden)
        .background(PAXBackground())
        .navigationTitle(L10n.SettingsSectionAppearance)
        .navigationBarTitleDisplayMode(.inline)
    }

    private var resolvedIsDark: Bool {
        switch settings.appearanceMode {
        case .dark: return true
        case .light: return false
        case .system: return colorScheme == .dark
        }
    }
}

private struct ThemePreviewRow: View {
    let theme: AppSettingsStore.VisualTheme
    let isSelected: Bool
    let isDark: Bool

    var body: some View {
        let palette = PAXThemePalette.palette(for: theme)

        HStack(spacing: 14) {
            ZStack {
                RoundedRectangle(cornerRadius: 10, style: .continuous)
                    .fill(palette.background(isDark: isDark))
                    .frame(width: 52, height: 52)
                    .overlay {
                        RoundedRectangle(cornerRadius: 10, style: .continuous)
                            .fill(
                                LinearGradient(
                                    colors: [
                                        palette.glowPrimary.opacity(0.35),
                                        palette.glowSecondary.opacity(0.25)
                                    ],
                                    startPoint: .topLeading,
                                    endPoint: .bottomTrailing
                                )
                            )
                    }
                    .overlay(
                        RoundedRectangle(cornerRadius: 10, style: .continuous)
                            .stroke(palette.accent.opacity(0.35), lineWidth: 1)
                    )

                Circle()
                    .fill(palette.accent)
                    .frame(width: 14, height: 14)
                    .offset(x: 14, y: 14)
            }
            .accessibilityHidden(true)

            VStack(alignment: .leading, spacing: 3) {
                Text(theme.title)
                    .font(.body.weight(.medium))
                    .foregroundStyle(PAXTheme.textPrimary)
                Text(theme.subtitle)
                    .font(.caption)
                    .foregroundStyle(PAXTheme.textSecondary)
            }

            Spacer(minLength: 8)

            if isSelected {
                Image(systemName: "checkmark.circle.fill")
                    .foregroundStyle(PAXTheme.accent)
                    .font(.title3)
                    .accessibilityLabel(L10n.CommonActive)
            }
        }
        .padding(.vertical, 4)
        .contentShape(Rectangle())
        .accessibilityElement(children: .combine)
        .accessibilityAddTraits(isSelected ? .isSelected : [])
    }
}
