import SwiftUI

struct ChatThumbnailImage: View {
    let url: URL
    let onTap: () -> Void

    var body: some View {
        Button(action: onTap) {
            AsyncImage(url: url) { phase in
                switch phase {
                case .success(let image):
                    image
                        .resizable()
                        .scaledToFit()
                        .frame(maxWidth: PAXMessageStyle.imageMaxWidth)
                case .failure:
                    placeholder
                default:
                    ZStack {
                        placeholder
                        ProgressView()
                            .scaleEffect(0.8)
                    }
                }
            }
            .frame(maxHeight: PAXMessageStyle.imageMaxHeight)
            .clipShape(RoundedRectangle(cornerRadius: PAXMessageStyle.imageCornerRadius, style: .continuous))
            .overlay(
                RoundedRectangle(cornerRadius: PAXMessageStyle.imageCornerRadius, style: .continuous)
                    .stroke(Color.white.opacity(0.08), lineWidth: 0.5)
            )
        }
        .buttonStyle(.plain)
    }

    private var placeholder: some View {
        RoundedRectangle(cornerRadius: PAXMessageStyle.imageCornerRadius, style: .continuous)
            .fill(Color.white.opacity(0.06))
            .frame(width: 140, height: 100)
            .overlay {
                Image(systemName: "photo")
                    .font(.title3)
                    .foregroundStyle(PAXTheme.textTertiary)
            }
    }
}
