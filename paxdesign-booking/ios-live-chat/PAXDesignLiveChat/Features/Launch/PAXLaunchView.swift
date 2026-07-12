import SwiftUI

/// Branded launch screen that matches the static iOS launch screen.
struct PAXLaunchView: View {
    var onFinished: (() -> Void)?

    @State private var contentOpacity: Double = 1

    var body: some View {
        ZStack {
            PAXBrand.launchBackground
                .ignoresSafeArea()

            VStack(spacing: 14) {
                Image("AppMark")
                    .resizable()
                    .scaledToFit()
                    .frame(width: 56, height: 56)
                    .accessibilityHidden(true)

                Text(L10n.AppName)
                    .font(.footnote.weight(.semibold))
                    .foregroundStyle(.secondary)

                PAXInlineLoader(size: 18)
                    .padding(.top, 4)
            }
            .opacity(contentOpacity)
        }
        .onAppear { runLaunchSequence() }
    }

    private func runLaunchSequence() {
        DispatchQueue.main.asyncAfter(deadline: .now() + PAXBrand.launchDuration) {
            withAnimation(.easeOut(duration: 0.22)) {
                contentOpacity = 0.92
            }
            onFinished?()
        }
    }
}
