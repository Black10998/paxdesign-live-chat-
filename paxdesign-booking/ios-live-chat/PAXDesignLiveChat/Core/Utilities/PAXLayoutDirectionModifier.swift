import SwiftUI

struct PAXLayoutDirectionModifier: ViewModifier {
    @ObservedObject private var settings = AppSettingsStore.shared

    func body(content: Content) -> some View {
        if let direction = settings.languageMode.layoutDirectionOverride {
            content.environment(\.layoutDirection, direction)
        } else if Locale.current.language.languageCode?.identifier == "ar" {
            content.environment(\.layoutDirection, .rightToLeft)
        } else {
            content
        }
    }
}
