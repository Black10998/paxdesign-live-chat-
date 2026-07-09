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
                        PAXSkeletonBlock(width: 54, height: 6, cornerRadius: 5)
                            .frame(width: 74, height: 26)
                            .background(
                                Capsule(style: .continuous)
                                    .fill(PAXTheme.surface.opacity(0.68))
                            )
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
            .fill(PAXTheme.surface.opacity(0.4))
            .frame(width: 140, height: 100)
            .overlay {
                Image(systemName: "photo")
                    .font(.title3)
                    .foregroundStyle(PAXTheme.textTertiary)
            }
    }
}
