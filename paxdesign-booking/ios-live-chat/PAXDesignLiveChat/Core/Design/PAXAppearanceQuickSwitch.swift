import SwiftUI

/// Compact light / dark switch for the top navigation bar.
struct PAXAppearanceQuickSwitch: View {
    @ObservedObject private var settings = AppSettingsStore.shared
    @Environment(\.colorScheme) private var colorScheme

    var body: some View {
        Menu {
            Picker(String(localized: "Appearance"), selection: $settings.appearanceMode) {
                ForEach(AppSettingsStore.AppearanceMode.selectableModes) { mode in
                    Label {
                        Text(mode.title)
                    } icon: {
                        PAXIcon(icon(for: mode), size: .inline)
                    }
                    .tag(mode)
                }
            }
        } label: {
            PAXIcon(iconName(for: settings.appearanceMode), size: .row)
                .frame(width: 32, height: 32)
                .contentShape(Rectangle())
        }
        .accessibilityLabel(String(localized: "Appearance"))
    }

    private func icon(for mode: AppSettingsStore.AppearanceMode) -> String {
        switch mode {
        case .light: return "sun.max.fill"
        case .dark: return "moon.fill"
        case .system: return "circle.lefthalf.filled"
        }
    }

    private func iconName(for mode: AppSettingsStore.AppearanceMode) -> String {
        switch mode {
        case .light: return "sun.max"
        case .dark: return "moon"
        case .system:
            return settings.resolvedIsDark(for: colorScheme) ? "moon" : "sun.max"
        }
    }
}

extension View {
    func paxAppearanceQuickSwitchToolbar() -> some View {
        toolbar {
            ToolbarItem(placement: .topBarTrailing) {
                PAXAppearanceQuickSwitch()
            }
        }
    }
}
