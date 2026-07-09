import SwiftUI

/// Minimal native launch screen — fast fade to login.
struct PAXLaunchView: View {
    var onFinished: (() -> Void)?

    var body: some View {
        ZStack {
            Color(.systemBackground)
                .ignoresSafeArea()

            VStack(spacing: 20) {
                Image(systemName: "bubble.left.and.bubble.right.fill")
                    .font(.system(size: 52))
                    .symbolRenderingMode(.hierarchical)
                    .foregroundStyle(.tint)

                ProgressView()

                Text("PAXDesign Live Chat")
                    .font(.footnote)
                    .foregroundStyle(.secondary)
            }
        }
        .onAppear {
            DispatchQueue.main.asyncAfter(deadline: .now() + PAXBrand.launchDuration) {
                onFinished?()
            }
        }
    }
}
