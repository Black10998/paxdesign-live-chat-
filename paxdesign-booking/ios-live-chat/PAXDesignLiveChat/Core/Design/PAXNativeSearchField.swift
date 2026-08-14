import SwiftUI

struct PAXNativeSearchField: View {
    @Binding var text: String
    var prompt: String
    @FocusState.Binding var isFocused: Bool

    var body: some View {
        PAXRevolutSearchBar(text: $text, prompt: prompt, isFocused: $isFocused)
    }
}
