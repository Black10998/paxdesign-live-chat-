import SwiftUI

struct PAXLayoutDirectionModifier: ViewModifier {
    @ObservedObject private var settings = AppSettingsStore.shared

    func body(content: Content) -> some View {
        if let direction = settings.languageMode.layoutDirectionOverride {
            content.environment(\.layoutDirection, direction)
        } else if Locale.preferredLanguages.first?.hasPrefix("ar") == true {
            content.environment(\.layoutDirection, .rightToLeft)
        } else {
            content
        }
    }
}
