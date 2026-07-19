import SwiftUI

/// Compact light / dark / system switch for the top navigation bar.
struct PAXAppearanceQuickSwitch: View {
    @ObservedObject private var settings = AppSettingsStore.shared

    var body: some View {
        Menu {
            Picker(String(localized: "Appearance"), selection: $settings.appearanceMode) {
                ForEach(AppSettingsStore.AppearanceMode.allCases) { mode in
                    Label(mode.title, systemImage: icon(for: mode)).tag(mode)
                }
            }
        } label: {
            PAXIcon(iconName(for: settings.appearanceMode), size: .row)
                .frame(width: 32, height: 32)
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
        case .system: return "circle.lefthalf.filled"
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
