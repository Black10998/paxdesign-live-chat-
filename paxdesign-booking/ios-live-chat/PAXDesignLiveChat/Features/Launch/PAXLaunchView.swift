import SwiftUI

/// Polished native launch screen with a smooth symbol reveal.
struct PAXLaunchView: View {
    var onFinished: (() -> Void)?

    @State private var symbolScale: CGFloat = 0.82
    @State private var symbolOpacity: Double = 0
    @State private var labelOpacity: Double = 0

    var body: some View {
        ZStack {
            PAXBackground()

            VStack(spacing: 22) {
                Image(systemName: "bubble.left.and.bubble.right.fill")
                    .font(.system(size: 56))
                    .symbolRenderingMode(.hierarchical)
                    .foregroundStyle(.tint)
                    .scaleEffect(symbolScale)
                    .opacity(symbolOpacity)

                PAXInlineLoader(size: 22)
                    .opacity(labelOpacity)

                Text("PAXDesign Live Chat")
                    .font(.footnote.weight(.medium))
                    .foregroundStyle(.secondary)
                    .opacity(labelOpacity)
            }
            .padding(.horizontal, 26)
            .padding(.vertical, 24)
            .paxGlassCardStyle(cornerRadius: 22, fillOpacity: 0.78, borderOpacity: 0.42, shadowOpacity: 0.2)
        }
        .onAppear { runLaunchSequence() }
    }

    private func runLaunchSequence() {
        withAnimation(.spring(response: 0.55, dampingFraction: 0.82)) {
            symbolScale = 1
            symbolOpacity = 1
        }

        withAnimation(.easeOut(duration: 0.35).delay(0.18)) {
            labelOpacity = 1
        }

        DispatchQueue.main.asyncAfter(deadline: .now() + PAXBrand.launchDuration) {
            onFinished?()
        }
    }
}
