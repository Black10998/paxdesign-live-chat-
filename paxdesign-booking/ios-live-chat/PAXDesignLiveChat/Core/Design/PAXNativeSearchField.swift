import SwiftUI

struct PAXNativeSearchField: View {
    @Binding var text: String
    var prompt: String
    @FocusState.Binding var isFocused: Bool

    var body: some View {
        HStack(spacing: 8) {
            PAXIcon( "magnifyingglass")
                .foregroundStyle(PAXTheme.textTertiary)
                .font(.body)

            TextField(prompt, text: $text)
                .textInputAutocapitalization(.never)
                .autocorrectionDisabled()
                .focused($isFocused)

            if isFocused || !text.isEmpty {
                Button(L10n.CommonCancel) {
                    text = ""
                    isFocused = false
                    PAXKeyboard.dismiss()
                }
                .font(.body)
                .foregroundStyle(PAXTheme.accent)
                .transition(.opacity.combined(with: .scale(scale: 0.96)))
            }
        }
        .padding(.horizontal, 12)
        .padding(.vertical, 10)
        .paxGlassCardStyle(cornerRadius: 12, fillOpacity: 0.78, borderOpacity: 0.44, shadowOpacity: 0.1)
        .animation(PAXTheme.quickSpring, value: isFocused)
        .animation(PAXTheme.quickSpring, value: text.isEmpty)
    }
}
