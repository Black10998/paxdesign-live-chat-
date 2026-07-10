import SwiftUI

/// Chat screens use plain background without shell tab-bar clearance.
extension View {
    func paxChatScreenBackground() -> some View {
        background(PAXBackground())
    }
}
