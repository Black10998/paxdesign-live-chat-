import SwiftUI

struct FullScreenImageView: View {
    let url: URL
    @Environment(\.dismiss) private var dismiss
    @State private var scale: CGFloat = 1

    var body: some View {
        ZStack(alignment: .topTrailing) {
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
                    Text(L10n.ChatImageUnavailable)
                        .foregroundStyle(.white.opacity(0.7))
                default:
                    PAXTimelineLoaderCard(status: "Bild wird geladen")
                        .frame(maxWidth: 260)
                }
            }
            .padding(.top, 48)

            Button("Schließen") { dismiss() }
                .font(.subheadline.weight(.semibold))
                .foregroundStyle(.white)
                .padding(.horizontal, 16)
                .padding(.vertical, 10)
                .padding(.top, 8)
        }
    }
}
