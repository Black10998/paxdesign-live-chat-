import SwiftUI

struct FullScreenImageView: View {
    let url: URL
    @Environment(\.dismiss) private var dismiss
    @State private var scale: CGFloat = 1

    var body: some View {
        NavigationStack {
            ZStack {
                Color.black.ignoresSafeArea()
                AsyncImage(url: url) { phase in
                    switch phase {
                    case .success(let image):
                        image
                            .resizable()
                            .scaledToFit()
                            .scaleEffect(scale)
                            .gesture(
                                MagnificationGesture()
                                    .onChanged { value in scale = max(1, value) }
                                    .onEnded { _ in withAnimation(PAXTheme.quickSpring) { scale = 1 } }
                            )
                    case .failure:
                        ContentUnavailableView("Bild nicht verfügbar", systemImage: "photo")
                    default:
                        ProgressView().tint(.white)
                    }
                }
            }
            .navigationBarTitleDisplayMode(.inline)
            .toolbar {
                ToolbarItem(placement: .cancellationAction) {
                    Button("Schließen") { dismiss() }
                        .foregroundStyle(.white)
                }
            }
        }
    }
}
