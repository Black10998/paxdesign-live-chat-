import SwiftUI

/// Branded launch screen — pure black background with the original PAXdesign logo animation.
struct PAXLaunchView: View {
    var onFinished: (() -> Void)?

    @State private var logoOpacity: Double = 1
    @State private var sequenceID = UUID()

    var body: some View {
        GeometryReader { proxy in
            let markWidth = min(max(proxy.size.width * 0.44, 118), 168)

            ZStack {
                Color.black.ignoresSafeArea()

                PAXAnimatedLogoView(markWidth: markWidth)
                    .opacity(logoOpacity)
                    .id(sequenceID)
                    .environment(\.layoutDirection, .leftToRight)
            }
        }
        .onAppear { runLaunchSequence() }
    }

    private func runLaunchSequence() {
        sequenceID = UUID()
        logoOpacity = 1

        DispatchQueue.main.asyncAfter(deadline: .now() + PAXBrand.launchDuration) {
            withAnimation(.easeOut(duration: 0.28)) {
                logoOpacity = 0
            }
            DispatchQueue.main.asyncAfter(deadline: .now() + 0.28) {
                onFinished?()
            }
        }
    }
}
