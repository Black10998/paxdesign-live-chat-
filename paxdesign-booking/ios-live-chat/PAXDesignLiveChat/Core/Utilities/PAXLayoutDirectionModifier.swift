import SwiftUI

struct PAXLayoutDirectionModifier: ViewModifier {
    @EnvironmentObject private var settings: AppSettingsStore

    func body(content: Content) -> some View {
        if let direction = settings.languageMode.layoutDirectionOverride {
            content.environment(\.layoutDirection, direction)
        } else {
            content
        }
    }
}
