import SwiftUI

/// Polished native launch screen with a smooth symbol reveal.
struct PAXLaunchView: View {
    var onFinished: (() -> Void)?

    @State private var symbolScale: CGFloat = 0.82
    @State private var symbolOpacity: Double = 0
    @State private var labelOpacity: Double = 0

    var body: some View {
        ZStack {
            Color(.systemBackground)
                .ignoresSafeArea()

            VStack(spacing: 22) {
                Image(systemName: "bubble.left.and.bubble.right.fill")
                    .font(.system(size: 56))
                    .symbolRenderingMode(.hierarchical)
                    .foregroundStyle(.tint)
                    .scaleEffect(symbolScale)
                    .opacity(symbolOpacity)

                ProgressView()
                    .controlSize(.regular)
                    .opacity(labelOpacity)

                Text("PAXDesign Live Chat")
                    .font(.footnote.weight(.medium))
                    .foregroundStyle(.secondary)
                    .opacity(labelOpacity)
            }
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
